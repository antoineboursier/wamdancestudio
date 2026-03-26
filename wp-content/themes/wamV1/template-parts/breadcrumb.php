<?php
/**
 * Template part: Breadcrumb — Fil d'Ariane (Yoast SEO)
 *
 * Délègue entièrement à yoast_breadcrumb().
 * La configuration des séparateurs et des libellés se fait dans
 * Yoast SEO > Apparence de la recherche > Fil d'Ariane.
 *
 * Paramètres via $args :
 *   'id'   (string) — attribut id HTML du <nav>. Optionnel.
 *   'full' (bool)   — true → .wam-breadcrumb--full (max-width + padding page). Défaut : false.
 *
 * @package wamv1
 */

$id   = $args['id']   ?? '';
$full = $args['full'] ?? false;

$classes = 'wam-breadcrumb';
if ($full) $classes .= ' wam-breadcrumb--full';
?>
<nav<?php echo $id ? ' id="' . esc_attr($id) . '"' : ''; ?>
    class="<?php echo esc_attr($classes); ?>"
    aria-label="Fil d'Ariane">
    <?php if (function_exists('yoast_breadcrumb')) : ?>
        <?php yoast_breadcrumb(); ?>
    <?php endif; ?>
</nav>
