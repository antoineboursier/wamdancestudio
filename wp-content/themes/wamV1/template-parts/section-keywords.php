<?php
/**
 * Template Part : Section Keywords animée
 * Les 3 mots (Bienveillance / Collectif / Confiance)
 * défilent en fondu doux via home.js
 *
 * @package wamv1
 */
?>
<section class="section-keywords" aria-label="<?php esc_attr_e('Nos valeurs', 'wamv1'); ?>">
    <p class="section-keywords__intro">
        <?php esc_html_e('Rejoins le studio pour un moment de :', 'wamv1'); ?>
    </p>

    <div class="keywords-display" aria-live="polite" aria-atomic="true" style="min-height:120px;">
        <span class="keyword-word keyword-word--1">
            {
            <?php esc_html_e('Bienveillance', 'wamv1'); ?>}
        </span>
        <span class="keyword-word keyword-word--2">
            {
            <?php esc_html_e('Collectif', 'wamv1'); ?> }
        </span>
        <span class="keyword-word keyword-word--3">
            {
            <?php esc_html_e('confiance', 'wamv1'); ?> }
        </span>
    </div>

    <?php get_template_part('template-parts/btn', 'pause', array(
        'id' => 'pause-keywords',
        'label' => __('Mettre en pause l\'animation', 'wamv1'),
    )); ?>
</section>