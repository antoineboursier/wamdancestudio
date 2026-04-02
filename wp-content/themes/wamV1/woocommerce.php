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
        <div id="section-shop-content" class="shop-content wam-shop">
            <?php woocommerce_content(); ?>
        </div>
    </div>
</main>

<?php get_footer(); ?>
