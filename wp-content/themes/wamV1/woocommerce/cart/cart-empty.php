<?php
/**
 * Empty cart page — override WAM
 *
 * Copié depuis WooCommerce 7.0.1 et adapté :
 * - Remplacement du message WC par un bloc personnalisé (sad emoji + texte FR)
 * - Bouton "Retour aux cours" vers /cours-collectifs
 *
 * @package wamv1
 */

defined('ABSPATH') || exit;

$sad_emoji_url = get_template_directory_uri() . '/assets/images/sad-emoji.svg';
?>

<div class="wam-cart-empty">
    <img src="<?php echo esc_url($sad_emoji_url); ?>" alt="" aria-hidden="true" class="wam-cart-empty__icon">
    <p class="wam-cart-empty__message">Aucun article dans le panier.</p>
</div>

<?php if (wc_get_page_id('shop') > 0) : ?>
    <p class="return-to-shop">
        <a class="button wc-backward" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">
            <?php echo esc_html(apply_filters('woocommerce_return_to_shop_text', __('Retour aux cours', 'wamv1'))); ?>
        </a>
    </p>
<?php endif; ?>
