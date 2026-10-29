<?php
/**
 * Page « Produits WAM » (/produits-wam/).
 *
 * Gabarit sélectionné automatiquement d'après le slug. La liste est produite
 * ici (catégorie WooCommerce « produits-wam »), le contenu de la page en base
 * n'est pas rendu : rien à recopier en prod, tout part par git.
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">

        <?php while (have_posts()) : the_post(); ?>

            <?php get_template_part('template-parts/breadcrumb', null, [
                'id'   => 'breadcrumb-page',
                'full' => true,
            ]); ?>

            <?php get_template_part('template-parts/single-hero', null, [
                'id'               => 'section-page-header',
                'title_class'      => 'is-style-title-sign-lg',
                'content_modifier' => 'lg',
            ]); ?>

        <?php endwhile; ?>

        <div id="section-products" class="page-content">
            <?php if (function_exists('woocommerce_output_all_notices')) {
                woocommerce_output_all_notices();
            } ?>

            <?php
            $produits = new WP_Query([
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'tax_query'      => [[
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => 'produits-wam',
                ]],
            ]);
            ?>

            <?php if ($produits->have_posts()) : ?>
                <div class="page-products__grid">
                    <?php while ($produits->have_posts()) : $produits->the_post(); ?>
                        <?php get_template_part('template-parts/card-product'); ?>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p class="color-subtext">Aucun produit pour le moment.</p>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>

    </div>
</main>

<?php get_footer(); ?>
