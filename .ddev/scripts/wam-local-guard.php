<?php
/**
 * Plugin Name: WAM — Garde-fou environnement local
 * Description: Empêche l'environnement local (copie de la prod) d'agir sur le monde extérieur.
 * Version: 1.0.0
 *
 * Déposé automatiquement dans wp-content/mu-plugins/ par `ddev pull o2switch`.
 * Source versionnée : .ddev/scripts/wam-local-guard.php — ne pas éditer la copie mu-plugins.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Force l'envoi des mails par la fonction mail() de PHP, interceptée par Mailpit dans DDEV.
 * Neutralise les réglages SMTP hérités de la base de production (envois réels aux adhérents).
 */
add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		$phpmailer->isMail();
		$phpmailer->SMTPAutoTLS = false;
	},
	PHP_INT_MAX
);

/**
 * Coupe les tâches planifiées sortantes les plus bruyantes héritées de la prod.
 */
add_filter( 'automatic_updater_disabled', '__return_true' );
add_filter( 'pre_site_transient_update_core', '__return_null' );

/**
 * Bandeau discret pour ne jamais confondre le local avec la production.
 */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		$bar->add_node(
			array(
				'id'    => 'wam-local-env',
				'title' => '⚠ COPIE LOCALE',
				'meta'  => array( 'title' => 'Base issue de la production — aucun envoi de mail réel' ),
			)
		);
	},
	100
);
