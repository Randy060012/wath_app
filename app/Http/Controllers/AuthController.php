<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * ÉTAPE 2 — AUTHENTIFICATION (sessions Laravel classiques)
 * -----------------------------------------------------------------
 * Auth monolithe par sessions + CSRF (le standard Blade). Chaque
 * employé a un compte ; son rôle (Spatie) détermine ses écrans.
 * Anti brute-force : limiteur manuel 5 tentatives / minute par
 * couple e-mail + IP, avec message lisible au-delà.
 */
class AuthController extends Controller
{
    /** Formulaire de connexion. */
    public function showLogin()
    {
        return view('auth.login');
    }

    /** Clé du limiteur : e-mail + IP (un attaquant ne bloque pas le magasin). */
    private function throttleKey(Request $request): string
    {
        return strtolower($request->input('email')) . '|' . $request->ip();
    }

    /** Tente la connexion puis redirige selon le rôle. */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'L\'adresse e-mail est obligatoire.',
            'email.email'       => 'Adresse e-mail invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        // --- ANTI BRUTE-FORCE : 5 tentatives / minute ------------------------
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));

            return back()
                ->withErrors(['email' => 'Trop de tentatives. Réessayez dans ' . $seconds . ' s.'])
                ->onlyInput('email');
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($this->throttleKey($request)); // succès → remise à zéro
            $request->session()->regenerate(); // anti fixation de session

            return redirect()->intended(route('dashboard'));
        }

        // Échec : compte la tentative (le hit expire après 60 s).
        RateLimiter::hit($this->throttleKey($request), 60);

        return back()
            ->withErrors(['email' => 'Identifiants incorrects.'])
            ->onlyInput('email');
    }

    /** Déconnexion. */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
