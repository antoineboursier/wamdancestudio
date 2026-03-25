/**
 * Global JS — WAM Dance Studio
 * Handles menu toggle, navigation particles, and global interactions.
 */
document.addEventListener('DOMContentLoaded', () => {

    /* =====================================================
       1. NAV MENU TOGGLE
       ===================================================== */
    const menuToggle = document.querySelector('.js-menu-toggle');
    const menuClose = document.querySelector('.js-menu-close');
    const menuOverlay = document.querySelector('.js-menu-overlay');
    const body = document.body;

    if (menuToggle && menuOverlay) {

        const openMenu = () => {
            menuOverlay.classList.add('wam-nav-overlay--open');
            menuOverlay.setAttribute('aria-hidden', 'false');
            menuToggle.setAttribute('aria-expanded', 'true');
            body.style.overflow = 'hidden'; // Prevent scroll
            createNavParticles();

            // Prepare staggered entrance animations
            const animItems = menuOverlay.querySelectorAll('.wam-nav__header, .wam-nav__list > li, .wam-nav__socials');
            animItems.forEach((item, index) => {
                item.classList.add('wam-nav__anim-item');
                item.style.transitionDelay = `${100 + index * 60}ms`;
            });

            // Trap focus or just focus close button
            if (menuClose) setTimeout(() => menuClose.focus(), 100);
        };

        const closeMenu = () => {
            menuOverlay.classList.remove('wam-nav-overlay--open');
            menuOverlay.setAttribute('aria-hidden', 'true');
            menuToggle.setAttribute('aria-expanded', 'false');
            body.style.overflow = '';

            // Clean up particles
            const container = document.querySelector('.js-nav-particles');
            if (container) container.innerHTML = '';

            menuToggle.focus();
        };

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
       3. NAV PARTICLES (Creative Idea)
       ===================================================== */
    function createNavParticles() {
        const container = document.querySelector('.js-nav-particles');
        if (!container) return;

        const particleCount = 44;
        const colors = [
            'var(--wam-color-green)',
            'var(--wam-color-yellow)',
            'var(--wam-color-orange)',
            'var(--wam-color-pink)'
        ];

        for (let i = 0; i < particleCount; i++) {
            const p = document.createElement('div');
            p.className = 'keywords-particle'; // Reuse the particle style from home.css

            const size = Math.random() * 4 + 2;
            p.style.width = `${size}px`;
            p.style.height = `${size}px`;

            p.style.left = Math.random() * 100 + '%';
            p.style.top = Math.random() * 100 + '%';

            const color = colors[Math.floor(Math.random() * colors.length)];
            p.style.backgroundColor = color;
            p.style.boxShadow = `0 0 10px ${color}`;

            const dur = 3 + Math.random() * 5;
            const delay = -Math.random() * dur;
            const drift = (Math.random() - 0.5) * 100;

            p.style.setProperty('--dur', `${dur}s`);
            p.style.setProperty('--delay', `${delay}s`);
            p.style.setProperty('--drift', `${drift}px`);

            container.appendChild(p);
        }
    }

    /* =====================================================
       4. STAGE DATES TOGGLE
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
});
