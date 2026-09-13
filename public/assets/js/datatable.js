/**
 * ÉTAPE 1 — DATATABLE (sans dépendance : pas de Node, pas de CDN)
 * -----------------------------------------------------------------------------
 * Transforme un <table class="data-table"> classique en tableau interactif :
 *   - recherche plein texte,
 *   - tri par colonne (clic sur <th data-sort>),
 *   - filtres déclarés (<th data-filter-label="Statut"> → menu déroulant),
 *   - pagination (taille de page + boutons numérotés),
 *   - compteur de résultats.
 *
 * Balisage attendu dans la vue Blade :
 *   <div data-datatable data-row-link>
 *     <table class="data-table">
 *       <thead>
 *         <th data-sort data-type="text|num|date">…</th>
 *       </thead>
 *       <tbody>… toutes les lignes rendues par Blade (aucun LIMIT SQL) …</tbody>
 *     </table>
 *   </div>
 * La barre d'outils et le pied de table sont générés ici, stylés dans
 * build/tailwind.input.css (recompiler avec ./build.sh après modification).
 *
 * Types de tri :
 *   - data-type="num"  : valeur numérique (montants, quantités)
 *   - data-type="date" : attribut data-timestamp="unix" sur le <td>
 *   - défaut           : texte insensible à la casse et aux accents
 *
 * LIGNES CLIQUABLES (data-row-link sur le conteneur) :
 *   - chaque <tr data-href="URL"> devient cliquable en entier ;
 *   - clic simple → navigation ; Ctrl/Clic-milieu → nouvel onglet ;
 *   - Entrée au clavier sur la ligne focusée (tabindex) → navigation ;
 *   - les liens/boutons/inputs DANS la ligne gardent leur comportement normal.
 *
 * PERSISTANCE D'ÉTAT + EXPORT CSV :
 *   - data-state-key="page.orders" : la recherche, le tri, les filtres et
 *     la page courante sont mémorisés dans sessionStorage (clé unique par
 *     liste) → revenir sur l'onglet retrouve la vue exacte ;
 *   - un bouton "Export CSV" apparaît dans la barre d'outils si le
 *     conteneur porte data-csv="nom-du-fichier" (export des lignes
 *     filtrées, séparateur ; compatible Excel FR, BOM UTF-8).
 *
 * MENU CONTEXTUEL PAR LIGNE (data-row-menu sur le conteneur) :
 *   - un bouton déclencheur ⋮ est ajouté dans la DERNIÈRE colonne de
 *     chaque ligne (bouton [data-menu-trigger] généré) ;
 *   - clic droit n'importe où sur la ligne ouvre aussi le menu ;
 *   - les entrées viennent d'un <template data-menu-template> placé dans
 *     la vue : chaque élément porte data-action="link|post|confirm-link",
 *     data-url (ou :name="url" pour injecter l'URL de la ligne),
 *     data-confirm (texte affiché avant une action destructrice) ;
 *   - les actions "post" envoient un POST JSON avec token CSRF (meta tag)
 *     puis rechargent la page (messages flash du serveur).
 */

