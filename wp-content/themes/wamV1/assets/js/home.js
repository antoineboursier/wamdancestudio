/**
 * WAM Dance Studio — Home animations
 * Animations de la page d'accueil : keywords + vidéos
 */

;(function () {
    'use strict';

    /* =====================================================
       KEYWORDS — Animation douce séquentielle
       ===================================================== */
    const keywordsEl = document.querySelectorAll('.keyword-word');
    const pauseKeywordBtn = document.getElementById('pause-keywords');

    if (keywordsEl.length) {
        let current = 0;
        let timer = null;
        let isPaused = false;
        const duration = 2800; // ms entre les mots

        function showWord(index) {
            // Retire les états des mots précédents
            keywordsEl.forEach((el, i) => {
                if (i === index) return;
                if (el.classList.contains('is-active')) {
                    el.classList.remove('is-active');
                    el.classList.add('is-leaving');
                    setTimeout(() => el.classList.remove('is-leaving'), 600);
                }
            });
            keywordsEl[index].classList.add('is-active');
        }

        function next() {
            current = (current + 1) % keywordsEl.length;
            showWord(current);
        }

        function start() {
            showWord(current);
            timer = setInterval(next, duration);
        }

        function pause() {
            clearInterval(timer);
            isPaused = true;
            if (pauseKeywordBtn) {
                pauseKeywordBtn.setAttribute('aria-pressed', 'true');
                pauseKeywordBtn.querySelector('.btn-pause__label').textContent = 'Reprendre l\'animation';
            }
        }

        function resume() {
            isPaused = false;
            timer = setInterval(next, duration);
            if (pauseKeywordBtn) {
                pauseKeywordBtn.setAttribute('aria-pressed', 'false');
                pauseKeywordBtn.querySelector('.btn-pause__label').textContent = 'Mettre en pause l\'animation';
            }
        }

        // Respect de prefers-reduced-motion
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (prefersReduced.matches) {
            // Affiche tous les mots empilés, pas d'animation
            keywordsEl.forEach(el => {
                el.style.position = 'static';
                el.style.opacity = '1';
                el.style.transform = 'none';
                el.style.display = 'block';
            });
        } else {
            start();
        }

        if (pauseKeywordBtn) {
            pauseKeywordBtn.addEventListener('click', () => {
                isPaused ? resume() : pause();
            });
        }

        // Pause quand la page perd le focus (accessibilité)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && !isPaused) pause();
            else if (!document.hidden && isPaused && !pauseKeywordBtn?.getAttribute('aria-pressed') === 'true') resume();
        });
    }

    /* =====================================================
       VIDÉOS — Pause globale
       ===================================================== */
    const pauseVideoBtn = document.getElementById('pause-videos');
    const videos = document.querySelectorAll('.video-card video');

    if (pauseVideoBtn && videos.length) {
        let videosPaused = false;

        pauseVideoBtn.addEventListener('click', () => {
            videosPaused = !videosPaused;
            videos.forEach(v => videosPaused ? v.pause() : v.play().catch(() => {}));
            pauseVideoBtn.setAttribute('aria-pressed', videosPaused ? 'true' : 'false');
            pauseVideoBtn.querySelector('.btn-pause__label').textContent =
                videosPaused ? 'Reprendre les vidéos' : 'Mettre en pause les vidéos';
        });

        // Respect prefers-reduced-motion → auto pause
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            videos.forEach(v => { v.pause(); v.removeAttribute('autoplay'); });
        }
    }

})();
