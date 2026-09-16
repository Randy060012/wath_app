@extends('layouts.app')

@section('title', 'Rapports')

@section('header_hint', "Chiffre d'affaires, top prestations, moyens de paiement.")

{{-- ÉTAPE 1 — RAPPORTS : graphique CA + PLAGE DE DATES + impression --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
            <x-icon name="trending-up" class="w-6 h-6 text-sky-700" />
            Rapports
        </h1>
        <div class="flex flex-wrap gap-2 no-print">
            {{-- EXPORT PDF : mêmes filtres que l'écran (période + agence) --}}
            <a href="{{ route('admin.reports.pdf', request()->only(['preset', 'from', 'to', 'agency'])) }}"
               class="btn-primary gap-2"
               title="Télécharger le rapport en PDF">
                <x-icon name="file-down" class="w-4 h-4" />
                Export PDF
            </a>
            <button type="button" onclick="window.print()" class="btn-ghost gap-2">
                <x-icon name="printer" class="w-4 h-4" />
                Imprimer
            </button>
        </div>
    </div>

    {{-- Sélecteur de plage (GET) — presets, dates explicites et AGENCIE (super-admin) --}}
    <form method="GET" class="card no-print mb-6 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label class="label" for="preset">Période</label>
            <select name="preset" id="preset" class="input !w-44">
                <option value="7d" @selected($preset === '7d')>7 derniers jours</option>
                <option value="30d" @selected($preset === '30d')>30 derniers jours</option>
                <option value="90d" @selected($preset === '90d')>90 derniers jours</option>
                <option value="month" @selected($preset === 'month')>Mois en cours</option>
                <option value="last_month" @selected($preset === 'last_month')>Mois dernier</option>
                <option value="custom" @selected($preset === 'custom')>Dates personnalisées</option>
            </select>
        </div>
        <div>
            <label class="label" for="from">Du</label>
            <input type="date" name="from" id="from" value="{{ $from->format('Y-m-d') }}" class="input !w-40">
        </div>
        <div>
            <label class="label" for="to">Au</label>
            <input type="date" name="to" id="to" value="{{ $to->format('Y-m-d') }}" class="input !w-40">
        </div>
        {{-- MULTI-TENANT : filtre par agence (super-admin uniquement) --}}
        @if ($filterAgency['choices'] && $agencies->isNotEmpty())
            <div>
                <label class="label" for="agency">Agence</label>
                <select name="agency" id="agency" class="input !w-52">
                    <option value="all" @selected(!request()->filled('agency') || request('agency') === 'all')>Toutes les agences</option>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected(request('agency') == $agency->id)>
                            {{ $agency->name }} ({{ $agency->code }})
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <button class="btn-primary gap-2">
            <x-icon name="refresh-cw" class="w-4 h-4" />
            Appliquer
        </button>
    </form>

    {{-- ==================================================================
         TENDANCE MENSUELLE — courbe SVG des 12 derniers mois (encaissements).
         Svg 100×100 unités mis à l'échelle par viewBox : pas de librairie,
         responsive, et les points/labels sont positionnés en pourcentages.
         ================================================================== --}}
    <div class="card mb-6 p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-slate-900">Tendance mensuelle — 12 derniers mois ({{ $currency }})</h2>
            @php
                $maxMonthly = max($monthly->max('total'), 1);
                $first = $monthly->first();
                $last = $monthly->last();
                $growth = $first['total'] > 0
                    ? round(($last['total'] - $first['total']) / $first['total'] * 100)
                    : ($last['total'] > 0 ? 100 : 0);
                $isUp = $growth >= 0;
            @endphp
            {{-- Variation sur la période (vs il y a 12 mois) --}}
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold
                         {{ $isUp ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                <x-icon name="{{ $isUp ? 'trending-up' : 'trending-down' }}" class="h-3.5 w-3.5" />
                {{ $isUp ? '+' : '' }}{{ $growth }} % sur 12 mois
            </span>
        </div>

        @php
            $W = 100; $H = 42; $PAD = 2;      // viewBox unités
            $n = $monthly->count();
            // Coordonnées des points (x régulier, y proportionnel au CA)
            $pts = $monthly->map(function ($m, $i) use ($W, $H, $PAD, $maxMonthly, $n) {
                $x = $n > 1 ? $PAD + $i * ($W - 2 * $PAD) / ($n - 1) : $W / 2;
                $y = $H - $PAD - ($m['total'] / $maxMonthly) * ($H - 2 * $PAD);
                return ['x' => $x, 'y' => $y, ...$m];
            });
            // Ligne + aire (l'aire ferme sur l'axe bas)
            $linePath = $pts->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L') . round($p['x'], 2) . ' ' . round($p['y'], 2))->join(' ');
            $areaPath = $linePath . " L {$W} {$H} L 0 {$H} Z";
        @endphp
        <div class="relative">
            <svg viewBox="0 0 100 42" preserveAspectRatio="none" class="h-44 w-full" role="img" aria-label="Courbe de CA mensuel sur 12 mois">
                {{-- Grille horizontale (4 lignes) --}}
                @for ($g = 1; $g <= 3; $g++)
                    <line x1="0" y1="{{ 42 * $g / 4 }}" x2="100" y2="{{ 42 * $g / 4 }}" stroke="#e2e8f0" stroke-width="0.3" />
                @endfor
                {{-- Aire + courbe --}}
                <path d="{{ $areaPath }}" fill="rgb(3 105 161 / .10)" />
                <path d="{{ $linePath }}" fill="none" stroke="#0369a1" stroke-width="0.8"
                      stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                {{-- Points avec infobulle SVG : un <g> par mois, survol CSS.
                     L'infobulle est un <g> rect+text ; comme le viewBox est
                     étiré (preserveAspectRatio=none), le texte est rendu
                     dans une FOREIGN IMAGE (aucune déformation) : plus
                     simple — l'infobulle vit dans un overlay HTML aligné. --}}
                @foreach ($pts as $i => $p)
                    <g class="dt-point-group">
                        {{-- Zone de survol généreuse (invisible) --}}
                        <rect x="{{ $p['x'] - 3 }}" y="0" width="6" height="42" fill="transparent" />
                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="1.1" fill="#0369a1" class="dt-point" />
                        {{-- Ligne de lecture verticale au survol --}}
                        <line x1="{{ $p['x'] }}" y1="{{ $p['y'] }}" x2="{{ $p['x'] }}" y2="42"
                              stroke="#0369a1" stroke-width="0.3" class="dt-point-guide" />
                    </g>
                @endforeach
            </svg>
            {{-- Infobulles HTML alignées sur les points : le survol d'un
                 .dt-point-group révèle l'étiquette correspondante (JS léger,
                 15 lignes en fin de page — les deux listes sont appariées
                 par leur index). --}}
            <div class="dt-tips pointer-events-none absolute inset-x-0 top-0 h-44">
                @foreach ($pts as $i => $p)
                    <span class="dt-month-tip" style="left: {{ $p['x'] }}%; top: {{ max(0, $p['y'] / 42 * 100 - 14) }}%"
                          data-tip-for="{{ $i }}">{{ $p['label'] }} {{ substr($p['ym'], 0, 4) }} : {{ number_format($p['total'], 0, ',', ' ') }} {{ $currency }}</span>
                @endforeach
            </div>
            {{-- Mois affichés sous l'axe (étiquettes espacées) --}}
            <div class="mt-1 flex justify-between px-1 text-[10px] font-medium text-slate-400">
                @foreach ($monthly->values() as $i => $m)
                    <span @if ($i % 2 !== 0) class="hidden sm:inline" @endif>{{ $m['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        /* Interaction courbe : survol d'un point (SVG) ↔ infobulle (HTML).
           Les deux listes ont le même ordre ; l'appariement se fait par
           index via data-tip-for. Sans dépendance, ~15 lignes. */
        document.addEventListener('DOMContentLoaded', () => {
            const tips = document.querySelectorAll('.dt-month-tip');
            const show = (i) => tips[i]?.classList.add('is-active');
            const hide = (i) => tips[i]?.classList.remove('is-active');

            document.querySelectorAll('.dt-point-group').forEach((g, i) => {
                g.addEventListener('mouseenter', () => show(i));
                g.addEventListener('mouseleave', () => hide(i));
            });
        });
    </script>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Graphique CA : barres SVG générées en Blade --}}
        <div class="card p-4 lg:col-span-2">
            <h2 class="mb-4 font-semibold text-slate-900">
                Chiffre d'affaires — {{ $filterAgency['label'] }} · {{ $from->format('d/m') }} au {{ $to->format('d/m/Y') }} ({{ $currency }})
            </h2>
            @php
                $max = max($revenue->max('total') ?? 1, 1);
                $totalRevenue = $revenue->sum('total');
            @endphp
            <p class="mb-4 text-3xl font-bold text-sky-700">
                {{ number_format($totalRevenue, 0, ',', ' ') }} <span class="text-base text-slate-400">{{ $currency }}</span>
            </p>
            <div class="flex h-48 items-end gap-1">
                @forelse ($revenue as $day)
                    <div class="group relative flex-1">
                        {{-- Hauteur proportionnelle au CA du jour --}}
                        <div class="w-full rounded-t bg-sky-600/80 transition-colors group-hover:bg-sky-800"
                             style="height: {{ max(4, round($day->total / $max * 100)) }}%"></div>
                        {{-- Infobulle au survol --}}
                        <span class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-0.5 text-[10px] text-white group-hover:block">
                            {{ \Illuminate\Support\Carbon::parse($day->day)->format('d/m') }} · {{ number_format($day->total, 0, ',', ' ') }}
                        </span>
                    </div>
                @empty
                    <p class="w-full py-10 text-center text-sm text-slate-400">Pas encore d'encaissement sur cette période.</p>
                @endforelse
            </div>
        </div>

        {{-- Répartition par moyen de paiement --}}
        <div class="card p-4">
            <h2 class="mb-4 font-semibold text-slate-900">Moyens de paiement</h2>
            <ul class="space-y-3">
                @forelse ($byMethod as $m)
                    @php $total = $byMethod->sum('total'); $pct = $total > 0 ? round($m->total / $total * 100) : 0; @endphp
                    <li>
                        <div class="mb-1 flex justify-between text-sm">
                            {{-- $m->method est un ENUM (cast Payment) : on utilise son libellé --}}
                            <span class="font-medium">{{ is_object($m->method) ? $m->method->label() : \App\Enums\PaymentMethod::from($m->method)->label() }}</span>
                            <span class="text-slate-500">{{ number_format($m->total, 0, ',', ' ') }} · {{ $pct }} %</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-sky-600" style="width: {{ $pct }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">Aucune donnée.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Top prestations --}}
    <div class="card mt-6 overflow-hidden">
        <h2 class="px-4 py-3 font-semibold text-slate-900">Top prestations (CA cumulé)</h2>
        <table class="table-simple">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Prestation</th>
                    <th class="!text-right">Quantité</th>
                    <th class="!text-right">CA</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topServices as $i => $s)
                    <tr>
                        <td class="text-slate-400">{{ $i + 1 }}</td>
                        <td class="font-medium">{{ $s->name }}</td>
                        <td class="text-right">{{ $s->qty }}</td>
                        <td class="text-right font-semibold">{{ number_format($s->revenue, 0, ',', ' ') }} {{ $currency }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Aucune vente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ============================================================
         MULTI-TENANT — COMPARATIF PAR AGENCE (super-admin, vue groupe)
         Une ligne par agence : CA, dépôts, clients, panier moyen,
         prestation n°1. Permet de comparer les businesses d'un coup d'œil.
         ============================================================ --}}
    @if ($agencyComparison->isNotEmpty())
        <div class="card mt-6 overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">
                <x-icon name="building-2" class="w-4 h-4 text-sky-700" />
                Comparatif par agence
                <span class="text-sm font-normal text-slate-400">
                    · {{ $from->format('d/m') }} au {{ $to->format('d/m/Y') }}
                </span>
            </h2>
            <table class="table-simple">
                <thead>
                    <tr>
                        <th>Agence</th>
                        <th class="!text-right">CA encaissé</th>
                        <th class="!text-right">Dépôts</th>
                        <th class="!text-right">Nouveaux clients</th>
                        <th class="!text-right">Panier moyen</th>
                        <th>Prestation n°1</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxRevenue = max($agencyComparison->max('revenue'), 1); @endphp
                    @forelse ($agencyComparison as $row)
                        <tr>
                            <td>
                                <span class="font-semibold">{{ $row->name }}</span>
                                <span class="block font-mono text-xs text-slate-400">{{ $row->code }}</span>
                            </td>
                            <td class="text-right">
                                <span class="font-semibold">{{ number_format($row->revenue, 0, ',', ' ') }}</span>
                                {{-- Barre proportionnelle : comparaison visuelle immédiate --}}
                                <span class="mt-1 block h-1.5 w-28 overflow-hidden rounded-full bg-slate-100 ml-auto">
                                    <span class="block h-full rounded-full bg-sky-600"
                                          style="width: {{ round(100 * $row->revenue / $maxRevenue) }}%"></span>
                                </span>
                            </td>
                            <td class="text-right">{{ $row->orders }}</td>
                            <td class="text-right">{{ $row->clients }}</td>
                            <td class="text-right">{{ number_format($row->avg_ticket, 0, ',', ' ') }}</td>
                            <td class="text-sm text-slate-500">{{ $row->top_service ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucune agence.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
