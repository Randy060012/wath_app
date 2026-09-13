@component('mail::message')
# Bonjour {{ $proforma->client->name }},

Veuillez trouver ci-dessous votre devis **{{ $proforma->number }}**.

## Détail des prestations

| Prestation | Qté | Prix unitaire | Total |
|---|---|---|---|
@foreach ($proforma->items as $item)
| {{ $item->label }} | {{ $item->quantity }} | {{ number_format($item->unit_price, 0, ',', ' ') }} | {{ number_format($item->line_total, 0, ',', ' ') }} |
@endforeach

**Total : {{ number_format($proforma->net_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}**

@if ($proforma->discount_amount > 0)
Remise appliquée : -{{ number_format($proforma->discount_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}
@endif

Offre valable jusqu'au **{{ $proforma->valid_until->format('d/m/Y') }}**.

@if ($proforma->notes)
{{ $proforma->notes }}
@endif

Merci de votre confiance — {{ config('app.name', 'Pressing Pro') }}.
@endcomponent
