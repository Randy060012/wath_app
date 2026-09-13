@extends('layouts.app')

@section('title', 'Stock')

@section('header_hint', 'Fournitures : housse, cintres, détergent… avec alertes de seuil.')

{{-- ÉTAPE 1 — STOCK DE FOURNITURES avec mouvements +/− --}}
@section('content')
    <h1 class="mb-6 flex items-center gap-2 text-2xl font-bold text-slate-900">
        <x-icon name="package" class="w-6 h-6 text-sky-700" />
        Stock de fournitures
    </h1>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Formulaire d'ajout --}}
        <form method="POST" action="{{ route('admin.inventory.store') }}" class="card h-fit space-y-3 p-4">
            @csrf
            <h2 class="font-semibold text-slate-900">Ajouter une fourniture</h2>
            <div>
                <label class="label">Nom</label>
                <input name="name" required class="input" placeholder="Ex : Sacs plastique M">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Quantité</label><input name="quantity" type="number" step="0.01" min="0" required class="input text-right"></div>
                <div><label class="label">Unité</label><input name="unit" value="pièce" class="input"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Seuil alerte</label><input name="min_quantity" type="number" step="0.01" min="0" required class="input text-right"></div>
                <div><label class="label">Coût unitaire</label><input name="unit_cost" type="number" step="0.01" min="0" class="input text-right"></div>
            </div>
            <button class="btn-primary w-full">Ajouter</button>
        </form>

        {{-- Liste du stock en DataTable (recherche + tri côté navigateur) --}}
        <div class="card overflow-hidden lg:col-span-2" data-datatable>
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort data-type="text">Fourniture</th>
                        <th data-sort data-type="num" class="!text-right">Stock</th>
                        <th data-sort data-type="num" class="!text-right">Seuil</th>
                        <th>Mouvement</th>
                        <th class="!text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="{{ $item->isLowStock() ? 'bg-amber-50/60' : '' }}">
                            <td>
                                <span class="inline-flex items-center gap-1.5">
                                    {{ $item->name }}
                                    @if ($item->isLowStock())
                                        <span class="badge bg-amber-100 text-amber-800 ring-amber-200">stock bas</span>
                                    @endif
                                </span>
                            </td>
                            {{-- data-search : le DataTable filtre sur la valeur numérique --}}
                            <td class="text-right font-semibold" data-search="{{ $item->quantity }}">{{ $item->quantity }} <span class="text-xs font-normal text-slate-400">{{ $item->unit }}</span></td>
                            <td class="text-right text-slate-400" data-search="{{ $item->min_quantity }}">{{ $item->min_quantity }}</td>
                            {{-- Mouvement de stock en ligne --}}
                            <td>
                                <form method="POST" action="{{ route('admin.inventory.adjust', $item) }}" class="flex items-center gap-1">
                                    @csrf @method('PATCH')
                                    <input name="delta" type="number" step="0.01" placeholder="+/-" class="input input-sm !w-20 text-right">
                                    <button class="btn-ghost btn-sm">OK</button>
                                </form>
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.inventory.destroy', $item) }}" data-confirm="Supprimer cette fourniture ?">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger btn-icon" title="Supprimer" aria-label="Supprimer">
                                        <x-icon name="trash-2" class="w-4 h-4" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell" data-empty-text="Aucune fourniture.">Aucune fourniture.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
