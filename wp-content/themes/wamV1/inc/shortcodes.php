<?php
/**
 * Shortcodes WAM
 *
 * @package wamv1
 */

/**
 * Shortcode [wam_teachers]
 * Affiche la grille des professeur·es. Utilisable dans Gutenberg via le bloc Shortcode.
 */
function wamv1_shortcode_teachers($atts)
{
    $atts = shortcode_atts(array(
        'limit' => -1,
    ), $atts, 'wam_teachers');

    ob_start();
    get_template_part('template-parts/section', 'teachers');
    return ob_get_clean();
}
add_shortcode('wam_teachers', 'wamv1_shortcode_teachers');

/**
 * Shortcode [wam_contact_form]
 * Affiche le formulaire de contact AJAX stylisé.
 */
function wamv1_shortcode_contact_form($atts)
{
    // On peut passer un sujet par défaut si besoin : [wam_contact_form default_subject="Cours particuliers"]
    $atts = shortcode_atts(array(
        'default_subject' => ''
    ), $atts, 'wam_contact_form');

    // Charge dynamiquement le JS requis pour que le formulaire marche en AJAX
    wp_enqueue_script('wamv1-contact');

    ob_start();
    // On passe les variables au template via les args
    get_template_part('template-parts/shortcode', 'contact', $atts);
    return ob_get_clean();
}
add_shortcode('wam_contact_form', 'wamv1_shortcode_contact_form');

/**
 * Shortcode [wam_price ids="983, 984"]
 * Affiche une ou plusieurs cartes de tarif WooCommerce stylisées WAM.
 */
function wamv1_shortcode_price($atts)
{
    $atts = shortcode_atts(array(
        'ids' => '',
        'id'  => '', // Fallback pour compatibilité
    ), $atts, 'wam_price');

    $raw_ids = !empty($atts['ids']) ? $atts['ids'] : $atts['id'];
    if (empty($raw_ids)) return '';

    // Transformation de la chaîne en array d'IDs propres
    $ids = array_map('trim', explode(',', $raw_ids));
    
    ob_start();
    ?>
    <div class="wam-prices-wrapper">
        <?php foreach ($ids as $id) : 
            $product = function_exists('wc_get_product') ? wc_get_product($id) : null;
            if (!$product) continue;

            $title = $product->get_name();
            $price = $product->get_price_html();
            ?>
            <div class="wam-price-card">
                <div class="wam-price-card__content">
                    <h3 class="wam-price-card__title is-style-title-norm-md"><?php echo esc_html($title); ?></h3>
                    <div class="wam-price-card__price text-4xl color-subtext">
                        <?php echo $price; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wam_price', 'wamv1_shortcode_price');
