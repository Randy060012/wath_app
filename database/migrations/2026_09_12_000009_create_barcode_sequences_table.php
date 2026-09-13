<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `barcode_sequences` (compteur technique)
 * -----------------------------------------------------------------
 * Table technique à une ligne par année : alloue les numéros séquentiels
 * des codes-barres (BC-2026-000001, BC-2026-000002...) de façon ATOMIQUE
 * (UPSERT), même avec plusieurs caisses simultanées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_sequences', function (Blueprint $table) {
            $table->string('year', 4)->primary();           // PK : année
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Année courante pré-initialisée
        \Illuminate\Support\Facades\DB::table('barcode_sequences')->insert([
            'year'       => now()->format('Y'),
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_sequences');
    }
};
