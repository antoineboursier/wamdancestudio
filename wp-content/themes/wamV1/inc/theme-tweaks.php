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
 * Ajoute le slug de la page aux classes du body
 */
function wamv1_add_slug_body_class($classes) {
    global $post;
    if (isset($post) && is_page()) {
        $classes[] = 'page-slug-' . $post->post_name;
    }
    return $classes;
}
add_filter('body_class', 'wamv1_add_slug_body_class');
// -------------------------------------------------------
// llms.txt — Contenu dynamique pour les LLMs et robots
// -------------------------------------------------------

/**
 * Génère le contenu Markdown du fichier llms.txt dynamiquement.
 *
 * Toutes les données proviennent de WordPress / Config WAM :
 *   - URLs via home_url()
 *   - Adresse via wam_nom_lieu() / wam_adresse_lieu()
 *   - Réseaux sociaux via wam_url_instagram() / facebook() / tiktok() / etc.
 *
 * Aucun fichier statique n'est nécessaire.
 */
function wamv1_generate_llms_content(): string
{
    $base = rtrim(home_url('/'), '/');

    // --- Réseaux sociaux (depuis Config WAM) ---
    $socials = [];
    if (function_exists('wam_url_instagram') && wam_url_instagram()) {
        $socials[] = '- [Instagram](' . wam_url_instagram() . ')';
    }
    if (function_exists('wam_url_facebook') && wam_url_facebook()) {
        $socials[] = '- [Facebook](' . wam_url_facebook() . ')';
    }
    if (function_exists('wam_url_tiktok') && wam_url_tiktok()) {
        $socials[] = '- [TikTok](' . wam_url_tiktok() . ')';
    }
    if (function_exists('wam_url_linkedin') && wam_url_linkedin()) {
        $socials[] = '- [LinkedIn](' . wam_url_linkedin() . ')';
    }
    if (function_exists('wam_url_youtube') && wam_url_youtube()) {
        $socials[] = '- [YouTube](' . wam_url_youtube() . ')';
    }
    $socials_md = $socials ? implode("\n", $socials) : '_Aucun réseau configuré._';

    // --- Adresse (depuis Config WAM) ---
    $nom_lieu    = function_exists('wam_nom_lieu') ? wam_nom_lieu() : 'WAM Dance Studio';
    $adresse_raw = function_exists('wam_adresse_lieu') ? wam_adresse_lieu() : "202 rue Jean Jaurès\nVilleneuve d'Ascq";
    // Transforme les retours à la ligne en séparateur inline pour le format Markdown
    $adresse_md  = str_replace(["\r\n", "\r", "\n"], ', ', trim($adresse_raw));

    // --- Construction du document Markdown ---
    $md  = "# WAM Dance Studio\n\n";
    $md .= "> École de danse à Croix (59170), proche de Lille, Roubaix et Wasquehal. ";
    $md .= "WAM Dance Studio propose des cours collectifs, stages, ateliers et services événementiels ";
    $md .= "pour tous les niveaux et tous les âges (enfants, ados, adultes). ";
    $md .= "Styles pluriels : danses de salon, urbain, contemporain, K-Pop, Heels, Comédie musicale, et plus encore.\n\n";

    $md .= "## À propos\n\n";
    $md .= "WAM Dance Studio est une association de danse (loi 1901) basée à Croix (Nord, Hauts-de-France). ";
    $md .= "L'école accueille débutants et danseurs confirmés dans une ambiance bienveillante et inclusive. ";
    $md .= "L'équipe pédagogique est composée de professeurs diplômés d'État et de professionnels de la scène.\n\n";
    $md .= "**Établissement :** {$nom_lieu}  \n";
    $md .= "**Adresse :** {$adresse_md}  \n";
    $md .= "**Accès :** Bus ligne 32 (arrêt Pont du Breucq) · Tramway (arrêt Le Sart) · Métro (station Croix Centre)  \n";
    $md .= "**Proximité :** ~20 min de Lille · ~15 min de Roubaix · ~10 min de Wasquehal et Marcq-en-Barœul\n\n";

    $md .= "## Pages principales\n\n";
    $md .= "- [Accueil]({$base}/)\n";
    $md .= "- [Notre école de danse]({$base}/notre-ecole-de-danse/)\n";
    $md .= "- [Cours collectifs]({$base}/cours-collectifs/)\n";
    $md .= "- [Stages, Workshops & Ateliers]({$base}/stages-workshop-ateliers/)\n";
    $md .= "- [Cours particuliers]({$base}/cours-particuliers/)\n";
    $md .= "- [Nos professeur·es]({$base}/prof-wam/)\n";
    $md .= "- [Nos tarifs]({$base}/tarifs/)\n";
    $md .= "- [Événements chez WAM]({$base}/evenements/)\n";
    $md .= "- [Services spécialisés]({$base}/services/)\n";
    $md .= "- [Contact]({$base}/contact/)\n\n";

    $md .= "## Cours collectifs — Catégories\n\n";
    $md .= "### Danses à deux\n";
    $md .= "- Circuit Swing\n";
    $md .= "- Danse de salon\n\n";
    $md .= "### Solo — Urbain & Moderne\n";
    $md .= "- K-Pop\n";
    $md .= "- Street Jazz\n";
    $md .= "- Heels\n";
    $md .= "- Jazz solo\n";
    $md .= "- Danse moderne\n";
    $md .= "- Contemporain\n";
    $md .= "- Comédie musicale\n";
    $md .= "- Danse orientale\n";
    $md .= "- Danse de caractère\n";
    $md .= "- Dance Solo\n";
    $md .= "- Dance Workout\n\n";
    $md .= "### Enfants & Ados\n";
    $md .= "- Éveil danse (niveaux 1 & 2)\n";
    $md .= "- Initiation danse\n";
    $md .= "- Danse moderne Ados\n\n";

    $md .= "## Services événementiels et spéciaux\n\n";
    $md .= "- **Ouvertures de bal de mariage** : Chorégraphies personnalisées pour les mariés.\n";
    $md .= "- **EVJF & EVG** : Cours et expériences danse pour enterrements de vie de jeune fille/garçon.\n";
    $md .= "- **Team building** : Ateliers danse pour entreprises et groupes.\n";
    $md .= "- **Interventions scolaires** : Ateliers TAP/NAP en milieu scolaire.\n\n";

    $md .= "## Informations pratiques\n\n";
    $md .= "- Inscriptions en ligne disponibles sur le site.\n";
    $md .= "- Paiement en plusieurs fois accepté.\n";
    $md .= "- Tous niveaux acceptés, de débutant à confirmé.\n";
    $md .= "- Cours ouverts aux enfants dès le plus jeune âge (éveil danse).\n\n";

    $md .= "## Réseaux sociaux\n\n";
    $md .= $socials_md . "\n";

    return $md;
}

