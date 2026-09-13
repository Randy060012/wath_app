<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ÉTAPE 2 — SEEDER DES UTILISATEURS (comptes de démonstration)
 *  - admin@pressing.test    / password  → rôle admin
 *  - caissier@pressing.test / password  → rôle caissier
 *  - atelier@pressing.test  / password  → rôle atelier
 * À changer impérativement en production !
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrateur', 'email' => 'admin@pressing.test'],
            ['name' => 'Fatou Caissière', 'email' => 'caissier@pressing.test'],
            ['name' => 'Moussa Atelier', 'email' => 'atelier@pressing.test'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('password')]
            );

            // Rôle dérivé du préfixe de l'e-mail de démo
            $role = str_contains($u['email'], 'admin') ? 'admin'
                : (str_contains($u['email'], 'caissier') ? 'caissier' : 'atelier');

            $user->assignRole($role);
        }
    }
}
