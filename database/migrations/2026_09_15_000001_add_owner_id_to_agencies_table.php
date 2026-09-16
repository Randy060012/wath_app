<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-TENANT SELF-SERVICE — PROPRIÉTAIRE DE GROUPE
 * -----------------------------------------------------------------
 * Chaque agence porte désormais son PROPRIÉTAIRE (users.id) :
 *  - le client inscrit via /register devient owner de l'agence
 *    qu'il crée à l'inscription (et de celles qu'il ajoute ensuite) ;
 *  - le propriétaire gère SON groupe depuis les écrans Agences /
 *    Utilisateurs (vue limitée à ses agences) ;
 *  - NULL = agence créée par le super-admin (aucun owner dédié).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
