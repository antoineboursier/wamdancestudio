<?php
namespace WamNewsletter\Admin;

use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Menu de premier niveau « Newsletter ».
 *
 * La page parente n'affiche presque rien : elle n'existe que pour porter le menu,
 * le CPT venant s'y insérer comme premier sous-menu.
 */
class Menu {

	const SLUG = 'wam-nl';

	public static function register_hooks(): void {
		// Priorité 9 : le menu doit exister avant que le CPT ne demande à s'y ranger.
		add_action( 'admin_menu', array( self::class, 'register' ), 9 );
	}

	public static function register(): void {
		add_menu_page(
			__( 'Newsletter', 'wam-newsletter' ),
			__( 'Newsletter', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::SLUG,
			array( self::class, 'render_placeholder' ),
			'dashicons-email-alt',
			26
		);
	}

	/**
	 * Rarement affichée : conservée pour qu'une URL bricolée ne tombe pas
	 * sur une page blanche.
	 */
	public static function render_placeholder(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		$url = admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE );
		echo '<div class="wrap"><h1>' . esc_html__( 'Newsletter', 'wam-newsletter' ) . '</h1>';
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Voir les newsletters', 'wam-newsletter' ) . '</a></p></div>';
	}
}
