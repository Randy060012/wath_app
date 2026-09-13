<?php

namespace App\Listeners;

use App\Events\OrderMarkedReady;
use App\Notifications\OrderReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * ÉTAPE 2 — LISTENER SendOrderReadyNotification
 * -----------------------------------------------------------------
 * Écoute OrderMarkedReady et envoie la notification au client.
 * Implements ShouldQueue → l'envoi (SMS/e-mail réseau) est exécuté
 * par le worker de files (composer dev lance déjà queue:listen),
 * la caisse n'attend jamais un appel API externe.
 * Enregistrement : AppServiceProvider avec ->listenToEvents().
 */
class SendOrderReadyNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderMarkedReady $event): void
    {
        $client = $event->order->client;

        if ($client === null) {
            return;
        }

        $client->notify(new OrderReadyNotification($event->order));
    }
}
