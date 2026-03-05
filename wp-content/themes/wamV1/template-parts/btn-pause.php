<?php
/**
 * Template Part : Bouton Pause (composant réutilisable)
 * Paramètres :
 *   $args['id']    - ID du bouton (pour cibler depuis JS)
 *   $args['label'] - Texte du bouton
 *
 * @package wamv1
 */
$btn_id = $args['id'] ?? 'pause-btn';
$btn_label = $args['label'] ?? __('Mettre en pause', 'wamv1');
?>
<div class="btn-pause-wrapper">
    <button id="<?php echo esc_attr($btn_id); ?>" class="btn-pause" aria-pressed="false" type="button">
        <span class="btn-pause__icon" aria-hidden="true">
            <svg viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg">
                <rect x="1" y="1" width="3" height="8" rx="1" />
                <rect x="6" y="1" width="3" height="8" rx="1" />
            </svg>
        </span>
        <span class="btn-pause__label">
            <?php echo esc_html($btn_label); ?>
        </span>
    </button>
</div>