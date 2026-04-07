<?php
/**
 * Template part: Card event (paysage)
 *
 * Réutilisable dans page-events-tous.php.
 * Doit être appelé dans une WP_Query loop (the_post() requis).
 *
 * Champs ACF (groupe "Métadonnées Events") :
 *   sous_titre      (text)       — description courte / sous-titre
 *   date_event      (date)       — format d/m/Y retourné par ACF, Ymd en DB
 *   heure_debut     (text)       — ex. "20:00"
 *   heure_de_fin    (text)       — ex. "23:00"
 *   complete_event  (true_false) — badge orange "Complet"
 *
 * Design : image paysage (405×243), pill date bas-gauche glassmorphism,
 *   titre title-cool-md color-text, sous-titre text-md color-subtext,
 *   horaires text-md color-text, CTA chevron bottom-right.
 *
 * @package wamv1
 */

$has_acf = function_exists('get_field');

/* ---- Champs ACF ---- */
$sous_titre  = $has_acf ? get_field('sous_titre')      : '';
$date_event  = $has_acf ? get_field('date_event')      : '';
$heure_debut = $has_acf ? get_field('heure_debut')     : '';
$heure_fin   = $has_acf ? get_field('heure_de_fin')    : '';
$complet     = $has_acf ? get_field('complete_event')  : false;

/* ---- Parsing date ---- */
$days_fr = [
    'Monday'    => 'Lun', 'Tuesday'  => 'Mar', 'Wednesday' => 'Mer',
    'Thursday'  => 'Jeu', 'Friday'   => 'Ven', 'Saturday'  => 'Sam', 'Sunday' => 'Dim',
];
$months_fr = [
    1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr',  5 => 'Mai',  6 => 'Juin',
    7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Octo', 11 => 'Nov', 12 => 'Déc',
];

$date_day   = '';
$date_num   = '';
$date_month = '';
$date_year  = '';

if ($date_event) {
    $dt = DateTime::createFromFormat('d/m/Y', $date_event);
    if ($dt) {
        $date_day   = $days_fr[ date('l', $dt->getTimestamp()) ] ?? '';
        $date_num   = $dt->format('j');
        $date_month = $months_fr[ (int) $dt->format('n') ] ?? '';
        $date_year  = $dt->format('Y');
    }
}

/* ---- Horaires ---- */
$horaires = ($heure_debut && $heure_fin) ? esc_html($heure_debut) . ' – ' . esc_html($heure_fin) : '';

/* ---- Classes de la card ---- */
$card_classes = ['card-event'];
if ($complet) $card_classes[] = 'card-event--complet';
?>

<article id="post-<?php the_ID(); ?>"
         class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
         data-title="<?php echo esc_attr(get_the_title()); ?>">

    <!-- ---- Media (image paysage) ---- -->
    <div class="card-event__media">

        <?php if (has_post_thumbnail()) : ?>
            <?php echo wp_get_attachment_image(
                get_post_thumbnail_id(),
                'wam-event-card',
                false,
                [
                    'class'          => 'card-event__img',
                    'data-no-overlay' => 'true',
                ]
            ); ?>
            <div class="card-event__img-overlay" aria-hidden="true"></div>
        <?php else : ?>
            <div class="card-event__img-placeholder" aria-hidden="true"></div>
        <?php endif; ?>

        <!-- Badge "Complet" — top-right -->
        <?php if ($complet) : ?>
            <div class="card-event__badge--complet text-sm fw-bold" aria-label="Événement complet">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/sad-emoji.svg"
                     width="20" height="20" alt="" aria-hidden="true">
                <span>Complet</span>
            </div>
        <?php endif; ?>

        <!-- Pill de date — bas-gauche -->
        <?php if ($date_num) : ?>
            <div class="card-event__date" aria-hidden="true">
                <span class="card-event__date-day text-sm"><?php echo esc_html($date_day); ?></span>
                <span class="card-event__date-num title-norm-md"><?php echo esc_html($date_num); ?></span>
                <span class="card-event__date-month text-lg fw-bold"><?php echo esc_html($date_month); ?></span>
                <span class="card-event__date-year text-xs"><?php echo esc_html($date_year); ?></span>
            </div>
        <?php endif; ?>

    </div><!-- .card-event__media -->

    <!-- ---- Body ---- -->
    <div class="card-event__body">

        <!-- Titre -->
        <h3 class="card-event__title title-cool-md">
            <?php the_title(); ?>
            <?php if ($sous_titre) : ?>
                <span class="card-event__subtitle"><?php echo esc_html($sous_titre); ?></span>
            <?php endif; ?>
        </h3>

        <!-- Horaires -->
        <?php if ($horaires) : ?>
            <p class="card-event__time text-md"><?php echo $horaires; ?></p>
        <?php endif; ?>

        <!-- CTA -->
        <div class="card-event__footer">
            <a href="<?php the_permalink(); ?>"
               class="card-event__cta stretched-link <?php echo $complet ? 'card-event__cta--disabled' : ''; ?>"
               aria-label="<?php echo esc_attr(get_the_title()); ?>">
                <span class="btn-icon btn-icon--sm"
                      style="--icon-url: url('<?php echo get_template_directory_uri(); ?>/assets/images/chevron-right.svg');"></span>
            </a>
        </div>

    </div><!-- .card-event__body -->

</article>
