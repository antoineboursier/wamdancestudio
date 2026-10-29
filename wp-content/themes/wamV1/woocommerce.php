<?php
/**
 * The template for displaying WooCommerce content
 *
 * @package wamv1
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="page-layout__inner">
        <?php if ( is_singular( 'product' ) ) : ?>
            <?php
            // Catégorie « Produits WAM » (totebags…) : fiche dédiée, les autres produits gardent le gabarit commun.
            if ( function_exists( 'wamv1_is_wam_product' ) && wamv1_is_wam_product( get_the_ID() ) ) {
                get_template_part( 'template-parts/single-product', 'wam' );
            } else {
                get_template_part( 'template-parts/single', 'product' );
            }
            ?>
        <?php else : ?>
            <div id="section-shop-content" class="shop-content wam-shop">
                <?php woocommerce_content(); ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
