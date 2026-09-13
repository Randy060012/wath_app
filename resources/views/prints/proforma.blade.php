<!DOCTYPE html>
{{-- SPÉCIFICATIONS C — PROFORMA IMPRIMABLE (A4, HTML autonome) --}}
{{-- 100 % autonome (styles inline) : ouvert dans un onglet, window.print()
     permet l'impression papier OU l'enregistrement en PDF. --}}
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $proforma->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; background: #fff; padding: 24px; }
        .doc { max-width: 800px; margin: 0 auto; }
        .btn-print {
            position: fixed; top: 12px; right: 12px; padding: 8px 16px;
            background: #0c4a6e; color: #fff; border: 0; border-radius: 6px;
            font-family: system-ui; cursor: pointer;
        }
        @media print { .btn-print { display: none; } body { padding: 0; } }
        @page { margin: 15mm; }

        header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
        .company h1 { font-size: 20px; color: #0c4a6e; }
        .company p { font-size: 12px; color: #64748b; }
        .docmeta { text-align: right; font-size: 13px; }
        .docmeta .num { font-size: 18px; font-weight: 700; color: #0369a1; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }

        .parties { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: 13px; }
        .parties h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; margin-bottom: 4px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th { background: #f0f9ff; color: #0c4a6e; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        tbody td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; }
        .r { text-align: right; white-space: nowrap; }

        .totals { margin-top: 18px; margin-left: auto; width: 300px; font-size: 13px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { border-top: 2px solid #0c4a6e; margin-top: 6px; padding-top: 8px; font-size: 16px; font-weight: 700; color: #0369a1; }

        .footer { margin-top: 36px; font-size: 11px; color: #94a3b8; text-align: center; }
        .notes { margin-top: 20px; font-size: 12px; color: #475569; background: #f8fafc; padding: 10px 12px; border-radius: 8px; }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimer / PDF</button>

    <div class="doc">
        <header>
            <div class="company">
                <h1>{{ config('app.name', 'Pressing Pro') }}</h1>
                <p>{{ config('app.address', 'Pressing — quartier du marché') }}</p>
                <p>Tél : {{ config('app.phone', '00 000 00 00 00') }}</p>
            </div>
            <div class="docmeta">
                <p class="num">PROFORMA {{ $proforma->number }}</p>
                <p>Émis le {{ $proforma->issued_at->format('d/m/Y') }}</p>
                <p>Valable jusqu'au <strong>{{ $proforma->valid_until->format('d/m/Y') }}</strong></p>
                <span class="badge" style="background:#f0f9ff;color:#0c4a6e;">Document non comptable — devis</span>
            </div>
        </header>

        <div class="parties">
            <div>
                <h2>Destinataire</h2>
                <p><strong>{{ $proforma->client->name }}</strong></p>
                @if ($proforma->client->phone) <p>{{ $proforma->client->phone }}</p> @endif
                @if ($proforma->client->address) <p>{{ $proforma->client->address }}</p> @endif
                @if ($proforma->client->email) <p>{{ $proforma->client->email }}</p> @endif
            </div>
            <div style="text-align:right;">
                <h2>Émis par</h2>
                <p>{{ $proforma->author?->name ?? '—' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Prestation</th>
                    <th>Unité</th>
                    <th class="r">Qté</th>
                    <th class="r">P.U.</th>
                    <th class="r">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($proforma->items as $item)
                    <tr>
                        <td>{{ $item->label }}</td>
                        <td>{{ $item->pricing_unit === 'kg' ? 'au kg' : ($item->pricing_unit === 'forfait' ? 'forfait' : 'pièce') }}</td>
                        <td class="r">{{ $item->quantity }}</td>
                        <td class="r">{{ number_format($item->unit_price, 0, ',', ' ') }}</td>
                        <td class="r">{{ number_format($item->line_total, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Sous-total</span><span>{{ number_format($proforma->total_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}</span></div>
            @if ($proforma->discount_amount > 0)
                <div class="row"><span>Remise</span><span>- {{ number_format($proforma->discount_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}</span></div>
            @endif
            <div class="row grand"><span>NET À PAYER</span><span>{{ number_format($proforma->net_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}</span></div>
        </div>

        @if ($proforma->notes)
            <div class="notes">{{ $proforma->notes }}</div>
        @endif

        <p class="footer">
            Devis valable jusqu'au {{ $proforma->valid_until->format('d/m/Y') }} — au-delà, les tarifs pourront être révisés.<br>
            {{ config('app.name', 'Pressing Pro') }} — merci de votre confiance.
        </p>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
