<?php
/**
 * Création des rôles personnalisés WAM
 *
 * @package wamv1
 */

function wamv1_register_roles()
{
    // 1. Rôle PROFESSEUR (Base Contributeur + upload_files)
    if (!get_role('professeur')) {
        add_role('professeur', __('Professeur·e', 'wamv1'), array('read' => true));
    }

    $prof = get_role('professeur');
    if ($prof) {
        $prof_caps = array(
            'read',
            'edit_posts',
            'delete_posts',
            'upload_files',
            'assign_terms',
            'edit_pages',
            'edit_published_posts',
            'edit_published_pages',
            'edit_others_posts', // Requis pour contourner l'auteur unique WP
            'edit_others_pages', // Idem pour les cours et stages
        );
        foreach ($prof_caps as $cap) {
            $prof->add_cap($cap);
        }
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
            // WooCommerce total access
            'manage_woocommerce',
            'manage_woocommerce_marketing',
            'manage_woocommerce_settings',
            'manage_woocommerce_tax',
            'view_woocommerce_reports',
            'edit_products', 'edit_others_products', 'publish_products', 'read_private_products', 'delete_products', 'delete_private_products', 'delete_published_products', 'delete_others_products', 'edit_private_products', 'edit_published_products',
            'manage_product_terms', 'edit_product_terms', 'delete_product_terms', 'assign_product_terms',
            'edit_shop_orders', 'edit_others_shop_orders', 'publish_shop_orders', 'read_private_shop_orders', 'delete_shop_orders', 'delete_private_shop_orders', 'delete_published_shop_orders', 'delete_others_shop_orders', 'edit_private_shop_orders', 'edit_published_shop_orders',
            'edit_shop_coupons', 'edit_others_shop_coupons', 'publish_shop_coupons', 'read_private_shop_coupons', 'delete_shop_coupons', 'delete_private_shop_coupons', 'delete_published_shop_coupons', 'delete_others_shop_coupons', 'edit_private_shop_coupons', 'edit_published_shop_coupons',
        );

        foreach ($caps as $cap) {
            $directrice->add_cap($cap);
        }
    }
}
add_action('init', 'wamv1_register_roles');
