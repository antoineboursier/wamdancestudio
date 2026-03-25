<?php
/**
 * Template part: Breadcrumb — Fil d'Ariane
 *
 * Deux modes :
 *   - Yoast  ($args['yoast'] = true) : délègue à yoast_breadcrumb().
 *   - Manuel ($args['links'])        : construit les liens manuellement.
 *
 * @param array $args {
 *   @type array  $links   Tableau de liens [{label, url}]. Mode manuel uniquement.
 *   @type string $current Label de la page active (dernier item, sans lien).
 *   @type bool   $yoast   true → use yoast_breadcrumb(). Défaut : false.
 *   @type string $id      Attribut id HTML du <nav>. Optionnel.
 *   @type bool   $full    true → .wam-breadcrumb--full (max-width + padding page). Défaut : false.
 * }
 * @package wamv1
 */

$links   = $args['links']   ?? [];
$current = $args['current'] ?? '';
$yoast   = $args['yoast']   ?? false;
$id      = $args['id']      ?? '';
$full    = $args['full']    ?? false;

$classes = 'wam-breadcrumb';
if ($full) $classes .= ' wam-breadcrumb--full';
?>
<nav<?php echo $id ? ' id="' . esc_attr($id) . '"' : ''; ?> class="<?php echo esc_attr($classes); ?>" aria-label="Fil d'Ariane">
    <?php if ($yoast && function_exists('yoast_breadcrumb')) : ?>
        <?php yoast_breadcrumb(); ?>
    <?php else : ?>
        <?php foreach ($links as $link) : ?>
            <a href="<?php echo esc_url($link['url']); ?>" class="wam-breadcrumb__link"><?php echo esc_html($link['label']); ?></a>
            <span class="wam-breadcrumb__sep" aria-hidden="true">›</span>
        <?php endforeach; ?>
        <?php if ($current) : ?>
            <span class="wam-breadcrumb__current"><?php echo esc_html($current); ?></span>
        <?php endif; ?>
    <?php endif; ?>
</nav>
