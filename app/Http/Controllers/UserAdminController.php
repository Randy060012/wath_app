<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * MULTI-TENANT SELF-SERVICE — CONTRÔLEUR UserAdminController (GROUPE)
 * -----------------------------------------------------------------
 * Gestion des utilisateurs : création avec rattachement à une agence
 * et attribution du rôle (admin local, caissier, atelier),
 * activation/désactivation, changement de mot de passe.
 *
 * Accès (middleware group-manager) :
 *  - SUPER-ADMIN (plateforme) : tous les utilisateurs de toutes les agences ;
 *  - PROPRIÉTAIRE self-service : UNIQUEMENT les utilisateurs rattachés
 *    aux agences QU'IL POSSÈDE (agencies.owner_id = son id).
 *
 * Règles d'isolation (inchangées) :
 *  - le rôle 'admin' + agency_id null = super-admin (vue groupe) :
 *    ce choix est réservé à la création via CLI/seeders pour éviter
 *    les erreurs — ici, un admin créé est TOUJOURS rattaché à une agence ;
 *  - un utilisateur d'agence ne peut jamais être détaché de son agence.
 */
class UserAdminController extends Controller
{
    /** Liste des utilisateurs (périmètre du gestionnaire) + formulaires. */
    public function index()
    {
        // User n'a PAS le scope global par agence (il est lui-même rattaché) :
        // le périmètre est calculé ici (super-admin = tout, owner = ses agences).
        $users = $this->visibleUsers()
            ->with(['agency', 'roles'])
            ->orderBy('name')
            ->get();

        $agencies = $this->visibleAgencies();

        return view('admin.users.index', [
            'users'    => $users,
            'agencies' => $agencies,
            'roles'    => ['caissier', 'atelier', 'admin'],
        ]);
    }

    /** Crée un utilisateur (rôle + agence obligatoires, dans le périmètre). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'email'      => ['required', 'email', 'max:120', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:6'],
            'role'       => ['required', 'in:caissier,atelier,admin'],
            'agency_id'  => ['required', 'integer'],
        ], [
            'email.unique'  => 'Cet e-mail est déjà utilisé.',
            'agency_id.required' => 'Sélectionnez l\'agence de rattachement.',
        ]);

        $user = $request->user();

        // Sécurité self-service : le propriétaire ne rattache que SES agences.
        if (! $user->isSuperAdmin() && ! $user->ownedAgencies()->whereKey((int) $data['agency_id'])->exists()) {
            return back()->with('error', 'Cette agence ne fait pas partie de votre groupe.');
        }

        $newUser = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'agency_id' => (int) $data['agency_id'],
            'is_active' => true,
        ]);

        $newUser->assignRole($data['role']);

        return back()->with('success', $data['name'] . ' ajouté à l\'agence avec le rôle « ' . $data['role'] . ' ».');
    }

    /** Change le rôle et/ou l'agence d'un utilisateur (périmètre seulement). */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role'      => ['required', 'in:caissier,atelier,admin'],
            'agency_id' => ['required', 'integer'],
        ]);

        $manager = $request->user();

        // Sécurité : on ne crée JAMAIS un super-admin (admin sans agence)
        // depuis l'UI — un admin reste forcément rattaché à une agence.
        // On ne déplace pas non plus le super-admin existant (protection seeders).
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Le super-administrateur ne peut pas être modifié depuis cet écran.');
        }

        // Sécurité self-service : l'utilisateur ciblé ET l'agence de
        // destination doivent appartenir au groupe du gestionnaire.
        if (! $manager->isSuperAdmin() && (
            ! $manager->ownedAgencies()->whereKey($user->agency_id)->exists()
            || ! $manager->ownedAgencies()->whereKey((int) $data['agency_id'])->exists()
        )) {
            abort(403, 'Cet utilisateur ne fait pas partie de votre groupe.');
        }

        $user->update(['agency_id' => (int) $data['agency_id']]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Rôle et agence mis à jour pour ' . $user->name . '.');
    }

    /** Active / désactive un compte (sans le supprimer : historique conservé). */
    public function toggle(User $user)
    {
        $manager = request()->user();

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Le super-administrateur ne peut pas être désactivé.');
        }

        if ($user->id === $manager->id) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        // Sécurité self-service : uniquement les users de SON groupe.
        if (! $manager->isSuperAdmin() && ! $manager->ownedAgencies()->whereKey($user->agency_id)->exists()) {
            abort(403, 'Cet utilisateur ne fait pas partie de votre groupe.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    /** Réinitialise le mot de passe d'un utilisateur (périmètre seulement). */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $manager = $request->user();

        if ($user->isSuperAdmin() && ! $manager->isSuperAdmin()) {
            abort(403, 'Seul le super-administrateur peut modifier ce compte.');
        }

        if (! $manager->isSuperAdmin() && ! $manager->ownedAgencies()->whereKey($user->agency_id)->exists()) {
            abort(403, 'Cet utilisateur ne fait pas partie de votre groupe.');
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Mot de passe réinitialisé pour ' . $user->name . '.');
    }

    /* -----------------------------------------------------------------
     | Helpers de périmètre (super-admin = tout, owner = SES agences)
     | ----------------------------------------------------------------- */

    /** Requête des utilisateurs visibles par le gestionnaire courant. */
    private function visibleUsers(): Builder
    {
        $user = request()->user();

        if ($user->isSuperAdmin()) {
            return User::query();
        }

        $ownedIds = $user->ownedAgencies()->pluck('id');

        return User::query()->whereIn('agency_id', $ownedIds);
    }

    /** Agences sélectionnables (super-admin = toutes, owner = les siennes). */
    private function visibleAgencies()
    {
        $user = request()->user();

        if ($user->isSuperAdmin()) {
            return Agency::query()->orderBy('name')->get();
        }

        return $user->ownedAgencies()->orderBy('name')->get();
    }
}
