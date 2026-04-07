<?php
/**
 * Template : Page des articles (Blog)
 *
 * Utilisé automatiquement par WordPress quand une page est définie comme
 * "Page des articles" dans Réglages → Lecture.
 *
 * Hiérarchie WP : home.php > index.php
 * get_queried_object() retourne l'objet WP_Post de la page configurée,
 * ce qui affiche le bouton "Modifier" dans la barre d'admin.
 *
 * @package wamv1
 */

get_header();

$page         = get_queried_object();
$page_title   = $page ? get_the_title($page->ID) : 'Actualités';
$page_desc    = $page ? get_the_excerpt($page->ID) : '';
$icons_path   = get_template_directory_uri() . '/assets/images/';
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
        <?php get_template_part('template-parts/breadcrumb', null, [
            'id'   => 'breadcrumb-blog',
            'full' => true,
        ]); ?>

        <!-- ============================================================
             HERO — titre de la page blog
             ============================================================ -->
        <section class="page-blog__hero">
            <h1 class="page-blog__title title-sign-lg"><?php echo esc_html($page_title); ?></h1>
            <?php if ($page_desc) : ?>
                <p class="page-blog__desc text-md color-subtext"><?php echo esc_html($page_desc); ?></p>
            <?php endif; ?>
        </section>

    </div><!-- .page-layout__inner -->

    <!-- ============================================================
         GRILLE DES ARTICLES
         ============================================================ -->
    <div class="wam-container" id="blog-results">

        <?php if (have_posts()) : ?>

            <div class="page-blog__grid">
                <?php while (have_posts()) :
                    the_post();
                    get_template_part('template-parts/card-article-list');
                endwhile; ?>
            </div>

            <!-- Pagination -->
            <nav class="page-blog__pagination" aria-label="Navigation entre les pages d'articles">
                <?php the_posts_pagination([
                    'prev_text' => '← Précédent',
                    'next_text' => 'Suivant →',
                    'mid_size'  => 2,
                ]); ?>
            </nav>

        <?php else : ?>
            <p class="page-blog__empty text-md color-subtext">Aucun article pour le moment.</p>
        <?php endif; ?>

    </div><!-- .wam-container -->

</main>

<?php get_footer(); ?>
