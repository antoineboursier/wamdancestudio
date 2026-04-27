/**
 * Global JS — WAM Dance Studio
 * Handles menu toggle, navigation particles, and global interactions.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (document.body.classList.contains('wp-admin')) return;

    /* =====================================================
       1. NAV MENU TOGGLE
       ===================================================== */
    const menuToggle = document.querySelector('.js-menu-toggle');
    const menuClose = document.querySelector('.js-menu-close');
    const menuOverlay = document.querySelector('.js-menu-overlay');
    const body = document.body;
    
    if (menuToggle && menuOverlay) {
        const focusableElements = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
        
        const toggleMenuFocusable = (isOpening) => {
            const elements = menuOverlay.querySelectorAll(focusableElements);
            elements.forEach(el => {
                el.setAttribute('tabindex', isOpening ? '0' : '-1');
            });
        };

        const openMenu = () => {
            menuOverlay.classList.add('wam-nav-overlay--open');
            menuOverlay.setAttribute('aria-hidden', 'false');
            menuToggle.setAttribute('aria-expanded', 'true');
            body.style.overflow = 'hidden'; // Prevent scroll

            // Rendre les éléments du menu focusables
            toggleMenuFocusable(true);

            // Focus trap : rendre le contenu principal inerte
            const main = document.querySelector('#primary');
            if (main) main.setAttribute('inert', '');
            const footer = document.querySelector('.wam-footer');
            if (footer) footer.setAttribute('inert', '');

            const particlesContainer = menuOverlay.querySelector('.js-nav-particles');
            if (particlesContainer) window.wamCreateParticles(particlesContainer);

            // Prepare staggered entrance animations
            const animItems = menuOverlay.querySelectorAll('.wam-nav__header, .wam-nav__list > li, .wam-nav__socials');
            animItems.forEach((item, index) => {
                item.classList.add('wam-nav__anim-item');
                item.style.transitionDelay = `${100 + index * 60}ms`;
            });

            // Focus close button
            if (menuClose) setTimeout(() => menuClose.focus(), 100);
        };

        const closeMenu = () => {
            menuOverlay.classList.remove('wam-nav-overlay--open');
            menuOverlay.setAttribute('aria-hidden', 'true');
            menuToggle.setAttribute('aria-expanded', 'false');
            body.style.overflow = '';

            // Rendre les éléments du menu non focusables
            toggleMenuFocusable(false);

            // Retirer le focus trap
            const main = document.querySelector('#primary');
            if (main) main.removeAttribute('inert');
            const footer = document.querySelector('.wam-footer');
            if (footer) footer.removeAttribute('inert');

            const particlesContainer = menuOverlay.querySelector('.js-nav-particles');
            if (particlesContainer) window.wamCloseParticles(particlesContainer);

            menuToggle.focus();
        };

        // État initial (sécurité si le JS charge tard)
        toggleMenuFocusable(false);

        menuToggle.addEventListener('click', openMenu);
        if (menuClose) menuClose.addEventListener('click', closeMenu);

        // Close on Esc key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && menuOverlay.classList.contains('wam-nav-overlay--open')) {
                closeMenu();
            }
        });

        // Close on click outside (on the overlay background)
        menuOverlay.addEventListener('click', (e) => {
            if (e.target === menuOverlay) {
                closeMenu();
            }
        });
    }

    /* =====================================================
       2. NAV MENU ACCORDION (SUB-MENUS)
       ===================================================== */
    const parentItems = document.querySelectorAll('.wam-nav__list .menu-item-has-children');
    parentItems.forEach(item => {
        const link = item.querySelector('a');

        // Create wrapper so we can push link and button side-by-side
        const wrapper = document.createElement('div');
        wrapper.className = 'wam-nav-item-wrap';

        // Wrap link inside the wrapper
        item.insertBefore(wrapper, link);
        wrapper.appendChild(link);

        // Create the big chevron toggle button
        const btn = document.createElement('button');
        btn.className = 'wam-nav__chevron-btn';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Développer le sous-menu');

        // Create the actual icon using our shared .btn-icon class
        const chevronUrl = menuOverlay ? menuOverlay.dataset.chevronUrl : '';
        const icon = document.createElement('span');
        icon.className = 'btn-icon w-3.5 h-3.5';
        icon.style.setProperty('--icon-url', `url('${chevronUrl}')`);
        btn.appendChild(icon);

        wrapper.appendChild(btn);

        // Toggle logic
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const isExpanded = item.classList.contains('is-expanded');

            // Close all others at same level (optional accordion style)
            const siblings = item.parentElement.querySelectorAll(':scope > .menu-item-has-children');
            siblings.forEach(sibling => {
                sibling.classList.remove('is-expanded');
                sibling.querySelector('.wam-nav__chevron-btn')?.setAttribute('aria-expanded', 'false');
            });

            if (!isExpanded) {
                item.classList.add('is-expanded');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    /* =====================================================
       3. PARTICLES — Wave effect
       ===================================================== */
    window.wamCreateParticles = function(container) {
        if (!container) return;

        const particleCount = 44;
        const waveDuration = 500; 
        const colors = [
            'var(--wam-color-green)',
            'var(--wam-color-yellow)',
            'var(--wam-color-orange)',
            'var(--wam-color-pink)'
        ];

        for (let i = 0; i < particleCount; i++) {
            const xFraction = Math.random();
            const dur = 3 + Math.random() * 5;

            const p = document.createElement('div');
            p.className = 'keywords-particle';
            p.style.width = `${Math.random() * 4 + 2}px`;
            p.style.height = p.style.width;
            p.style.left = xFraction * 100 + '%';
            p.style.top = Math.random() * 100 + '%';
            p.dataset.x = xFraction;

            const color = colors[Math.floor(Math.random() * colors.length)];
            p.style.backgroundColor = color;
            p.style.boxShadow = `0 0 10px ${color}`;

            p.style.setProperty('--dur', `${dur}s`);
            p.style.setProperty('--delay', `${-Math.random() * dur * 0.4}s`);
            p.style.setProperty('--drift', `${(Math.random() - 0.5) * 100}px`);

            setTimeout(() => container.appendChild(p), xFraction * waveDuration);
        }
    };

    window.wamCloseParticles = function(container) {
        if (!container) return;

        const waveDuration = 0.4; 
        const fadeDuration = 300; 

        container.querySelectorAll('.keywords-particle').forEach(p => {
            const x = parseFloat(p.dataset.x ?? Math.random());
            p.style.setProperty('--close-delay', `${(1 - x) * waveDuration}s`);
            p.classList.add('keywords-particle--closing');
        });

        setTimeout(() => {
            container.innerHTML = '';
        }, (waveDuration * 1000) + fadeDuration);
    };

    /* =====================================================
       4. FOOTER ACCORDION (mobile only)
       Categories become collapsible buttons on narrow screens.
       ===================================================== */
    if (window.matchMedia('(max-width: 800px)').matches) {
        document.querySelectorAll('.js-footer-accordion').forEach(list => {
            const chevronUrl = list.dataset.chevronUrl || '';

            list.querySelectorAll('.wam-footer__category-title-li').forEach(catLi => {
                // Collect following siblings until the next category heading
                const courseLis = [];
                let next = catLi.nextElementSibling;
                while (next && !next.classList.contains('wam-footer__category-title-li')) {
                    courseLis.push(next);
                    next = next.nextElementSibling;
                }
                if (!courseLis.length) return;

                const label = catLi.textContent.trim();
                catLi.removeAttribute('role'); // No longer a static heading
                catLi.innerHTML = '';

                const btn = document.createElement('button');
                btn.className = 'wam-footer__category-btn';
                btn.setAttribute('type', 'button');
                btn.setAttribute('aria-expanded', 'false');
                btn.textContent = label;

                const icon = document.createElement('span');
                icon.className = 'btn-icon w-3.5 h-3.5';
                icon.setAttribute('aria-hidden', 'true');
                icon.style.setProperty('--icon-url', `url('${chevronUrl}')`);
                btn.appendChild(icon);
                catLi.appendChild(btn);

                // Start collapsed
                courseLis.forEach(li => { li.hidden = true; });

                btn.addEventListener('click', () => {
                    const isExpanded = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', String(!isExpanded));
                    catLi.classList.toggle('is-expanded', !isExpanded);
                    courseLis.forEach(li => { li.hidden = isExpanded; });
                });
            });
        });
    }

    /* =====================================================
       5. STAGE DATES TOGGLE
       ===================================================== */
    const toggleDatesBtn = document.getElementById('toggle-dates-list');
    const closeDatesBtn = document.getElementById('close-dates-list');
    const datesList = document.getElementById('dates-list');

    if (toggleDatesBtn && datesList) {
        toggleDatesBtn.addEventListener('click', () => {
            const isHidden = datesList.hasAttribute('hidden');
            if (isHidden) {
                datesList.removeAttribute('hidden');
                toggleDatesBtn.setAttribute('aria-expanded', 'true');
            } else {
                datesList.setAttribute('hidden', '');
                toggleDatesBtn.setAttribute('aria-expanded', 'false');
            }
        });

        if (closeDatesBtn) {
            closeDatesBtn.addEventListener('click', () => {
                datesList.setAttribute('hidden', '');
                toggleDatesBtn.setAttribute('aria-expanded', 'false');
                toggleDatesBtn.focus();
            });
        }
    }

    /* =====================================================
       6. SCROLL HEADER — Hide on scroll down, show on scroll up (mobile)
       ===================================================== */
    if (window.matchMedia('(max-width: 800px)').matches) {
        const header = document.querySelector('.wam-header');
        if (header) {
            let lastScrollY = window.scrollY;
            window.addEventListener('scroll', () => {
                // Pendant un scroll programmatique du planning, ne pas réafficher le header
                if (header.dataset.planningScroll) return;

                const currentScrollY = window.scrollY;
                // Ne masquer qu'après 80px (évite le masquage au moindre drag en haut de page)
                if (currentScrollY > lastScrollY && currentScrollY > 80) {
                    header.classList.add('header--hidden');
                } else {
                    header.classList.remove('header--hidden');
                }
                lastScrollY = currentScrollY;
            }, { passive: true });
        }
    }

});
