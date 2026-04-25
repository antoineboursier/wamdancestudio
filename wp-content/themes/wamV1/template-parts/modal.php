<?php
/**
 * Template Part : Modal réutilisable
 *
 * Paramètres attendus dans $args :
 * - id (string) : ID unique de la modal
 * - title (string) : Titre de la modal
 * - content_html (string) : Contenu HTML intérieur
 * - footer_html (string, optionnel) : HTML pour le bas de la modal
 * - extra_class (string, optionnel) : Classes CSS supplémentaires pour le container
 * - show_particles (bool, optionnel) : Activer ou non l'effet de particules (défaut true)
 */

$modal_id       = $args['id'] ?? 'wam-modal-default';
$modal_title    = $args['title'] ?? '';
$modal_content  = $args['content_html'] ?? '';
$modal_footer   = $args['footer_html'] ?? '';
$extra_class    = $args['extra_class'] ?? '';
$show_particles = $args['show_particles'] ?? true;

$icon_dir = get_template_directory_uri() . '/assets/images/';
?>

<div id="<?php echo esc_attr($modal_id); ?>" class="wam-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title" aria-hidden="true">
    <div class="wam-modal__overlay" data-close-modal></div>
    
    <?php if ($show_particles) : ?>
        <div class="wam-modal__particles js-nav-particles"></div>
    <?php endif; ?>

    <div class="wam-modal__container bg-page <?php echo esc_attr($extra_class); ?>">
        
        <div class="wam-modal__handle md:hidden"></div>

        <button class="wam-modal__close" data-close-modal aria-label="<?php esc_attr_e('Fermer', 'wamv1'); ?>">
            <span class="btn-icon btn-icon--md" style="--icon-url: url('<?php echo esc_url($icon_dir . 'close.svg'); ?>');"></span>
        </button>

        <?php if ($modal_title) : ?>
            <h3 id="<?php echo esc_attr($modal_id); ?>-title" class="wam-modal__title title-sign-md"><?php echo esc_html($modal_title); ?></h3>
        <?php endif; ?>

        <!-- Zone aria-live pour les erreurs d'accessibilité -->
        <div class="wam-modal__live-feedback sr-only" aria-live="polite"></div>

        <div class="wam-modal__content">
            <?php echo $modal_content; ?>
        </div>

        <?php if ($modal_footer) : ?>
            <div class="wam-modal__footer mt-lg">
                <?php echo $modal_footer; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
