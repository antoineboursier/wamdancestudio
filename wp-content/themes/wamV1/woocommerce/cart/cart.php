<?php
/**
 * Cart Page — Override WAM Dance Studio
 *
 * Remplace le tableau WC par une présentation "cartes de cours"
 * avec image du cours lié, nom du cours, créneau horaire et tarif.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package wamv1
 * @version 10.1.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart');
?>

<div class="wam-cart-wrapper">

    <?php do_action('woocommerce_before_cart_table'); ?>

    <form class="woocommerce-cart-form wam-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">

        <div class="wam-cart-layout">

            <!-- ========================================
                 Colonne principale : Liste des cours
                 ======================================== -->
            <div class="wam-cart-items-wrapper">
                <div class="wam-cart-items">

                    <?php do_action('woocommerce_before_cart_contents'); ?>

                    <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item):
                        $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                        $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                        $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);

                        if (!$_product || !$_product->exists() || $cart_item['quantity'] <= 0)
                            continue;
                        if (!apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key))
                            continue;

                        // — Récupérer les données WAM liées à cet article —
                        $course_id = $cart_item['wam_course_id'] ?? null;
                        $course_title = $course_id ? get_the_title($course_id) : null;
                        $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;
                        $course_thumb = $course_id ? get_the_post_thumbnail_url($course_id, 'medium') : null;

                        // On récupère les horaires directement depuis le cours (plus fiable que la session)
                        $wam_jour = null;
                        $wam_heure = null;

                        if ($course_id) {
                            $jour_slug = get_field('jour_de_cours', $course_id);
                            $wam_jour = function_exists('wamv1_get_day_label') ? wamv1_get_day_label($jour_slug) : $jour_slug;

                            $h_deb = get_field('heure_debut', $course_id);
                            $h_fin = get_field('heure_de_fin', $course_id);
                            if ($h_deb && $h_fin) {
                                $wam_heure = $h_deb . ' – ' . $h_fin;
                            } elseif ($h_deb) {
                                $wam_heure = $h_deb;
                            }
                        }


                        // Image du cours
                        $image_html = $course_thumb
                            ? '<img src="' . esc_url($course_thumb) . '" alt="' . esc_attr($course_title ?: $product_name) . '" width="120" height="120" loading="lazy">'
                            : null;

                        // URL du cours (retour vers la page)
                        $course_url = $course_id ? get_permalink($course_id) : null;

                        // Prix
                        $price_html = WC()->cart->get_product_price($_product);
                        ?>

                        <article
                            class="wam-cart-card <?php echo !$image_html ? 'wam-cart-card--no-thumb' : ''; ?> <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>"
                            data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">

                            <!-- Miniature du cours -->
                            <?php if ($image_html): ?>
                                <div class="wam-cart-card__thumbnail">
                                    <?php echo $image_html; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Infos principales -->
                            <div class="wam-cart-card__body">

                                <!-- Badge Adhésion (nom du produit WC) -->
                                <span class="wam-cart-card__badge text-xs">
                                    <?php echo esc_html($_product->get_name()); ?>
                                </span>

                                <!-- Noms du cours -->
                                <div class="wam-cart-card__title-row mt-2xs">
                                    <?php if ($course_title): ?>
                                        <?php if ($course_url): ?>
                                            <h2 class="wam-cart-card__title text-lg fw-bold">
                                                <a
                                                    href="<?php echo esc_url($course_url); ?>"><?php echo esc_html($course_title); ?></a>
                                            </h2>
                                        <?php else: ?>
                                            <h2 class="wam-cart-card__title text-lg fw-bold"><?php echo esc_html($course_title); ?>
                                            </h2>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <h2 class="wam-cart-card__title text-lg fw-bold"><?php echo esc_html($product_name); ?>
                                        </h2>
                                    <?php endif; ?>

                                    <div class="wam-cart-card__type-subtitle">
                                        <?php if ($course_subtitle): ?>
                                            <span
                                                class="wam-cart-card__subtitle text-md fw-bold d-block"><?php echo esc_html($course_subtitle); ?></span>
                                        <?php endif; ?>
                                        <?php 
                                        $wam_tarif_label = $cart_item['wam_tarif_label'] ?? null;
                                        $is_stage = $course_id && get_post_type($course_id) === 'stages';
                                        if ($wam_tarif_label && $is_stage): ?>
                                            <span class="wam-cart-card__tarif text-md color-text d-block mt-2"><?php echo esc_html($wam_tarif_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Créneau horaire -->
                                <?php if ($wam_jour || $wam_heure): ?>
                                    <div class="wam-cart-card__meta">
                                        <span class="wam-cart-card__meta-item">
                                            <span class="btn-icon" aria-hidden="true"
                                                style="--icon-url: url('<?php echo esc_url(get_template_directory_uri() . '/assets/images/calendar.svg'); ?>'); --icon-size: 16px; color: currentcolor;"></span>
                                            <span class="color-subtext text-sm">
                                                <?php
                                                $wam_date = $cart_item['wam_date'] ?? null;
                                                $is_stage = $course_id && get_post_type($course_id) === 'stages';
                                                
                                                if ($is_stage && $wam_date) {
                                                    echo esc_html($wam_date) . ' — ';
                                                }

                                                if ($wam_jour && $wam_heure) {
                                                    echo esc_html($wam_jour . ' — ' . $wam_heure);
                                                } else {
                                                    echo esc_html($wam_jour . $wam_heure);
                                                }
                                                ?>
                                            </span>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Description courte du produit -->
                                <?php $short_desc = $_product->get_short_description(); ?>
                                <?php if ($short_desc): ?>
                                    <p class="wam-cart-card__desc text-sm color-subtext mt-2xs">
                                        <?php echo wp_strip_all_tags($short_desc); ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Ligne bas de carte : quantité + actions -->
                                <div class="wam-cart-card__footer">
                                    <div class="wam-cart-card__qty product-quantity">
                                        <button type="button" class="wam-qty-btn minus" aria-label="<?php echo esc_attr( sprintf( __( 'Diminuer la quantité pour %s', 'wamv1' ), $course_title ?: $product_name ) ); ?>">-</button>
                                        <?php
                                        if ($_product->is_sold_individually()) {
                                            $min_quantity = 1;
                                            $max_quantity = 1;
                                        } else {
                                            $min_quantity = 0;
                                            $max_quantity = $_product->get_max_purchase_quantity();
                                        }

                                        $product_quantity = woocommerce_quantity_input(
                                            array(
                                                'input_name' => "cart[{$cart_item_key}][qty]",
                                                'input_value' => $cart_item['quantity'],
                                                'max_value' => $max_quantity,
                                                'min_value' => $min_quantity,
                                                'product_name' => $product_name,
                                            ),
                                            $_product,
                                            false
                                        );

                                        echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item);
                                        ?>
                                        <button type="button" class="wam-qty-btn plus" aria-label="<?php echo esc_attr( sprintf( __( 'Augmenter la quantité pour %s', 'wamv1' ), $course_title ?: $product_name ) ); ?>">+</button>
                                    </div>
                                    <div class="wam-cart-card__price">
                                        <span class="wam-cart-card__price-label title-norm-sm color-yellow">
                                            <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?>
                                        </span>
                                    </div>
                                </div>
                            </div><!-- /.wam-cart-card__body -->
                        </article>

                    <?php endforeach; ?>

                    <?php do_action('woocommerce_cart_contents'); ?>

                    <div class="wam-cart-update-actions d-none">
                        <button type="submit" class="button btn-secondary wam-cart-update-btn" name="update_cart"
                            value="<?php esc_attr_e('Mettre à jour le panier', 'woocommerce'); ?>"><?php esc_html_e('Mettre à jour le panier', 'woocommerce'); ?></button>
                    </div>

                    <!-- Champs cachés WC obligatoires -->
                    <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>

                    <?php do_action('woocommerce_after_cart_contents'); ?>

                </div><!-- /.wam-cart-items -->

                <p class="wam-cart-helloasso-note mt-lg text-sm color-subtext">
                    💡 Grâce à <span class="fw-bold color-text">Hello Asso</span>, un règlement en 3X sans
                    frais est disponible.
                </p>
            </div>

            <!-- ========================================
                 Colonne latérale : Récapitulatif & CTA
                 ======================================== -->
            <aside class="wam-cart-summary">

                <?php do_action('woocommerce_before_cart_collaterals'); ?>

                <h2 class="wam-cart-summary__title"><?php _e('Récapitulatif', 'wamv1'); ?></h2>

                <?php do_action('woocommerce_cart_collaterals'); ?>

                <!-- Code promo -->
                <?php if (wc_coupons_enabled()): ?>
                    <details class="wam-cart-coupon wam-prose">
                        <summary class="wam-cart-coupon__label color-subtext"
                            style="cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; margin:0;">
                            <?php _e('Ajouter un code promo', 'wamv1'); ?>
                            <span aria-hidden="true">+</span>
                        </summary>
                        <div class="wam-cart-coupon__row mt-sm">
                            <input type="text" name="coupon_code" id="coupon_code" class="wam-cart-coupon__input" value=""
                                placeholder="<?php esc_attr_e('Entrer un code', 'wamv1'); ?>" />
                            <button type="submit" name="apply_coupon"
                                value="<?php esc_attr_e('Appliquer', 'woocommerce'); ?>"
                                class="btn-secondary wam-cart-coupon__btn">
                                <?php _e('Appliquer', 'wamv1'); ?>
                            </button>
                        </div>
                        <?php do_action('woocommerce_cart_coupon'); ?>
                    </details>
                <?php endif; ?>

                <?php do_action('woocommerce_after_cart'); ?>

            </aside><!-- /.wam-cart-summary -->

        </div><!-- /.wam-cart-layout -->

    </form>

</div><!-- /.wam-cart-wrapper -->

<!-- Auto-Update Script for WooCommerce Quantities -->
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // 1. Empêcher WooCommerce de scroller en haut de page brutalement à chaque mise à jour
        if (typeof jQuery !== 'undefined') {
            // On écrase la fonction native de WooCommerce servant à scroller vers les messages
            jQuery.scroll_to_notices = function () {
                // Ne rien faire pour rester à la même position
            };
        }

        // 2. Gestion des Toasts (Croix de fermeture & Accessibilité)
        function initWAMToasts() {
            const notices = document.querySelectorAll('.woocommerce-message, .woocommerce-info, .woocommerce-error');
            const closeIconUrl = '<?php echo esc_url(get_template_directory_uri() . "/assets/images/close.svg"); ?>';

            notices.forEach(notice => {
                // Éviter les doublons
                if (notice.querySelector('.wam-toast-close')) return;

                // Amélioration Accessibilité
                if (!notice.getAttribute('role')) {
                    notice.setAttribute('role', notice.classList.contains('woocommerce-error') ? 'alert' : 'status');
                }
                notice.setAttribute('aria-live', notice.classList.contains('woocommerce-error') ? 'assertive' : 'polite');

                // Création du bouton close
                const closeBtn = document.createElement('button');
                closeBtn.className = 'wam-toast-close';
                closeBtn.type = 'button';
                closeBtn.setAttribute('aria-label', 'Fermer la notification');
                closeBtn.innerHTML = `<span class="btn-icon" style="--icon-url: url('${closeIconUrl}'); color: currentColor;"></span>`;

                closeBtn.addEventListener('click', function() {
                    notice.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    notice.style.opacity = '0';
                    notice.style.transform = 'translateX(20px)';
                    setTimeout(() => notice.remove(), 400);
                });

                notice.appendChild(closeBtn);
            });
        }

        // Init au chargement
        initWAMToasts();

        // Ré-init après mise à jour du panier (AJAX)
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('updated_cart_totals applied_coupon removed_coupon', function() {
                initWAMToasts();
            });
        }

        // 3. Auto-Update panier sur changement de quantité et boutons +/-
        let timeout;

        // Fonctionnalité clics +/- sur la page
        document.body.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('wam-qty-btn')) {
                let container = e.target.closest('.wam-cart-card__qty');
                if (container) {
                    let input = container.querySelector('input.qty');
                    if (input) {
                        let currentVal = parseFloat(input.value) || 0;
                        let max = parseFloat(input.getAttribute('max'));
                        let min = parseFloat(input.getAttribute('min'));
                        let step = parseFloat(input.getAttribute('step')) || 1;

                        if (e.target.classList.contains('plus')) {
                            if (!max || max === '' || currentVal < max) {
                                input.value = currentVal + step;
                            }
                        } else if (e.target.classList.contains('minus')) {
                            if (!min || min === '' || currentVal > min) {
                                input.value = currentVal - step;
                            }
                        }

                        // Déclenche l'événement "change" pour que le script d'auto-update suivant se lance
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
        });

        // Auto-update lors d'un "change" (clavier ou bouton)
        document.body.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('qty')) {
                if (e.target.closest('.wam-cart-card__qty')) { // Uniquement pour le change sur les quantités
                    clearTimeout(timeout);
                    timeout = setTimeout(function () {
                        let updateBtn = document.querySelector('[name="update_cart"]');
                        if (updateBtn) {
                            updateBtn.disabled = false;
                            updateBtn.click();
                        }
                    }, 600);
                }
            }
        });
    });
</script>