<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * MULTI-TENANT : un employé appartient à UNE agence (agency_id).
 *  - agency_id null + rôle admin = SUPER-ADMIN (vue GROUPE : toutes
 *    les agences, création d'agences, gestion des utilisateurs) ;
 *  - agency_id X = employé de l'agence X (admin local, caissier,
 *    atelier) : il ne voit QUE sa propre agence.
 */
/**
 * ÉTAPE 2 — MODÈLE User (employé du pressing).
 * Le trait HasRoles (Spatie Laravel-Permission) fournit :
 *   $user->assignRole('caissier'), $user->hasRole('admin'),
 *   $user->hasPermissionTo('orders.create')...
 * Rôles du pressing : admin | caissier | atelier (voir RoleSeeder).
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'agency_id',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Agence de rattachement (null = super-admin, vue groupe). */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** Agences possédées (self-service : le client peut en créer plusieurs). */
    public function ownedAgencies(): HasMany
    {
        return $this->hasMany(Agency::class, 'owner_id');
    }

    /** Super-admin : admin NON rattaché à une agence (vue groupe). */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->agency_id === null;
    }

    /**
     * Peut gérer le groupe (écrans Agences / Utilisateurs) :
     *  - super-admin (plateforme) : tout le monde ;
     *  - PROPRIÉTAIRE self-service : l'agence qu'il a créée + les suivantes.
     */
    public function managesGroup(): bool
    {
        return $this->isSuperAdmin() || $this->ownedAgencies()->exists();
    }

    /* -----------------------------------------------------------------
     | Helpers de rôles (sucres syntaxiques utilisés dans les vues Blade)
     | ----------------------------------------------------------------- */

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('caissier');
    }

    public function isWorkshop(): bool
    {
        return $this->hasRole('atelier');
    }
}
