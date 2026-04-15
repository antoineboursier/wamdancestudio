<?php
/**
 * Création des rôles personnalisés WAM
 *
 * @package wamv1
 */

function wamv1_register_roles()
{
    // Rôle Professeur·e
        add_role('professeur', __('Professeur·e', 'wamv1'), array(
            'read'         => true,
            'edit_posts'   => true, // Nécessaire pour éditer sa propre fiche (CPT wam_membre)
            'edit_pages'   => true, // Nécessaire pour éditer ses cours/stages (capability_type => 'page')
            'upload_files' => true, // Pour changer sa photo de profil
            'delete_posts' => false,
            'delete_pages' => false,
        ));

    // Rôle Directrice
    if (!get_role('directrice')) {
        add_role('directrice', __('Directrice', 'wamv1'), array(
            'read'                   => true,
            'edit_posts'             => true,
            'edit_others_posts'      => true,
            'publish_posts'          => true,
            'edit_published_posts'   => true,
            'delete_posts'           => true,
            'delete_others_posts'    => true,
            'edit_pages'             => true, // Pour les Cours et Stages (capability_type => 'page')
            'edit_others_pages'      => true,
            'publish_pages'          => true,
            'edit_published_pages'   => true,
            'delete_pages'           => true,
            'delete_others_pages'    => true,
            'upload_files'           => true,
            'manage_options'         => true, // Pour accéder à la page "Configuration WAM"
        ));
    }
}
add_action('init', 'wamv1_register_roles');
