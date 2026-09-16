<?php

namespace App\Http\Middleware;

use App\Support\AgencyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * MULTI-TENANT — MIDDLEWARE EnsureAgencyContext
 * -----------------------------------------------------------------
 * Appliqué au groupe 'auth' : garantit qu'une session authentifiée
 * porte toujours un contexte d'agence cohérent.
 *
 *  - utilisateur d'agence SANS groupe : le contexte est FORCÉ à son
 *    agence (impossible de voir autre chose, même en manipulant la
 *    session) ;
 *  - PROPRIÉTAIRE self-service : respecte son sélecteur de session
 *    (AgencyContext valide l'appartenance — sinon son agence de
 *    rattachement fait foi) ;
 *  - super-admin : respecte son sélecteur (vue groupe ou une agence).
 *
 * Partage aussi l'agence courante à toutes les vues (badge sidebar).
 */
class EnsureAgencyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('agencies')) {
            return $next($request); // avant migration : application mono-tenant
        }

        $user = $request->user();

        if ($user !== null) {
            if ($user->agency_id !== null && ! $user->managesGroup()) {
                // Employé d'agence simple : contexte verrouillé sur son agence.
                AgencyContext::set((int) $user->agency_id);
            }
            // Propriétaire self-service : sélecteur validé par AgencyContext::id()
            // (une agence non possédée est ignorée — fallback agence de rattachement).
            // Super-admin : son sélecteur fait foi (peut être null = groupe).
        }

        view()->share('currentAgency', AgencyContext::agency());

        return $next($request);
    }
}
