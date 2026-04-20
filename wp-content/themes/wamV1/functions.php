<?php
/**
 * Functions and definitions
 *
 * @package wamv1
 */

// -------------------------------------------------------
// Includes
// -------------------------------------------------------
require_once get_template_directory() . '/inc/roles.php';
require_once get_template_directory() . '/inc/admin-ui.php';
require_once get_template_directory() . '/inc/admin-config.php';
require_once get_template_directory() . '/inc/schema.php';
require_once get_template_directory() . '/inc/shortcodes.php';
require_once get_template_directory() . '/inc/no-comments.php';
require_once get_template_directory() . '/inc/accessibility.php';
require_once get_template_directory() . '/inc/theme-tweaks.php';
require_once get_template_directory() . '/inc/nav-walker.php';
require_once get_template_directory() . '/inc/cleanup.php';
require_once get_template_directory() . '/inc/smtp-config.php';
require_once get_template_directory() . '/inc/contact-form-handler.php';

// -------------------------------------------------------
// Setup
// -------------------------------------------------------
if (!function_exists('wamv1_setup')):
    function wamv1_setup()
    {
        /* Déclaration du textdomain pour la traduction (i18n) */
        load_theme_textdomain('wamv1', get_template_directory() . '/languages');

        /* Largeur maximale du contenu (oEmbeds, images) */
        global $content_width;
        if (!isset($content_width)) {
            $content_width = 1200; // Aligné sur max-width éditorial dans Tokens CSS
        }

        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_image_size('wamv1-page-hero', 1536, 600, true); // banner pages (landscape)
        add_image_size('wam-page-thumbnail', 1248, 400, true); // header listing pages (Retina x2)
        add_image_size('wam-card-thumbnail', 466, 370, true); // thumbnail cours card (x2 for Retina)
        add_image_size('wam-hero', 1536, 800, true); // héros single plein écran
        add_image_size('wam-card', 1600, 1200, true); // card media & colonne hero (Retina x2)
        add_image_size('wam-stage-card', 810, 1172, true);   // x2 Retina (405x586 Figma)
        add_image_size('wam-stage-portrait', 1204, 1704, false); // A4 portrait x2 Retina pour écrans densités (602x852 @2x) - Pas de recadrage forcé
        add_image_size('wam-portrait', 960, 1280, true); // photo profil portrait (Retina x2)
        add_image_size('wam-thumb', 800, 600, true); // miniature vignette compacte (Retina x2)
        add_image_size('wam-event-card', 810, 486, true); // card event paysage (Retina x2 de 405×243)
        add_theme_support('editor-styles');
        add_theme_support('html5', array(
            'search-form',
            'gallery',
            'caption',
            'style',
            'script',
        ));

        // Menu de navigation principal
        register_nav_menus(array(
            'primary' => __('Menu principal', 'wamv1'),
            'footer' => __('Menu footer', 'wamv1'),
        ));

        // Support WooCommerce
        add_theme_support('woocommerce');
        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');

        // Editor styles — back office fidèle au front
        // Google Fonts doit être passé en premier pour que les polices soient dispo
        add_editor_style(array(
            'https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap',
            'assets/css/tokens.css',
            'assets/css/base.css',
            'assets/css/prose-shared.css',
            'assets/css/editor.css',
        ));
    }
endif;
add_action('after_setup_theme', 'wamv1_setup');

// -------------------------------------------------------
// Injection de la classe wam-prose sur le body de l'éditeur Gutenberg
// Permet à prose-shared.css d'être actif dans le back via un seul fichier CSS
// -------------------------------------------------------
add_filter('block_editor_settings_all', function ($settings) {
    $settings['bodyClassName'] = trim(($settings['bodyClassName'] ?? '') . ' wam-prose');
    return $settings;
});
add_filter('mce_body_class', function ($classes) {
    return $classes . ' wam-prose';
});

