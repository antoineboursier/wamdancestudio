/**
 * planning.js — Filtrage par catégorie et état "Complet"
 *
 * Écoute les clics sur .planning-legend__item[data-filter].
 * Filtre via .planning-card--hidden (display:none) sur .planning-card.
 *
 * Logique :
 *   - "all"       → Réinitialise tout, tout visible.
 *   - "cat:SLUG"  → Filtre sur data-cats de la card (slugs cat_cours).
 *   - "complet"   → Filtre sur classe .planning-card--complet.
 *   - Multi-select (OR) : plusieurs filtres peuvent être actifs.
 *   - Cliquer un filtre actif le désactive.
 *
 * @package wamv1
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var filterBtns = document.querySelectorAll('.planning-legend__item[data-filter]');
        var cards      = document.querySelectorAll('.planning-card');

        if (!filterBtns.length || !cards.length) return;

        var activeFilters = new Set();

        /* ---- Application des filtres ---- */
        function applyFilters() {
            cards.forEach(function (card) {

                /* Aucun filtre actif → tout montrer */
                if (activeFilters.size === 0) {
                    card.classList.remove('planning-card--hidden');
                    return;
                }

                var cardCats  = (card.dataset.cats || '').split(' ').filter(Boolean);
                var isComplet = card.classList.contains('planning-card--complet');
                var matches   = false;

                activeFilters.forEach(function (f) {
                    if (f === 'complet' && isComplet) {
                        matches = true;
                    } else if (f.startsWith('cat:')) {
                        var slug = f.slice(4); // 'cat:enfants' → 'enfants'
                        if (cardCats.indexOf(slug) !== -1) matches = true;
                    }
                });

                card.classList.toggle('planning-card--hidden', !matches);
            });
        }

        /* ---- Toggle bouton filtre ---- */
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = btn.dataset.filter;

                /* Bouton "Tous" : réinitialise tout */
                if (filter === 'all') {
                    activeFilters.clear();
                    filterBtns.forEach(function (b) {
                        b.classList.remove('is-active');
                        b.setAttribute('aria-pressed', 'false');
                    });
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-pressed', 'true');
                    applyFilters();
                    return;
                }

                /* Désactiver le bouton "Tous" si on active un filtre spécifique */
                var allBtn = document.querySelector('.planning-legend__item[data-filter="all"]');
                if (allBtn) {
                    allBtn.classList.remove('is-active');
                    allBtn.setAttribute('aria-pressed', 'false');
                }

                if (activeFilters.has(filter)) {
                    activeFilters.delete(filter);
                    btn.classList.remove('is-active');
                    btn.setAttribute('aria-pressed', 'false');
                } else {
                    activeFilters.add(filter);
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-pressed', 'true');
                }

                /* Si plus aucun filtre actif → réactiver "Tous" */
                if (activeFilters.size === 0 && allBtn) {
                    allBtn.classList.add('is-active');
                    allBtn.setAttribute('aria-pressed', 'true');
                }

                applyFilters();
            });
        });

    });
})();
