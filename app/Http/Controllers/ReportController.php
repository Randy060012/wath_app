<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Payment;
use App\Support\AgencyContext;
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
 *
 * MULTI-TENANT :
 *  - un utilisateur d'agence voit UNIQUEMENT les chiffres de son agence
 *    (scoping automatique via AgencyContext) ;
 *  - le SUPER-ADMIN dispose d'un filtre « Toutes les agences / agence… »
 *    (?agency=ID ou toutes) ET d'un tableau comparatif par agence
 *    (CA, dépôts, clients, encaissements moyens, top prestation).
 */
class ReportController extends Controller
{
    public function __construct(private readonly \App\Services\ReportPdfService $pdfService)
    {
    }

    public function index(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);

        // -----------------------------------------------------------------
        // MULTI-TENANT — filtre agence (super-admin uniquement).
        //   null            = contexte courant (groupe = tout / agence = la sienne)
        //   'all'           = explicitement toutes les agences (vue groupe)
        //   ID              = une agence précise (comparaison ciblée)
        // -----------------------------------------------------------------
        $filterAgency = $this->resolveAgencyFilter($request);

        // --- 1) CA quotidien — basé sur les PAIEMENTS réels --------------
        // (Payment est Eloquent + trait BelongsToAgency : en contexte
        //  agence le filtre est appliqué par le scope global ; en vue
        //  groupe on ajoute explicitement le filtre choisi.)
        $revenue = Payment::query()
            ->select(
                DB::raw('date(created_at) as day'),
                DB::raw('SUM(amount) as total')
            )
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($filterAgency['applied'], fn ($q, $agencyId) => $q->where('payments.agency_id', $agencyId))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // --- 2) Top prestations par CA (jointure brute : filtre manuel) ---
        $topServices = DB::table('order_items')
            ->join('services', 'services.id', '=', 'order_items.service_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when(
                $filterAgency['applied'],
                fn ($q, $agencyId) => $q->where('order_items.agency_id', $agencyId)
            )
            ->select(
                'services.name',
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('SUM(order_items.quantity) as qty')
            )
            ->groupBy('services.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        // --- 3) Répartition par moyen de paiement ------------------------
        $byMethod = Payment::query()
            ->select('method', DB::raw('SUM(amount) as total'))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($filterAgency['applied'], fn ($q, $agencyId) => $q->where('payments.agency_id', $agencyId))
            ->groupBy('method')
            ->get();

        // -----------------------------------------------------------------
        // MULTI-TENANT — COMPARATIF PAR AGENCE (super-admin en vue groupe).
        // Une ligne par agence : CA, dépôts, clients, panier moyen,
        // top prestation — pour comparer d'un coup d'œil les businesses.
        // -----------------------------------------------------------------
        $agencyComparison = $this->buildAgencyComparison($from, $to);

        return view('reports.index', [
            'revenue'          => $revenue,
            'topServices'      => $topServices,
            'byMethod'         => $byMethod,
            'monthly'          => $this->monthlyRevenue(),
            'agencyComparison' => $agencyComparison,
            'agencies'         => $this->availableAgencies(),
            'filterAgency'     => $filterAgency,
            'currency'         => config('pressing.currency', 'FCFA'),
            'from'             => $from,
            'to'               => $to,
            'preset'           => $preset,
            'print'            => $request->boolean('print'),
        ]);
    }

    /**
     * Résout le filtre agence demandé.
     *
     * @return array{applied: ?int, choices: bool, label: string}
     *         applied = ID d'agence à filtrer (null = tout) ;
     *         choices = afficher le sélecteur (super-admin) ;
     *         label   = libellé affiché.
     */
    private function resolveAgencyFilter(Request $request): array
    {
        $contextId = AgencyContext::id();

        // Utilisateur d'agence : forcé sur son agence (déjà scopé).
        if ($contextId !== null) {
            $agency = Agency::find($contextId);

            return [
                'applied' => null,       // le scope global fait déjà le travail
                'choices' => false,
                'label'   => $agency?->name ?? 'Agence',
            ];
        }

        // Super-admin (vue groupe) : suit ?agency=all|ID|absent.
        $param = $request->query('agency');

        if ($param !== null && $param !== '' && $param !== 'all' && ctype_digit((string) $param)) {
            $agency = Agency::find((int) $param);

            if ($agency !== null) {
                return [
                    'applied' => $agency->id,
                    'choices' => true,
                    'label'   => $agency->name,
                ];
            }
        }

        return [
            'applied' => null,
            'choices' => true,
            'label'   => 'Toutes les agences',
        ];
    }

