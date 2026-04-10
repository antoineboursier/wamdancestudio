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

/**
 * Force le thème "Moderne" par défaut pour les utilisateurs dans l'administration.
 */
function wamv1_default_admin_color($color_scheme)
{
    // Si la couleur est la valeur par défaut de WP ("fresh") ou vide, on retourne "modern"
    if ($color_scheme === 'fresh' || empty($color_scheme)) {
        return 'modern';
    }
    return $color_scheme;
}
add_filter('get_user_option_admin_color', 'wamv1_default_admin_color', 5);

/**
 * Personnalisation de la page de connexion wp-admin
 */
function wamv1_custom_login_assets()
{
    $bg_url = get_template_directory_uri() . '/assets/images/bg_admin.jpg';
    $logo_url = get_template_directory_uri() . '/assets/images/wam_logo_hero.svg';
    $font_url = get_template_directory_uri() . '/fonts/Outfit-VariableFont_wght.woff2';
    ?>
    <style type="text/css">
        @font-face {
            font-family: 'Outfit';
            src: url('<?php echo esc_url($font_url); ?>') format('woff2');
            font-weight: 100 900;
            font-style: normal;
            font-display: swap;
        }

        :root {
            /* Variables basées sur theme.json / TOKENS.md */
            --wam-color-card-bg: #1A1D28;
            --wam-color-input-bg: #232734;
            --wam-color-text: #F9F4EB;
            --wam-color-subtext: #E1CDAC;
            --wam-color-yellow: #FFDC08;
            --wam-color-page-bg: #131620;
            --wam-font-body: 'Outfit', sans-serif;
            --wam-radius-lg: 16px;
            --wam-radius-sm: 8px;
        }

        body.login {
            background-color: var(--wam-color-page-bg);
            background-image: url('<?php echo esc_url($bg_url); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            font-family: var(--wam-font-body);
            color: var(--wam-color-text);
        }

        body.login label {
            color: var(--wam-color-subtext);
        }

        body.login h1 a {
            background-image: url('<?php echo esc_url($logo_url); ?>');
            background-size: contain;
            width: 100%;
            height: 90px;
            max-width: 280px;
            margin: 56px auto;
        }

        #loginform,
        #registerform,
        #lostpasswordform {
            border-radius: var(--wam-radius-lg);
            box-shadow: 0 16px 64px rgba(6, 8, 14, 0.8);
            background: var(--wam-color-card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Inputs texte */

        .login form .input,
        .login input[type=text],
        .login input[type=password] {
            background: var(--wam-color-input-bg) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--wam-color-text);
            border-radius: var(--wam-radius-sm);
            font-family: var(--wam-font-body);
            font-size: 20px;
        }

        /* Checkbox se souvenir de moi */
        .login .forgetmenot label {
            color: var(--wam-color-subtext);
        }

        /* Bouton show/hide password */
        .wp-core-ui .button-secondary.wp-hide-pw {
            color: var(--wam-color-subtext);
        }

        body.login #backtoblog a,
        body.login #nav a {
            color: var(--wam-color-text);
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8);
            transition: color 0.2s ease;
        }

        body.login #backtoblog a:hover,
        body.login #nav a:hover,
        body.login h1 a:hover {
            color: var(--wam-color-yellow);
        }

        /* Bouton de soumission principal */
        .login .button-primary {
            background: var(--wam-color-yellow) !important;
            border-color: var(--wam-color-yellow) !important;
            color: var(--wam-color-page-bg) !important;
            font-weight: 700;
            font-family: var(--wam-font-body);
            text-shadow: none !important;
            box-shadow: none !important;
            transition: opacity 0.2s ease;
            border-radius: var(--wam-radius-sm);
            font-size: 16px;
            padding: 4px 24px;
        }

        .login .button-primary:hover {
            opacity: 0.9;
        }

        input[type=text]:focus,
        input[type=password]:focus,
        input[type=checkbox]:focus {
            border-color: var(--wam-color-yellow) !important;
            box-shadow: 0 0 0 1px var(--wam-color-yellow) !important;
            outline: none;
        }

        .login .message,
        .login .notice,
        .login .success {
            background: transparent;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'wamv1_custom_login_assets');

/**
 * Lien du logo wp-login redirigeant vers l'accueil du site
 */
function wamv1_custom_login_logo_url()
{
    return home_url();
}
add_filter('login_headerurl', 'wamv1_custom_login_logo_url');

/**
 * Bouton "Modifier" flottant pour les admins (bas à droite)
 * Permet un accès rapide au back-office depuis le front.
 */
function wamv1_admin_floating_edit()
{
    // Déterminer l'ID à éditer selon le contexte
    $post_id = get_the_ID();

    // Si on est sur la page des articles (Blog), on veut éditer la page parente
    if (is_home()) {
        $post_id = get_option('page_for_posts');
    }

    // Ne pas afficher si admin back-office, ou si pas les droits d'édition sur cet ID
    if (is_admin() || !current_user_can('edit_post', $post_id)) {
        return;
    }

    // Pas sur les pages sensibles WooCommerce
    if (function_exists('is_account_page') && (is_account_page() || is_cart() || is_checkout())) {
        return;
    }

    $edit_url = get_edit_post_link($post_id);
    if (!$edit_url) {
        return;
    }

    echo '<a href="' . esc_url($edit_url) . '" class="wam-floating-edit" aria-label="Modifier cette page">';
    echo '<span class="wam-floating-edit__icon" aria-hidden="true">&#9998;</span>';
    echo '<span class="wam-floating-edit__label">Modifier</span>';
    echo '</a>';
}
// add_action('wp_footer', 'wamv1_admin_floating_edit', 100); // Déplacé dans site-footer.php

