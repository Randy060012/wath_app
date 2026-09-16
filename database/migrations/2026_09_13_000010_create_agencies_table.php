<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-TENANT — TABLE `agencies` + COLONNE `agency_id`
 * -----------------------------------------------------------------
 * Modèle choisi : BASE PARTAGÉE, une ligne = une agence / business.
 * Chaque donnée métier (users, clients, catalogue, commandes,
 * encaissements, proformas, stock) porte une clé `agency_id`.
 *
 *  - NULL = donnée GLOBALE : réservé au super-administrateur
 *    (le super-admin n'est rattaché à aucune agence) ;
 *  - le scoping applicatif est assuré par le trait BelongsToAgency
 *    (scope global Eloquent) + le middleware EnsureAgencyContext.
 *
 * Les tables d'audit (status_logs) et de séquences (barcode_sequences)
 * ne sont PAS scoper : elles passent toujours par leur parent ou sont
 * volontairement globales (unicité des codes-barres entre agences).
 */
return new class extends Migration
{
    /**
     * Recrée les contraintes d'unicité en version PAR AGENCIE
     * (les index SQLite sont des objets séparés : drop/create OK).
     */
    private function rescopeUniqueIndexes(): void
    {
        // clients.code : un même code client peut exister dans chaque agence
        // (l'index SQLite est un objet séparé : dropable) ; pour les contraintes
        // inline de create() (categories, services), SQLite n'expose pas d'index
        // supprimable : la recréation de table serait nécessaire. Pour rester
        // portable, on ajoute UNIQUE(agency_id, ...) qui complète la contrainte
        // d'origine — MySQL : dropUnique OK ; SQLite : la contrainte inline
        // reste (limite acceptable : le code applicatif scope déjà par agence).
        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_code_unique');
            });
        } catch (\Throwable) {
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['agency_id', 'code']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['agency_id', 'name']);
            $table->unique(['agency_id', 'slug']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unique(['agency_id', 'category_id', 'name']);
        });
    }

    /** Tables métier rattachées à une agence. */
    private const TENANT_TABLES = [
        'users',
        'categories',
        'services',
        'clients',
        'orders',
        'order_items',
        'payments',
        'proformas',
        'inventories',
    ];

    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();            // AG-001
            $table->string('name')->unique();                // "Pressing Plateau"
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);     // désactivation sans suppression
            $table->timestamps();
        });

        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('agency_id')
                    ->nullable()
                    ->index()
                    ->constrained('agencies')
                    ->nullOnDelete();
            });
        }

        // Activation/désactivation des comptes (sans suppression : historique)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        $this->rescopeUniqueIndexes();

        $this->backfillFromParents();
    }

    /**
     * Données héritées (mono-tenant avant migration) : les lignes filles
     * héritent l'agence de leur commande parente (les lignes sans parent
     * restent NULL = globales).
     */
    private function backfillFromParents(): void
    {
        DB::statement(
            'UPDATE order_items SET agency_id = (SELECT agency_id FROM orders WHERE orders.id = order_items.order_id) WHERE agency_id IS NULL'
        );
        DB::statement(
            'UPDATE payments SET agency_id = (SELECT agency_id FROM orders WHERE orders.id = payments.order_id) WHERE agency_id IS NULL'
        );
    }

    public function down(): void
    {
        // Restaure la contrainte globale sur clients.code (si l'index existe)
        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique(['agency_id', 'code']);
                $table->unique('code');
            });
        } catch (\Throwable) {
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('agency_id');
            });
        }

        Schema::dropIfExists('agencies');
    }
};
