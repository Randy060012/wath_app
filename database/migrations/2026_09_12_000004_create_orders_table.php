<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — TABLE `orders` (la commande / le ticket)
 * -----------------------------------------------------------------
 * En-tête de dépôt. Champs clés :
 *  - PK          : id
 *  - ticket_no   : Numéro de ticket UNIQUE, lisible et imprimé (ex: T-2026-000123)
 *                  → généré par OrderService::generateTicketNumber()
 *  - FK client_id   → clients.id
 *  - FK user_id     → users.id (l'employé caissier qui a créé le dépôt)
 *  - Montants    : DECIMAL(10,2) — total, acompte dérivé de payments,
 *                  status global dérivé du statut des articles
 *  - Express     : booléen "presse" (surcharge tarifaire possible)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();                                              // PK
            $table->string('ticket_no')->unique();                     // T-2026-000123
            $table->foreignId('client_id')->constrained()->restrictOnDelete(); // FK clients
            $table->foreignId('user_id')->constrained()->restrictOnDelete();   // FK users (caissier)
            $table->enum('status', ['recu', 'en_cours', 'repasse', 'pret', 'livre', 'perdu'])
                  ->default('recu')->index();                          // statut global dérivé
            $table->decimal('total_amount', 10, 2)->default(0);        // montant total
            $table->decimal('discount_amount', 10, 2)->default(0);     // remise accordée
            $table->boolean('is_express')->default(false);             // service presse
            $table->timestamp('promised_at')->nullable()->index();     // date de retour promise
            $table->timestamp('delivered_at')->nullable();             // retrait effectif
            $table->text('notes')->nullable();
            $table->timestamps();

            // Tableaux de bord : "dépôts du jour"
            $table->index('created_at');
            $table->index(['status', 'promised_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
