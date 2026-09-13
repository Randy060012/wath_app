<?php

namespace App\Mail;

use App\Models\Proforma;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SPÉCIFICATIONS C.1 — E-MAIL DE TRANSMISSION D'UN PROFORMA.
 * Le corps du mail reprend la mise en page du document imprimable :
 * le destinataire peut l'imprimer en PDF depuis son client mail.
 */
class ProformaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Proforma $proforma)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre devis ' . $this->proforma->number . ' — ' . config('app.name', 'Pressing Pro'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.proforma',
            with: ['proforma' => $this->proforma->load(['client', 'items'])],
        );
    }
}
