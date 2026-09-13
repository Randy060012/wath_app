@extends('layouts.app')

@section('title', 'Caisse du jour')

@section('header_hint', 'Encaissements, retraits attendus et retards du jour.')

{{-- ÉTAPE 1 — CAISSE DU JOUR (repensée) :
     1) bandeau KPI : encaissé / à encaisser / dépôts / retards ;
     2) file "À encaisser maintenant" : commandes PRÊTES avec solde,
        encaissement INLINE sans quitter l'écran (formulaire dépliable) ;
     3) dépôts du jour (statut + heure promise) ;
     4) journal des encaissements + répartition par moyen de paiement ;
     5) retards sous forme de pastilles.
     Tout est borné à AUJOURD'HUI côté contrôleur (CashRegisterController). --}}
@section('content')
@php
    // Jour en français, indépendant de la locale du serveur
    $frDays = ['Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi',
        'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche'];
    $frMonths = ['January' => 'janvier', 'February' => 'février', 'March' => 'mars', 'April' => 'avril',
        'May' => 'mai', 'June' => 'juin', 'July' => 'juillet', 'August' => 'août',
        'September' => 'septembre', 'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'];
    $todayLabel = $frDays[now()->format('l')] . ' ' . now()->format('j') . ' ' . $frMonths[now()->format('F')] . ' ' . now()->format('Y');

    // Icône par moyen de paiement (aucun emoji — Lucide uniquement)
    $methodIcon = ['cash' => 'banknote', 'mobile_money' => 'smartphone', 'card' => 'credit-card'];
@endphp

{{-- ================= EN-TÊTE ================= --}}
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Caisse du jour</h1>
        <p class="flex items-center gap-1.5 text-sm capitalize text-slate-500">
            <x-icon name="calendar" class="w-4 h-4" />
            {{ $todayLabel }}
        </p>
    </div>
    <a href="{{ route('orders.create') }}" class="btn-primary gap-2">
        <x-icon name="plus" class="w-4 h-4" />
        Nouveau dépôt
    </a>
</div>

