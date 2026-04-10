<?php
/**
 * Nettoyage et optimisation de WordPress
 * Retire les scripts additionnels et balises meta inutiles injectés par défaut par le CMS.
 */

// -------------------------------------------------------
// 1. Désactivation des Emojis natifs (car les OS les gèrent très bien)
// -------------------------------------------------------
function wamv1_disable_emojis() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' ); 
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); 
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    add_filter( 'tiny_mce_plugins', 'wamv1_disable_emojis_tinymce' );
    add_filter( 'wp_resource_hints', 'wamv1_disable_emojis_remove_dns_prefetch', 10, 2 );
}
add_action( 'init', 'wamv1_disable_emojis' );

function wamv1_disable_emojis_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
        return array_diff( $plugins, array( 'wpemoji' ) );
    } else {
        return array();
    }
}

function wamv1_disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
    if ( 'dns-prefetch' == $relation_type ) {
        $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
        $urls = array_diff( $urls, array( $emoji_svg_url ) );
    }
    return $urls;
}

// -------------------------------------------------------
// 2. Nettoyage de la balise <head> (Sécurité & Perf)
// -------------------------------------------------------
function wamv1_clean_head() {
    // Retire la balise Meta de version WP (sécurité)
    remove_action('wp_head', 'wp_generator');
    
    // Retire le lien RSD (utilisé pour les anciens clients de blogging type Windows Live Writer)
    remove_action('wp_head', 'rsd_link');
    
    // Retire le lien Windows Live Writer Manifest
    remove_action('wp_head', 'wlwmanifest_link');

    // Retire l'url courte générée pour la page <link rel="shortlink">
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);
    
    // Retire les liens du flux RSS principal et des commentaires si non utilisés
    // remove_action('wp_head', 'feed_links', 2);
    // remove_action('wp_head', 'feed_links_extra', 3);

    // Retire l'API REST dans le head de chaque page
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
}
add_action('init', 'wamv1_clean_head');

// -------------------------------------------------------
// 3. Désactiver Dashicons sur le frontend (utilisé que par l'admin bar)
// -------------------------------------------------------
function wamv1_dequeue_dashicons() {
    if ( !is_user_logged_in() ) {
        wp_deregister_style( 'dashicons' );
    }
}
add_action( 'wp_enqueue_scripts', 'wamv1_dequeue_dashicons' );
