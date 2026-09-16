@extends('layouts.app')

@section('title', 'Utilisateurs')

@section('header_hint', 'Créez les comptes, choisissez rôle et agence de rattachement.')

{{-- MULTI-TENANT — ÉCRAN SUPER-ADMIN : gestion des utilisateurs.
     Chaque compte = 1 rôle + 1 agence (ou vue groupe pour le super-admin). --}}
@section('content')
    <h1 class="mb-6 flex items-center gap-2 text-2xl font-bold text-slate-900">
        <x-icon name="users" class="w-6 h-6 text-sky-700" />
        Utilisateurs & rôles
        <span class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-sm font-semibold text-sky-700">{{ $users->count() }}</span>
    </h1>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Création d'un utilisateur --}}
        <form method="POST" action="{{ route('admin.users.store') }}" class="card h-fit space-y-4 p-4">
            @csrf
            <h2 class="font-semibold text-slate-900">Nouvel utilisateur</h2>

            <div>
                <label class="label" for="user-name">Nom complet</label>
                <input id="user-name" name="name" required class="input" placeholder="Ex : Awa Diop">
            </div>
            <div>
                <label class="label" for="user-email">E-mail (identifiant)</label>
                <input id="user-email" name="email" type="email" required class="input">
            </div>
            <div>
                <label class="label" for="user-password">Mot de passe</label>
                <input id="user-password" name="password" type="password" required minlength="6" class="input">
            </div>
            <div>
                <label class="label" for="user-role">Rôle</label>
                <select id="user-role" name="role" class="input" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="user-agency">Agence</label>
                <select id="user-agency" name="agency_id" class="input" required>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}">{{ $agency->name }} ({{ $agency->code }})</option>
                    @endforeach
                </select>
            </div>

            <button class="btn-primary w-full gap-2">
                <x-icon name="plus" class="w-4 h-4" />
                Créer le compte
            </button>
        </form>

        {{-- Liste des utilisateurs --}}
        <div class="card overflow-hidden lg:col-span-2">
            <table class="table-simple">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle & agence</th>
                        <th class="!text-right">Statut</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <span class="font-semibold">{{ $user->name }}</span>
                                <span class="block text-xs text-slate-400">{{ $user->email }}</span>
                            </td>
                            <td>
                                @if ($user->isSuperAdmin())
                                    <span class="badge bg-indigo-100 text-indigo-700">super-admin</span>
                                    <span class="block text-xs text-slate-400">vue groupe — toutes agences</span>
                                @else
                                    <form method="POST" action="{{ route('admin.users.update', $user) }}"
                                          class="flex flex-wrap items-center gap-1">
                                        @csrf @method('PATCH')
                                        <select name="role" class="input input-sm !w-32" aria-label="Rôle de {{ $user->name }}">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected($user->hasRole($role))>{{ $role }}</option>
                                            @endforeach
                                        </select>
                                        <select name="agency_id" class="input input-sm !w-40" aria-label="Agence de {{ $user->name }}">
                                            @foreach ($agencies as $agency)
                                                <option value="{{ $agency->id }}" @selected($user->agency_id === $agency->id)>
                                                    {{ $agency->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn-ghost btn-icon" title="Enregistrer">
                                            <x-icon name="check" class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($user->is_active)
                                    <span class="badge bg-emerald-100 text-emerald-800">actif</span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-500">désactivé</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Réinitialisation du mot de passe --}}
                                    <form method="POST" action="{{ route('admin.users.resetPassword', $user) }}"
                                          class="flex items-center gap-1"
                                          x-data="{ open: false }">
                                        @csrf
                                        <template x-if="open">
                                            <input type="password" name="password" minlength="6" required
                                                   class="input input-sm !w-28" placeholder="Nouveau mdp">
                                        </template>
                                        <button type="button" x-show="!open" @click="open = true" class="btn-ghost btn-sm">mdp</button>
                                        <button x-show="open" x-cloak class="btn-ghost btn-sm">ok</button>
                                    </form>
                                    @unless ($user->isSuperAdmin())
                                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                            @csrf
                                            <button class="btn-ghost btn-sm {{ $user->is_active ? 'text-rose-600' : 'text-emerald-700' }}"
                                                    @disabled($user->id === auth()->id())>
                                                {{ $user->is_active ? 'désactiver' : 'réactiver' }}
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Aucun utilisateur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
