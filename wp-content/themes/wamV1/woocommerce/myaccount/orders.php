<?php
/**
 * Orders — WAM override
 *
 * Basé sur WooCommerce/Templates v9.5.0.
 * Modification : suppression du bouton "Parcourir les produits"
 * sur le message "aucune commande" (non pertinent pour une école de danse).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_orders', $has_orders); ?>

<?php if ($has_orders): ?>

	<div class="wam-orders-list">
		<?php
		$order_count = 0;
		foreach ($customer_orders->orders as $customer_order) {
			$order_count++;
			$order = wc_get_order($customer_order); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$item_count = $order->get_item_count() - $order->get_item_count_refunded();

			if ($order_count > 1) {
				get_template_part('template-parts/separator');
			}
			?>
			<article class="wam-order-card">
				<header class="wam-order-card__header">
					<div>
						<span class="text-md color-subtext">
							Commande #<?php echo $order->get_order_number(); ?> du
							<?php echo wc_format_datetime($order->get_date_created()); ?>
						</span>
						<span class="text-sm wam-order-card__status">
							<?php echo wc_get_order_status_name($order->get_status()); ?>
						</span>
					</div>
					<div class="wam-order-card__actions">
						<span class="text-md color-text wam-order-card__total">
							<?php echo $order->get_formatted_order_total(); ?>
						</span>
						<a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="text-sm color-accent-yellow wam-order-card__link">Voir la commande</a>
					</div>
				</header>

				<div class="wam-order-card__items">
					<?php foreach ($order->get_items() as $item_id => $item):
						$product = $item->get_product();
						$course_id = $item->get_meta('_wam_course_id');
						$course_title = $course_id ? get_the_title($course_id) : $item->get_name();
						$course_subtitle = $course_id ? get_field('sous_titre', $course_id) : '';
						$course_thumb = $course_id ? get_the_post_thumbnail_url($course_id, 'medium') : ($product ? wp_get_attachment_image_url($product->get_image_id(), 'medium') : '');

						// On essaie de récupérer le libellé du tarif
						$wam_tarif_label = '';
						$meta_data = $item->get_formatted_meta_data('');
						foreach ($meta_data as $meta) {
							if ($meta->key === 'Tarif' || $meta->key === 'Formule') {
								$wam_tarif_label = wp_strip_all_tags($meta->display_value);
							}
						}
						?>
						<div class="wam-order-item">
							<?php if ($course_thumb): ?>
								<div class="wam-order-item__thumb">
									<img src="<?php echo esc_url($course_thumb); ?>" alt="<?php echo esc_attr($course_title); ?>">
								</div>
							<?php endif; ?>
							<div class="wam-order-item__info">
								<span
									class="text-xs color-green"><?php echo esc_html($product ? $product->get_name() : $item->get_name()); ?></span>
								<h3 class="text-md fw-bold color-text wam-order-item__title"><?php echo esc_html($course_title); ?>
								</h3>

								<?php if ($course_subtitle || $wam_tarif_label): ?>
									<span class="text-sm color-text wam-order-item__meta">
										<?php echo esc_html($course_subtitle); ?>
										<?php if ($course_subtitle && $wam_tarif_label)
											echo ' — '; ?>
										<?php echo esc_html($wam_tarif_label); ?>
									</span>
								<?php endif; ?>

								<span class="text-sm fw-bold color-yellow wam-order-item__price">
									<?php echo wc_price($order->get_line_total($item, true, true)); ?>
									<?php if ($item->get_quantity() > 1): ?>
										<span class="text-xs color-subtext fw-normal">x <?php echo $item->get_quantity(); ?></span>
									<?php endif; ?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php
				// Actions supprimées car le lien "Voir la commande" est déjà présent en haut
				?>
			</article>
			<?php
		}
		?>
	</div>

	<?php do_action('woocommerce_before_account_orders_pagination'); ?>

	<?php if (1 < $customer_orders->max_num_pages): ?>
		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
			<?php if (1 !== $current_page): ?>
				<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button<?php echo esc_attr($wp_button_class); ?>"
					href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page - 1)); ?>"><?php esc_html_e('Previous', 'woocommerce'); ?></a>
			<?php endif; ?>

			<?php if (intval($customer_orders->max_num_pages) !== $current_page): ?>
				<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button<?php echo esc_attr($wp_button_class); ?>"
					href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page + 1)); ?>"><?php esc_html_e('Next', 'woocommerce'); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else: ?>

	<?php wc_print_notice(esc_html__('Vous n\'avez pas encore passé de commande.', 'woocommerce'), 'notice'); ?>

<?php endif; ?>

<?php do_action('woocommerce_after_account_orders', $has_orders); ?>