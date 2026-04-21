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
        var btn = document.getElementById('btn-inscription-cours');
        if (!btn || !btn.dataset.productId) return;

        var productId = btn.dataset.productId;
        var courseId = btn.dataset.courseId || null;
        var busy = false;

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (busy) return;

            busy = true;
            btn.setAttribute('aria-busy', 'true');
            btn.classList.add('is-loading');

            var body = new FormData();
            body.append('action', 'wam_add_to_cart');
            body.append('nonce', wamEnroll.nonce);
            body.append('product_id', productId);
            if (courseId) {
                body.append('course_id', courseId);
            }

            fetch(wamEnroll.ajaxurl, { method: 'POST', body: body })
                .then(function (r) { 
                    if (!r.ok) throw new Error('Erreur réseau');
                    return r.json(); 
                })
                .then(function (res) {
                    if (res.success) {
                        // Feedback visuel
                        setLabel(btn, res.data.message);
                        btn.classList.add('is-added');

                        // Refresh fragments WC (animation icône panier)
                        if (res.data.fragments) {
                            Object.keys(res.data.fragments).forEach(function (selector) {
                                var el = document.querySelector(selector);
                                if (el) {
                                    el.outerHTML = res.data.fragments[selector];
                                }
                            });
                            // Déclencher l'événement standard de WC pour prévenir les autres scripts
                            if (typeof jQuery !== 'undefined') {
                                jQuery(document.body).trigger('added_to_cart', [res.data.fragments, res.data.cart_hash, null]);
                            }
                        }

                        // Désactiver le bouton
                        btn.disabled = true;
                        btn.classList.remove('is-loading');
                        btn.removeAttribute('aria-busy');
                    } else {
                        // Erreur (doublon ou autre)
                        setLabel(btn, res.data.message || 'Erreur');
                        btn.classList.add('is-error');
                        btn.disabled = true;
                        btn.classList.remove('is-loading');
                        btn.removeAttribute('aria-busy');
                    }
                })
                .catch(function (err) {
                    console.error('WAM Enroll Error:', err);
                    setLabel(btn, 'Erreur réseau');
                    btn.classList.remove('is-loading');
                    btn.removeAttribute('aria-busy');
                    busy = false;
                });
        });
    }

    function setLabel(el, text) {
        var icon = el.querySelector('.btn-icon');
        el.textContent = text + ' ';
        if (icon) el.appendChild(icon);
    }

    // Protection double exécution si le script est chargé plusieurs fois
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
