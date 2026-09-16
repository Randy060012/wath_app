<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MULTI-TENANT — MODÈLE Agency (agence / business)
 * -----------------------------------------------------------------
 * Une ligne = un pressing (agence) exploité dans la base partagée.
 * L'admin GROUPE (super-admin, users.agency_id null) peut créer
 * autant d'agences que nécessaire ; chaque agence dispose ensuite de
 * son propre catalogue, ses clients, ses commandes et son personnel.
 */
class Agency extends Model
{
    protected $fillable = ['code', 'name', 'phone', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Employés rattachés à cette agence. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Prestations du catalogue de cette agence. */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** Clients de cette agence. */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** Commandes de cette agence (vue groupe : toutes agences). */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    /** Prochain code libre : AG-001, AG-002… (dérivé de l'ID). */
    public static function nextCode(): string
    {
        return sprintf('AG-%03d', (int) self::max('id') + 1);
    }

    /** Le catalogue a-t-il déjà été initialisé pour cette agence ? */
    public function hasCatalog(): bool
    {
        return $this->services()->exists();
    }
}
