<!DOCTYPE html>
{{-- ÉTAPE 1 — VUE D'IMPRESSION : TICKET DE CAISSE 80 mm --}}
{{-- HTML 100 % autonome (styles inline, pas de Vite) : il est généré par
     LabelPrintingService puis servi tel quel par CashRegisterController.
     window.print() se déclenche au chargement ; l'utilisateur choisit
     son imprimante (thermique 80 mm ou A4). --}}
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $order->ticket_no }}</title>
    <style>
        /* --- Styles thermique 80 mm : monospace, fond blanc --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; color: #000; background: #fff; padding: 12px 8px; }
        .ticket { width: 72mm; margin: 0 auto; font-size: 12px; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; margin: 1px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        td { padding: 1px 0; vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .hr { border-top: 1px dashed #000; margin: 6px 0; }
        .total { font-size: 15px; font-weight: bold; }
        .small { font-size: 10px; }
        .barcode { margin: 8px auto; width: 220px; }
        .barcode svg { width: 100%; height: auto; }
        .btn-print {
            position: fixed; top: 10px; right: 10px; padding: 8px 16px;
            background: #0c4a6e; color: #fff; border: 0; border-radius: 6px;
            font-family: system-ui; cursor: pointer;
        }
        @media print { .btn-print { display: none; } }
        @page { margin: 4mm; }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimer le ticket</button>

    <div class="ticket">
        {{-- MULTI-TENANT : en-tête portant l'agence émettrice du ticket --}}
        @if ($agency)
            <p class="center"><strong>{{ $agency->name }}</strong></p>
            @if ($agency->address)
                <p class="center small">{{ $agency->address }}</p>
            @endif
            @if ($agency->phone)
                <p class="center small">Tél : {{ $agency->phone }}</p>
            @endif
        @else
            <p class="center"><strong>{{ config('app.name', 'Pressing Pro') }}</strong></p>
            <p class="center small">{{ config('app.address', 'Pressing — quartier du marché') }}</p>
            <p class="center small">Tél : {{ config('app.phone', '00 000 00 00 00') }}</p>
        @endif
        <div class="hr"></div>
        <p class="center"><strong>TICKET DÉPÔT</strong></p>
        <div class="row"><span>Ticket :</span><strong>{{ $order->ticket_no }}</strong></div>
        <div class="row"><span>Date :</span><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Client :</span><span>{{ $order->client->name }}</span></div>
        <div class="row"><span>Tél :</span><span>{{ $order->client->phone ?? '—' }}</span></div>
        @if ($order->is_express)
            <div class="row"><span class="total small">*** EXPRESS ***</span><span></span></div>
        @endif
        <div class="hr"></div>

        {{-- Articles --}}
        <table>
            {!! $itemsHtml !!}
        </table>

        <div class="hr"></div>
        <div class="row"><span>Sous-total</span><span class="r">{{ number_format($order->total_amount, 2, ',', ' ') }}</span></div>
        @if ($order->discount_amount > 0)
            <div class="row"><span>Remise</span><span class="r">- {{ number_format($order->discount_amount, 2, ',', ' ') }}</span></div>
        @endif
        <div class="row total"><span>NET À PAYER</span><span class="r">{{ number_format($order->net_amount, 0, ',', ' ') }} {{ $currency }}</span></div>
        <div class="row"><span>Accompte versé</span><span class="r">{{ number_format($order->paid_amount, 2, ',', ' ') }}</span></div>
        <div class="row"><span>Reste au retrait</span><span class="r">{{ number_format($order->balance_due, 2, ',', ' ') }}</span></div>
        <div class="row"><span>Retour promis</span><span>{{ $order->promised_at?->format('d/m H\h') ?? '—' }}</span></div>

        {{-- Paiements --}}
        @if ($paymentsHtml)
            <div class="hr"></div>
            <table>
                {!! $paymentsHtml !!}
            </table>
        @endif

        {{-- Code-barres du ticket (retrouvable au scan même sans étiquette) --}}
        <div class="barcode">{!! $barcodeSvg !!}</div>
        <p class="center small">{{ $order->ticket_no }}</p>

        <div class="hr"></div>
        <p class="center small">Conservez ce ticket, il sera demandé au retrait.</p>
        <p class="center small">Merci de votre confiance !</p>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
