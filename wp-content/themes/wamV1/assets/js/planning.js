/**
 * planning.js — Filtrage single-select + Vue mobile jour par jour
 *
 * Filtres :
 *   - "all"       → Réinitialise tout, tout visible, aucun filtre barré.
 *   - "cat:SLUG"  → Seul ce filtre actif ; les autres sont barrés (is-struck).
 *   - "complet"   → Idem, filtre sur les cours marqués complet.
 *   - Cliquer le filtre déjà actif → retour à "Tous".
 *   - Le filtre s'applique aux 3 vues : grille desktop, mobile jour, agenda.
 *
 * Mobile (≤768px) :
 *   - Navigation jour par jour avec flèches + dots.
 *   - Swipe gauche/droite pour changer de jour.
 *   - Auto-sélection du jour courant au chargement.
 *
 * @package wamv1
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ==============================================
           FILTER SYSTEM — single select
           ============================================== */

        var filterBtns  = document.querySelectorAll('.planning-legend__item[data-filter]');
        var gridCards   = document.querySelectorAll('.planning-card');
        var mobileCards = document.querySelectorAll('.planning-mobile__card');
        var allBtn      = document.querySelector('.planning-legend__item[data-filter="all"]');

        if (!filterBtns.length) return;

        var activeFilter = null; // null = "Tous"

        function resetAll() {
            activeFilter = null;
            filterBtns.forEach(function (b) {
                b.classList.remove('is-active', 'is-struck');
                b.setAttribute('aria-pressed', 'false');
            });
            if (allBtn) {
                allBtn.classList.add('is-active');
                allBtn.setAttribute('aria-pressed', 'true');
            }
        }

        function cardMatchesFilter(card) {
            if (!activeFilter) return true;

            var cardCats  = (card.dataset.cats || '').split(' ').filter(Boolean);
            var isComplet = card.hasAttribute('data-complet') ||
                            card.classList.contains('planning-card--complet');

            if (activeFilter === 'complet') return isComplet;
            if (activeFilter.startsWith('cat:')) {
                return cardCats.indexOf(activeFilter.slice(4)) !== -1;
            }
            return false;
        }

        function applyFilter() {
            /* Grille desktop */
            gridCards.forEach(function (card) {
                card.classList.toggle('planning-card--hidden', !cardMatchesFilter(card));
            });

            /* Cards mobile jour par jour */
            mobileCards.forEach(function (card) {
                card.classList.toggle('planning-mobile__card--hidden', !cardMatchesFilter(card));
            });

            /* Mobile : message "aucun cours pour ce filtre" par panel */
            document.querySelectorAll('.planning-mobile__panel').forEach(function (panel) {
                var cards   = panel.querySelectorAll('.planning-mobile__card');
                var noMatch = panel.querySelector('.planning-mobile__no-match');
                if (!noMatch || cards.length === 0) return;

                var allHidden = true;
                cards.forEach(function (c) {
                    if (!c.classList.contains('planning-mobile__card--hidden')) allHidden = false;
                });
                noMatch.hidden = !(allHidden && activeFilter);
            });
        }

        function updateButtons() {
            filterBtns.forEach(function (b) {
                var f = b.dataset.filter;
                if (!activeFilter) {
                    b.classList.remove('is-active', 'is-struck');
                    if (f === 'all') {
                        b.classList.add('is-active');
                        b.setAttribute('aria-pressed', 'true');
                    } else {
                        b.setAttribute('aria-pressed', 'false');
                    }
                } else {
                    if (f === activeFilter) {
                        b.classList.add('is-active');
                        b.classList.remove('is-struck');
                        b.setAttribute('aria-pressed', 'true');
                    } else if (f === 'all') {
                        b.classList.remove('is-active', 'is-struck');
                        b.setAttribute('aria-pressed', 'false');
                    } else {
                        b.classList.remove('is-active');
                        b.classList.add('is-struck');
                        b.setAttribute('aria-pressed', 'false');
                    }
                }
            });
        }

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = btn.dataset.filter;
                if (filter === 'all' || activeFilter === filter) {
                    resetAll();
                } else {
                    activeFilter = filter;
                }
                updateButtons();
                applyFilter();
            });
        });

        /* ==============================================
           MOBILE DAY-BY-DAY NAVIGATION
           ============================================== */

        var mobileView = document.querySelector('.planning-mobile');
        if (!mobileView) return;

        var track    = mobileView.querySelector('.planning-mobile__track');
        var panels   = mobileView.querySelectorAll('.planning-mobile__panel');
        var dayLabel = mobileView.querySelector('.planning-mobile__day-label');
        var prevBtn  = mobileView.querySelector('.planning-mobile__arrow--prev');
        var nextBtn  = mobileView.querySelector('.planning-mobile__arrow--next');
        var dots     = mobileView.querySelectorAll('.planning-mobile__dot');

        if (!track || !panels.length) return;

        var totalDays  = panels.length;
        var currentDay = 0;

        /* Jour courant : JS 0=Dim, 1=Lun…6=Sam → index 0=Lun…6=Dim */
        var jsDay = new Date().getDay();
        currentDay = jsDay === 0 ? 6 : jsDay - 1;

        function goToDay(index) {
            currentDay = Math.max(0, Math.min(totalDays - 1, index));

            /* Show/hide panels — hauteur s'adapte au contenu du jour */
            panels.forEach(function (p, i) {
                p.classList.toggle('is-active', i === currentDay);
            });

            /* Scroll vers le haut de la zone (respecte scroll-margin-top).
               On masque temporairement le header pour ne pas qu'il
               réapparaisse pendant ce scroll programmatique. */
            var header = document.querySelector('.wam-header');
            if (header) {
                header.dataset.planningScroll = '1';
                header.classList.add('header--hidden');
            }
            mobileView.scrollIntoView({ behavior: 'smooth', block: 'start' });
            /* Relâcher le verrou une fois le scroll terminé */
            if (header) {
                setTimeout(function () {
                    delete header.dataset.planningScroll;
                }, 600);
            }

            /* Label jour */
            if (dayLabel && panels[currentDay]) {
                dayLabel.textContent = panels[currentDay].dataset.dayLabel || '';
            }

            /* Dots */
            dots.forEach(function (d, i) {
                d.classList.toggle('is-active', i === currentDay);
            });

            /* Flèches */
            if (prevBtn) prevBtn.disabled = (currentDay === 0);
            if (nextBtn) nextBtn.disabled = (currentDay === totalDays - 1);
        }

        /* Init */
        goToDay(currentDay);

        /* Flèches */
        if (prevBtn) prevBtn.addEventListener('click', function () { goToDay(currentDay - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goToDay(currentDay + 1); });

        /* Dots */
        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { goToDay(i); });
        });

        /* Touch swipe */
        var touchStartX = 0;
        var touchStartY = 0;

        track.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        track.addEventListener('touchend', function (e) {
            var deltaX = e.changedTouches[0].clientX - touchStartX;
            var deltaY = e.changedTouches[0].clientY - touchStartY;

            /* Swipe horizontal dominant + seuil de 50px */
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                if (deltaX < 0) goToDay(currentDay + 1);   // swipe gauche → jour suivant
                else            goToDay(currentDay - 1);    // swipe droite → jour précédent
            }
        }, { passive: true });

    });
})();
