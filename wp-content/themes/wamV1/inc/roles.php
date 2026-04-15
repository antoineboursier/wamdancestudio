<?php
/**
 * Création des rôles personnalisés WAM
 *
 * @package wamv1
 */

function wamv1_register_roles()
{
    // 1. Rôle PROFESSEUR (Capacités minimales + accès épuré)
    if (!get_role('professeur')) {
        add_role('professeur', __('Professeur·e', 'wamv1'), array(
            'read'         => true,
            'edit_posts'   => true, // Pour sa propre fiche wam_membre
            'edit_pages'   => true, // Pour ses propres cours/stages
            'upload_files' => true,
        ));
    }

    // 2. Rôle DIRECTRICE (Base Admin / Éditeur + Config)
    if (!get_role('directrice')) {
        add_role('directrice', __('Directrice', 'wamv1'), array('read' => true));
    }

    $directrice = get_role('directrice');
    if ($directrice) {
        $caps = array(
            // Contenu global (Posts & Pages)
            'edit_posts', 'edit_others_posts', 'publish_posts', 'edit_published_posts',
            'delete_posts', 'delete_others_posts', 'delete_published_posts',
            'edit_pages', 'edit_others_pages', 'publish_pages', 'edit_published_pages',
            'delete_pages', 'delete_others_pages', 'delete_published_pages',
            // Contenu privé
            'read_private_posts', 'edit_private_posts', 'delete_private_posts',
            'read_private_pages', 'edit_private_pages', 'delete_private_pages',
            // Médias et Taxonomies
            'upload_files', 'manage_categories', 'moderate_comments', 'unfiltered_html',
            // Administration & Config WAM
            'manage_options',      // Accès configuration WAM
            'edit_theme_options',  // Accès Menus & Widgets
        );

        foreach ($caps as $cap) {
            $directrice->add_cap($cap);
        }
    }
}
add_action('init', 'wamv1_register_roles');
