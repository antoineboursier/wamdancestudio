<?php
/**
 * Template part for displaying a single WooCommerce product, based on page.php
 *
 * @package wamv1
 */

while (have_posts()) :
    the_post(); 
    global $product;
    
    // Récupérer la première catégorie du produit pour le pre_title
    $categories = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
    $category_name = ! empty( $categories ) ? $categories[0] : '';
    ?>

    <!-- Breadcrumb : Accueil > Boutique > [Titre du produit] -->
    <?php get_template_part('template-parts/breadcrumb', null, [
        'id'   => 'breadcrumb-product',
        'full' => true,
    ]); ?>

    <!-- En-tête de page : titre + image à la une optionnelle -->
    <?php get_template_part('template-parts/single-hero', null, [
        'id'               => 'section-product-header',
        'title_class'      => 'is-style-title-sign-lg',
        'content_modifier' => 'lg',
        'image_size'       => 'wam-page-thumbnail',
        'image_modifier'   => 'sm',
        'pre_title'        => $category_name,
    ]); ?>

    <div id="section-product-content" class="page-content wam-container">
        <!-- Notices WooCommerce (ex: "Produit ajouté au panier") -->
        <?php woocommerce_output_all_notices(); ?>
        
        <div class="product-layout__inner">
            <?php
            $product_content = get_the_content();
            if (!empty(trim($product_content))): ?>
                <!-- Contenu (prose) du produit -->
                <div class="product-content wam-prose">
                    <?php echo apply_filters('the_content', $product_content); ?>
                </div>
            <?php endif; ?>

            <!-- Sidebar / Bloc d'achat (Prix + Bouton) -->
            <div class="product-sidebar">
                <div class="product-buy-card">
                    <?php 
                    // Affiche le prix
                    woocommerce_template_single_price(); 
                    
                    // Affiche l'extrait (description courte) s'il y en a un
                    woocommerce_template_single_excerpt();

                    // Affiche le formulaire d'ajout au panier
                    woocommerce_template_single_add_to_cart(); 
                    
                    // Affiche les méta (catégories, UGS...)
                    woocommerce_template_single_meta();
                    ?>
                </div>
            </div>
        </div>
    </div>

<?php endwhile; ?>
