<?php

namespace App\Models;

use App\Enums\ProformaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SPÉCIFICATIONS C — MODÈLE Proforma (devis / facture proforma).
 * Document commercial NON comptable : décrit des prestations et un
 * tarif estimé, adressé à un Acteur ou un Client. Ne touche ni au
 * stock, ni aux code-barres, ni aux encaissements.
 * Numérotation lisible P-YYYY-000NNN dérivée de l'ID (zéro contention).
 */
class Proforma extends Model
{
    protected $fillable = [
        'number', 'client_id', 'user_id', 'status', 'total_amount',
        'discount_amount', 'issued_at', 'valid_until', 'converted_order_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'     => ProformaStatus::class,
            'issued_at'  => 'date',
            'valid_until' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Employé ayant émis le document. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProformaItem::class);
    }

    /** Dépôt réel issu de la conversion (null si pas encore converti). */
    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    /* -----------------------------------------------------------------
     | Montants (même logique que Order : total, remise, net)
     | ----------------------------------------------------------------- */

    public function getNetAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->discount_amount);
    }

    /**
     * Statut AFFICHÉ : un devis « envoyé » dont la validité est dépassée
     * s'affiche « Expiré » (sans réécrire la base — le statut stocké
     * reste Envoyé, l'expiration est déduite à la lecture).
     */
    public function getDisplayStatusAttribute(): ProformaStatus
    {
        if ($this->status === ProformaStatus::Envoye
            && $this->valid_until !== null
            && $this->valid_until->isPast()) {
            return ProformaStatus::Expire;
        }

        return $this->status;
    }

    /** Version imprimable / mail : libellé du statut réellement affiché. */
    public function statusLabel(): string
    {
        return $this->display_status->label();
    }

    /* -----------------------------------------------------------------
     | Cycle de vie
     | ----------------------------------------------------------------- */

    /** Marque le proforma comme envoyé (avec ou sans e-mail réel). */
    public function markSent(): void
    {
        if (in_array($this->status, [ProformaStatus::Brouillon, ProformaStatus::Refuse], true)) {
            $this->update(['status' => ProformaStatus::Envoye]);
        }
    }

    /** Acceptation / refus par le tiers. */
    public function markAccepted(): void
    {
        $this->update(['status' => ProformaStatus::Accepte]);
    }

    public function markRefused(): void
    {
        $this->update(['status' => ProformaStatus::Refuse]);
    }

    /** Proforma converti en dépôt réel (spec C.2) : mémorise le lien. */
    public function markConvertedTo(Order $order): void
    {
        $this->markAccepted();
        $this->update(['converted_order_id' => $order->id]);
    }
}
