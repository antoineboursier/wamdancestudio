<?php
/**
 * Template : Page d'accueil
 * @package wamv1
 */
get_header();
$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<?php get_template_part('template-parts/site-header', null, array('variant' => 'home')); ?>

<main id="primary" class="site-main flex flex-col items-center w-full bg-wam-bg800">

    <?php get_template_part('template-parts/hero-home'); ?>

    <div class="flex flex-col items-center gap-14 py-12 w-full">

        <?php
        /*
         * Article à la une : template-parts/card-article-featured.php
         * Affiche le post épinglé (sticky) ou le plus récent en card horizontale.
         */
        get_template_part('template-parts/card-article-featured');
        ?>

        <?php /* Raccourcis de navigation : liens rapides vers les sections principales */ ?>
        <nav id="section-nav-shortcuts" class="flex flex-col items-center gap-6 w-full" aria-label="<?php esc_attr_e('Raccourcis', 'wamv1'); ?>">

            <div class="flex items-center justify-center gap-4 flex-wrap">
                <a href="#cours" class="btn-primary" id="btn-cours-hebdo">
                    <?php esc_html_e('Cours hebdo', 'wamv1'); ?>
                    <span class="btn-icon w-3 h-3" style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');" aria-hidden="true"></span>
                </a>
                <a href="#stages" class="btn-secondary" id="btn-stages">
                    <?php esc_html_e('Stages / ateliers / workshop', 'wamv1'); ?>
                    <span class="btn-icon w-3 h-3" style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');" aria-hidden="true"></span>
                </a>
            </div>

            <div class="flex items-center justify-center gap-4 flex-wrap">
                <?php
                $smart_btns = array(
                    array('href' => '#ecole', 'id' => 'btn-ecole', 'label' => "L'école"),
                    array('href' => '#particuliers', 'id' => 'btn-particuliers', 'label' => 'Cours particuliers'),
                    array('href' => '#evjf', 'id' => 'btn-evjf', 'label' => 'EVJF / EVG'),
                    array('href' => '#mariage', 'id' => 'btn-mariage', 'label' => 'Mariage'),
                );
                foreach ($smart_btns as $btn): ?>
                    <a href="<?php echo esc_url($btn['href']); ?>" class="btn-smart"
                        id="<?php echo esc_attr($btn['id']); ?>">
                        <?php echo esc_html($btn['label']); ?>
                        <span class="btn-icon w-2.5 h-2.5" style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron right.svg'); ?>');" aria-hidden="true"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

        <?php
        /*
         * Sections animées : mots-clés (carrousel JS) et vidéos (grille 2×2 autoplay).
         * Chaque template-part gère son propre id de section.
         */
        get_template_part('template-parts/section-keywords');
        get_template_part('template-parts/section-videos');
        get_template_part('template-parts/separator');
        ?>

        <!-- Contenu éditorial Gutenberg de la page d'accueil -->
        <div id="section-content" class="max-w-wam-content w-full mx-auto px-24 box-border">
            <?php
            /*
             * Boucle WP standard sur le contenu de la page d'accueil (page statique définie
             * dans Réglages > Lecture → "Page d'accueil statique").
             */
            if (have_posts()) {
                while (have_posts()) {
                    the_post();
                    the_content();
                }
            }
            ?>
        </div>

        <?php
        get_template_part('template-parts/separator');
        // Section professeur·es : récupère les users rôle 'professeur' et 'directrice'
        get_template_part('template-parts/section-teachers');
        ?>

    </div>
</main>
<?php get_footer(); ?>