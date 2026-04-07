<?php
/**
 * Fallback ultime — hiérarchie WP
 *
 * Ne devrait jamais s'afficher en conditions normales :
 * chaque type de contenu a son propre template (page.php, single.php,
 * home.php, front-page.php, archive.php…).
 * Si ce fichier est utilisé, c'est qu'un template manque.
 *
 * @package wamv1
 */

// Force le statut 404 pour signaler un contenu manquant
global $wp_query;
$wp_query->set_404();
status_header(404);

get_header();

$icon_dir = get_template_directory_uri() . '/assets/images/';
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <section class="page-404" aria-label="Page introuvable">

            <div class="page-404__visual">
                <span class="page-404__emoji" aria-hidden="true">
                    <?php include get_template_directory() . '/assets/images/sad-emoji.svg'; ?>
                </span>
                <h1 class="page-404__title title-cool-lg">Cette page n'existe pas…</h1>
                <p class="page-404__desc text-md">
                    Il semblerait que la page que vous cherchez ait été déplacée ou supprimée.
                </p>
            </div>

            <nav class="section-buttons" aria-label="Raccourcis">
                <div class="section-buttons__row">
                    <a href="<?php echo esc_url(home_url('/cours-collectifs/')); ?>" class="btn-primary">
                        Cours de danse collectifs
                        <span class="btn-icon btn-icon--sm"
                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                              aria-hidden="true"></span>
                    </a>
                    <a href="<?php echo esc_url(home_url('/stages-workshop-ateliers/')); ?>" class="btn-secondary">
                        Stages & Workshop
                        <span class="btn-icon btn-icon--sm"
                              style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                              aria-hidden="true"></span>
                    </a>
                </div>
                <div class="section-buttons__row">
                    <?php
                    $smart_btns = [
                        ['href' => home_url('/tarifs/'),                              'label' => 'Nos tarifs'],
                        ['href' => home_url('/notre-ecole-wam-dance-studio/'),        'label' => "L'école"],
                        ['href' => home_url('/cours-particulier-prive/'),             'label' => 'Cours particuliers'],
                        ['href' => home_url('/evjf-evg-animation-danse/'),            'label' => 'EVJF / EVG'],
                        ['href' => home_url('/choregraphie-de-mariage-ouvertures-de-bal/'), 'label' => 'Mariage'],
                    ];
                    foreach ($smart_btns as $btn) : ?>
                        <a href="<?php echo esc_url($btn['href']); ?>" class="btn-smart">
                            <?php echo esc_html($btn['label']); ?>
                            <span class="btn-icon btn-icon--xs"
                                  style="--icon-url: url('<?php echo esc_url($icon_dir . 'chevron-right.svg'); ?>');"
                                  aria-hidden="true"></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>

        </section>

    </div>
</main>

<?php get_footer(); ?>
