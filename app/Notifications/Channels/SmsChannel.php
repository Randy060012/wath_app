<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

/**
 * ÉTAPE 2 — CANAL DE NOTIFICATION 'sms' PERSONNALISÉ
 * -----------------------------------------------------------------
 * Laravel n'a pas de canal SMS natif ; on en crée un, branché sur
 * SmsService (Twilio / Africa's Talking). Enregistrement dans
 * AppServiceProvider via Notification::extend('sms', ...).
 * La notification expose simplement une méthode toSms($notifiable).
 */
class SmsChannel
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    /**
     * Point d'entrée appelé par le gestionnaire de notifications.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        // routeNotificationForSms() si défini sur le modèle, sinon ->phone
        $to = $notifiable->routeNotificationFor('sms', $notification)
            ?: ($notifiable->phone ?? null);

        if (blank($to)) {
            return; // pas de numéro → notification silencieusement ignorée
        }

        $this->sms->send([$to], $notification->toSms($notifiable));
    }
}
