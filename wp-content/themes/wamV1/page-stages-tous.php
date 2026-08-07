<?php
/**
 * Template Name: Tous les stages
 *
 * Page d'archive des stages, workshops et ateliers.
 * Grille verticale portrait, filtrable par chips + recherche.
 * Triée par date_stage croissante (prochains stages en premier).
 *
 * ACF requis : date_stage (date picker), heure_debut, heure_de_fin,
 *              sous_titre, complete_cours, mult_date_stage, other_date
 *
 * @package wamv1
 */

get_header();

/* ---- Données de la page courante ---- */
$current_page = get_queried_object();
$page_title   = $current_page ? get_the_title($current_page->ID) : 'Stages & workshops';
$page_desc    = $current_page ? get_the_excerpt($current_page->ID) : '';

$icons_path = get_template_directory_uri() . '/assets/images/';

/* ---- Termes cat_cours non vides (pour le filtre) ---- */
$terms = get_terms([
    'taxonomy'   => 'cat_cours',
    'hide_empty' => true,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
    'object_ids' => get_posts([
        'post_type'   => 'stages',
        'numberposts' => -1,
        'fields'      => 'ids',
        'post_status' => 'publish',
    ]),
]);

/* ---- Requête tous les stages publiés, triés par date ACF ---- */
/* ---- Requête tous les stages publiés, triés par date ACF ---- */
$stages_query = new WP_Query([
    'post_type'      => 'stages',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'meta_key'       => 'date_stage',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'no_found_rows'  => true,
]);

/* ---- Séparation Futur / Passé ---- */
/* La règle "ce stage est passé" est centralisée dans wamv1_stage_est_passe()
   (functions.php), partagée avec single-stages.php et related-content.php. */
$stages_futurs = [];
$stages_passes = [];

if ($stages_query->have_posts()) {
    while ($stages_query->have_posts()) {
        $stages_query->the_post();

        if (wamv1_stage_est_passe(get_the_ID())) {
            $stages_passes[] = get_post(); // Date passée (Historique)
        } else {
            $stages_futurs[] = get_post(); // Date future, aujourd'hui, ou sans date
        }
    }
    wp_reset_postdata();
}

// Inversion du tri de l'historique : les plus récents (les moins vieux) en premier
if (!empty($stages_passes)) {
    usort($stages_passes, function($a, $b) {
        $da = get_field('date_stage', $a->ID);
        $db = get_field('date_stage', $b->ID);
        $dta = $da ? DateTime::createFromFormat('d/m/Y', $da) : null;
        $dtb = $db ? DateTime::createFromFormat('d/m/Y', $db) : null;
        $timestamp_a = $dta ? $dta->getTimestamp() : 0;
        $timestamp_b = $dtb ? $dtb->getTimestamp() : 0;
        return $timestamp_b - $timestamp_a; // Tri décroissant
    });
}
?>

<main id="primary" class="site-main">
    <div class="page-cours page-stages">

        <div class="page-layout__inner">

            <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-stages',
                'full' => true,
            ]); ?>

            <!-- ============================================================
             HERO — titre + image décorative
             ============================================================ -->
            <?php get_template_part('template-parts/page-hero', null, [
                'page'       => $current_page,
                'page_title' => $page_title,
                'page_desc'  => '',
                'icons_path' => $icons_path,
            ]); ?>

        </div><!-- .page-layout__inner -->

    <!-- ============================================================
         FILTRE — chips par taxonomie + recherche texte
         ============================================================ -->
    <div class="page-cours__filter-wrap wam-container">
        <?php get_template_part('template-parts/filter', null, [
            'terms'      => $terms,
            'icons_path' => $icons_path,
        ]); ?>
    </div>

    <!-- ============================================================
         GRILLES DES STAGES
         ============================================================ -->
    <div class="wam-container" id="cours-results">

        <?php if (!empty($stages_futurs)) : ?>
            <section class="cours-categorie">
                <div class="page-stages__grid cours-categorie__grid">
                    <?php 
                    global $post;
                    foreach ($stages_futurs as $post) : 
                        setup_postdata($post);
                        get_template_part('template-parts/card-stage');
                    endforeach; 
                    wp_reset_postdata(); 
                    ?>
                </div>
            </section>
        <?php else : ?>
            <p class="page-stages__empty">Aucun stage à venir pour le moment.</p>
        <?php endif; ?>

    </div><!-- .wam-container #cours-results -->

    <!-- ============================================================
         ENCART NEWSLETTER
         L'URL suit le domaine courant (home_url) : locale en DDEV,
         wamdancestudio.fr en prod — comme dans template-parts/site-footer.php.
         ============================================================ -->
    <?php $newsletter_url = home_url('/newsletter/'); ?>
    <aside class="wam-container page-stages__newsletter">
        <div class="page-stages__newsletter-inner">
            <div class="page-stages__newsletter-body">
                <h2 class="page-stages__newsletter-title title-cool-md color-yellow">Pour ne rien manquer</h2>
                <?php /* Pas de color-subtext ici : sur le fond teinté de l'encart, il tombe à 3,70:1
                         en thème clair (4,65:1 sur le fond de page). La couleur de texte par défaut donne 8,22:1. */ ?>
                <p class="page-stages__newsletter-text text-md">
                    Nouveaux stages, workshops et ateliers : inscrivez-vous à la newsletter pour être prévenu·e
                    dès l'ouverture des réservations.
                </p>
            </div>
            <a href="<?php echo esc_url($newsletter_url); ?>" class="btn-primary page-stages__newsletter-cta">
                Je m'inscris à la newsletter
                <span class="btn-icon btn-icon--sm"
                    style="--icon-url: url('<?php echo esc_url($icons_path . 'chevron-right.svg'); ?>');"></span>
            </a>
        </div>
    </aside>

    <!-- ============================================================
         HISTORIQUE (Stages Passés) — Sorti du wam-container pour Full-Bleed
         ============================================================ -->
    <?php if (!empty($stages_passes)) : ?>
        
        <?php get_template_part('template-parts/separator'); ?>

        <section class="cours-categorie page-stages__history wam-container">
            <div class="cours-categorie__header">
                <h2 class="title-cool-lg has-text-color color-subtext">Ces stages ont déjà eu lieu...</h2>
            </div>
            <div class="page-stages__history-grid">
                <?php 
                global $post;
                foreach ($stages_passes as $post) : 
                    setup_postdata($post);
                    get_template_part('template-parts/card-stage-history');
                endforeach; 
                wp_reset_postdata(); 
                ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $stages_outro = get_post_field('post_content', $current_page->ID ?? get_the_ID());
    if (!empty(trim($stages_outro))): ?>
        <!-- Conclusion de la page (the_content) -->
        <div class="page-cours__outro wam-container">
            <div class="wam-prose text-sm color-subtext">
                <?php echo apply_filters('the_content', $stages_outro); ?>
            </div>
        </div>
    <?php endif; ?>

</div><!-- .page-stages -->
</main>

<?php
get_footer();
?>
