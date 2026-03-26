<?php
/**
 * The template for displaying all single pages (page.php)
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');
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

            <!-- En-tête de page : titre (Mallia) + temps de lecture + image à la une optionnelle -->
            <div id="section-page-header" class="page-header">
                <div class="page-header__meta page-header__meta--lg">
                    <h1 class="page-header__title is-style-title-sign-lg">
                        <?php the_title(); ?>
                    </h1>
                    <?php if ($reading_time = wamv1_get_reading_time(get_the_content())) : ?>
                        <p class="page-header__reading-time">
                            <?php echo esc_html($reading_time); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (has_post_thumbnail()) : ?>
                    <!-- Image à la une (conditionnelle — non affichée si absente) -->
                    <div class="page-header__photo-outer">
                        <div class="page-header__photo page-header__photo--sm">
                            <?php the_post_thumbnail('wam-page-thumbnail', ['class' => 'page-header__photo-img']); ?>
                            <div class="page-header__photo-overlay"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contenu Gutenberg -->
            <div id="section-page-content" class="page-content">
                <div class="page-content__inner wam-prose">
                    <?php the_content(); ?>
                </div>
            </div>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>
