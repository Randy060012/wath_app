<!DOCTYPE html>
{{-- ÉTAPE 1 — VUE D'IMPRESSION : PLANCHE D'ÉTIQUETTES ARTICLES --}}
{{-- Une étiquette PAR article : code-barres (douchette atelier) + QR
     (lecture smartphone) + client + rangement. Découpe puis thermocollage. --}}
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Étiquettes {{ $order->ticket_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #fff; padding: 10px; color: #000; }
        .sheet { display: flex; flex-wrap: wrap; gap: 6px; max-width: 210mm; margin: 0 auto; }
        .label-card {
            width: 50mm; border: 1px solid #000; border-radius: 4px;
            padding: 5px 6px; page-break-inside: avoid;
        }
        .label-card .head { display: flex; justify-content: space-between; font-size: 9px; font-weight: bold; }
        .label-card .client { font-size: 10px; font-weight: bold; margin: 2px 0; }
        .label-card .service { font-size: 10px; }
        .label-card .detail { font-size: 8.5px; color: #222; }
        .barcode svg { width: 100%; height: 40px; }
        .qr { width: 14mm; height: 14mm; }
        .foot { display: flex; align-items: center; gap: 4px; margin-top: 3px; }
        .code { font-family: monospace; font-size: 8.5px; font-weight: bold; }
        .btn-print {
            position: fixed; top: 10px; right: 10px; padding: 8px 16px;
            background: #0c4a6e; color: #fff; border: 0; border-radius: 6px;
            font-family: system-ui; cursor: pointer;
        }
        @media print { .btn-print { display: none; } }
        @page { margin: 6mm; }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimer les étiquettes</button>

    <div class="sheet">
        @foreach ($labels as $label)
            <div class="label-card">
                <div class="head">
                    <span>{{ config('app.name', 'Pressing Pro') }}</span>
                    <span>{{ $order->ticket_no }}</span>
                </div>
                <p class="client">{{ \Illuminate\Support\Str::limit($order->client->name, 22) }}</p>
                <p class="service">{{ $label['item']->service->name }}</p>
                @if ($label['item']->description)
                    <p class="detail">{{ \Illuminate\Support\Str::limit($label['item']->description, 30) }}</p>
                @endif
                <p class="detail">Dépôt : {{ $order->created_at->format('d/m') }} · Prévu : {{ $order->promised_at?->format('d/m') ?? '—' }}</p>

                <div class="foot">
                    <div class="barcode">{!! $label['barcodeSvg'] !!}</div>
                    <img class="qr" src="{{ $label['qrDataUri'] }}" alt="QR {{ $label['item']->barcode }}">
                </div>
                <p class="code">{{ $label['item']->barcode }}</p>
            </div>
        @endforeach
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
