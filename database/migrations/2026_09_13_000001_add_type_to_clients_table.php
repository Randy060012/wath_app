<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÉTAPE 2 — CRM : DISTINCTION ACTEUR / CLIENT (spécifications fonctionnelles B)
 * -----------------------------------------------------------------
 *  - `type` : statut dynamique du tiers.
 *      · acteur  : prospect / demande de renseignement / proforma —
 *                  aucun dépôt validé à son actif ;
 *      · client  : a effectué au moins un dépôt validé. La promotion
 *                  acteur → client est AUTOMATIQUE à la validation
 *                  d'un dépôt (voir OrderService::createOrder).
 *  - L'inscription directe d'un Acteur depuis la liste clients est
 *    possible : le formulaire de création enregistre en type "acteur".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('type', 20)->default('client')->after('code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
