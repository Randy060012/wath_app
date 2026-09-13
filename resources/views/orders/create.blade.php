@extends('layouts.app')

@section('title', 'Nouveau dépôt')

{{-- ÉTAPE 1 — ÉCRAN CAISSE "NOUVEAU DÉPÔT" (POS) --}}
{{-- Composant Alpine `pos` : catalogue filtrable + panier + client + acompte.
     La validation FINALE reste côté serveur (StoreOrderRequest) ; le JS
     n'est qu'une aide à la saisie ultra-rapide. --}}
@section('content')

<div x-data="pos({
        services: {{ Illuminate\Support\Js::from($services) }},
        currency: '{{ config('pressing.currency', 'FCFA') }}',
        expressRate: {{ (int) config('pressing.express_surcharge', 25) }},
        selectedClient: {{ Illuminate\Support\Js::from($selectedClient ?? null) }}
     })"
    x-init="restore()"
    @input.debounce.400ms.window="persist()"
    class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    {{-- ================= COLONNE GAUCHE : CATALOGUE ================= --}}
    <div class="space-y-4 xl:col-span-2">

        {{-- Recherche client (autocomplete JSON → /clients/search) --}}
        <div class="card p-4">
            <label class="label">Client</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                    <x-icon name="search" class="w-4 h-4" />
                </span>
                <input type="search" x-model="clientQuery" @input.debounce.300ms="searchClient()"
                    placeholder="Nom, téléphone ou code client…" class="input pl-9" autocomplete="off">
                {{-- Résultats de l'autocomplétion --}}
                <ul x-show="clientResults.length" x-cloak @click.outside="clientResults = []"
                    class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                    <template x-for="c in clientResults" :key="c.id">
                        <li>
                            {{-- SPÉCIFICATIONS A.1 — nom + badge Acteur/Client + contact --}}
                            <button type="button" @click="pickClient(c)"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-sky-50">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="truncate font-medium" x-text="c.name"></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="typeBadge(c.type)" x-text="typeLabel(c.type)"></span>
                                </span>
                                <span class="shrink-0 text-xs text-slate-400" x-text="c.phone || c.code"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Client sélectionné — nom, badge Acteur/Client, téléphone cliquable, adresse --}}
            <div x-show="client" x-cloak class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-sky-50 px-3 py-2">
                <span class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-sky-900">
                    <x-icon name="user" class="w-4 h-4 shrink-0" />
                    <span x-text="client?.name"></span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="typeBadge(client?.type)" x-text="typeLabel(client?.type)"></span>
                    <a x-show="client?.phone" :href="'tel:' + (client?.phone || '')"
                        class="text-xs font-normal text-sky-700 hover:underline" x-text="client?.phone"></a>
                    <span x-show="client?.address" class="w-full truncate text-xs font-normal text-slate-500"
                        x-text="client?.address"></span>
                </span>
                <button type="button" @click="client = null; clientQuery = ''" class="text-xs text-slate-400 hover:text-rose-600">changer</button>
            </div>

            {{-- SPÉCIFICATIONS A.1 — création à la volée SANS quitter la caisse,
                 ou fiche complète dans un onglet si l'on a l'adresse, etc. --}}
            <p class="mt-2 flex flex-wrap items-center gap-1 text-xs text-slate-400">
                Le client n'existe pas ?
                <button type="button" @click="openQuick()"
                    class="inline-flex items-center gap-1 font-semibold text-sky-700 hover:underline">
                    <x-icon name="user-plus" class="w-3 h-3" />
                    créer à la volée
                </button>
                ou
                <a href="{{ route('clients.create') }}" target="_blank"
                    class="inline-flex items-center gap-1 font-semibold text-sky-700 hover:underline">
                    fiche complète
                    <x-icon name="arrow-right" class="w-3 h-3" />
                </a>
            </p>
        </div>

        {{-- Catalogue des prestations --}}
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-semibold text-slate-900">Catalogue</h2>
                <div class="relative max-w-xs flex-1">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <x-icon name="search" class="w-4 h-4" />
                    </span>
                    <input type="search" x-ref="posFilter" x-model="filter" placeholder="Filtrer une prestation… — raccourci : /" class="input pl-9">
                </div>
            </div>

            {{-- Grille par catégorie --}}
            <div class="max-h-[520px] space-y-4 overflow-y-auto scroll-thin pr-1">
                <template x-for="cat in filteredCatalog" :key="cat.id">
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400" x-text="cat.name"></p>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
                            <template x-for="s in cat.services" :key="s.id">
                                <button type="button" @click="addItem(s)"
                                    class="rounded-lg border border-slate-200 bg-white p-3 text-left transition
                                               hover:border-sky-400 hover:bg-sky-50 active:scale-[.98]">
                                    <p class="text-sm font-semibold text-slate-800" x-text="s.name"></p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        <span x-text="formatMoney(s.price)"></span>
                                        <span x-text="s.pricing_unit === 'kg' ? ' /kg' : ''"></span>
                                    </p>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <p x-show="filteredCatalog.length === 0" x-cloak class="py-8 text-center text-sm text-slate-400">Aucune prestation trouvée.</p>
            </div>
        </div>
    </div>

    {{-- ================= COLONNE DROITE : PANIER / TICKET ================= --}}
    <!-- <form method="POST" action="{{ route('orders.store') }}" class="card h-fit p-4"
          @submit="markSubmitted()">
        @csrf

        <h2 class="flex items-center gap-2 font-semibold text-slate-900">
            <x-icon name="shopping-basket" class="w-4 h-4 text-sky-700" />
            Ticket en cours
        </h2>
        <p class="mb-3 text-xs text-slate-400">Quantité cliquable · remise · express · acompte</p>

        {{-- ID du client retenu (champ caché piloté par Alpine) --}}
        <input type="hidden" name="client_id" :value="client?.id ?? ''">

        {{-- Lignes du panier --}}
        <ul class="divide-y divide-slate-100">
            <template x-for="(line, i) in lines" :key="i">
                <li class="py-2">
                    <div class="flex items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" x-text="line.name"></p>
                        </div>
                        <input type="number" min="1" max="99" x-model.number="line.quantity"
                               class="input input-sm !w-14 text-center" aria-label="Quantité">
                        <span class="w-16 text-right text-sm font-semibold" x-text="formatMoney(line.price * line.quantity)"></span>
                        <button type="button" @click="removeItem(i)" class="text-slate-300 hover:text-rose-600">
                            <x-icon name="x" class="w-4 h-4" />
                        </button>
                    </div>
                    <input type="text" x-model="line.description" placeholder="Précision (couleur, tache…)"
                           class="mt-1 w-full border-0 bg-transparent p-0 text-xs text-slate-400 placeholder:text-slate-300 focus:ring-0">
                </li>
                {{-- Champs masqués envoyés au serveur pour CHAQUE ligne --}}
                <input type="hidden" :name="`items[${i}][service_id]`" :value="line.service_id">
                <input type="hidden" :name="`items[${i}][quantity]`" :value="line.quantity">
                <input type="hidden" :name="`items[${i}][description]`" :value="line.description ?? ''">
            </template>
            <li x-show="lines.length === 0" x-cloak class="py-8 text-center text-sm text-slate-400">
                Cliquez sur une prestation pour l'ajouter.
            </li>
        </ul>

        {{-- Options --}}
        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
            <label class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 font-medium">
                    <x-icon name="zap" class="w-4 h-4 text-amber-500" />
                    Service express (+<span x-text="expressRate"></span> %)
                </span>
                <input type="checkbox" x-model="express" class="checkbox">
            </label>
            <input type="hidden" name="is_express" :value="express ? 1 : 0">
            <label class="flex items-center justify-between text-sm">
                <span class="font-medium">Remise</span>
                <input type="number" step="0.01" min="0" x-model.number="discount" name="discount_amount"
                       class="input input-sm !w-24 text-right">
            </label>
            <label class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 font-medium">
                    <x-icon name="clock" class="w-4 h-4 text-slate-400" />
                    Retour promis
                </span>
                <input type="datetime-local" name="promised_at" class="input input-sm !w-44">
            </label>
        </div>

        {{-- Totaux --}}
        <div class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
            <div class="flex justify-between text-slate-500"><span>Sous-total</span>
                <span x-text="formatMoney(subtotal)"></span></div>
            <div class="flex justify-between text-slate-500"><span>Remise</span>
                <span>- <span x-text="formatMoney(discount)"></span></span></div>
            <div class="flex justify-between text-base font-bold text-slate-900">
                <span>Net à payer</span>
                <span x-text="formatMoney(net) + ' ' + currency"></span>
            </div>
        </div>

        {{-- Acompte --}}
        <div class="mt-3 border-t border-slate-100 pt-3">
            <label class="label">Acompte versé</label>
            <div class="mt-1 flex gap-2">
                <input type="number" step="0.01" min="0" x-model.number="deposit" name="deposit_amount"
                       class="input input-sm !w-28 text-right">
                <select name="deposit_method" class="input flex-1 py-1.5">
                    <option value="cash">Espèces</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="card">Carte</option>
                </select>
            </div>
            <p class="mt-1 text-xs text-slate-400">
                Reste au retrait : <strong x-text="formatMoney(Math.max(0, net - deposit))"></strong> <span x-text="currency"></span>
            </p>
        </div>

        {{-- Notes + validation --}}
        <textarea name="notes" rows="2" x-model="notes" placeholder="Note interne (tache, fragilité…)" class="input mt-3"></textarea>

        <button type="submit" :disabled="lines.length === 0 || !client"
                class="btn-primary btn-lg mt-3 w-full gap-2">
            <x-icon name="check" class="w-5 h-5" />
            Enregistrer le dépôt
        </button>
        <p x-show="!client" x-cloak class="mt-2 flex items-center justify-center gap-1 text-xs text-amber-600">
            <x-icon name="triangle-alert" class="w-3.5 h-3.5" />
            Sélectionnez d'abord un client.
        </p>
    </form> -->
    <form method="POST" action="{{ route('orders.store') }}" class="card h-fit p-4"
        @submit="markSubmitted()">
        @csrf

        <h2 class="flex items-center gap-2 font-semibold text-slate-900">
            <x-icon name="shopping-basket" class="w-4 h-4 text-sky-700" />
            Ticket en cours
        </h2>
        <p class="mb-3 text-xs text-slate-400">Décocher/Supprimer · Ajuster quantité · Calcul instantané</p>

        {{-- ID du client retenu --}}
        <input type="hidden" name="client_id" :value="client?.id ?? ''">

        {{-- Lignes du panier --}}
        <ul class="divide-y divide-slate-100">
            <template x-for="(line, i) in lines" :key="i">
                <li class="py-2">
                    <div class="flex items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" x-text="line.name"></p>
                            <p class="text-xs text-slate-400" x-text="formatMoney(line.price) + ' / un'"></p>
                        </div>

                        {{-- Boutons d'ajustement rapide (+/-) --}}
                        <div class="flex items-center gap-1">
                            <button type="button" @click="decrementQuantity(i)"
                                class="flex h-6 w-6 items-center justify-center rounded border border-slate-200 text-slate-500 hover:bg-slate-100">
                                -
                            </button>
                            <input type="number" min="1" max="99" x-model.number="line.quantity"
                                class="input input-sm !w-12 text-center !p-1" aria-label="Quantité">
                            <button type="button" @click="line.quantity++"
                                class="flex h-6 w-6 items-center justify-center rounded border border-slate-200 text-slate-500 hover:bg-slate-100">
                                +
                            </button>
                        </div>

                        <span class="w-20 text-right text-sm font-semibold text-slate-800" x-text="formatMoney(line.price * line.quantity)"></span>

                        {{-- Bouton de déconnexion / suppression explicite --}}
                        <button type="button" @click="removeItem(i)" title="Retirer cette prestation"
                            class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                            <x-icon name="trash-2" class="w-4 h-4" />
                        </button>

                        {{-- CORRECTION DE L'ERREUR : Les champs cachés DOIVENT être DANS le <li> --}}
                        <input type="hidden" :name="`items[${i}][service_id]`" :value="line.service_id">
                        <input type="hidden" :name="`items[${i}][quantity]`" :value="line.quantity">
                        <input type="hidden" :name="`items[${i}][description]`" :value="line.description ?? ''">
                    </div>

                    <input type="text" x-model="line.description" placeholder="Précision (couleur, tache…)"
                        class="mt-1 w-full border-0 bg-transparent p-0 text-xs text-slate-400 placeholder:text-slate-300 focus:ring-0">
                </li>
            </template>

            <li x-show="lines.length === 0" x-cloak class="py-8 text-center text-sm text-slate-400">
                Aucune prestation sélectionnée.<br>Cliquez sur le catalogue pour en ajouter.
            </li>
        </ul>

        {{-- Options --}}
        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
            <label class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 font-medium">
                    <x-icon name="zap" class="w-4 h-4 text-amber-500" />
                    Service express (+<span x-text="expressRate"></span> %)
                </span>
                <input type="checkbox" x-model="express" class="checkbox">
            </label>
            <input type="hidden" name="is_express" :value="express ? 1 : 0">

            <label class="flex items-center justify-between text-sm">
                <span class="font-medium">Remise accordée</span>
                <input type="number" step="0.01" min="0" x-model.number="discount" name="discount_amount"
                    class="input input-sm !w-24 text-right" placeholder="0">
            </label>

            <label class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 font-medium">
                    <x-icon name="clock" class="w-4 h-4 text-slate-400" />
                    Retour promis
                </span>
                <input type="datetime-local" name="promised_at" class="input input-sm !w-44">
            </label>
        </div>

        {{-- Synthèse financière en temps réel --}}
        <div class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
            <div class="flex justify-between text-slate-500">
                <span>Sous-total (<span x-text="totalItemsCount"></span> articles)</span>
                <span x-text="formatMoney(subtotal)"></span>
            </div>
            <div x-show="express" class="flex justify-between text-amber-600">
                <span>Surcharge Express</span>
                <span>+ <span x-text="formatMoney(expressSurcharge)"></span></span>
            </div>
            <div x-show="discount > 0" class="flex justify-between text-emerald-600">
                <span>Remise appliquée</span>
                <span>- <span x-text="formatMoney(discount)"></span></span>
            </div>
            <div class="flex justify-between text-base font-bold text-slate-900 border-t border-slate-100 pt-1 mt-1">
                <span>Total Net à payer</span>
                <span x-text="formatMoney(net) + ' ' + currency"></span>
            </div>
        </div>

        {{-- Encaissement / Acompte & Calcul du rendu --}}
        <div class="mt-3 border-t border-slate-100 pt-3">
            <label class="label">Paiement / Acompte du client</label>
            <div class="mt-1 flex gap-2">
                <input type="number" step="0.01" min="0" x-model.number="deposit" name="deposit_amount"
                    class="input input-sm !w-28 text-right" placeholder="0">
                <select name="deposit_method" class="input flex-1 py-1.5">
                    <option value="cash">Espèces</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="card">Carte</option>
                </select>
            </div>

            {{-- Saisie du montant reçu en espèces (Calculateur de monnaie à rendre) --}}
            <div class="mt-2" x-show="deposit > 0">
                <label class="text-xs text-slate-500">Montant reçu de la main du client :</label>
                <input type="number" step="0.01" min="0" x-model.number="cashGiven"
                    class="input input-sm w-full mt-0.5" placeholder="Saisir le montant perçu">
            </div>

            {{-- Messages explicatifs dynamique sur le solde et la monnaie --}}
            <div class="mt-2 space-y-1 text-xs">
                <template x-if="cashGiven > 0 && cashGiven >= deposit">
                    <p class="font-bold text-emerald-600">
                        Monnaie à rendre au client : <span x-text="formatMoney(cashGiven - deposit)"></span> <span x-text="currency"></span>
                    </p>
                </template>
                <p class="text-slate-500">
                    Reste à payer au retrait :
                    <strong :class="net - deposit > 0 ? 'text-amber-600' : 'text-emerald-600'"
                        x-text="formatMoney(Math.max(0, net - deposit))"></strong>
                    <span x-text="currency"></span>
                </p>
            </div>
        </div>

        {{-- Notes + validation --}}
        <textarea name="notes" rows="2" x-model="notes" placeholder="Note interne (état des vêtements, taches…)" class="input mt-3"></textarea>

        <button type="submit" :disabled="lines.length === 0 || !client"
            class="btn-primary btn-lg mt-3 w-full gap-2">
            <x-icon name="check" class="w-5 h-5" />
            Enregistrer le dépôt
        </button>
        <p x-show="!client" x-cloak class="mt-2 flex items-center justify-center gap-1 text-xs text-amber-600">
            <x-icon name="triangle-alert" class="w-3.5 h-3.5" />
            Sélectionnez d'abord un client pour valider.
        </p>
    </form>

    {{-- ================================================================
         SPÉCIFICATIONS A.1 — MODALE DE CRÉATION À LA VOLÉE
         POST JSON /clients/quick-store (CSRF) → le nouveau tiers est
         créé en statut ACTEUR et immédiatement sélectionné. --}}
    <div x-show="showQuick" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        x-transition.opacity @keydown.escape.window="showQuick = false">
        <div class="absolute inset-0 bg-sky-950/50" @click="showQuick = false"></div>
        <div x-show="showQuick"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="card relative w-full max-w-md p-6">
            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                <x-icon name="user-plus" class="w-5 h-5 text-sky-700" />
                Créer un tiers
            </h2>
            <p class="mt-1 text-xs text-slate-400">
                Enregistré comme <strong>Acteur</strong> — il deviendra <strong>Client</strong>
                automatiquement à la validation de son premier dépôt.
            </p>

            <form @submit.prevent="createClient()" class="mt-4 space-y-3">
                <div>
                    <label class="label" for="quick-name">Nom complet *</label>
                    <input id="quick-name" x-ref="quickName" x-model="quick.name" required class="input" placeholder="Ex : Awa Diop">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="quick-phone">Téléphone</label>
                        <input id="quick-phone" x-model="quick.phone" class="input" placeholder="+221 77 000 00 00">
                    </div>
                    <div>
                        <label class="label" for="quick-email">E-mail</label>
                        <input id="quick-email" type="email" x-model="quick.email" class="input">
                    </div>
                </div>
                <p x-show="quickError" x-text="quickError" x-cloak class="text-xs font-medium text-rose-600"></p>
                <div class="flex gap-2 pt-1">
                    <button type="submit" :disabled="quickBusy" class="btn-primary flex-1 gap-2">
                        <x-icon name="check" class="w-4 h-4" />
                        Créer et sélectionner
                    </button>
                    <button type="button" @click="showQuick = false" class="btn-ghost">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ==================================================================
     COMPOSANT ALPINE "pos" : tout l'état de l'écran de dépôt
     ================================================================== --}}
<!-- <script>
    function pos({
        services,
        currency,
        expressRate,
        selectedClient = null
    }) {
        return {
            currency,
            expressRate,
            filter: '',
            lines: [],
            express: false,
            discount: 0,
            deposit: 0,
            notes: '',
            // SPÉCIFICATIONS A — client pré-sélectionné via ?client=ID (fiche client)
            client: selectedClient,
            clientQuery: selectedClient?.name ?? '',
            clientResults: [],

            // SPÉCIFICATIONS B — libellés/badges Acteur / Client
            typeLabel(t) {
                return t === 'acteur' ? 'Acteur' : 'Client';
            },
            typeBadge(t) {
                return t === 'acteur' ? 'bg-amber-100 text-amber-800' : 'bg-teal-100 text-teal-800';
            },

            // Catalogue groupé par catégorie, filtré par la recherche
            get filteredCatalog() {
                const q = this.filter.trim().toLowerCase();
                const grouped = {};
                for (const s of services) {
                    if (q && !s.name.toLowerCase().includes(q)) continue;
                    (grouped[s.category_name] ??= {
                        id: s.category_id,
                        name: s.category_name,
                        services: []
                    })
                    .services.push(s);
                }
                return Object.values(grouped);
            },

            // Sous-total brut
            get subtotal() {
                return this.lines.reduce((sum, l) => sum + l.price * l.quantity, 0);
            },

            // Net après remise
            get net() {
                return Math.max(0, this.subtotal - (this.discount || 0));
            },

            addItem(s) {
                // Ligne existante ? on incrémente (comportement caisse)
                const existing = this.lines.find(l => l.service_id === s.id && !l.description);
                if (existing) {
                    existing.quantity++;
                    return;
                }
                this.lines.push({
                    service_id: s.id,
                    name: s.name,
                    price: s.price,
                    quantity: 1,
                    description: ''
                });
            },

            removeItem(i) {
                this.lines.splice(i, 1);
            },

            // Autocomplétion client : appelle l'endpoint interne /clients/search
            async searchClient() {
                if (this.clientQuery.trim().length < 2) {
                    this.clientResults = [];
                    return;
                }
                const res = await fetch(`{{ url('clients/search') }}?q=${encodeURIComponent(this.clientQuery)}`);
                const json = await res.json();
                this.clientResults = json.data;
            },

            pickClient(c) {
                this.client = c;
                this.clientQuery = c.name;
                this.clientResults = [];
                this.persist();
            },

            /* ---------------------------------------------------------------
             * SPÉCIFICATIONS A.1 — CRÉATION À LA VOLÉE (POST JSON)
             * Le serveur crée le tiers en statut ACTEUR et le renvoie ; un
             * téléphone déjà connu renvoie le tiers EXISTANT (anti-doublon).
             * --------------------------------------------------------------- */
            showQuick: false,
            quick: {
                name: '',
                phone: '',
                email: ''
            },
            quickBusy: false,
            quickError: '',

            openQuick() {
                this.quickError = '';
                // Pré-remplit le nom avec ce qui est déjà tapé dans la recherche
                if (!this.quick.name && !this.client && this.clientQuery.trim().length > 1) {
                    this.quick.name = this.clientQuery.trim();
                }
                this.showQuick = true;
                this.$nextTick(() => this.$refs.quickName?.focus());
            },

            async createClient() {
                this.quickBusy = true;
                this.quickError = '';
                try {
                    const res = await fetch('{{ route('clients.quickStore') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(this.quick),
                        });
                    const json = await res.json();
                    if (!res.ok) {
                        this.quickError = json.message ?? 'Création impossible.';
                        return;
                    }
                    this.pickClient(json.data);
                    this.showQuick = false;
                    this.quick = {
                        name: '',
                        phone: '',
                        email: ''
                    };
                    window.showToast(json.existing ?
                        'Tiers existant sélectionné (téléphone déjà enregistré).' :
                        'Tiers créé et sélectionné. Statut : Acteur.', 'success');
                } catch (e) {
                    this.quickError = 'Erreur réseau — réessayez.';
                } finally {
                    this.quickBusy = false;
                }
            },

            /* ---------------------------------------------------------------
             * PERSISTANCE DU PANIER (sessionStorage)
             * Un rechargement accidentel (F5, fermeture d'onglet) ne perd plus
             * le ticket en cours : client, lignes, remise, express, acompte
             * sont restaurés à l'identique. Clé par onglet de navigateur.
             * --------------------------------------------------------------- */
            storageKey: 'pressing.pos.cart',
            submittedKey: 'pressing.pos.justSubmitted',

            /* Appelé par @submit du formulaire : mémorise l'instant de la
             * soumission pour distinguer (à la page suivante) une NAVIGATION
             * réussie (→ poubelle du panier) d'un simple refresh accidentel
             * ou d'un retour de validation (→ panier conservé). */
            markSubmitted() {
                try {
                    sessionStorage.setItem(this.submittedKey, String(Date.now()));
                } catch (e) {}
            },

            persist() {
                try {
                    sessionStorage.setItem(this.storageKey, JSON.stringify({
                        lines: this.lines,
                        client: this.client,
                        express: this.express,
                        discount: this.discount,
                        deposit: this.deposit,
                        notes: this.notes,
                    }));
                } catch (e) {
                    /* quota dépassé : non bloquant */ }
            },

            restore() {
                try {
                    const saved = sessionStorage.getItem(this.storageKey);
                    if (!saved) return;

                    // Une erreur de validation vient de nous renvoyer ici ?
                    const hasErrors = !!document.querySelector('[data-toast="error"]');
                    const submittedAt = parseInt(sessionStorage.getItem(this.submittedKey) || '0', 10);
                    const justSubmitted = submittedAt > 0 && (Date.now() - submittedAt) < 15000;

                    if (justSubmitted && !hasErrors) {
                        // Dépôt enregistré avec succès → le panier est consommé.
                        sessionStorage.removeItem(this.storageKey);
                        sessionStorage.removeItem(this.submittedKey);
                        return;
                    }
                    sessionStorage.removeItem(this.submittedKey);

                    const s = JSON.parse(saved);
                    if (Array.isArray(s.lines) && s.lines.length) {
                        this.lines = s.lines;
                        this.client = s.client ?? null;
                        this.clientQuery = this.client?.name ?? '';
                        this.express = !!s.express;
                        this.discount = s.discount ?? 0;
                        this.deposit = s.deposit ?? 0;
                        this.notes = s.notes ?? '';
                        window.showToast('Ticket en cours restauré.', 'success', 4000);
                    }
                } catch (e) {
                    /* JSON corrompu : on repart de zéro */ }
            },
        };
    }
</script> -->
<script>
    function pos({
        services,
        currency,
        expressRate,
        selectedClient = null
    }) {
        return {
            currency,
            expressRate,
            filter: '',
            lines: [],
            express: false,
            discount: 0,
            deposit: 0,
            cashGiven: 0, // Argent liquide remis en main par le client
            notes: '',

            // Client pré-sélectionné
            client: selectedClient,
            clientQuery: selectedClient?.name ?? '',
            clientResults: [],

            // Badges et libellés
            typeLabel(t) {
                return t === 'acteur' ? 'Acteur' : 'Client';
            },
            typeBadge(t) {
                return t === 'acteur' ? 'bg-amber-100 text-amber-800' : 'bg-teal-100 text-teal-800';
            },

            // Catalogue groupé par catégorie et filtré dynamiquement
            get filteredCatalog() {
                const q = this.filter.trim().toLowerCase();
                const grouped = {};
                for (const s of services) {
                    if (q && !s.name.toLowerCase().includes(q)) continue;
                    (grouped[s.category_name] ??= {
                        id: s.category_id,
                        name: s.category_name,
                        services: []
                    })
                    .services.push(s);
                }
                return Object.values(grouped);
            },

            // --- CALCULS ET TOTAUX EN TEMPS RÉEL ---
            get totalItemsCount() {
                return this.lines.reduce((sum, l) => sum + l.quantity, 0);
            },

            get rawSubtotal() {
                return this.lines.reduce((sum, l) => sum + l.price * l.quantity, 0);
            },

            get expressSurcharge() {
                return this.express ? Math.round(this.rawSubtotal * (this.expressRate / 100)) : 0;
            },

            get subtotal() {
                return this.rawSubtotal + this.expressSurcharge;
            },

            get net() {
                return Math.max(0, this.subtotal - (this.discount || 0));
            },

            // --- GESTION DU PANIER & TICKET ---
            addItem(s) {
                const existing = this.lines.find(l => l.service_id === s.id && !l.description);
                if (existing) {
                    existing.quantity++;
                } else {
                    this.lines.push({
                        service_id: s.id,
                        name: s.name,
                        price: s.price,
                        quantity: 1,
                        description: ''
                    });
                }
                this.persist();
            },

            decrementQuantity(index) {
                if (this.lines[index].quantity > 1) {
                    this.lines[index].quantity--;
                } else {
                    this.removeItem(index);
                }
                this.persist();
            },

            removeItem(i) {
                this.lines.splice(i, 1);
                this.persist();
            },

            formatMoney(amount) {
                return new Intl.NumberFormat('fr-FR').format(amount || 0);
            },

            // --- RECHERCHE ET SELECTION CLIENT ---
            async searchClient() {
                if (this.clientQuery.trim().length < 2) {
                    this.clientResults = [];
                    return;
                }
                const res = await fetch(`{{ url('clients/search') }}?q=${encodeURIComponent(this.clientQuery)}`);
                const json = await res.json();
                this.clientResults = json.data;
            },

            pickClient(c) {
                this.client = c;
                this.clientQuery = c.name;
                this.clientResults = [];
                this.persist();
            },

            // --- CREATION DE CLIENT A LA VOLEE (MODALE) ---
            showQuick: false,
            quick: {
                name: '',
                phone: '',
                email: ''
            },
            quickBusy: false,
            quickError: '',

            openQuick() {
                this.quickError = '';
                if (!this.quick.name && !this.client && this.clientQuery.trim().length > 1) {
                    this.quick.name = this.clientQuery.trim();
                }
                this.showQuick = true;
                this.$nextTick(() => this.$refs.quickName?.focus());
            },

            async createClient() {
                this.quickBusy = true;
                this.quickError = '';
                try {
                    const res = await fetch('{{ route('clients.quickStore') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(this.quick),
                        });
                    const json = await res.json();
                    if (!res.ok) {
                        this.quickError = json.message ?? 'Création impossible.';
                        return;
                    }
                    this.pickClient(json.data);
                    this.showQuick = false;
                    this.quick = {
                        name: '',
                        phone: '',
                        email: ''
                    };
                    window.showToast(json.existing ?
                        'Tiers existant sélectionné (téléphone déjà enregistré).' :
                        'Tiers créé et sélectionné. Statut : Acteur.', 'success');
                } catch (e) {
                    this.quickError = 'Erreur réseau — réessayez.';
                } finally {
                    this.quickBusy = false;
                }
            },

            // --- PERSISTENCE DE SESSION (sessionStorage) ---
            storageKey: 'pressing.pos.cart',
            submittedKey: 'pressing.pos.justSubmitted',

            markSubmitted() {
                try {
                    sessionStorage.setItem(this.submittedKey, String(Date.now()));
                } catch (e) {}
            },

            persist() {
                try {
                    sessionStorage.setItem(this.storageKey, JSON.stringify({
                        lines: this.lines,
                        client: this.client,
                        express: this.express,
                        discount: this.discount,
                        deposit: this.deposit,
                        cashGiven: this.cashGiven,
                        notes: this.notes,
                    }));
                } catch (e) {
                    /* quota dépassé */ }
            },

            restore() {
                try {
                    const saved = sessionStorage.getItem(this.storageKey);
                    if (!saved) return;

                    const hasErrors = !!document.querySelector('[data-toast="error"]');
                    const submittedAt = parseInt(sessionStorage.getItem(this.submittedKey) || '0', 10);
                    const justSubmitted = submittedAt > 0 && (Date.now() - submittedAt) < 15000;

                    if (justSubmitted && !hasErrors) {
                        sessionStorage.removeItem(this.storageKey);
                        sessionStorage.removeItem(this.submittedKey);
                        return;
                    }
                    sessionStorage.removeItem(this.submittedKey);

                    const s = JSON.parse(saved);
                    if (Array.isArray(s.lines) && s.lines.length) {
                        this.lines = s.lines;
                        this.client = s.client ?? null;
                        this.clientQuery = this.client?.name ?? '';
                        this.express = !!s.express;
                        this.discount = s.discount ?? 0;
                        this.deposit = s.deposit ?? 0;
                        this.cashGiven = s.cashGiven ?? 0;
                        this.notes = s.notes ?? '';
                        window.showToast('Ticket en cours restauré.', 'success', 4000);
                    }
                } catch (e) {
                    /* JSON corrompu */ }
            },
        };
    }
</script>
@endsection