(function () {
    'use strict';

    // Normalise : minuscules + suppression des accents/diacritiques.
    function normalize(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    // Valeur de tri d'une cellule selon le type déclaré sur le <th>.
    function sortValue(td, type) {
        if (type === 'num') {
            var n = parseFloat((td.textContent || '').replace(/[^\d.,-]/g, '').replace(/\s/g, '').replace(',', '.'));
            return isNaN(n) ? -Infinity : n;
        }
        if (type === 'date') {
            var ts = td.getAttribute('data-timestamp');
            return ts ? parseInt(ts, 10) : 0;
        }
        return normalize(td.textContent);
    }

    // Valeur de recherche : attribut data-search s'il existe, sinon le texte.
    function searchValue(td) {
        return td.hasAttribute('data-search') ? td.getAttribute('data-search') : (td.textContent || '');
    }

    function DataTable(root) {
        var self = this;
        this.root = root;
        this.table = root.querySelector('table.data-table');
        if (!this.table || !this.table.tBodies.length) return;

        this.headers = Array.prototype.slice.call(this.table.querySelectorAll('thead th'));
        this.body = this.table.tBodies[0];
        this.originalRows = Array.prototype.slice.call(this.body.rows)
            .filter(function (r) { return !r.querySelector('.empty-cell'); });
        this.emptyRow = this.body.querySelector('.empty-cell')
            ? this.body.querySelector('.empty-cell').closest('tr') : null;

        this.page = 1;
        this.perPage = 15;
        this.sortKey = -1;   // index de colonne triée (-1 : ordre serveur)
        this.sortDir = 1;    // 1 = ascendant, -1 = descendant
        this.filters = {};   // {indexColonne: valeurChoisie}

        this.stateKey = root.getAttribute('data-state-key') || null;

        this.buildToolbar();
        this.buildFooter();
        this.bindHeaders();
        this.bindFilters();
        if (root.hasAttribute('data-row-link') || this.table.hasAttribute('data-row-link')) {
            this.bindRowLinks();
        }
        if (root.hasAttribute('data-row-menu') || this.table.hasAttribute('data-row-menu')) {
            this.buildMenuColumn();
        }
        this.restoreState();
        this.apply();
        this.saveState();
    }

    DataTable.prototype = {
        /* ---------------------------------------------------------- UI */
        el: function (tag, cls, html) {
            var n = document.createElement(tag);
            if (cls) n.className = cls;
            if (html !== undefined) n.innerHTML = html;
            return n;
        },

        buildToolbar: function () {
            var self = this;
            var bar = this.el('div', 'data-table-toolbar');

            // — Recherche plein texte —
            var tools = this.el('div', 'data-table-tools');
            var search = this.el('div', 'data-table-search');
            var icon = this.el('i');
            icon.setAttribute('data-lucide', 'search');
            search.appendChild(icon);
            var input = this.el('input', 'input input-sm');
            input.type = 'search';
            input.placeholder = 'Rechercher…';
            input.setAttribute('aria-label', 'Recherche dans le tableau');
            search.appendChild(input);
            tools.appendChild(search);
            bar.appendChild(tools);
            this.searchInput = input;

            // — Sélecteur de taille de page —
            var right = this.el('div', 'data-table-tools');
            var per = this.el('select', 'input input-sm data-table-select');
            [10, 15, 25, 50, 100].forEach(function (n) {
                var o = self.el('option', '', n + ' / page');
                o.value = n;
                if (n === self.perPage) o.selected = true;
                per.appendChild(o);
            });
            per.addEventListener('change', function () {
                self.perPage = parseInt(per.value, 10);
                self.page = 1;
                self.apply();
            });
            right.appendChild(per);

            // — Bouton Export CSV (optionnel : data-csv="nom") —
            var csvName = self.root.getAttribute('data-csv');
            if (csvName) {
                var csvBtn = self.el('button', 'btn-ghost btn-sm gap-1.5');
                csvBtn.type = 'button';
                csvBtn.innerHTML = '<i data-lucide="download"></i> CSV';
                csvBtn.setAttribute('aria-label', 'Exporter en CSV');
                csvBtn.addEventListener('click', function () { self.exportCsv(); });
                right.appendChild(csvBtn);
            }

            bar.appendChild(right);

            this.table.parentNode.insertBefore(bar, this.table);
            this.toolbar = bar;

            input.addEventListener('input', function () {
                self.page = 1;
                self.apply();
            });
        },

        buildFooter: function () {
            var foot = this.el('div', 'data-table-footer');
            this.info = this.el('div', 'data-table-info');
            this.pager = this.el('div', 'data-table-pager');
            foot.appendChild(this.info);
            foot.appendChild(this.pager);
            this.table.parentNode.insertBefore(foot, this.table.nextSibling);
        },

        bindHeaders: function () {
            var self = this;
            this.headers.forEach(function (th, i) {
                if (th.hasAttribute('data-sort')) {
                    th.addEventListener('click', function () { self.toggleSort(i); });
                }
            });
        },

        /* Menus déroulants générés depuis <th data-filter-label="Statut">. */
        bindFilters: function () {
            var self = this;
            this.headers.forEach(function (th, i) {
                var label = th.getAttribute('data-filter-label');
                if (!label) return;

                var sel = self.el('select', 'input input-sm data-table-select');
                sel.appendChild(self.el('option', '', label));
                sel.options[0].value = '';

                // Valeurs uniques présentes dans les lignes (texte brut du td).
                var values = [];
                var seen = {};
                self.originalRows.forEach(function (row) {
                    var td = row.cells[i];
                    if (!td) return;
                    var v = (td.getAttribute('data-search') || td.textContent || '').trim();
                    if (v && !seen[v]) { seen[v] = true; values.push(v); }
                });
                values.sort(function (a, b) { return a.localeCompare(b, 'fr'); });
                values.forEach(function (v) {
                    var o = self.el('option', '', v);
                    o.value = v;
                    sel.appendChild(o);
                });

                sel.addEventListener('change', function () {
                    if (sel.value === '') delete self.filters[i];
                    else self.filters[i] = sel.value;
                    self.page = 1;
                    self.apply();
                });

                // Placement : à côté du champ de recherche.
                self.toolbar.querySelector('.data-table-tools').appendChild(sel);
            });

            if (window.lucide) window.lucide.createIcons();
        },

        /*
         * Lignes cliquables : délégation d'événements sur le tbody —
         * un SEUL listener survit aux re-rendus (tri, pagination) car
         * apply() réattache les MÊMES éléments <tr>.
         */
        bindRowLinks: function () {
            var self = this;

            // Souris : clic sur la ligne (sauf liens/boutons/champs internes).
            this.body.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, textarea, label, [data-noclick]')) return;
                var row = e.target.closest('tr[data-href]');
                if (!row) return;
                if (e.ctrlKey || e.metaKey || e.button === 1) {
                    window.open(row.getAttribute('data-href'), '_blank');
                } else {
                    window.location.href = row.getAttribute('data-href');
                }
            });

            // Clavier : Entrée sur une ligne focusée ouvre la fiche.
            this.body.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                if (e.target.closest('a, button, input, select, textarea')) return;
                var row = e.target.closest('tr[data-href]');
                if (row) window.location.href = row.getAttribute('data-href');
            });

            // Les lignes avec lien deviennent focusables (navigation clavier).
            this.originalRows.forEach(function (row) {
                if (row.hasAttribute('data-href')) row.setAttribute('tabindex', '0');
            });
        },

        /* ------------------------------------------------- logique */
        toggleSort: function (col) {
            if (this.sortKey === col) {
                if (this.sortDir === 1) this.sortDir = -1;      // asc → desc
                else { this.sortKey = -1; this.sortDir = 1; }   // desc → neutre
            } else {
                this.sortKey = col;
                this.sortDir = 1;
            }
            this.updateHeaderClasses();
            this.apply();
        },

        updateHeaderClasses: function () {
            var self = this;
            this.headers.forEach(function (th, i) {
                th.classList.remove('is-sorted', 'is-sorted-asc', 'is-sorted-desc');
                if (self.sortKey === i) {
                    th.classList.add('is-sorted');
                    th.classList.add(self.sortDir === 1 ? 'is-sorted-asc' : 'is-sorted-desc');
                }
            });
        },

        visibleRows: function () {
            var self = this;
            var q = normalize(this.searchInput.value);

            var rows = this.originalRows.filter(function (row) {
                // 1) Filtres par colonne
                for (var col in self.filters) {
                    var td = row.cells[col];
                    if (!td) return false;
                    var v = (td.getAttribute('data-search') || td.textContent || '').trim();
                    if (v !== self.filters[col]) return false;
                }
                // 2) Recherche plein texte sur toutes les cellules
                if (q !== '') {
                    var hay = '';
                    Array.prototype.forEach.call(row.cells, function (c) {
                        hay += ' ' + searchValue(c);
                    });
                    if (normalize(hay).indexOf(q) === -1) return false;
                }
                return true;
            });

            // 3) Tri
            if (this.sortKey >= 0) {
                var th = this.headers[this.sortKey];
                var type = th.getAttribute('data-type') || 'text';
                var dir = this.sortDir;
                rows = rows.slice().sort(function (a, b) {
                    var ta = a.cells[self.sortKey], tb = b.cells[self.sortKey];
                    if (!ta || !tb) return 0;
                    var va = sortValue(ta, type), vb = sortValue(tb, type);
                    if (va < vb) return -1 * dir;
                    if (va > vb) return 1 * dir;
                    return 0;
                });
            }
            return rows;
        },

        /* Restaure la vue mémorisée (recherche, tri, filtres, page). */
        restoreState: function () {
            if (!this.stateKey) return;
            try {
                var s = JSON.parse(sessionStorage.getItem('dt.' + this.stateKey) || 'null');
                if (!s) return;
                if (s.q) this.searchInput.value = s.q;
                if (s.perPage) this.perPage = s.perPage;
                if (typeof s.sortKey === 'number' && s.sortKey >= 0) {
                    this.sortKey = s.sortKey;
                    this.sortDir = s.sortDir === -1 ? -1 : 1;
                }
                if (s.filters) this.filters = s.filters;
                if (s.page) this.page = s.page;
                this.updateHeaderClasses();
            } catch (e) { /* état corrompu : ignore */ }
        },

        /* Mémorise la vue courante (appelé à chaque apply). */
        saveState: function () {
            if (!this.stateKey) return;
            try {
                sessionStorage.setItem('dt.' + this.stateKey, JSON.stringify({
                    q: this.searchInput.value,
                    perPage: this.perPage,
                    sortKey: this.sortKey,
                    sortDir: this.sortDir,
                    filters: this.filters,
                    page: this.page,
                }));
            } catch (e) { /* quota : ignore */ }
        },

        /* Export CSV des lignes filtrées (séparateur ; + BOM UTF-8). */
        exportCsv: function () {
            var rows = this.visibleRows();
            var esc = function (v) {
                return '"' + String(v).replace(/"/g, '""') + '"';
            };
            var lines = [this.headers
                .filter(function (th) { return !th.hasAttribute('data-no-export'); })
                .map(function (th) { return esc(th.textContent.trim()); })
                .join(';')];

            rows.forEach(function (row) {
                var cells = [];
                Array.prototype.forEach.call(row.cells, function (td, i) {
                    if (self_no_export(td)) return;
                    cells.push(esc((td.getAttribute('data-search') || td.textContent || '').trim()));
                });
                lines.push(cells.join(';'));
            });

            function self_no_export(td) {
                // Colonne Actions (cellule du trigger ⋮) exclue de l'export
                return td.classList.contains('dt-menu-cell');
            }

            var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            var d = new Date();
            a.download = (this.root.getAttribute('data-csv') || 'export')
                + '_' + d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0') + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);
        },

        apply: function () {
            var rows = this.visibleRows();
            var total = rows.length;

            // Pagination
            var pages = Math.max(1, Math.ceil(total / this.perPage));
            if (this.page > pages) this.page = pages;
            var start = (this.page - 1) * this.perPage;
            var slice = rows.slice(start, start + this.perPage);

            // Rendu du tbody : on vide, on remet les lignes sélectionnées.
            var self = this;
            this.body.innerHTML = '';
            if (slice.length === 0) {
                if (this.emptyRow) {
                    // Message adapté selon la présence de filtres actifs.
                    var cell = this.emptyRow.querySelector('.empty-cell');
                    if (cell) cell.textContent = this.hasActiveFilter()
                        ? 'Aucun résultat pour ces critères.'
                        : (this.emptyRow.getAttribute('data-empty-text') || 'Aucune donnée.');
                    this.body.appendChild(this.emptyRow.cloneNode(true));
                }
            } else {
                slice.forEach(function (r) { self.body.appendChild(r); });
            }

            // Compteur
            var from = total === 0 ? 0 : start + 1;
            var to = Math.min(start + this.perPage, total);
            this.info.textContent = total === 0
                ? '0 résultat'
                : 'Résultats ' + from + '–' + to + ' sur ' + total;

            this.renderPager(pages);
            this.saveState();
        },

        hasActiveFilter: function () {
            return this.searchInput.value !== '' || Object.keys(this.filters).length > 0;
        },

        renderPager: function (pages) {
            var self = this;
            this.pager.innerHTML = '';

            var mk = function (label, page, disabled, active, aria) {
                var b = self.el('button', 'page-btn' + (active ? ' is-active' : ''), label);
                b.type = 'button';
                if (aria) b.setAttribute('aria-label', aria);
                if (disabled) b.disabled = true;
                else b.addEventListener('click', function () {
                    self.page = page;
                    self.apply();
                });
                return b;
            };

            this.pager.appendChild(mk('‹', this.page - 1, this.page <= 1, false, 'Page précédente'));

            var pad = 1; // pages de chaque côté de la page courante
            var shown = [];
            for (var p = 1; p <= pages; p++) {
                if (p === 1 || p === pages || Math.abs(p - this.page) <= pad) shown.push(p);
            }
            var last = 0;
            shown.forEach(function (p) {
                if (p - last > 1) self.pager.appendChild(self.el('span', 'page-ellipsis', '…'));
                self.pager.appendChild(mk(String(p), p, false, p === self.page));
                last = p;
            });

            this.pager.appendChild(mk('›', this.page + 1, this.page >= pages, false, 'Page suivante'));
        },

        /* ============================================================
         * MENU CONTEXTUEL PAR LIGNE
         * ============================================================ */

        /*
         * Ajoute la colonne "Actions" : un bouton ⋮ par ligne. Le menu
         * lui-même est un élément unique, rempli depuis le <template>
         * de la vue à CHAQUE ouverture (les URLs sont donc toujours celles
         * de la ligne cliquée).
         */
        buildMenuColumn: function () {
            var self = this;
            var template = this.root.querySelector('template[data-menu-template]');
            if (!template) return;

            this.menuTemplate = template;

            // En-tête de la colonne ajoutée.
            var th = document.createElement('th');
            th.className = '!w-12';
            th.setAttribute('aria-label', 'Actions');
            this.table.querySelector('thead tr').appendChild(th);
            this.headers.push(th);

            // Un trigger ⋮ dans la dernière cellule de chaque ligne.
            this.originalRows.forEach(function (row) {
                row.setAttribute('data-menu-row', ''); // cible du clic droit
                var td = row.insertCell(-1);
                td.className = 'dt-menu-cell text-right';
                var btn = self.el('button', 'dt-menu-trigger');
                btn.type = 'button';
                btn.setAttribute('data-menu-trigger', '');
                btn.setAttribute('aria-haspopup', 'true');
                btn.setAttribute('aria-label', 'Actions pour cette ligne');
                btn.innerHTML = '<i data-lucide="ellipsis-vertical"></i>';
                td.appendChild(btn);
            });

            // Un seul menu flottant pour toute la table, créé paresseusement.
            this.menuEl = null;

            // Ouverture : clic sur un trigger OU clic droit sur une ligne.
            this.table.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-menu-trigger]');
                if (trigger) {
                    e.stopPropagation();
                    self.toggleMenuFor(trigger.closest('tr'));
                }
            });

            this.table.addEventListener('contextmenu', function (e) {
                var row = e.target.closest('tbody tr[data-menu-row]');
                if (!row) return;
                e.preventDefault();
                self.openMenuFor(row, e.clientX, e.clientY);
            });

            // Fermeture : clic ailleurs, Échap, scroll de la page.
            document.addEventListener('click', function (e) {
                if (self.menuEl && !e.target.closest('.dt-context-menu')) self.closeMenu();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') self.closeMenu();
            });
            window.addEventListener('scroll', function () { self.closeMenu(); }, true);
            window.addEventListener('resize', function () { self.closeMenu(); });

            if (window.lucide) window.lucide.createIcons();
        },

        toggleMenuFor: function (row) {
            if (this.menuEl && this.menuRow === row) {
                this.closeMenu();
            } else {
                var rect = row.getBoundingClientRect();
                this.openMenuFor(row, rect.right - 8, rect.bottom + 4);
            }
        },

        openMenuFor: function (row, x, y) {
            var self = this;
            this.closeMenu();

            // Construction du menu depuis le template, avec les données de
            // la ligne : les vues peuvent exposer des champs via data-* sur
            // le <tr> (data-menu-url-*, data-menu-label...).
            var menu = this.el('div', 'dt-context-menu');
            menu.setAttribute('role', 'menu');

            // — En-tête optionnel : 1ʳʱ cellule de la ligne (ex: n° ticket)—
            var title = this.el('div', 'dt-menu-title');
            title.textContent = (row.cells[0] ? row.cells[0].textContent.trim() : '') || 'Actions';
            menu.appendChild(title);

            this.menuRow = row;

            this.menuTemplate.content.querySelectorAll('[data-action]').forEach(function (item) {
                var action = item.getAttribute('data-action');

                // Éligibilité : l'entrée n'apparaît que si la ligne porte
                // l'attribut data-menu-<needs> demandé (ex: data-needs="ready"
                // → data-menu-ready présent sur le <tr>).
                var needs = item.getAttribute('data-needs');
                if (needs && !row.hasAttribute('data-menu-' + needs)) return;

                var url = self.resolveMenuUrl(item, row);
                if (!url) return; // entrée non applicable à cette ligne

                var btn = self.el('button', 'dt-menu-item' + (action.indexOf('confirm') === 0 ? ' dt-menu-danger' : ''));
                btn.type = 'button';
                btn.setAttribute('role', 'menuitem');
                // Le contenu du <template> (icône SVG rendue par Blade + libellé)
                // est cloné tel quel dans l'entrée du menu.
                btn.innerHTML = item.innerHTML;
                btn.tabIndex = 0; // navigation clavier dans le menu

                btn.addEventListener('click', function () {
                    self.closeMenu();
                    if (action === 'post') {
                        self.postMenuAction(url, item);
                    } else {
                        // link / confirm-link : navigation (nouvel onglet si ctrl)
                        if (action === 'confirm-link' && !window.confirm(item.getAttribute('data-confirm') || 'Confirmer ?')) return;
                        window.open(url, '_blank');
                    }
                });
                menu.appendChild(btn);
            });

            document.body.appendChild(menu);

            // Les icônes du menu sont des <i data-lucide> clonés du template :
            // une passe createIcons les convertit en SVG (après chargement).
            if (window.lucide) window.lucide.createIcons();

            // Positionnement dans le viewport (bascule à gauche si débordement).
            var mw = menu.offsetWidth, mh = menu.offsetHeight;
            var left = Math.min(x, window.innerWidth - mw - 8);
            var top = y;
            if (top + mh > window.innerHeight - 8) top = Math.max(8, y - mh - 28);
            menu.style.left = Math.max(8, left) + 'px';
            menu.style.top = top + 'px';

            this.menuEl = menu;
            var first = menu.querySelector('.dt-menu-item');
            if (first) first.focus();
        },

        /* Injecte les identifiants de la ligne dans l'URL du template. */
        resolveMenuUrl: function (item, row) {
            var url = item.getAttribute('data-url') || '';
            if (!url) return null;

            // La vue fournit data-menu-params="id:123,cid:7" sur le <tr>.
            var params = row.getAttribute('data-menu-params') || '';
            params.split(',').forEach(function (pair) {
                var kv = pair.split(':');
                if (kv.length === 2) {
                    url = url.replace('{' + kv[0].trim() + '}', encodeURIComponent(kv[1].trim()));
                }
            });
            // Une URL encore paramétrée = entrée non applicable à cette ligne.
            return url.indexOf('{') === -1 ? url : null;
        },

        /* Action POST (CSRF inclus) puis rechargement pour lire les flash. */
        postMenuAction: function (url, item) {
            var token = document.querySelector('meta[name="csrf-token"]');
            var confirmText = item.getAttribute('data-confirm');
            if (confirmText && !window.confirm(confirmText)) return;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            }).then(function (res) {
                if (res.ok) { window.location.reload(); return; }
                return res.json().then(function (data) {
                    window.alert((data && data.message) || 'Une erreur est survenue.');
                });
            }).catch(function () {
                window.alert('Erreur réseau : action non effectuée.');
            });
        },

        closeMenu: function () {
            if (this.menuEl) {
                this.menuEl.remove();
                this.menuEl = null;
                this.menuRow = null;
            }
        }
    };        /* ------------------------------------------------------ init auto */
    function init() {
        document.querySelectorAll('[data-datatable]').forEach(function (root) {
            if (!root.__datatable) root.__datatable = new DataTable(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
