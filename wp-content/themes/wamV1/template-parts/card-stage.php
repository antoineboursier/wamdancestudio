<?php
/**
 * Template part: Card stage (verticale portrait)
 *
 * Réutilisable dans page-stages-tous.php.
 * Doit être appelé dans une WP_Query loop (the_post() requis).
 *
 * Champs ACF (groupe "Métadonnées Stages") :
 *   sous_titre        (text)       — niveau / description courte
 *   date_stage        (date)       — format d/m/Y retourné par ACF, Ymd en DB
 *   heure_debut       (text)       — ex. "14:00"
 *   heure_de_fin      (text)       — ex. "17:00"
 *   complete_cours    (true_false) — badge orange "Complet"
 *   mult_date_stage   (radio)      — "uniquedate" | "multidate"
 *   other_date        (relation)   — autres sessions (retourne array de WP_Post)
 *
 * Variante enfant : terme slug 'danse-enfant' dans cat_cours
 *   → --card-accent vert, border verte
 *
 * Badge type : cherché parmi les termes cat_cours
 *   (stage / atelier / workshop → affiché en majuscules)
 *
 * @package wamv1
 */

$has_acf = function_exists('get_field');

/* ---- Variante enfant ---- */
$is_enfant = wamv1_is_enfant_variant();

/* ---- Champs ACF ---- */
$sous_titre  = $has_acf ? get_field('sous_titre')       : '';
$date_stage  = $has_acf ? get_field('date_stage')       : '';
$heure_debut = $has_acf ? get_field('heure_debut')      : '';
$heure_fin   = $has_acf ? get_field('heure_de_fin')     : '';
$complet     = $has_acf ? get_field('complete_cours')   : false;
$mult_date   = $has_acf ? get_field('mult_date_stage')  : 'uniquedate';
$other_dates = ($has_acf && $mult_date === 'multidate') ? get_field('other_date') : [];

/* ---- Parsing date principale ---- */
$days_fr = [
    'Monday'    => 'Lun', 'Tuesday'  => 'Mar', 'Wednesday' => 'Mer',
    'Thursday'  => 'Jeu', 'Friday'   => 'Ven', 'Saturday'  => 'Sam', 'Sunday' => 'Dim',
];
$months_fr = [
    1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr',  5 => 'Mai',  6 => 'Juin',
    7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
];

$date_day   = '';
$date_num   = '';
$date_month = '';
$date_year  = '';

if ($date_stage) {
    $dt = DateTime::createFromFormat('d/m/Y', $date_stage);
    if ($dt) {
        $date_day   = $days_fr[ date('l', $dt->getTimestamp()) ] ?? '';
        $date_num   = $dt->format('j');
        $date_month = $months_fr[ (int) $dt->format('n') ] ?? '';
        $date_year  = $dt->format('Y');
    }
}

/* ---- Badge type (stage / atelier / workshop) via ACF type_format ---- */
$type_format_val = $has_acf ? get_field('type_format') : 'type_stage';
$type_map = [
    'type_stage' => ['label' => 'Stage',    'class' => 'card-stage--stage',    'color_class' => 'color-yellow'],
    'type_atel'  => ['label' => 'Atelier',  'class' => 'card-stage--atelier',  'color_class' => 'color-green'],
    'type_wshop' => ['label' => 'Workshop', 'class' => 'card-stage--workshop', 'color_class' => 'color-pink'],
];
$current_type = $type_map[$type_format_val] ?? $type_map['type_stage'];
$badge_label  = $current_type['label'];
$color_class  = $current_type['color_class'];

/* ---- data-cat pour le filtre JS ---- */
$term_slugs = [];
$terms      = get_the_terms(get_the_ID(), 'cat_cours');
if ($terms && ! is_wp_error($terms)) {
    foreach ($terms as $t) {
        $term_slugs[] = esc_attr($t->slug);
    }
}
$data_cat = implode(' ', $term_slugs);

/* ---- Horaires ---- */
$horaires = ($heure_debut && $heure_fin) ? esc_html($heure_debut) . ' – ' . esc_html($heure_fin) : '';

/* ---- Classes de la card ---- */
$card_classes = ['card-stage'];
if ($is_enfant) $card_classes[] = 'card-stage--enfant';
if ($complet)   $card_classes[] = 'card-stage--complet';
if (isset($current_type['class'])) $card_classes[] = $current_type['class'];
?>

