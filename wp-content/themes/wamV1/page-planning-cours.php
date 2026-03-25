<?php
/**
 * Template Name: Planning des cours
 * Description:   Vue hebdomadaire de tous les cours collectifs (lundi→dimanche).
 *
 * Chaque cours est positionné dans une grille CSS par heure de début et de fin.
 * Le contenu ne provient pas du contenu de page WP — c'est une vue dynamique.
 *
 * ACF requis : jour_de_cours (select "01day"…"07day"), heure_debut, heure_de_fin
 *
 * @package wamv1
 */

/* ---- Constantes planning ---- */
$wam_planning_start      = 8 * 60;  // 8h00 en minutes
$wam_planning_end        = 22 * 60; // 22h00 en minutes
$wam_planning_granularity = 15;     // minutes par ligne de grille CSS

/* ---- Utilitaire : "12h30" → minutes ---- */
if ( ! function_exists( 'wamv1_time_to_min' ) ) {
    function wamv1_time_to_min( $time_str ) {
        if ( strpos( $time_str, 'h' ) !== false ) {
            $parts = explode( 'h', trim( $time_str ) );
            return intval( $parts[0] ) * 60 + intval( $parts[1] ?? 0 );
        }
        return 0;
    }
}

/* ---- Utilitaire : minutes → ligne CSS grid (row 1 = header jours) ---- */
if ( ! function_exists( 'wamv1_time_to_grid_row' ) ) {
    function wamv1_time_to_grid_row( $minutes, $start = 480, $granularity = 15 ) {
        return intval( ( $minutes - $start ) / $granularity ) + 2;
    }
}

/* ---- Jours : slug → [label, colonne CSS grid] ---- */
$wam_day_map = [
    '01day' => [ 'label' => 'Lundi',    'col' => 2 ],
    '02day' => [ 'label' => 'Mardi',    'col' => 3 ],
    '03day' => [ 'label' => 'Mercredi', 'col' => 4 ],
    '04day' => [ 'label' => 'Jeudi',    'col' => 5 ],
    '05day' => [ 'label' => 'Vendredi', 'col' => 6 ],
    '06day' => [ 'label' => 'Samedi',   'col' => 7 ],
    '07day' => [ 'label' => 'Dimanche', 'col' => 8 ],
];

/* ---- Requête de tous les cours publiés ---- */
$has_acf = function_exists( 'get_field' );

$all_cours_query = new WP_Query( [
    'post_type'      => 'cours',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'no_found_rows'  => true,
] );

/* ---- Construction des items ---- */
$planning_items = [];

if ( $all_cours_query->have_posts() ) {
    while ( $all_cours_query->have_posts() ) {
        $all_cours_query->the_post();

        if ( ! $has_acf ) continue;

        $jour        = get_field( 'jour_de_cours' );
        $heure_debut = get_field( 'heure_debut' );
        $heure_fin   = get_field( 'heure_de_fin' );

        /* Données obligatoires */
        if ( ! $jour || ! isset( $wam_day_map[ $jour ] ) || ! $heure_debut || ! $heure_fin ) continue;

        $start_min = wamv1_time_to_min( $heure_debut );
        $end_min   = wamv1_time_to_min( $heure_fin );

        /* Plage valide */
        if ( $start_min < $wam_planning_start || $end_min > $wam_planning_end || $start_min >= $end_min ) continue;

        $is_enfant = wamv1_is_enfant_variant();
        $complet   = get_field( 'complete_cours' );

        $planning_items[] = [
            'title'     => get_the_title(),
            'permalink' => get_permalink(),
            'sous_titre' => get_field( 'sous_titre' ),
            'col'       => $wam_day_map[ $jour ]['col'],
            'row_start' => wamv1_time_to_grid_row( $start_min, $wam_planning_start, $wam_planning_granularity ),
            'row_end'   => wamv1_time_to_grid_row( $end_min,   $wam_planning_start, $wam_planning_granularity ),
            'debut'     => $heure_debut,
            'fin'       => $heure_fin,
            'is_enfant' => $is_enfant,
            'complet'   => $complet,
        ];
    }
    wp_reset_postdata();
}

/*
 * Nombre de lignes time-slot :
 * De 8h à 22h = 840 min / 15 = 56 intervalles
 * → grid-template-rows: 48px repeat(56, 20px)
 */
$grid_rows = ( $wam_planning_end - $wam_planning_start ) / $wam_planning_granularity;

get_header();
?>

