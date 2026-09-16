<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use App\Services\AgencyService;
use App\Support\AgencyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * INSCRIPTION SELF-SERVICE (nouveau client de la plateforme)
 * -----------------------------------------------------------------
 * Un visiteur qui n'a pas de compte crée ici :
 *  1. SON COMPTE utilisateur — il devient le PROPRIÉTAIRE de son
 *     groupe d'agences (agencies.owner_id) : il peut ensuite ajouter
 *     d'autres agences et gérer les utilisateurs de son groupe ;
 *  2. SA PREMIÈRE AGENCIE (son business) — catalogue initial copié
 *     automatiquement depuis le modèle global pour démarrer vite.
 *
 * Tout est ATOMIQUE (une transaction) : jamais un compte sans agence
 * ni une agence sans propriétaire en base.
 *
 * Sécurité : le compte créé est TOUJOURS rattaché à SA nouvelle agence
 * (il ne peut pas s'auto-proclamer super-admin — statut réservé aux
 * seeders) et le contexte de session est verrouillé sur cette agence
 * dès la première requête.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly AgencyService $agencies)
    {
    }

    /** Formulaire d'inscription (compte + agence). */
    public function show()
    {
        return view('auth.register');
    }

    /**
     * Crée le compte + l'agence, connecte l'utilisateur et ouvre la
     * session dans le contexte de sa nouvelle agence.
     *
     * @throws \Throwable
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            // --- Compte (admin local de l'agence) -------------------------
            'name'                  => ['required', 'string', 'max:120'],
            'email'                 => ['required', 'email', 'max:120', 'unique:users,email'],
            'password'              => ['required', Password::min(6)->letters()->numbers()],
            'password_confirmation' => ['required', 'same:password'],

            // --- Première agence ------------------------------------------
            'agency_name'  => ['required', 'string', 'max:120', 'unique:agencies,name'],
            'agency_phone' => ['nullable', 'string', 'max:30'],
            'agency_email' => ['nullable', 'email', 'max:120'],
            'agency_address' => ['nullable', 'string', 'max:255'],
            // --- Conditions d'utilisation ---------------------------------
            'terms' => ['accepted'],
        ], [
            'name.required'              => 'Votre nom est obligatoire.',
            'email.unique'               => 'Un compte existe déjà avec cet e-mail — connectez-vous.',
            'password.min'               => 'Le mot de passe doit contenir au moins 6 caractères.',
            'password.letters'           => 'Le mot de passe doit contenir au moins une lettre.',
            'password.numbers'           => 'Le mot de passe doit contenir au moins un chiffre.',
            'password_confirmation.same' => 'Les deux mots de passe ne correspondent pas.',
            'agency_name.required'       => 'Le nom de votre agence est obligatoire.',
            'agency_name.unique'         => 'Une agence porte déjà ce nom — choisissez-en un autre.',
            'terms.accepted'             => 'Vous devez accepter les conditions d\u2019utilisation.',
        ]);

        $user = DB::transaction(function () use ($data) {
            // 1) L'agence (via le service : code AG-### + catalogue copié).
            //    AUCUN owner au départ : le compte ci-dessous n'existe pas
            //    encore (pas d'auto-référence FK possible dans la même
            //    transaction) — il est gravé juste après sa création.
            $agency = $this->agencies->create([
                'name'       => $data['agency_name'],
                'phone'      => $data['agency_phone'] ?? null,
                'email'      => $data['agency_email'] ?? null,
                'address'    => $data['agency_address'] ?? null,
                'copy_catalog' => true,
            ]);

            // 2) Le compte PROPRIÉTAIRE : admin LOCAL de son agence ET
            //    gestionnaire de son groupe (agency_id rempli => jamais
            //    super-admin via l'inscription).
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'agency_id' => $agency->id,
                'is_active' => true,
            ]);
            $user->assignRole('admin');

            // 3) Propriété du groupe : il pourra ajouter d'autres agences
            //    et gérer les utilisateurs de son groupe (écrans Agences/
            //    Utilisateurs scoppés sur SES agences).
            $agency->update(['owner_id' => $user->id]);

            return $user;
        });

        // 3) Connexion immédiate + contexte verrouillé sur SON agence.
        Auth::login($user);
        $request->session()->regenerate(); // anti fixation de session
        AgencyContext::set((int) $user->agency_id);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Bienvenue ' . $user->name . ' ! Votre agence « '
                . $user->agency->name . ' » est prête (catalogue initial copié).');
    }
}
