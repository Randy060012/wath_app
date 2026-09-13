<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ config('app.name', 'Pressing Pro') }}</title>

    {{-- ÉTAPE 1 — ASSETS STATIQUES (aucun Node.js) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

    <script defer src="{{ asset('assets/vendor/alpine.min.js') }}"></script>
    <script defer src="{{ asset('assets/vendor/lucide.min.js') }}"></script>
    <script defer src="{{ asset('assets/js/app.js') }}"></script>
</head>
<body class="grid min-h-screen place-items-center bg-sky-900 px-4">

    {{-- ÉTAPE 1 — Layout "invité" : utilisé uniquement par la page de login --}}
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            {{-- Logo : gouttes d'eau (blanchisserie) --}}
            <div class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-sky-700 text-white">
                <x-icon name="droplets" class="w-7 h-7" />
            </div>
            <h1 class="text-xl font-bold text-white">{{ config('app.name', 'Pressing Pro') }}</h1>
            <p class="text-sm text-sky-300">Gestion pressing & blanchisserie</p>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-xl">
            @yield('content')
        </div>
    </div>

</body>
</html>
