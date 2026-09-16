@extends('layouts.guest')

@section('title', 'Connexion')

{{-- ÉTAPE 1 — PAGE DE CONNEXION --}}
@section('content')
    <h2 class="mb-1 text-lg font-bold text-slate-900">Bon retour !</h2>
    <p class="mb-6 text-sm text-slate-500">Connectez-vous avec votre compte employé.</p>

    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5">
        @csrf

        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="input @error('email') input-error @enderror"
                   placeholder="vous@pressing.test">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="password">Mot de passe</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="input @error('password') input-error @enderror">
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="checkbox">
            Se souvenir de moi
        </label>

        <button type="submit" class="btn-primary btn-lg w-full">Se connecter</button>
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">
        Nouveau sur la plateforme ?
        <a href="{{ route('register.show') }}" class="font-semibold text-sky-700 hover:underline">Créer un compte</a>
    </p>

    @if (app()->environment('local'))
        {{-- Rappel des comptes de démo (uniquement en local) --}}
        <div class="mt-6 rounded-lg bg-sky-50 p-3 text-xs text-sky-800 ring-1 ring-sky-100">
            <p class="font-semibold">Comptes de démonstration (mot de passe : password) :</p>
            <ul class="mt-1 space-y-0.5">
                <li>admin@pressing.test — accès total</li>
                <li>caissier@pressing.test — caisse</li>
                <li>atelier@pressing.test — atelier</li>
            </ul>
        </div>
    @endif
@endsection
