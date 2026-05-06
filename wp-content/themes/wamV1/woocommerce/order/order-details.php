<?php
/**
 * Order details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.1.0
 *
 * @var bool $show_downloads Controls whether the downloads table should be rendered.
 */

// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment

defined('ABSPATH') || exit;

$order = wc_get_order($order_id); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if (!$order) {
	return;
}

$order_items = $order->get_items(apply_filters('woocommerce_purchase_order_item_types', 'line_item'));
$show_purchase_note = $order->has_status(apply_filters('woocommerce_purchase_note_order_statuses', array('completed', 'processing')));
$downloads = $order->get_downloadable_items();
$actions = array_filter(
	wc_get_account_orders_actions($order),
	function ($key) {
		return 'view' !== $key;
	},
	ARRAY_FILTER_USE_KEY
);

// We make sure the order belongs to the user. This will also be true if the user is a guest, and the order belongs to a guest (userID === 0).
$show_customer_details = $order->get_user_id() === get_current_user_id();

if ($show_downloads) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads' => $downloads,
			'show_title' => true,
		)
	);
}
?>
<section class="woocommerce-order-details wam-order-details">
	<?php do_action('woocommerce_order_details_before_order_table', $order); ?>

	<div class="wam-order-details__items">
		<?php
		do_action('woocommerce_order_details_before_order_table_items', $order);

		foreach ($order_items as $item_id => $item) {
			$product = $item->get_product();
			$course_id = $item->get_meta('_wam_course_id');
			$course_title = $course_id ? get_the_title($course_id) : '';
			$course_subtitle = $course_id ? get_field('sous_titre', $course_id) : '';
			$course_thumb = $course_id ? get_the_post_thumbnail_url($course_id, 'medium') : ($product ? wp_get_attachment_image_url($product->get_image_id(), 'medium') : '');

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
						<img src="<?php echo esc_url($course_thumb); ?>" alt="<?php echo esc_attr($course_title); ?>" loading="lazy">
					</div>
				<?php endif; ?>
				<div class="wam-order-item__info">
					<?php $product_name = $product ? $product->get_name() : $item->get_name(); ?>
					<span class="text-xs color-green d-block mb-2xs"><?php echo esc_html($product_name); ?></span>
					
					<?php 
					// Si le titre du cours est différent du nom du produit, on l'affiche en titre
					if ($course_title && $course_title !== $product_name) : ?>
						<h3 class="text-lg fw-bold color-yellow m-0"><?php echo esc_html($course_title); ?></h3>
					<?php endif; ?>

					<?php if ($course_subtitle || $wam_tarif_label): ?>
						<span class="text-md color-text d-block">
							<?php echo esc_html($course_subtitle); ?>
							<?php if ($course_subtitle && $wam_tarif_label) echo ' — '; ?>
							<?php echo esc_html($wam_tarif_label); ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="wam-order-item__price">
					<span class="text-md color-subtext fw-normal">
						Qté : <?php echo $item->get_quantity(); ?>
					</span>

					<div class="wam-order-item__price-details">
						<?php if ($item->get_quantity() > 1): ?>
							<span class="text-xs color-disabled d-block mb-2xs">
								<?php echo wc_price($order->get_item_subtotal($item, false, true)); ?> / unité
							</span>
						<?php endif; ?>

						<span class="text-md fw-bold color-yellow d-block">
							<?php echo wc_price($order->get_line_total($item, true, true)); ?>
						</span>
					</div>
				</div>
			</div>
			<?php
		}

		do_action('woocommerce_order_details_after_order_table_items', $order);
		?>
	</div>

	<div class="wam-order-totals">
		<?php
		foreach ($order->get_order_item_totals() as $key => $total) {
			$is_total = ($key === 'order_total');
			$row_class = $is_total ? 'order-total' : '';
			?>
			<div class="<?php echo esc_attr($row_class); ?>">
				<span class="<?php echo $is_total ? 'title-norm-sm' : 'text-sm color-subtext'; ?>"><?php echo esc_html(wp_strip_all_tags($total['label'])); ?></span>
				<span class="<?php echo $is_total ? 'title-norm-sm color-yellow' : 'color-text'; ?>"><?php echo wp_kses_post($total['value']); ?></span>
			</div>
			<?php
		}
		?>

		<?php if ($order->get_customer_note()): ?>
			<div class="wam-order-customer-note mt-md pt-md border-top">
				<strong class="text-sm color-text d-block mb-2xs"><?php esc_html_e('Note:', 'woocommerce'); ?></strong>
				<div class="text-sm color-subtext">
					<?php
					$customer_note = wc_wptexturize_order_note($order->get_customer_note());
					echo wp_kses(nl2br($customer_note), array('br' => array()));
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if (!empty($actions)): ?>
		<div class="wam-order-actions mt-lg d-flex gap-sm justify-content-end">
			<?php
			foreach ($actions as $key => $action) {
				echo '<a href="' . esc_url($action['url']) . '" class="btn-primary">' . esc_html($action['name']) . '</a>';
			}
			?>
		</div>
	<?php endif; ?>

	<?php do_action('woocommerce_order_details_after_order_table', $order); ?>
</section>

<?php get_template_part('template-parts/separator'); ?>

<?php
/**
 * Action hook fired after the order details.
 *
 * @since 4.4.0
 * @param WC_Order $order Order data.
 */
do_action('woocommerce_after_order_details', $order);

if ($show_customer_details) {
	wc_get_template('order/order-details-customer.php', array('order' => $order));
}
