<?php
/**
 * Désactivation complète des commentaires — WAM Dance Studio
 *
 * Ce module supprime :
 *   - L'accès aux commentaires (redirect vers la home si accès direct)
 *   - Les métas et supports HTML5 des commentaires
 *   - L'onglet "Commentaires" dans le menu Admin
 *   - La barre d'outils Admin des commentaires
 *   - Les clés des requêtes WP liées aux commentaires
 *   - Les widgets de commentaires récents
 *
 * @package wamv1
 */

// 1. Désactiver le support des commentaires sur tous les types de contenus
function wamv1_disable_comments_support() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}
add_action('admin_init', 'wamv1_disable_comments_support');

// 2. Fermer les commentaires sur tous les posts (filtre en front)
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// 3. Masquer les commentaires existants (liste vide)
add_filter('comments_array', '__return_empty_array', 10, 2);

// 4. Supprimer le menu "Commentaires" dans l'admin
function wamv1_remove_comments_admin_menu() {
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'wamv1_remove_comments_admin_menu');

// 5. Supprimer "Commentaires" de la barre d'outils (admin bar)
function wamv1_remove_comments_admin_bar() {
    if (is_admin_bar_showing()) {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }
}
add_action('wp_before_admin_bar_render', 'wamv1_remove_comments_admin_bar');

// 6. Rediriger toutes les pages de commentaires vers la home (accès direct)
function wamv1_redirect_comment_pages() {
    global $pagenow;
    // Page admin des commentaires
    if ($pagenow === 'edit-comments.php' || $pagenow === 'comment.php') {
        wp_safe_redirect(admin_url());
        exit;
    }
    // Page front des commentaires
    if (is_comment_feed()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}
add_action('admin_init', 'wamv1_redirect_comment_pages');
add_action('template_redirect', 'wamv1_redirect_comment_pages');

// 7. Supprimer le widget "Commentaires récents"
function wamv1_disable_recent_comments_widget() {
    unregister_widget('WP_Widget_Recent_Comments');
}
add_action('widgets_init', 'wamv1_disable_recent_comments_widget', 15);

// 8. Désactiver le fil RSS des commentaires
add_filter('feed_links_show_comments_feed', '__return_false');

// 9. Supprimer le compteur de commentaires en attente dans la barre admin
function wamv1_remove_comments_from_dashboard() {
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}
add_action('wp_dashboard_setup', 'wamv1_remove_comments_from_dashboard');
