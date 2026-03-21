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
get_template_part('template-parts/site-header');

/* ---- Données de la page courante ---- */
$page       = get_queried_object();
$page_title = $page ? get_the_title($page->ID) : 'Stages & workshops';
$page_desc  = $page ? get_the_excerpt($page->ID) : '';

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
/*
 * ACF date_picker stocke en interne au format Ymd (sans séparateur).
 * orderby => 'meta_value' trie donc correctement chronologiquement.
 */
$stages_query = new WP_Query([
    'post_type'      => 'stages',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'meta_key'       => 'date_stage',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'no_found_rows'  => true,
]);
?>

<main id="primary" class="site-main">
<div class="page-stages">

    <div class="wam-container">

        <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
        <nav class="page-cours__breadcrumb" aria-label="Fil d'Ariane">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="breadcrumb__link">Accueil</a>
            <span class="breadcrumb__sep" aria-hidden="true"> › </span>
            <span class="breadcrumb__current"><?php echo esc_html($page_title); ?></span>
        </nav>

        <!-- ============================================================
             HERO — titre + image décorative
             ============================================================ -->
        <section class="page-cours__hero">

            <div class="page-cours__hero-text">

                <div class="page-cours__hero-head">
                    <h1 class="is-style-title-sign-lg"><?php echo esc_html($page_title); ?></h1>
                    <?php if ($page_desc) : ?>
                        <p class="page-cours__hero-desc"><?php echo esc_html($page_desc); ?></p>
                    <?php endif; ?>
                </div>

                <div class="page-cours__address">
                    <img src="<?php echo $icons_path; ?>map.svg"
                         class="page-cours__address-icon"
                         alt=""
                         aria-hidden="true"
                         width="24"
                         height="24">
                    <div class="page-cours__address-info">
                        <span class="page-cours__address-name">WAM Dance Studio</span>
                        <span class="page-cours__address-street">202 rue Jean Jaurès à Villeneuve&nbsp;d'Ascq</span>
                    </div>
                </div>

            </div><!-- .page-cours__hero-text -->

            <div class="page-cours__hero-media" aria-hidden="true">
                <?php if ($page && has_post_thumbnail($page->ID)) : ?>
                    <div class="page-cours__hero-img-wrap">
                        <?php echo wp_get_attachment_image(
                            get_post_thumbnail_id($page->ID),
                            'large',
                            false,
                            ['class' => 'page-cours__hero-img']
                        ); ?>
                        <div class="page-cours__hero-overlay"></div>
                    </div>
                <?php else : ?>
                    <div class="page-cours__hero-img-wrap page-cours__hero-img-wrap--svg">
                        <img src="<?php echo $icons_path; ?>dancer_courssolo.svg"
                             class="page-cours__hero-svg"
                             alt="">
                    </div>
                <?php endif; ?>
            </div><!-- .page-cours__hero-media -->

        </section><!-- .page-cours__hero -->

    </div><!-- .wam-container -->

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
         GRILLE DES STAGES
         ============================================================ -->
    <div class="wam-container" id="cours-results">

        <?php if ($stages_query->have_posts()) : ?>

            <div class="page-stages__grid">

                <?php while ($stages_query->have_posts()) : $stages_query->the_post(); ?>
                    <?php get_template_part('template-parts/card-stage'); ?>
                <?php endwhile; ?>

            </div>

        <?php else : ?>
            <p class="page-stages__empty">Aucun stage disponible pour le moment.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>

    </div><!-- .wam-container #cours-results -->

</div><!-- .page-stages -->
</main>

<?php
get_template_part('template-parts/site-footer');
get_footer();
?>
