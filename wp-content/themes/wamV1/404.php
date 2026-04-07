<?php
/**
 * Template 404 — Page introuvable
 *
 * Affiche : smiley + titre + description + bandeau de navigation
 * identique à celui de la page d'accueil (section-buttons).
 *
 * Pas de CSS dédié : layout.css (.page-404) + components.css (.btn-*)
 * + layout.css (.section-buttons) couvrent tout.
 *
 * @package wamv1
 */

get_header();

$icon_dir = get_template_directory_uri() . '/assets/images/';
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <section class="page-404" aria-label="Page introuvable">

            <!-- ---- Visuel : smiley + texte ---- -->
            <div class="page-404__visual">
                <span class="page-404__emoji" aria-hidden="true">
                    <?php include get_template_directory() . '/assets/images/sad-emoji.svg'; ?>
                </span>

                <h1 class="page-404__title title-cool-lg">
                    Cette page n'existe pas…
                </h1>
                <p class="page-404__desc text-md">
                    Elle a peut-être été déplacée, supprimée, ou tu as suivi un lien cassé. Pas de panique, voilà de quoi reprendre la danse&nbsp;!
                </p>
            </div>

            <!-- ---- Bandeau navigation (identique accueil) ---- -->
            <nav class="section-buttons" aria-label="Raccourcis">

                <div class="section-buttons__row">
                    <a href="<?php echo esc_url(home_url('/cours-collectifs/')); ?>" class="btn-primary" id="btn-404-cours">
                        <?php esc_html_e('Cours de danse collectifs', 'wamv1'); ?>
                        <span class="btn-icon btn-icon--sm"
                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                              aria-hidden="true"></span>
                    </a>
                    <a href="<?php echo esc_url(home_url('/stages-workshop-ateliers/')); ?>" class="btn-secondary" id="btn-404-stages">
                        <?php esc_html_e('Stages & Workshop', 'wamv1'); ?>
                        <span class="btn-icon btn-icon--sm"
                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                              aria-hidden="true"></span>
                    </a>
                </div>

                <div class="section-buttons__row">
                    <?php
                    $smart_btns = [
                        ['href' => home_url('/tarifs/'),                              'id' => 'btn-404-tarifs',       'label' => 'Nos tarifs'],
                        ['href' => home_url('/notre-ecole-wam-dance-studio/'),        'id' => 'btn-404-ecole',        'label' => "L'école"],
                        ['href' => home_url('/cours-particulier-prive/'),             'id' => 'btn-404-particuliers', 'label' => 'Cours particuliers'],
                        ['href' => home_url('/evjf-evg-animation-danse/'),            'id' => 'btn-404-evjf',        'label' => 'EVJF / EVG'],
                        ['href' => home_url('/choregraphie-de-mariage-ouvertures-de-bal/'), 'id' => 'btn-404-mariage', 'label' => 'Mariage'],
                    ];
                    foreach ($smart_btns as $btn) : ?>
                        <a href="<?php echo esc_url($btn['href']); ?>"
                           class="btn-smart"
                           id="<?php echo esc_attr($btn['id']); ?>">
                            <?php echo esc_html($btn['label']); ?>
                            <span class="btn-icon btn-icon--xs"
                                  style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                                  aria-hidden="true"></span>
                        </a>
                    <?php endforeach; ?>
                </div>

            </nav>

        </section>

    </div><!-- .page-layout__inner -->
</main>

<?php get_footer(); ?>
