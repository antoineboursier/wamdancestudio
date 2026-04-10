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
