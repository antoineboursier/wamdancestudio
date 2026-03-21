/**
 * planning.js — Filtrage multi-select du planning hebdomadaire
 *
 * Écoute les clics sur .planning-legend__item[data-filter].
 * Filtre via .planning-card--hidden (display:none) sur .planning-card.
 *
 * Logique :
 *   - Aucun filtre actif → tout visible.
 *   - Filtres actifs (OR) : "standard" = adultes sans --enfant,
 *                           "enfant"   = avec --enfant,
 *                           "complet"  = avec --complet.
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

                var isEnfant  = card.classList.contains('planning-card--enfant');
                var isComplet = card.classList.contains('planning-card--complet');
                var matches   = false;

                /* OR entre les filtres actifs */
                if (activeFilters.has('standard') && !isEnfant) matches = true;
                if (activeFilters.has('enfant')   && isEnfant)  matches = true;
                if (activeFilters.has('complet')   && isComplet) matches = true;

                card.classList.toggle('planning-card--hidden', !matches);
            });
        }

        /* ---- Toggle bouton filtre ---- */
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = btn.dataset.filter;

                if (activeFilters.has(filter)) {
                    activeFilters.delete(filter);
                    btn.classList.remove('is-active');
                    btn.setAttribute('aria-pressed', 'false');
                } else {
                    activeFilters.add(filter);
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-pressed', 'true');
                }

                applyFilters();
            });
        });

    });
})();
