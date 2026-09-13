<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * ÉTAPE 2 — SEEDER DU CATALOGUE (démo / recette)
 * Crée les familles de prestations classiques d'un pressing
 * + quelques fournitures en stock avec seuils d'alerte.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Lavage' => [
                ['name' => 'Chemise / haut', 'price' => 500, 'unit' => 'piece', 'hours' => 48],
                ['name' => 'Pantalon', 'price' => 600, 'unit' => 'piece', 'hours' => 48],
                ['name' => 'Robe simple', 'price' => 1000, 'unit' => 'piece', 'hours' => 48],
                ['name' => 'Linge de maison', 'price' => 1500, 'unit' => 'kg', 'hours' => 72],
            ],
            'Nettoyage à sec' => [
                ['name' => 'Costume 2 pièces', 'price' => 3500, 'unit' => 'piece', 'hours' => 72],
                ['name' => 'Veste', 'price' => 2000, 'unit' => 'piece', 'hours' => 72],
                ['name' => 'Manteau', 'price' => 4000, 'unit' => 'piece', 'hours' => 96],
            ],
            'Repassage' => [
                ['name' => 'Chemise (repassage seul)', 'price' => 300, 'unit' => 'piece', 'hours' => 24],
                ['name' => 'Pantalon (repassage seul)', 'price' => 350, 'unit' => 'piece', 'hours' => 24],
            ],
            'Cuir & Doudounes' => [
                ['name' => 'Doudoune', 'price' => 5000, 'unit' => 'piece', 'hours' => 96],
                ['name' => 'Blouson cuir', 'price' => 7000, 'unit' => 'piece', 'hours' => 120],
            ],
        ];

        $sort = 0;
        foreach ($catalog as $categoryName => $services) {
            // icon : nom d'icône Lucide (rendu par le composant <x-icon>)
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => \Illuminate\Support\Str::slug($categoryName), 'sort_order' => $sort++, 'icon' => 'layers']
            );

            foreach ($services as $s) {
                Service::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $s['name']],
                    [
                        'price'         => $s['price'],
                        'pricing_unit'  => $s['unit'],
                        'default_hours' => $s['hours'],
                        'is_active'     => true,
                    ]
                );
            }
        }

        // Stock de fournitures initial
        $supplies = [
            ['name' => 'Housse transparente', 'unit' => 'pièce', 'quantity' => 250, 'min_quantity' => 50, 'unit_cost' => 25],
            ['name' => 'Cintre métal', 'unit' => 'pièce', 'quantity' => 120, 'min_quantity' => 40, 'unit_cost' => 100],
            ['name' => 'Détergent 5L', 'unit' => 'bidon', 'quantity' => 6, 'min_quantity' => 3, 'unit_cost' => 8500],
            ['name' => 'Étiquettes thermiques', 'unit' => 'rouleau', 'quantity' => 4, 'min_quantity' => 2, 'unit_cost' => 3500],
            ['name' => 'Sacs kraft', 'unit' => 'pièce', 'quantity' => 80, 'min_quantity' => 100, 'unit_cost' => 150],
        ];

        foreach ($supplies as $supply) {
            Inventory::firstOrCreate(['name' => $supply['name']], $supply);
        }
    }
}
