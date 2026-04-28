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
<section class="woocommerce-order-details wam-order-details"
	style="background: var(--wam-color-card-bg); border-radius: var(--wam-radius-lg); margin-bottom: var(--wam-spacing-xl);">
	<?php do_action('woocommerce_order_details_before_order_table', $order); ?>

	<div class="wam-order-details__items"
		style="display:flex; flex-direction:column; gap: var(--wam-spacing-md); margin-bottom: var(--wam-spacing-xl);">
		<?php
		do_action('woocommerce_order_details_before_order_table_items', $order);

		foreach ($order_items as $item_id => $item) {
			$product = $item->get_product();
			$course_id = $item->get_meta('_wam_course_id');
			$course_title = $course_id ? get_the_title($course_id) : $item->get_name();
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
			<div class="wam-order-item"
				style="display: flex; gap: var(--wam-spacing-md); align-items: center; flex-direction: row; border: 1px solid var(--wp--preset--color--background-500); width: 100%; border-radius: var(--wam-field-radius, 8px); padding: var(--wam-spacing-lg);">
				<?php if ($course_thumb): ?>
					<div class="wam-order-item__thumb"
						style="width: 200px; height: 300px; flex-shrink: 0; border-radius: var(--wam-radius-sm); overflow: hidden;">
						<img src="<?php echo esc_url($course_thumb); ?>" alt="<?php echo esc_attr($course_title); ?>"
							style="width: 100%; height: 100%; object-fit: cover;">
					</div>
				<?php endif; ?>
				<div class="wam-order-item__info" style="flex-grow: 1;">
					<span
						class="text-xs color-green"><?php echo esc_html($product ? $product->get_name() : $item->get_name()); ?></span>
					<h3 class="text-lg fw-bold accent-yellow" style="margin: 0;"><?php echo esc_html($course_title); ?></h3>

					<?php if ($course_subtitle || $wam_tarif_label): ?>
						<span class="text-md color-text" style="display:block;">
							<?php echo esc_html($course_subtitle); ?>
							<?php if ($course_subtitle && $wam_tarif_label)
								echo ' — '; ?>
							<?php echo esc_html($wam_tarif_label); ?>
						</span>
					<?php endif; ?>

					<?php
					// Autres métadonnées (Participants, etc)
					$formatted_meta = $item->get_formatted_meta_data();
					if (!empty($formatted_meta)) {
						echo '<div class="wam-order-item__meta" style="margin-top: var(--wam-spacing-sm); display: flex; flex-direction:column; flex-wrap: wrap; gap: var(--wam-spacing-md); row-gap: var(--wam-spacing-2xs);">';
						foreach ($formatted_meta as $meta_id => $meta) {
							if ($meta->key === 'Tarif' || $meta->key === 'Formule' || $meta->key === '_wam_course_id')
								continue;
							echo '<div class="text-xs color-subtext fw-normal">' . wp_kses_post($meta->display_key) . ' <span class="color-text text-md"  style="margin-left:var(--wam-spacing-2xs);">' . wp_strip_all_tags($meta->display_value) . '</span></div>';
						}
						echo '</div>';
					}
					?>
				</div>
				<div class="wam-order-item__price"
					style="text-align: right; display: flex; flex-direction: column; justify-content: center; gap: var(--wam-spacing-xs); min-width: 140px;">
					<span class="text-lg color-subtext fw-normal">
						Qté : <?php echo $item->get_quantity(); ?>
					</span>

					<div class="wam-order-item__price-details">
						<?php if ($item->get_quantity() > 1): ?>
							<span class="text-xs color-disabled" style="display:block; margin-bottom: 2px;">
								<?php echo wc_price($order->get_item_subtotal($item, false, true)); ?> / unité
							</span>
						<?php endif; ?>

						<span class="text-xl fw-bold color-yellow" style="display:block;">
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

	<div class="wam-order-totals"
		style="background: var(--wam-color-page-bg); padding: var(--wam-spacing-md); border-radius: var(--wam-radius-sm);">
		<?php
		foreach ($order->get_order_item_totals() as $key => $total) {
			$is_total = ($key === 'order_total');
			?>
			<div
				style="display:flex; justify-content: space-between; align-items: center; margin-bottom: var(--wam-spacing-2xs); <?php echo $is_total ? 'margin-top: var(--wam-spacing-sm); padding-top: var(--wam-spacing-sm); border-top: 1px solid var(--wam-color-disabled); font-weight: bold; font-size: var(--wam-font-size-md);' : 'font-size: var(--wam-font-size-sm); color: var(--wam-color-subtext);'; ?>">
				<span><?php echo esc_html(wp_strip_all_tags($total['label'])); ?></span>
				<span class="color-text"><?php echo wp_kses_post($total['value']); ?></span>
			</div>
			<?php
		}
		?>

		<?php if ($order->get_customer_note()): ?>
			<div
				style="margin-top: var(--wam-spacing-md); padding-top: var(--wam-spacing-md); border-top: 1px solid var(--wam-color-disabled);">
				<strong class="text-sm color-text"
					style="display:block; margin-bottom: var(--wam-spacing-2xs);"><?php esc_html_e('Note:', 'woocommerce'); ?></strong>
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
		<div class="wam-order-actions"
			style="margin-top: var(--wam-spacing-lg); display: flex; gap: var(--wam-spacing-sm); justify-content: flex-end;">
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
