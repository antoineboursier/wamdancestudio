<?php
/**
 * The template for displaying search results
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner search-results">
        <header class="page-header search-results__header">
            <h1 class="page-title is-style-title-sign-lg">
                <?php
                printf(esc_html__('Résultats pour : %s', 'wamv1'), '<span>' . get_search_query() . '</span>');
                ?>
            </h1>
        </header><!-- .page-header -->

        <div class="page-content search-results__content">
            <?php if (have_posts()) : ?>
                <div class="search-results__list">
                    <?php
                    /* Start the Loop */
                    while (have_posts()) :
                        the_post();
                        ?>
                        <div class="search-result-item">
                            <a href="<?php the_permalink(); ?>" class="search-result-item__link">
                                <h2 class="is-style-title-norm-md m-0"><?php the_title(); ?></h2>
                                <p class="text-xs color-subtext mt-2xs">Publié le <?php echo get_the_date('d/m/Y'); ?> | Type : <?php echo get_post_type(); ?></p>
                                <div class="search-excerpt text-md mt-sm"><?php the_excerpt(); ?></div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php the_posts_pagination(['prev_text' => 'Précédent', 'next_text' => 'Suivant']); ?>

            <?php else : ?>
                <div class="search-results__empty">
                    <p class="text-md fw-bold">Désolé, mais aucun résultat ne correspond à votre recherche.</p>
                    <p class="text-sm color-subtext">Veuillez réessayer avec d'autres mots-clés.</p>
                </div>
            <?php endif; ?>
        </div><!-- .page-content -->
    </div><!-- .page-layout__inner -->
</main><!-- #main -->

<?php
get_footer();
