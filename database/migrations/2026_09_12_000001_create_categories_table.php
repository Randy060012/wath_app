<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `categories`
 * -----------------------------------------------------------------
 * Regroupe les prestations par famille pour l'affichage de la grille
 * de caisse : "Lavage", "Repassage", "Nettoyage à sec", "Cuir & Doudounes".
 * Champs clés :
 *  - PK   : id (BIGINT UNSIGNED AUTO_INCREMENT)
 *  - name : nom de la famille (unique)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();                                    // PK
            $table->string('name')->unique();                // "Lavage", "Nettoyage à sec"...
            $table->string('slug')->unique();                // pour les URL propres
            $table->string('icon')->nullable();              // emoji/classe d'icône pour la grille caisse
            $table->unsignedSmallInteger('sort_order')->default(0); // tri de l'affichage
            $table->boolean('is_active')->default(true);
            $table->timestamps();                            // created_at / updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
