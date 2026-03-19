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
require_once get_template_directory() . '/inc/shortcodes.php';
require_once get_template_directory() . '/inc/accessibility.php';
require_once get_template_directory() . '/inc/nav-walker.php';

// -------------------------------------------------------
// Setup
// -------------------------------------------------------
if (!function_exists('wamv1_setup')):
    function wamv1_setup()
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_image_size('wamv1-page-hero', 1536, 600, true);
        add_theme_support('editor-styles');
        add_theme_support('html5', array(
            'search-form',
            'comment-form',
            'comment-list',
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

        // Editor styles — back office fidèle au front
        // Google Fonts doit être passé en premier pour que les polices soient dispo
        add_editor_style(array(
            'https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap',
            'assets/css/tokens.css',
            'assets/css/base.css',
            'assets/css/editor.css',
        ));
    }
endif;
add_action('after_setup_theme', 'wamv1_setup');

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
// Performance - Preconnect pour Google Fonts
// -------------------------------------------------------
function wamv1_font_preconnect()
{
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}
add_action('wp_head', 'wamv1_font_preconnect', 1);

// -------------------------------------------------------
function wamv1_scripts()
{
    $ver = wp_get_theme()->get('Version');
    $css = get_template_directory_uri() . '/assets/css/';
    $js = get_template_directory_uri() . '/assets/js/';

    // -------------------------------------------------------
    // 0. Fonts — Outfit de Google Fonts
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-fonts', 'https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap', array(), null);

    // -------------------------------------------------------
    // 1. style.css — requis WordPress (header thème)
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-style', get_stylesheet_uri(), array('wamv1-fonts'), $ver);

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
    // 3. dist/main.css — Bundle Tailwind compilé
    //    Contient : utilities Tailwind (purgées) +
    //    classes typo Figma (.title-cool-lg...) +
    //    classes couleurs + composants (@layer components)
    //    TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-main', $css . 'dist/main.css', array('wamv1-base'), $ver);

    // -------------------------------------------------------
    // accessibility.css — module accessibilité global
    // TOUTES LES PAGES
    // -------------------------------------------------------
    wp_enqueue_style('wamv1-accessibility', $css . 'accessibility.css', array('wamv1-main'), $ver);

    // -------------------------------------------------------
    // Page d'accueil uniquement
    // -------------------------------------------------------
    if (is_front_page()) {
        wp_enqueue_style('wamv1-home', $css . 'home.css', array('wamv1-main'), $ver);
    }

    // -------------------------------------------------------
    // Scripts JS
    // -------------------------------------------------------
    wp_enqueue_script('wamv1-main', $js . 'main.js', array(), $ver, true);

    if (is_front_page()) {
        wp_enqueue_script(
            'wamv1-home',
            $js . 'home.js',
            array(),
            $ver,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'wamv1_scripts');



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
}
add_action('show_user_profile', 'wamv1_add_user_specialty_field');
add_action('edit_user_profile', 'wamv1_add_user_specialty_field');

function wamv1_save_user_specialty_field($user_id)
{
    if (!current_user_can('edit_user', $user_id))
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
        'supports' => array('title', 'thumbnail'),
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

function wamv1_disable_gutenberg_cours($use_block_editor, $post_type)
{
    if (in_array($post_type, array('cours', 'stages'))) {
        return false;
    }
    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'wamv1_disable_gutenberg_cours', 10, 2);

// =============================================================================
// CPT : MEMBRES (Professeur·es & Directrice)
// =============================================================================

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
            'title',  // Bio via Gutenberg
            'thumbnail',  // Photo de profil
        ),
        'menu_icon' => 'dashicons-groups',
        'has_archive' => false,
        'rewrite' => array('slug' => 'equipe'),
        'capability_type' => 'post',
    );

    register_post_type('wam_membre', $args);
}
add_action('init', 'wamv1_register_cpt_membre');

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
    // Pages : déjà Mallia via editor.css h1 — aucun override nécessaire
}
add_action('enqueue_block_editor_assets', 'wamv1_block_editor_title_styles');

// -------------------------------------------------------
// Utilitaires de contenu
// -------------------------------------------------------
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