// -------------------------------------------------------
// Styles de display custom (Gutenberg Block Styles)
// régièrent les variantes typographiques WAM dans l'éditeur
// -------------------------------------------------------
function wamv1_register_text_styles()
{
    // Title Norm — Outfit Bold
    register_block_style('core/paragraph', ['name' => 'title-norm-lg', 'label' => 'Title Norm LG']);
    register_block_style('core/paragraph', ['name' => 'title-norm-md', 'label' => 'Title Norm MD']);
    register_block_style('core/paragraph', ['name' => 'title-norm-sm', 'label' => 'Title Norm SM']);

    // Title Cool — Cholo Rhita
    register_block_style('core/paragraph', ['name' => 'title-cool-lg', 'label' => 'Title Cool LG']);
    register_block_style('core/paragraph', ['name' => 'title-cool-md', 'label' => 'Title Cool MD']);

    // Title Sign — Mallia
    register_block_style('core/paragraph', ['name' => 'title-sign-lg', 'label' => 'Title Sign LG']);
    register_block_style('core/paragraph', ['name' => 'title-sign-md', 'label' => 'Title Sign MD']);
    register_block_style('core/paragraph', ['name' => 'title-sign-sm', 'label' => 'Title Sign SM']);

    // Sur core/heading aussi, pour les cas où on veut un display différent
    register_block_style('core/heading', ['name' => 'title-cool-lg', 'label' => 'Title Cool LG']);
    register_block_style('core/heading', ['name' => 'title-cool-md', 'label' => 'Title Cool MD']);
    register_block_style('core/heading', ['name' => 'title-sign-lg', 'label' => 'Title Sign LG']);
    register_block_style('core/heading', ['name' => 'title-sign-md', 'label' => 'Title Sign MD']);
    register_block_style('core/heading', ['name' => 'title-sign-sm', 'label' => 'Title Sign SM']);
}
add_action('init', 'wamv1_register_text_styles');

