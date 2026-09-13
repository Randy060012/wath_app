<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ÉTAPE 2 — NOTIFICATION "Votre commande est prête"
 * -----------------------------------------------------------------
 * Canaux choisis dynamiquement (via() ) :
 *  - 'database'   TOUJOURS : trace interne consultable en caisse ;
 *  - 'mail'       si le client a une adresse e-mail ;
 *  - 'sms' (via SmsService, Twilio / Africa's Talking) si n° de téléphone.
 *
 * Implements ShouldQueue → exécutée par le worker (aucune latence caisse).
 * Testabilité : MAIL_MAILER=array dans phpunit.xml capte les e-mails.
 */
class OrderReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order)
    {
    }

    /**
     * Canaux de diffusion, déterminés par les coordonnées disponibles.
     * Pour ajouter WhatsApp (Meta Cloud API) : créer la classe
     * Channels\WhatsAppChannel et l'ajouter dans ce tableau.
     *
     * @return list<string|object>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        if (filled($notifiable->phone)) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    /** Version e-mail (ticket + rappel des montants). */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre commande ' . $this->order->ticket_no . ' est prête !')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Votre dépôt **' . $this->order->ticket_no . '** est prêt au retrait.')
            ->line($this->order->balance_due > 0
                ? 'Solde à régler au retrait : ' . number_format($this->order->balance_due, 2) . ' '
                  . config('app.currency', 'FCFA')
                : 'Commande intégralement réglée, merci !')
            ->action('Voir le détail', url('/orders/' . $this->order->id))
            ->line('À très bientôt chez ' . config('app.name', 'Pressing Pro') . '.');
    }

    /**
     * Canal SMS personnalisé — délégué au SmsService
     * (Twilio / Africa's Talking selon config/services.php).
     */
    public function toSms(object $notifiable): string
    {
        $due = $this->order->balance_due > 0
            ? ' Solde: ' . number_format($this->order->balance_due, 0) . ' ' . config('app.currency', 'FCFA')
            : ' Solde: 0';

        return config('app.name', 'Pressing') . ' : votre commande '
            . $this->order->ticket_no . ' est prete.' . $due
            . ' Lieu: ' . config('app.address', 'notre agence');
    }

    /**
     * Canal 'database' : tableau (jsonb) stocké dans notifications.data.
     * Consulté via $client->unreadNotifications en caisse.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id'   => $this->order->id,
            'ticket_no'  => $this->order->ticket_no,
            'status'     => 'pret',
            'message'    => 'Votre commande ' . $this->order->ticket_no . ' est prête au retrait.',
            'balance_due' => $this->order->balance_due,
        ];
    }
}
