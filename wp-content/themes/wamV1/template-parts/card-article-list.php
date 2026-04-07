<?php
/**
 * Template part: Card article listing (verticale)
 *
 * Réutilisable dans home.php et tout contexte de listing d'articles.
 * Doit être appelé dans une boucle WP (the_post() requis).
 *
 * Données WP standard : title, date, excerpt, thumbnail, permalink.
 * Pas de champs ACF — les articles natifs n'ont pas de groupe ACF dédié.
 *
 * @package wamv1
 */

$reading_time = ceil(str_word_count(strip_tags(get_the_content())) / 200);
?>

<article id="post-<?php the_ID(); ?>" class="card-article-list <?php echo has_post_thumbnail() ? '' : 'card-article-list--no-img'; ?>">

    <!-- ---- Image ---- -->
    <a href="<?php the_permalink(); ?>" class="card-article-list__media" tabindex="-1" aria-hidden="true">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('wam-card', [
                'class' => 'card-article-list__img',
                'alt'   => esc_attr(get_the_title()),
            ]); ?>
        <?php else : ?>
            <div class="card-article-list__img-placeholder" aria-hidden="true"></div>
        <?php endif; ?>
    </a>

    <!-- ---- Corps ---- -->
    <div class="card-article-list__body">

        <div class="card-article-list__meta text-xs color-subtext">
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                <?php echo esc_html(get_the_date()); ?>
            </time>
            <?php if ($reading_time) : ?>
                <span aria-hidden="true">·</span>
                <span><?php echo esc_html($reading_time); ?> min de lecture</span>
            <?php endif; ?>
        </div>

        <h2 class="card-article-list__title title-cool-md">
            <?php the_title(); ?>
        </h2>

        <?php $excerpt = get_the_excerpt();
        if ($excerpt) : ?>
            <p class="card-article-list__excerpt text-sm color-subtext">
                <?php echo wp_kses_post($excerpt); ?>
            </p>
        <?php endif; ?>

        <!-- CTA -->
        <div class="card-article-list__footer">
            <a href="<?php the_permalink(); ?>"
               class="card-article-list__cta stretched-link"
               aria-label="<?php echo esc_attr(get_the_title()); ?>">
                <span class="btn-icon btn-icon--sm"
                      style="--icon-url: url('<?php echo get_template_directory_uri(); ?>/assets/images/chevron-right.svg');"
                      aria-hidden="true"></span>
            </a>
        </div>

    </div><!-- .card-article-list__body -->

</article>
