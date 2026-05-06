<?php
/**
 * Template Name: Réinscription
 *
 * Page de réinscription pour les élèves.
 * Hidden (noindex) et avec ajout direct au panier dans la grille.
 *
 * @package wamv1
 */

get_header();

/* ---- Données de la page courante ---- */
$page = get_queried_object();
$page_title = $page ? get_the_title($page->ID) : 'Réinscription';

/* ---- Chemin icônes ---- */
$icons_path = get_template_directory_uri() . '/assets/images/';

/* ---- Mapping slug terme → SVG ---- */
$cat_icons = wamv1_get_cat_cours_icons();

/* ---- Termes cat_cours non vides ---- */
$terms = get_terms([
    'taxonomy' => 'cat_cours',
    'hide_empty' => true,
    'orderby' => 'menu_order',
    'order' => 'ASC',
]);
?>

<main id="primary" class="site-main">
    <div class="page-cours">

        <div class="page-layout__inner">

            <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id' => 'breadcrumb-reinscription',
                'full' => true,
            ]); ?>

            <!-- ============================================================
             HERO — titre + adresse + planning | image décorative
             ============================================================ -->
            <?php get_template_part('template-parts/page-hero', null, [
                'page' => $page,
                'page_title' => $page_title,
                'page_desc' => '',
                'icons_path' => $icons_path,
                'show_planning_btn' => false,
            ]); ?>

        </div><!-- .page-layout__inner -->

        <!-- ============================================================
         FILTRE — pleine largeur avec padding interne
         ============================================================ -->
        <div class="page-cours__filter-wrap wam-container">
            <?php get_template_part('template-parts/filter', null, [
                'terms' => $terms,
                'icons_path' => $icons_path,
            ]); ?>
        </div>

        <!-- ============================================================
         CONTENU GUTENBERG (CONSIGNES)
         ============================================================ -->
        <?php
        $content = get_post_field('post_content', $page->ID ?? get_the_ID());
        if (!empty(trim($content))): ?>
            <div class="page-cours__intro wam-container mt-lg mb-lg">
                <div class="wam-prose">
                    <?php echo apply_filters('the_content', $content); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
         SECTIONS PAR TAXONOMIE (LOOP PART)
         ============================================================ -->
        <?php get_template_part('template-parts/archive-cours-loop', null, [
            'terms'      => $terms,
            'cat_icons'  => $cat_icons,
            'icons_path' => $icons_path,
            'mode'       => 'reinscription',
        ]); ?>

    </div><!-- .page-cours -->
</main>

<?php
get_footer();
?>
