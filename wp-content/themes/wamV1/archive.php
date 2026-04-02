<?php
/**
 * The template for displaying archive pages
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">
        <header class="page-header" style="padding: var(--wam-spacing-2xl) var(--wam-page-mx);">
            <?php
            the_archive_title('<h1 class="page-title is-style-title-sign-lg">', '</h1>');
            the_archive_description('<div class="archive-description text-md color-subtext mt-sm">', '</div>');
            ?>
        </header><!-- .page-header -->

        <div class="page-content" style="padding: 0 var(--wam-page-mx) var(--wam-spacing-3xl);">
            <?php if (have_posts()) : ?>
                <div class="wam-grid wam-grid--news">
                    <?php
                    /* Start the Loop */
                    while (have_posts()) :
                        the_post();
                        // On fallback sur une carte style article par défaut
                        ?>
                        <article id="post-<?php the_ID(); ?>" <?php post_class('card-post'); ?>>
                            <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: inherit;">
                                <h2 class="is-style-title-norm-md m-0"><?php the_title(); ?></h2>
                                <p class="text-sm color-subtext mt-2xs"><?php echo get_the_date(); ?></p>
                                <div class="text-md mt-sm"><?php the_excerpt(); ?></div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php the_posts_pagination(['prev_text' => 'Précédent', 'next_text' => 'Suivant']); ?>

            <?php else : ?>
                <p class="text-lg">Aucun contenu trouvé pour cette archive.</p>
            <?php endif; ?>
        </div>
    </div><!-- .page-layout__inner -->
</main><!-- #main -->

<?php
get_footer();
