@extends('layouts.app')

@section('title', 'Atelier')

@section('header_hint', 'Scannez les articles à chaque étape : lavage, repassage, prêt.')

{{-- ÉTAPE 1 — ÉCRAN ATELIER : kanban + zone de scan --}}
{{-- Le composant Alpine `workshop` envoie chaque scan en JSON puis met à
     jour le kanban SANS rechargement : la carte est déplacée de colonne
     en colonne, les compteurs recalculés, le champ reste focusé. --}}
@section('content')

<div x-data="workshop()" class="space-y-6">

    {{-- Zone de scan --}}
    <div class="card p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label class="label" for="scan-field">Scanner un article</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <x-icon name="scan-line" class="w-4 h-4" />
                    </span>
                    <input id="scan-field" type="text" x-ref="scan" x-model="barcode" @keydown.enter.prevent="submitScan()"
                           placeholder="Code-barres (douchette)… — raccourci : /" class="input pl-9 font-mono" autocomplete="off">
                </div>
            </div>
            <div>
                <label class="label" for="scan-status">Nouveau statut</label>
                <select id="scan-status" x-model="newStatus" class="input !w-48">
                    <option value="en_cours">En cours de lavage</option>
                    <option value="repasse">Repassé</option>
                    <option value="pret">Prêt</option>
                    <option value="perdu">Déclarer perdu</option>
                </select>
            </div>
            <div>
                <label class="label" for="scan-location">Ranger à</label>
                <select id="scan-location" x-model="location" class="input !w-44">
                    <option value="">—</option>
                    @foreach (config('pressing.locations') as $loc)
                        <option value="{{ $loc }}">{{ $loc }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" @click="submitScan()" :disabled="busy" class="btn-primary gap-2">
                <x-icon name="check" class="w-4 h-4" />
                Appliquer
            </button>
        </div>

        {{-- Feedback du scan (vert/rouge) --}}
        <p x-show="feedback" x-text="feedback" x-cloak
           :class="feedbackOk ? 'text-emerald-700' : 'text-rose-700'"
           class="mt-3 flex items-center gap-2 text-sm font-semibold"></p>
    </div>

    {{-- Kanban — TOUTES les cartes (pas de limite : un article jamais visible
         est un article perdu). data-col : cible du déplacement live. --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-4">
        @foreach ($byStatus as $key => $col)
            <div class="card flex flex-col" data-col="{{ $key }}">
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="font-semibold text-slate-900">{{ $col['label'] }}</h2>
                    <span class="badge {{ $col['badge'] }}" data-count="{{ $key }}">{{ $col['total'] }}</span>
                </div>
                <ul class="max-h-[520px] min-h-24 space-y-2 overflow-y-auto scroll-thin p-3" data-list="{{ $key }}">
                    @foreach ($col['items'] as $item)
                        <li data-card="{{ $item->barcode }}"
                            data-ticket="{{ $item->order->ticket_no }}"
                            data-client="{{ $item->order->client->name }}"
                            data-service="{{ $item->service->name }}"
                            data-location="{{ $item->location ?? '' }}"
                            data-status="{{ $key }}"
                            draggable="true"
                            class="cursor-grab rounded-lg bg-sky-50/60 p-2.5 text-sm ring-1 ring-sky-100 transition hover:bg-sky-100/80 active:cursor-grabbing">
                            <p class="font-mono text-xs text-slate-500">{{ $item->barcode }}</p>
                            <p class="font-medium">{{ $item->service->name }}</p>
                            <p class="flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                {{ $item->order->ticket_no }} — {{ $item->order->client->name }}
                                @if ($item->location)
                                    <span class="loc-line ml-1 inline-flex items-center gap-0.5">
                                        <x-icon name="map-pin" class="inline w-3 h-3" />
                                        {{ $item->location }}
                                    </span>
                                @endif
                            </p>
                        </li>
                    @endforeach
                    @if ($col['items']->isEmpty())
                        <li class="py-6 text-center text-xs text-slate-400" data-empty>Vide</li>
                    @endif
                </ul>
            </div>
        @endforeach
    </div>
</div>

{{-- ==================================================================
     COMPOSANT ALPINE "workshop" : scan → POST JSON → kanban live
     ================================================================== --}}
<script>
function workshop() {
    return {
        barcode: '',
        newStatus: 'en_cours',
        location: '',
        feedback: '',
        feedbackOk: false,
        busy: false,

        async submitScan() {
            if (!this.barcode.trim() || this.busy) return;
            this.busy = true;

            try {
                const res = await fetch('{{ url('atelier/scan') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        barcode: this.barcode.trim(),
                        new_status: this.newStatus,
                        location: this.location || null,
                    }),
                });

                const json = await res.json();

                if (!res.ok || !json.ok) {
                    this.feedbackOk = false;
                    this.feedback = json.message ?? 'Erreur : scan non appliqué.';
                    return;
                }

                // --- Kanban LIVE : déplace la carte sans recharger ---------
                this.moveCard(json.item.barcode, json.item.status, json.item.location);
                this.feedbackOk = true;
                this.feedback = json.message;
                this.barcode = '';              // prêt pour le scan suivant
                this.$refs.scan.focus();
            } catch (e) {
                this.feedbackOk = false;
                this.feedback = 'Erreur réseau : ' + e.message;
            } finally {
                this.busy = false;
            }
        },

        /* Déplace la carte d'une colonne à l'autre et recalcule les badges.
             Les colonnes cibles existent toujours : le serveur ne renvoie
             que des statuts affichés sur le plateau (jamais "livre"). */
        moveCard(barcode, newStatus, location) {
            const card = document.querySelector(`[data-card="${CSS.escape(barcode)}"]`);
            if (!card) return; // article non affiché (ex: colonne vide rechargeable)

            const targetList = document.querySelector(`[data-list="${newStatus}"]`);
            if (!targetList) return;

            // 1) Retire l'ancien "Vide" si présent
            targetList.querySelector('[data-empty]')?.remove();

            // 2) Met à jour la carte (emplacement) puis la déplace
            card.dataset.status = newStatus;
            card.dataset.location = location || '';
            let locLine = card.querySelector('.loc-line');
            if (location) {
                if (!locLine) {
                    locLine = document.createElement('span');
                    locLine.className = 'loc-line ml-1 inline-flex items-center gap-0.5';
                    card.querySelector('p:last-child').appendChild(locLine);
                }
                locLine.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline w-3 h-3"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> ' + location;
            } else if (locLine) {
                locLine.remove();
            }
            targetList.prepend(card);

            // 3) Recalcule les compteurs de toutes les colonnes
            document.querySelectorAll('[data-col]').forEach((col) => {
                const key = col.dataset.col;
                const n = col.querySelectorAll('[data-card]').length;
                const badge = col.querySelector(`[data-count="${key}"]`);
                if (badge) badge.textContent = n;

                // Ligne "Vide" restaurée si la colonne se vide
                const list = col.querySelector('[data-list]');
                if (n === 0 && !list.querySelector('[data-empty]')) {
                    const empty = document.createElement('li');
                    empty.setAttribute('data-empty', '');
                    empty.className = 'py-6 text-center text-xs text-slate-400';
                    empty.textContent = 'Vide';
                    list.appendChild(empty);
                }
            });

            // 4) Petite animation de repérage sur la carte déplacée
            card.classList.add('flash-move');
            setTimeout(() => card.classList.remove('flash-move'), 1200);
        },
    };
}

