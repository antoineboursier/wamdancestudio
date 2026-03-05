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

// -------------------------------------------------------
// Setup
// -------------------------------------------------------
if (!function_exists('wamv1_setup')):
    function wamv1_setup()
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
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
    }
endif;
add_action('after_setup_theme', 'wamv1_setup');

// -------------------------------------------------------
// Enqueue Scripts & Styles
// -------------------------------------------------------
function wamv1_scripts()
{
    $ver = wp_get_theme()->get('Version');

    // Style global
    wp_enqueue_style('wamv1-style', get_stylesheet_uri(), array(), $ver);

    // CSS page d'accueil uniquement
    if (is_front_page()) {
        wp_enqueue_style(
            'wamv1-home',
            get_template_directory_uri() . '/assets/css/home.css',
            array('wamv1-style'),
            $ver
        );
        wp_enqueue_script(
            'wamv1-home',
            get_template_directory_uri() . '/assets/js/home.js',
            array(),
            $ver,
            true  // footer
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