/**
 * Enregistre une réécriture WordPress pour /llms.txt → ?wam_llms=1
 * afin que la requête passe par PHP et non par un éventuel fichier statique.
 *
 * Endpoint REST API de secours : /wp-json/wam/v1/llms
 */
function wamv1_llms_rewrite_rule(): void
{
    add_rewrite_rule('^llms\\.txt$', 'index.php?wam_llms=1', 'top');
}
add_action('init', 'wamv1_llms_rewrite_rule');

/** Déclare la query var personnalisée à WordPress. */
function wamv1_llms_query_vars(array $vars): array
{
    $vars[] = 'wam_llms';
    return $vars;
}
add_filter('query_vars', 'wamv1_llms_query_vars');

/** Sert le contenu dynamique quand /llms.txt est demandé. */
function wamv1_llms_serve(): void
{
    if (!get_query_var('wam_llms')) return;

    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: public, max-age=3600'); // 1h (dynamique, donc cache plus court)
    header_remove('X-Pingback');
    echo wamv1_generate_llms_content();
    exit;
}
add_action('template_redirect', 'wamv1_llms_serve', 1);

/**
 * Endpoint REST API (fallback universel) : /wp-json/wam/v1/llms
 * Fonctionne même si la réécriture n'est pas active.
 */
function wamv1_register_llms_endpoint(): void
{
    register_rest_route('wam/v1', '/llms', [
        'methods'             => 'GET',
        'callback'            => 'wamv1_rest_llms_callback',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'wamv1_register_llms_endpoint');

function wamv1_rest_llms_callback(): WP_REST_Response
{
    return new WP_REST_Response(wamv1_generate_llms_content(), 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
}

/**
 * Mentionne /llms.txt dans le robots.txt généré par WordPress.
 * Permet aux robots et LLMs de découvrir le fichier automatiquement.
 */
function wamv1_robots_txt(string $output): string
{
    $llms_url = home_url('/llms.txt');
    $output  .= "\n# Fichier de contexte pour les LLMs (standard llmstxt.org)\n";
    $output  .= "# LLM-Context: {$llms_url}\n";
    return $output;
}
add_filter('robots_txt', 'wamv1_robots_txt');



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
            --wam-color-yellow: #FBD150; /* À mettre à jour si accent-yellow change dans theme.json */
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
            background-color: var(--wam-color-card-bg) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--wam-color-text) !important;
            border-radius: var(--wam-radius-sm);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            margin-bottom: 20px !important;
            padding: 16px !important;
        }

        .login .message a {
            color: var(--wam-color-yellow);
        }

        .wam-local-accounts {
            background: rgba(255, 220, 8, 0.1);
            border: 1px solid var(--wam-color-yellow);
            border-radius: var(--wam-radius-sm);
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--wam-color-text);
        }
        .wam-local-accounts strong { color: var(--wam-color-yellow); }
    </style>
    <?php
    // Affichage des comptes uniquement en local
    if ($_SERVER['HTTP_HOST'] === 'wam-v1.ddev.site') {
        echo '<div class="wam-local-accounts">';
        echo '<strong>Accès Tests (Local uniquement) :</strong><br>';
        echo 'Directrice : <code>test_directrice</code> / <code>wam_test_2024</code><br>';
        echo 'Professeur : <code>test_prof</code> / <code>wam_test_2024</code><br>';
        echo 'Admin technique : <code>test_admin_tech</code> / <code>wam_test_2024</code>';
        echo '</div>';
    }
}
add_action('login_message', 'wamv1_custom_login_assets');
// On remplace 'login_enqueue_scripts' par 'login_message' pour permettre l'affichage HTML au bon endroit.

