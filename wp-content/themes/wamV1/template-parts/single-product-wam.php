<?php
/**
 * Fiche produit de la catégorie « Produits WAM » (totebags…).
 *
 * Disposition : visuel en haut à gauche (ratio natif, plafonné), puis à droite
 * catégorie (discrète), titre en Outfit, description et encart d'achat.
 * Sous 1024px tout s'empile, visuel d'abord. Réutilise la structure .page-hero
 * avec l'image placée avant le contenu.
 *
 * Appelé par woocommerce.php. Les autres produits restent sur single-product.php.
 *
 * @package wamv1
 */

while (have_posts()) :
    the_post();

    $categories    = wc_get_product_terms(get_the_ID(), 'product_cat', ['fields' => 'names']);
    $category_name = !empty($categories) ? $categories[0] : '';
    $content       = get_the_content();
    ?>

    <?php get_template_part('template-parts/breadcrumb', null, [
        'id'   => 'breadcrumb-product',
        'full' => true,
    ]); ?>

    <div id="section-product-header" class="page-hero page-hero--product-wam">

        <?php if (has_post_thumbnail()) : ?>
            <!-- Visuel : ratio natif, jamais recadré, plafonné (tokens.css) -->
            <div class="page-hero__image page-hero__image--native">
                <?php the_post_thumbnail('large', [
                    'class'         => 'page-hero__image-img',
                    'sizes'         => '(max-width: 1023px) 100vw, 560px',
                    'fetchpriority' => 'high',
                    'loading'       => 'eager',
                ]); ?>
                <div class="page-hero__image-overlay"></div>
            </div>
        <?php endif; ?>

        <div class="page-hero__content product-wam__content">

            <?php woocommerce_output_all_notices(); ?>

            <?php if ($category_name) : ?>
                <p class="product-wam__category color-subtext text-sm"><?php echo esc_html($category_name); ?></p>
            <?php endif; ?>

            <h1 class="page-hero__title is-style-title-norm-lg"><?php the_title(); ?></h1>

            <?php if (!empty(trim($content))) : ?>
                <div class="product-wam__description wam-prose">
                    <?php echo apply_filters('the_content', $content); ?>
                </div>
            <?php endif; ?>

            <!-- Encart d'achat : prix, description courte, ajout au panier (sans catégorie ni UGS) -->
            <div class="product-buy-card">
                <?php
                woocommerce_template_single_price();
                woocommerce_template_single_excerpt();
                woocommerce_template_single_add_to_cart();
                ?>
            </div>

        </div>

    </div><!-- .page-hero -->

<?php endwhile; ?>
