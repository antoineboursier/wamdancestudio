<?php
/**
 * Template : Page d'accueil
 * Appelle les composants dans l'ordre du design Figma.
 *
 * @package wamv1
 */

get_template_part('template-parts/header', null, array('variant' => 'home'));
?>
<main id="primary" class="site-main page-home">

    <?php /* 1. HERO ---------------------------------------------------------- */ ?>
    <?php get_template_part('template-parts/hero-home'); ?>

    <?php /* 2. CARD ARTICLE (sticky posts) ------------------------------------ */ ?>
    <?php get_template_part('template-parts/card-article-featured'); ?>

    <?php /* 3. BOUTONS DE NAVIGATION RAPIDE ---------------------------------- */ ?>
    <nav class="section-buttons" aria-label="<?php esc_attr_e('Raccourcis', 'wamv1'); ?>">
        <div class="section-buttons__row">
            <a href="#cours" class="btn-primary" id="btn-cours-hebdo">
                <?php esc_html_e('Cours hebdo', 'wamv1'); ?>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M6 1L11 6L6 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </a>
            <a href="#stages" class="btn-secondary" id="btn-stages">
                <?php esc_html_e('Stages / ateliers / workshop', 'wamv1'); ?>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M6 1L11 6L6 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </a>
        </div>
        <div class="section-buttons__row">
            <a href="#ecole" class="btn-ghost" id="btn-ecole">
                <?php esc_html_e('L\'école', 'wamv1'); ?>
            </a>
            <a href="#particuliers" class="btn-ghost" id="btn-particuliers">
                <?php esc_html_e('Cours particuliers', 'wamv1'); ?>
            </a>
            <a href="#evjf" class="btn-ghost" id="btn-evjf">
                <?php esc_html_e('EVJF / EVG', 'wamv1'); ?>
            </a>
            <a href="#mariage" class="btn-ghost" id="btn-mariage">
                <?php esc_html_e('Mariage', 'wamv1'); ?>
            </a>
        </div>
    </nav>

    <?php /* 4. ZONE KEYWORDS ANIMÉE ------------------------------------------ */ ?>
    <?php get_template_part('template-parts/section-keywords'); ?>

    <?php /* 5. ZONE VIDÉOS ---------------------------------------------------- */ ?>
    <?php get_template_part('template-parts/section-videos'); ?>

    <?php /* 6. SÉPARATEUR ----------------------------------------------------- */ ?>
    <?php get_template_part('template-parts/separator'); ?>

    <?php /* 7. CONTENU ÉDITORIAL (speechValeurs — édité dans Gutenberg) ------- */ ?>
    <div class="section-content wp-block-group"
        style="max-width:1200px; margin:0 auto; padding: var(--wp--preset--spacing--6xl) var(--wp--preset--spacing--9xl);">
        <?php
        if (have_posts()) {
            while (have_posts()) {
                the_post();
                the_content();
            }
        }
        ?>
    </div>

    <?php /* 8. SÉPARATEUR ----------------------------------------------------- */ ?>
    <?php get_template_part('template-parts/separator'); ?>

    <?php /* 9. SECTION PROFESSEUR·ES ----------------------------------------- */ ?>
    <?php get_template_part('template-parts/section-teachers'); ?>

</main>
<?php get_footer(); ?>