<main id="primary" class="site-main page-planning">
    <div class="wam-container">

        <!-- Breadcrumb -->
        <?php get_template_part('template-parts/breadcrumb', null, [
            'links'   => [
                ['label' => 'Accueil',          'url' => home_url('/')],
                ['label' => 'Cours collectifs', 'url' => get_permalink(get_page_by_path('cours-collectifs'))],
            ],
            'current' => 'Planning',
        ]); ?>

        <div class="page-planning__header">
            <h1 class="is-style-title-sign-lg">Planning des cours</h1>
            <p class="page-planning__desc">
                Retrouvez tous nos cours sur la semaine —
                cliquez sur une carte pour en savoir plus.
            </p>
        </div>

        <!-- Légende / Filtres (cliquer pour filtrer, multi-select) -->
        <div class="planning-legend" role="group" aria-label="Filtrer par type de cours">

            <button class="planning-legend__item"
                    data-filter="standard"
                    type="button"
                    aria-pressed="false">
                <span class="planning-legend__dot planning-legend__dot--standard" aria-hidden="true"></span>
                Cours adultes
            </button>

            <button class="planning-legend__item"
                    data-filter="enfant"
                    type="button"
                    aria-pressed="false">
                <span class="planning-legend__dot planning-legend__dot--enfant" aria-hidden="true"></span>
                Cours enfants
            </button>

            <button class="planning-legend__item"
                    data-filter="complet"
                    type="button"
                    aria-pressed="false">
                <span class="planning-legend__dot planning-legend__dot--complet" aria-hidden="true"></span>
                Cours complet
            </button>

        </div>

        <!-- Wrapper scrollable horizontal (mobile) -->
        <div class="planning-scroll-wrapper">

            <div class="planning-grid"
                 role="grid"
                 aria-label="Planning hebdomadaire des cours WAM"
                 style="grid-template-rows: 48px repeat(<?php echo intval( $grid_rows ); ?>, 20px);">

                <!-- Coin supérieur gauche -->
                <div class="planning-corner" aria-hidden="true"></div>

                <!-- En-têtes jours (colonnes 2-8) -->
                <?php foreach ( $wam_day_map as $day_info ) : ?>
                    <div class="planning-header-day" role="columnheader">
                        <?php echo esc_html( $day_info['label'] ); ?>
                    </div>
                <?php endforeach; ?>

                <!-- Labels horaires + lignes horizontales (8h → 21h) -->
                <?php for ( $h = 8; $h <= 21; $h++ ) :
                    $row = wamv1_time_to_grid_row( $h * 60, $wam_planning_start, $wam_planning_granularity );
                ?>
                    <div class="planning-time-label"
                         style="grid-row: <?php echo $row; ?>;"
                         aria-hidden="true">
                        <?php echo $h; ?>h
                    </div>
                    <div class="planning-hour-line"
                         style="grid-row: <?php echo $row; ?>;"
                         aria-hidden="true"></div>
                <?php endfor; ?>

                <!-- Cards cours -->
                <?php foreach ( $planning_items as $item ) :
                    $classes = 'planning-card';
                    if ( $item['is_enfant'] ) $classes .= ' planning-card--enfant';
                    if ( $item['complet']   ) $classes .= ' planning-card--complet';
                ?>
                    <a href="<?php echo esc_url( $item['permalink'] ); ?>"
                       class="<?php echo $classes; ?>"
                       style="grid-column: <?php echo $item['col']; ?>; grid-row: <?php echo $item['row_start']; ?> / <?php echo $item['row_end']; ?>;"
                       aria-label="<?php echo esc_attr( $item['title'] . ', ' . $item['debut'] . ' – ' . $item['fin'] ); ?>">

                        <span class="planning-card__title"><?php echo esc_html( $item['title'] ); ?></span>

                        <?php if ( $item['sous_titre'] ) : ?>
                            <span class="planning-card__subtitle"><?php echo esc_html( $item['sous_titre'] ); ?></span>
                        <?php endif; ?>

                        <span class="planning-card__time"><?php echo esc_html( $item['debut'] . ' – ' . $item['fin'] ); ?></span>

                        <?php if ( $item['complet'] ) : ?>
                            <span class="planning-card__complet-badge" aria-label="Cours complet">Complet</span>
                        <?php endif; ?>

                    </a>
                <?php endforeach; ?>

                <?php if ( empty( $planning_items ) ) : ?>
                    <p class="planning-empty" style="grid-column: 2 / -1; grid-row: 2 / 8;">
                        Aucun cours planifié pour le moment.
                    </p>
                <?php endif; ?>

            </div><!-- .planning-grid -->

        </div><!-- .planning-scroll-wrapper -->

    </div><!-- .wam-container -->
</main>

<?php get_footer(); ?>
