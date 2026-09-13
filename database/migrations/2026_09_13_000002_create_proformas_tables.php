<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — GESTION DOCUMENTAIRE : PROFORMAS / DEVIS (spécifications C)
 * -----------------------------------------------------------------
 * Un proforma est un document commercial NON comptable (devis) adressé
 * à un Acteur (prospect) ou un Client, décrivant des prestations et
 * leur tarif estimé. Il vit en dehors des commandes réelles : pas de
 * stock, pas de code-barres, pas d'encaissement.
 * Cycle de vie : brouillon → envoyé → accepté | refusé | expiré.
 *  - proformas      : en-tête (n° P-2026-0001, tiers, totaux, validité) ;
 *  - proforma_items : lignes détaillées (prestation figée ou libre).
 * Un commentaire optionnel est conservé quand un proforma est converti
 * en dépôt réel (traçabilité commerciale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proformas', function (Blueprint $table) {
            $table->id();
            // Numéro lisible unique P-2026-000123 (dérivé de l'ID, cf. modèle)
            $table->string('number', 24)->unique();

            // Tiers destinataire (acteur ou client)
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();

            // Auteur du document (employé connecté)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // brouillon | envoye | accepte | refuse | expire
            $table->string('status', 20)->default('brouillon')->index();

            // Montants (MÊME logique que orders : total, remise, net)
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);

            // Date d'émission + validité de l'offre (défaut +15 jours)
            $table->date('issued_at');
            $table->date('valid_until');

            // Conversion en dépôt réel (si accepté) : traçabilité
            $table->foreignId('converted_order_id')
                  ->nullable()
                  ->constrained('orders')
                  ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('proforma_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_id')->constrained('proformas')->cascadeOnDelete();

            // Prestation du catalogue (facultative : ligne libre possible
            // pour une prestation ponctuelle non cataloguée)
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('label', 160);                 // libellé figé au moment de l'édition
            $table->string('pricing_unit', 10)->default('piece'); // piece | kg | forfait
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_items');
        Schema::dropIfExists('proformas');
    }
};
