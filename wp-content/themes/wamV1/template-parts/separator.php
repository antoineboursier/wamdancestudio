<?php
/**
 * Template Part : Séparateur
 * Réutilise le bg_pattern_hero.svg comme le hero
 *
 * @package wamv1
 */

$pattern_url = get_template_directory_uri() . '/assets/images/bg_pattern_color_black.svg';
?>
<div class="wam-separator" style="background-image: url('<?php echo esc_url($pattern_url); ?>')" aria-hidden="true">
</div>