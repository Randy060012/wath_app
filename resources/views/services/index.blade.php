@extends('layouts.app')

@section('title', 'Catalogue')

@section('header_hint', 'Gérez prestations et tarifs — visible immédiatement en caisse.')

{{-- ÉTAPE 1 — CATALOGUE (admin) : ajout rapide + DataTable triable/filtrable --}}
@section('content')
    <h1 class="mb-6 flex items-center gap-2 text-2xl font-bold text-slate-900">
        <x-icon name="tags" class="w-6 h-6 text-sky-700" />
        Catalogue des prestations
    </h1>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Formulaire d'ajout --}}
        <form method="POST" action="{{ route('admin.services.store') }}" class="card h-fit space-y-4 p-4">
            @csrf
            <h2 class="font-semibold text-slate-900">Ajouter une prestation</h2>
            <div>
                <label class="label">Catégorie</label>
                <select name="category_id" class="input" required>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Nom</label>
                <input name="name" required class="input" placeholder="Ex : Jupe">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Prix</label>
                    <input name="price" type="number" step="0.01" min="0" required class="input text-right">
                </div>
                <div>
                    <label class="label">Unité</label>
                    <select name="pricing_unit" class="input">
                        <option value="piece">pièce</option>
                        <option value="kg">kg</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Délai standard (heures)</label>
                <input name="default_hours" type="number" min="1" value="48" class="input">
            </div>
            <button class="btn-primary w-full gap-2">
                <x-icon name="plus" class="w-4 h-4" />
                Ajouter
            </button>
        </form>

        {{-- Catalogue complet en DataTable : filtre par catégorie + tri + édition prix --}}
        <div class="card overflow-hidden lg:col-span-2" data-datatable>
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort data-type="text">Prestation</th>
                        <th data-sort data-type="text" data-filter-label="Filtrer : catégorie">Catégorie</th>
                        <th data-sort data-type="text">Unité</th>
                        <th data-sort data-type="num" class="!text-right">Prix</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td>
                                {{ $service->name }}
                                @unless ($service->is_active)
                                    <span class="badge bg-slate-100 text-slate-500 ring-slate-200">désactivée</span>
                                @endunless
                            </td>
                            <td class="text-slate-500">{{ $service->category?->name }}</td>
                            <td class="text-xs text-slate-400">{{ $service->pricing_unit === 'kg' ? 'au kg' : 'à la pièce' }}</td>
                            {{-- data-search : le tri/filtre lit la valeur numérique, pas le champ de saisie --}}
                            <td class="text-right" data-search="{{ $service->price }}">
                                <form method="POST" action="{{ route('admin.services.update', $service) }}" class="flex items-center justify-end gap-2">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="name" value="{{ $service->name }}">
                                    <input type="hidden" name="pricing_unit" value="{{ $service->pricing_unit }}">
                                    <input name="price" value="{{ $service->price }}" type="number" step="0.01" min="0"
                                           class="input input-sm !w-24 text-right" aria-label="Prix de {{ $service->name }}">
                                    <input type="hidden" name="is_active" value="1">
                                    <button class="btn-ghost btn-icon" title="Enregistrer le prix" aria-label="Enregistrer le prix">
                                        <x-icon name="check" class="w-3.5 h-3.5" />
                                    </button>
                                </form>
                            </td>
                            <td class="text-right">
                                @if ($service->is_active)
                                    <form method="POST" action="{{ route('admin.services.destroy', $service) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm">désactiver</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell" data-empty-text="Aucune prestation.">Aucune prestation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
