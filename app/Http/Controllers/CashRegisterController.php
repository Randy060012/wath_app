<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\LabelPrintingService;

/**
 * ÉTAPE 2 — CONTRÔLEUR CashRegisterController (écran CAISSE)
 * -----------------------------------------------------------------
 * - dashboard()   : indicateurs du jour, retraits attendus, retards,
 *                   encaissements détaillés (répartition par moyen) ;
 * - printTicket() : HTML autonome du ticket 80 mm (impression navigateur) ;
 * - printLabels() : planche d'étiquettes code-barres/QR des articles.
 */
class CashRegisterController extends Controller
{
    public function __construct(private readonly LabelPrintingService $printer)
    {
    }

    /** Tableau de bord de la caisse (tout est borné à AUJOURD'HUI). */
    public function dashboard()
    {
        $today = today();

        // ------------------------------------------------------------------
        // REPRISES : ce que la caisse doit traiter aujourd'hui
        // ------------------------------------------------------------------
        // Déposés aujourd'hui (toutes commandes du jour)
        $todayOrders = Order::query()->today()->with('client')->latest()->get();

        // Prêts À ENCAISSER : tous les articles prêts + solde > 0
        $readyToCollect = Order::query()->open()
            ->where('status', OrderStatus::Pret)
            ->with('client')->orderBy('promised_at')->get()
            // balance_due est un ACCESSEUR (net - payé) : filtrage en mémoire
            ->filter(fn ($o) => $o->balance_due > 0)
            ->values();

        // Retards : dossiers ouverts dont la date promise est dépassée
        $overdue = Order::query()->open()->where('promised_at', '<', now())
            ->orderBy('promised_at')->with('client')->get();

        // ------------------------------------------------------------------
        // ENCAISSEMENTS du jour : total, répartition par moyen, détail
        // ------------------------------------------------------------------
        $paymentsToday = Payment::query()->whereDate('created_at', $today)
            ->with('order.client')->latest()->get();

        $byMethod = ['cash' => 0, 'mobile_money' => 0, 'card' => 0];
        foreach ($paymentsToday as $payment) {
            $byMethod[$payment->method->value] = ($byMethod[$payment->method->value] ?? 0)
                + (float) $payment->amount;
        }
        ksort($byMethod);

        // À ENCAISSER encore aujourd'hui : somme des soldes des dossiers prêts
        $outstanding = (float) $readyToCollect->sum('balance_due');

        // ------------------------------------------------------------------
        // SOLDES À RECOUVRER : dossiers OUVERTS (toutes dates) avec reste à
        // payer — paiements partiels à compléter, même après plusieurs jours.
        // ------------------------------------------------------------------
        $withBalance = Order::query()->open()
            ->with('client')
            ->orderBy('created_at')
            ->get()
            ->filter(fn ($o) => $o->balance_due > 0)
            ->values();

        $outstandingAll = (float) $withBalance->sum('balance_due');

        return view('caisse.dashboard', [
            'todayOrders'    => $todayOrders,
            'readyToCollect' => $readyToCollect,
            'overdue'        => $overdue,
            'paymentsToday'  => $paymentsToday,
            'byMethod'       => $byMethod,
            'outstanding'    => $outstanding,
            'withBalance'    => $withBalance,
            'outstandingAll' => $outstandingAll,
            'cashToday'      => (float) $paymentsToday->sum('amount'),
            'currency'       => config('pressing.currency', 'FCFA'),
        ]);
    }

    /** Ticket de caisse 80 mm (HTML autonome → impression navigateur). */
    public function printTicket(Order $order)
    {
        return response($this->printer->ticketHtml($order))
            ->header('Content-Type', 'text/html');
    }

    /** Planche d'étiquettes articles (code-barres + QR par vêtement). */
    public function printLabels(Order $order)
    {
        return response($this->printer->labelsHtml($order))
            ->header('Content-Type', 'text/html');
    }
}
