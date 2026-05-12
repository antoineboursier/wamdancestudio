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
            <?php get_template_part( 'template-parts/single', 'product' ); ?>
        <?php else : ?>
            <div id="section-shop-content" class="shop-content wam-shop">
                <?php woocommerce_content(); ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
