<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ÉTAPE 2 — CONTRÔLEUR WorkshopController (écran ATELIER)
 * -----------------------------------------------------------------
 * Cœur du suivi de production :
 *  - board() : vue kanban (Reçu / En cours / Repassé / Prêt) + champ scan ;
 *  - scan()  : traitement d'un code-barres scanné (douchette USB = clavier).
 * Les changements de statut passent par markStatus() (machine à états),
 * l'audit et la synchro commande sont automatiques (Observer + Event).
 */
class WorkshopController extends Controller
{
    /** Vue atelier : kanban par statut + compteurs. */
    public function board()
    {
        $columns = [];

        foreach (OrderStatus::cases() as $status) {
            if (in_array($status, [OrderStatus::Livre, OrderStatus::Perdu], true)) {
                continue;
            }

            $columns[$status->value] = [
                'label' => $status->label(),
                'badge' => $status->badgeClass(),
                // TOUTES les cartes : un article caché est un article oublié.
                // Le conteneur défile (max-h + scroll dans la vue).
                'items' => OrderItem::query()
                    ->where('status', $status->value)
                    ->with(['order.client', 'service'])
                    ->latest()
                    ->get(),
                'total' => OrderItem::query()->where('status', $status->value)->count(),
            ];
        }

        return view('workshop.board', ['byStatus' => $columns]);
    }

    /**
     * Traitement d'un scan : trouve l'article et applique la transition
     * de statut demandée (machine à états du workflow atelier).
     */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'barcode'    => ['required', 'string', 'max:32'],
            'new_status' => ['required', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'location'   => ['nullable', 'string', 'max:120'],
        ], [
            'barcode.required' => 'Scannez ou saisissez un code article.',
            'new_status.in'    => 'Statut cible invalide.',
        ]);

        $item = OrderItem::query()
            ->where('barcode', $data['barcode'])
            ->with(['order.client', 'service'])
            ->first();

        if ($item === null) {
            $message = 'Code article inconnu : ' . $data['barcode'];

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $message], 404)
                : back()->with('error', $message);
        }

        try {
            // Transaction : le changement de statut est atomique.
            DB::transaction(function () use ($item, $data) {
                $item->markStatus(
                    OrderStatus::from($data['new_status']),
                    $data['location'] ?? null,
                );
            });
        } catch (\InvalidArgumentException $e) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        // Réponse JSON : le JS met à jour le kanban sans rechargement.
        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $item->barcode . ' : ' . $item->status->label(),
                'item'    => [
                    'id'        => $item->id,
                    'barcode'   => $item->barcode,
                    'status'    => $item->status->value,
                    'label'     => $item->status->label(),
                    'location'  => $item->location,
                    'order_url' => route('orders.show', $item->order_id),
                ],
            ]);
        }

        return back()->with('success', $item->barcode . ' : ' . $item->status->label());
    }
}
