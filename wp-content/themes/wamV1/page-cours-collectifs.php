<?php
/**
 * Template Name: Cours collectifs
 *
 * Page d'archive des cours collectifs.
 * Groupés par taxonomie cat_cours, filtrables par chips + recherche.
 * Le contenu Gutenberg de la page (the_content) s'affiche en fin de page.
 *
 * Icônes par terme : mapper le slug ACF sur un SVG dans /assets/images/.
 * Ajouter les slugs manquants dans $cat_icons si la taxonomie évolue.
 *
 * @package wamv1
 */

get_header();

/* ---- Données de la page courante ---- */
$page = get_queried_object();
$page_title = $page ? get_the_title($page->ID) : 'Cours collectifs';
$page_except = $page ? get_the_excerpt($page->ID) : '';
/* ---- Chemin icônes ---- */
$icons_path = get_template_directory_uri() . '/assets/images/';

/* ---- Mapping slug terme → SVG ---- */
$cat_icons = [
    'cours-solo' => 'dancer_courssolo.svg',
    'solo' => 'dancer_courssolo.svg',
    'danse-solo' => 'dancer_courssolo.svg',
    'danse-adeux' => 'dancer_adeux.svg',
    'a-deux' => 'dancer_adeux.svg',
    'danse-en-couple' => 'dancer_adeux.svg',
    'couple' => 'dancer_adeux.svg',
    'enfants' => 'dancer_warmup.svg',
    'danse-enfant' => 'dancer_warmup.svg',
    'enfants-ados' => 'dancer_warmup.svg',
    'ados' => 'dancer_warmup.svg',
];

/* ---- Termes cat_cours non vides ---- */
$terms = get_terms([
    'taxonomy' => 'cat_cours',
    'hide_empty' => true,
    'orderby' => 'menu_order',
    'order' => 'ASC',
]);
?>

<main id="primary" class="site-main">
    <div class="page-cours">

        <div class="page-layout__inner">

            <!-- ============================================================
             BREADCRUMB
             ============================================================ -->
            <?php get_template_part('template-parts/breadcrumb', null, [
                'id' => 'breadcrumb-cours-collectifs',
                'full' => true,
            ]); ?>

            <!-- ============================================================
             HERO — titre + adresse + planning | image décorative
             ============================================================ -->
            <?php get_template_part('template-parts/page-hero', null, [
                'page' => $page,
                'page_title' => $page_title,
                'page_desc' => '',
                'icons_path' => $icons_path,
                'show_planning_btn' => true,
                'planning_url' => get_permalink(get_page_by_path('planning')),
            ]); ?>

        </div><!-- .page-layout__inner -->

        <!-- ============================================================
         FILTRE — pleine largeur avec padding interne
         ============================================================ -->
        <div class="page-cours__filter-wrap wam-container">
            <?php get_template_part('template-parts/filter', null, [
                'terms' => $terms,
                'icons_path' => $icons_path,
            ]); ?>
        </div>

        <!-- ============================================================
         SECTIONS PAR TAXONOMIE
         ============================================================ -->
        <div class="wam-container" id="cours-results">

            <?php if (!is_wp_error($terms) && !empty($terms)): ?>

                <?php foreach ($terms as $term):

                    /* Requête des cours de ce terme triés par Jour puis Heure */
                    $term_query = new WP_Query([
                        'post_type' => 'cours',
                        'posts_per_page' => -1,
                        'meta_query' => [
                            'relation' => 'AND',
                            'day_clause' => [
                                'key' => 'jour_de_cours',
                                'compare' => 'EXISTS',
                            ],
                            'hour_clause' => [
                                'key' => 'heure_debut',
                                'compare' => 'EXISTS',
                            ],
                        ],
                        'orderby' => [
                            'day_clause' => 'ASC',
                            'hour_clause' => 'ASC',
                        ],
                        'tax_query' => [
                            [
                                'taxonomy' => 'cat_cours',
                                'field' => 'term_id',
                                'terms' => $term->term_id,
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

                        <div class="cours-categorie__header">
                            <span class="btn-icon cours-categorie__icon color-subtext" 
                                  style="--icon-url: url('<?php echo esc_url($icons_path . $icon_file); ?>');"
                                  aria-hidden="true"></span>
                            <h2 class="is-style-title-cool-md color-text"><?php echo esc_html($term->name); ?>&nbsp;:</h2>
                        </div>

                        <div class="cours-categorie__grid">
                            <?php while ($term_query->have_posts()):
                                $term_query->the_post(); ?>
                                <?php get_template_part('template-parts/card-cours'); ?>
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
            $autres_query = new WP_Query([
                'post_type' => 'cours',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    'day_clause' => [
                        'key' => 'jour_de_cours',
                        'compare' => 'EXISTS',
                    ],
                    'hour_clause' => [
                        'key' => 'heure_debut',
                        'compare' => 'EXISTS',
                    ],
                ],
                'orderby' => [
                    'day_clause' => 'ASC',
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
                            <?php get_template_part('template-parts/card-cours'); ?>
                        <?php endwhile; ?>
                    </div>

                </section><!-- .cours-categorie[data-cat=autres] -->
                <?php
            endif;
            wp_reset_postdata();
            ?>

        </div><!-- .wam-container #cours-results -->

        <?php
        $cours_outro = get_post_field('post_content', $page->ID ?? get_the_ID());
        if (!empty(trim($cours_outro))): ?>
            <!-- Conclusion de la page (the_content) -->
            <div class="page-cours__outro wam-container">
                <div class="wam-prose text-sm color-subtext">
                    <?php echo apply_filters('the_content', $cours_outro); ?>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- .page-cours -->
</main>

<?php
get_footer();
?>