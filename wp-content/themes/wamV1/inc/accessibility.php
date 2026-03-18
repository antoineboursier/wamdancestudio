<?php
/**
 * Module Accessibilité WAM
 * Shortcode : [wam_accessibility]
 *
 * Ce module fournit un panneau de personnalisation accessible via un bouton
 * flottant, injecting des classes CSS sur <html> sauvegardées en localStorage.
 *
 * Options proposées :
 * 1. Thème : Dark / Light
 * 2. Typographies graphiques → Outfit uniquement
 * 3. Police de substitution : par défaut / Comic Sans / Arial / Times New Roman
 * 4. Taille du texte : 100% / 120% / 150%
 * 5. Interlignage : par défaut / augmenté (+25%)
 * 6. Animations : activées / désactivées
 *
 * @package wamv1
 */

if (!function_exists('wamv1_enqueue_accessibility')):
    function wamv1_enqueue_accessibility()
    {
        $ver = wp_get_theme()->get('Version');
        wp_enqueue_style(
            'wamv1-accessibility',
            get_template_directory_uri() . '/assets/css/accessibility.css',
            array('wamv1-style'),
            $ver
        );
        wp_enqueue_script(
            'wamv1-accessibility',
            get_template_directory_uri() . '/assets/js/accessibility.js',
            array(),
            $ver,
            true // footer
        );
    }
endif;
// Toujours enqueuer — disponible sur toutes les pages
add_action('wp_enqueue_scripts', 'wamv1_enqueue_accessibility');

/**
 * Shortcode [wam_accessibility]
 * Peut être utilisé dans l'éditeur Gutenberg via le bloc "Shortcode".
 * N'a pas besoin d'enqueuer les assets (déjà fait via wp_enqueue_scripts).
 */
function wamv1_shortcode_accessibility($atts)
{
    $atts = shortcode_atts(array(
        'show_intro' => 'yes',
    ), $atts, 'wam_accessibility');

    ob_start();
    get_template_part('template-parts/accessibility-module');
    return ob_get_clean();
}
add_shortcode('wam_accessibility', 'wamv1_shortcode_accessibility');

/**
 * Injection automatique du panel dans le footer de toutes les pages.
 * (On évite d'injecter si le shortcode l'a déjà fait via une page dédiée,
 *  mais dans notre cas le panel est un overlay global, donc double-safe.)
 */
function wamv1_inject_accessibility_panel()
{
    get_template_part('template-parts/accessibility-module');
}
add_action('wp_footer', 'wamv1_inject_accessibility_panel', 99);