<article id="post-<?php the_ID(); ?>"
         class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
         data-cat="<?php echo $data_cat; ?>"
         data-title="<?php echo esc_attr(get_the_title()); ?>">

    <!-- ---- Media (image portrait) ---- -->
    <div class="card-stage__media">

        <?php if (has_post_thumbnail()) : ?>
            <?php echo wp_get_attachment_image(
                get_post_thumbnail_id(),
                'wam-stage-card',
                false,
                [
                    'class' => 'card-stage__img',
                    'data-no-overlay' => 'true'
                ]
            ); ?>
            <div class="card-stage__img-overlay" aria-hidden="true"></div>
        <?php else : ?>
            <div class="card-stage__img-placeholder" aria-hidden="true"></div>
        <?php endif; ?>

        <!-- Badge statut — au-dessus de la date pill -->
        <?php if ($complet) : ?>
            <div class="card-stage__badge card-stage__badge--complet text-sm fw-bold" aria-label="Stage complet">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/sad-emoji.svg" 
                     width="20" height="20" alt="" aria-hidden="true">
                <span>Complet</span>
            </div>
        <?php endif; ?>

        <!-- Pill de date — bottom-left -->
        <?php if ($date_num) : ?>
            <div class="card-stage__date" aria-hidden="true">
                <?php if ($mult_date === 'multidate' && ! empty($other_dates)) :
                    /*
                     * Multi-dates : affiche les jours + numéros de tous les sessions.
                     * Ex : "Sam Dim" / "30.31" / "Juin"
                     */
                    $all_days  = [$date_day];
                    $all_nums  = [$date_num];
                    $all_months = [$date_month];
                    foreach ($other_dates as $od) {
                        $od_date = get_field('date_stage', $od->ID);
                        if ($od_date) {
                            $odt = DateTime::createFromFormat('d/m/Y', $od_date);
                            if ($odt) {
                                $all_days[]   = $days_fr[ date('l', $odt->getTimestamp()) ] ?? '';
                                $all_nums[]   = $odt->format('j');
                                $all_months[] = $months_fr[ (int) $odt->format('n') ] ?? '';
                            }
                        }
                    }
                    $unique_months = array_unique($all_months);
                ?>
                    <span class="card-stage__date-day text-sm color-subtext"><?php echo esc_html(implode(' ', $all_days)); ?></span>
                    <span class="card-stage__date-num title-norm-md <?php echo $color_class; ?>"><?php echo esc_html(implode('.', $all_nums)); ?></span>
                    <span class="card-stage__date-month text-lg fw-bold <?php echo $color_class; ?>"><?php echo esc_html(implode(' · ', $unique_months)); ?></span>
                    <span class="card-stage__date-year text-xs color-subtext"><?php echo esc_html($date_year); ?></span>
                <?php else : ?>
                    <span class="card-stage__date-day text-sm color-subtext"><?php echo esc_html($date_day); ?></span>
                    <span class="card-stage__date-num title-norm-md <?php echo $color_class; ?>"><?php echo esc_html($date_num); ?></span>
                    <span class="card-stage__date-month text-lg fw-bold <?php echo $color_class; ?>"><?php echo esc_html($date_month); ?></span>
                    <span class="card-stage__date-year text-xs color-subtext"><?php echo esc_html($date_year); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div><!-- .card-stage__media -->

    <!-- ---- Body ---- -->
    <div class="card-stage__body">

        <!-- Badge type (STAGE / ATELIER / WORKSHOP) -->
        <div class="card-stage__type text-xs fw-bold <?php echo $color_class; ?>"><?php echo esc_html($badge_label); ?></div>

        <!-- Titre -->
        <h3 class="card-stage__title title-norm-md <?php echo $color_class; ?>">
            <?php the_title(); ?>
            <?php if ($sous_titre) : ?>
                <span class="card-stage__subtitle text-md fw-bold color-subtext"><?php echo esc_html($sous_titre); ?></span>
            <?php endif; ?>
        </h3>

        <!-- Horaires -->
        <?php if ($horaires) : ?>
            <p class="card-stage__time text-md color-text"><?php echo $horaires; ?></p>
        <?php endif; ?>

        <!-- CTA -->
        <div class="card-stage__footer">
            <a href="<?php the_permalink(); ?>"
               class="card-stage__cta stretched-link <?php echo $complet ? 'card-stage__cta--disabled' : ''; ?>"
               aria-label="<?php echo esc_attr(get_the_title()); ?>">
                <span class="btn-icon btn-icon--sm"
                      style="--icon-url: url('<?php echo get_template_directory_uri(); ?>/assets/images/chevron-right.svg');"></span>
            </a>
        </div>

    </div><!-- .card-stage__body -->

</article>
