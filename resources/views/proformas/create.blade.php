@extends('layouts.app')

@section('title', 'Nouveau proforma')

@section('header_hint', 'Composez un devis : prestations du catalogue ou ligne libre, remise, validité.')

{{-- ÉTAPE 1 — SPÉCIFICATIONS C.1 : ÉDITEUR DE PROFORMA --}}
{{-- Même ergonomie que le POS : composant Alpine + recherche client live +
     création à la volée. Le serveur RECALCULE les prix du catalogue
     (jamais confiance au navigateur) — voir ProformaService::create(). --}}
@section('content')
<div x-data="proforma({
        services: {{ Illuminate\Support\Js::from($services) }},
        currency: '{{ config('pressing.currency', 'FCFA') }}',
        selectedClient: {{ Illuminate\Support\Js::from($selected ? [
            'id' => $selected->id, 'name' => $selected->name, 'phone' => $selected->phone,
            'address' => $selected->address, 'type' => $selected->type->value,
        ] : null) }}
     })"
     class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    {{-- ================= COLONNE GAUCHE : DESTINATAIRE + CATALOGUE ================= --}}
    <div class="space-y-4 xl:col-span-2">

        {{-- Recherche client (même endpoint JSON que la caisse) --}}
        <div class="card p-4">
            <label class="label">Destinataire (Acteur ou Client)</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                    <x-icon name="search" class="w-4 h-4" />
                </span>
                <input type="search" x-model="clientQuery" @input.debounce.300ms="searchClient()"
                       placeholder="Nom, téléphone ou code…" class="input pl-9" autocomplete="off">
                <ul x-show="clientResults.length" x-cloak @click.outside="clientResults = []"
                    class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                    <template x-for="c in clientResults" :key="c.id">
                        <li>
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

            <div x-show="client" x-cloak class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-sky-50 px-3 py-2">
                <span class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-sky-900">
                    <x-icon name="user" class="w-4 h-4 shrink-0" />
                    <span x-text="client?.name"></span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                          :class="typeBadge(client?.type)" x-text="typeLabel(client?.type)"></span>
                    <a x-show="client?.phone" :href="'tel:' + (client?.phone || '')"
                       class="text-xs font-normal text-sky-700 hover:underline" x-text="client?.phone"></a>
                </span>
                <button type="button" @click="client = null; clientQuery = ''" class="text-xs text-slate-400 hover:text-rose-600">changer</button>
            </div>
        </div>

        {{-- Catalogue --}}
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-semibold text-slate-900">Prestations</h2>
                <div class="relative max-w-xs flex-1">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <x-icon name="search" class="w-4 h-4" />
                    </span>
                    <input type="search" x-model="filter" placeholder="Filtrer une prestation… — raccourci : /" class="input pl-9">
                </div>
            </div>

            <div class="max-h-[420px] space-y-4 overflow-y-auto scroll-thin pr-1">
                <template x-for="cat in filteredCatalog" :key="cat.id">
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400" x-text="cat.name"></p>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
                            <template x-for="s in cat.services" :key="s.id">
                                <button type="button" @click="addItem(s)"
                                        class="rounded-lg border border-slate-200 bg-white p-3 text-left transition hover:border-sky-400 hover:bg-sky-50 active:scale-[.98]">
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

            {{-- Ligne libre : prestation hors catalogue (ex: customisation spéciale) --}}
            <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                <div class="min-w-[180px] flex-1">
                    <label class="label" for="free-label">Ligne libre (hors catalogue)</label>
                    <input id="free-label" x-model="free.label" class="input input-sm" placeholder="Ex : Customisation sac cuir">
                </div>
                <div class="w-28">
                    <label class="label" for="free-price">P.U.</label>
                    <input id="free-price" type="number" min="0" step="0.01" x-model.number="free.price" class="input input-sm text-right">
                </div>
                <div class="w-20">
                    <label class="label" for="free-qty">Qté</label>
                    <input id="free-qty" type="number" min="1" max="999" x-model.number="free.quantity" class="input input-sm text-center">
                </div>
                <button type="button" @click="addFreeLine()" class="btn-ghost gap-2">
                    <x-icon name="plus" class="w-4 h-4" />
                    Ajouter
                </button>
            </div>
        </div>
    </div>

    {{-- ================= COLONNE DROITE : DEVIS ================= --}}
    <form method="POST" action="{{ route('proformas.store') }}" class="card h-fit p-4">
        @csrf

        <h2 class="flex items-center gap-2 font-semibold text-slate-900">
            <x-icon name="file-text" class="w-4 h-4 text-sky-700" />
            Devis en cours
        </h2>
        <p class="mb-3 text-xs text-slate-400">Remise · validité · notes</p>

        <input type="hidden" name="client_id" :value="client?.id ?? ''">

        {{-- Lignes du devis --}}
        <ul class="divide-y divide-slate-100">
            <template x-for="(line, i) in lines" :key="i">
                <li class="py-2">
                    <div class="flex items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" x-text="line.label"></p>
                            <p class="text-xs text-slate-400" x-text="line.service_id ? 'Catalogue' : 'Ligne libre'"></p>
                        </div>
                        <input type="number" min="1" max="999" x-model.number="line.quantity"
                               class="input input-sm !w-14 text-center" aria-label="Quantité">
                        <span class="w-16 text-right text-sm font-semibold" x-text="formatMoney(line.unit_price * line.quantity)"></span>
                        <button type="button" @click="lines.splice(i, 1)" class="text-slate-300 hover:text-rose-600">
                            <x-icon name="x" class="w-4 h-4" />
                        </button>
                    </div>
                </li>
                <input type="hidden" :name="`items[${i}][service_id]`" :value="line.service_id ?? ''">
                <input type="hidden" :name="`items[${i}][label]`" :value="line.service_id ? '' : (line.label || '')">
                <input type="hidden" :name="`items[${i}][quantity]`" :value="line.quantity">
                <input type="hidden" :name="`items[${i}][unit_price]`" :value="line.service_id ? '' : line.unit_price">
                <input type="hidden" :name="`items[${i}][pricing_unit]`" :value="line.service_id ? '' : (line.pricing_unit || 'piece')">
            </template>
            <li x-show="lines.length === 0" x-cloak class="py-8 text-center text-sm text-slate-400">
                Cliquez sur une prestation pour l'ajouter.
            </li>
        </ul>

        {{-- Remise + validité --}}
        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
            <label class="flex items-center justify-between text-sm">
                <span class="font-medium">Remise</span>
                <input type="number" step="0.01" min="0" x-model.number="discount" name="discount_amount"
                       class="input input-sm !w-24 text-right">
            </label>
            <label class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 font-medium">
                    <x-icon name="calendar-clock" class="w-4 h-4 text-slate-400" />
                    Valable (jours)
                </span>
                <input type="number" min="1" max="180" value="15" name="valid_days" class="input input-sm !w-20 text-right">
            </label>
        </div>

        {{-- Totaux --}}
        <div class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
            <div class="flex justify-between text-slate-500"><span>Sous-total</span>
                <span x-text="formatMoney(subtotal)"></span></div>
            <div class="flex justify-between text-slate-500"><span>Remise</span>
                <span>- <span x-text="formatMoney(discount)"></span></span></div>
            <div class="flex justify-between text-base font-bold text-slate-900">
                <span>Net estimé</span>
                <span x-text="formatMoney(net) + ' ' + currency"></span>
            </div>
        </div>

        <textarea name="notes" rows="2" x-model="notes" placeholder="Conditions, précisions…" class="input mt-3"></textarea>

        <button type="submit" :disabled="lines.length === 0 || !client"
                class="btn-primary btn-lg mt-3 w-full gap-2">
            <x-icon name="check" class="w-5 h-5" />
            Enregistrer le proforma
        </button>
        <p x-show="!client" x-cloak class="mt-2 flex items-center justify-center gap-1 text-xs text-amber-600">
            <x-icon name="triangle-alert" class="w-3.5 h-3.5" />
            Sélectionnez d'abord un destinataire.
        </p>
    </form>
