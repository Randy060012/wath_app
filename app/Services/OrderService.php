<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * ÉTAPE 3 — SERVICE MÉTIER OrderService (classe d'action)
 * -----------------------------------------------------------------
 * Toute la logique de création d'un dépôt est centralisée ICI et non
 * dans le contrôleur : le contrôleur valide (FormRequest), délègue,
 * redirige. Avantages : réutilisable (autre contrôleur, commande
 * artisan), testable unitairement, et surtout ATOMICITÉ claire.
 *
 * Responsabilités :
 *  1. Générer un numéro de ticket unique         (T-2026-000123)
 *  2. Générer un code-barres unique PAR ARTICLE  (BC-2026-000001)
 *  3. Calculer les montants (fige le prix catalogue, gère l'express)
 *  4. Enregistrer l'acompte éventuel
 *  5. Le tout dans UNE transaction SQL : tout ou rien.
 */
class OrderService
{
    public function __construct(private readonly BarcodeService $barcodes)
    {
    }

    /**
     * Crée une commande complète à partir des données validées.
     *
     * @param  array{
     *     client_id: int,
     *     items: array<int, array{service_id: int, quantity: int, description?: ?string}>,
     *     discount_amount?: float,
     *     is_express?: bool,
     *     promised_at?: ?string,
     *     notes?: ?string,
     *     deposit_amount?: float,
     *     deposit_method?: ?string
     * } $data
     *
     * @throws \Throwable (rollback automatique en cas d'erreur)
     */
    public function createOrder(array $data, int $cashierId): Order
    {
        // ------------------------------------------------------------------
        // TRANSACTION SQL : commande + lignes + code-barres + acompte sont
        // inséparables. Si UNE insertion échoue, TOUT est annulé : jamais
        // de ticket sans articles ni d'article sans ticket en base.
        // ------------------------------------------------------------------
        return DB::transaction(function () use ($data, $cashierId) {
            $client = Client::findOrFail($data['client_id']);

            // Prix catalogue FRAIS (pas ceux postés par le navigateur !)
            $services = Service::whereIn(
                'id',
                collect($data['items'])->pluck('service_id')
            )->get()->keyBy('id');

            // --- 1) En-tête de commande --------------------------------------
            // On insère d'abord pour obtenir l'ID auto-incrémenté, puis on
            // calcule le numéro de ticket définitif à partir de cet ID :
            // pas de table de séquences à verrouiller, pas de contention.
            $order = Order::create([
                'ticket_no'       => 'TMP-' . uniqid(), // placeholder provisoire
                'client_id'       => $client->id,
                'user_id'         => $cashierId,
                'status'          => OrderStatus::Recu,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'is_express'      => $data['is_express'] ?? false,
                'promised_at'     => $data['promised_at'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            $order->update(['ticket_no' => $this->ticketNumberFor($order->id)]);

            // --- 2) Lignes + étiquettes code-barres --------------------------
            $total = 0.0;

            foreach ($data['items'] as $line) {
                $service = $services[$line['service_id']]
                    ?? throw new \InvalidArgumentException('Prestation inconnue.');

                $unitPrice = $this->effectivePrice($service, (bool) ($data['is_express'] ?? false));
                $qty       = max(1, (int) $line['quantity']);
                $lineTotal = round($unitPrice * $qty, 2);
                $total    += $lineTotal;

                // Quantity > 1 et articles individualisables → on crée
                // autant d'articles (donc d'étiquettes) que d'unités, SAUF
                // pour les prestations au kilo (1 ligne suffit).
                $copies = $service->pricing_unit === 'kg' ? 1 : $qty;

                for ($i = 0; $i < $copies; $i++) {
                    $order->items()->create([
                        'service_id'  => $service->id,
                        // Chaque article reçoit son code unique, généré
                        // par BarcodeService (voir plus bas).
                        'barcode'     => $this->barcodes->next(),
                        'description' => $line['description'] ?? null,
                        'status'      => OrderStatus::Recu,
                        'unit_price'  => $service->pricing_unit === 'kg'
                            ? $unitPrice
                            : round($lineTotal / $qty, 2),
                        'quantity'    => $service->pricing_unit === 'kg' ? $qty : 1,
                        'line_total'  => $service->pricing_unit === 'kg'
                            ? $lineTotal
                            : round($lineTotal / $qty, 2),
                    ]);
                }
            }

            // --- 3) Totaux (remise + surcharge express déjà dans le prix) ----
            $order->update(['total_amount' => round($total, 2)]);

            // --- 4) Acompte éventuel (versement à la création) ---------------
            $deposit = (float) ($data['deposit_amount'] ?? 0);
            if ($deposit > 0) {
                $this->addPayment(
                    $order,
                    min($deposit, $order->net_amount), // jamais plus que le dû
                    PaymentMethod::from($data['deposit_method'] ?? 'cash'),
                    $cashierId,
                );
            }

            // Points fidélité : 1 point par tranche de 500 payés
            $client->increment('loyalty_points', (int) floor($order->net_amount / 500));

            // SPÉCIFICATIONS B — PROMOTION AUTOMATIQUE : la validation du
            // dépôt transforme un Acteur (prospect) en Client. Idempotent.
            $client->promoteToClientIfActeur();

            return $order->refresh(); // recharge items + payments pour l'impression
        });
    }

    /**
     * ACTION ATELIER — passe tous les articles d'une commande à "Prêt".
     * Utilisée par le menu contextuel de la liste des dépôts.
     *
     * Règles métier :
     *  - la commande doit être ouverte (jamais sur une commande livrée) ;
     *  - chaque article avance par la machine à états en respectant les
     *    transitions légales (Reçu → En cours → Repassé → Prêt) : l'atelier
     *    ne "saute" jamais d'étapes, même depuis un raccourci UI ;
     *  - le tout dans UNE transaction : audit (status_logs via l'Observer),
     *    statut global dérivé et notification client (Event Prêt) sont
     *    atomiques avec les changements de statut.
     *
     * @throws \InvalidArgumentException si la commande n'est pas éligible
     */
    public function markReady(Order $order): void
    {
        $order->load('items');

        if ($order->status === OrderStatus::Livre) {
            throw new \InvalidArgumentException('Commande déjà livrée : impossible de la marquer prête.');
        }
        if ($order->items->isEmpty()) {
            throw new \InvalidArgumentException('Commande sans article : rien à marquer prêt.');
        }

        // Chemin légal Reçu → En cours → Repassé → Prêt (1 cran = 1 transition).
        $path = [
            OrderStatus::Recu->value    => [OrderStatus::EnCours, OrderStatus::Repasse, OrderStatus::Pret],
            OrderStatus::EnCours->value => [OrderStatus::Repasse, OrderStatus::Pret],
            OrderStatus::Repasse->value => [OrderStatus::Pret],
            OrderStatus::Pret->value    => [],
        ];

        // Idempotent : une commande déjà entièrement "Prêt" ne change pas
        // (aucune transition, donc pas de notification dupliquée).
        DB::transaction(function () use ($order, $path) {
            foreach ($order->items as $item) {
                foreach ($path[$item->status->value] ?? [] as $step) {
                    $item->markStatus($step); // valide + journalise (Observer)
                }
            }

            $order->refresh();
        });
    }

    /**
     * Enregistre un encaissement (acompte à la création ou solde au retrait).
     */
    public function addPayment(Order $order, float $amount, PaymentMethod $method, int $userId, ?string $reference = null): void
    {
        $order->payments()->create([
            'user_id'   => $userId,
            'amount'    => $amount,
            'method'    => $method->value,
            'reference' => $reference,
        ]);
    }

    /**
     * Prix effectif d'une prestation : prix catalogue + surcharge express.
     */
    private function effectivePrice(Service $service, bool $express): float
    {
        $price = (float) $service->price;

        return $express
            ? round($price * (1 + (int) config('pressing.express_surcharge', 25) / 100), 2)
            : $price;
    }

    /**
     * ÉTAPE 3 — NUMÉRO DE TICKET UNIQUE ET LISIBLE : T-<année>-<ID sur 6 chiffres>
     * (ex: T-2026-000123). Dérivé de l'ID auto-incrémenté → unicité garantie
     * par l'index UNIQUE, zéro contention entre caissiers simultanés.
     */
    private function ticketNumberFor(int $orderId): string
    {
        return sprintf('T-%s-%06d', now()->format('Y'), $orderId);
    }
}
