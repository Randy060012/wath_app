<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\AgencyService;
use App\Support\AgencyContext;
use Illuminate\Http\Request;

/**
 * MULTI-TENANT SELF-SERVICE — CONTRÔLEUR AgencyController (GROUPE)
 * -----------------------------------------------------------------
 * Gestion des agences / businesses : liste, création (avec duplication
 * du catalogue et admin local), activation/désactivation.
 *
 * Accès (middleware group-manager) :
 *  - SUPER-ADMIN (plateforme) : toutes les agences ;
 *  - PROPRIÉTAIRE self-service : UNIQUEMENT les agences dont il est
 *    owner (agencies.owner_id) — liste, création (il en devient
 *    automatiquement le propriétaire), activation, catalogue.
 */
class AgencyController extends Controller
{
    public function __construct(private readonly AgencyService $agencies)
    {
    }

    /** Liste des agences (scoppée au groupe possédé) + vue GROUPE. */
    public function index()
    {
        $agencies = $this->visibleAgencies()
            ->withCount([
                'users',
                // clients/orders portent le scope global de contexte :
                // les compteurs du groupe doivent ignorer ce contexte.
                'clients' => fn ($q) => $q->withAgency(),
                'orders'  => fn ($q) => $q->withAgency(),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.agencies.index', [
            'agencies'     => $agencies,
            'currentId'    => AgencyContext::id(),
        ]);
    }

    /** Crée une agence (catalogue dupliqué + admin local optionnel). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120', 'unique:agencies,name'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:120'],
            'address'      => ['nullable', 'string', 'max:255'],
            'copy_catalog' => ['nullable', 'boolean'],
            'admin_name'     => ['nullable', 'string', 'max:120', 'required_with:admin_email'],
            'admin_email'    => ['nullable', 'email', 'max:120', 'unique:users,email'],
            'admin_password' => ['nullable', 'string', 'min:6', 'required_with:admin_email'],
        ], [
            'name.unique'                          => 'Une agence porte déjà ce nom.',
            'admin_email.unique'                   => 'Cet e-mail est déjà utilisé par un utilisateur.',
            'admin_password.min'                   => 'Le mot de passe admin doit faire au moins 6 caractères.',
            'admin_name.required_with'             => 'Le nom de l\'admin local est obligatoire avec son e-mail.',
            'admin_password.required_with'         => 'Le mot de passe admin local est obligatoire avec son e-mail.',
        ]);

        $user = $request->user();

        $agency = $this->agencies->create([
            'name'         => $data['name'],
            'phone'        => $data['phone'] ?? null,
            'email'        => $data['email'] ?? null,
            'address'      => $data['address'] ?? null,
            'copy_catalog' => $request->boolean('copy_catalog'),
            // Self-service : le créateur devient PROPRIÉTAIRE de la nouvelle
            // agence (le super-admin crée des agences « orphelines », sans
            // owner dédié, comme avant).
            'owner_id'     => $user->isSuperAdmin() ? null : $user->id,
            'admin'        => isset($data['admin_email']) ? [
                'name'     => $data['admin_name'],
                'email'    => $data['admin_email'],
                'password' => $data['admin_password'],
            ] : null,
        ]);

        return back()->with('success', 'Agence ' . $agency->code . ' « ' . $agency->name . ' » créée.'
            . ($request->boolean('copy_catalog') ? ' Catalogue initial copié.' : ''));
    }

    /** Active / désactive une agence (du groupe possédé uniquement). */
    public function toggle(Request $request, Agency $agency)
    {
        $this->authorizeAgency($request, $agency);

        $this->agencies->toggle($agency);

        return back()->with(
            'success',
            $agency->is_active
                ? 'Agence « ' . $agency->name . ' » réactivée.'
                : 'Agence « ' . $agency->name . ' » désactivée.'
        );
    }

    /** Duplique à nouveau le catalogue global vers une agence existante (si vide). */
    public function seedCatalog(Request $request, Agency $agency)
    {
        $this->authorizeAgency($request, $agency);

        $this->agencies->duplicateCatalogTo($agency);

        return back()->with('success', 'Catalogue initialisé pour « ' . $agency->name . ' ».');
    }

    /* -----------------------------------------------------------------
     | Helpers de périmètre (super-admin = tout, owner = SES agences)
     | ----------------------------------------------------------------- */

    /** Requête des agences visibles par l'utilisateur courant. */
    private function visibleAgencies()
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            // Agency n'a pas de scope global : toutes les agences.
            return Agency::query();
        }

        return $user->ownedAgencies();
    }

    /** Garde : l'agence ciblée doit appartenir au périmètre de l'utilisateur. */
    private function authorizeAgency(Request $request, Agency $agency): void
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && (int) $agency->owner_id !== (int) $user->id) {
            abort(403, 'Cette agence ne fait pas partie de votre groupe.');
        }
    }
}
