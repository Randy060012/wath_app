<?php

namespace App\Services;

use App\Models\Agency;
use App\Support\AgencyContext;
use Dompdf\Dompdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ÉTAPE 3 — EXPORT PDF DES RAPPORTS
 * -----------------------------------------------------------------
 * Génère le PDF du rapport (même données que l'écran rapports) via
 * dompdf : rendu HTML → PDF 100 % PHP, aucun binaire côté serveur
 * (cohérent avec la stratégie « impression navigateur » du projet).
 *
 * Isolation multi-tenant : les données suivent EXACTEMENT le filtre
 * de l'écran (scope agence automatique ; super-admin = filtre choisi
 * ?agency=all|ID). Le comparatif par agence n'apparaît QUE pour le
 * super-admin, comme sur l'écran.
 */
class ReportPdfService
{
    /**
     * Construit le PDF du rapport pour la plage/filtre donnés.
     *
     * @param  Carbon  $from  début de plage
     * @param  Carbon  $to    fin de plage
     * @param  string  $label libellé du filtre agence (ex : « Toutes les agences »)
     *
     * @return \Dompdf\Dompdf instance prête pour stream()/output()
     */
    public function build(Carbon $from, Carbon $to, string $label): Dompdf
    {
        $start = $from->copy()->startOfDay();
        $end   = $to->copy()->endOfDay();

        // --- CA quotidien (Eloquent : scopé par agence automatiquement) ---
        $revenue = \App\Models\Payment::query()
            ->selectRaw('date(created_at) as day, SUM(amount) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // --- Top prestations (requête brute : filtre agence manuel) -------
        $topServices = DB::table('order_items')
            ->join('services', 'services.id', '=', 'order_items.service_id')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->when(AgencyContext::id(), fn ($q, $id) => $q->where('order_items.agency_id', $id))
            ->selectRaw('services.name, SUM(order_items.line_total) as revenue, SUM(order_items.quantity) as qty')
            ->groupBy('services.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        // --- Répartition par moyen de paiement ---------------------------
        $byMethod = \App\Models\Payment::query()
            ->selectRaw('method, SUM(amount) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        // --- Comparatif par agence (super-admin uniquement) ---------------
        $agencyComparison = AgencyContext::id() === null
            ? $this->agencyComparison($start, $end)
            : collect();

        // Rendu HTML (CSS inline compatible dompdf) puis conversion PDF.
        $html = view('reports.pdf', [
            'from'        => $from,
            'to'          => $to,
            'agencyLabel' => $label,
            'revenue'     => $revenue,
            'topServices' => $topServices,
            'byMethod'    => $byMethod,
            'agencyComparison' => $agencyComparison,
            'totalRevenue'     => (float) $revenue->sum('total'),
            'currency'         => config('pressing.currency', 'FCFA'),
            'generatedFor'     => auth()->user()?->name ?? '—',
            'generatedAt'      => now(),
        ])->render();

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    /**
     * Ligne par agence : CA, dépôts, clients, panier moyen, top prestation.
     * (Même logique que ReportController — super-admin seulement.)
     */
    private function agencyComparison($start, $end)
    {
        $revenues = \App\Models\Payment::query()
            ->selectRaw('agency_id, SUM(amount) as revenue')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('agency_id')
            ->pluck('revenue', 'agency_id');

        $orders = DB::table('orders')
            ->selectRaw('agency_id, COUNT(*) as nb')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('agency_id')
            ->pluck('nb', 'agency_id');

        $clients = DB::table('clients')
            ->selectRaw('agency_id, COUNT(*) as nb')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('agency_id')
            ->pluck('nb', 'agency_id');

        $topByAgency = DB::table('order_items')
            ->join('services', 'services.id', '=', 'order_items.service_id')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->selectRaw('order_items.agency_id, services.name, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.agency_id', 'services.name')
            ->get()
            ->groupBy('agency_id')
            ->map(fn ($rows) => $rows->sortByDesc('revenue')->first()->name ?? null);

        return Agency::query()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(function (Agency $agency) use ($revenues, $orders, $clients, $topByAgency) {
                $revenue  = (float) ($revenues[$agency->id] ?? 0);
                $nbOrders = (int) ($orders[$agency->id] ?? 0);

                return (object) [
                    'name'        => $agency->name,
                    'code'        => $agency->code,
                    'revenue'     => $revenue,
                    'orders'      => $nbOrders,
                    'clients'     => (int) ($clients[$agency->id] ?? 0),
                    'avg_ticket'  => $nbOrders > 0 ? round($revenue / $nbOrders, 0) : 0,
                    'top_service' => $topByAgency[$agency->id] ?? null,
                ];
            });
    }
}
