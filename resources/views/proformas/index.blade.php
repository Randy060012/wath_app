@extends('layouts.app')

@section('title', 'Proformas')

@section('header_hint', 'Devis et factures proforma — envoyables par e-mail, convertibles en dépôt.')

{{-- ÉTAPE 1 — SPÉCIFICATIONS C : LISTE DES PROFORMAS (DataTable) --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Proformas</h1>
        <a href="{{ route('proformas.create') }}" class="btn-primary gap-2">
            <x-icon name="file-plus" class="w-4 h-4" />
            Nouveau proforma
        </a>
    </div>

    {{-- Filtres rapides par statut (liens GET) --}}
    @php
        $tabs = [['key' => '', 'label' => 'Tous'], ...collect($statuses)->map(fn ($s) => ['key' => $s->value, 'label' => $s->label()])->all()];
        $pill = fn (string $k) => $k === $status ? 'bg-sky-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-sky-50';
    @endphp
    <div class="mb-4 flex flex-wrap gap-2 text-sm font-medium">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['key'] === '' ? route('proformas.index') : route('proformas.index', ['status' => $tab['key']]) }}"
               class="rounded-full px-4 py-1.5 {{ $pill($tab['key']) }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    <div class="card overflow-hidden" data-datatable data-state-key="proformas" data-csv="proformas">
        <table class="data-table">
            <thead>
                <tr>
                    <th data-sort data-type="text">Numéro</th>
                    <th data-sort data-type="text">Tiers</th>
                    <th data-sort data-type="date">Émis le</th>
                    <th data-sort data-type="date">Valable jusqu'au</th>
                    <th data-sort data-type="num" class="!text-right">Net</th>
                    <th data-sort data-type="text">Statut</th>
                    <th class="!text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($proformas as $proforma)
                    <tr>
                        <td class="font-mono text-xs font-semibold">
                            <a href="{{ route('proformas.show', $proforma) }}" class="text-sky-700 hover:underline">{{ $proforma->number }}</a>
                        </td>
                        <td>
                            <span class="font-medium">{{ $proforma->client->name }}</span>
                            <span class="badge ml-1 {{ $proforma->client->type->badgeClass() }}">{{ $proforma->client->type->label() }}</span>
                        </td>
                        <td class="text-xs">{{ $proforma->issued_at->format('d/m/Y') }}</td>
                        <td class="text-xs">{{ $proforma->valid_until->format('d/m/Y') }}</td>
                        <td class="text-right font-semibold">{{ number_format($proforma->net_amount, 0, ',', ' ') }}</td>
                        {{-- Statut AFFICHÉ : un devis envoyé dont la validité est dépassée apparaît « Expiré » --}}
                        <td><span class="badge {{ $proforma->display_status->badgeClass() }}">{{ $proforma->display_status->label() }}</span></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('proformas.show', $proforma) }}" class="btn-ghost btn-sm">Voir</a>
                                <a href="{{ route('proformas.print', $proforma) }}" target="_blank" class="btn-ghost btn-sm" title="Imprimer / PDF">
                                    <x-icon name="printer" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell" data-empty-text="Aucun proforma.">Aucun proforma.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