/**
 * Sécurité anti-prod et création auto en local.
 */
function wamv1_manage_test_accounts() {
    $is_local = ($_SERVER['HTTP_HOST'] === 'wam-v1.ddev.site');
    $test_users = [
        [
            'login' => 'test_directrice',
            'email' => 'directrice@test.wam',
            'role'  => 'directrice',
            'pass'  => 'wam_test_2024'
        ],
        [
            'login' => 'test_prof',
            'email' => 'prof@test.wam',
            'role'  => 'professeur',
            'pass'  => 'wam_test_2024'
        ],
        [
            'login' => 'test_admin_tech',
            'email' => 'admin_tech@test.wam',
            'role'  => 'admin_technique',
            'pass'  => 'wam_test_2024'
        ]
    ];

    if ($is_local) {
        // En LOCAL : On s'assure que les comptes existent
        foreach ($test_users as $u) {
            if (!username_exists($u['login'])) {
                wp_insert_user([
                    'user_login' => $u['login'],
                    'user_email' => $u['email'],
                    'user_pass'  => $u['pass'],
                    'role'       => $u['role'],
                    'display_name' => ucfirst(str_replace('test_', '', $u['login'])) . ' Test'
                ]);
            }
        }
    } else {
        // En PROD (ou autre) : On supprime les comptes s'ils existent
        foreach ($test_users as $u) {
            $user = get_user_by('login', $u['login']);
            if ($user) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user($user->ID);
            }
        }
    }
}
add_action('init', 'wamv1_manage_test_accounts');



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

