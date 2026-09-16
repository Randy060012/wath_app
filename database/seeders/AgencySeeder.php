<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Services\AgencyService;
use Illuminate\Database\Seeder;

/**
 * MULTI-TENANT — SEEDER DES AGENCES
 * -----------------------------------------------------------------
 *  1) crée le catalogue GLOBAL (agency_id null) : modèle de départ
 *     copié dans chaque nouvelle agence (CatalogSeeder) ;
 *  2) crée l'agence de démonstration « Agence Centrale » avec la
 *     copie du catalogue (idempotent : relancer ne duplique rien).
 */
class AgencySeeder extends Seeder
{
    public function run(): void
    {
        // --- 1) Catalogue global (modèle de départ) ----------------------
        $this->call(CatalogSeeder::class);

        // --- 2) Agence de démonstration ----------------------------------
        $agency = Agency::query()->firstWhere('name', 'Agence Centrale');

        if ($agency === null) {
            $agency = new Agency(['name' => 'Agence Centrale', 'phone' => '+221 33 800 00 00']);
            $agency->code = Agency::nextCode();
            $agency->save();
        }

        // Catalogue initial copié depuis le global (idempotent)
        if (! $agency->hasCatalog()) {
            app(AgencyService::class)->duplicateCatalogTo($agency);
        }
    }
}
