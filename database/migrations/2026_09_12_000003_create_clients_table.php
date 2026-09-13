<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `clients`
 * -----------------------------------------------------------------
 * Fiche client de passage ou fidèle. Volontairement SÉPARÉE de `users`
 * : les clients ne se connectent pas à l'application, ce sont des
 * enregistrements gérés par la caisse.
 * Champs clés :
 *  - PK        : id
 *  - code      : code client lisible (ex: CL-000042), unique, généré
 *  - phone     : indexé car LA clé de recherche rapide à la caisse
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();                                    // PK
            $table->string('code')->unique();                // CL-000042
            $table->string('name');                          // nom / raison sociale
            $table->string('phone', 30)->nullable()->index();// recherche caisse par téléphone
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();               // allergies, habitudes...
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
