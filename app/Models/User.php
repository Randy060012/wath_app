<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

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
        ];
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
