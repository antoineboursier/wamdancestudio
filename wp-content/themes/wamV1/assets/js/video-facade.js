/**
 * WAM Dance Studio — Façade vidéo YouTube (click-to-load)
 *
 * Source unique du comportement des `.wam-video-facade` produites par
 * wamv1_youtube_facade() (inc/performance.php). Auparavant ce comportement
 * était dupliqué : un <script> inline par vidéo intégrée, plus une copie dans
 * cours-carousel.js pour reconstruire la façade après un changement de
 * diapositive. Les deux copies pouvaient diverger en silence.
 *
 * Deux partis pris qui simplifient beaucoup :
 *   1. Délégation sur le document — une façade réinitialisée redevient
 *      cliquable sans qu'on ait à ré-attacher quoi que ce soit.
 *   2. L'iframe se SUPERPOSE à la miniature au lieu de la remplacer. La
 *      réinitialisation se réduit alors à supprimer l'iframe : plus aucun
 *      markup à reconstruire en JS, le HTML rendu par PHP fait toujours foi.
 *
 * API publique : window.wamVideoFacade.activate(el) / .reset(el)
 */
(function () {
    'use strict';

    var SELECTOR = '.wam-video-facade';

    // Le bouton Play porte `color` et le SVG est en `fill="currentColor"` :
    // une seule propriété à basculer au survol.
    var PLAY_IDLE = { background: 'rgba(0,0,0,0.7)', color: '#fff' };
    var PLAY_HOVER = { background: 'var(--wam-color-yellow)', color: '#000' };

    function paintPlayBtn(btn, state) {
        if (!btn) return;
        btn.style.background = state.background;
        btn.style.color = state.color;
    }

    function facadeOf(node) {
        return node && node.closest ? node.closest(SELECTOR) : null;
    }

    function playBtn(facade) {
        return facade.querySelector('.wam-video-play-btn');
    }

    /**
     * Lance la vidéo. L'iframe est ajoutée par-dessus la miniature, qui reste
     * en place (et déjà chargée) pour la réinitialisation.
     */
    function activate(facade) {
        if (!facade || facade.classList.contains('is-playing')) return;

        var id = facade.dataset.videoId;
        if (!id) return;

        var iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) +
                     '?autoplay=1&enablejsapi=1&rel=0';
        iframe.title = facade.dataset.videoTitle || 'Vidéo YouTube';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.style.cssText = 'position:absolute; top:0; left:0; width:100%; height:100%; border:0; border-radius:inherit; z-index:10;';

        var btn = playBtn(facade);
        if (btn) btn.hidden = true;

        // Pendant la lecture, l'enveloppe n'est plus un bouton : c'est le
        // lecteur YouTube qui reçoit le clavier et le pointeur.
        facade.classList.add('is-playing');
        facade.removeAttribute('role');
        facade.removeAttribute('tabindex');
        facade.removeAttribute('aria-label');

        facade.appendChild(iframe);
    }

    /**
     * Coupe la lecture et rend sa miniature à la façade.
     * Détruire l'iframe est le seul moyen fiable de couper le son d'un lecteur
     * cross-origin — postMessage n'aboutit pas si l'API n'a pas fini de charger.
     */
    function reset(facade) {
        if (!facade || !facade.classList.contains('is-playing')) return;

        var iframe = facade.querySelector('iframe');
        if (iframe) iframe.remove();

        facade.classList.remove('is-playing');

        var btn = playBtn(facade);
        if (btn) {
            btn.hidden = false;
            paintPlayBtn(btn, PLAY_IDLE);
        }

        var img = facade.querySelector('img');
        if (img) img.style.opacity = '0.8';

        facade.setAttribute('role', 'button');
        facade.setAttribute('tabindex', '0');
        if (facade.dataset.playLabel) {
            facade.setAttribute('aria-label', facade.dataset.playLabel);
        }
    }

    // — Activation : clic, et clavier (la façade est un <div role="button">)
    document.addEventListener('click', function (e) {
        activate(facadeOf(e.target));
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
        var facade = facadeOf(e.target);
        if (!facade || facade.classList.contains('is-playing')) return;
        e.preventDefault(); // évite le défilement sur Espace
        activate(facade);
    });

    // — Survol : mouseover/mouseout délèguent (contrairement à mouseenter),
    //   on ignore les mouvements internes à une même façade.
    function hover(entering) {
        return function (e) {
            var facade = facadeOf(e.target);
            if (!facade || facade === facadeOf(e.relatedTarget)) return;
            if (facade.classList.contains('is-playing')) return;

            var img = facade.querySelector('img');
            if (img) img.style.opacity = entering ? '1' : '0.8';

            paintPlayBtn(playBtn(facade), entering ? PLAY_HOVER : PLAY_IDLE);
        };
    }

    document.addEventListener('mouseover', hover(true));
    document.addEventListener('mouseout', hover(false));

    window.wamVideoFacade = { activate: activate, reset: reset };
})();
