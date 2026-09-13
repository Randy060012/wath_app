<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * ÉTAPE 2 — CONTRÔLEUR ReportController (rapports & statistiques)
 * -----------------------------------------------------------------
 * 3 rapports filtrables par PLAGE DE DATES (presets : 7/30/90 jours,
 * mois en cours, mois dernier, ou dates explicites from/to) :
 *  - CA par jour (à partir des paiements réels) ;
 *  - Top prestations par CA (jointure order_items + services) ;
 *  - Répartition des encaissements par moyen de paiement.
 * Le bouton "Imprimer" de la vue utilise ?print=1 (classe body.print-mode).
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);

        // --- 1) CA quotidien — basé sur les PAIEMENTS réels ---
        $revenue = Payment::query()
            ->select(
                DB::raw('date(created_at) as day'),
                DB::raw('SUM(amount) as total')
            )
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // --- 2) Top prestations par CA ---
        $topServices = DB::table('order_items')
            ->join('services', 'services.id', '=', 'order_items.service_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                'services.name',
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('SUM(order_items.quantity) as qty')
            )
            ->groupBy('services.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        // --- 3) Répartition par moyen de paiement ---
        $byMethod = Payment::query()
            ->select('method', DB::raw('SUM(amount) as total'))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('method')
            ->get();

        return view('reports.index', [
            'revenue'     => $revenue,
            'topServices' => $topServices,
            'byMethod'    => $byMethod,
            'monthly'     => $this->monthlyRevenue(),
            'currency'    => config('pressing.currency', 'FCFA'),
            'from'        => $from,
            'to'          => $to,
            'preset'      => $preset,
            'print'       => $request->boolean('print'),
        ]);
    }

    /**
     * TENDANCE MENSUELLE : CA des 12 derniers mois (encaissements réels).
     * Retourne une collection [{ym, label, total}] triée chronologiquement
     * — alimente la courbe de tendance de la vue rapports.
     */
    private function monthlyRevenue()
    {
        // Agrégat par mois : syntaxe différente SQLite / MySQL, même résultat.
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $rows = Payment::query()
            ->select(
                DB::raw("{$monthExpr} as ym"),
                DB::raw('SUM(amount) as total')
            )
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('total', 'ym'); // {"2026-01": 125000, ...}

        // Complète les 12 mois : un mois sans encaissement = 0 (la courbe
        // ne doit jamais "trouer" — c'est une information, pas une absence).
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $ym = $date->format('Y-m');
            $months->push([
                'ym'    => $ym,
                'label' => self::FRENCH_MONTHS[(int) $date->format('n') - 1],
                'total' => (float) ($rows[$ym] ?? 0),
            ]);
        }

        return $months;
    }

    /** Libellés de mois courts indépendants de la locale PHP (fr). */
    private const FRENCH_MONTHS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

    /**
     * Résout la plage demandée. Sécurité : from <= to, jamais de plage
     * plus grande que 2 ans (anti-requête monstrueuse).
     *
     * @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon,2:string}
     */
    private function resolveRange(Request $request): array
    {
        $preset = (string) $request->query('preset', '30d');

        $presets = [
            '7d'   => [now()->subDays(6), now()],
            '30d'  => [now()->subDays(29), now()],
            '90d'  => [now()->subDays(89), now()],
            'month' => [now()->startOfMonth(), now()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
        ];

        if ($request->filled('from') || $request->filled('to')) {
            // Dates explicites (champs date du formulaire)
            $from = \Illuminate\Support\Carbon::parse($request->query('from', now()->subDays(29)));
            $to   = \Illuminate\Support\Carbon::parse($request->query('to', now()));
            $preset = 'custom';
        } else {
            [$from, $to] = $presets[$preset] ?? $presets['30d'];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from]; // utilisateur a inversé les dates
        }
        if ($from->diffInDays($to) > 730) {
            $to = $from->copy()->addDays(730);
        }

        return [$from, $to, $preset];
    }
}
