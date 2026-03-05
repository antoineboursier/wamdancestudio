<?php
/**
 * Template Part : Card Article (sticky posts)
 * Affiche le ou les articles épinglés (sticky)
 *
 * @package wamv1
 */

$sticky_ids = get_option('sticky_posts');

if (empty($sticky_ids)) {
    return; // Rien à afficher si pas d'article épinglé
}

$query = new WP_Query(array(
    'post__in' => $sticky_ids,
    'posts_per_page' => 1,
    'ignore_sticky_posts' => true,
));

if (!$query->have_posts()) {
    return;
}
?>
<section class="section-featured" aria-label="<?php esc_attr_e('Article à la une', 'wamv1'); ?>">
    <?php while ($query->have_posts()):
        $query->the_post(); ?>
        <article class="card-article">
            <?php /* Pastille notification animée */ ?>
            <span class="card-article__notif" aria-label="<?php esc_attr_e('Nouveauté', 'wamv1'); ?>" role="img"></span>

            <?php /* Image de l'article */ ?>
            <div class="card-article__image">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('large', array('alt' => get_the_title())); ?>
                <?php else: ?>
                    <div style="background: var(--wp--preset--color--background-500); width:100%; height:100%;"></div>
                <?php endif; ?>
            </div>

            <div class="card-article__body">
                <div>
                    <h2 class="card-article__title">
                        <?php the_title(); ?>
                    </h2>
                    <p class="card-article__excerpt">
                        <?php echo wp_kses_post(get_the_excerpt()); ?>
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap: var(--wp--preset--spacing--2xl); width:100%;">
                    <div
                        style="flex:1; font-family: var(--wp--preset--font-family--outfit); font-size:14px; color: var(--wp--preset--color--text-normal);">
                        <?php echo wp_kses_post(get_the_excerpt()); ?>
                    </div>
                    <a href="<?php the_permalink(); ?>" class="card-article__btn">
                        <?php esc_html_e('M\'inscrire', 'wamv1'); ?>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                            <path d="M6 1L11 6L6 11M1 6h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </a>
                </div>
            </div>
        </article>
    <?php endwhile;
    wp_reset_postdata(); ?>
</section>