<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MULTI-TENANT — MIDDLEWARE EnsureSuperAdmin
 * -----------------------------------------------------------------
 * Réserve les routes d'administration GROUPE (agences, utilisateurs)
 * au super-administrateur : rôle admin ET users.agency_id null.
 * Un admin LOCAL (rattaché à une agence) est redirigé avec erreur.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Écran réservé au super-administrateur (vue groupe).');
        }

        return $next($request);
    }
}
