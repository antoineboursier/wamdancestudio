<?php
/**
 * The template for displaying all single pages (page.php)
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php
        /*
         * Boucle WP standard — sur une page, have_posts() retourne true une seule fois.
         * Requise avant tout appel à the_title(), the_content(), etc.
         */
        while (have_posts()) :
            the_post(); ?>

            <!-- Breadcrumb : Accueil > [Titre de la page] -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-page',
                'full' => true,
            ]); ?>

            <!-- En-tête de page : titre (Mallia) + image à la une optionnelle -->
            <?php get_template_part('template-parts/single-hero', null, [
                'id'               => 'section-page-header',
                'title_class'      => 'is-style-title-sign-lg',
                'content_modifier' => 'lg',
                'image_size'       => 'wam-page-thumbnail',
                'image_modifier'   => 'sm',
            ]); ?>

            <?php
            $page_content = get_the_content();
            if (!empty(trim($page_content))): ?>
                <!-- Contenu Gutenberg -->
                <div id="section-page-content" class="page-content">
                    <div class="page-content__inner wam-prose">
                        <?php echo apply_filters('the_content', $page_content); ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>
