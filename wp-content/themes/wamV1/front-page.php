<?php
/**
 * Template : Page d'accueil
 * @package wamv1
 */
get_header();
$icon_dir = get_template_directory_uri() . '/assets/images/';
?>

<main id="primary" class="site-main">

    <?php get_template_part('template-parts/hero-home'); ?>

    <div class="page-home__main">

        <?php
        /*
         * Article à la une : template-parts/card-article-featured.php
         * Affiche le post épinglé (sticky) ou le plus récent en card horizontale.
         */
        get_template_part('template-parts/card-article-featured');
        ?>

        <?php /* Raccourcis de navigation : liens rapides vers les sections principales */ ?>
        <nav id="section-nav-shortcuts" class="section-buttons"
            aria-label="<?php esc_attr_e('Raccourcis', 'wamv1'); ?>">

            <div class="section-buttons__row">
                <a href="<?php echo esc_url(home_url('/cours-collectifs/')); ?>" class="btn-primary" id="btn-cours-hebdo">
                    <?php esc_html_e('Cours de danse collectifs', 'wamv1'); ?>
                    <span class="btn-icon btn-icon--sm"
                        style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                        aria-hidden="true"></span>
                </a>
                <a href="<?php echo esc_url(home_url('/stages-workshop-ateliers/')); ?>" class="btn-secondary" id="btn-stages">
                    <?php esc_html_e('Stages & Workshop', 'wamv1'); ?>
                    <span class="btn-icon btn-icon--sm"
                        style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                        aria-hidden="true"></span>
                </a>
            </div>

            <div class="section-buttons__row">
                <?php
                $smart_btns = array(
                    array('href' => home_url('/tarifs/'), 'id' => 'btn-tarifs', 'label' => 'Nos tarifs'),
                    // array('href' => home_url('/notre-ecole-wam-dance-studio/'), 'id' => 'btn-ecole', 'label' => "L'école"),
                    array('href' => home_url('/cours-particulier-prive/'), 'id' => 'btn-particuliers', 'label' => 'Cours particuliers'),
                    array('href' => home_url('/evjf-evg-animation-danse/'), 'id' => 'btn-evjf', 'label' => 'EVJF / EVG'),
                    array('href' => home_url('/choregraphie-de-mariage-ouvertures-de-bal/'), 'id' => 'btn-mariage', 'label' => 'Mariage'),
                );
                foreach ($smart_btns as $btn): ?>
                    <a href="<?php echo esc_url($btn['href']); ?>" class="btn-smart"
                        id="<?php echo esc_attr($btn['id']); ?>">
                        <?php echo esc_html($btn['label']); ?>
                        <span class="btn-icon btn-icon--xs"
                            style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                            aria-hidden="true"></span>
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

        <?php
        $home_content = get_the_content();
        if (!empty(trim($home_content))): ?>
            <!-- Contenu éditorial Gutenberg de la page d'accueil -->
            <div id="section-content" class="section-content wam-prose">
                <?php echo apply_filters('the_content', $home_content); ?>
            </div>
        <?php endif; ?>

        <?php
        // Section Avis Clients
        get_template_part('template-parts/section-reviews');

        // Section professeur·es
        get_template_part('template-parts/section-teachers');

        // Section Signature (A bientôt sur le parquet)
        get_template_part('template-parts/section-signature');
        ?>

    </div>
</main>
<?php get_footer(); ?>