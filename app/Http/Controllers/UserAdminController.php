<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * MULTI-TENANT — CONTRÔLEUR UserAdminController (SUPER-ADMIN)
 * -----------------------------------------------------------------
 * Gestion des utilisateurs : création avec rattachement à une agence
 * et attribution du rôle (admin local, caissier, atelier),
 * activation/désactivation, changement de mot de passe.
 *
 * Règles d'isolation :
 *  - seul le SUPER-ADMIN accède à cet écran (route protégée) ;
 *  - le rôle 'admin' + agency_id null = super-admin (vue groupe) :
 *    ce choix est réservé à la création via CLI/seeders pour éviter
 *    les erreurs — ici, un admin créé est TOUJOURS rattaché à une agence ;
 *  - un utilisateur d'agence ne peut jamais être détaché de son agence.
 */
class UserAdminController extends Controller
{
    /** Liste des utilisateurs (toutes agences) + formulaires. */
    public function index()
    {
        // User n'a PAS le scope global par agence (il est lui-même rattaché) :
        // la route étant réservée au super-admin, la liste est volontairement complète.
        $users = User::query()
            ->with(['agency', 'roles'])
            ->orderBy('name')
            ->get();

        $agencies = Agency::query()->orderBy('name')->get();

        return view('admin.users.index', [
            'users'    => $users,
            'agencies' => $agencies,
            'roles'    => ['caissier', 'atelier', 'admin'],
        ]);
    }

    /** Crée un utilisateur (rôle + agence obligatoires, admin local = agence imposée). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'email'      => ['required', 'email', 'max:120', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:6'],
            'role'       => ['required', 'in:caissier,atelier,admin'],
            'agency_id'  => ['required', 'integer', 'exists:agencies,id'],
        ], [
            'email.unique'  => 'Cet e-mail est déjà utilisé.',
            'agency_id.required' => 'Sélectionnez l\u2019agence de rattachement.',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'agency_id' => (int) $data['agency_id'],
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);

        return back()->with('success', $data['name'] . ' ajouté à l\u2019agence avec le rôle « ' . $data['role'] . ' ».');
    }

    /** Change le rôle et/ou l'agence d'un utilisateur. */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role'      => ['required', 'in:caissier,atelier,admin'],
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
        ]);

        // Sécurité : on ne crée JAMAIS un super-admin (admin sans agence)
        // depuis l'UI — un admin reste forcément rattaché à une agence.
        // On ne déplace pas non plus le super-admin existant (protection seeders).
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Le super-administrateur ne peut pas être modifié depuis cet écran.');
        }

        $user->update(['agency_id' => (int) $data['agency_id']]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Rôle et agence mis à jour pour ' . $user->name . '.');
    }

    /** Active / désactive un compte (sans le supprimer : historique conservé). */
    public function toggle(User $user)
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Le super-administrateur ne peut pas être désactivé.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    /** Réinitialise le mot de passe d'un utilisateur. */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Mot de passe réinitialisé pour ' . $user->name . '.');
    }
}
