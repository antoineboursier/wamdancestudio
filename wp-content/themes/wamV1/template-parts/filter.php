<?php
/**
 * Template part: Barre de filtrage cours
 *
 * Réutilisable — passer via $args :
 *   $args['terms']      (array WP_Term) — termes de la taxonomie cat_cours
 *   $args['icons_path'] (string)        — chemin vers /assets/images/
 *   $args['mode']       (string)        — 'filter' (défaut) ou 'links'
 *   $args['current']    (string)        — slug du terme actif (mode 'links')
 *   $args['all_url']    (string)        — URL du chip « Tous » (mode 'links')
 *
 * Deux modes, même libellé et même structure de zone dans les deux cas — c'est
 * tout l'intérêt de garder un seul template part plutôt que d'en dupliquer un :
 *   'filter' — chips <button> filtrant côté client, une seule URL.
 *              JS: filter.js lit .chip[data-filter] et .cours-categorie[data-cat].
 *   'links'  — chips <a> pointant vers les archives /cat_cours/<slug>/.
 *              Aucun data-filter n'est émis : filter.js les ignore d'office
 *              (il ne cible que .chip[data-filter]) et seule la recherche reste active.
 *              Sert au maillage interne entre les pages de catégorie.
 *              data-label est conservé : .chip::after s'en sert pour réserver la
 *              largeur du texte en gras et éviter le décalage au survol.
 *
 * @package wamv1
 */

$filter_terms = $args['terms'] ?? [];
$icons_path   = $args['icons_path'] ?? (get_template_directory_uri() . '/assets/images/');
$mode         = $args['mode']    ?? 'filter';
$current      = $args['current'] ?? '';
$all_url      = $args['all_url'] ?? '';
$is_links     = ($mode === 'links');
?>

<div class="cours-filter" role="search" aria-label="Filtrer les cours">

    <div class="cours-filter__chips">
        <span class="cours-filter__label text-md" aria-hidden="true">Filtrer&nbsp;:</span>

        <?php if ($is_links) : ?>
            <a class="chip text-sm<?php echo $current === '' ? ' chip--active' : ''; ?>"
               href="<?php echo esc_url($all_url); ?>"
               data-label="Tous"
               <?php echo $current === '' ? 'aria-current="page"' : ''; ?>>
                Tous
            </a>
        <?php else : ?>
            <button class="chip chip--active text-sm"
                    data-filter="all"
                    data-label="Tous"
                    type="button"
                    aria-pressed="true">
                Tous
            </button>
        <?php endif; ?>

        <?php if (! is_wp_error($filter_terms) && ! empty($filter_terms)) : ?>
            <?php foreach ($filter_terms as $term) : ?>
                <?php if ($is_links) :
                    $is_current = ($term->slug === $current); ?>
                    <a class="chip text-sm<?php echo $is_current ? ' chip--active' : ''; ?>"
                       href="<?php echo esc_url(get_term_link($term)); ?>"
                       data-label="<?php echo esc_attr($term->name); ?>"
                       <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
                        <?php echo esc_html($term->name); ?>
                    </a>
                <?php else : ?>
                    <button class="chip text-sm"
                            data-filter="<?php echo esc_attr($term->slug); ?>"
                            data-label="<?php echo esc_attr($term->name); ?>"
                            type="button"
                            aria-pressed="false">
                        <?php echo esc_html($term->name); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cours-filter__search">
        <label for="cours-search-input" class="sr-only">Rechercher un cours</label>
        <div class="cours-search-wrap">
            <input type="text"
                   id="cours-search-input"
                   class="cours-search text-md"
                   placeholder="Rechercher..."
                   autocomplete="off">
            <button type="button"
                    class="cours-search-clear"
                    aria-label="Effacer la recherche">
                &times;
            </button>
            <span class="btn-icon cours-filter__search-icon color-subtext"
                  style="--icon-url: url('<?php echo $icons_path; ?>search.svg');"
                  aria-hidden="true"></span>
        </div>
    </div>

</div>
