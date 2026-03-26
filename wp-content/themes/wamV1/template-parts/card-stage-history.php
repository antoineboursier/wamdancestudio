<?php
/**
 * Template part: Card stage (Historique)
 *
 * Affichage compact sans image pour les stages passés sur la page "Tous les stages".
 *
 * @package wamv1
 */

$has_acf = function_exists('get_field');

/* ---- Variante enfant ---- */
$is_enfant = wamv1_is_enfant_variant();

/* ---- Champs ACF ---- */
$sous_titre = $has_acf ? get_field('sous_titre') : '';
$date_stage = $has_acf ? get_field('date_stage') : '';

/* ---- Parsing date principale (Format: 24 SEPT. 25) ---- */
$months_fr_short = [
    1 => 'JANV.',  2 => 'FÉVR.',  3 => 'MARS', 4 => 'AVR.',  5 => 'MAI',  6 => 'JUIN',
    7 => 'JUIL.',  8 => 'AOÛT',   9 => 'SEPT.',10 => 'OCT.', 11 => 'NOV.', 12 => 'DÉC.'
];

$date_formatted = '';
if ($date_stage) {
    $dt = DateTime::createFromFormat('d/m/Y', $date_stage);
    if ($dt) {
        $date_num   = $dt->format('j');
        $date_month = $months_fr_short[(int) $dt->format('n')] ?? '';
        $date_year  = $dt->format('y'); // Format YY (ex: 25)
        $date_formatted = "$date_num $date_month $date_year";
    }
}

/* ---- Badge type (Couleurs) ---- */
$type_format_val = $has_acf ? get_field('type_format') : 'type_stage';
$type_map = [
    'type_stage' => 'color-yellow',
    'type_atel'  => 'color-green',
    'type_wshop' => 'color-pink',
];
$color_class = $type_map[$type_format_val] ?? 'color-yellow';

/* ---- Classes de la card ---- */
$card_classes = ['card-stage-history'];
if ($is_enfant) $card_classes[] = 'card-stage-history--enfant';

?>

<article id="post-<?php the_ID(); ?>" class="<?php echo esc_attr(implode(' ', $card_classes)); ?>">
    
    <div class="card-stage-history__date">
        <span class="text-xs fw-bold color-subtext"><?php echo esc_html($date_formatted); ?></span>
    </div>

    <div class="card-stage-history__content">
        <h3 class="card-stage-history__title title-norm-sm color-text">
            <?php the_title(); ?>
        </h3>
        <?php if ($sous_titre) : ?>
            <p class="card-stage-history__subtitle text-xs color-subtext"><?php echo esc_html($sous_titre); ?></p>
        <?php endif; ?>
    </div>

    <!-- Lien sur toute la carte -->
    <a href="<?php the_permalink(); ?>" class="card-stage-history__link stretched-link" aria-label="<?php echo esc_attr(get_the_title()); ?>"></a>

</article>
