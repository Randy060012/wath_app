@extends('layouts.app')

@section('title', 'Dépôts & retraits')

{{-- ÉTAPE 1 — LISTE DES DÉPÔTS en DataTable interactif :
     recherche, filtre statut, tri par colonne, pagination gérés
     côté navigateur par public/assets/js/datatable.js. --}}
@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Dépôts & retraits</h1>
        <a href="{{ route('orders.create') }}" class="btn-primary gap-2">
            <x-icon name="plus" class="w-4 h-4" />
            Nouveau dépôt
        </a>
    </div>

    {{-- data-datatable : le JS ajoute barre de recherche, filtres, tri et pagination.
         data-row-link : chaque ligne est cliquable en entier (tr[data-href]).
         data-row-menu : colonne ⋮ + clic droit → menu contextuel d'actions
         rapides (impressions, "Marquer prêt") alimenté par le <template> ci-dessous. --}}
    <div class="card overflow-hidden" data-datatable data-row-link data-row-menu data-state-key="orders" data-csv="depots">
        <table class="data-table">
            <thead>
                <tr>
                    {{-- data-sort : colonne triable ; data-type : mode de tri --}}
                    <th data-sort data-type="text">Ticket</th>
                    <th data-sort data-type="text">Client</th>
                    <th data-sort data-type="num" class="!text-center">Articles</th>
                    <th data-sort data-type="text" data-filter-label="Filtrer : statut">Statut</th>
                    <th data-sort data-type="date">Promis le</th>
                    <th data-sort data-type="num" class="!text-right">Net</th>
                    <th data-sort data-type="num" class="!text-right">Solde</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    {{-- data-href : ligne cliquable → fiche ticket.
                         data-menu-params : injecte {id} dans les URLs du template.
                         is-overdue : fond rosé si la date promise est dépassée.
                         data-menu-ready : active l'entrée "Marquer prêt". --}}
                    <tr data-href="{{ route('orders.show', $order) }}"
                        data-menu-params="id:{{ $order->id }}"
                        @if ($order->canBeMarkedReady()) data-menu-ready="1" @endif
                        @class(['opacity-60' => $order->status === \App\Enums\OrderStatus::Livre,
                                'is-overdue' => $order->promised_at && $order->promised_at->isPast() && $order->status !== \App\Enums\OrderStatus::Livre && $order->status !== \App\Enums\OrderStatus::Perdu])>
                        <td class="font-mono text-xs">
                            <a class="font-semibold text-sky-700 hover:underline" href="{{ route('orders.show', $order) }}">{{ $order->ticket_no }}</a>
                        </td>
                        <td>
                            {{ $order->client->name }}
                            @if ($order->client->phone)
                                <a href="tel:{{ $order->client->phone }}" data-noclick class="block text-xs text-sky-700 hover:underline">{{ $order->client->phone }}</a>
                            @endif
                        </td>
                        <td class="text-center">{{ $order->items->count() }}</td>
                        <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        {{-- data-timestamp : utilisé par le tri data-type="date" --}}
                        <td class="text-xs {{ $order->promised_at && $order->promised_at->isPast() && $order->status !== \App\Enums\OrderStatus::Livre ? 'text-rose-600 font-semibold' : 'text-slate-500' }}"
                            @if($order->promised_at) data-timestamp="{{ $order->promised_at->timestamp }}" @endif>
                            {{ $order->promised_at?->format('d/m H\h') ?? '—' }}
                        </td>
                        <td class="text-right">{{ number_format($order->net_amount, 0, ',', ' ') }}</td>
                        <td class="text-right font-semibold {{ $order->balance_due > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ number_format($order->balance_due, 0, ',', ' ') }}
                        </td>
                    </tr>
                @empty
                    {{-- colspan 7 : le JS ajoutera la colonne Actions en 8e --}}
                    <tr><td colspan="7" class="empty-cell" data-empty-text="Aucun dépôt trouvé.">Aucun dépôt trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ==================================================================
         TEMPLATE DU MENU CONTEXTUEL — une entrée = un élément data-action.
         data-url avec {id} : injecté par ligne via data-menu-params="id:N".
         Types : link (nouvel onglet), post (POST CSRF + rechargement).
         Les entrées POST rechargent la page pour afficher le flash serveur.
         ================================================================== --}}
    <template data-menu-template>
        <button data-action="link"
                data-url="{{ route('orders.show', ['order' => '__ID__']) }}"
                data-confirm="">
            <x-icon name="eye" class="w-4 h-4" /> Voir la fiche
        </button>
        <button data-action="link"
                data-url="{{ route('orders.print.ticket', '__ID__') }}"
                data-confirm="">
            <x-icon name="printer" class="w-4 h-4" /> Imprimer le ticket
        </button>
        <button data-action="link"
                data-url="{{ route('orders.print.labels', '__ID__') }}"
                data-confirm="">
            <x-icon name="tags" class="w-4 h-4" /> Imprimer les étiquettes
        </button>
        {{-- data-needs="ready" : l'entrée n'apparaît que si la ligne porte
             data-menu-ready (commande dont TOUS les articles sont Repassé).
             Évite de marquer prête une commande non finie d'un simple clic. --}}
        <button data-action="post"
                data-url="{{ route('orders.markReady', '__ID__') }}"
                data-confirm="Marquer cette commande comme prête ? Le client sera notifié."
                data-needs="ready">
            <x-icon name="circle-check" class="w-4 h-4" /> Marquer prêt
        </button>
    </template>
@endsection
