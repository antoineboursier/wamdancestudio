<?php
/**
 * Review Order — WAM Premium Override
 *
 * Remplace le tableau WC par un résumé compact "cartes" pour l'aside
 * .wam-cart-summary du checkout. Affiche titre du cours (CPT), sous-titre,
 * variation (Adulte/Enfant), créneau (jour/heure) ou date de stage, prix ligne.
 *
 * Les hooks WC d'origine sont préservés pour rester compatibles avec les
 * extensions (taxes, shipping, frais, coupons, etc.).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package wamv1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wam-order-review">

	<?php do_action( 'woocommerce_review_order_before_cart_contents' ); ?>
	<ul class="wam-order-review__list">
		<?php


		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );

			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
				continue;
			}
			if ( ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}

			// — Données WAM liées à l'article (même logique que cart.php) —
			$course_id       = $cart_item['wam_course_id'] ?? null;
			$course_title    = $course_id && function_exists( 'get_field' ) ? get_the_title( $course_id ) : null;
			$course_subtitle = $course_id && function_exists( 'get_field' ) ? get_field( 'sous_titre', $course_id ) : null;

			// Créneau horaire — récupéré du cours (plus fiable que la session)
			$wam_jour  = null;
			$wam_heure = null;
			if ( $course_id && function_exists( 'get_field' ) ) {
				$jour_slug = get_field( 'jour_de_cours', $course_id );
				if ( $jour_slug ) {
					$wam_jour = function_exists( 'wamv1_get_day_label' ) ? wamv1_get_day_label( $jour_slug ) : $jour_slug;
				}
				$h_deb = get_field( 'heure_debut', $course_id );
				$h_fin = get_field( 'heure_de_fin', $course_id );
				if ( $h_deb && $h_fin ) {
					$wam_heure = $h_deb . ' – ' . $h_fin;
				} elseif ( $h_deb ) {
					$wam_heure = $h_deb;
				}
			}

			// Stage : date (injectée côté add_to_cart)
			$wam_date = $cart_item['wam_date'] ?? null;

			// Variation (Adulte / Enfant) — label formaté par WC
			$variation_label = '';
			if ( $_product->is_type( 'variation' ) ) {
				$variation_label = wc_get_formatted_variation( $_product, true );
			}

			$price_html = apply_filters(
				'woocommerce_cart_item_subtotal',
				WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ),
				$cart_item,
				$cart_item_key
			);

			$display_title = $course_title ?: $product_name;
			?>
			<li class="wam-order-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
				<div class="wam-order-item__head">
					<div class="wam-order-item__titles">
						<h3 class="wam-order-item__title text-sm fw-bold"><?php echo esc_html( $display_title ); ?></h3>
						<?php if ( $course_subtitle ) : ?>
							<span class="wam-order-item__subtitle text-xs color-subtext d-block"><?php echo esc_html( $course_subtitle ); ?></span>
						<?php endif; ?>
						<?php 
						$wam_tarif_label = $cart_item['wam_tarif_label'] ?? null;
						$is_stage = $course_id && get_post_type( $course_id ) === 'stages';
						if ( $wam_tarif_label && $is_stage ) : ?>
							<span class="wam-order-item__tarif text-md color-text d-block mt-2"><?php echo esc_html( $wam_tarif_label ); ?></span>
						<?php endif; ?>
					</div>
					<span class="wam-order-item__qty text-sm color-text">× <?php echo esc_html( $cart_item['quantity'] ); ?></span>
				</div>

				<?php if ( $course_title ) : ?>
					<span class="wam-order-item__product text-xs color-subtext"><?php echo esc_html( $product_name ); ?></span>
				<?php endif; ?>

				<?php if ( $variation_label ) : ?>
					<span class="wam-order-item__variation text-xs color-subtext"><?php echo wp_kses_post( $variation_label ); ?></span>
				<?php endif; ?>

				<?php if ( $wam_date ) : ?>
					<span class="wam-order-item__meta text-xs color-subtext"><?php echo esc_html( $wam_date ); ?></span>
				<?php endif; ?>

				<span class="wam-order-item__price text-sm color-subtext"><?php echo wp_kses_post( $price_html ); ?></span>
			</li>
			<?php
		}

	</ul>
	<?php do_action( 'woocommerce_review_order_after_cart_contents' ); ?>

	<div class="wam-order-review__totals">

		<div class="wam-order-review__row">
			<span class="color-subtext text-sm"><?php esc_html_e( 'Sous-total', 'wamv1' ); ?></span>
			<span class="text-sm fw-bold"><?php wc_cart_totals_subtotal_html(); ?></span>
		</div>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<div class="wam-order-review__row cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<span class="color-subtext text-sm"><?php wc_cart_totals_coupon_label( $coupon ); ?></span>
				<span class="text-sm fw-bold"><?php wc_cart_totals_coupon_html( $coupon ); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
			<?php wc_cart_totals_shipping_html(); ?>
			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<div class="wam-order-review__row fee">
				<span class="color-subtext text-sm"><?php echo esc_html( $fee->name ); ?></span>
				<span class="text-sm fw-bold"><?php wc_cart_totals_fee_html( $fee ); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<div class="wam-order-review__row tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<span class="color-subtext text-sm"><?php echo esc_html( $tax->label ); ?></span>
						<span class="text-sm fw-bold"><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="wam-order-review__row tax-total">
					<span class="color-subtext text-sm"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
					<span class="text-sm fw-bold"><?php wc_cart_totals_taxes_total_html(); ?></span>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<div class="wam-order-review__row wam-order-review__row--total order-total">
			<span class="title-norm-sm"><?php esc_html_e( 'Total', 'wamv1' ); ?></span>
			<span class="title-norm-sm color-yellow"><?php wc_cart_totals_order_total_html(); ?></span>
		</div>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</div>

</div>
