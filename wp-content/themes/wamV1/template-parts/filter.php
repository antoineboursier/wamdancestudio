<?php
/**
 * Template part: Barre de filtrage cours
 *
 * Réutilisable — passer via $args :
 *   $args['terms']      (array WP_Term) — termes de la taxonomie cat_cours
 *   $args['icons_path'] (string)        — chemin vers /assets/images/
 *
 * JS: cours-filter.js lit .chip[data-filter] et .cours-categorie[data-cat].
 *
 * @package wamv1
 */

$filter_terms = $args['terms'] ?? [];
$icons_path   = $args['icons_path'] ?? (get_template_directory_uri() . '/assets/images/');
?>

<div class="cours-filter" role="search" aria-label="Filtrer les cours">

    <div class="cours-filter__chips">
        <span class="cours-filter__label text-md" aria-hidden="true">Filtrer&nbsp;:</span>

        <button class="chip chip--active text-sm"
                data-filter="all"
                data-label="Tous"
                type="button"
                aria-pressed="true">
            Tous
        </button>

        <?php if (! is_wp_error($filter_terms) && ! empty($filter_terms)) : ?>
            <?php foreach ($filter_terms as $term) : ?>
                <button class="chip text-sm"
                        data-filter="<?php echo esc_attr($term->slug); ?>"
                        data-label="<?php echo esc_attr($term->name); ?>"
                        type="button"
                        aria-pressed="false">
                    <?php echo esc_html($term->name); ?>
                </button>
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
