<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ProformaStatus;
use App\Models\Proforma;
use App\Models\ProformaItem;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * SPÉCIFICATIONS C — SERVICE MÉTIER ProformaService
 * -----------------------------------------------------------------
 * Toute la logique documentaire des devis est centralisée ici
 * (même philosophie que OrderService : contrôleur mince, transaction
 * SQL, règles métier testables) :
 *   1. create()      : proforma + lignes dans UNE transaction, prix
 *                      figés depuis le catalogue (jamais ceux du
 *                      navigateur) ;
 *   2. send()        : e-mail au tiers (PDF/HTML) + passage ENVOYÉ ;
 *   3. setStatus()   : accepté / refusé (workflow commercial) ;
 *   4. convertToOrder() : transforme un devis accepté en dépôt réel
 *                      (réutilise OrderService — code-barres, ticket,
 *                      promotion Acteur→Client automatique).
 */
class ProformaService
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /**
     * Crée un proforma avec ses lignes. Les prix VIENNENT DU CATALOGUE
     * (service_id présent) sauf ligne libre (label seul, prix saisi et
     * validé côté serveur).
     *
     * @param  array{client_id:int, items:array<int, array{service_id?:int, label?:string, service_id_new?:mixed, quantity:int, unit_price?:float, pricing_unit?:string}>, discount_amount?:float, valid_days?:int, notes?:string} $data
     * @throws \Throwable
     */
    public function create(array $data, int $userId): Proforma
    {
        return DB::transaction(function () use ($data, $userId) {
            // --- 1) En-tête (numéro provisoire, finalisé après insert) ---
            $proforma = Proforma::create([
                'number'          => 'TMP-' . uniqid(),
                'client_id'       => $data['client_id'],
                'user_id'         => $userId,
                'status'          => ProformaStatus::Brouillon,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'issued_at'       => now(),
                'valid_until'     => now()->addDays((int) ($data['valid_days'] ?? 15)),
                'notes'           => $data['notes'] ?? null,
            ]);

            $proforma->update(['number' => $this->numberFor($proforma->id)]);

            // --- 2) Lignes : prix catalogue figés -----------------------
            $serviceIds = collect($data['items'])
                ->pluck('service_id')
                ->filter()
                ->unique()
                ->values();

            $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

            $total = 0.0;
            foreach ($data['items'] as $line) {
                if (!empty($line['service_id'])) {
                    $service = $services[$line['service_id']]
                        ?? throw new \InvalidArgumentException('Prestation inconnue.');
                    $label  = $service->name;
                    $unit   = $service->pricing_unit;
                    $price  = (float) $service->price;
                } else {
                    // Ligne libre : libellé + prix soumis, validés ici
                    $label  = trim((string) ($line['label'] ?? ''));
                    $label  = $label !== '' ? $label : throw new \InvalidArgumentException('Ligne sans libellé.');
                    $unit   = in_array(($line['pricing_unit'] ?? 'piece'), ['piece', 'kg', 'forfait'], true)
                        ? $line['pricing_unit'] : 'piece';
                    $price  = max(0, (float) ($line['unit_price'] ?? 0));
                }

                $qty  = max(1, (int) ($line['quantity'] ?? 1));
                $lineTotal = round($price * $qty, 2);
                $total += $lineTotal;

                $proforma->items()->create([
                    'service_id'   => $line['service_id'] ?? null,
                    'label'        => $label,
                    'pricing_unit' => $unit,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                    'line_total'   => $lineTotal,
                ]);
            }

            $proforma->update(['total_amount' => round($total, 2)]);

            return $proforma->refresh(); // recharge items pour l'affichage
        });
    }

    /**
     * SPÉCIFICATIONS C.1 — ENVOI du proforma par e-mail au tiers
     * (le HTML du mail est optimisé impression → PDF par le client mail).
     * Si le tiers n'a pas d'e-mail, on passe juste ENVOYÉ (remise papier).
     */
    public function send(Proforma $proforma, int $userId): bool
    {
        $proforma->load(['client', 'items', 'author']);

        $emailed = false;
        if ($proforma->client->email) {
            Mail::to($proforma->client->email)->send(
                new \App\Mail\ProformaMail($proforma)
            );
            $emailed = true;
        }

        $proforma->markSent();

        return $emailed;
    }

    /** Workflow commercial : accepté ou refusé. */
    public function setStatus(Proforma $proforma, ProformaStatus $status, ?string $reason = null): void
    {
        if (!in_array($status, [ProformaStatus::Accepte, ProformaStatus::Refuse], true)) {
            throw new \InvalidArgumentException('Statut non autorisé pour cette action.');
        }

        if ($reason !== null && $reason !== '') {
            $proforma->update([
                'notes' => trim(($proforma->notes ? $proforma->notes . "\n" : '')
                    . '[' . now()->format('d/m/Y') . '] ' . $reason),
            ]);
        }

        $status === ProformaStatus::Accepte
            ? $proforma->markAccepted()
            : $proforma->markRefused();
    }

    /**
     * SPÉCIFICATIONS C.2 — CONVERSION en dépôt réel : crée la commande
     * via OrderService (transaction, codes-barres, acompte éventuel) puis
     * lie le proforma à la commande (statut ACCEPTÉ + converted_order_id).
     *
     * @throws \Throwable
     */
    public function convertToOrder(Proforma $proforma, float $depositAmount, PaymentMethod $method, int $userId)
    {
        $proforma->load('items');

        if ($proforma->converted_order_id !== null) {
            throw new \InvalidArgumentException('Ce proforma a déjà été converti en dépôt.');
        }

        // Workflow : seul un devis ACCEPTÉ peut devenir un dépôt réel
        // (un brouillon n'a jamais été soumis au tiers).
        if ($proforma->status !== ProformaStatus::Accepte) {
            throw new \InvalidArgumentException('Seul un proforma accepté peut être converti en dépôt.');
        }

        // Mappe les lignes proforma vers les items de commande.
        // Les lignes LIBRES (sans service_id) ne peuvent pas devenir des
        // articles suivis : elles sont signalées au caissier.
        $items = $proforma->items
            ->filter(fn ($i) => $i->service_id !== null)
            ->map(fn ($i) => [
                'service_id'  => $i->service_id,
                'quantity'    => $i->quantity,
                'description' => $i->label,
            ])
            ->values();

        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('Aucune ligne catalogue convertible : créez le dépôt manuellement.');
        }

        $order = $this->orders->createOrder([
            'client_id'      => $proforma->client_id,
            'items'          => $items->all(),
            'discount_amount' => $proforma->discount_amount,
            'deposit_amount' => $depositAmount,
            'deposit_method' => $method->value,
            'notes'          => 'Suite au proforma ' . $proforma->number,
        ], $userId);

        $proforma->markConvertedTo($order);

        return $order;
    }

    /** Numéro lisible : P-<année>-<ID sur 6 chiffres> (zéro contention). */
    private function numberFor(int $id): string
    {
        return sprintf('P-%s-%06d', now()->format('Y'), $id);
    }
}
