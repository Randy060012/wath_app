<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `order_items` (un vêtement / lot confié)
 * -----------------------------------------------------------------
 * LIGNE de commande : UN article = UNE étiquette code-barres = UN
 * cycle de statut individuel. C'est le cœur du suivi de l'atelier.
 * Champs clés :
 *  - PK           : id
 *  - FK order_id  → orders.id (CASCADE : la suppression d'une commande
 *                   annulée emporte ses lignes)
 *  - FK service_id → services.id (RESTRICT : historique tarifaire conservé)
 *  - barcode      : code unique imprimé sur l'étiquette (BC00000001),
 *                   indexé UNIQUE car c'est la clé de scan en atelier
 *  - status       : statut INDIVIDUEL de l'article (cycle de vie complet)
 *  - location     : emplacement physique rangé (ex: "Convoyeur A - Étagère 12")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();                                                    // PK
            $table->foreignId('order_id')->constrained()->cascadeOnDelete(); // FK orders
            $table->foreignId('service_id')->constrained()->restrictOnDelete(); // FK services
            $table->string('barcode', 32)->unique();                         // BC-2026-000001
            $table->string('description')->nullable();                       // "Chemise bleue rayée"
            $table->enum('status', ['recu', 'en_cours', 'repasse', 'pret', 'livre', 'perdu'])
                  ->default('recu')->index();                                // statut individuel
            $table->decimal('unit_price', 10, 2);                            // prix au moment de la vente (figé)
            $table->unsignedInteger('quantity')->default(1);                 // 1 kg ou 3 chemises identiques
            $table->decimal('line_total', 10, 2);                            // unit_price × quantity
            $table->string('location', 120)->nullable();                     // Convoyeur A - Étagère 12
            $table->timestamps();

            // Écran atelier : "articles en cours" par statut
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
