@extends('layouts.app')

@section('title', 'Ticket ' . $order->ticket_no)

{{-- ÉTAPE 1 — FICHE TICKET : suivi détaillé + retrait + impressions --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-mono text-2xl font-bold text-slate-900">{{ $order->ticket_no }}</h1>
            <p class="text-sm text-slate-500">
                Dépôt du {{ $order->created_at->format('d/m/Y H:i') }} par {{ $order->cashier?->name ?? '—' }}
                @if ($order->delivered_at) · livré le {{ $order->delivered_at->format('d/m/Y H:i') }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            {{-- Impressions (HTML autonome → impression navigateur) --}}
            <a href="{{ route('orders.print.ticket', $order) }}" target="_blank" class="btn-ghost gap-2">
                <x-icon name="printer" class="w-4 h-4" />
                Ticket
            </a>
            <a href="{{ route('orders.print.labels', $order) }}" target="_blank" class="btn-ghost gap-2">
                <x-icon name="tags" class="w-4 h-4" />
                Étiquettes
            </a>
            @if ($order->status !== \App\Enums\OrderStatus::Livre)
                <a href="#settle" class="btn-primary gap-2">
                    <x-icon name="wallet" class="w-4 h-4" />
                    Encaisser & livrer
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Articles confiés --}}
            <div class="card">
                <h2 class="px-4 py-3 font-semibold text-slate-900">Articles confiés</h2>
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>Code-barres</th>
                            <th class="px-4 py-2">Prestation</th>
                            <th class="px-4 py-2">Emplacement</th>
                            <th class="px-4 py-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr class="border-b border-slate-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $item->barcode }}</td>
                                <td class="px-4 py-2">
                                    {{ $item->service->name }}
                                    <span class="block text-xs text-slate-400">
                                        {{ $item->description ?? '—' }} · {{ $item->quantity }} × {{ number_format($item->unit_price, 0, ',', ' ') }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $item->location ?? '—' }}</td>
                                <td class="px-4 py-2"><span class="badge {{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Historique complet (audit) --}}
            <div class="card p-4">
                <h2 class="mb-3 flex items-center gap-2 font-semibold text-slate-900">
                    <x-icon name="history" class="w-4 h-4 text-slate-400" />
                    Historique des scans
                </h2>
                <ol class="space-y-2">
                    @forelse ($order->items->flatMap->statusLogs->sortByDesc('created_at') as $log)
                        <li class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="font-mono text-xs text-slate-400">{{ $log->created_at->format('d/m H:i') }}</span>
                            <span class="font-mono text-xs">{{ $log->orderItem->barcode }}</span>
                            <span class="badge {{ \App\Enums\OrderStatus::tryFrom($log->to)?->badgeClass() ?? '' }}">
                                {{ \App\Enums\OrderStatus::tryFrom($log->to)?->label() ?? $log->to }}
                            </span>
                            <span class="text-xs text-slate-400">par {{ $log->user?->name ?? 'système' }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400">Aucun mouvement enregistré.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        {{-- Colonne droite : client, montants, encaissement --}}
        <div class="space-y-6">
            <div class="card p-4">
                <h2 class="mb-2 flex items-center gap-2 font-semibold text-slate-900">
                    <x-icon name="user" class="w-4 h-4 text-slate-400" />
                    Client
                </h2>
                <p class="font-semibold">{{ $order->client->name }}</p>
                <p class="text-sm text-slate-500">{{ $order->client->phone ?? '—' }}</p>
                <a href="{{ route('clients.show', $order->client) }}" class="mt-2 inline-block text-xs font-semibold text-sky-700 hover:underline">
                    Voir la fiche
                </a>
            </div>

            <div class="card p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Montants ({{ config('pressing.currency', 'FCFA') }})</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Total</dt><dd>{{ number_format($order->total_amount, 0, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Remise</dt><dd>- {{ number_format($order->discount_amount, 0, ',', ' ') }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Net</dt><dd>{{ number_format($order->net_amount, 0, ',', ' ') }}</dd></div>
                    <div class="flex justify-between text-emerald-700"><dt>Déjà réglé</dt><dd>{{ number_format($order->paid_amount, 0, ',', ' ') }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-1 text-base font-bold {{ $order->balance_due > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                        <dt>Solde dû</dt><dd>{{ number_format($order->balance_due, 0, ',', ' ') }}</dd>
                    </div>
                </dl>

                {{-- Paiements enregistrés --}}
                @if ($order->payments->isNotEmpty())
                    <ul class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                        @foreach ($order->payments as $p)
                            <li class="flex justify-between">
                                <span>{{ $p->created_at->format('d/m H:i') }} · {{ $p->method }}</span>
                                <span class="font-semibold">{{ number_format($p->amount, 0, ',', ' ') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- PAIEMENT PARTIEL : régler une partie du solde SANS livrer --}}
            @if ($order->status !== \App\Enums\OrderStatus::Livre && $order->balance_due > 0)
                <div id="partial-payment" class="card p-4 ring-1 ring-amber-200">
                    <h2 class="mb-1 font-semibold text-slate-900">Payer une partie du solde</h2>
                    <p class="mb-3 text-xs text-slate-500">Encaissement sans livraison — plusieurs versements possibles.</p>
                    <form method="POST" action="{{ route('orders.pay', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="label">Montant versé</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $order->balance_due }}" name="amount"
                                   value="{{ $order->balance_due }}" class="input text-right font-semibold" required>
                        </div>
                        <div>
                            <label class="label">Moyen</label>
                            <select name="method" class="input">
                                <option value="cash">Espèces</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="card">Carte</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Référence (optionnel)</label>
                            <input type="text" name="reference" class="input" placeholder="ID transaction…">
                        </div>
                        <button class="btn-primary w-full gap-2">
                            <x-icon name="hand-coins" class="w-4 h-4" />
                            Enregistrer le paiement
                        </button>
                    </form>
                </div>
            @endif

            {{-- Retrait : encaissement du solde + livraison --}}
            @if ($order->status !== \App\Enums\OrderStatus::Livre)
                <div id="settle" class="card p-4">
                    <h2 class="mb-3 font-semibold text-slate-900">Retrait</h2>
                    <form method="POST" action="{{ route('orders.settle', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="label">Montant encaissé</label>
                            <input type="number" step="0.01" min="0" name="amount"
                                   value="{{ $order->balance_due }}" class="input text-right font-semibold">
                        </div>
                        <div>
                            <label class="label">Moyen</label>
                            <select name="method" class="input">
                                <option value="cash">Espèces</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="card">Carte</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Référence (optionnel)</label>
                            <input type="text" name="reference" class="input" placeholder="ID transaction…">
                        </div>
                        <button class="btn-primary w-full gap-2">
                            <x-icon name="check" class="w-4 h-4" />
                            Valider le retrait
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
