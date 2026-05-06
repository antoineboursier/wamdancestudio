/**
 * WAM — Add-to-cart AJAX pour les cours collectifs
 *
 * Intercepte le clic sur #btn-inscription-cours,
 * envoie une requête AJAX pour ajouter le produit WC lié au panier,
 * puis déclenche le refresh des fragments (animation icône panier).
 */
(function () {
    'use strict';

    function init() {
        if (document.body.classList.contains('wp-admin')) return;

        // 1. Boutons d'ajout direct (Single Tarif ou Cours)
        var directBtns = document.querySelectorAll('#btn-inscription-cours, .btn-inscription, .btn-stage-item');
        directBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (btn.classList.contains('btn-open-stage-modal')) return;

                e.preventDefault();
                handleAddToCart(btn, {
                    product_id: btn.dataset.productId,
                    course_id: btn.dataset.courseId || btn.dataset.stageId,
                    tarif_index: btn.dataset.tarifIndex
                });
            });
        });

        // 2. Gestion de la Modal Multi-Tarifs
        var modalOpenBtn = document.querySelector('.btn-open-stage-modal');
        var modal = document.getElementById('modal-booking-stage');
        
        if (modalOpenBtn && modal) {
            var confirmBtn = modal.querySelector('.btn-confirm-booking');
            var lastFocusedElement;

            function updateConfirmButtonState() {
                var totalQty = 0;
                modal.querySelectorAll('.wam-qty-value').forEach(function(valEl) {
                    totalQty += parseInt(valEl.textContent);
                });
                confirmBtn.disabled = (totalQty === 0);
            }

            function updateLiveFeedback(message) {
                var feedback = modal.querySelector('.wam-modal__live-feedback');
                if (feedback) {
                    feedback.textContent = message;
                }
            }

            function trapFocus(e) {
                var focusableEls = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                var firstFocusableEl = focusableEls[0];
                var lastFocusableEl = focusableEls[focusableEls.length - 1];

                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusableEl) {
                            lastFocusableEl.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastFocusableEl) {
                            firstFocusableEl.focus();
                            e.preventDefault();
                        }
                    }
                }
            }

            function handleEscape(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            }

            function openModal() {
                lastFocusedElement = document.activeElement;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                
                // Activer les particules
                var particleContainer = modal.querySelector('.js-nav-particles');
                if (particleContainer && window.wamCreateParticles) {
                    window.wamCreateParticles(particleContainer);
                }

                updateConfirmButtonState(); // Init state

                // Focus sur la croix de fermeture par défaut
                var closeBtn = modal.querySelector('.wam-modal__close');
                if (closeBtn) closeBtn.focus();

                document.addEventListener('keydown', trapFocus);
                document.addEventListener('keydown', handleEscape);
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';

                // Désactiver les particules
                var particleContainer = modal.querySelector('.js-nav-particles');
                if (particleContainer && window.wamCloseParticles) {
                    window.wamCloseParticles(particleContainer);
                }

                document.removeEventListener('keydown', trapFocus);
                document.removeEventListener('keydown', handleEscape);

                // Restituer le focus
                if (lastFocusedElement) {
                    lastFocusedElement.focus();
                }
            }

            modalOpenBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });

            // Fermeture
            modal.querySelectorAll('[data-close-modal]').forEach(function(el) {
                el.addEventListener('click', closeModal);
            });

            // Gestion des quantités +/-
            modal.querySelectorAll('.wam-tarif-selector-item').forEach(function(item) {
                var minus = item.querySelector('[data-qty-minus]');
                var plus = item.querySelector('[data-qty-plus]');
                var valEl = item.querySelector('.wam-qty-value');

                minus.addEventListener('click', function() {
                    var v = parseInt(valEl.textContent);
                    if (v > 0) valEl.textContent = v - 1;
                    updateConfirmButtonState();
                });
                plus.addEventListener('click', function() {
                    var v = parseInt(valEl.textContent);
                    valEl.textContent = v + 1;
                    updateConfirmButtonState();
                });
            });

            // Confirmation Modal
            confirmBtn.addEventListener('click', function() {
                var selections = [];
                modal.querySelectorAll('.wam-qty-value').forEach(function(valEl) {
                    var qty = parseInt(valEl.textContent);
                    if (qty > 0) {
                        selections.push({
                            tarif_index: valEl.dataset.tarifIndex,
                            qty: qty
                        });
                    }
                });

                if (selections.length === 0) {
                    updateLiveFeedback('Veuillez sélectionner au moins une place.');
                    return;
                }

                handleAddToCart(confirmBtn, {
                    product_id: confirmBtn.dataset.productId,
                    course_id: confirmBtn.dataset.stageId,
                    selections: selections
                }, function(res) {
                    // Mettre à jour le bouton principal sur la page
                    if (modalOpenBtn) {
                        setLabel(modalOpenBtn, res.data.message || 'Cours ajouté au panier');
                        modalOpenBtn.classList.add('is-added');
                    }

                    // Fermer la modal après succès
                    setTimeout(function() {
                        closeModal();
                    }, 1000);
                });
            });
        }
    }

    function handleAddToCart(btn, data, onSuccess) {
        if (btn.classList.contains('is-loading')) return;

        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');

        var body = new FormData();
        body.append('action', 'wam_add_to_cart');
        body.append('nonce', wamEnroll.nonce);
        body.append('product_id', data.product_id);
        
        if (data.course_id) body.append('course_id', data.course_id);
        if (data.tarif_index) body.append('tarif_index', data.tarif_index);
        
        if (data.selections) {
            data.selections.forEach(function(sel, i) {
                body.append('selections[' + i + '][tarif_index]', sel.tarif_index);
                body.append('selections[' + i + '][qty]', sel.qty);
            });
        }

        fetch(wamEnroll.ajaxurl, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    // Pour les boutons avec icône uniquement (réinscription), on évite d'ajouter du texte
                    if (!btn.classList.contains('card-cours__cta--reinscription')) {
                        setLabel(btn, res.data.message);
                    }
                    
                    // Mise à jour de TOUTES les pastilles de ce COURS sur la page
                    var courseId = data.course_id;
                    var allButtons = document.querySelectorAll('.btn-inscription[data-course-id="' + courseId + '"]');
                    
                    allButtons.forEach(function(otherBtn) {
                        var counter = otherBtn.querySelector('.card-cours__cart-count');
                        var addedQty = 1; 
                        if (data.selections) {
                            addedQty = data.selections.reduce(function(acc, sel) { return acc + sel.qty; }, 0);
                        }

                        if (counter) {
                            var currentQty = parseInt(counter.textContent) || 0;
                            var newQty = currentQty + addedQty;
                            counter.textContent = newQty;
                            counter.classList.remove('is-hidden');
                        }

                        otherBtn.classList.add('is-added');
                        
                        // Si c'est un bouton "Réinscription", on le désactive pour limiter à 1
                        if (otherBtn.classList.contains('card-cours__cta--reinscription')) {
                            otherBtn.classList.add('is-disabled');
                            otherBtn.disabled = true;
                            
                            // On change juste l'icône par un panier (on garde le format icône seule)
                            var btnIcon = otherBtn.querySelector('.btn-icon');
                            if (btnIcon) {
                                btnIcon.style.setProperty('--icon-url', 'url(\'' + wamEnroll.icons_url + 'panier.svg\')');
                            }
                        } else {
                            // Pour les autres boutons (ex: single cours), on peut mettre à jour le texte
                            var btnText = otherBtn.querySelector('.btn-text');
                            if (btnText) {
                                btnText.textContent = 'Déjà au panier';
                            }
                            var btnIcon = otherBtn.querySelector('.btn-icon');
                            if (btnIcon) {
                                btnIcon.style.setProperty('--icon-url', 'url(\'' + wamEnroll.icons_url + 'panier.svg\')');
                            }
                            otherBtn.classList.add('is-disabled');
                            otherBtn.disabled = true;
                        }
                    });

                    if (res.data.fragments) {
                        updateCartFragments(res.data.fragments, res.data.cart_hash);
                    }
                    if (onSuccess) onSuccess(res);
                } else {
                    setLabel(btn, res.data.message || 'Erreur');
                    btn.classList.add('is-error');
                }
            })
            .finally(function() {
                btn.classList.remove('is-loading');
                btn.removeAttribute('aria-busy');
            });
    }

    function updateCartFragments(fragments, cartHash) {
        Object.keys(fragments).forEach(function (selector) {
            var el = document.querySelector(selector);
            if (el) el.outerHTML = fragments[selector];
        });
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).trigger('added_to_cart', [fragments, cartHash, null]);
        }
    }

    function setLabel(el, text) {
        var icon = el.querySelector('.btn-icon');
        var counter = el.querySelector('.card-cours__cart-count');
        el.textContent = text + ' ';
        if (icon) el.appendChild(icon);
        if (counter) el.appendChild(counter);
    }

    // Protection double exécution si le script est chargé plusieurs fois
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
