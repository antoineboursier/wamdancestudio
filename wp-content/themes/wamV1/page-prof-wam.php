<?php
/**
 * Template Name: Tous les profs
 *
 * Page de listing de l'équipe des professeur·es.
 * @package wamv1
 */

get_header();
get_template_part('template-parts/site-header');

/* ---- Données de la page courante ---- */
$page       = get_queried_object();
$page_title = $page ? get_the_title($page->ID) : esc_html__('L’équipe de professeur·es', 'wamv1');
$page_desc  = $page ? get_the_excerpt($page->ID) : '';
$icons_path = get_template_directory_uri() . '/assets/images/';
?>

<main id="primary" class="site-main">
    <div class="page-cours">
        <div class="page-layout__inner">

            <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-profs',
                'full' => true,
            ]); ?>

            <!-- ============================================================
             HERO — titre + image décorative + adresse
             ============================================================ -->
            <?php get_template_part('template-parts/page-hero', null, [
                'page'       => $page,
                'page_title' => $page_title,
                'page_desc'  => $page_desc,
                'icons_path' => $icons_path,
            ]); ?>

            <!-- ============================================================
                 CONTENU ÉDITORIAL
                 ============================================================ -->
            <div id="section-content" class="section-content wam-container">
                <div class="wam-prose">
                    <?php
                    if (have_posts()) {
                        while (have_posts()) {
                            the_post();
                            the_content();
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- ============================================================
                 GRILLE DES PROFS (Réutilisation du composant section-teachers)
                 ============================================================ -->
            <div class="wam-container">
                <?php get_template_part('template-parts/section-teachers', null, [
                    'show_title' => false,
                    'show_cta'   => false,
                    'no_pattern' => true,
                ]); ?>
            </div>

        </div><!-- .page-layout__inner -->
    </div><!-- .page-cours -->
</main>

<?php
get_template_part('template-parts/site-footer');
get_footer();
?>
