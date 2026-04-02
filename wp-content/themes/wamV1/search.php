<?php
/**
 * The template for displaying search results
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner" style="min-height: 50vh;">
        <header class="page-header" style="padding: var(--wam-spacing-2xl) var(--wam-page-mx);">
            <h1 class="page-title is-style-title-sign-lg">
                <?php
                printf(esc_html__('Résultats pour : %s', 'wamv1'), '<span>' . get_search_query() . '</span>');
                ?>
            </h1>
        </header><!-- .page-header -->

        <div class="page-content" style="padding: 0 var(--wam-page-mx) var(--wam-spacing-3xl);">
            <?php if (have_posts()) : ?>
                <div class="wam-grid" style="display: flex; flex-direction: column; gap: var(--wam-spacing-lg);">
                    <?php
                    /* Start the Loop */
                    while (have_posts()) :
                        the_post();
                        ?>
                        <div class="search-result-item" style="border-bottom: 1px solid var(--wam-color-disabled); padding-bottom: var(--wam-spacing-md);">
                            <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: inherit;">
                                <h2 class="is-style-title-norm-md m-0"><?php the_title(); ?></h2>
                                <p class="text-xs color-subtext mt-2xs">Publié le <?php echo get_the_date('d/m/Y'); ?> | Type : <?php echo get_post_type(); ?></p>
                                <div class="search-excerpt text-md mt-sm"><?php the_excerpt(); ?></div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php the_posts_pagination(['prev_text' => 'Précédent', 'next_text' => 'Suivant']); ?>

            <?php else : ?>
                <div style="background: var(--wam-color-card-bg); padding: var(--wam-spacing-lg); border-radius: var(--wam-radius-md);">
                    <p class="text-md fw-bold">Désolé, mais aucun résultat ne correspond à votre recherche.</p>
                    <p class="text-sm color-subtext">Veuillez réessayer avec d'autres mots-clés.</p>
                </div>
            <?php endif; ?>
        </div><!-- .page-content -->
    </div><!-- .page-layout__inner -->
</main><!-- #main -->

<?php
get_footer();
