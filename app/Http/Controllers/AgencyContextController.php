<?php

namespace App\Http\Controllers;

use App\Support\AgencyContext;
use Illuminate\Http\Request;

/**
 * MULTI-TENANT — CONTRÔLEUR AgencyContextController
 * -----------------------------------------------------------------
 * Permet de basculer sa session entre les agences :
 *  - SUPER-ADMIN : vue GROUPE (AgencyContext = null) ⇄ une agence
 *    précise (il « endosse » le point de vue de l'agence) ;
 *  - PROPRIÉTAIRE self-service : uniquement parmi LES AGENCES QU'IL
 *    POSSÈDE (agencies.owner_id) — pas de vue groupe.
 *
 * Un utilisateur d'agence simple ne peut PAS changer de contexte :
 * son agency_id est verrouillé par le middleware
 * EnsureAgencyContext (et ici par le reset qui ne l'atteint jamais).
 */
class AgencyContextController extends Controller
{
    /**
     * Choix du contexte : agency_id absent/null = vue groupe
     * (super-admin uniquement ; un propriétaire vise une de SES agences).
     */
    public function switch(Request $request)
    {
        $data = $request->validate([
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
        ]);

        $user = $request->user();

        // Propriétaire self-service : bascule possible UNIQUEMENT vers
        // une agence qu'il possède (jamais la vue groupe, jamais ailleurs).
        if (! $user->isSuperAdmin()) {
            if (empty($data['agency_id'])
                || ! $user->ownedAgencies()->whereKey((int) $data['agency_id'])->exists()) {
                return back()->with('error', 'Vous ne pouvez basculer que vers une agence de votre groupe.');
            }
        }

        AgencyContext::set(isset($data['agency_id']) ? (int) $data['agency_id'] : null);

        $label = isset($data['agency_id'])
            ? ('Contexte : ' . \App\Models\Agency::find($data['agency_id'])->name)
            : 'Contexte : GROUPE (toutes les agences).';

        return back()->with('success', $label);
    }

    /** Retour à la vue GROUPE (accessible à tout admin ; sans effet pour un admin local). */
    public function reset(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            AgencyContext::set(null);
        }

        return back();
    }
}
