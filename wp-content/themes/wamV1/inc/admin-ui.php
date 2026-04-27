<?php
/**
 * Nettoyage et personnalisation de l'interface d'administration WAM
 *
 * @package wamv1
 */

/**
 * 1. Nettoyage du menu latéral selon le rôle
 */
function wamv1_clean_admin_menu() {
    $user = wp_get_current_user();
    if (!$user) return;

    // --- LOGIQUE DIRECTRICE ---
    if (in_array('directrice', (array) $user->roles)) {
        // Menus techniques masqués
        remove_menu_page('edit-comments.php');
        remove_menu_page('plugins.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('themes.php');
        remove_menu_page('edit.php?post_type=acf-field-group');

        // Yoast SEO — tous les slugs connus
        remove_menu_page('wpseo_dashboard');
        remove_menu_page('wpseo_workouts');
        remove_submenu_page('wpseo_dashboard', 'wpseo_dashboard');
        remove_submenu_page('wpseo_dashboard', 'wpseo_page_settings');
        remove_submenu_page('wpseo_dashboard', 'wpseo_workouts');
        remove_submenu_page('wpseo_dashboard', 'wpseo_redirects');
        remove_submenu_page('wpseo_dashboard', 'wpseo_licenses');

        add_menu_page(
            'Configuration WAM',
            'Configuration WAM',
            'manage_options',
            'wam-config',
            'wam_config_page_html',
            'dashicons-admin-generic',
            80
        );
    }

    // --- LOGIQUE PROFESSEUR ---
    if (in_array('professeur', (array) $user->roles)) {
        remove_menu_page('index.php');
        remove_menu_page('edit.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit.php?post_type=evenements');
        remove_menu_page('edit.php?post_type=product');
        remove_menu_page('upload.php');
        remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('edit.php?post_type=acf-field-group');

        // Yoast SEO — tous les slugs connus
        remove_menu_page('wpseo_dashboard');
        remove_menu_page('wpseo_workouts');
        remove_submenu_page('wpseo_dashboard', 'wpseo_dashboard');
        remove_submenu_page('wpseo_dashboard', 'wpseo_page_settings');
        remove_submenu_page('wpseo_dashboard', 'wpseo_workouts');
        remove_submenu_page('wpseo_dashboard', 'wpseo_redirects');
        remove_submenu_page('wpseo_dashboard', 'wpseo_licenses');

    }
}
add_action('admin_menu', 'wamv1_clean_admin_menu', 999);

/**
 * 1b. Ajoute des classes au body admin selon le rôle pour le styling CSS
 */
function wamv1_admin_body_class($classes) {
    $user = wp_get_current_user();
    if (!$user) return $classes;

    if (in_array('professeur', (array) $user->roles)) {
        $classes .= ' wam-restricted-ui wam-role-professeur';
    }
    if (in_array('directrice', (array) $user->roles)) {
        $classes .= ' wam-restricted-ui wam-role-directrice';
    }
    return $classes;
}
add_filter('admin_body_class', 'wamv1_admin_body_class');

/**
 * 1b. Bloque l'accès direct aux pages sensibles pour l'Admin technique
 */
function wamv1_restrict_admin_access() {
    if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) return;

    $user = wp_get_current_user();
}
add_action('admin_init', 'wamv1_restrict_admin_access');

/**
 * 2. Masquage des meta boxes Yoast et LiteSpeed dans l'éditeur
 *     Pour directrice et professeur : interface de rédaction épurée
 */
function wamv1_hide_plugin_metaboxes() {
    $user = wp_get_current_user();
    $restricted_roles = ['directrice', 'professeur'];
    $is_restricted = !empty(array_intersect($restricted_roles, (array) $user->roles));

    if (!$is_restricted) return;

    // Yoast SEO — meta boxes enregistrées par le plugin
    $post_types = ['post', 'page', 'cours', 'stages', 'wam_membre', 'evenements'];
    foreach ($post_types as $pt) {
        remove_meta_box('wpseo_meta', $pt, 'normal');
        remove_meta_box('wpseo_meta', $pt, 'side');
    }

    // Note: Les styles de masquage sont maintenant dans assets/css/admin.css
    // activés via la classe .wam-restricted-ui sur le body.
}
add_action('add_meta_boxes', 'wamv1_hide_plugin_metaboxes', 999);

/**
 * 2. Filtrage de la liste des posts pour les professeurs
 * Ils ne voient que les contenus auxquels ils sont liés
 */
/**
 * 2. Filtrage de la liste des posts pour les professeurs
 * Ils ne voient que les contenus auxquels ils sont liés via ACF
 */
function wamv1_filter_profs_list($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $user = wp_get_current_user();
    if (!$user || !in_array('professeur', (array) $user->roles)) return;

    $post_type = $query->get('post_type');

    if ($post_type === 'wam_membre') {
        $query->set('meta_query', array(
            array('key' => 'user_prof', 'value' => $user->ID, 'compare' => '=')
        ));
    }
    if ($post_type === 'cours') {
        // LIKE '"12"' car champ ACF mutli = tableau sérialisé
        $query->set('meta_query', array(
            array('key' => 'prof_cours', 'value' => '"' . $user->ID . '"', 'compare' => 'LIKE')
        ));
    }
    if ($post_type === 'stages') {
        $query->set('meta_query', array(
            array('key' => 'intervenant·e_stage_intervenant', 'value' => $user->ID, 'compare' => '=')
        ));
    }
}
add_action('pre_get_posts', 'wamv1_filter_profs_list');

/**
 * 3. Barrière de sécurité pour empêcher d'éditer le cours d'un collègue
 * Même si le prof a le droit "edit_others_pages" théorique, on vérifie ACF.
 */
function wamv1_guard_teacher_editing() {
    global $pagenow;
    if ($pagenow !== 'post.php' || !isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'edit') return;

    $user = wp_get_current_user();
    if (!$user || !in_array('professeur', (array) $user->roles)) return;

    $post_id = (int) $_GET['post'];
    $post_type = get_post_type($post_id);

    $has_access = false;
    
    if ($post_type === 'wam_membre') {
        $val = get_field('user_prof', $post_id, false);
        $has_access = (is_array($val) ? in_array($user->ID, $val) : $val == $user->ID);
    } elseif ($post_type === 'cours') {
        $val = get_field('prof_cours', $post_id, false);
        $has_access = (is_array($val) ? in_array($user->ID, $val) : $val == $user->ID);
    } elseif ($post_type === 'stages') {
        $val = get_field('intervenant·e_stage_intervenant', $post_id, false);
        $has_access = ($val == $user->ID);
    } else {
        // Pas le droit de modifier d'autres CPT (pages normales, articles, etc)
        $has_access = false;
    }

    if (!$has_access && !current_user_can('manage_options')) {
        wp_die("Oups ! Vous n'êtes pas assigné(e) à ce contenu en tant que professeur. Contactez la directrice si c'est une erreur.");
    }
}
add_action('admin_head', 'wamv1_guard_teacher_editing');

/**
 * 4. Redirection après login pour les profs
 */
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles) && in_array('professeur', $user->roles)) {
        // On les redirige vers leurs cours plutôt que la fiche prof, c'est plus utile
        return admin_url('edit.php?post_type=cours');
    }
    return $redirect_to;
}, 10, 3);
