<?php
/**
 * Plugin Name:       WAMletter
 * Description:       Newsletters WAM Dance Studio — abonné·es, listes, éditeur Gutenberg, envoi par lots.
 * Version:           0.1.0
 * Author:            WAM Dance Studio
 * Text Domain:       wam-newsletter
 * Requires at least: 6.5
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WAM_NL_VERSION', '0.1.0' );
define( 'WAM_NL_FILE', __FILE__ );
define( 'WAM_NL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAM_NL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload maison : WamNewsletter\Settings\Settings -> includes/Settings/Settings.php
 * Pas de Composer : le plugin se déploie par cp -r, sans étape de build.
 */
spl_autoload_register(
	static function ( $classe ) {
		$prefixe = 'WamNewsletter\\';
		if ( 0 !== strpos( $classe, $prefixe ) ) {
			return;
		}
		$relatif = str_replace( '\\', '/', substr( $classe, strlen( $prefixe ) ) );
		$fichier = WAM_NL_DIR . 'includes/' . $relatif . '.php';
		if ( is_readable( $fichier ) ) {
			require_once $fichier;
		}
	}
);

register_activation_hook( __FILE__, array( 'WamNewsletter\\Install', 'activate' ) );

add_action( 'plugins_loaded', array( 'WamNewsletter\\Plugin', 'boot' ) );
