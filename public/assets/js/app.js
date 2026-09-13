/**
 * ÉTAPE 1 — JAVASCRIPT APPLICATIF (sans Node.js, sans bundler)
 * -----------------------------------------------------------------------------
 * Servi directement depuis public/assets/js/app.js.
 * Ordre de chargement (defer) garanti par les layouts :
 *   1. alpine.min.js   → expose window.Alpine
 *   2. lucide.min.js   → expose window.lucide
 *   3. datatable.js    → tables interactives
 *   4. app.js (ce fichier) → démarre tout
 *
 * Responsabilités :
 *   - Icônes Lucide
 *   - Toasts (remplace les flashs en haut de page, toujours visibles)
 *   - Garde anti double-soumission des formulaires POST
 *   - Keep-alive de session (anti-déconnexion pendant la saisie)
 *   - Impression, formatage monétaire, raccourcis clavier POS
 */

window.lucide?.createIcons();

/* =============================================================================
   TOASTS — feedback flottant en bas à droite, empilable, auto-fermé
   ========================================================================== */

/**
 * Affiche un toast. window.showToast(msg, 'success'|'error', durationMs)
 * Exposé globalement : les vues Alpine peuvent l'appeler après un fetch.
 */
window.showToast = function (message, type = 'success', duration = 5000) {
    const container = document.getElementById('toasts');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.setAttribute('data-lucide', type === 'error' ? 'triangle-alert' : 'circle-check');
    toast.appendChild(icon);

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.innerHTML = message; // le flash Blade peut contenir du HTML autorisé
    toast.appendChild(body);

    const close = document.createElement('button');
    close.className = 'toast-close';
    close.setAttribute('aria-label', 'Fermer la notification');
    close.innerHTML = '<i data-lucide="x"></i>';
    close.addEventListener('click', () => toast.remove());
    toast.appendChild(close);

    container.appendChild(toast);
    window.lucide?.createIcons();

    // Entrée animée + sortie automatique
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    if (duration > 0) setTimeout(() => {
        toast.classList.remove('is-visible');
        setTimeout(() => toast.remove(), 250);
    }, duration);

    return toast;
};

// Conversion des flashs Blade (blocs data-toast masqués) en toasts.
document.querySelectorAll('[data-toast]').forEach((el) => {
    window.showToast(el.innerHTML, el.getAttribute('data-toast'), 6000);
});

/* =============================================================================
   ANTI DOUBLE-SOUMISSION — un seul clic compte, bouton visuellement occupé
   ========================================================================== */
document.addEventListener('submit', (e) => {
    const form = e.target;

    // Confirmation destructrice (attribut data-confirm)
    const msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
        e.preventDefault();
        return;
    }

    // Garde : ignore les soumissions rapprochées (double-clic, Enter répété)
    if (form.dataset.submitting === '1') {
        e.preventDefault();
        return;
    }
    form.dataset.submitting = '1';

    // Passe le bouton (et son libellé) en état "occupé" — pas de rechargement
    // en arrière-plan : en cas d'erreur serveur, la page se recharge avec
    // les erreurs et l'état du formulaire est restauré.
    const button = form.querySelector('[type="submit"]');
    if (button) {
        button.dataset.originalWidth = button.offsetWidth + 'px';
        button.style.minWidth = button.dataset.originalWidth; // évite le "saut" visuel
        button.disabled = true;
        button.classList.add('is-busy');
        // La classe .is-busy (CSS) masque l'icône et affiche le spinner ::after
    }

    // Filet de sécurité : réautorise le formulaire après 15 s (échec réseau)
    setTimeout(() => {
        delete form.dataset.submitting;
        if (button) {
            button.disabled = false;
            button.classList.remove('is-busy');
            button.style.minWidth = '';
        }
    }, 15000);
}, true);

/* =============================================================================
   KEEP-ALIVE DE SESSION — évite la déconnexion silencieuse en pleine saisie
   ========================================================================== */
(function () {
    const meta = document.querySelector('meta[name="keep-alive-url"]');
    if (!meta) return; // page sans session (login)

    // Ping toutes les 5 minutes : léger, maintient la session Laravel active.
    setInterval(() => {
        fetch(meta.content, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).catch(() => {}); // silencieux : une erreur réseau n'est pas bloquante
    }, 5 * 60 * 1000);
})();

/* =============================================================================
   RACCOURCIS CLAVIER (POS caisse + atelier)
   ========================================================================== */
document.addEventListener('keydown', (e) => {
    // Ne pas intercepter quand l'utilisateur est DANS un champ de saisie
    const inField = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '');

    // "/" : focus sur la recherche principale (POS, DataTable, atelier)
    if (e.key === '/' && !inField && !e.ctrlKey && !e.metaKey) {
        const target =
            document.querySelector('[x-ref="posFilter"]') ||
            document.querySelector('[x-ref="scan"]') ||
            document.querySelector('.data-table-search input');
        if (target) {
            e.preventDefault();
            target.focus();
        }
    }
});

/* =============================================================================
   DIVERS — impression & formatage (exposés pour les vues Blade/Alpine)
   ========================================================================== */

// Déclenche l'impression du navigateur (tickets 80 mm, étiquettes)
window.printNow = function () {
    window.print();
};

// Formatage rapide d'un montant (utilisé par le POS caisse et l'atelier)
window.formatMoney = function (value) {
    return Number(value || 0).toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
};
