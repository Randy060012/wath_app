@extends('layouts.app')

@section('title', 'Agences')

@section('header_hint', 'Créez et gérez vos agences / businesses (vue groupe).')

{{-- MULTI-TENANT — ÉCRAN SUPER-ADMIN : gestion des agences.
     Création = catalogue initial copié + admin local optionnel. --}}
@section('content')
    <h1 class="mb-6 flex items-center gap-2 text-2xl font-bold text-slate-900">
        <x-icon name="building-2" class="w-6 h-6 text-sky-700" />
        Agences & businesses
        <span class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-sm font-semibold text-sky-700">{{ $agencies->count() }}</span>
    </h1>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Formulaire de création --}}
        <form method="POST" action="{{ route('admin.agencies.store') }}" class="card h-fit space-y-4 p-4">
            @csrf
            <h2 class="font-semibold text-slate-900">Nouvelle agence</h2>

            <div>
                <label class="label" for="agency-name">Nom de l'agence</label>
                <input id="agency-name" name="name" required class="input" placeholder="Ex : Pressing Plateau">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="agency-phone">Téléphone</label>
                    <input id="agency-phone" name="phone" class="input" placeholder="Optionnel">
                </div>
                <div>
                    <label class="label" for="agency-email">E-mail</label>
                    <input id="agency-email" name="email" type="email" class="input" placeholder="Optionnel">
                </div>
            </div>
            <div>
                <label class="label" for="agency-address">Adresse</label>
                <input id="agency-address" name="address" class="input" placeholder="Optionnel">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="copy_catalog" value="1" checked class="checkbox">
                Copier le catalogue de prestations initial
            </label>

            <div class="border-t border-slate-100 pt-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Admin local (optionnel)</p>
                <div class="space-y-3">
                    <input name="admin_name" class="input" placeholder="Nom de l'admin local">
                    <input name="admin_email" type="email" class="input" placeholder="E-mail de connexion">
                    <input name="admin_password" type="password" class="input" placeholder="Mot de passe (min. 6)">
                </div>
            </div>

            <button class="btn-primary w-full gap-2">
                <x-icon name="plus" class="w-4 h-4" />
                Créer l'agence
            </button>
        </form>

        {{-- Liste des agences --}}
        <div class="card overflow-hidden lg:col-span-2">
            <table class="table-simple">
                <thead>
                    <tr>
                        <th>Agence</th>
                        <th>Équipe</th>
                        <th>Activité</th>
                        <th class="!text-right">Statut</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agencies as $agency)
                        <tr class="{{ $agency->id === $currentId ? 'bg-sky-50' : '' }}">
                            <td>
                                <span class="font-semibold">{{ $agency->name }}</span>
                                <span class="block font-mono text-xs text-slate-400">{{ $agency->code }}</span>
                                @if ($agency->phone)
                                    <span class="block text-xs text-slate-400">{{ $agency->phone }}</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                <span class="flex items-center gap-1"><x-icon name="users" class="w-3.5 h-3.5 text-slate-400" /> {{ $agency->users_count }}</span>
                            </td>
                            <td class="text-sm text-slate-500">
                                <span class="flex items-center gap-1"><x-icon name="receipt-text" class="w-3.5 h-3.5 text-slate-400" /> {{ $agency->orders_count }} dépôts</span>
                                <span class="flex items-center gap-1"><x-icon name="user" class="w-3.5 h-3.5 text-slate-400" /> {{ $agency->clients_count }} clients</span>
                            </td>
                            <td class="text-right">
                                @if ($agency->is_active)
                                    <span class="badge bg-emerald-100 text-emerald-800">active</span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-500">désactivée</span>
                                @endif
                                @if ($agency->id === $currentId)
                                    <span class="badge ml-1 bg-sky-100 text-sky-700">contexte</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Endosser le contexte de l'agence --}}
                                    <form method="POST" action="{{ route('admin.agency-context.switch') }}">
                                        @csrf
                                        <input type="hidden" name="agency_id" value="{{ $agency->id }}">
                                        <button class="btn-ghost btn-sm" title="Travailler dans cette agence">ouvrir</button>
                                    </form>
                                    @unless ($agency->hasCatalog())
                                        <form method="POST" action="{{ route('admin.agencies.seedCatalog', $agency) }}">
                                            @csrf
                                            <button class="btn-ghost btn-sm" title="Copier le catalogue initial">catalogue</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.agencies.toggle', $agency) }}">
                                        @csrf
                                        <button class="btn-ghost btn-sm {{ $agency->is_active ? 'text-rose-600' : 'text-emerald-700' }}">
                                            {{ $agency->is_active ? 'désactiver' : 'réactiver' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucune agence — créez la première ci-contre.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
