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
