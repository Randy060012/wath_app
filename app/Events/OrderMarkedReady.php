<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ÉTAPE 2 — ÉVÉNEMENT OrderMarkedReady
 * -----------------------------------------------------------------
 * Diffusé par Order::syncStatus() quand une commande vient de passer
 * à l'état PRÊT. Découplage complet : le domaine métier ne sait PAS
 * qui écoute (SMS, e-mail, WhatsApp...), on ajoute/retire des canaux
 * sans toucher à la logique de suivi.
 */
class OrderMarkedReady
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order)
    {
    }
}
