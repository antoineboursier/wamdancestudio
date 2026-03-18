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
<section class="section-featured w-full max-w-wam-content px-24 box-border"
    aria-label="<?php esc_attr_e('Article à la une', 'wamv1'); ?>">
    <?php while ($query->have_posts()):
        $query->the_post(); ?>

        <article
            class="card-article relative flex items-stretch gap-4 p-2 rounded-wam-3xl border-2 border-transparent box-border transition-colors duration-200"
            style="background: var(--wam-color-accent-tertiary-bg);">

            <?php /* Pastille notification animée */ ?>
            <span class="card-article__notif absolute top-1 left-8 w-4 h-4 rounded-full bg-wam-green z-10"
                aria-label="<?php esc_attr_e('Nouveauté', 'wamv1'); ?>" role="img"></span>

            <?php /* Thumbnail : 400x196 fill */ ?>
            <div
                class="card-article__image w-[400px] h-[196px] flex-shrink-0 rounded-wam-xl overflow-hidden relative bg-wam-bg500">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('large', array(
                        'alt' => get_the_title(),
                        'class' => 'w-full h-full object-cover block',
                    )); ?>
                <?php else: ?>
                    <div class="w-full h-full bg-wam-bg500" aria-hidden="true"></div>
                <?php endif; ?>
            </div>

            <?php /* Corps */ ?>
            <div class="flex flex-1 flex-col justify-between gap-4 p-6">

                <?php /* Titre : Cholo Rhita vert — .title-cool-md */ ?>
                <h2 class="title-cool-md text-wam-green m-0">
                    <?php the_title(); ?>
                </h2>

                <div class="flex items-end justify-between gap-10 w-full">
                    <p class="text-wam-xs text-wam-text flex-1 leading-relaxed m-0 line-clamp-4">
                        <?php echo wp_kses_post(get_the_excerpt()); ?>
                    </p>

                    <a href="<?php the_permalink(); ?>" class="btn-outlined card-article__btn flex-shrink-0"
                        id="btn-card-article">
                        <?php esc_html_e("M'inscrire aux spectacles", 'wamv1'); ?>
                        <span class="btn-icon w-3 h-3"
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