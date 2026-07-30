/**
 * WAM Dance Studio — Carrousel média fiche cours
 * - Défilement des médias (photo à la une + vidéos ACF 16:9 / Shorts 9:16)
 * - Navigation tactile (swipe), flèches, puces et clavier
 * - Coupe la lecture des diapositives quittées
 *
 * La lecture des façades YouTube n'est PAS gérée ici : elle est centralisée
 * dans video-facade.js (window.wamVideoFacade), qui sert aussi les vidéos
 * intégrées hors carrousel. Ce fichier ne fait que demander la coupure.
 */

document.addEventListener('DOMContentLoaded', function () {
    var carousels = document.querySelectorAll('.wam-carousel');
    if (!carousels.length) return;

    carousels.forEach(function (carousel) {
        var track = carousel.querySelector('.wam-carousel__track');
        var slides = Array.prototype.slice.call(carousel.querySelectorAll('.wam-carousel__slide'));
        var prevBtn = carousel.querySelector('.wam-carousel__nav--prev');
        var nextBtn = carousel.querySelector('.wam-carousel__nav--next');
        var dotsNav = carousel.querySelector('.wam-carousel__dots');
        var dots = dotsNav ? Array.prototype.slice.call(dotsNav.querySelectorAll('.wam-carousel__dot')) : [];

        if (slides.length <= 1) return; // Média unique : rien à faire défiler

        var currentIndex = 0;
        var total = slides.length;

        /**
         * Coupe tout ce qui joue dans une diapositive.
         */
        function pauseSlideMedia(slide) {
            if (!slide) return;

            // Façades YouTube : l'API partagée détruit l'iframe, ce qui coupe le son.
            if (window.wamVideoFacade) {
                slide.querySelectorAll('.wam-video-facade.is-playing').forEach(window.wamVideoFacade.reset);
            }

            // Lecteurs hors façade (Vimeo notamment) : demande de pause, puis
            // rechargement SEULEMENT si la vidéo avait été lancée en autoplay.
            // Réécrire `src` inconditionnellement rechargeait le lecteur à chaque
            // changement de diapositive, même sans lecture.
            slide.querySelectorAll('iframe').forEach(function (iframe) {
                try {
                    iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'pauseVideo', args: '' }), '*');
                    iframe.contentWindow.postMessage(JSON.stringify({ method: 'pause' }), '*');
                } catch (e) { /* lecteur cross-origin non prêt : sans conséquence */ }

                var src = iframe.getAttribute('src') || '';
                if (src.indexOf('autoplay=1') !== -1) {
                    iframe.setAttribute('src', src.replace('autoplay=1', 'autoplay=0'));
                }
            });

            slide.querySelectorAll('video').forEach(function (v) {
                v.pause();
                v.currentTime = 0;
            });
        }

        /**
         * @param {number}  index
         * @param {boolean} moveFocus  Déplace le focus sur la puce sélectionnée.
         *                             Réservé au clavier : au swipe ou au clic,
         *                             voler le focus ferait sauter la page.
         */
        function goToSlide(index, moveFocus) {
            if (index < 0) index = total - 1;
            if (index >= total) index = 0;

            currentIndex = index;

            slides.forEach(function (slide, i) {
                var isActive = (i === currentIndex);

                if (!isActive) pauseSlideMedia(slide);

                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));

                // `inert` retire les diapositives hors champ du focus et de
                // l'arbre d'accessibilité. Nécessaire depuis que les façades
                // sont focusables : sans lui, la tabulation atteindrait des
                // vidéos invisibles et ferait défiler la piste.
                if (isActive) {
                    slide.removeAttribute('inert');
                } else {
                    slide.setAttribute('inert', '');
                }
            });

            track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

            dots.forEach(function (dot, i) {
                var isActive = (i === currentIndex);
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-selected', String(isActive));
                dot.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            if (moveFocus && dots[currentIndex]) dots[currentIndex].focus();
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function (e) {
                e.preventDefault();
                goToSlide(currentIndex - 1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                goToSlide(currentIndex + 1);
            });
        }

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function (e) {
                e.preventDefault();
                goToSlide(i);
            });
        });

        carousel.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                goToSlide(currentIndex - 1, true);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                goToSlide(currentIndex + 1, true);
            } else if (e.key === 'Home') {
                e.preventDefault();
                goToSlide(0, true);
            } else if (e.key === 'End') {
                e.preventDefault();
                goToSlide(total - 1, true);
            }
        });

        // — Navigation tactile
        var startX = 0;
        var startY = 0;
        var diffX = 0;
        var diffY = 0;
        var isSwiping = false;

        var touchContainer = carousel.querySelector('.wam-carousel__track-container') || carousel;

        touchContainer.addEventListener('touchstart', function (e) {
            if (e.touches.length > 1) {
                isSwiping = false;
                return;
            }

            // Une touche sur le lecteur lui-même ne doit pas déclencher de swipe :
            // les commandes YouTube doivent rester utilisables. Le test porte sur
            // la CIBLE, pas sur la diapositive entière — sinon, sur un Short qui
            // n'occupe que la moitié du cadre, les bandes latérales devenaient
            // mortes et il n'y avait plus aucun moyen de sortir de la vidéo au doigt.
            if (e.target && e.target.closest && e.target.closest('.wam-video-facade.is-playing, iframe, video')) {
                isSwiping = false;
                return;
            }

            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            diffX = 0;
            diffY = 0;
            isSwiping = true;
        }, { passive: true });

        touchContainer.addEventListener('touchmove', function (e) {
            if (!isSwiping) return;
            diffX = e.touches[0].clientX - startX;
            diffY = e.touches[0].clientY - startY;

            // Geste franchement horizontal : on garde la main et on empêche le
            // défilement vertical de la page. Sinon on laisse passer.
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 10) {
                if (e.cancelable) e.preventDefault();
            }
        }, { passive: false });

        touchContainer.addEventListener('touchend', function () {
            if (!isSwiping) return;
            isSwiping = false;

            var threshold = 40; // px minimum pour déclencher un changement
            if (Math.abs(diffX) > threshold && Math.abs(diffX) > Math.abs(diffY)) {
                goToSlide(diffX < 0 ? currentIndex + 1 : currentIndex - 1);
            }

            diffX = 0;
            diffY = 0;
        });

        goToSlide(0);
    });
});
