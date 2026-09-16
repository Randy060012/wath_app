<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    /* ----------------------------------------------------------------------
     * NAVIGATION calculée UNE fois ici : partagée par la sidebar desktop et
     * le drawer mobile (partials/nav.blade.php). Filtrée par rôle Spatie.
     * ---------------------------------------------------------------------- */
    $u = auth()->user();
    $isCashier = $u?->isCashier() || $u?->isAdmin();
    $isWorkshop = $u?->isWorkshop() || $u?->isAdmin();
    $isAdmin = $u?->isAdmin();

    $nav = [];
    $nav[] = ['label' => 'Tableau de bord', 'icon' => 'layout-dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard')];

    if ($isCashier) {
        $nav[] = ['label' => 'Dépôts & retraits', 'icon' => 'receipt-text', 'url' => route('orders.index'), 'active' => request()->routeIs('orders.*')];
        $nav[] = ['label' => 'Caisse du jour', 'icon' => 'wallet', 'url' => route('caisse.dashboard'), 'active' => request()->routeIs('caisse.*')];
    }
    if ($isWorkshop) {
        $nav[] = ['label' => 'Atelier', 'icon' => 'washing-machine', 'url' => route('workshop.board'), 'active' => request()->routeIs('workshop.*')];
    }
    if ($isCashier) {
        $nav[] = ['label' => 'Clients', 'icon' => 'users', 'url' => route('clients.index'), 'active' => request()->routeIs('clients.*')];
        // SPÉCIFICATIONS C — module documentaire Devis / Proformas
        $nav[] = ['label' => 'Proformas', 'icon' => 'file-text', 'url' => route('proformas.index'), 'active' => request()->routeIs('proformas.*')];
    }
    if ($isAdmin) {
        $nav[] = ['label' => '__SEPARATOR__', 'icon' => '', 'url' => '#', 'active' => false, 'separator' => true];
        $nav[] = ['label' => 'Catalogue', 'icon' => 'tags', 'url' => route('admin.services.index'), 'active' => request()->routeIs('admin.services.*')];
        $nav[] = ['label' => 'Stock', 'icon' => 'package', 'url' => route('admin.inventory.index'), 'active' => request()->routeIs('admin.inventory.*')];
        $nav[] = ['label' => 'Rapports', 'icon' => 'trending-up', 'url' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports.*')];

        // MULTI-TENANT — écrans GROUPE : super-admin (plateforme)
        // OU propriétaire self-service (gestionnaire de son groupe).
        if ($u->managesGroup()) {
            $nav[] = ['label' => 'Agences', 'icon' => 'building-2', 'url' => route('admin.agencies.index'), 'active' => request()->routeIs('admin.agencies.*')];
            $nav[] = ['label' => 'Utilisateurs', 'icon' => 'user-cog', 'url' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*')];
        }
    }
    // Action rapide : visible uniquement dans le drawer mobile
    if ($isCashier) {
        $nav[] = ['label' => 'Nouveau dépôt', 'icon' => 'plus', 'url' => route('orders.create'), 'active' => false, 'mobile_only' => true];
    }
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tableau de bord') — {{ config('app.name', 'Pressing Pro') }}</title>

    {{-- ÉTAPE 1 — ASSETS STATIQUES (aucun Node.js) --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    {{-- CSS compilé une fois pour toutes par le CLI standalone Tailwind
         (source : build/tailwind.input.css → régénérer via ./build.sh). --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

    {{-- Ordre de chargement (defer) : Alpine → Lucide → DataTable → app.js --}}
    <script defer src="{{ asset('assets/vendor/alpine.min.js') }}"></script>
    <script defer src="{{ asset('assets/vendor/lucide.min.js') }}"></script>
    <script defer src="{{ asset('assets/js/datatable.js') }}"></script>
    <script defer src="{{ asset('assets/js/app.js') }}"></script>

    {{-- Keep-alive session : un ping toutes les 5 min maintient la session
         ouverte pendant la saisie d'un long ticket (anti-déconnexion). --}}
    @if (auth()->check())
        <meta name="keep-alive-url" content="{{ route('dashboard') }}">
    @endif
</head>
<body class="min-h-screen bg-sky-50/50 text-slate-800 font-sans">

<div class="flex min-h-screen">

    {{-- ================= SIDEBAR DESKTOP ================= --}}
    <aside class="hidden w-64 shrink-0 flex-col bg-sky-900 text-sky-100 md:flex">
        @include('layouts.partials.brand')
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm" aria-label="Navigation principale">
            @include('layouts.partials.nav', ['nav' => $nav, 'mobile' => false])
        </nav>
        @include('layouts.partials.userbox')
    </aside>

    {{-- ================= CONTENU (pleine largeur) ================= --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- Barre supérieure --}}
        <header class="no-print flex items-center justify-between gap-4 border-b border-sky-100 bg-white px-4 py-3 md:px-6">
            {{-- Burger : ouvre le drawer mobile (composant Alpine "mobileNav") --}}
            <button type="button"
                    x-data @click="$dispatch('open-mobile-nav')"
                    class="btn-ghost btn-icon md:hidden"
                    aria-label="Ouvrir le menu de navigation">
                <x-icon name="menu" class="w-5 h-5" />
            </button>

            <div class="hidden md:block text-sm text-slate-500">
                @yield('header_hint', "Bienvenue ! Voici l'activité du jour.")
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-ghost btn-sm gap-2">
                    <x-icon name="log-out" class="w-3.5 h-3.5" />
                    Déconnexion
                </button>
            </form>
        </header>

        {{-- max-w-none : le contenu occupe TOUT l'espace disponible --}}
        <main class="w-full max-w-none flex-1 px-4 py-6 md:px-6 md:py-8">
            {{-- Flash messages (succès / erreur) — convertis en toasts par app.js --}}
            @if (session('success'))
                <div data-toast="success" class="hidden">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                {{-- TOUTES les erreurs de validation, pas seulement la première --}}
                <div data-toast="error" class="hidden">
                    <strong>Formulaire incomplet :</strong>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- ================= DRAWER MOBILE (partagé avec la sidebar) ================= --}}
<div x-data="{ open: false }"
     @open-mobile-nav.window="open = true"
     @keydown.escape.window="open = false"
     x-cloak>
    {{-- Voile --}}
    <div x-show="open" @click="open = false" x-transition.opacity
         class="fixed inset-0 z-40 bg-sky-950/50"></div>
    {{-- Panneau latéral --}}
    <aside x-show="open"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-sky-900 text-sky-100">
        @include('layouts.partials.brand')
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm" aria-label="Navigation mobile"
             @click="if ($event.target.closest('a')) open = false">
            @include('layouts.partials.nav', ['nav' => $nav, 'mobile' => true])
        </nav>
        @include('layouts.partials.userbox')
    </aside>
</div>

{{-- ================= CONTENEUR DES TOASTS ================= --}}
<div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-[70] flex w-80 flex-col gap-2"></div>

</body>
</html>
