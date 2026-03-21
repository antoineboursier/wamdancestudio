/**
 * WAM Accessibility Module — JS
 * Gère le panel de personnalisation et persiste les choix en localStorage.
 * Applique les classes CSS sur <html> dès le chargement de la page.
 *
 * Clé localStorage : 'wamA11yPrefs' → objet JSON
 */

; (function () {
    'use strict';

    /* =====================================================
       CONSTANTES
       ===================================================== */
    const STORAGE_KEY = 'wamA11yPrefs';

    const DEFAULTS = {
        theme: 'dark',   // 'dark' | 'light'
        noGraphicalFonts: false,   // bool
        fontChoice: 'default', // 'default' | 'comic-sans' | 'arial' | 'times'
        fontSize: '100',    // '100' | '120' | '150'
        lineHeight: 'default', // 'default' | 'increased'
        reduceMotion: false,    // bool
    };

    /* =====================================================
       LECTURE / ÉCRITURE localStorage
       ===================================================== */
    function loadPrefs() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? Object.assign({}, DEFAULTS, JSON.parse(raw)) : Object.assign({}, DEFAULTS);
        } catch {
            return Object.assign({}, DEFAULTS);
        }
    }

    function savePrefs(prefs) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs)); } catch { }
    }

    /* =====================================================
       APPLICATION DES CLASSES SUR <html>
       ===================================================== */
    const html = document.documentElement;

    function applyPrefs(prefs) {
        // Thème
        html.classList.toggle('wam-theme-dark', prefs.theme === 'dark');
        html.classList.toggle('wam-theme-light', prefs.theme === 'light');

        // Polices graphiques
        html.classList.toggle('wam-no-graphical-fonts', !!prefs.noGraphicalFonts);

        // Police de substitution
        html.classList.remove('wam-font-comic-sans', 'wam-font-arial', 'wam-font-times');
        if (prefs.fontChoice && prefs.fontChoice !== 'default') {
            const map = { 'comic-sans': 'wam-font-comic-sans', 'arial': 'wam-font-arial', 'times': 'wam-font-times' };
            if (map[prefs.fontChoice]) html.classList.add(map[prefs.fontChoice]);
        }

        // Taille texte
        html.classList.remove('wam-text-120', 'wam-text-150');
        if (prefs.fontSize === '120') html.classList.add('wam-text-120');
        if (prefs.fontSize === '150') html.classList.add('wam-text-150');

        // Interlignage
        html.classList.toggle('wam-line-height-increased', prefs.lineHeight === 'increased');

        // Animations
        html.classList.toggle('wam-reduce-motion', !!prefs.reduceMotion);
    }

    /* Appliqué immédiatement (avant DOMContentLoaded) pour éviter le flash */
    const prefsEarly = loadPrefs();
    applyPrefs(prefsEarly);

    /* =====================================================
       INITIALISATION DU PANEL
       ===================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        const trigger = document.getElementById('wam-a11y-trigger');
        const panel = document.getElementById('wam-a11y-panel');
        const closeBtn = document.getElementById('wam-a11y-close');
        const backdrop = document.getElementById('wam-a11y-backdrop');
        const resetBtn = document.getElementById('wam-a11y-reset');

        if (!trigger || !panel) return;

        let prefs = loadPrefs();

        /* ----- Sync UI → prefs ----- */
        function syncUI() {
            // Radios thème
            const themeRadios = panel.querySelectorAll('input[name="wam-theme"]');
            themeRadios.forEach(r => { r.checked = r.value === prefs.theme; });

            // Checkbox polices graphiques
            const noGraphical = panel.querySelector('#a11y-no-graphical-fonts');
            if (noGraphical) noGraphical.checked = !!prefs.noGraphicalFonts;

            // Select police
            const fontSelect = panel.querySelector('#a11y-font-choice');
            if (fontSelect) fontSelect.value = prefs.fontChoice || 'default';

            // Radios taille
            const sizeRadios = panel.querySelectorAll('input[name="wam-font-size"]');
            sizeRadios.forEach(r => { r.checked = r.value === prefs.fontSize; });

            // Radios interlignage
            const lhRadios = panel.querySelectorAll('input[name="wam-line-height"]');
            lhRadios.forEach(r => { r.checked = r.value === prefs.lineHeight; });

            // Checkbox animations
            const motionCheck = panel.querySelector('#a11y-reduce-motion');
            if (motionCheck) motionCheck.checked = !!prefs.reduceMotion;
        }

        /* ----- Ouverture / Fermeture ----- */
        function openPanel() {
            panel.removeAttribute('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            backdrop.classList.add('is-visible');
            panel.focus && setTimeout(() => {
                const first = panel.querySelector('input, select, button');
                if (first) first.focus();
            }, 50);
        }

        function closePanel() {
            panel.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
            backdrop.classList.remove('is-visible');
            trigger.focus();
        }

        trigger.addEventListener('click', () => {
            const isOpen = !panel.hasAttribute('hidden');
            isOpen ? closePanel() : openPanel();
        });

        closeBtn && closeBtn.addEventListener('click', closePanel);
        backdrop && backdrop.addEventListener('click', closePanel);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !panel.hasAttribute('hidden')) closePanel();
        });

        /* ----- Écoute des changements ----- */
        panel.addEventListener('change', function (e) {
            const el = e.target;
            const name = el.name;

            if (name === 'wam-theme') prefs.theme = el.value;
            else if (el.id === 'a11y-no-graphical-fonts') prefs.noGraphicalFonts = el.checked;
            else if (el.id === 'a11y-font-choice') prefs.fontChoice = el.value;
            else if (name === 'wam-font-size') prefs.fontSize = el.value;
            else if (name === 'wam-line-height') prefs.lineHeight = el.value;
            else if (el.id === 'a11y-reduce-motion') prefs.reduceMotion = el.checked;

            savePrefs(prefs);
            applyPrefs(prefs);
        });

        /* ----- Réinitialisation ----- */
        resetBtn && resetBtn.addEventListener('click', function () {
            prefs = Object.assign({}, DEFAULTS);
            savePrefs(prefs);
            applyPrefs(prefs);
            syncUI();
        });

        /* ----- Sync initiale ----- */
        syncUI();

        /* ----- Mode icône au scroll ----- */
        const triggerWrap = document.getElementById('wam-a11y-trigger-wrap');
        if (triggerWrap) {
            const SCROLL_THRESHOLD = 80;
            const ICON_CLASS = 'wam-a11y-trigger-wrap--icon';

            function updateIconMode() {
                if (window.scrollY > SCROLL_THRESHOLD) {
                    triggerWrap.classList.add(ICON_CLASS);
                } else {
                    triggerWrap.classList.remove(ICON_CLASS);
                }
            }

            window.addEventListener('scroll', updateIconMode, { passive: true });
            updateIconMode();
        }
    });

})();
