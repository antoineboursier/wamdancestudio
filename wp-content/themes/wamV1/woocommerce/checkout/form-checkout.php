<?php
/**
 * Checkout Form - WAM Premium Override
 *
 * Surcharge graphique pour disposer la page de paiement en 2 colonnes (comme le panier).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Les notices seront déplacées ou stylisées globalement
do_action( 'woocommerce_before_checkout_form', $checkout );

// Check registration
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Vous devez vous connecter pour commander.', 'wamv1' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout wam-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Validation de la commande', 'wamv1' ); ?>">

	<div class="wam-cart-layout"> <!-- Réutilisation de la grille cart : 1fr 380px -->
	
		<div class="wam-checkout-main">
			<p class="wam-checkout-required-notice text-xs color-pink mb-md">
				* Champs obligatoires
			</p>
			<?php if ( $checkout->get_checkout_fields() ) : ?>

				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div id="customer_details">
					<div class="wam-billing-details">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>
					<!-- Shipping is disabled for WAM (no physical products) -->
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php endif; ?>

			<!-- Zone de paiement déplacée ici (sous la facturation/adhérents) -->
			<div id="wam-checkout-payment">
				<h3 class="title-norm-sm color-green mb-md">Moyens de paiement</h3>
				<?php woocommerce_checkout_payment(); ?>
			</div>
		</div><!-- /.wam-checkout-main -->

		<!-- Résumé de la commande (Style Glassmorphism) -->
		<aside class="wam-cart-summary">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
			
			<h3 id="order_review_heading" class="wam-cart-summary__title title-norm-sm color-yellow"><?php esc_html_e( 'Résumé de la commande', 'wamv1' ); ?></h3>
			
			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>

			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
		</aside><!-- /.wam-cart-summary -->

	</div><!-- /.wam-cart-layout -->

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
