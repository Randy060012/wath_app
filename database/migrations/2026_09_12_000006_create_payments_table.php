<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `payments` (journal des encaissements)
 * -----------------------------------------------------------------
 * Une commande peut avoir PLUSIEURS paiements : acompte à la création,
 * solde au retrait. JAMAIS de suppression d'un paiement (comptabilité)
 * → onDelete('restrict') : un paiement référence toujours une commande
 * existante ; l'annulation se fait par un paiement négatif si besoin.
 * Champs clés :
 *  - PK         : id
 *  - FK order_id → orders.id (RESTRICT)
 *  - FK user_id  → users.id (qui a encaissé)
 *  - amount     : DECIMAL — positif (encaissement), l'acompte/solde se
 *                 lit via SUM(amount) comparé à orders.total_amount
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();                                                      // PK
            $table->foreignId('order_id')->constrained()->restrictOnDelete();  // FK orders
            $table->foreignId('user_id')->constrained()->restrictOnDelete();   // FK users (encaisseur)
            $table->decimal('amount', 10, 2);                                  // montant encaissé
            $table->string('method', 20);                                      // cash | mobile_money | card
            $table->string('reference')->nullable();                           // réf. transaction MM
            $table->timestamps();

            $table->index('created_at'); // caisse du jour
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
