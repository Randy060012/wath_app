<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `services`
 * -----------------------------------------------------------------
 * Catalogue des prestations vendues (le "produit" du pressing).
 * Champs clés :
 *  - PK           : id
 *  - FK           : category_id → categories.id (RESTRICT : on ne supprime
 *                   pas une catégorie contenant des prestations)
 *  - price        : DECIMAL(10,2) — JAMAIS de FLOAT pour l'argent !
 *  - pricing_unit : 'piece' (à l'unité) ou 'kg' (au poids)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();                                              // PK
            $table->foreignId('category_id')                           // FK → categories
                  ->constrained()
                  ->restrictOnDelete();
            $table->string('name');                                    // "Chemise lavage-repassage"
            $table->decimal('price', 10, 2);                           // prix unitaire
            $table->string('pricing_unit', 10)->default('piece');      // piece | kg
            $table->unsignedSmallInteger('default_hours')->default(48);// délai standard (heures)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Une prestation est unique dans sa famille
            $table->unique(['category_id', 'name']);
            // Index pour la grille de caisse triée par prix
            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
