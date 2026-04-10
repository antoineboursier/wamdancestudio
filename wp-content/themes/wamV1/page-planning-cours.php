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
$wam_planning_start      = 9 * 60;  // 9h00 en minutes
$wam_planning_end        = 22 * 60; // 22h00 en minutes
$wam_planning_granularity = 15;     // minutes par ligne de grille CSS
$wam_planning_row_height  = 28;     // px par ligne (1h = 4 rows = 112px)

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

/* ---- Termes cat_cours pour la légende dynamique ---- */
$legend_terms = get_terms( [
    'taxonomy'   => 'cat_cours',
    'hide_empty' => true,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
] );
if ( is_wp_error( $legend_terms ) ) $legend_terms = [];

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

        /* Slugs des catégories du cours, pour le filtrage JS */
        $cats = wp_get_post_terms( get_the_ID(), 'cat_cours', [ 'fields' => 'slugs' ] );
        if ( is_wp_error( $cats ) ) $cats = [];

        $planning_items[] = [
            'title'      => get_the_title(),
            'permalink'  => get_permalink(),
            'sous_titre' => get_field( 'sous_titre' ),
            'col'        => $wam_day_map[ $jour ]['col'],
            'row_start'  => wamv1_time_to_grid_row( $start_min, $wam_planning_start, $wam_planning_granularity ),
            'row_end'    => wamv1_time_to_grid_row( $end_min,   $wam_planning_start, $wam_planning_granularity ),
            'debut'      => $heure_debut,
            'fin'        => $heure_fin,
            'is_enfant'  => $is_enfant,
            'complet'    => $complet,
            'cats'       => $cats, // slugs cat_cours
        ];
    }
    wp_reset_postdata();
}

/*
 * Nombre de lignes time-slot :
 * De 9h à 22h = 780 min / 15 = 52 intervalles
 * → grid-template-rows: 48px repeat(52, 28px)
 */
$grid_rows = ( $wam_planning_end - $wam_planning_start ) / $wam_planning_granularity;

/* ---- Groupement par jour pour la transcription textuelle ---- */
$transcript_by_col = [];
foreach ( $planning_items as $item ) {
    $transcript_by_col[ $item['col'] ][] = $item;
}
foreach ( $transcript_by_col as &$day_items ) {
    usort( $day_items, fn( $a, $b ) => $a['row_start'] - $b['row_start'] );
}
unset( $day_items );

get_header();
?>

