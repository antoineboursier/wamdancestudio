<?php
/**
 * Card produit (page « Produits WAM »).
 *
 * Même langage visuel que card-event.php : visuel carré aux bords arrondis,
 * titre, puis prix et ajout au panier. À appeler dans une boucle WP_Query
 * (the_post() déjà fait) sur des produits WooCommerce.
 *
 * @package wamv1
 */

$product = wc_get_product(get_the_ID());
if (!$product) {
    return;
}

$thumb_id = get_post_thumbnail_id();
?>

<article id="post-<?php the_ID(); ?>" class="card-product">

    <a class="card-product__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
        <?php if ($thumb_id) :
            wamv1_square_image_ensure_sizes((int) $thumb_id);
            echo wp_get_attachment_image($thumb_id, 'wam-square-md', false, [
                'class'           => 'card-product__img',
                'sizes'           => '(max-width: 331px) 100vw, 300px',
                'data-no-overlay' => 'true',
            ]); ?>
            <span class="card-product__img-overlay" aria-hidden="true"></span>
        <?php else : ?>
            <span class="card-product__img-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
    </a>

    <div class="card-product__body">

        <h2 class="card-product__title is-style-title-norm-sm">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>

        <p class="card-product__price"><?php echo wp_kses_post($product->get_price_html()); ?></p>

        <?php if ($product->is_purchasable() && $product->is_in_stock()) : ?>
            <?php if ($product->is_type('simple')) : ?>
                <form class="card-product__cart" method="post" action="<?php echo esc_url(get_permalink()); ?>">
                    <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="btn-primary">
                        Ajouter au panier
                    </button>
                </form>
            <?php else : ?>
                <a class="btn-primary" href="<?php the_permalink(); ?>">Choisir</a>
            <?php endif; ?>
        <?php else : ?>
            <p class="card-product__unavailable color-subtext text-sm">Indisponible</p>
        <?php endif; ?>

    </div>

</article>
