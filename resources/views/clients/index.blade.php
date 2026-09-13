@extends('layouts.app')

@section('title', 'Clients')

{{-- ÉTAPE 1 — LISTE DES TIERS (CRM) en DataTable interactif :
     recherche, tri, pagination gérés par public/assets/js/datatable.js.
     SPÉCIFICATIONS B — onglets Acteur / Client + badges de statut
     dynamique ; SPÉCIFICATIONS C — accès direct au proforma. --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Clients</h1>
        <div class="flex flex-wrap items-center gap-2">
            {{-- SPÉCIFICATIONS C — proforma direct (depuis la liste) --}}
            <a href="{{ route('proformas.create') }}" class="btn-ghost gap-2">
                <x-icon name="file-text" class="w-4 h-4" />
                Nouveau proforma
            </a>
            {{-- SPÉCIFICATIONS B — inscription directe d'un Acteur --}}
            <a href="{{ route('clients.create') }}" class="btn-primary gap-2">
                <x-icon name="user-plus" class="w-4 h-4" />
                Nouveau client
            </a>
        </div>
    </div>

    {{-- Onglets de filtrage Acteur / Client (liens GET, garde le DataTable simple) --}}
    @php $q = fn (string $t) => $t === ($typeFilter ?? '') ? 'bg-sky-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-sky-50'; @endphp
    <div class="mb-4 flex flex-wrap gap-2 text-sm font-medium">
        <a href="{{ route('clients.index') }}" class="rounded-full px-4 py-1.5 {{ $q('') }}">
            Tous <span class="opacity-60">({{ $counts['all'] }})</span>
        </a>
        <a href="{{ route('clients.index', ['type' => 'acteur']) }}" class="rounded-full px-4 py-1.5 {{ $q('acteur') }}">
            Acteurs <span class="opacity-60">({{ $counts['acteur'] }})</span>
        </a>
        <a href="{{ route('clients.index', ['type' => 'client']) }}" class="rounded-full px-4 py-1.5 {{ $q('client') }}">
            Clients <span class="opacity-60">({{ $counts['client'] }})</span>
        </a>
    </div>

    <div class="card overflow-hidden" data-datatable data-state-key="clients" data-csv="clients">
        <table class="data-table">
            <thead>
                <tr>
                    <th data-sort data-type="text">Code</th>
                    <th data-sort data-type="text">Nom</th>
                    <th data-sort data-type="text">Statut</th>
                    <th data-sort data-type="text">Téléphone</th>
                    <th data-sort data-type="num" class="!text-right">Dépôts</th>
                    <th data-sort data-type="num" class="!text-right">Points</th>
                    <th class="!text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td class="font-mono text-xs">{{ $client->code }}</td>
                        <td class="font-semibold">{{ $client->name }}</td>
                        {{-- SPÉCIFICATIONS B — badge du statut dynamique --}}
                        <td><span class="badge {{ $client->type->badgeClass() }}">{{ $client->type->label() }}</span></td>
                        <td>
                            @if ($client->phone)
                                <a href="tel:{{ $client->phone }}" class="text-sky-700 hover:underline">{{ $client->phone }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">{{ $client->orders_count }}</td>
                        <td class="text-right">{{ $client->loyalty_points }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('clients.show', $client) }}" class="btn-ghost btn-sm">Fiche</a>
                                <a href="{{ route('proformas.create', ['client' => $client->id]) }}"
                                   class="btn-ghost btn-sm" title="Nouveau proforma pour ce tiers">
                                    <x-icon name="file-text" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell" data-empty-text="Aucun tiers.">Aucun tiers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
