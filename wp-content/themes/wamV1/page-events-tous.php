<?php
/**
 * Template Name: Tous les événements
 *
 * Page d'archive des événements (galas, soirées, spectacles…).
 * Grille 3 colonnes paysage, triée par date_event croissante.
 * Séparation événements à venir / passés.
 *
 * ACF requis : date_event (date picker), heure_debut, heure_de_fin,
 *              sous_titre, complete_event
 *
 * @package wamv1
 */

get_header();

/* ---- Données de la page courante ---- */
$current_page = get_queried_object();
$page_title   = $current_page ? get_the_title($current_page->ID) : 'Événements';
$page_desc    = $current_page ? get_the_excerpt($current_page->ID) : '';

$icons_path = get_template_directory_uri() . '/assets/images/';

/* ---- Requête tous les events publiés ---- */
/*
 * On n'utilise pas meta_key/orderby car cela filtre les events
 * sans date renseignée. Le tri par date se fait en PHP après coup.
 */
$events_query = new WP_Query([
    'post_type'      => 'evenements',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
]);

/* ---- Séparation Futur / Passé ---- */
$events_futurs = [];
$events_passes = [];
$today_ymd     = date('Ymd');

if ($events_query->have_posts()) {
    while ($events_query->have_posts()) {
        $events_query->the_post();

        $date_acf_dmY = get_field('date_event');
        $date_ymd     = '';

        if ($date_acf_dmY) {
            $dt = DateTime::createFromFormat('d/m/Y', $date_acf_dmY);
            if ($dt) {
                $date_ymd = $dt->format('Ymd');
            }
        }

        if ($date_ymd && $date_ymd < $today_ymd) {
            $events_passes[] = get_post();
        } else {
            $events_futurs[] = get_post();
        }
    }
    wp_reset_postdata();
}

/* ---- Futur : les plus proches en premier ---- */
if (!empty($events_futurs)) {
    usort($events_futurs, function ($a, $b) {
        $da  = get_field('date_event', $a->ID);
        $db  = get_field('date_event', $b->ID);
        $dta = $da ? DateTime::createFromFormat('d/m/Y', $da) : null;
        $dtb = $db ? DateTime::createFromFormat('d/m/Y', $db) : null;
        $ta  = $dta ? $dta->getTimestamp() : PHP_INT_MAX; // sans date → en dernier
        $tb  = $dtb ? $dtb->getTimestamp() : PHP_INT_MAX;
        return $ta - $tb; // croissant
    });
}

/* ---- Historique : plus récents en premier ---- */
if (!empty($events_passes)) {
    usort($events_passes, function ($a, $b) {
        $da  = get_field('date_event', $a->ID);
        $db  = get_field('date_event', $b->ID);
        $dta = $da ? DateTime::createFromFormat('d/m/Y', $da) : null;
        $dtb = $db ? DateTime::createFromFormat('d/m/Y', $db) : null;
        $ta  = $dta ? $dta->getTimestamp() : 0;
        $tb  = $dtb ? $dtb->getTimestamp() : 0;
        return $tb - $ta; // décroissant
    });
}
?>

<main id="primary" class="site-main">
    <div class="page-cours page-events">

        <div class="page-layout__inner">

            <!-- ============================================================
                 BREADCRUMB
                 ============================================================ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-events',
                'full' => true,
            ]); ?>

            <!-- ============================================================
                 HERO — titre + image décorative
                 ============================================================ -->
            <?php get_template_part('template-parts/page-hero', null, [
                'page'       => $current_page,
                'page_title' => $page_title,
                'page_desc'  => $page_desc,
                'icons_path' => $icons_path,
            ]); ?>

        </div><!-- .page-layout__inner -->

        <!-- ============================================================
             GRILLE DES ÉVÉNEMENTS À VENIR
             ============================================================ -->
        <div class="wam-container" id="events-results">

            <?php if (!empty($events_futurs)) : ?>
                <section class="cours-categorie" aria-label="Prochains événements">
                    <div class="page-events__grid">
                        <?php
                        global $post;
                        foreach ($events_futurs as $post) :
                            setup_postdata($post);
                            get_template_part('template-parts/card-event');
                        endforeach;
                        wp_reset_postdata();
                        ?>
                    </div>
                </section>
            <?php else : ?>
                <p class="page-events__empty">Aucun événement à venir pour le moment.</p>
            <?php endif; ?>

        </div><!-- .wam-container #events-results -->

        <!-- ============================================================
             HISTORIQUE (Événements passés)
             ============================================================ -->
        <?php if (!empty($events_passes)) : ?>

            <?php get_template_part('template-parts/separator'); ?>

            <section class="cours-categorie page-events__history wam-container" aria-label="Événements passés">
                <div class="cours-categorie__header">
                    <h2 class="title-cool-lg has-text-color color-subtext">Ces événements ont déjà eu lieu...</h2>
                </div>
                <div class="page-events__history-grid">
                    <?php
                    global $post;
                    foreach ($events_passes as $post) :
                        setup_postdata($post);
                        get_template_part('template-parts/card-event');
                    endforeach;
                    wp_reset_postdata();
                    ?>
                </div>
            </section>

        <?php endif; ?>

    </div><!-- .page-events -->
</main>

<?php
get_footer();
?>