</div>

{{-- ==================================================================
     COMPOSANT ALPINE "proforma" — même structure que le POS (pos()).
     ================================================================== --}}
<script>
function proforma({ services, currency, selectedClient = null }) {
    return {
        currency,
        filter: '',
        lines: [],
        discount: 0,
        notes: '',
        client: selectedClient,
        clientQuery: selectedClient?.name ?? '',
        clientResults: [],

        // Ligne libre en cours de saisie
        free: { label: '', price: 0, quantity: 1 },

        typeLabel(t) { return t === 'acteur' ? 'Acteur' : 'Client'; },
        typeBadge(t) {
            return t === 'acteur' ? 'bg-amber-100 text-amber-800' : 'bg-teal-100 text-teal-800';
        },

        get filteredCatalog() {
            const q = this.filter.trim().toLowerCase();
            const grouped = {};
            for (const s of services) {
                if (q && !s.name.toLowerCase().includes(q)) continue;
                (grouped[s.category_name] ??= { id: s.category_id, name: s.category_name, services: [] })
                    .services.push(s);
            }
            return Object.values(grouped);
        },

        get subtotal() {
            return this.lines.reduce((sum, l) => sum + l.unit_price * l.quantity, 0);
        },

        get net() {
            return Math.max(0, this.subtotal - (this.discount || 0));
        },

        addItem(s) {
            const existing = this.lines.find(l => l.service_id === s.id);
            if (existing) { existing.quantity++; return; }
            this.lines.push({ service_id: s.id, label: s.name, unit_price: s.price, pricing_unit: s.pricing_unit, quantity: 1 });
        },

        addFreeLine() {
            const label = (this.free.label || '').trim();
            if (!label || !(this.free.price > 0)) return;
            this.lines.push({
                service_id: null, label,
                unit_price: this.free.price,
                pricing_unit: 'piece',
                quantity: Math.max(1, this.free.quantity || 1),
            });
            this.free = { label: '', price: 0, quantity: 1 };
        },

        async searchClient() {
            if (this.clientQuery.trim().length < 2) { this.clientResults = []; return; }
            const res = await fetch(`{{ url('clients/search') }}?q=${encodeURIComponent(this.clientQuery)}`);
            const json = await res.json();
            this.clientResults = json.data;
        },

        pickClient(c) {
            this.client = c;
            this.clientQuery = c.name;
            this.clientResults = [];
        },
    };
}
</script>
@endsection
