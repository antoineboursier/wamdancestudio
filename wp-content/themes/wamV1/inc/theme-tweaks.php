<?php
/**
 * Theme Tweaks & Global Filters
 * 
 * Regroupe les optimisations et comportements globaux qui ne font pas partie 
 * des réglages optionnels d'accessibilité.
 *
 * @package wamv1
 */

/**
 * Détection automatique des liens externes
 * Ajoute la classe .is-external et un texte pour les lecteurs d'écran.
 */
function wamv1_mark_external_links($content)
{
    if (is_admin() || empty($content)) {
        return $content;
    }

    $home_url = home_url();
    $home_host = wp_parse_url($home_url, PHP_URL_HOST);

    // Regex pour trouver les balises <a>
    return preg_replace_callback('/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>(.*?)<\/a>/is', function ($matches) use ($home_host) {
        $full_open_tag = $matches[1];
        $url = $matches[2];
        $link_text = $matches[3];

        $url_host = wp_parse_url($url, PHP_URL_HOST);

        // Si l'hôte existe et est différent de l'hôte du site, c'est externe
        if ($url_host && $url_host !== $home_host) {
            // Ajouter la classe is-external
            if (strpos($full_open_tag, 'class=') !== false) {
                // On ajoute l'espace au début si une classe existe déjà
                $full_open_tag = preg_replace('/class=(["\'])(.*?)\1/', 'class=$1$2 is-external$1', $full_open_tag);
            } else {
                $full_open_tag .= ' class="is-external"';
            }

            // Ajouter la notion d'accessibilité (sr-only)
            // Texte masqué pour les lecteurs d'écran
            $accessibility_text = '<span class="sr-only"> (ouvre un nouveau lien externe)</span>';
            
            // On vérifie si le texte ne contient pas déjà cette notion (évite les doublons si le filtre tourne x fois)
            if (strpos($link_text, 'sr-only') === false) {
                $link_text .= $accessibility_text;
            }
        }

        return '<a ' . $full_open_tag . '>' . $link_text . '</a>';
    }, $content);
}

// On applique le filtre sur le contenu et les widgets
add_filter('the_content', 'wamv1_mark_external_links', 20);
add_filter('widget_text', 'wamv1_mark_external_links', 20);
add_filter('the_excerpt', 'wamv1_mark_external_links', 20);

/**
 * Marquage des liens dans les menus WordPress
 */
function wamv1_mark_menu_external_links($atts, $item, $args)
{
    $home_url = home_url();
    $home_host = wp_parse_url($home_url, PHP_URL_HOST);
    $url = !empty($atts['href']) ? $atts['href'] : '';
    $url_host = wp_parse_url($url, PHP_URL_HOST);

    // Si l'hôte existe et est différent de l'hôte du site, c'est externe
    if ($url_host && $url_host !== $home_host) {
        $atts['class'] = !empty($atts['class']) ? $atts['class'] . ' is-external' : 'is-external';
        
        // Note: Ajouter le span sr-only dans un menu est plus complexe car on n'a pas accès 
        // directement au titre du lien ici via $atts. On peut utiliser le filtre nav_menu_item_title.
    }

    return $atts;
}
add_filter('nav_menu_link_attributes', 'wamv1_mark_menu_external_links', 10, 3);

/**
 * Ajout de la notion d'accessibilité aux titres des menus externes
 */
function wamv1_mark_menu_item_title($title, $item, $args, $depth)
{
    $home_url = home_url();
    $home_host = wp_parse_url($home_url, PHP_URL_HOST);
    $url_host = wp_parse_url($item->url, PHP_URL_HOST);

    if ($url_host && $url_host !== $home_host) {
        $accessibility_text = '<span class="sr-only"> (ouvre un nouveau lien externe)</span>';
        if (strpos($title, 'sr-only') === false) {
            $title .= $accessibility_text;
        }
    }

    return $title;
}
add_filter('nav_menu_item_title', 'wamv1_mark_menu_item_title', 10, 4);
