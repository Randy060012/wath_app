<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Models\StatusLog;
use Illuminate\Support\Facades\Auth;

/**
 * ÉTAPE 2 — OBSERVER OrderItemObserver
 * -----------------------------------------------------------------
 * Centralise l'audit et la cohérence, sans surcharger les contrôleurs :
 *  - 'created' : première entrée du journal (statut initial "Reçu") ;
 *  - 'updated' : si le statut a changé → écriture dans status_logs
 *                (QUI a fait QUOI, QUAND) + recalcul du statut global
 *                de la commande parente (Order::syncStatus).
 *
 * Enregistrement dans AppServiceProvider::boot().
 */
class OrderItemObserver
{
    /** Premier journal : réception de l'article à la caisse. */
    public function created(OrderItem $item): void
    {
        $item->statusLogs()->create([
            'user_id' => Auth::id(),
            'from'    => null,
            'to'      => $item->status->value,
            'location' => $item->location,
        ]);
    }

    /** Changement de statut : audit + synchronisation de la commande. */
    public function updated(OrderItem $item): void
    {
        if (! $item->wasChanged('status')) {
            return; // seule une évolution de statut est auditée
        }

        $old = $item->getOriginal('status');

        $item->statusLogs()->create([
            'user_id' => Auth::id(),
            'from'    => $old instanceof \App\Enums\OrderStatus ? $old->value : $old,
            'to'      => $item->status->value,
            'location' => $item->location,
        ]);

        // Le statut global de la commande dérive de ses articles.
        $item->order->syncStatus();
    }
}
