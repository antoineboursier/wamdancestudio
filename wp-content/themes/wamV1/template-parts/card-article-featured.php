<?php
/**
 * Template Part : Card Article (sticky posts) — Horizontale
 * Proportions exactes Figma : thumbnail 400x196 fill, corps flex-1
 *
 * @package wamv1
 */

$sticky_ids = get_option('sticky_posts');
if (empty($sticky_ids)) {
    return;
}

$query = new WP_Query(array(
    'post__in' => $sticky_ids,
    'posts_per_page' => 1,
    'ignore_sticky_posts' => true,
));

if (!$query->have_posts()) {
    return;
}

$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<section class="section-featured"
    aria-label="<?php esc_attr_e('Article à la une', 'wamv1'); ?>">
    <?php while ($query->have_posts()):
        $query->the_post(); ?>

        <article class="card-article">

            <?php /* Pastille notification animée */ ?>
            <span class="card-article__notif"
                aria-label="<?php esc_attr_e('Nouveauté', 'wamv1'); ?>" role="img"></span>

            <?php /* Thumbnail : 400×196 Figma */ ?>
            <div class="card-article__image">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('large', array('alt' => get_the_title())); ?>
                <?php else: ?>
                    <div class="card-article__image-placeholder" aria-hidden="true"></div>
                <?php endif; ?>
            </div>

            <?php /* Corps */ ?>
            <div class="card-article__body">

                <?php /* Titre : Cholo Rhita vert */ ?>
                <h2 class="card-article__title">
                    <?php the_title(); ?>
                </h2>

                <div class="card-article__content-bottom">
                    <p class="card-article__excerpt text-xs">
                        <?php echo wp_kses_post(get_the_excerpt()); ?>
                    </p>

                    <a href="<?php the_permalink(); ?>" class="btn-outlined card-article__btn"
                        id="btn-card-article">
                        <?php esc_html_e("M'inscrire aux spectacles", 'wamv1'); ?>
                        <span class="btn-icon btn-icon--sm"
                            style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');"
                            aria-hidden="true">
                        </span>
                    </a>
                </div>
            </div>
        </article>

    <?php endwhile;
    wp_reset_postdata(); ?>
</section>