<?php
/**
 * Template part: Related Content (Articles, Cours, Stages)
 * Affiche 3 contenus similaires en bas d'un single de façon centralisée (DRY).
 *
 * Paramètres optionnels via $args :
 *   'post_type'  => string (par défaut le post_type courant)
 *   'current_id' => int (par défaut get_the_ID())
 *   'title'      => string (par défaut "ça peut vous faire kiffer :")
 *   'icon'       => string (par défaut "dancer_kiff.svg")
 *
 * @package wamv1
 */

$post_type  = $args['post_type'] ?? get_post_type();
$current_id = $args['current_id'] ?? get_the_ID();
$title      = $args['title'] ?? 'ça peut vous faire kiffer :';
$icon       = $args['icon'] ?? 'dancer_kiff.svg';

$icon_dir   = get_template_directory_uri() . '/assets/images/';

/* ---- Construction des arguments WP_Query ---- */
$query_args = [
    'post_type'      => $post_type,
    'posts_per_page' => 3,
    'post_status'    => 'publish',
    'post__not_in'   => [$current_id],
];

// Si Article (blog) -> ordre chronologique, catégorie commune si possible.
if ($post_type === 'post') {
    $query_args['orderby'] = 'date';
    $query_args['order']   = 'DESC';
    $categories = get_the_category($current_id);
    if (! empty($categories)) {
        $query_args['category__in'] = [$categories[0]->term_id];
    }
} else {
    // Si Cours ou Stages -> ordre aléatoire
    $query_args['orderby'] = 'rand';
}

$related_query = new WP_Query($query_args);

/* ---- Détection de la variante de card ---- */
$variant = '';
if ($post_type === 'cours') {
    $variant = 'cours';
} elseif ($post_type === 'stages') {
    $variant = 'stage';
}

if ($related_query->have_posts()) :
?>

    <div id="section-similaires" class="section-similaires section-similaires--<?php echo esc_attr($post_type); ?>">
        <div class="section-similaires__heading">
            <span class="btn-icon section-similaires__icon"
                  style="--icon-url: url('<?php echo esc_url($icon_dir . $icon); ?>'); --icon-size: 48px;">
            </span>
            <h2 class="section-similaires__title title-cool-md color-yellow">
                <?php echo esc_html($title); ?>
            </h2>
        </div>

        <div class="section-similaires__grid">
            <?php
            while ($related_query->have_posts()) : $related_query->the_post();
                if ($variant) {
                    get_template_part('template-parts/card-similaire', null, ['variant' => $variant]);
                } else {
                    get_template_part('template-parts/card-similaire');
                }
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </div>

<?php endif; ?>
