<?php
/**
 * Template part : Section Avis Clients (Gutenberg)
 * @package wamv1
 */

// On n'affiche la section que si elle contient des avis (au moins un <li> généré via Gutenberg)
if ( empty($args['content']) || strpos($args['content'], '<li') === false ) {
    return;
}

// Récupération dynamique des stats Google
$google_stats = function_exists('wamv1_get_google_business_stats') ? wamv1_get_google_business_stats() : ['rating' => '4.7', 'count' => '23'];
$google_rating = $google_stats['rating'];
$google_count  = $google_stats['count'];

$id = 'section-reviews';
if (!empty($args['block_attributes']['anchor'])) {
    $id = esc_attr($args['block_attributes']['anchor']);
}
?>

<section id="<?php echo $id; ?>" class="section-reviews" role="region" aria-labelledby="reviews-title">
    <div class="section-reviews__header">
        <h2 id="reviews-title" class="title-sign-md color-pink">
            <?php esc_html_e('C\'est vous qui le dites...', 'wamv1'); ?>
        </h2>
        <div class="section-reviews__google-badge"
            aria-label="<?php printf(esc_attr__('Note Google de %s sur 5 basée sur %s avis', 'wamv1'), $google_rating, $google_count); ?>">
            <span class="text-xs fw-bold"
                aria-hidden="true"><?php printf(esc_html__('%s/5 sur Google (%s avis)', 'wamv1'), $google_rating, $google_count); ?></span>
            <div class="stars" aria-hidden="true">★★★★★</div>
        </div>
    </div>

    <div class="section-reviews__slider-container">
        <?php echo $args['content']; ?>
    </div>

    <div class="section-reviews__footer">
        <a href="https://www.google.com/search?q=wam+dance+studio+avis" target="_blank" rel="noopener"
            class="section-reviews__link text-sm fw-bold">
            <?php esc_html_e('Voir tous les avis sur Google', 'wamv1'); ?>
            <span class="sr-only">(ouvre un nouvel onglet)</span>
        </a>
    </div>
</section>
