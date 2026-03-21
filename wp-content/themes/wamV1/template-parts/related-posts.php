<?php
/**
 * Template part: Related Posts
 * Affiche 3 articles récents ou similaires en bas d'un single
 *
 * @package wamv1
 */

/*
 * WP_Query pour les articles similaires :
 * - même catégorie si disponible (category__in)
 * - exclut l'article courant (post__not_in)
 * - 3 articles max, tri par date décroissante
 * wp_reset_postdata() en fin de boucle est impératif pour restaurer
 * le post global ($post) et éviter tout bug d'affichage en aval.
 */
$related_args = array(
    'post_type' => 'post',
    'posts_per_page' => 3,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
    'post__not_in' => array(get_the_ID()),
);

// Si on est sur un article avec une catégorie, essayer d'afficher la même catégorie
$categories = get_the_category();
if (!empty($categories)) {
    $related_args['category__in'] = array($categories[0]->term_id);
}

$related_query = new WP_Query($related_args);

if ($related_query->have_posts()):
    ?>

    <!-- Articles similaires -->
    <div id="section-similaires" class="section-similaires">
        <div class="section-similaires__heading">
            <span class="btn-icon section-similaires__icon" style="--icon-url: url('<?php echo get_template_directory_uri(); ?>/assets/images/dancer_kiff.svg'); --icon-size: 48px;"></span>
            <h2 class="section-similaires__title title-cool-md color-yellow">
                ça peut vous faire kiffer :
            </h2>
        </div>

        <div class="section-similaires__grid">
            <?php
            while ($related_query->have_posts()):
                $related_query->the_post();
                get_template_part('template-parts/card-article');
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </div>

<?php endif; ?>