    /**
     * Liste des agences pour le sélecteur (super-admin uniquement,
     * sinon collection vide : l'utilisateur d'agence n'a pas le choix).
     *
     * @return \Illuminate\Support\Collection<int, Agency>
     */
    private function availableAgencies()
    {
        if (AgencyContext::id() !== null) {
            return collect();
        }

        return Agency::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * Construit le tableau comparatif : UNE ligne par agence avec, sur la
     * plage sélectionnée : CA encaissé, nb de dépôts, nb de clients,
     * panier moyen et prestation n°1.
     *
     * @return \Illuminate\Support\Collection<int, object{
     *     id:int, code:string, name:string, revenue:float, orders:int,
     *     clients:int, avg_ticket:float, top_service:?string
     * }>
     */
    private function buildAgencyComparison($from, $to)
    {
        // Vue groupe uniquement (super-admin) : un utilisateur d'agence
        // n'a rien à comparer — il voit ses propres chiffres en haut.
        if (AgencyContext::id() !== null) {
            return collect();
        }

        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        // CA + dépôts par agence (encaissements réels sur la plage)
        $revenues = Payment::query()
            ->selectRaw('agency_id, SUM(amount) as revenue')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('agency_id')
            ->pluck('revenue', 'agency_id');

        // Dépôts créés sur la plage, par agence
        $orders = DB::table('orders')
            ->selectRaw('agency_id, COUNT(*) as nb')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('agency_id')
            ->pluck('nb', 'agency_id');

        // Clients enregistrés sur la plage, par agence
        $clients = DB::table('clients')
            ->selectRaw('agency_id, COUNT(*) as nb')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('agency_id')
            ->pluck('nb', 'agency_id');

        // Top prestation par agence (la plus rentable sur la plage)
        $topByAgency = DB::table('order_items')
            ->join('services', 'services.id', '=', 'order_items.service_id')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->selectRaw("order_items.agency_id, services.name, SUM(order_items.line_total) as revenue")
            ->groupBy('order_items.agency_id', 'services.name')
            ->get()
            ->groupBy('agency_id')
            ->map(fn ($rows) => $rows->sortByDesc('revenue')->first()->name ?? null);

        // Panier moyen = CA / dépôts (par agence, calculé à l'affichage)
        return Agency::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function (Agency $agency) use ($revenues, $orders, $clients, $topByAgency) {
                $revenue = (float) ($revenues[$agency->id] ?? 0);
                $nbOrders = (int) ($orders[$agency->id] ?? 0);

                return (object) [
                    'id'         => $agency->id,
                    'code'       => $agency->code,
                    'name'       => $agency->name,
                    'revenue'    => $revenue,
                    'orders'     => $nbOrders,
                    'clients'    => (int) ($clients[$agency->id] ?? 0),
                    'avg_ticket' => $nbOrders > 0 ? round($revenue / $nbOrders, 0) : 0,
                    'top_service' => $topByAgency[$agency->id] ?? null,
                ];
            });
    }

    /**
     * EXPORT PDF du rapport : mêmes filtres que l'écran (preset, from/to,
     * agency), mêmes données, mise en page A4 dédiée dompdf.
     * Téléchargement direct (Content-Disposition attachment).
     */
    public function pdf(Request $request)
    {
        [$from, $to, $preset] = $this->resolveRange($request);

        $filter = $this->resolveAgencyFilter($request);

        $dompdf = $this->pdfService->build($from, $to, $filter['label']);

        $periode = $from->format('Ymd') . '-' . $to->format('Ymd');

        return \Illuminate\Support\Facades\Response::streamDownload(
            fn () => $dompdf->stream('rapport.pdf', ['Attachment' => false]),
            "rapport-pressing-{$periode}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
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
