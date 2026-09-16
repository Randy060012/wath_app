@extends('layouts.guest')

@section('title', 'Créer un compte')

{{-- Carte élargie (format RECTANGULAIRE paysage, 2 colonnes) :
     la section 'card_wide' est consommée par le layout guest. --}}
@section('card_wide', true)

{{-- INSCRIPTION SELF-SERVICE : le nouveau client crée SON compte
     (propriétaire de son groupe) ET sa première agence en un seul
     formulaire — deux colonnes côte à côte sur écran suffisamment large. --}}
@section('content')
    <div class="mb-5">
        <h2 class="text-lg font-bold text-slate-900">Créer votre espace</h2>
        <p class="mt-1 text-sm text-slate-500">
            Un compte propriétaire + votre première agence — prêt en 1 minute.
        </p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf

        {{-- ============ DEUX COLONNES : COMPTE | AGENCE ============ --}}
        <div class="grid gap-6 sm:grid-cols-2">

            {{-- ---- Colonne 1 : VOTRE COMPTE (propriétaire) ---- --}}
            <fieldset class="min-w-0">
                <legend class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-sky-700">
                    <x-icon name="user" class="w-3.5 h-3.5" />
                    Votre compte (propriétaire)
                </legend>

                <div class="space-y-4">
                    <div>
                        <label class="label" for="name">Nom complet</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required
                               class="input @error('name') input-error @enderror" placeholder="Ex : Awa Diop">
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="email">E-mail</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                               autocomplete="username"
                               class="input @error('email') input-error @enderror" placeholder="vous@exemple.com">
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="password">Mot de passe</label>
                            <input id="password" type="password" name="password" required
                                   autocomplete="new-password" minlength="6"
                                   class="input @error('password') input-error @enderror">
                            @error('password') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="password_confirmation">Confirmation</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required
                                   autocomplete="new-password"
                                   class="input @error('password_confirmation') input-error @enderror">
                            @error('password_confirmation') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="form-hint">Au moins 6 caractères, avec lettres et chiffres.</p>
                </div>
            </fieldset>

            {{-- ---- Colonne 2 : VOTRE PREMIÈRE AGENCE ---- --}}
            <fieldset class="min-w-0">
                <legend class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-sky-700">
                    <x-icon name="building-2" class="w-3.5 h-3.5" />
                    Votre première agence
                </legend>

                <div class="space-y-4">
                    <div>
                        <label class="label" for="agency_name">Nom du pressing</label>
                        <input id="agency_name" type="text" name="agency_name" value="{{ old('agency_name') }}" required
                               class="input @error('agency_name') input-error @enderror"
                               placeholder="Ex : Pressing Plateau">
                        @error('agency_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="agency_phone">Téléphone</label>
                            <input id="agency_phone" type="text" name="agency_phone" value="{{ old('agency_phone') }}"
                                   class="input" placeholder="Optionnel">
                        </div>
                        <div>
                            <label class="label" for="agency_email">E-mail agence</label>
                            <input id="agency_email" type="email" name="agency_email" value="{{ old('agency_email') }}"
                                   class="input" placeholder="Optionnel">
                        </div>
                    </div>

                    <div>
                        <label class="label" for="agency_address">Adresse</label>
                        <input id="agency_address" type="text" name="agency_address" value="{{ old('agency_address') }}"
                               class="input" placeholder="Optionnel — affichée sur le ticket">
                        @error('agency_address') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <p class="flex items-start gap-1.5 rounded-lg bg-sky-50 p-2.5 text-xs text-sky-800">
                        <x-icon name="sparkles" class="mt-0.5 w-3.5 h-3.5 shrink-0" />
                        Le catalogue standard est copié automatiquement — vous
                        pourrez l'ajuster ensuite, et ajouter d'autres agences
                        depuis l'écran « Agences ».
                    </p>
                </div>
            </fieldset>
        </div>

        {{-- ============ PIED : CONDITIONS + BOUTON ============ --}}
        <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <label class="flex flex-1 cursor-pointer items-start gap-2 text-sm text-slate-600">
                <input type="checkbox" name="terms" value="1" required
                       class="checkbox mt-0.5 @error('terms') input-error @enderror">
                <span>J'accepte les conditions d'utilisation de la plateforme.</span>
            </label>
            @error('terms') <p class="form-error">{{ $message }}</p> @enderror

            <button type="submit" class="btn-primary btn-lg shrink-0">
                Créer mon compte et mon agence
            </button>
        </div>
    </form>

    <p class="mt-5 text-sm text-slate-500">
        Vous avez déjà un compte ?
        <a href="{{ route('login') }}" class="font-semibold text-sky-700 hover:underline">Se connecter</a>
    </p>
@endsection
