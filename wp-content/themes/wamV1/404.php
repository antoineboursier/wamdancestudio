<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">
        <section class="error-404 not-found" style="text-align: center; padding: var(--wam-spacing-3xl) var(--wam-page-mx);">
            <div class="page-content" style="align-items: center;">
                <h1 class="page-title is-style-title-sign-lg" style="margin-bottom: var(--wam-spacing-md);">Oups ! Cette page n'existe pas.</h1>
                <p class="text-lg color-subtext" style="margin-bottom: var(--wam-spacing-xl);">Il semblerait que la page que vous cherchez ait été déplacée, supprimée ou n'ait jamais existé.</p>
                <div class="error-404__actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary" style="display: inline-flex; justify-content: center;">
                        <span class="btn-label">Retourner à l'accueil</span>
                    </a>
                </div>
            </div><!-- .page-content -->
        </section><!-- .error-404 -->
    </div><!-- .page-layout__inner -->
</main><!-- #main -->

<?php
get_footer();
