<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `status_logs` (piste d'audit / historique)
 * -----------------------------------------------------------------
 * Chaque changement de statut d'un article est journalisé ici par
 * l'Observer OrderItemObserver (voir app/Observers). On ne devine
 * JAMAIS : on sait QUI a scanné QUOI, QUAND, et depuis quel statut
 * vers quel statut. Indispensable pour gérer les litiges client.
 * Champs clés :
 *  - PK                : id
 *  - FK order_item_id  → order_items.id (CASCADE : suit son article)
 *  - FK user_id        → users.id (nullable : job système sans user)
 *  - from / to         : statut avant / après
 *  - location          : emplacement au moment du scan (optionnel)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();                                                          // PK
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();  // FK order_items
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // FK users
            $table->string('from', 20)->nullable();                                // statut précédent
            $table->string('to', 20);                                              // nouveau statut
            $table->string('location', 120)->nullable();                           // rangement au moment du scan
            $table->timestamp('created_at')->useCurrent();                         // horodatage

            // Timeline d'un article (ordre chronologique)
            $table->index(['order_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_logs');
    }
};
