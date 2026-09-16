<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * MULTI-TENANT — SERVICE AgencyService
 * -----------------------------------------------------------------
 * Cycle de vie d'une agence, centralisé ici (contrôleurs minces) :
 *  - create() : agence + catalogue DUPLE depuis le catalogue global
 *    (modèle de démarrage : chaque business démarre avec les mêmes
 *    prestations, puis les personnalise) + compte admin local optionnel ;
 *  - update() : coordonnées / activation ;
 *  - toggle() : désactivation (sans supprimer les données).
 */
class AgencyService
{
    /**
     * Crée une agence + son environnement de départ :
     *  - copie du catalogue global (catégories + prestations) si aucun
     *    catalogue global n'existe, le modèle est pris sur la première
     *    agence existante, sinon l'agence démarre vide ;
     *  - admin local optionnel (nom/email/mot de passe) ;
     *  - PROPRIÉTAIRE optionnel (self-service : le client gestionnaire
     *    du groupe — agencies.owner_id).
     *
     * @param  array{name:string, phone?:?string, email?:?string, address?:?string, copy_catalog?:bool, owner_id?:int|null, admin?:?array{name:string,email:string,password:string}} $data
     *
     * @throws \Throwable
     */
    public function create(array $data): Agency
    {
        return DB::transaction(function () use ($data) {
            $agency = Agency::create([
                'code'      => Agency::nextCode(),
                'name'      => $data['name'],
                'phone'     => $data['phone'] ?? null,
                'email'     => $data['email'] ?? null,
                'address'   => $data['address'] ?? null,
                'is_active' => true,
                'owner_id'  => $data['owner_id'] ?? null,
            ]);

            if ($data['copy_catalog'] ?? true) {
                $this->duplicateCatalogTo($agency);
            }

            // Compte admin LOCAL optionnel (rattaché à l'agence).
            if (! empty($data['admin']['email'])) {
                $this->createAgencyAdmin($agency, $data['admin']);
            }

            return $agency;
        });
    }

    /**
     * Duplique le catalogue (catégories + prestations) vers une agence.
     * Source : catalogue GLOBAL (agency_id null) si présent, sinon la
     * première agence qui en possède un. Idempotent : ne duplique pas
     * si l'agence a déjà des prestations.
     */
    public function duplicateCatalogTo(Agency $agency): void
    {
        if ($agency->hasCatalog()) {
            return; // déjà initialisée
        }

        // NB : withAgency() sur la relation services aussi — le contexte
        // de session (ex: propriétaire travaillant dans son agence) ne
        // doit PAS masquer les services GLOBAUX du modèle source.
        $globalExists = Category::query()->withAgency()->whereNull('agency_id')->exists();
        $sourceCategories = $globalExists
            ? Category::query()->withAgency()->whereNull('agency_id')
                ->with(['services' => fn ($q) => $q->withAgency()])
                ->orderBy('sort_order')->get()
            : Category::query()->withAgency()
                ->with(['services' => fn ($q) => $q->withAgency()])
                ->orderBy('sort_order')->limit(1)->get();

        foreach ($sourceCategories as $source) {
            $category = Category::create([
                'name'       => $source->name,
                'slug'       => $source->slug,
                'icon'       => $source->icon,
                'sort_order' => $source->sort_order,
                'is_active'  => $source->is_active,
                'agency_id'  => $agency->id,
            ]);

            foreach ($source->services as $service) {
                Service::create([
                    'category_id'   => $category->id,
                    'name'          => $service->name,
                    'price'         => $service->price,
                    'pricing_unit'  => $service->pricing_unit,
                    'default_hours' => $service->default_hours,
                    'description'   => $service->description,
                    'is_active'     => $service->is_active,
                    'agency_id'     => $agency->id,
                ]);
            }
        }
    }

    /**
     * Crée l'admin LOCAL d'une agence (rattaché, rôle 'admin').
     *
     * @param  array{name:string, email:string, password:string} $admin
     *
     * @throws \Throwable
     */
    public function createAgencyAdmin(Agency $agency, array $admin): User
    {
        return DB::transaction(function () use ($agency, $admin) {
            $user = User::create([
                'name'       => $admin['name'],
                'email'      => $admin['email'],
                'password'   => Hash::make($admin['password']),
                'agency_id'  => $agency->id,
                'is_active'  => true,
            ]);

            $user->assignRole('admin');

            return $user;
        });
    }

    /** Active / désactive une agence (les sessions scoppées ne voient plus rien d'actif). */
    public function toggle(Agency $agency): Agency
    {
        $agency->update(['is_active' => ! $agency->is_active]);

        return $agency;
    }
}