// -------------------------------------------------------
function wamv1_scripts()
{
    // On utilise filemtime pour forcer le rafraîchissement du cache (cache-busting)
    $ver = filemtime(get_template_directory() . '/style.css');
    $css = get_template_directory_uri() . '/assets/css/';
    $js = get_template_directory_uri() . '/assets/js/';

    // -------------------------------------------------------
    // 1. style.css — requis WordPress (header thème)
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-style', get_stylesheet_uri(), array(), $ver);

    // -------------------------------------------------------
    // 2. tokens.css — variables CSS WP (--wp--preset--*)
    //    Nécessaire pour les composants PHP qui utilisent
    //    les CSS vars directement (bg-pattern, border, etc.)
    //    TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-tokens', $css . 'tokens.css', array('wamv1-style'), $ver);

    // -------------------------------------------------------
    // 2b. base.css — Typographie globale, has-*, is-style-*
    //     Doit être chargé après tokens (dépend des variables)
    //     et avant main.css pour que main puisse surcharger
    //     TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-base', $css . 'base.css', array('wamv1-tokens'), $ver);

    // -------------------------------------------------------
    // 4. components.css — Composants réutilisables vanilla CSS
    //    Boutons, header, footer, nav overlay, cards, etc.
    //    Chargé après base.css
    //    TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-components', $css . 'components.css', array('wamv1-base'), $ver);

    // -------------------------------------------------------
    // 5. layout.css — Structure globale vanilla CSS
    //    site-main, sections, grilles réutilisables
    //    TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-layout', $css . 'layout.css', array('wamv1-components'), $ver);

    // -------------------------------------------------------
    // accessibility.css — module accessibilité global
    // TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-accessibility', $css . 'accessibility.css', array('wamv1-layout'), $ver);

    // -------------------------------------------------------
    // forms.css — champs formulaires (Fluent Forms, MailPoet, natifs)
    // TOUTES LES PAGES (formulaires peuvent apparaître partout)
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-forms', $css . 'forms.css', array('wamv1-accessibility'), $ver);

    // -------------------------------------------------------
    // prose-shared.css — Source unique des styles de contenu éditorial
    // Chargé après base.css, TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-prose', $css . 'prose-shared.css', array('wamv1-base'), $ver);

    // -------------------------------------------------------
    // Page d'accueil uniquement
    // -------------------------------------------------------
    if (is_front_page()) {
        wp_enqueue_style('wamv1-home', $css . 'home.css', array('wamv1-layout'), $ver);
    }

    // -------------------------------------------------------
    // Programme (cours, stages, events) — singles + pages listing + planning
    // -------------------------------------------------------
    if (
        is_singular('cours') ||
        is_singular('stages') ||
        is_singular('evenements') ||
        is_page_template('page-cours-collectifs.php') ||
        is_page_template('page-stages-tous.php') ||
        is_page_template('page-events-tous.php') ||
        is_page_template('page-prof-wam.php') ||
        is_page_template('page-planning-cours.php')
    ) {
        wp_enqueue_style('wamv1-programme', $css . 'programme.css', array('wamv1-accessibility'), $ver);
    }

    // JS filtrage — pages listing (cours collectifs + stages)
    if (is_page_template('page-cours-collectifs.php') || is_page_template('page-stages-tous.php')) {
        wp_enqueue_script('wamv1-filter', $js . 'filter.js', array(), $ver, true);
    }

    // CSS + JS planning — page planning uniquement (dépend de programme.css)
    if (is_page_template('page-planning-cours.php')) {
        wp_enqueue_style('wamv1-planning', $css . 'planning.css', array('wamv1-programme'), $ver);
        wp_enqueue_script('wamv1-planning', $js . 'planning.js', array(), $ver, true);
    }

    // -------------------------------------------------------
    // Events — CSS spécifique (dépend de programme.css)
    // -------------------------------------------------------
    if (
        is_singular('evenements') ||
        is_page_template('page-events-tous.php')
    ) {
        wp_enqueue_style('wamv1-events', $css . 'events.css', array('wamv1-programme'), $ver);
    }

    // -------------------------------------------------------
    // WooCommerce — shop.css
    // -------------------------------------------------------
    if (class_exists('WooCommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) {
        wp_enqueue_style('wamv1-shop', $css . 'shop.css', array('wamv1-layout'), $ver);
    }

    // -------------------------------------------------------
    // Scripts JS (defer pour ne pas bloquer le rendu)
    // -------------------------------------------------------
    wp_enqueue_script('wamv1-main', $js . 'main.js', array(), $ver, ['in_footer' => true, 'strategy' => 'defer']);

    if (is_front_page()) {
        wp_enqueue_script(
            'wamv1-home',
            $js . 'home.js',
            array(),
            $ver,
            ['in_footer' => true, 'strategy' => 'defer']
        );
    }

    // Scripts chargés à la demande
    $contact_ver = file_exists(get_template_directory() . '/assets/js/contact.js') ? filemtime(get_template_directory() . '/assets/js/contact.js') : $ver;
    wp_register_script('wamv1-contact', $js . 'contact.js', array(), $contact_ver, ['in_footer' => true, 'strategy' => 'defer']);
    wp_localize_script('wamv1-contact', 'wamParams', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'wamv1_scripts');

// -------------------------------------------------------
// Resource Hints — preload de la police Outfit (critique FCP)
// -------------------------------------------------------
function wamv1_preload_fonts()
{
    $font_url = get_template_directory_uri() . '/fonts/Outfit-VariableFont_wght.woff2';
    echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}
add_action('wp_head', 'wamv1_preload_fonts', 1);

// -------------------------------------------------------
// Scripts & Styles Admin WP
// -------------------------------------------------------
function wamv1_admin_scripts()
{
    $ver = wp_get_theme()->get('Version');
    wp_enqueue_style('wamv1-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), $ver);
}
add_action('admin_enqueue_scripts', 'wamv1_admin_scripts');



// -------------------------------------------------------
// Champ meta "Spécialité" pour les utilisateurs professeurs
// -------------------------------------------------------
function wamv1_add_user_specialty_field($user)
{
    if (!current_user_can('edit_user', $user->ID))
        return;
    $specialty = get_user_meta($user->ID, 'wam_specialite', true);
    ?>
    <h3><?php esc_html_e('Profil WAM', 'wamv1'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="wam_specialite"><?php esc_html_e('Spécialité (style de danse)', 'wamv1'); ?></label></th>
            <td>
                <input type="text" name="wam_specialite" id="wam_specialite" value="<?php echo esc_attr($specialty); ?>"
                    class="regular-text">
                <p class="description"><?php esc_html_e('Ex : Moderne, Contemporain, Hip-Hop…', 'wamv1'); ?></p>
            </td>
        </tr>
    </table>
    <?php
    wp_nonce_field('wamv1_save_specialty_' . $user->ID, 'wamv1_specialty_nonce');
}
add_action('show_user_profile', 'wamv1_add_user_specialty_field');
add_action('edit_user_profile', 'wamv1_add_user_specialty_field');

function wamv1_save_user_specialty_field($user_id)
{
    if (!current_user_can('edit_user', $user_id))
        return;
    if (!isset($_POST['wamv1_specialty_nonce']) || !wp_verify_nonce($_POST['wamv1_specialty_nonce'], 'wamv1_save_specialty_' . $user_id))
        return;
    if (isset($_POST['wam_specialite'])) {
        update_user_meta($user_id, 'wam_specialite', sanitize_text_field($_POST['wam_specialite']));
    }
}
add_action('personal_options_update', 'wamv1_save_user_specialty_field');
add_action('edit_user_profile_update', 'wamv1_save_user_specialty_field');

// =============================================================================
// CPT : COURS
// =============================================================================

function wamv1_register_cpt_cours()
{
    $labels = array(
        'name' => __('Les cours', 'wamv1'),
        'singular_name' => __('Cours', 'wamv1'),
        'menu_name' => __('Les cours', 'wamv1'),
        'name_admin_bar' => __('Ajouter un cours', 'wamv1'),
        'all_items' => __('Voir tous les cours', 'wamv1'),
        'add_new' => __('Ajouter un cours', 'wamv1'),
        'add_new_item' => __('Ajouter un cours', 'wamv1'),
        'edit_item' => __('Modifier ce cours', 'wamv1'),
        'not_found' => __('Aucun cours trouvé', 'wamv1'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'revisions', 'author'),
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-welcome-learn-more',
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'rewrite' => array('slug' => 'cours'),
    );

    register_post_type('cours', $args);
}
add_action('init', 'wamv1_register_cpt_cours');


// =============================================================================
// CPT : STAGES
// =============================================================================

function wamv1_register_cpt_stages()
{
    $labels = array(
        'name' => __('Les stages', 'wamv1'),
        'singular_name' => __('Stage', 'wamv1'),
        'menu_name' => __('Les stages', 'wamv1'),
        'name_admin_bar' => __('Ajouter un stage', 'wamv1'),
        'all_items' => __('Voir tous les stages', 'wamv1'),
        'add_new' => __('Ajouter un stage', 'wamv1'),
        'add_new_item' => __('Ajouter un stage', 'wamv1'),
        'edit_item' => __('Modifier ce stage', 'wamv1'),
        'not_found' => __('Aucun stage trouvé', 'wamv1'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'thumbnail', 'author'),
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
        'menu_position' => 6,
        'menu_icon' => 'dashicons-awards',
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'rewrite' => array('slug' => 'stages'),
    );

    register_post_type('stages', $args);
}
add_action('init', 'wamv1_register_cpt_stages');



// CPT events géré via ACF → Post Types (interface back-office)
// Ne pas déclarer ici pour éviter le conflit de double registration.

function wamv1_disable_gutenberg_cours($use_block_editor, $post_type)
{
    if (in_array($post_type, array('cours', 'stages', 'events'))) {
        return false;
    }
    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'wamv1_disable_gutenberg_cours', 10, 2);


// -------------------------------------------------------
// Performances - Génération d'images AVIF par défaut
// -------------------------------------------------------

function wamv1_register_cpt_membre()
{
    $labels = array(
        'name' => __('Profs', 'wamv1'),
        'singular_name' => __('Prof', 'wamv1'),
        'menu_name' => __('Équipe', 'wamv1'),
        'add_new' => __('Ajouter', 'wamv1'),
        'add_new_item' => __('Ajouter un.e prof', 'wamv1'),
        'edit_item' => __('Modifier le.a prof', 'wamv1'),
        'view_item' => __('Voir le.a prof', 'wamv1'),
        'all_items' => __('Tous les profs', 'wamv1'),
        'not_found' => __('Aucun.e prof trouvé.e', 'wamv1'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true,
        'supports' => array(
            'title',
            'thumbnail',  // Photo de profil
            'author',     // <-- Indispensable pour modifier l'auteur
        ),
        'menu_icon' => 'dashicons-groups',
        'has_archive' => false,
        'rewrite' => array('slug' => 'equipe'),
        'capability_type' => 'post',
    );

    register_post_type('wam_membre', $args);
}
add_action('init', 'wamv1_register_cpt_membre');

// =============================================================================
// CPT : ÉVÈNEMENTS
// =============================================================================

function wamv1_register_cpt_evenements()
{
    $labels = array(
        'name' => __('Les évènements', 'wamv1'),
        'singular_name' => __('Évènement', 'wamv1'),
        'menu_name' => __('Évènements', 'wamv1'),
        'add_new' => __('Ajouter', 'wamv1'),
        'add_new_item' => __('Ajouter un évènement', 'wamv1'),
        'edit_item' => __('Modifier l\'évènement', 'wamv1'),
        'view_item' => __('Voir l\'évènement', 'wamv1'),
        'all_items' => __('Tous les évènements', 'wamv1'),
        'not_found' => __('Aucun évènement trouvé', 'wamv1'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true, // Gutenberg active
        'supports' => array(
            'title',
            'editor',      // Texte libre
            'thumbnail',   // Image en avant
            'excerpt',     // Courte description
            'revisions',   // Historique
        ),
        'menu_icon' => 'dashicons-calendar-alt',
        'has_archive' => true,
        'rewrite' => array('slug' => 'evenements'),
        'capability_type' => 'post',
        // --- Modèle de blocs par défaut ---
        'template' => array(
            array('core/paragraph', array(
                'placeholder' => 'Commencez à rédiger la description détaillée de l\'événement ici...',
            )),
        ),
        // Optionnel : on peut décommenter la ligne suivante pour verrouiller l'ordre
        // 'template_lock' => 'all',
    );

    register_post_type('evenements', $args);
}
add_action('init', 'wamv1_register_cpt_evenements');

// -------------------------------------------------------
// Performances - Génération d'images AVIF par défaut
// -------------------------------------------------------
function wamv1_output_image_formats($formats)
{
    // Si le serveur supporte la compression AVIF (PHP 8.1+, GD/Imagick récent)
    if (function_exists('wp_image_editor_supports') && wp_image_editor_supports(array('mime_type' => 'image/avif'))) {
        $formats['image/jpeg'] = 'image/avif';
        $formats['image/webp'] = 'image/avif';
        // On permet de garder la transparence PNG, mais on convertit aussi en AVIF.
        $formats['image/png'] = 'image/avif';
    }
    return $formats;
}
// Transforme les recadrages et miniatures générés par WordPress (ex: tailles par défaut)
add_filter('image_editor_output_format', 'wamv1_output_image_formats');

// -------------------------------------------------------
// Styles éditeur Gutenberg : titre selon post_type
// -------------------------------------------------------
function wamv1_block_editor_title_styles()
{
    global $post;
    if (!$post)
        return;

    if ($post->post_type === 'post') {
        // Articles : titre Outfit Bold (identique au front single.php)
        $css = '
            .editor-styles-wrapper h1,
            .editor-styles-wrapper .wp-block-post-title,
            .editor-post-title__input {
                font-family: var(--wp--preset--font-family--outfit) !important;
                font-weight: 700 !important;
                line-height: 1.1 !important;
            }
        ';
        wp_add_inline_style('wp-edit-blocks', $css);
    }
}
add_action('enqueue_block_editor_assets', 'wamv1_block_editor_title_styles');

// -------------------------------------------------------
// Utilitaires de contenu
// -------------------------------------------------------

/**
 * Retourne le label français d'une valeur ACF "jour_de_cours" ("01day"…"07day").
 * Utilisé dans card-cours.php, single-cours.php, single-wam_membre.php,
 * page-planning-cours.php, card-article.php.
 *
 * @param string $value   Valeur ACF ("01day", "02day"…).
 * @param bool   $short   false = nom complet (Lundi), true = abrégé (Lun).
 * @return string
 */
if (!function_exists('wamv1_get_day_label')):
    function wamv1_get_day_label(?string $value, bool $short = false): string
    {
        $map_long = [
            '01day' => 'Lundi',
            '02day' => 'Mardi',
            '03day' => 'Mercredi',
            '04day' => 'Jeudi',
            '05day' => 'Vendredi',
            '06day' => 'Samedi',
            '07day' => 'Dimanche',
        ];
        $map_short = [
            '01day' => 'Lun',
            '02day' => 'Mar',
            '03day' => 'Mer',
            '04day' => 'Jeu',
            '05day' => 'Ven',
            '06day' => 'Sam',
            '07day' => 'Dim',
        ];
        $map = $short ? $map_short : $map_long;
        return $map[$value] ?? ($value ?? '');
    }
endif;

/**
 * Vérifie si le post courant (ou $post_id) appartient à la variante "Enfant"
 * (terme slug "enfants" dans la taxonomie cat_cours).
 * Utilisé dans card-cours.php, card-stage.php, single-cours.php,
 * single-stages.php, page-planning-cours.php.
 *
 * @param int $post_id 0 = post courant de la boucle.
 * @return bool
 */
if (!function_exists('wamv1_is_enfant_variant')):
    function wamv1_is_enfant_variant(int $post_id = 0): bool
    {
        return has_term('enfants', 'cat_cours', $post_id ?: null);
    }
endif;

if (!function_exists('wamv1_get_reading_time')):
    /**
     * Calcule le temps de lecture estimé d'un contenu.
     * Basé sur une moyenne de 250 mots par minute.
     *
     * @param string $post_content Le contenu de l'article ou de la page.
     * @return string Texte formaté avec le temps de lecture.
     */
    function wamv1_get_reading_time($post_content = '')
    {
        $word_count = str_word_count(strip_tags(strip_shortcodes($post_content)));

        // S'il n'y a littéralement aucun mot (page vide / cours expérimental vide)
        if ($word_count === 0) {
            return '';
        }

        $reading_time = ceil($word_count / 250);
        if ($reading_time < 1) {
            $reading_time = 1;
        }
        return $reading_time . ' min de lecture';
    }
endif;

// =============================================================================
// SYSTÈME IMAGES WAM
// =============================================================================

/**
 * Affiche l'overlay de blend "systématique" préconisé dans le README.
 * Utilise le mode mix-blend-mode: lighten pour uniformiser les visuels du site.
 * 
 * @param string $classes Classes CSS additionnelles pour l'overlay.
 * @return void
 */
if (!function_exists('wamv1_the_photo_overlay')):
    function wamv1_the_photo_overlay($classes = '')
    {
        echo '<div class="photo-overlay ' . esc_attr($classes) . '" aria-hidden="true"></div>';
    }
endif;

/**
 * Encapsule une image (via ID d'attachment) dans son wrapper avec l'overlay blend.
 * Utilise les préconisations du README (position:relative, mix-blend-mode:lighten).
 * 
 * @param int $attachment_id ID de l'image WordPress.
 * @param string $size Taille de l'image (par défaut 'large').
 * @param string $wrapper_classes Classes CSS pour le conteneur .photo-wrapper.
 * @param array $attr Attributs HTML pour la balise <img>.
 * @return string HTML complet : wrapper + image + overlay.
 */
if (!function_exists('wamv1_get_image_with_overlay')):
    function wamv1_get_image_with_overlay($attachment_id, $size = 'large', $wrapper_classes = '', $attr = [])
    {
        if (!$attachment_id)
            return '';

        // On ajoute un flag pour éviter que le filtre auto ne s'applique une deuxième fois
        $attr['data-has-overlay'] = 'true';

        $img_html = wp_get_attachment_image($attachment_id, $size, false, $attr);
        if (!$img_html)
            return '';

        $output = '<div class="photo-wrapper ' . esc_attr($wrapper_classes) . '">';
        $output .= $img_html;
        $output .= '<div class="photo-overlay" aria-hidden="true"></div>';
        $output .= '</div>';

        return $output;
    }
endif;

/**
 * Automatisation de l'overlay sur TOUTES les images issues de la librairie (hors SVG)
 * Cela couvre the_post_thumbnail(), wp_get_attachment_image(), etc.
 */
add_filter('wp_get_attachment_image', 'wamv1_auto_blend_overlay', 10, 5);
function wamv1_auto_blend_overlay($html, $attachment_id, $size, $icon, $attr)
{
    if (empty($html) || is_admin())
        return $html;

    // Skip SVG
    $mime = get_post_mime_type($attachment_id);
    if ($mime === 'image/svg+xml')
        return $html;

    // Skip si déjà traité ou si explicitement désactivé
    if (isset($attr['data-has-overlay']) || isset($attr['data-no-overlay']))
        return $html;
    if (strpos($html, 'photo-overlay') !== false)
        return $html;

    return '<div class="photo-wrapper">' . $html . '<div class="photo-overlay" aria-hidden="true"></div></div>';
}



/**
 * Masquer la barre d'administration pour les non-administrateurs sur le front-end.
 * Permet de garder un design épuré pour les clients et membres tout en laissant
 * l'accès rapide aux outils WP pour les admins.
 */
add_filter('show_admin_bar', function ($show) {
    return current_user_can('manage_options') ? $show : false;
});

// =============================================================================
// GENERATION LLM.TXT
// =============================================================================

require_once get_template_directory() . '/inc/llms-txt.php';

// =============================================================================
// WOOCOMMERCE — Hooks et intégration
// =============================================================================

require_once get_template_directory() . '/inc/woocommerce.php';

// =============================================================================
// YOAST SEO : CORRECTION DU FIL D'ARIANE DES CPTs
// =============================================================================

add_filter('wpseo_breadcrumb_links', 'wamv1_corriger_fil_ariane_yoast');
function wamv1_corriger_fil_ariane_yoast($links) {
    // Règle manuelle pour les Cours
    if (is_singular('cours')) {
        $links[1]['url']  = home_url('/cours-collectifs/'); // Modifiez si le slug de votre page Cours est différent
        $links[1]['text'] = 'Cours collectifs';
    }
    // Règle manuelle pour les Professeurs
    elseif (is_singular('wam_membre')) {
        $breadcrumb_equipe = array(
            'url'  => home_url('/prof-wam/'),
            'text' => 'Notre Équipe'
        );
        // Comme le CPT prof n'a pas d'archive, le titre est en position 1. 
        // On "insère" la page équipe en position 1 sans écraser la position du prof.
        array_splice($links, 1, 0, array($breadcrumb_equipe));
    }
    // Règle manuelle pour les Stages
    elseif (is_singular('stages')) {
        $links[1]['url']  = home_url('/stages-workshop-ateliers/'); 
        $links[1]['text'] = 'Les stages';
    }
    // Règle manuelle pour les Évènements
    elseif (is_singular('evenements')) {
        $links[1]['url']  = home_url('/les-evenements-au-studio/'); 
        $links[1]['text'] = 'Évènements';
    }
    return $links;
}

// =============================================================================
// ADMIN : Favicon spécifique pour le Back-Office
// =============================================================================

/**
 * Forcer un Favicon spécifique (favicon_bo.png) pour tout l'admin et login
 */
/**
 * Gestion des Favicons via le thème (Front vs Back)
 * Outrepasse les réglages "Identité du site" de WordPress
 */
function wamv1_custom_favicon_filter($url) {
    if (is_admin() || is_network_admin() || $GLOBALS['pagenow'] === 'wp-login.php') {
        return get_template_directory_uri() . '/favicon_bo.png';
    }
    // Par défaut pour le front-office
    return get_template_directory_uri() . '/favicon.png';
}
add_filter('get_site_icon_url', 'wamv1_custom_favicon_filter', 99);

function wamv1_custom_favicon_output() {
    $is_bo = is_admin() || is_network_admin() || $GLOBALS['pagenow'] === 'wp-login.php';
    $file = $is_bo ? '/favicon_bo.png' : '/favicon.png';
    $favicon_url = get_template_directory_uri() . $file;
    
    echo '<link rel="icon" href="' . esc_url($favicon_url) . '" type="image/png" />';
    echo '<link rel="shortcut icon" href="' . esc_url($favicon_url) . '" type="image/png" />';
}
// Injecter sur tout le site
add_action('wp_head', 'wamv1_custom_favicon_output', 1);
add_action('admin_head', 'wamv1_custom_favicon_output', 1);
add_action('login_head', 'wamv1_custom_favicon_output', 1);