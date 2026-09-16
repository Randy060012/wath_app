<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * MULTI-TENANT — SEEDER DES UTILISATEURS (comptes de démonstration)
 * -----------------------------------------------------------------
 *  - admin@pressing.test    : SUPER-ADMIN (agency_id null, vue groupe) ;
 *  - les autres comptes     : rattachés à l'agence de démonstration.
 * Mots de passe de démo : « password » — à changer en production !
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Le super-admin n'appartient à AUCUNE agence (vue groupe).
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@pressing.test'],
            ['name' => 'Administrateur', 'password' => Hash::make('password'), 'agency_id' => null]
        );
        $superAdmin->assignRole('admin');

        // Rattachement à l'agence de démo si elle existe (AgencySeeder).
        $agency = Agency::query()->firstWhere('name', 'Agence Centrale');

        $demoUsers = [
            ['name' => 'Fatou Caissière', 'email' => 'caissier@pressing.test', 'role' => 'caissier'],
            ['name' => 'Moussa Atelier', 'email' => 'atelier@pressing.test', 'role' => 'atelier'],
            ['name' => 'Ousmane Gestion', 'email' => 'gestion@pressing.test', 'role' => 'admin'],
        ];

        foreach ($demoUsers as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name'      => $u['name'],
                    'password'  => Hash::make('password'),
                    'agency_id' => $agency?->id,
                ]
            );

            $user->assignRole($u['role']);
        }
    }
}
