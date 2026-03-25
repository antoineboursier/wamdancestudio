/**
 * filter.js — Filtrage client-side
 *
 * Générique : fonctionne sur toute page avec :
 *   .cours-filter               — conteneur filtre
 *   .chip[data-filter]          — boutons de filtrage (slug terme ou "all")
 *   .cours-search               — champ de recherche texte
 *   .cours-categorie[data-cat]  — sections de catégories
 *   .card-cours[data-cat]       — cartes cours (slugs espace-séparés)
 *   .card-cours[data-title]     — titre indexé pour la recherche
 *
 * Masquage via classes CSS (.card-cours--hidden / .cours-categorie--hidden)
 * plutôt que l'attribut [hidden] — évite le conflit avec display:flex des cartes.
 * Animations fade-in gérées par CSS sur #cours-results.is-filtered.
 * Debounce 150ms sur la recherche.
 *
 * @package wamv1
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var filter = document.querySelector('.cours-filter');
        if (!filter) return;

        var chips            = filter.querySelectorAll('.chip[data-filter]');
        var searchInput      = filter.querySelector('.cours-search');
        var clearBtn         = filter.querySelector('.cours-search-clear');
        var categories       = document.querySelectorAll('.cours-categorie[data-cat]');
        var cards            = document.querySelectorAll('.card-cours, .card-stage');
        var resultsContainer = document.getElementById('cours-results');

        var activeFilter = 'all';
        var searchQuery  = '';
        var searchTimer  = null;

        /* ---- Pré-filtrage via ?cat= dans l'URL ---- */
        (function () {
            var params    = new URLSearchParams(window.location.search);
            var preFilter = params.get('cat');
            if (!preFilter || preFilter === 'all') return;

            var target = filter.querySelector('.chip[data-filter="' + preFilter + '"]');
            if (!target) return;

            /* Désactiver "Tous", activer le chip cible */
            chips.forEach(function (c) {
                c.classList.remove('chip--active');
                c.setAttribute('aria-pressed', 'false');
            });
            target.classList.add('chip--active');
            target.setAttribute('aria-pressed', 'true');
            activeFilter = preFilter;
        })();

        /* Appliquer l'état initial si un pré-filtre URL est actif */
        if (activeFilter !== 'all') applyFilters();

        /* ---- Filtrage ---- */
        function applyFilters() {
            var q = searchQuery.toLowerCase().trim();

            /* Retirer la classe d'animation pour pouvoir la ré-appliquer */
            if (resultsContainer) resultsContainer.classList.remove('is-filtered');

            /* Mise à jour des cartes */
            cards.forEach(function (card) {
                var catData = (card.dataset.cat   || '').split(' ');
                var title   = (card.dataset.title || '').toLowerCase();

                var matchesCat    = activeFilter === 'all' || catData.includes(activeFilter);
                var matchesSearch = !q || title.includes(q);

                var isVisible = matchesCat && matchesSearch;

                // On applique le masquage selon la classe de la card
                if (card.classList.contains('card-cours')) {
                    card.classList.toggle('card-cours--hidden', !isVisible);
                } 
                if (card.classList.contains('card-stage')) {
                    card.classList.toggle('card-stage--hidden', !isVisible);
                }
            });

            /* Masquer les sections entièrement vides */
            categories.forEach(function (section) {
                var visible = section.querySelectorAll('.card-cours:not(.card-cours--hidden)');
                section.classList.toggle('cours-categorie--hidden', visible.length === 0);
            });

            /* Force reflow puis ré-active les animations CSS */
            if (resultsContainer) {
                void resultsContainer.offsetWidth;
                resultsContainer.classList.add('is-filtered');
            }
        }

        /* ---- Chips ---- */
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) {
                    c.classList.remove('chip--active');
                    c.setAttribute('aria-pressed', 'false');
                });
                chip.classList.add('chip--active');
                chip.setAttribute('aria-pressed', 'true');
                activeFilter = chip.dataset.filter;
                applyFilters();
            });
        });

        /* ---- Recherche live (debounce 150ms) ---- */
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                searchQuery = searchInput.value;
                if (clearBtn) clearBtn.classList.toggle('is-visible', searchQuery.length > 0);
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilters, 150);
            });
        }

        /* ---- Bouton effacer ---- */
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchQuery = '';
                clearBtn.classList.remove('is-visible');
                clearTimeout(searchTimer);
                applyFilters();
                searchInput.focus();
            });
        }

    });
})();