/* ==================================================================
   DRAG & DROP KANBAN — déplacer une carte = changement de statut
   ------------------------------------------------------------------
   La drop est TOUJOURS validée par le serveur (machine à états
   OrderStatus::allowedTransitions) : en cas de refus, la carte est
   rendue à sa colonne d'origine et l'erreur est affichée.
   ================================================================== */
let dragState = null; // { barcode, fromStatus, card }

document.addEventListener('dragstart', (e) => {
    const card = e.target.closest('[data-card]');
    if (!card) return;
    dragState = {
        barcode: card.dataset.card,
        fromStatus: card.dataset.status,
        card,
    };
    card.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.card); // requis par Firefox
});

document.addEventListener('dragend', () => {
    dragState?.card?.classList.remove('is-dragging');
    document.querySelectorAll('.drop-target').forEach((el) => el.classList.remove('drop-target'));
    dragState = null;
});

document.addEventListener('dragover', (e) => {
    const list = e.target.closest('[data-list]');
    if (!list || !dragState) return;
    e.preventDefault();                    // autorise le drop
    e.dataTransfer.dropEffect = 'move';
    list.classList.add('drop-target');
});

document.addEventListener('dragleave', (e) => {
    const list = e.target.closest('[data-list]');
    if (list) list.classList.remove('drop-target');
});

document.addEventListener('drop', async (e) => {
    const list = e.target.closest('[data-list]');
    if (!list || !dragState) return;
    e.preventDefault();

    const targetStatus = list.dataset.list;
    const { barcode, fromStatus, card } = dragState;

    // Nettoyage visuel immédiat
    list.classList.remove('drop-target');
    card.classList.remove('is-dragging');
    dragState = null;

    if (targetStatus === fromStatus) return; // repositionnement sans changement

    // Déplacement OPTIMISTE (l'UX atelier prime), confirmé par le serveur.
    moveCard(barcode, targetStatus, null);

    try {
        const res = await fetch('{{ url('atelier/scan') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ barcode, new_status: targetStatus }),
        });
        const json = await res.json();

        if (!res.ok || !json.ok) {
            // REFUS (machine à états) : retour à la colonne d'origine
            moveCard(barcode, fromStatus, null);
            setFeedback(json.message ?? 'Transition non autorisée.', false);
            return;
        }

        setFeedback(json.message, true);
    } catch (err) {
        moveCard(barcode, fromStatus, null);
        setFeedback('Erreur réseau : ' + err.message, false);
    }
});

// Petit utilitaire : affiche le retour de scan/drop dans la zone feedback
function setFeedback(message, ok) {
    const el = document.querySelector('[x-show="feedback"]');
    if (!el) return;
    el.textContent = message;
    el.classList.toggle('text-emerald-700', ok);
    el.classList.toggle('text-rose-700', !ok);
    el.style.display = 'flex';
}

// Clic sur une carte = pré-remplit le champ scan (gain de temps atelier)
document.addEventListener('click', (e) => {
    const card = e.target.closest('[data-card]');
    if (!card) return;
    const input = document.querySelector('[x-ref="scan"]');
    if (!input) return;
    input.value = card.dataset.card;
    input.dispatchEvent(new Event('input')); // synchronise Alpine (x-model)
    input.focus();
});

// Focus automatique du champ scan à l'ouverture
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => document.querySelector('#scan-field')?.focus(), 50);
});
</script>
@endsection
