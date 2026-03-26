<?php
/**
 * Template part : Hero des pages liste (cours collectifs, stages, etc.)
 *
 * Affiche le bloc hero commun aux pages d'archive :
 *   titre, description optionnelle, adresse, image à la une optionnelle.
 *
 * Paramètres via $args :
 *   'page'             => WP_Post|null  — objet de la page courante
 *   'page_title'       => string        — titre affiché dans le H1
 *   'page_desc'        => string        — description (optionnelle)
 *   'icons_path'       => string        — chemin vers /assets/images/
 *   'show_planning_btn'=> bool          — afficher le bouton "Voir le planning" (défaut : false)
 *   'planning_url'     => string        — URL du planning (utilisé si show_planning_btn est true)
 *
 * @package wamv1
 */

$page             = $args['page']              ?? null;
$page_title       = $args['page_title']        ?? '';
$page_desc        = $args['page_desc']         ?? '';
$icons_path       = $args['icons_path']        ?? (get_template_directory_uri() . '/assets/images/');
$show_planning    = $args['show_planning_btn'] ?? false;
$planning_url     = $args['planning_url']      ?? '';
$page_id          = is_object($page) ? $page->ID : (is_numeric($page) ? $page : 0);
$has_thumbnail    = $page_id && has_post_thumbnail($page_id);
?>

<section class="page-cours__hero <?php echo !$has_thumbnail ? 'page-cours__hero--no-media' : ''; ?>">

    <div class="page-cours__hero-text">

        <div class="page-cours__hero-head">
            <h1 class="page-header__title is-style-title-sign-lg"><?php echo esc_html($page_title); ?></h1>
            <?php if ($page_desc) : ?>
                <p class="page-cours__hero-desc"><?php echo esc_html($page_desc); ?></p>
            <?php endif; ?>
        </div>

        <div class="page-cours__address">
            <img src="<?php echo esc_url($icons_path . 'map.svg'); ?>" class="page-cours__address-icon" alt=""
                aria-hidden="true" width="24" height="24">
            <div class="page-cours__address-info">
                <span class="page-cours__address-name">WAM Dance Studio</span>
                <span class="page-cours__address-street">202 rue Jean Jaurès à Villeneuve&nbsp;d'Ascq</span>
            </div>
            <?php if ($show_planning && $planning_url) : ?>
                <a href="<?php echo esc_url($planning_url); ?>"
                    class="btn-secondary page-cours__planning-btn" aria-label="Voir le planning des cours">
                    Voir le planning
                    <span class="btn-icon btn-icon--sm"
                        style="--icon-url: url('<?php echo esc_url($icons_path . 'chevron-right.svg'); ?>');"
                        aria-hidden="true"></span>
                </a>
            <?php endif; ?>
        </div>

    </div><!-- .page-cours__hero-text -->

    <?php if ($has_thumbnail) : ?>
        <div class="page-cours__hero-media" aria-hidden="true">
            <div class="page-cours__hero-img-wrap">
                <?php echo wp_get_attachment_image(
                    get_post_thumbnail_id($page->ID),
                    'wam-page-thumbnail',
                    false,
                    ['class' => 'page-cours__hero-img']
                ); ?>
                <div class="page-cours__hero-overlay"></div>
            </div>
        </div><!-- .page-cours__hero-media -->
    <?php endif; ?>

</section><!-- .page-cours__hero -->
