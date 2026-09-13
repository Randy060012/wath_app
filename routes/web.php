<?php

use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

/**
 * ÉTAPE 2 — ORGANISATION DES ROUTES (web.php)
 * -----------------------------------------------------------------
 * Monolithe : toutes les routes passent par le groupe 'web'
 * (sessions + cookies + CSRF). Organisation :
 *   - /login, /logout          : publiques (auth session) ;
 *   - /                        : dashboard, tout employé connecté ;
 *   - /orders, /clients, /caisse : rôles caissier + admin ;
 *   - /atelier                 : rôle atelier + admin ;
 *   - /admin/*                 : rôle admin uniquement.
 * L'alias 'role' (Spatie) est déclaré dans bootstrap/app.php.
 * Principe : les contrôleurs restent accessibles via route() en vue ;
 * les noms de routes sont les points d'ancrage des menus Blade.
 */

// ---------------------------------------------------------------------
// AUTHENTIFICATION (publique)
// ---------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout'])
    ->middleware('auth')->name('logout');

// ---------------------------------------------------------------------
// ZONE CONNECTÉE (tous rôles) — dashboard d'accueil
// ---------------------------------------------------------------------
Route::middleware(['auth'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Recherche live clients (JSON) : utile en caisse, accessible à tous
    Route::get('/clients/search', ClientSearchController::class)->name('clients.search');
    // SPÉCIFICATIONS A.1 — création d'un tiers à la volée depuis la caisse (JSON)
    Route::post('/clients/quick-store', [ClientSearchController::class, 'quickStore'])->name('clients.quickStore');
});

// ---------------------------------------------------------------------
// CAISSE + ADMIN (création de dépôts, retraits, impressions)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'role:caissier|admin'])->group(function () {
    // Dépôts / commandes
    Route::resource('orders', OrderController::class)->except(['destroy', 'edit']);
    // settle = retrait avec encaissement du solde
    Route::post('/orders/{order}/settle', [OrderController::class, 'settle'])->name('orders.settle');
    // mark-ready = action express depuis la liste des dépôts (menu contextuel)
    Route::post('/orders/{order}/mark-ready', [OrderController::class, 'markReady'])->name('orders.markReady');

    // Fiches clients
    Route::resource('clients', ClientController::class);

    // SPÉCIFICATIONS C — DEVIS / PROFORMAS (documents commerciaux : caisse)
    Route::resource('proformas', ProformaController::class)->except(['edit', 'update', 'destroy']);
    Route::get('/proformas/{proforma}/print', [ProformaController::class, 'print'])->name('proformas.print');
    Route::post('/proformas/{proforma}/send', [ProformaController::class, 'send'])->name('proformas.send');
    Route::post('/proformas/{proforma}/status', [ProformaController::class, 'status'])->name('proformas.status');
    Route::post('/proformas/{proforma}/convert', [ProformaController::class, 'convert'])->name('proformas.convert');

    // Tableau de bord caisse + impressions
    Route::get('/caisse', [CashRegisterController::class, 'dashboard'])->name('caisse.dashboard');
    Route::get('/orders/{order}/print/ticket', [CashRegisterController::class, 'printTicket'])->name('orders.print.ticket');
    Route::get('/orders/{order}/print/labels', [CashRegisterController::class, 'printLabels'])->name('orders.print.labels');
});

// ---------------------------------------------------------------------
// ATELIER + ADMIN (suivi de production par scan)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'role:atelier|admin'])->group(function () {
    Route::get('/atelier', [WorkshopController::class, 'board'])->name('workshop.board');
    // POST d'un scan (JSON pour la mise à jour live du kanban)
    Route::post('/atelier/scan', [WorkshopController::class, 'scan'])->name('workshop.scan');
});

// ---------------------------------------------------------------------
// ADMIN (catalogue, stock, rapports, utilisateurs)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Catalogue des prestations
    Route::resource('services', ServiceController::class)->only(['index', 'store', 'update', 'destroy']);
    // Stock de fournitures
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::patch('/inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    // Rapports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});
