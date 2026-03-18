<?php
/**
 * Template part: Card réutilisable pour articles, cours et stages
 *
 * $args['variant'] = 'article' | 'cours' | 'stage'
 * Si absent, auto-détecté via post_type.
 *
 * article → photo + titre (Mallia) + date (pink) + bouton
 * cours   → photo + titre (Mallia) + sous-titre (yellow) + horaires + bouton
 * stage   → alias cours (mêmes champs ACF)
 *
 * @package wamv1
 */

$post_type = get_post_type();

/*
 * Détection de la variante :
 * 1. Si $args['variant'] est fourni par l'appelant → prioritaire
 * 2. Sinon, auto-détection via post_type WP
 * Cela permet à card-article-featured.php ou single-cours.php
 * de passer explicitement une variante indépendamment du post_type courant.
 */
if (isset($args['variant'])) {
    $variant = $args['variant'];
} elseif ($post_type === 'cours') {
    $variant = 'cours';
} elseif ($post_type === 'wam_stage' || $post_type === 'stage') {
    $variant = 'stage';
} else {
    $variant = 'article';
}

$is_article = $variant === 'article';
$is_cours   = $variant === 'cours' || $variant === 'stage';

// Sous-titre : champ ACF en priorité, fallback sur la première catégorie WP
$subtitle = function_exists('get_field') ? get_field('sous_titre') : '';
if (!$subtitle) {
    $categories = get_the_category();
    $subtitle = !empty($categories) ? esc_html($categories[0]->name) : '';
}
?>
<article id="post-<?php the_ID(); ?>"
    <?php post_class('bg-gradient-to-b flex flex-col flex-1 from-wam-bg800 gap-4 items-center min-w-[280px] max-w-[320px] w-full p-2 relative rounded-wam-xl to-wam-bg600 group hover:-translate-y-2 transition-transform duration-300'); ?>>

    <a href="<?php the_permalink(); ?>" class="absolute inset-0 z-10">
        <span class="sr-only"><?php the_title(); ?></span>
    </a>

    <!-- Photo (pas de placeholder si absente) -->
    <div class="aspect-[304/503] relative rounded-wam-lg w-full overflow-hidden shrink-0">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('medium_large', ['class' => 'absolute inset-0 object-cover w-full h-full group-hover:scale-105 transition-transform duration-500']); ?>
            <div class="absolute inset-0 bg-[rgba(21,28,50,0.7)] mix-blend-lighten pointer-events-none group-hover:bg-[rgba(21,28,50,0.5)] transition-colors duration-500"></div>
        <?php else : ?>
            <div class="absolute inset-0 bg-wam-bg500"></div>
        <?php endif; ?>
    </div>

    <!-- Contenu -->
    <div class="flex flex-col gap-8 items-center px-4 py-6 w-full flex-grow">

        <!-- Titre + sous-titre -->
        <div class="flex flex-col gap-2 items-center text-center w-full">
            <h3 class="font-mallia leading-[1.1] text-[32px] text-wam-text m-0 break-words line-clamp-2">
                <?php the_title(); ?>
            </h3>
            <?php if ($is_cours && $subtitle) : ?>
                <p class="font-outfit font-bold leading-[1.25] text-wam-md text-wam-yellow m-0">
                    <?php echo esc_html($subtitle); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Infos + bouton -->
        <div class="flex flex-col gap-8 items-center w-full mt-auto">

            <!-- Infos selon variant : jour/horaires (cours) ou date (article) -->
            <div class="flex flex-col font-outfit font-normal items-center leading-[1.25] text-center w-full">
                <?php if ($is_cours) : ?>
                    <p class="text-wam-lg text-wam-text m-0 capitalize">
                        <?php echo function_exists('get_field') && get_field('jour')
                            ? esc_html(get_field('jour'))
                            : get_the_date('l'); ?>
                    </p>
                    <p class="text-wam-md text-wam-subtext m-0">
                        <?php echo function_exists('get_field') && get_field('horaires')
                            ? esc_html(get_field('horaires'))
                            : get_the_time('H:i'); ?>
                    </p>
                <?php else : ?>
                    <p class="text-wam-sm text-wam-pink m-0">
                        <?php echo get_the_date('d/m/Y'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Bouton CTA (primary jaune) -->
            <div class="bg-wam-yellow border-[3px] border-transparent flex gap-2 items-center justify-center px-8 py-4 rounded-wam-sm group-hover:border-wam-green transition-colors duration-300">
                <span class="font-outfit font-bold leading-[1.25] text-wam-bg800 text-wam-md whitespace-nowrap">
                    Découvrir
                </span>
            </div>
        </div>
    </div>
</article>
