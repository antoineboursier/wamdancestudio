<?php
/**
 * The template for displaying all single posts (single.php)
 *
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');
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
            <div id="section-article-header" class="page-header">
                <div class="page-header__meta">
                    <h1 class="page-header__title is-style-title-norm-md">
                        <?php the_title(); ?>
                    </h1>
                    <div class="page-header__dateline">
                        <p class="page-header__date">
                            Publié le <?php echo get_the_date('d/m/Y'); ?>
                            <?php
                            $u_time = get_the_time('U');
                            $u_modified_time = get_the_modified_time('U');
                            if ($u_modified_time >= $u_time + 86400) :
                                $days_diff = round((current_time('timestamp') - $u_modified_time) / 86400);
                                if ($days_diff > 0) :
                                    echo ' - Modifié il y a ' . $days_diff . ' ' . _n('jour', 'jours', $days_diff, 'wamv1');
                                endif;
                            endif;
                            ?>
                        </p>
                        <?php if ($reading_time = wamv1_get_reading_time(get_the_content())) : ?>
                            <p class="page-header__reading-time">
                                <?php echo esc_html($reading_time); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (has_post_thumbnail()) : ?>
                    <!-- Image à la une (conditionnelle — non affichée si absente) -->
                    <div class="page-header__photo-outer">
                        <div class="page-header__photo">
                            <?php the_post_thumbnail('wamv1-page-hero', ['class' => 'page-header__photo-img']); ?>
                            <div class="page-header__photo-overlay"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contenu de l'article -->
            <div id="section-article-content" class="page-content">
                <div class="page-content__inner wam-prose">
                    <?php the_content(); ?>
                </div>
            </div>

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
