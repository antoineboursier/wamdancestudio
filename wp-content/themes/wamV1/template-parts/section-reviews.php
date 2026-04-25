<?php
/**
 * Template part : Section Avis Clients (Google Business)
 * @package wamv1
 */

$reviews = [
    [
        'author' => 'Marion Eltrudis',
        'text' => "Charlotte est une excellente professeure de danse moderne, toujours à l’écoute et pleine de bienveillance. Mon rayon de soleil chaque semaine depuis 6 ans déjà ! Merci pour ce que tu nous apportes ❤️",
        'stars' => 5
    ],
    [
        'author' => 'Sarah Wimberley',
        'text' => "Ma fille de 9 ans est ravie de son année avec Charlotte. La musique est moderne et rythmée, chaque enfant s'habille comme il veut pour les cours, et la qualité de la restitution finale était excellente.",
        'stars' => 5
    ],
    [
        'author' => 'Ophélie Ghyselinck',
        'text' => "Des professeures passionnées qui proposent de la qualité à leurs élèves. Une bonne ambiance : salsa, west coast swing, latino et enfants au top !",
        'stars' => 5
    ],
    [
        'author' => 'Annabelle Cauderlier',
        'text' => "Professeures très sympas et pédagogues, souriantes et professionnelles. Des danses pour tous les goûts, en solo ou en couple. Je vous recommande vivement WAM.",
        'stars' => 5
    ],
    [
        'author' => 'Pauline Hauet',
        'text' => "Une association de danse dirigée et enseignée par des passionnés. Avec WAM, vous ne pourrez qu'aimer danser !",
        'stars' => 5
    ]
];

// Récupération dynamique des stats (Note et nombre d'avis)
$google_stats = function_exists('wamv1_get_google_business_stats') ? wamv1_get_google_business_stats() : ['rating' => '4.7', 'count' => '23'];
$google_rating = $google_stats['rating'];
$google_count  = $google_stats['count'];
?>

<section id="section-reviews" class="section-reviews wam-container" role="region" aria-labelledby="reviews-title">
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
        <div class="section-reviews__grid">
            <?php foreach ($reviews as $review): ?>
                <article class="review-card">
                    <div class="review-card__stars" aria-hidden="true">
                        <?php echo str_repeat('★', $review['stars']); ?>
                    </div>
                    <blockquote class="review-card__text text-xs">
                        <p>« <?php echo esc_html($review['text']); ?> »</p>
                    </blockquote>
                    <cite class="review-card__author text-xs fw-bold"><?php echo esc_html($review['author']); ?></cite>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section-reviews__footer">
        <a href="https://www.google.com/search?q=wam+dance+studio+avis" target="_blank" rel="noopener"
            class="section-reviews__link text-sm fw-bold">
            <?php esc_html_e('Voir tous les avis sur Google', 'wamv1'); ?>
            <span class="sr-only">(ouvre un nouvel onglet)</span>
        </a>
    </div>
</section>

