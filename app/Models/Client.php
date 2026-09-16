<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * ÉTAPE 2 — MODÈLE Client (tiers commerciaux : Acteurs et Clients).
 * Relations : Client HasMany Order (dépôts) + HasMany Proforma (devis).
 * Séparé de User : le client ne s'authentifie pas, MAIS il utilise le
 * trait Notifiable pour recevoir les notifications (mail/SMS/database)
 * quand sa commande passe à l'état PRÊT.
 *
 * SPÉCIFICATIONS B — STATUT DYNAMIQUE :
 *  - "acteur"  : prospect sans aucun dépôt validé (proforma, renseignement) ;
 *  - "client"  : au moins un dépôt validé — promotion AUTOMATIQUE dans
 *    OrderService::createOrder() via promoteToClientIfActeur().
 */
class Client extends Model
{
    use Notifiable, BelongsToAgency;

    protected $fillable = [
        'code', 'name', 'phone', 'email', 'address', 'notes', 'loyalty_points', 'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
        ];
    }

    /** Historique complet des dépôts (1-N). */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    /** Devis / proformas adressés à ce tiers (spec C). */
    public function proformas(): HasMany
    {
        return $this->hasMany(Proforma::class)->latest();
    }

    /* -----------------------------------------------------------------
     | SPÉCIFICATIONS B — statut dynamique Acteur / Client
     | ----------------------------------------------------------------- */

    /**
     * PROMOTION AUTOMATIQUE Acteur → Client, appelée à chaque validation
     * de dépôt (OrderService). Idempotent : un Client reste un Client.
     * Retourne true si une promotion a eu lieu (pratique pour les tests
     * et les logs).
     */
    public function promoteToClientIfActeur(): bool
    {
        if ($this->type === ClientType::Client) {
            return false;
        }

        $this->update(['type' => ClientType::Client->value]);

        return true;
    }

    /* -----------------------------------------------------------------
     | Scopes et helpers d'affichage
     | ----------------------------------------------------------------- */

    /** Scope : uniquement les acteurs (prospects) — écran CRM. */
    public function scopeOfType($query, ClientType $type)
    {
        return $query->where('type', $type->value);
    }

    /** L'a-t-on déjà au moins un dépôt ? (utilisé par les vues) */
    public function hasOrders(): bool
    {
        return $this->orders_count > 0;
    }
}
