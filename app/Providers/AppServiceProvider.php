<?php

namespace App\Providers;

use App\Events\OrderMarkedReady;
use App\Listeners\SendOrderReadyNotification;
use App\Models\OrderItem;
use App\Notifications\Channels\SmsChannel;
use App\Observers\OrderItemObserver;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * ÉTAPE 2 — CÂBLAGE DES ÉVÉNEMENTS, OBSERVERS & CANAUX :
     * c'est ICI que la logique métier est branchée sur le framework.
     */
    public function boot(): void
    {
        // 1) OBSERVER : chaque création/changement de statut d'un article
        //    est journalisé (status_logs) et le statut de la commande
        //    parente est recalculé — sans code dans les contrôleurs.
        OrderItem::observe(OrderItemObserver::class);

        // 2) ÉVÉNEMENT → LISTENER : quand une commande devient PRÊT,
        //    la notification client (mail/SMS/database) est mise en file.
        //    (Alternative équivalente : attribut #[AsEventListener] ou
        //    tableau $listen d'un EventServiceProvider.)
        \Illuminate\Support\Facades\Event::listen(
            OrderMarkedReady::class,
            SendOrderReadyNotification::class,
        );

        // 3) CANAL SMS PERSONNALISÉ : permet d'écrire 'sms' dans la
        //    liste des canaux d'une notification (via()). Le container
        //    injecte automatiquement SmsService dans le constructeur.
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));
    }
}
