<?php
/**
 * Création des rôles personnalisés WAM
 *
 * @package wamv1
 */

function wamv1_register_roles()
{
    // Rôle Professeur·e
    if (!get_role('professeur')) {
        add_role('professeur', __('Professeur·e', 'wamv1'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ));
    }

    // Rôle Directrice
    if (!get_role('directrice')) {
        add_role('directrice', __('Directrice', 'wamv1'), array(
            'read' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            'delete_posts' => true,
            'manage_options' => false,
        ));
    }
}
add_action('init', 'wamv1_register_roles');
