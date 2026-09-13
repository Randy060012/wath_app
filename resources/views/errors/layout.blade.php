<!DOCTYPE html>
{{-- ÉTAPE 1 — PAGES D'ERREUR BRANDÉES (404, 500, 403…)
     Laravel rend automaticement error::404 / errors::500 si ces vues
     existent. Layout minimal autonome : même identité que le login. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ config('app.name', 'Pressing Pro') }}</title>
    <meta name="error-message" content="@yield('message')">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    {{-- Icônes Lucide (le layout d'erreur est autonome, sans Alpine) --}}
    <script defer src="{{ asset('assets/vendor/lucide.min.js') }}"></script>
    <script defer>document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons());</script>
</head>
<body class="grid min-h-screen place-items-center bg-sky-900 px-4">
    <div class="w-full max-w-md text-center">
        <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-sky-700 text-white">
            <i data-lucide="{{ $icon }}"></i>
        </div>
        <p class="text-5xl font-bold text-white">{{ $code }}</p>
        <p class="mt-2 text-sm text-sky-200">{{ $title }}</p>
        <p class="mt-1 text-sm text-sky-300">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn-primary mt-6 gap-2 inline-flex">
            <i data-lucide="arrow-left"></i>
            Retour à l'accueil
        </a>
    </div>
</body>
</html>
