<?php
/**
 * Template part : Hero unifié pour page.php et single.php
 *
 * Rend la zone en-tête [titre + méta] | [image à la une optionnelle]
 * en utilisant les classes .page-hero communes à tous les singles.
 *
 * Paramètres via $args :
 *   'id'               => string  — attribut id de la section (optionnel)
 *   'title_class'      => string  — classe typo du H1, ex: 'is-style-title-sign-lg'
 *   'content_modifier' => string  — modificateur BEM de la colonne texte : 'lg' ou ''
 *   'show_date'        => bool    — afficher la date de publication (articles)
 *   'show_reading_time'=> bool    — afficher le temps de lecture
 *   'image_size'       => string  — taille WP de la miniature, ex: 'wam-page-thumbnail'
 *   'image_modifier'   => string  — modificateur BEM de l'image : 'sm' | 'lg' | ''
 *
 * Doit être appelé à l'intérieur de la boucle WP (have_posts / the_post).
 *
 * @package wamv1
 */

$id               = $args['id']                ?? '';
$title_class      = $args['title_class']       ?? 'is-style-title-sign-lg';
$content_modifier = $args['content_modifier']  ?? '';
$show_date        = $args['show_date']         ?? false;
$show_reading_time = $args['show_reading_time'] ?? false;
$image_size       = $args['image_size']        ?? 'wam-page-thumbnail';
$image_modifier   = $args['image_modifier']    ?? '';

$content_class = 'page-hero__content' . ($content_modifier ? ' page-hero__content--' . $content_modifier : '');
$image_class   = 'page-hero__image'   . ($image_modifier   ? ' page-hero__image--'   . $image_modifier   : '');

$reading_time = ($show_reading_time && function_exists('wamv1_get_reading_time'))
    ? wamv1_get_reading_time(get_the_content())
    : '';
?>

<div <?php echo $id ? 'id="' . esc_attr($id) . '"' : ''; ?> class="page-hero">

    <div class="<?php echo esc_attr($content_class); ?>">

        <h1 class="page-hero__title <?php echo esc_attr($title_class); ?>">
            <?php the_title(); ?>
        </h1>

        <?php if ($show_date) : ?>
            <!-- Dateline : date de publication + temps de lecture -->
            <div class="page-hero__dateline">
                <p class="page-hero__date">
                    Publié le <?php echo get_the_date('d/m/Y'); ?>
                    <?php
                    $u_time          = get_the_time('U');
                    $u_modified_time = get_the_modified_time('U');
                    if ($u_modified_time >= $u_time + 86400) :
                        $days_diff = round((current_time('timestamp') - $u_modified_time) / 86400);
                        if ($days_diff > 0) :
                            echo ' — Modifié il y a ' . $days_diff . ' ' . _n('jour', 'jours', $days_diff, 'wamv1');
                        endif;
                    endif;
                    ?>
                </p>
                <?php if ($reading_time) : ?>
                    <p class="page-hero__reading-time">
                        <?php echo esc_html($reading_time); ?>
                    </p>
                <?php endif; ?>
            </div>

        <?php elseif ($reading_time) : ?>
            <!-- Temps de lecture seul (pages statiques) -->
            <p class="page-hero__reading-time">
                <?php echo esc_html($reading_time); ?>
            </p>
        <?php endif; ?>

    </div><!-- .page-hero__content -->

    <?php if (has_post_thumbnail()) : ?>
        <div class="<?php echo esc_attr($image_class); ?>">
            <?php the_post_thumbnail($image_size, [
                'class' => 'page-hero__image-img',
                'fetchpriority' => 'high',
                'loading' => 'eager'
            ]); ?>
            <div class="page-hero__image-overlay"></div>
        </div>
    <?php endif; ?>

</div><!-- .page-hero -->
