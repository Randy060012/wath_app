<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * ÉTAPE 2 — SEEDER DES RÔLES (Spatie Laravel-Permission)
 * -----------------------------------------------------------------
 * Trois rôles métier :
 *  - admin    : toute l'application + catalogue + stock + rapports ;
 *  - caissier : dépôts, retraits, clients, impressions ;
 *  - atelier  : suivi de production, scannés, emplacements.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'caissier', 'atelier'] as $roleName) {
            // firstOrCreate : idempotent — relancer le seeder ne duplique pas
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }
}
