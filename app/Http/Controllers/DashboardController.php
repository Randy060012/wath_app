<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * ÉTAPE 2 — CONTRÔLEUR DashboardController (page d'accueil après login)
 * -----------------------------------------------------------------
 * Indicateurs temps réel : dossiers ouverts, prêts à rendre, retards,
 * CA du jour. Chaque chiffre est un simple agrégat scope + count/sum.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('dashboard', [
            // Dossiers ouverts (non livrés)
            'openOrders'   => Order::query()->open()->count(),
            // Commandes prêtes à rendre
            'readyOrders'  => Order::query()->where('status', OrderStatus::Pret->value)->count(),
            // Retards : dossiers ouverts dont la date promise est dépassée
            'overdueOrders' => Order::query()->open()->where('promised_at', '<', now())->count(),
            // Dépôts enregistrés aujourd'hui
            'todayOrders'  => Order::query()->today()->count(),
            // Chiffre d'affaires encaissé aujourd'hui
            'revenueToday' => (float) \App\Models\Payment::query()->whereDate('created_at', today())->sum('amount'),
            // Derniers dépôts (tableau)
            'recentOrders' => Order::query()->with(['client', 'items'])
                ->latest()->limit(8)->get(),
            'currency'     => config('pressing.currency', 'FCFA'),
        ]);
    }
}
