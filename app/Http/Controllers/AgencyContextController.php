<?php

namespace App\Http\Controllers;

use App\Support\AgencyContext;
use Illuminate\Http\Request;

/**
 * MULTI-TENANT — CONTRÔLEUR AgencyContextController
 * -----------------------------------------------------------------
 * Permet au SUPER-ADMIN de basculer sa session entre :
 *  - la vue GROUPE (toutes les agences, AgencyContext = null) ;
 *  - une agence précise (il « endosse » le point de vue de l'agence).
 *
 * Un utilisateur rattaché à une agence ne peut PAS changer de
 * contexte : son agency_id est verrouillé par le middleware
 * EnsureAgencyContext (et ici par le reset qui ne l'atteint jamais).
 */
class AgencyContextController extends Controller
{
    /** Choix du contexte (super-admin) : agency_id absent/null = vue groupe. */
    public function switch(Request $request)
    {
        $data = $request->validate([
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
        ]);

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
