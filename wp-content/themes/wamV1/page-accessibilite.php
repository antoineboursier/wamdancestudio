<?php
/**
 * Template for /accessibilite/ page
 *
 * Affiche les réglages d'accessibilité directement dans le contenu de la page,
 * sans le bouton flottant ni le backdrop (gérés uniquement via le panneau inline).
 * Le panneau flottant habituel (wp_footer) est supprimé pour cette page.
 *
 * @package wamv1
 */

// Ajoute la classe CSS page-accessibilite sur <body> pour cibler les styles inline
add_filter('body_class', function ($classes) {
    $classes[] = 'page-accessibilite';
    return $classes;
});

// Empêche la double injection du panneau via wp_footer
remove_action('wp_footer', 'wamv1_inject_accessibility_panel', 99);

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php while (have_posts()) : the_post(); ?>

            <!-- Breadcrumb : Accueil > Accessibilité -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-accessibilite',
                'full' => true,
            ]); ?>

            <!-- En-tête : titre de la page -->
            <?php get_template_part('template-parts/single-hero', null, [
                'id'               => 'section-accessibilite-header',
                'title_class'      => 'is-style-title-sign-lg',
                'content_modifier' => 'lg',
                'image_size'       => 'wam-page-thumbnail',
                'image_modifier'   => 'sm',
            ]); ?>

            <?php
            // Contenu éditorial (intro rédigée dans l'éditeur WP)
            $page_content = get_the_content();
            if (!empty(trim($page_content))): ?>
                <div id="section-accessibilite-content" class="page-content">
                    <div class="page-content__inner wam-prose">
                        <?php echo apply_filters('the_content', $page_content); ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endwhile; ?>

        <!-- Widget accessibilité inline — même HTML que le panneau flottant,
             affiché ici en position statique, visible sans interaction. -->
        <div id="section-a11y-widget" class="a11y-widget-wrap">
            <?php get_template_part('template-parts/accessibility-module', null, [
                'inline' => true,
            ]); ?>
        </div>

    </div><!-- /.page-layout__inner -->
</main>

<?php get_footer(); ?>
