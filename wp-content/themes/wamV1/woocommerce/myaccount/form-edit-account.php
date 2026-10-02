<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
?>


<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
		<label for="account_first_name">Prénom&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
		<label for="account_last_name">Nom&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
	</p>
	<div class="clear"></div>

	<?php /* Nom affiché : pas de champ dédié, on le fige à sa valeur actuelle (cf. woocommerce_save_account_details_required_fields dans inc/woocommerce.php). */ ?>
	<input type="hidden" name="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" />

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_email">Adresse e-mail&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
	</p>

	<?php
		/**
		 * Hook where additional fields should be rendered.
		 *
		 * @since 8.7.0
		 */
		do_action( 'woocommerce_edit_account_form_fields' );
	?>

	<!-- Adresse de facturation intégrée -->
	<div class="wam-edit-account-address" style="margin-top: var(--wam-spacing-xl); padding-top: var(--wam-spacing-lg); border-top: 1px solid var(--wam-color-disabled);">
		<h3 class="title-norm-sm" style="margin-bottom: var(--wam-spacing-xs);">Adresse de facturation</h3>
		<p class="text-sm color-subtext" style="margin-bottom: var(--wam-spacing-md);">Celle-ci est requise par notre plateforme de paiement.</p>
		
		<div class="wam-billing-fields">
			<?php
			$billing_fields = [
				'billing_address_1' => [
					'label' => 'Adresse',
					'required' => true,
					'class' => ['form-row-wide'],
				],
				'billing_postcode' => [
					'label' => 'Code postal',
					'required' => true,
					'class' => ['form-row-first'],
				],
				'billing_city' => [
					'label' => 'Ville',
					'required' => true,
					'class' => ['form-row-last'],
				],
				'billing_phone' => [
					'label' => 'Téléphone',
					'required' => true,
					'class' => ['form-row-wide'],
				],
			];

			foreach ( $billing_fields as $key => $field ) {
				$value = get_user_meta( $user->ID, $key, true );
				woocommerce_form_field( $key, $field, $value );
			}
			?>
		</div>
	</div>

	<fieldset>
		<legend>Modifier le mot de passe</legend>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_current">Mot de passe actuel (laissez vide pour ne pas le modifier)</label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_1">Nouveau mot de passe (laissez vide pour ne pas le modifier)</label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_2">Confirmer le nouveau mot de passe</label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" />
		</p>
	</fieldset>
	<div class="clear"></div>

	<?php
		/**
		 * My Account edit account form.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_edit_account_form' );
	?>

	<p>
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="Enregistrer">Enregistrer</button>
		<input type="hidden" name="action" value="save_account_details" />
	</p>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
