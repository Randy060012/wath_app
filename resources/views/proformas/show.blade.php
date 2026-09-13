@extends('layouts.app')

@section('title', 'Proforma ' . $proforma->number)

@section('header_hint', 'Devis — envoyez-le au tiers, puis convertissez-le en dépôt après acceptation.')

{{-- ÉTAPE 1 — SPÉCIFICATIONS C : FICHE PROFORMA + workflow --}}
@section('content')
    {{-- Fil d'ariane --}}
    <p class="mb-2 flex items-center gap-1 text-xs text-slate-400">
        <a href="{{ route('proformas.index') }}" class="hover:text-sky-700">Proformas</a>
        <x-icon name="chevron-right" class="h-3 w-3" />
        <span class="font-mono">{{ $proforma->number }}</span>
    </p>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl font-bold text-slate-900">{{ $proforma->number }}</h1>
                <span class="badge {{ $proforma->display_status->badgeClass() }}">{{ $proforma->display_status->label() }}</span>
                @if ($proforma->converted_order_id)
                    <a href="{{ route('orders.show', $proforma->converted_order_id) }}" class="badge bg-teal-100 text-teal-800 hover:underline">
                        Converti en dépôt
                    </a>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Émis le {{ $proforma->issued_at->format('d/m/Y') }}
                · valable jusqu'au <strong>{{ $proforma->valid_until->format('d/m/Y') }}</strong>
                · par {{ $proforma->author?->name ?? '—' }}
            </p>
        </div>

        {{-- ================= ACTIONS DU WORKFLOW ================= --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('proformas.print', $proforma) }}" target="_blank" class="btn-ghost gap-2">
                <x-icon name="printer" class="w-4 h-4" />
                Imprimer / PDF
            </a>

            @if (in_array($proforma->status, [\App\Enums\ProformaStatus::Brouillon, \App\Enums\ProformaStatus::Refuse]))
                <form method="POST" action="{{ route('proformas.send', $proforma) }}">
                    @csrf
                    <button class="btn-primary gap-2">
                        <x-icon name="send" class="w-4 h-4" />
                        {{ $proforma->client->email ? 'Envoyer par e-mail' : 'Marquer envoyé' }}
                    </button>
                </form>
            @endif

            @if ($proforma->status === \App\Enums\ProformaStatus::Envoye)
                {{-- Acceptation / refus (motif optionnel pour le refus) —
                     un SEUL champ status piloté par Alpine. --}}
                <form method="POST" action="{{ route('proformas.status', $proforma) }}"
                      x-data="{ refuse: false }" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="hidden" name="status" :value="refuse ? 'refuse' : 'accepte'">
                    <div x-show="!refuse" class="flex items-center gap-2">
                        <button class="btn-primary gap-2">
                            <x-icon name="check" class="w-4 h-4" />
                            Marquer accepté
                        </button>
                        <button type="button" class="btn-ghost gap-2" @click="refuse = true">
                            <x-icon name="x" class="w-4 h-4" />
                            Refuser
                        </button>
                    </div>
                    <div x-show="refuse" x-cloak class="flex items-center gap-2">
                        <input type="text" name="reason" placeholder="Motif du refus…" class="input !w-56">
                        <button class="btn-danger gap-2">
                            <x-icon name="ban" class="w-4 h-4" />
                            Confirmer
                        </button>
                        <button type="button" class="btn-ghost" @click="refuse = false">Annuler</button>
                    </div>
                </form>
            @endif

            @if ($proforma->status === \App\Enums\ProformaStatus::Accepte && !$proforma->converted_order_id)
                {{-- SPÉCIFICATIONS C.2 — conversion en dépôt réel, avec acompte optionnel --}}
                <form method="POST" action="{{ route('proformas.convert', $proforma) }}"
                      x-data="{ open: false }" class="flex items-center gap-2">
                    @csrf
                    <button type="button" class="btn-primary gap-2" @click="open = !open">
                        <x-icon name="arrow-right-left" class="w-4 h-4" />
                        Convertir en dépôt
                    </button>
                    <div x-show="open" x-cloak class="flex items-center gap-2">
                        <input type="number" name="deposit_amount" min="0" step="0.01" placeholder="Acompte (optionnel)"
                               class="input !w-44">
                        <select name="deposit_method" class="input !w-36">
                            <option value="cash">Espèces</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="card">Carte</option>
                        </select>
                        <button class="btn-primary gap-2">
                            <x-icon name="check" class="w-4 h-4" />
                            Valider
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ================= COLONNE PRINCIPALE : DÉTAIL ================= --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Tiers --}}
            <div class="card p-5">
                <h2 class="mb-3 flex items-center gap-2 font-semibold text-slate-900">
                    <x-icon name="user" class="w-4 h-4 text-sky-700" />
                    Destinataire
                </h2>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                    <a href="{{ route('clients.show', $proforma->client) }}" class="font-semibold text-sky-700 hover:underline">
                        {{ $proforma->client->name }}
                    </a>
                    <span class="badge {{ $proforma->client->type->badgeClass() }}">{{ $proforma->client->type->label() }}</span>
                    @if ($proforma->client->phone)
                        <a href="tel:{{ $proforma->client->phone }}" class="text-slate-600 hover:text-sky-700">{{ $proforma->client->phone }}</a>
                    @endif
                    @if ($proforma->client->email)
                        <span class="text-slate-600">{{ $proforma->client->email }}</span>
                    @endif
                </div>
            </div>

            {{-- Lignes --}}
            <div class="card overflow-hidden">
                <h2 class="border-b border-slate-100 px-5 py-3 font-semibold text-slate-900">Prestations devisées</h2>
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>Prestation</th>
                            <th class="!text-center">Unité</th>
                            <th class="!text-right">Qté</th>
                            <th class="!text-right">P.U.</th>
                            <th class="!text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proforma->items as $item)
                            <tr>
                                <td>
                                    {{ $item->label }}
                                    @if (!$item->service_id)
                                        <span class="badge ml-1 bg-slate-100 text-slate-600">ligne libre</span>
                                    @endif
                                </td>
                                <td class="text-center text-xs text-slate-500">
                                    {{ $item->pricing_unit === 'kg' ? 'au kg' : ($item->pricing_unit === 'forfait' ? 'forfait' : 'pièce') }}
                                </td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right text-slate-500">{{ number_format($item->unit_price, 0, ',', ' ') }}</td>
                                <td class="text-right font-semibold">{{ number_format($item->line_total, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="space-y-1 border-t border-slate-100 px-5 py-4 text-sm">
                    <div class="flex justify-between text-slate-500"><span>Sous-total</span>
                        <span>{{ number_format($proforma->total_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}</span></div>
                    @if ($proforma->discount_amount > 0)
                        <div class="flex justify-between text-slate-500"><span>Remise</span>
                            <span>- {{ number_format($proforma->discount_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}</span></div>
                    @endif
                    <div class="flex justify-between text-base font-bold text-slate-900"><span>Net à payer</span>
                        <span>{{ number_format($proforma->net_amount, 0, ',', ' ') }} {{ config('pressing.currency', 'FCFA') }}</span></div>
                </div>
            </div>

            @if ($proforma->notes)
                <div class="card p-5">
                    <h2 class="mb-2 flex items-center gap-2 font-semibold text-slate-900">
                        <x-icon name="sticky-note" class="w-4 h-4 text-sky-700" />
                        Notes
                    </h2>
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $proforma->notes }}</p>
                </div>
            @endif
        </div>

        {{-- ================= COLONNE LATÉRALE : JOURNAL ================= --}}
        <div class="space-y-6">
            <div class="card p-5">
                <h2 class="mb-3 flex items-center gap-2 font-semibold text-slate-900">
                    <x-icon name="history" class="w-4 h-4 text-sky-700" />
                    Cycle de vie
                </h2>
                <ol class="space-y-3 text-sm">
                    @php
                        $steps = [
                            'Brouillon' => $proforma->issued_at,
                            'Envoyé'    => $proforma->status !== \App\Enums\ProformaStatus::Brouillon ? $proforma->updated_at : null,
                            'Accepté'   => in_array($proforma->status, [\App\Enums\ProformaStatus::Accepte, \App\Enums\ProformaStatus::Expire]) || $proforma->converted_order_id ? $proforma->updated_at : null,
                        ];
                    @endphp
                    @foreach ($steps as $label => $when)
                        <li class="flex items-start gap-2">
                            <span class="mt-1 {{ $when ? 'text-emerald-500' : 'text-slate-300' }}">
                                <x-icon name="{{ $when ? 'circle-check' : 'circle' }}" class="w-4 h-4" />
                            </span>
                            <span>
                                <span class="font-medium {{ $when ? 'text-slate-800' : 'text-slate-400' }}">{{ $label }}</span>
                                @if ($when)
                                    <span class="block text-xs text-slate-400">{{ $when->format('d/m/Y H:i') }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
@endsection
