<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `notifications` (canal "database" de Laravel)
 * -----------------------------------------------------------------
 * Stocke les notifications internes (ex: "commande prête") liées à
 * n'importe quel modèle Notifiable (Client, User...) via les colonnes
 * polymorphiques notifiable_type / notifiable_id.
 * Créée par l'équivalent de `php artisan make:notifications-table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');                                   // classe de la notification
            $table->morphs('notifiable');                             // notifiable_type + notifiable_id (indexés)
            $table->text('data');                                     // payload JSON (toArray())
            $table->timestamp('read_at')->nullable();                 // lu ou non
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
