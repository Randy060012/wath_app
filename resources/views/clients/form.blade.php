@extends('layouts.app')

@section('title', $client->exists ? 'Modifier ' . $client->name : 'Nouveau client')

{{-- ÉTAPE 1 — FORMULAIRE CLIENT (création & édition) --}}
@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">
        {{ $client->exists ? 'Modifier : ' . $client->name : 'Nouveau client' }}
    </h1>

    {{-- Pleine largeur : le formulaire occupe tout l'espace disponible --}}
    <form method="POST"
          action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
          class="card w-full space-y-4 p-6">
        @csrf
        @if ($client->exists)
            @method('PUT')
        @endif

        <div>
            <label class="label" for="name">Nom complet *</label>
            <input id="name" name="name" required value="{{ old('name', $client->name) }}" class="input" placeholder="Ex : Awa Diop">
            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="phone">Téléphone</label>
                <input id="phone" name="phone" value="{{ old('phone', $client->phone) }}" class="input" placeholder="+221 77 000 00 00">
                @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="email">E-mail (notifications)</label>
                <input id="email" name="email" type="email" value="{{ old('email', $client->email) }}" class="input">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="label" for="address">Adresse</label>
            <textarea id="address" name="address" rows="2" class="input">{{ old('address', $client->address) }}</textarea>
        </div>

        <div>
            <label class="label" for="notes">Notes internes (allergies, habitudes…)</label>
            <textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $client->notes) }}</textarea>
        </div>

        <div class="flex gap-3">
            <button class="btn-primary gap-2">
                <x-icon name="save" class="w-4 h-4" />
                {{ $client->exists ? 'Enregistrer' : 'Créer le client' }}
            </button>
            <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
