<?php
/**
 * The template for displaying all single posts (single.php)
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main site-main--with-pb">
    <div class="page-layout__inner">

        <?php
        /*
         * Boucle WP standard — sur un single post, have_posts() retourne true une seule fois.
         * Requise avant tout appel à the_title(), get_the_date(), the_content(), etc.
         */
        while (have_posts()) :
            the_post(); ?>

            <!-- Breadcrumb : Accueil > [Catégorie] > [Titre de l'article] -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-article',
                'full' => true,
            ]); ?>

            <!-- En-tête article : titre (Outfit Bold) + date (pink) + temps de lecture + image à la une -->
            <?php get_template_part('template-parts/single-hero', null, [
                'id'               => 'section-article-header',
                'title_class'      => 'is-style-title-norm-md',
                'show_date'        => true,
                'show_reading_time' => true,
                'image_size'       => 'wamv1-page-hero',
                'image_modifier'   => 'lg',
            ]); ?>

            <?php
            $article_content = get_the_content();
            if (!empty(trim($article_content))): ?>
                <!-- Contenu de l'article -->
                <div id="section-article-content" class="page-content">
                    <div class="page-content__inner wam-prose">
                        <?php echo apply_filters('the_content', $article_content); ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endwhile; ?>

        <!-- Séparateur pattern danseurs + articles similaires -->
        <?php
        /*
         * get_template_part() inclut template-parts/separator.php (motif SVG danseurs).
         * get_template_part() inclut template-parts/related-content.php qui gère
         * sa propre WP_Query (articles de la même catégorie, hors article courant).
         */
        get_template_part('template-parts/separator');
        get_template_part('template-parts/related-content');
        ?>

    </div>
</main>

<?php get_footer(); ?>
