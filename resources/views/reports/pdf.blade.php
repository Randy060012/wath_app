{{-- Vue DÉDIÉE à l'export PDF (dompdf) : CSS inline simple, pas de
     JS, pas de SVG (dompdf ne les supporte pas bien), polices système.
     Les mêmes données que l'écran rapports, mêmes filtres. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rapport — {{ $agencyLabel }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; color: #0f172a; font-size: 11px; }
    .head { border-bottom: 2px solid #0369a1; padding-bottom: 8px; margin-bottom: 14px; }
    .head h1 { font-size: 17px; color: #0369a1; }
    .head .meta { color: #64748b; font-size: 9.5px; margin-top: 2px; }
    h2 { font-size: 12.5px; margin: 16px 0 6px; color: #0c4a6e;
         border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; margin: 6px 0; }
    th { background: #f1f5f9; text-align: left; padding: 4px 6px;
         font-size: 9px; text-transform: uppercase; letter-spacing: .04em;
         border-bottom: 1px solid #cbd5e1; }
    td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
    .r { text-align: right; }
    .total { font-size: 15px; font-weight: bold; color: #0369a1; margin: 4px 0 8px; }
    .muted { color: #64748b; }
    .empty { color: #94a3b8; font-style: italic; }
    .bars { margin: 10px 0 4px; }
    .bar-row { margin-bottom: 5px; }
    .bar-label { font-size: 9px; color: #475569; margin-bottom: 1px; }
    .bar-track { background: #f1f5f9; height: 8px; }
    .bar-fill { background: #0369a1; height: 8px; }
    .footer { margin-top: 22px; border-top: 1px solid #e2e8f0;
              padding-top: 6px; font-size: 8.5px; color: #94a3b8; }
    @page { margin: 15mm 12mm; }
</style>
</head>
<body>

    <div class="head">
        <h1>Rapport d'activité — Pressing Pro</h1>
        <div class="meta">
            Périmètre : <strong>{{ $agencyLabel }}</strong>
            · Période : {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}
        </div>
    </div>

    {{-- ================= CA DE LA PÉRIODE ================= --}}
    <h2>Chiffre d'affaires encaissé</h2>
    <p class="total">
        {{ number_format($totalRevenue, 0, ',', ' ') }} {{ $currency }}
        <span class="muted" style="font-size:10px; font-weight:normal;">sur la période</span>
    </p>

    @if ($revenue->isNotEmpty())
        {{-- Barres CSS (dompdf ne supporte pas SVG) --}}
        @php $max = max($revenue->max('total'), 1); @endphp
        <div class="bars">
            @foreach ($revenue as $day)
                <div class="bar-row">
                    <div class="bar-label">
                        {{ \Illuminate\Support\Carbon::parse($day->day)->format('d/m') }}
                        — {{ number_format($day->total, 0, ',', ' ') }} {{ $currency }}
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: {{ max(2, round(100 * $day->total / $max)) }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="empty">Aucun encaissement sur cette période.</p>
    @endif

    {{-- ================= MOYENS DE PAIEMENT ================= --}}
    <h2>Répartition par moyen de paiement</h2>
    @if ($byMethod->isNotEmpty())
        @php $sum = max($byMethod->sum('total'), 1); @endphp
        <table>
            <thead>
                <tr><th>Moyen</th><th class="r">Montant</th><th class="r">Part</th></tr>
            </thead>
            <tbody>
                @foreach ($byMethod as $m)
                    <tr>
                        <td>{{ is_object($m->method) ? $m->method->label() : \App\Enums\PaymentMethod::from($m->method)->label() }}</td>
                        <td class="r">{{ number_format($m->total, 0, ',', ' ') }} {{ $currency }}</td>
                        <td class="r">{{ round(100 * $m->total / $sum) }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Aucune donnée.</p>
    @endif

    {{-- ================= TOP PRESTATIONS ================= --}}
    <h2>Top prestations</h2>
    @if ($topServices->isNotEmpty())
        <table>
            <thead>
                <tr><th>#</th><th>Prestation</th><th class="r">Quantité</th><th class="r">CA</th></tr>
            </thead>
            <tbody>
                @foreach ($topServices as $i => $s)
                    <tr>
                        <td class="muted">{{ $i + 1 }}</td>
                        <td>{{ $s->name }}</td>
                        <td class="r">{{ $s->qty }}</td>
                        <td class="r">{{ number_format($s->revenue, 0, ',', ' ') }} {{ $currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Aucune vente sur la période.</p>
    @endif

    {{-- ================= COMPARATIF PAR AGENCE (super-admin) ============ --}}
    @if ($agencyComparison->isNotEmpty())
        <h2>Comparatif par agence</h2>
        <table>
            <thead>
                <tr>
                    <th>Agence</th>
                    <th class="r">CA encaissé</th>
                    <th class="r">Dépôts</th>
                    <th class="r">Nouveaux clients</th>
                    <th class="r">Panier moyen</th>
                    <th>Prestation n°1</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agencyComparison as $row)
                    <tr>
                        <td>{{ $row->name }} <span class="muted">({{ $row->code }})</span></td>
                        <td class="r">{{ number_format($row->revenue, 0, ',', ' ') }}</td>
                        <td class="r">{{ $row->orders }}</td>
                        <td class="r">{{ $row->clients }}</td>
                        <td class="r">{{ number_format($row->avg_ticket, 0, ',', ' ') }}</td>
                        <td>{{ $row->top_service ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Rapport généré le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedFor }}
        — périmètre : {{ $agencyLabel }}. Document interne, ne pas diffuser.
    </div>
</body>
</html>
