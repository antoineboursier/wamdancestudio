<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);
?>

<p class="text-md">
	<?php
	printf(
		wp_kses(__('Bonjour %1$s (ce n\'est pas vous ? <a href="%2$s" class="color-subtext">Déconnexion</a>)', 'woocommerce'), $allowed_html + array('a' => array('href' => array(), 'class' => array()))),
		'<strong>' . esc_html($current_user->display_name) . '</strong>',
		esc_url(wc_logout_url())
	);
	?>
</p>

<p class="text-sm">
	À partir du tableau de bord de votre compte, vous pouvez visualiser vos <a
		href="<?php echo esc_url(wc_get_endpoint_url('orders')); ?>" class="color-subtext">commandes récentes</a>,
	gérer vos <a href="<?php echo esc_url(wc_get_endpoint_url('edit-address')); ?>" class="color-subtext">adresses
		de facturation</a> ainsi que changer votre <a
		href="<?php echo esc_url(wc_get_endpoint_url('edit-account')); ?>" class="color-subtext">mot de passe et les
		détails de votre compte</a>.
</p>

<div class="wam-dashboard-legal" style="margin-top: var(--wam-spacing-4xl);">
	<h3 class="text-md color-text" style="font-weight: normal; margin-bottom: var(--wam-spacing-2xs);">Droit à l'image
	</h3>
	<p class="text-sm color-disabled">
		L'inscription aux cours ou stages WAM implique votre consentement au droit à l'image pour les besoins de
		communication du studio. Si vous souhaitez vous opposer à l'utilisation de votre image (ou celle de votre
		enfant), vous pouvez nous contacter à tout moment à <a href="mailto:contact@wamdancestudio.fr"
			class="color-subtext">contact@wamdancestudio.fr</a>.
	</p>
</div>


<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action('woocommerce_account_dashboard');

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_before_my_account');

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action('woocommerce_after_my_account');

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
