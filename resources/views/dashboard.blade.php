@extends('layouts.app')

@section('title', 'Tableau de bord')

{{-- ÉTAPE 1 — TABLEAU DE BORD : indicateurs + derniers dépôts --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tableau de bord</h1>
            <p class="text-sm text-slate-500">{{ today()->translatedFormat('l d F Y') }} — {{ config('pressing.currency', 'FCFA') }}</p>
        </div>
        @if(auth()->user()?->isCashier() || auth()->user()?->isAdmin())
            <a href="{{ route('orders.create') }}" class="btn-primary gap-2">
                <x-icon name="plus" class="w-4 h-4" />
                Nouveau dépôt
            </a>
        @endif
        <a href="{{ route('workshop.board') }}" class="btn-ghost gap-2">
            <x-icon name="scan-line" class="w-4 h-4" />
            Aller à l'atelier
        </a>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card flex items-center gap-4 p-5">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700">
                <x-icon name="folder-open" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Dossiers ouverts</p>
                <p class="text-3xl font-bold text-slate-900">{{ $openOrders }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                <x-icon name="check" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Prêts à rendre</p>
                <p class="text-3xl font-bold text-emerald-600">{{ $readyOrders }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-700">
                <x-icon name="alarm-clock" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">En retard</p>
                <p class="text-3xl font-bold text-rose-600">{{ $overdueOrders }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700">
                <x-icon name="wallet" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Encaissé aujourd'hui</p>
                <p class="text-3xl font-bold text-sky-700">
                    {{ number_format($revenueToday, 0, ',', ' ') }} <span class="text-base">{{ $currency }}</span>
                </p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Derniers dépôts --}}
        <div class="card xl:col-span-2">
            <div class="flex items-center justify-between px-4 py-3">
                <h2 class="font-semibold text-slate-900">Derniers dépôts</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr class="border-b border-slate-100">
                        <th class="px-4 py-2">Ticket</th>
                        <th class="px-4 py-2">Client</th>
                        <th class="px-4 py-2">Articles</th>
                        <th class="px-4 py-2">Statut</th>
                        <th class="px-4 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>                @forelse ($recentOrders as $order)
                    <tr class="border-b border-slate-50 hover:bg-sky-50/40 {{ $order->promised_at && $order->promised_at->isPast() && $order->status !== \App\Enums\OrderStatus::Livre ? 'is-overdue' : '' }}">
                        <td class="px-4 py-2 font-mono text-xs">
                            <a class="font-semibold text-sky-700 hover:underline" href="{{ route('orders.show', $order) }}">
                                {{ $order->ticket_no }}
                            </a>
                        </td>
                        <td class="px-4 py-2">
                            {{ $order->client->name }}
                            @if ($order->client->phone)
                                <a href="tel:{{ $order->client->phone }}" class="block text-xs text-sky-700 hover:underline">{{ $order->client->phone }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $order->items->count() }}</td>
                        <td class="px-4 py-2"><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format($order->net_amount, 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    {{-- Empty state : première utilisation, CTA direct --}}
                    <tr><td colspan="5" class="px-4 py-8 text-center">
                        <p class="text-slate-400">Aucun dépôt enregistré.</p>
                        @if (auth()->user()?->isCashier() || auth()->user()?->isAdmin())
                            <a href="{{ route('orders.create') }}" class="btn-primary btn-sm mt-3 gap-2">
                                <x-icon name="plus" class="w-4 h-4" />
                                Créer le premier dépôt
                            </a>
                        @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Retards --}}
        <div class="card p-4">
            <h2 class="mb-3 flex items-center gap-2 font-semibold text-slate-900">
                <x-icon name="alarm-clock" class="w-4 h-4 text-rose-600" />
                Retards à traiter
            </h2>
            <p class="mb-3 text-xs text-slate-400">Dossiers ouverts dont la date promise est dépassée.</p>
            <ul class="space-y-2">
                @forelse ($recentOrders->filter(fn ($o) => $o->promised_at && $o->promised_at->isPast() && $o->status !== \App\Enums\OrderStatus::Livre) as $order)
                    <li class="rounded-lg bg-rose-50 px-3 py-2 text-sm ring-1 ring-rose-100">
                        <a href="{{ route('orders.show', $order) }}" class="font-semibold text-rose-700 hover:underline">{{ $order->ticket_no }}</a>
                        — {{ $order->client->name }}
                        <span class="block text-xs text-rose-500">promis le {{ $order->promised_at->format('d/m H\h') }}</span>
                    </li>
                @empty
                    <li class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        <x-icon name="circle-check" class="w-4 h-4 shrink-0" />
                        Aucun retard, tout est sous contrôle.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
