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
} elseif ($post_type === 'evenements') {
    $variant = 'evenement';
} else {
    $variant = 'article';
}

$is_article = $variant === 'article';
$is_cours   = $variant === 'cours' || $variant === 'stage' || $variant === 'evenement';

// Sous-titre : champ ACF en priorité, fallback sur la première catégorie WP
$subtitle = function_exists('get_field') ? get_field('sous_titre') : '';
if (!$subtitle) {
    $categories = get_the_category();
    $subtitle = !empty($categories) ? esc_html($categories[0]->name) : '';
}
?>
<article id="post-<?php the_ID(); ?>"
    <?php post_class('card-similaire card-similaire--' . $variant); ?>>

    <?php $card_title = get_the_title(); ?>

    <!-- Photo (masquée si absente) -->
    <?php if (has_post_thumbnail()) : ?>
        <div class="card-similaire__photo">
            <?php echo wamv1_get_image_with_overlay(get_post_thumbnail_id(), 'medium_large', 'card-similaire__img-wrapper', ['class' => 'card-similaire__img']); ?>
        </div>
    <?php endif; ?>

    <!-- Contenu -->
    <div class="card-similaire__body">

        <!-- Titre + sous-titre -->
        <div class="card-similaire__header">
            <?php
            /*
             * Le lien est porté par le titre. L'::after s'étend sur toute la carte
             * via CSS (position: absolute; inset: 0) — évite le lien overlay
             * qui bloquait l'inspecteur et les éléments enfants.
             */
            ?>
            <h3 class="card-similaire__title <?php echo $is_article ? 'title-norm-sm has-text-normal-color' : 'title-sign-sm has-accent-yellow-color'; ?>">
                <a href="<?php the_permalink(); ?>" class="card-similaire__link">
                    <?php echo esc_html($card_title); ?>
                </a>
            </h3>
            <?php if ($is_cours && $subtitle) : ?>
                <p class="card-similaire__subtitle text-md">
                    <?php echo esc_html($subtitle); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Infos + bouton -->
        <div class="card-similaire__footer">

            <!-- Infos selon variant : jour/horaires (cours) ou date (article) -->
            <div class="card-similaire__meta">
                <?php if ($is_cours) :
                    if ($variant === 'stage') {
                        $date_val = function_exists('get_field') ? get_field('date_stage') : null;
                        $jour_label = $date_val;
                    } elseif ($variant === 'evenement') {
                        $date_val = function_exists('get_field') ? get_field('date_event') : null;
                        $jour_label = $date_val;
                    } else {
                        $jour_val = function_exists('get_field') ? get_field('jour_de_cours') : null;
                        $jour_label = wamv1_get_day_label($jour_val);
                    }

                    if ($variant === 'evenement') {
                        $heure_deb = function_exists('get_field') ? get_field('horaire_debut_event') : null;
                        $heure_f   = function_exists('get_field') ? get_field('horaire_fin_event') : null;
                    } else {
                        $heure_deb = function_exists('get_field') ? get_field('heure_debut') : null;
                        $heure_f   = function_exists('get_field') ? get_field('heure_de_fin') : null;
                    }

                    // Formatage horaires
                    $horaires_label = '';
                    if ($heure_deb && $heure_f) {
                        $horaires_label = $heure_deb . ' – ' . $heure_f;
                    } elseif ($heure_deb) {
                        $horaires_label = $heure_deb;
                    }
                    ?>
                    <p class="card-similaire__day text-md <?php echo ($variant === 'stage') ? 'fw-bold' : ''; ?>">
                        <?php echo $jour_label ? esc_html($jour_label) : '&nbsp;'; ?>
                    </p>
                    <p class="card-similaire__time text-sm">
                        <?php echo $horaires_label ? esc_html($horaires_label) : '&nbsp;'; ?>
                    </p>
                <?php else : ?>
                    <p class="card-similaire__date text-xs">
                        <?php echo get_the_date('d/m/Y'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Bouton CTA -->
            <div class="card-similaire__cta">
                <span class="btn-secondary" aria-hidden="true">
                    Découvrir
                    <span class="btn-icon btn-icon--sm" style="--icon-url: url('<?php echo get_template_directory_uri(); ?>/assets/images/chevron-right.svg');"></span>
                </span>
            </div>
        </div>
    </div>
</article>
