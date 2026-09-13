@extends('layouts.app')

@section('title', 'Client ' . $client->name)

{{-- ÉTAPE 1 — FICHE CLIENT + historique des dépôts --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="font-mono text-xs text-slate-400">{{ $client->code }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ $client->name }}</h1>
                {{-- SPÉCIFICATIONS B — statut dynamique Acteur / Client --}}
                <span class="badge {{ $client->type->badgeClass() }}">{{ $client->type->label() }}</span>
            </div>
            <p class="text-sm text-slate-500">
                @if ($client->phone)
                    <a href="tel:{{ $client->phone }}" class="text-sky-700 hover:underline">{{ $client->phone }}</a>
                @else
                    —
                @endif
                 · {{ $client->orders_count }} dépôt(s) · {{ $client->loyalty_points }} pts fidélité</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- SPÉCIFICATIONS C — devis pré-rempli pour ce tiers --}}
            <a href="{{ route('proformas.create', ['client' => $client->id]) }}" class="btn-ghost gap-2">
                <x-icon name="file-text" class="w-4 h-4" />
                Proforma
            </a>
            {{-- Nouveau dépôt pré-rempli avec ce client via ?client=ID --}}
            <a href="{{ route('orders.create', ['client' => $client->id]) }}" class="btn-primary gap-2">
                <x-icon name="plus" class="w-4 h-4" />
                Nouveau dépôt
            </a>
            <a href="{{ route('clients.edit', $client) }}" class="btn-ghost gap-2">
                <x-icon name="pencil" class="w-4 h-4" />
                Modifier
            </a>
            <form method="POST" action="{{ route('clients.destroy', $client) }}">
                @csrf @method('DELETE')
                @if ($client->orders_count === 0)
                    <button class="btn-danger gap-2">
                        <x-icon name="trash-2" class="w-4 h-4" />
                        Supprimer
                    </button>
                @endif
            </form>
        </div>
    </div>

    {{-- data-row-link : ligne cliquable → fiche ticket du dépôt --}}
    <h2 class="mb-2 mt-8 flex items-center gap-2 font-semibold text-slate-900">
        <x-icon name="receipt-text" class="w-4 h-4 text-sky-700" />
        Dépôts
    </h2>
    <div class="card overflow-hidden" data-datatable data-row-link data-state-key="client.{{ $client->id }}">
        <table class="data-table">
            <thead>
                <tr>
                    <th data-sort data-type="text">Ticket</th>
                    <th data-sort data-type="date">Date</th>
                    <th data-sort data-type="num" class="!text-center">Articles</th>
                    <th data-sort data-type="text">Statut</th>
                    <th data-sort data-type="num" class="!text-right">Net</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr data-href="{{ route('orders.show', $order) }}">
                        <td>
                            <a class="font-mono text-xs font-semibold text-sky-700 hover:underline" href="{{ route('orders.show', $order) }}">{{ $order->ticket_no }}</a>
                        </td>
                        <td class="text-xs" @if($order->created_at) data-timestamp="{{ $order->created_at->timestamp }}" @endif>{{ $order->created_at->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $order->items_count }}</td>
                        <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        <td class="text-right">{{ number_format($order->net_amount, 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell" data-empty-text="Aucun dépôt pour ce client.">Aucun dépôt pour ce client.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- SPÉCIFICATIONS C — DEVIS (proformas) du tiers --}}
    <h2 class="mb-2 mt-8 flex items-center gap-2 font-semibold text-slate-900">
        <x-icon name="file-text" class="w-4 h-4 text-sky-700" />
        Devis & proformas
    </h2>
    <div class="card overflow-hidden" data-datatable data-state-key="client.{{ $client->id }}.proformas" data-csv="proformas">
        <table class="data-table">
            <thead>
                <tr>
                    <th data-sort data-type="text">Numéro</th>
                    <th data-sort data-type="date">Émis le</th>
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
                        <td class="text-xs">{{ $proforma->issued_at->format('d/m/Y') }}</td>
                        <td class="text-right">{{ number_format($proforma->net_amount, 0, ',', ' ') }}</td>
                        <td><span class="badge {{ $proforma->display_status->badgeClass() }}">{{ $proforma->display_status->label() }}</span></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('proformas.print', $proforma) }}" target="_blank" class="btn-ghost btn-sm" title="Imprimer / PDF">
                                    <x-icon name="printer" class="w-3.5 h-3.5" />
                                </a>
                                @if ($proforma->status === \App\Enums\ProformaStatus::Accepte && $proforma->converted_order_id === null)
                                    <form method="POST" action="{{ route('proformas.convert', $proforma) }}">
                                        @csrf
                                        <button class="btn-primary btn-sm">Convertir en dépôt</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell" data-empty-text="Aucun devis pour ce tiers.">Aucun devis pour ce tiers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
