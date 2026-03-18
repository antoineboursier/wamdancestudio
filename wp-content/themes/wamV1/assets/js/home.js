/**
 * WAM Dance Studio — Home animations
 * - Cursive Live : boucle infinie sur les mots (cycle permanent)
 * - Vidéos : pause globale
 * - Icônes play/pause dynamiques via wamIconsDir
 */

; (function () {
    'use strict';

    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const iconDir = (window.wamIconsDir || '').replace(/\/$/, '');

    /* =====================================================
       HELPER — Changer l'icône d'un bouton pause
       img.icon-pause-state → play ou pause
       ===================================================== */
    function setPauseIcon(btn, isNowPaused) {
        const iconSpan = btn.querySelector('.btn-icon');
        const name = isNowPaused ? 'play' : 'pause';
        if (iconSpan) {
            iconSpan.style.setProperty('--icon-url', `url('${iconDir}/${name}.svg')`);
        }
    }

    /* =====================================================
       KEYWORDS — Cursive Live — Boucle infinie
       Les mots tournent en cycle permanent :
       kw-0 → kw-1 → kw-2 → kw-0 → kw-1 → ...
       ===================================================== */
    const wordsData = window.wamKeywords || [];
    const pauseBtn = document.getElementById('pause-keywords');
    const particlesC = document.getElementById('keywords-particles');

    if (wordsData.length) {

        // 1. Construction des spans lettre par lettre
        wordsData.forEach(({ text, id }) => {
            const el = document.getElementById(id);
            if (!el) return;
            [...text].forEach(char => {
                const s = document.createElement('span');
                s.className = 'kw-char';
                s.textContent = char === ' ' ? '\u00a0' : char;
                el.appendChild(s);
            });
        });

        // 2. Particules colorées
        if (particlesC && !prefersReduced) {
            const colors = [
                'var(--wp--preset--color--accent-yellow)',
                'var(--wp--preset--color--accent-pink)',
                'var(--wp--preset--color--accent-green)',
                'var(--wp--preset--color--accent-orange)',
            ];
            for (let i = 0; i < 240; i++) {
                const p = document.createElement('div');
                p.className = 'keywords-particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.top = (20 + Math.random() * 60) + '%';
                p.style.background = colors[Math.floor(Math.random() * colors.length)];
                p.style.setProperty('--dur', (3 + Math.random() * 4) + 's');
                p.style.setProperty('--delay', (Math.random() * 7) + 's');
                p.style.setProperty('--drift', ((Math.random() - 0.5) * 100) + 'px');
                const sz = Math.random() < 0.2 ? 3 : 2;
                p.style.width = sz + 'px';
                p.style.height = sz + 'px';
                particlesC.appendChild(p);
            }
        }

        // 3. Animation — cycle infini
        let current = 0;
        let mainTimer = null;
        let isPaused = false;
        let charTimers = [];

        function clearCharTimers() {
            charTimers.forEach(clearTimeout);
            charTimers = [];
        }

        function showWord(i) {
            const el = document.getElementById(wordsData[i].id);
            if (!el) return;
            el.classList.add('kw-visible');
            const chars = el.querySelectorAll('.kw-char');
            chars.forEach((c, j) => {
                const t = setTimeout(() => c.classList.add('kw-char--visible'), j * 55 + 40);
                charTimers.push(t);
            });
        }

        function hideWord(i) {
            const el = document.getElementById(wordsData[i].id);
            if (!el) return;
            el.classList.add('kw-leaving');
            const chars = [...el.querySelectorAll('.kw-char')].reverse();
            chars.forEach((c, j) => {
                const t = setTimeout(() => c.classList.remove('kw-char--visible'), j * 28);
                charTimers.push(t);
            });
            const totalDelay = chars.length * 28 + 350;
            charTimers.push(setTimeout(() => {
                el.classList.remove('kw-visible', 'kw-leaving');
            }, totalDelay));
        }

        function advanceWord() {
            clearCharTimers();
            hideWord(current);
            // Boucle infinie : retour à 0 après le dernier mot
            current = (current + 1) % wordsData.length;
            charTimers.push(setTimeout(() => showWord(current), 420));
        }

        function startAnim() {
            showWord(current);
            mainTimer = setInterval(advanceWord, 3600); // 3.6s par mot
        }

        function pauseAnim() {
            clearInterval(mainTimer);
            clearCharTimers(); // Stop current letter animations
            isPaused = true;
            const sect = document.querySelector('.section-keywords');
            if (sect) sect.classList.add('is-paused');
            if (pauseBtn) {
                pauseBtn.setAttribute('aria-pressed', 'true');
                const lbl = pauseBtn.querySelector('span:not(.btn-icon)');
                if (lbl) lbl.textContent = "Reprendre l'animation";
                setPauseIcon(pauseBtn, true);
            }
        }

        function resumeAnim() {
            isPaused = false;
            const sect = document.querySelector('.section-keywords');
            if (sect) sect.classList.remove('is-paused');
            advanceWord(); // Restart immediately
            mainTimer = setInterval(advanceWord, 3600);
            if (pauseBtn) {
                pauseBtn.setAttribute('aria-pressed', 'false');
                const lbl = pauseBtn.querySelector('span:not(.btn-icon)');
                if (lbl) lbl.textContent = "Mettre en pause l'animation";
                setPauseIcon(pauseBtn, false);
            }
        }

        // prefers-reduced-motion → statique
        if (prefersReduced) {
            wordsData.forEach(({ id }) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.style.position = 'static';
                el.style.display = 'block';
                el.classList.add('kw-visible');
                el.querySelectorAll('.kw-char').forEach(c => c.classList.add('kw-char--visible'));
            });
        } else {
            startAnim();
        }

        if (pauseBtn) {
            pauseBtn.addEventListener('click', () => isPaused ? resumeAnim() : pauseAnim());
        }

        // Pause automatique quand page cachée
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && !isPaused) pauseAnim();
        });
    }

    /* =====================================================
       VIDÉOS — Pause globale avec icône play/pause
       ===================================================== */
    const pauseVideoBtn = document.getElementById('pause-videos');
    const videos = document.querySelectorAll('.video-card video');

    if (pauseVideoBtn && videos.length) {
        let videosPaused = false;

        if (prefersReduced) {
            videos.forEach(v => { v.pause(); v.removeAttribute('autoplay'); });
            videosPaused = true;
            pauseVideoBtn.setAttribute('aria-pressed', 'true');
            setPauseIcon(pauseVideoBtn, true);
            const lbl = pauseVideoBtn.querySelector('.btn-pause__label');
            if (lbl) lbl.textContent = 'Reprendre les vidéos';
        }

        pauseVideoBtn.addEventListener('click', () => {
            videosPaused = !videosPaused;
            videos.forEach(v => videosPaused ? v.pause() : v.play().catch(() => { }));
            pauseVideoBtn.setAttribute('aria-pressed', videosPaused ? 'true' : 'false');
            setPauseIcon(pauseVideoBtn, videosPaused);
            const lbl = pauseVideoBtn.querySelector('span:not(.btn-icon)');
            if (lbl) lbl.textContent = videosPaused ? 'Reprendre les vidéos' : 'Mettre en pause les vidéos';
        });
    }

    /* =====================================================
       SECTION TEACHERS — bg pattern inline
       (injecte le bg-pattern si php ne le fait pas via style)
       ===================================================== */
    const sectTeachers = document.querySelector('.section-teachers');
    if (sectTeachers && iconDir) {
        const patternUrl = iconDir + '/bg_pattern_color_black.svg';
        if (!sectTeachers.style.backgroundImage) {
            sectTeachers.style.backgroundImage = "url('" + patternUrl + "')";
        }
    }

})();