{{-- ================= BANDEAU KPI ================= --}}
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    {{-- Encaissé --}}
    <div class="card flex items-center gap-3 p-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
            <x-icon name="hand-coins" class="w-5 h-5" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-400">Encaissé</p>
            <p class="truncate text-lg font-bold text-slate-900">{{ number_format($cashToday, 0, ',', ' ') }} {{ $currency }}</p>
        </div>
    </div>

    {{-- Reste à encaisser (dossiers prêts) --}}
    <div class="card flex items-center gap-3 p-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
            <x-icon name="wallet" class="w-5 h-5" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-400">À encaisser</p>
            <p class="truncate text-lg font-bold {{ $outstanding > 0 ? 'text-sky-700' : 'text-slate-400' }}">{{ number_format($outstanding, 0, ',', ' ') }} {{ $currency }}</p>
        </div>
    </div>

    {{-- Dépôts du jour --}}
    <div class="card flex items-center gap-3 p-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
            <x-icon name="receipt-text" class="w-5 h-5" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-400">Dépôts du jour</p>
            <p class="text-lg font-bold text-slate-900">{{ $todayOrders->count() }}</p>
        </div>
    </div>

    {{-- Retards --}}
    <a href="#overdue" class="card flex items-center gap-3 p-4 {{ $overdue->isNotEmpty() ? 'ring-1 ring-rose-200' : '' }}">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $overdue->isNotEmpty() ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-400' }}">
            <x-icon name="alarm-clock" class="w-5 h-5" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-400">En retard</p>
            <p class="text-lg font-bold {{ $overdue->isNotEmpty() ? 'text-rose-600' : 'text-slate-400' }}">{{ $overdue->count() }}</p>
        </div>
    </a>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

    {{-- ================= COLONNE GAUCHE : ENCAISSEMENTS ================= --}}
    <div class="space-y-6">
        {{-- À encaisser maintenant --}}
        <div class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">
                <x-icon name="package-check" class="w-4 h-4 text-sky-700" />
                À encaisser maintenant
                <span class="ml-auto rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700">{{ $readyToCollect->count() }}</span>
            </h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($readyToCollect as $order)
                    <li x-data="{ open: false }" class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <a class="font-mono text-xs font-semibold text-sky-700 hover:underline" href="{{ route('orders.show', $order) }}">{{ $order->ticket_no }}</a>
                                <span class="ml-2 text-sm font-medium">{{ $order->client->name }}</span>
                                @if ($order->client->phone)
                                    <a href="tel:{{ $order->client->phone }}" class="ml-1 align-middle text-xs text-slate-400 hover:text-sky-700" title="Appeler">
                                        <x-icon name="phone" class="inline w-3.5 h-3.5" />
                                    </a>
                                @endif
                                {{-- Retard sur la promesse --}}
                                @if ($order->promised_at && $order->promised_at->isPast())
                                    <span class="ml-2 inline-flex items-center gap-1 text-xs font-semibold text-rose-600">
                                        <x-icon name="clock-4" class="w-3.5 h-3.5" />
                                        promis {{ $order->promised_at->format('H:i') }}
                                    </span>
                                @elseif ($order->promised_at)
                                    <span class="ml-2 inline-flex items-center gap-1 text-xs text-slate-400">
                                        <x-icon name="clock" class="w-3.5 h-3.5" />
                                        promis {{ $order->promised_at->format('H:i') }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-right text-sm font-bold text-rose-600">{{ number_format($order->balance_due, 0, ',', ' ') }} {{ $currency }}</span>
                                <button type="button" class="btn-primary btn-sm gap-1" @click="open = !open">
                                    <x-icon name="wallet" class="w-3.5 h-3.5" />
                                    Encaisser
                                </button>
                            </div>
                        </div>

                        {{-- Encaissement INLINE (sans quitter l'écran) —
                             le garde anti double-soumission global s'applique. --}}
                        <form x-show="open" x-cloak method="POST" action="{{ route('orders.settle', $order) }}"
                              class="mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-sky-50 p-3">
                            @csrf
                            <label class="sr-only" for="amount-{{ $order->id }}">Montant</label>
                            <input id="amount-{{ $order->id }}" type="number" name="amount" step="0.01" min="0"
                                   value="{{ $order->balance_due }}" class="input input-sm !w-32 text-right" required>
                            <label class="sr-only" for="method-{{ $order->id }}">Moyen</label>
                            <select id="method-{{ $order->id }}" name="method" class="input input-sm !w-40">
                                <option value="cash">Espèces</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="card">Carte</option>
                            </select>
                            <button class="btn-primary btn-sm gap-1">
                                <x-icon name="check" class="w-3.5 h-3.5" />
                                Valider le retrait
                            </button>
                            <button type="button" class="btn-ghost btn-sm" @click="open = false">Annuler</button>
                        </form>
                    </li>
                @empty
                    <li class="flex flex-col items-center gap-2 px-4 py-10 text-center">
                        <x-icon name="package-check" class="w-8 h-8 text-slate-300" />
                        <p class="text-sm text-slate-400">Rien à encaisser pour l'instant.</p>
                    </li>
                @endforelse
            </ul>
        </div>

        {{-- Déposés aujourd'hui --}}
        <div class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">
                <x-icon name="receipt-text" class="w-4 h-4 text-indigo-600" />
                Déposés aujourd'hui
                <span class="ml-auto rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ $todayOrders->count() }}</span>
            </h2>
            <table class="table-simple">
                <tbody>
                    @forelse ($todayOrders as $order)
                        <tr>
                            <td>
                                <a class="font-mono text-xs font-semibold text-sky-700 hover:underline" href="{{ route('orders.show', $order) }}">{{ $order->ticket_no }}</a>
                                <span class="block text-xs text-slate-400">{{ $order->client->name }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                                @if ($order->is_express)
                                    <span class="badge ml-1 bg-amber-100 text-amber-800">Express</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($order->balance_due > 0)
                                    <span class="text-xs font-semibold text-rose-600">solde {{ number_format($order->balance_due, 0, ',', ' ') }}</span>
                                @else
                                    <span class="text-xs font-semibold text-emerald-600">réglé</span>
                                @endif
                                <span class="block text-xs text-slate-400">{{ number_format($order->net_amount, 0, ',', ' ') }} {{ $currency }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Aucun dépôt aujourd'hui.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================= COLONNE DROITE : JOURNAL ================= --}}
    <div class="space-y-6">
        {{-- Répartition par moyen de paiement --}}
        <div class="card p-4">
            <h2 class="mb-3 flex items-center gap-2 font-semibold text-slate-900">
                <x-icon name="pie-chart" class="w-4 h-4 text-sky-700" />
                Répartition des encaissements
            </h2>
            @php $methodTotal = max(1, (float) $cashToday); @endphp
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach (['cash', 'mobile_money', 'card'] as $m)
                    @php $pct = round(100 * ($byMethod[$m] ?? 0) / $methodTotal); @endphp
                    <div class="rounded-lg bg-slate-50 p-3 text-center">
                        <p class="flex items-center justify-center gap-1.5 text-xs font-medium text-slate-500">
                            <x-icon name="{{ $methodIcon[$m] }}" class="w-3.5 h-3.5" />
                            {{ \App\Enums\PaymentMethod::from($m)->label() }}
                        </p>
                        <p class="mt-1 font-bold text-slate-900">{{ number_format($byMethod[$m] ?? 0, 0, ',', ' ') }}</p>
                        <div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full {{ $m === 'cash' ? 'bg-emerald-500' : ($m === 'mobile_money' ? 'bg-sky-500' : 'bg-indigo-500') }}"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $pct }} %</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Journal des encaissements --}}
        <div class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 font-semibold text-slate-900">
                <x-icon name="history" class="w-4 h-4 text-emerald-600" />
                Encaissements du jour
                <span class="ml-auto rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $paymentsToday->count() }}</span>
            </h2>
            <table class="table-simple">
                <tbody>
                    @forelse ($paymentsToday as $payment)
                        <tr>
                            <td class="w-14 text-xs text-slate-400">{{ $payment->created_at->format('H:i') }}</td>
                            <td>
                                <a class="font-mono text-xs font-semibold text-sky-700 hover:underline" href="{{ route('orders.show', $payment->order) }}">{{ $payment->order->ticket_no }}</a>
                                <span class="block text-xs text-slate-400">
                                    {{ $payment->order->client->name }} · {{ $payment->method->label() }}
                                    @if ($payment->user)
                                        · {{ $payment->user->name }}
                                    @endif
                                </span>
                            </td>
                            <td class="text-right font-semibold">{{ number_format($payment->amount, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Aucun encaissement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ================= RETARDS ================= --}}
<div id="overdue" class="card mt-6 p-4 @if ($overdue->isEmpty()) hidden @endif">
    <h2 class="mb-3 flex items-center gap-2 font-semibold text-rose-700">
        <x-icon name="alarm-clock" class="w-4 h-4" />
        Retards (promesse dépassée)
        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ $overdue->count() }}</span>
    </h2>
    <div class="flex flex-wrap gap-2">
        @foreach ($overdue as $order)
            <a href="{{ route('orders.show', $order) }}"
               class="flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-100">
                <x-icon name="clock-4" class="w-3.5 h-3.5" />
                {{ $order->ticket_no }} — {{ $order->client->name }}
                @if ($order->promised_at)
                    <span class="font-normal text-rose-400">depuis {{ $order->promised_at->diffForHumans() }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endsection
