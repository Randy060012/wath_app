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
 *  - utilisateur d'agence : le contexte est FORCÉ à son agence
 *    (impossible de voir autre chose, même en manipulant la session) ;
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
            if ($user->agency_id !== null) {
                // Employé d'agence : contexte verrouillé sur son agence.
                AgencyContext::set((int) $user->agency_id);
            }
            // Super-admin : son sélecteur de session fait foi (peut être null = groupe).
        }

        view()->share('currentAgency', AgencyContext::agency());

        return $next($request);
    }
}
