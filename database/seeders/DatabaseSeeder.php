<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ÉTAPE 2 — SEEDER PRINCIPAL
 * Orchestre les seeders de l'application (php artisan db:seed).
 * Le catalogue de démonstration (CatalogSeeder) peut être ajouté ici.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
