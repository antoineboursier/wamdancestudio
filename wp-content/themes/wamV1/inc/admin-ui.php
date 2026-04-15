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
        // On masque les menus techniques
        remove_menu_page('edit-comments.php');
        remove_menu_page('plugins.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php'); // Masque les réglages WP
        
        // MAIS on veut garder l'accès à notre page de config WAM
        // Elle reste accessible via l'URL directe ou via un nouveau point d'entrée
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
        // On masque presque tout sauf : Équipe, Cours, Stages
        remove_menu_page('index.php'); // Tableau de bord
        remove_menu_page('edit.php'); // Articles Blog
        remove_menu_page('edit.php?post_type=page');
        // On garde : edit.php?post_type=cours
        // On garde : edit.php?post_type=stages
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

        // MASQUAGE DES BOUTONS "AJOUTER" (CSS)
        // Les profs peuvent éditer mais pas créer de nouveaux contenus
        echo '<style>
            .wp-admin.post-type-wam_membre .page-title-action,
            .wp-admin.post-type-cours .page-title-action,
            .wp-admin.post-type-stages .page-title-action,
            #menu-posts-wam_membre .wp-first-item + li,
            #menu-posts-cours .wp-first-item + li,
            #menu-posts-stages .wp-first-item + li {
                display: none !important;
            }
        </style>';
    }
}
add_action('admin_menu', 'wamv1_clean_admin_menu', 999);

/**
 * 2. Filtrage de la liste des posts pour les professeurs
 * Ils ne voient que les contenus auxquels ils sont liés
 */
function wamv1_filter_profs_list($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $user = wp_get_current_user();
    if (!$user || !in_array('professeur', (array) $user->roles)) return;

    $post_type = $query->get('post_type');

    // Cas 1 : Fiche Profil (wam_membre) -> filtrage par auteur
    if ($post_type === 'wam_membre') {
        $query->set('author', $user->ID);
    }

    // Cas 2 : Cours -> filtrage par champ ACF prof_cours (Multi-User)
    if ($post_type === 'cours') {
        $meta_query = array(
            array(
                'key'     => 'prof_cours',
                'value'   => '"' . $user->ID . '"', // ACF stocke les tableaux user as serialized array
                'compare' => 'LIKE'
            )
        );
        $query->set('meta_query', $meta_query);
    }

    // Cas 3 : Stages -> filtrage par champ ACF intervenant·e_stage_intervenant
    if ($post_type === 'stages') {
        $meta_query = array(
            array(
                'key'     => 'intervenant·e_stage_intervenant',
                'value'   => $user->ID,
                'compare' => '='
            )
        );
        $query->set('meta_query', $meta_query);
    }
}
add_action('pre_get_posts', 'wamv1_filter_profs_list');

/**
 * 3. Permissions dynamiques (Map Meta Cap)
 * Permet d'autoriser l'édition d'un post si le prof y est assigné
 */
function wamv1_map_teacher_caps($caps, $cap, $user_id, $args) {
    if (!in_array($cap, array('edit_post', 'delete_post', 'read_post'))) return $caps;
    
    $post_id = $args[0] ?? null;
    if (!$post_id) return $caps;

    $user = get_userdata($user_id);
    if (!$user || !in_array('professeur', (array) $user->roles)) return $caps;

    $post_type = get_post_type($post_id);

    // Droit d'édition sur sa PROPRE fiche prof
    if ($post_type === 'wam_membre') {
        $post = get_post($post_id);
        if ($post->post_author == $user_id) {
            return array('edit_posts'); // On mappe vers une cap de base qu'il possède
        }
    }

    // Droit d'édition sur ses COURS assignés
    if ($post_type === 'cours' && function_exists('get_field')) {
        $assigned_profs = get_field('prof_cours', $post_id, false); // false pour ID bruts
        if (is_array($assigned_profs) && in_array($user_id, $assigned_profs)) {
            return array('edit_pages');
        }
        // Cas mono-valeur si ACF a été configuré ainsi
        if (!is_array($assigned_profs) && $assigned_profs == $user_id) {
            return array('edit_pages');
        }
    }

    // Droit d'édition sur ses STAGES assignés
    if ($post_type === 'stages' && function_exists('get_field')) {
        $stage_prof = get_field('intervenant·e', $post_id);
        $assigned_id = $stage_prof['stage_intervenant']['ID'] ?? ($stage_prof['stage_intervenant'] ?? null);
        
        if ($assigned_id == $user_id) {
            return array('edit_pages');
        }
    }

    return $caps;
}
add_filter('map_meta_cap', 'wamv1_map_teacher_caps', 10, 4);

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