<main id="primary" class="site-main page-planning">

    <!-- Breadcrumb + hero — même structure que les autres pages listing -->
    <div class="page-layout__inner">

        <?php get_template_part('template-parts/breadcrumb', null, [
            'id'   => 'breadcrumb-planning',
            'full' => true,
        ]); ?>

        <?php get_template_part('template-parts/page-hero', null, [
            'page'       => get_queried_object(),
            'page_title' => get_the_title(),
            'page_desc'  => 'Retrouvez tous nos cours sur la semaine — cliquez sur une carte pour en savoir plus.',
            'icons_path' => get_template_directory_uri() . '/assets/images/',
        ]); ?>

    </div><!-- .page-layout__inner -->

    <div class="wam-container">

        <!-- Légende / Filtres — générés dynamiquement depuis cat_cours + état Complet -->
        <div class="planning-legend" role="group" aria-label="Filtrer par type de cours">

            <!-- Bouton Tous -->
            <button class="planning-legend__item is-active"
                    data-filter="all"
                    type="button"
                    aria-pressed="true">
                Tous
            </button>

            <?php foreach ( $legend_terms as $term ) : ?>
            <button class="planning-legend__item"
                    data-filter="cat:<?php echo esc_attr( $term->slug ); ?>"
                    type="button"
                    aria-pressed="false">
                <span class="planning-legend__dot" aria-hidden="true"></span>
                <?php echo esc_html( $term->name ); ?>
            </button>
            <?php endforeach; ?>

            <!-- Filtre Cours complet (ACF) -->
            <button class="planning-legend__item planning-legend__item--complet"
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
                 style="grid-template-rows: 48px repeat(<?php echo intval( $grid_rows ); ?>, <?php echo intval( $wam_planning_row_height ); ?>px);">

                <!-- Coin supérieur gauche -->
                <div class="planning-corner" aria-hidden="true"></div>

                <!-- Séparateur weekend : ligne pointillée à gauche du samedi -->
                <div class="planning-col-weekend" style="grid-column: 7; grid-row: 1 / -1;" aria-hidden="true"></div>

                <!-- En-têtes jours (colonnes 2-8) — positionnés explicitement pour éviter
                     tout conflit avec les divs de fond (planning-col-weekend) -->
                <?php foreach ( $wam_day_map as $day_info ) : ?>
                    <div class="planning-header-day" role="columnheader"
                         style="grid-column: <?php echo $day_info['col']; ?>; grid-row: 1;">
                        <?php echo esc_html( $day_info['label'] ); ?>
                    </div>
                <?php endforeach; ?>

                <!-- Labels horaires + lignes horizontales (9h → 21h) -->
                <?php for ( $h = 9; $h <= 21; $h++ ) :
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
                    $data_cats = implode( ' ', array_map( 'sanitize_html_class', $item['cats'] ) );
                ?>
                    <a href="<?php echo esc_url( $item['permalink'] ); ?>"
                       class="<?php echo $classes; ?>"
                       data-cats="<?php echo esc_attr( implode( ' ', $item['cats'] ) ); ?>"
                       style="grid-column: <?php echo $item['col']; ?>; grid-row: <?php echo $item['row_start']; ?> / <?php echo $item['row_end']; ?>;"
                       aria-label="<?php echo esc_attr( $item['title'] . ', ' . $item['debut'] . ' – ' . $item['fin'] ); ?>">

                        <span class="planning-card__title text-sm fw-bold"><?php echo esc_html( $item['title'] ); ?></span>

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
                    <p class="planning-empty text-sm color-subtext" style="grid-column: 2 / -1; grid-row: 2 / 8;">
                        Aucun cours planifié pour le moment.
                    </p>
                <?php endif; ?>

            </div><!-- .planning-grid -->

        </div><!-- .planning-scroll-wrapper -->

        <!-- ============================================================
             TRANSCRIPTION TEXTUELLE — accessibilité
             <details>/<summary> natif : toggle sans JS, accessible par défaut.
             ============================================================ -->
        <details class="planning-transcript">
            <summary class="planning-transcript__toggle">
                <span class="planning-transcript__toggle-label text-sm fw-bold">Transcription textuelle du planning</span>
                <span class="planning-transcript__chevron" aria-hidden="true"></span>
            </summary>

            <div class="planning-transcript__body">
                <?php foreach ( $wam_day_map as $day_info ) :
                    $col = $day_info['col'];
                    if ( empty( $transcript_by_col[ $col ] ) ) continue;
                ?>
                    <div class="planning-transcript__day">
                        <h3 class="planning-transcript__day-title text-sm fw-bold">
                            <?php echo esc_html( $day_info['label'] ); ?>
                        </h3>
                        <ul class="planning-transcript__list">
                            <?php foreach ( $transcript_by_col[ $col ] as $item ) : ?>
                                <li class="planning-transcript__item">
                                    <p class="text-sm">
                                        <span class="planning-transcript__time color-muted"><?php echo esc_html( $item['debut'] . ' – ' . $item['fin'] ); ?></span>
                                        — <span class="planning-transcript__title fw-bold"><?php echo esc_html( $item['title'] ); ?></span><?php if ( $item['sous_titre'] ) : ?> <span class="color-muted">— <?php echo esc_html( $item['sous_titre'] ); ?></span><?php endif; ?>
                                        <span class="color-muted"> — <?php echo $item['is_enfant'] ? 'Enfant' : 'Adulte'; ?></span><?php if ( $item['complet'] ) : ?> <span class="color-orange fw-bold"> — Complet</span><?php endif; ?>
                                        — <a href="<?php echo esc_url( $item['permalink'] ); ?>" class="planning-transcript__link">Aller sur la page du cours</a>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>

    </div><!-- .wam-container -->

</main>

<?php get_footer(); ?>
