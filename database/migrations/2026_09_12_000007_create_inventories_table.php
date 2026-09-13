<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `inventories` (stock de fournitures)
 * -----------------------------------------------------------------
 * Suivi des consommables du pressing : détergent, housse, cintres,
 * sacs kraft, étiquettes... Champs clés :
 *  - PK          : id
 *  - name / unit : "Housse transparente", unité "pièce"
 *  - quantity    : stock actuel ; min_quantity déclenche l'alerte
 *                  "stock bas" visible sur le dashboard admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();                                       // PK
            $table->string('name');                             // "Détergent 5L"
            $table->string('unit', 20)->default('pièce');       // pièce, litre, kg
            $table->decimal('quantity', 10, 2)->default(0);     // stock actuel
            $table->decimal('min_quantity', 10, 2)->default(0); // seuil d'alerte
            $table->decimal('unit_cost', 10, 2)->default(0);    // coût d'achat unitaire
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('quantity'); // requête "stock bas"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
