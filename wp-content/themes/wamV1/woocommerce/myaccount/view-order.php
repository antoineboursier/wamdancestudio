<?php
/**
 * View Order
 *
 * Shows the details of a particular order on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/view-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined('ABSPATH') || exit;

$notes = $order->get_customer_order_notes();
?>
<div class="wam-order-view-header"
	style="background: var(--wam-color-card-bg); border-radius: var(--wam-radius-lg); margin-bottom: var(--wam-spacing-xl);">
	<header class="wam-order-card__header"
		style="display:flex; justify-content: space-between; align-items: flex-start;">
		<div>
			<span class="text-md color-subtext">
				Commande #<?php echo $order->get_order_number(); ?> du
				<?php echo wc_format_datetime($order->get_date_created()); ?>
			</span>
			<span class="text-sm fw-bold" style="display:block; margin-top: var(--wam-spacing-2xs);">
				<?php echo wc_get_order_status_name($order->get_status()); ?>
			</span>
		</div>
		<div style="text-align: right;">
			<span class="text-md fw-bold color-text" style="display:block; margin-bottom: var(--wam-spacing-2xs);">
				<?php echo $order->get_formatted_order_total(); ?>
			</span>
		</div>
	</header>
</div>

<?php if ($notes): ?>
	<h2><?php esc_html_e('Order updates', 'woocommerce'); ?></h2>
	<ol class="woocommerce-OrderUpdates commentlist notes">
		<?php foreach ($notes as $note): ?>
			<li class="woocommerce-OrderUpdate comment note">
				<div class="woocommerce-OrderUpdate-inner comment_container">
					<div class="woocommerce-OrderUpdate-text comment-text">
						<p class="woocommerce-OrderUpdate-meta meta">
							<?php echo date_i18n(esc_html__('l jS \o\f F Y, h:ia', 'woocommerce'), strtotime($note->comment_date)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</p>
						<div class="woocommerce-OrderUpdate-description description">
							<?php echo wp_kses_post(wpautop(wptexturize($note->comment_content))); ?>
						</div>
						<div class="clear"></div>
					</div>
					<div class="clear"></div>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

<?php do_action('woocommerce_view_order', $order_id); ?>