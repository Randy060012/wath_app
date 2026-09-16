<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\AgencyService;
use Illuminate\Http\Request;

/**
 * MULTI-TENANT — CONTRÔLEUR AgencyController (SUPER-ADMIN)
 * -----------------------------------------------------------------
 * Gestion des agences / businesses : liste, création (avec duplication
 * du catalogue et admin local), activation/désactivation.
 * Réservé au super-admin (users.agency_id null + rôle admin).
 */
class AgencyController extends Controller
{
    public function __construct(private readonly AgencyService $agencies)
    {
    }

    /** Liste des agences + vue GROUPE (agrégats toutes agences). */
    public function index()
    {
        $agencies = Agency::query()
            ->withCount(['users', 'clients', 'orders'])
            ->orderBy('name')
            ->get();

        return view('admin.agencies.index', [
            'agencies'     => $agencies,
            'currentId'    => \App\Support\AgencyContext::id(),
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
            'admin_name.required_with'             => 'Le nom de l\u2019admin local est obligatoire avec son e-mail.',
            'admin_password.required_with'         => 'Le mot de passe admin local est obligatoire avec son e-mail.',
        ]);

        $agency = $this->agencies->create([
            'name'         => $data['name'],
            'phone'        => $data['phone'] ?? null,
            'email'        => $data['email'] ?? null,
            'address'      => $data['address'] ?? null,
            'copy_catalog' => $request->boolean('copy_catalog'),
            'admin'        => isset($data['admin_email']) ? [
                'name'     => $data['admin_name'],
                'email'    => $data['admin_email'],
                'password' => $data['admin_password'],
            ] : null,
        ]);

        return back()->with('success', 'Agence ' . $agency->code . ' « ' . $agency->name . ' » créée.'
            . ($request->boolean('copy_catalog') ? ' Catalogue initial copié.' : ''));
    }

    /** Active / désactive une agence. */
    public function toggle(Agency $agency)
    {
        $this->agencies->toggle($agency);

        return back()->with(
            'success',
            $agency->is_active
                ? 'Agence « ' . $agency->name . ' » réactivée.'
                : 'Agence « ' . $agency->name . ' » désactivée.'
        );
    }

    /** Duplique à nouveau le catalogue global vers une agence existante (si vide). */
    public function seedCatalog(Agency $agency)
    {
        $this->agencies->duplicateCatalogTo($agency);

        return back()->with('success', 'Catalogue initialisé pour « ' . $agency->name . ' ».');
    }
}
