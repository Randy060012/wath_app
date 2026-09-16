<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * ÉTAPE 2 — CONTRÔLEUR OrderController
 * -----------------------------------------------------------------
 * Méthodes :
 *  - index  : liste filtrée (statut, recherche ticket/client)
 *  - create : écran de dépôt (caisse)
 *  - store  : validation FormRequest → OrderService (transaction SQL)
 *  - show   : fiche ticket + timeline des articles
 *  - settle : encaissement du solde au retrait
 * Le contrôleur reste MINCE : il valide, délègue, redirige.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /**
     * Liste des commandes — SANS pagination serveur : toutes les lignes
     * sont envoyées à la vue et le DataTable côté navigateur
     * (public/assets/js/datatable.js) gère recherche, filtre par statut,
     * tri par colonne et pagination. Plus besoin des filtres GET Blade.
     */
    public function index()
    {
        $orders = Order::query()
            ->with(['client', 'items'])
            ->latest()
            ->get();

        return view('orders.index', ['orders' => $orders]);
    }

    /**
     * Écran de dépôt : catalogue + panier + recherche client live.
     * Le catalogue est aplati (category_name) pour le composant Alpine.
     * SPÉCIFICATIONS A — ?client=ID pré-sélectionne le tiers (depuis sa
     * fiche) ; le composant Alpine reçoit ce client déjà "picked".
     */
    public function create(Request $request)
    {
        $services = \App\Models\Service::query()
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id'            => $s->id,
                'name'          => $s->name,
                'price'         => (float) $s->price,
                'pricing_unit'  => $s->pricing_unit,
                'category_id'   => $s->category_id,
                'category_name' => $s->category?->name,
            ]);

        // Pré-sélection : /orders/create?client=12 (lien "Nouveau dépôt"
        // de la fiche client) — évite de research le client à la main.
        $selectedClient = null;
        if ($request->filled('client')) {
            $c = \App\Models\Client::find($request->integer('client'));
            $selectedClient = $c ? [
                'id' => $c->id, 'name' => $c->name, 'phone' => $c->phone,
                'address' => $c->address, 'type' => $c->type->value,
            ] : null;
        }

        return view('orders.create', [
            'services'       => $services,
            'selectedClient' => $selectedClient,
        ]);
    }

    /**
     * Enregistre le dépôt : délégation complète au service métier
     * (transaction, ticket, codes-barres, acompte) — voir OrderService.
     */
    public function store(StoreOrderRequest $request)
    {
        $order = $this->orders->createOrder(
            $request->validated(),
            $request->user()->id,
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Dépôt ' . $order->ticket_no . ' enregistré. Imprimez le ticket et les étiquettes.');
    }

    /**
     * Fiche ticket : articles, statuts, paiements, timeline.
     */
    public function show(Order $order)
    {
        $order->load(['client', 'items.service', 'payments', 'cashier', 'items.statusLogs.user']);

        return view('orders.show', ['order' => $order]);
    }

    /**
     * ACTION EXPRESS "Marquer prêt" (menu contextuel de la liste des dépôts) :
     * délègue au service métier — machine à états respectée article par
     * article, audit + notification client déclenchés comme un scan atelier.
     */
    public function markReady(Request $request, Order $order)
    {
        try {
            $this->orders->markReady($order);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Commande ' . $order->ticket_no . ' marquée prête. Le client est notifié.');
    }

    /**
     * PAIEMENT PARTIEL (acompte supplémentaire, SANS livraison) :
     * encaisse une part du solde restant et laisse la commande ouverte.
     * Utilisé quand le client règle « une partie maintenant, le reste
     * au retrait » — plusieurs versements sont possibles.
     *
     * Le garde-fou serveur : jamais plus que le solde dû (anti-paiement
     * négatif / excédentaire accidentel).
     */
    public function pay(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', 'in:cash,mobile_money,card'],
            'reference' => ['nullable', 'string', 'max:60'],
        ], [
            'amount.required' => 'Le montant est obligatoire.',
            'amount.min'      => 'Le montant doit être supérieur à zéro.',
            'method.required' => 'Le moyen de paiement est obligatoire.',
        ]);

        $balanceDue = $order->balance_due;

        if ($balanceDue <= 0) {
            return back()->with('error', 'Cette commande est déjà intégralement réglée.');
        }

        $amount = min((float) $data['amount'], $balanceDue); // jamais plus que le dû

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order, $amount, $data) {
            $this->orders->addPayment(
                $order,
                $amount,
                PaymentMethod::from($data['method']),
                $request->user()->id,
                $data['reference'] ?? null,
            );
        });

        $remaining = $order->refresh()->balance_due;

        return back()->with(
            'success',
            $remaining > 0
                ? 'Paiement de ' . number_format($amount, 0, ',', ' ') . ' ' . config('pressing.currency', 'FCFA')
                    . ' enregistré. Reste dû : ' . number_format($remaining, 0, ',', ' ') . '.'
                : 'Commande ' . $order->ticket_no . ' intégralement réglée.'
        );
    }

    /**
     * RETRAIT : encaisse le solde restant et marque la commande LIVRÉE
     * (tous les articles passent à "Livré" → l'Observer synchronise).
     */
    public function settle(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0'],
            'method'   => ['required', 'in:cash,mobile_money,card'],
            'reference' => ['nullable', 'string', 'max:60'],
        ], [
            'amount.required' => 'Le montant est obligatoire.',
            'method.required' => 'Le moyen de paiement est obligatoire.',
        ]);

        // Transaction : encaissement + livraison sont inséparables.
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order, $data) {
            if ($data['amount'] > 0) {
                $this->orders->addPayment(
                    $order,
                    (float) $data['amount'],
                    PaymentMethod::from($data['method']),
                    $request->user()->id,
                    $data['reference'] ?? null,
                );
            }

            foreach ($order->items as $item) {
                if ($item->status !== OrderStatus::Livre) {
                    $item->markStatus(OrderStatus::Livre);
                }
            }

            $order->update(['delivered_at' => now()]);
        });

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Retrait effectué. Commande ' . $order->ticket_no . ' livrée.');
    }
}
