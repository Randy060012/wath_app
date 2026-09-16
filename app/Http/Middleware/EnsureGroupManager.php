<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MULTI-TENANT SELF-SERVICE — MIDDLEWARE EnsureGroupManager
 * -----------------------------------------------------------------
 * Remplace (et englobe) « super-admin » pour les écrans GROUPE :
 *  - super-admin (users.agency_id null + rôle admin) : accès complet,
 *    vue toutes agences — cas plateforme/opérateur ;
 *  - PROPRIÉTAIRE self-service (agencies.owner_id = son id) : accès
 *    limité aux agences qu'il possède (et à leurs utilisateurs).
 *
 * Un admin LOCAL simple (ni l'un ni l'autre) est redirigé avec erreur.
 */
class EnsureGroupManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->managesGroup()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Écran réservé au gestionnaire du groupe (propriétaire ou super-administrateur).');
        }

        return $next($request);
    }
}
