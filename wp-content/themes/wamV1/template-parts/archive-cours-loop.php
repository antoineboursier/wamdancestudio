<?php
/**
 * Template part: Archive cours loop
 * 
 * Socle commun pour l'affichage de la grille des cours collectifs,
 * groupés par taxonomie cat_cours.
 * 
 * Paramètres via $args :
 *   'terms'       (array)  — Liste des termes cat_cours à afficher.
 *   'cat_icons'   (array)  — Mapping slug terme → SVG.
 *   'icons_path'  (string) — URL du dossier des images.
 *   'mode'        (string) — 'standard' ou 'reinscription'.
 *   'show_autres' (bool)   — Section « Autres » (cours sans catégorie). Défaut : true.
 *                            À passer à false sur une archive mono-terme, où des cours
 *                            hors catégorie n'auraient rien à faire.
 *   'show_header' (bool)   — En-tête H2 + icône de chaque section. Défaut : true.
 *                            À passer à false quand le H1 de la page nomme déjà le terme.
 *
 * @package wamv1
 */

$terms       = $args['terms']       ?? [];
$cat_icons   = $args['cat_icons']   ?? [];
$icons_path  = $args['icons_path']  ?? '';
$mode        = $args['mode']        ?? 'standard';
$show_autres = $args['show_autres'] ?? true;
$show_header = $args['show_header'] ?? true;
?>

<div class="wam-container" id="cours-results">

    <?php if (!is_wp_error($terms) && !empty($terms)): ?>

        <?php foreach ($terms as $term):

            /* Requête des cours de ce terme triés par Jour puis Heure */
            $term_query = new WP_Query([
                'post_type'      => 'cours',
                'posts_per_page' => -1,
                'meta_query'     => [
                    'relation' => 'AND',
                    'day_clause' => [
                        'key'     => 'jour_de_cours',
                        'compare' => 'EXISTS',
                    ],
                    'hour_clause' => [
                        'key'     => 'heure_debut',
                        'compare' => 'EXISTS',
                    ],
                ],
                'orderby' => [
                    'day_clause'  => 'ASC',
                    'hour_clause' => 'ASC',
                ],
                'tax_query' => [
                    [
                        'taxonomy' => 'cat_cours',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ]
                ],
            ]);

            if (!$term_query->have_posts()) {
                wp_reset_postdata();
                continue;
            }

            /* Icône décorative selon le slug du terme */
            $icon_file = $cat_icons[$term->slug] ?? 'dancer_courssolo.svg';
            ?>

            <section class="cours-categorie" data-cat="<?php echo esc_attr($term->slug); ?>"
                id="cat-<?php echo esc_attr($term->slug); ?>">

                <?php if ($show_header): ?>
                    <div class="cours-categorie__header">
                        <span class="btn-icon cours-categorie__icon color-subtext"
                              style="--icon-url: url('<?php echo esc_url($icons_path . $icon_file); ?>');"
                              aria-hidden="true"></span>
                        <h2 class="is-style-title-cool-md color-text"><?php echo esc_html($term->name); ?>&nbsp;:</h2>
                    </div>
                <?php endif; ?>

                <div class="cours-categorie__grid">
                    <?php while ($term_query->have_posts()):
                        $term_query->the_post(); ?>
                        <?php get_template_part('template-parts/card-cours', null, ['mode' => $mode]); ?>
                    <?php endwhile; ?>
                </div>

            </section><!-- .cours-categorie -->

            <?php
            wp_reset_postdata();
        endforeach;
        ?>

    <?php else: ?>
        <p class="page-cours__empty">Aucun cours disponible pour le moment.</p>
    <?php endif; ?>

    <!-- ============================================================
     SECTION "AUTRES" — cours sans catégorie affiliée
     ============================================================ -->
    <?php
    if ($show_autres):
    $autres_query = new WP_Query([
        'post_type'      => 'cours',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            'day_clause' => [
                'key'     => 'jour_de_cours',
                'compare' => 'EXISTS',
            ],
            'hour_clause' => [
                'key'     => 'heure_debut',
                'compare' => 'EXISTS',
            ],
        ],
        'orderby' => [
            'day_clause'  => 'ASC',
            'hour_clause' => 'ASC',
        ],
        'tax_query' => [
            [
                'taxonomy' => 'cat_cours',
                'operator' => 'NOT EXISTS',
            ]
        ],
    ]);

    if ($autres_query->have_posts()): ?>
        <section class="cours-categorie" data-cat="autres" id="cat-autres">

            <div class="cours-categorie__header">
                <span class="btn-icon cours-categorie__icon color-subtext"
                      style="--icon-url: url('<?php echo esc_url($icons_path); ?>dancer_autres.svg');"
                      aria-hidden="true"></span>
                <h2 class="is-style-title-cool-md">Autres&nbsp;:</h2>
            </div>

            <div class="cours-categorie__grid">
                <?php while ($autres_query->have_posts()):
                    $autres_query->the_post(); ?>
                    <?php get_template_part('template-parts/card-cours', null, ['mode' => $mode]); ?>
                <?php endwhile; ?>
            </div>

        </section><!-- .cours-categorie[data-cat=autres] -->
        <?php
    endif;
    wp_reset_postdata();
    endif; // $show_autres
    ?>

</div><!-- .wam-container #cours-results -->
