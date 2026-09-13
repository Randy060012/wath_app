<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ÉTAPE 2 — SERVICE SMS (Twilio / Africa's Talking)
 * -----------------------------------------------------------------
 * Un seul point d'entrée pour l'envoi de SMS, paramétré par .env :
 *   SMS_PROVIDER=twilio|africas_talking|log   (log = dev/test)
 *   TWILIO_SID= / TWILIO_TOKEN= / TWILIO_FROM=
 *   AT_USERNAME= / AT_API_KEY= / AT_SENDER=
 *
 * Principe : la notification ne connaît PAS le prestataire, elle
 * appelle SmsService::send(). Le failover de prestataire se fait ici.
 * Aucune exception propagée : un SMS raté ne doit jamais bloquer
 * une opération de caisse (loggué pour réexploitation).
 */
class SmsService
{
    /**
     * Envoie un SMS de façon silencieuse (jamais d'exception vers l'appelant).
     *
     * @param  list<string>  $recipients  numéros E.164 ou locaux
     */
    public function send(array $recipients, string $message): void
    {
        try {
            match (config('services.sms.provider', 'log')) {
                'twilio'            => $this->viaTwilio($recipients, $message),
                'africas_talking'   => $this->viaAfricasTalking($recipients, $message),
                default             => $this->viaLog($recipients, $message),
            };
        } catch (\Throwable $e) {
            Log::error('SMS non envoyé : ' . $e->getMessage(), [
                'recipients' => $recipients,
            ]);
        }
    }

    /** Twilio API (TWILIO_SID/TWILIO_TOKEN/TWILIO_FROM). */
    private function viaTwilio(array $recipients, string $message): void
    {
        $sid   = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from  = (string) config('services.twilio.from');

        foreach ($recipients as $to) {
            Http::withBasicAuth($sid, $token)->asForm()->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
                ['From' => $from, 'To' => $to, 'Body' => $message]
            )->throw();
        }
    }

    /** Africa's Talking (AT_USERNAME/AT_API_KEY/AT_SENDER). */
    private function viaAfricasTalking(array $recipients, string $message): void
    {
        Http::withHeaders([
            'apiKey' => (string) config('services.africas_talking.key'),
            'Accept' => 'application/json',
        ])->asForm()->post('https://api.africastalking.com/version1/messaging', [
            'username' => (string) config('services.africas_talking.username'),
            'to'       => implode(',', $recipients),
            'message'  => $message,
            'from'     => (string) config('services.africas_talking.from'),
        ])->throw();
    }

    /** Défaut : journalisation locale (dev, tests, recette). */
    private function viaLog(array $recipients, string $message): void
    {
        Log::info('SMS [simulé] → ' . implode(', ', $recipients) . ' : ' . $message);
    }
}
