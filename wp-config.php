<?php
/**
 * Configuration WordPress de l'environnement local DDEV.
 *
 * Fichier initialement généré par DDEV, désormais maintenu à la main :
 * `disable_settings_management: true` dans .ddev/config.yaml empêche DDEV de le
 * réécrire à chaque `ddev restart`. Sans cela, les réglages ci-dessous seraient
 * perdus et les salts régénérés à chaque redémarrage (sessions invalidées).
 *
 * Non déployé : .cpanel.yml ne copie que wp-content/themes/wamV1.
 *
 * @package ddevapp
 */

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/** Authentication Unique Keys and Salts. */
define( 'AUTH_KEY', 'XeBARtETuiGdnMExpLMMbZmbfkTkAIbmWSrKjRNLimBcRlyllmwZUKHJOvCMmOsE' );
define( 'SECURE_AUTH_KEY', 'yKDUPFEkBBVweoYdilzkwOmFBdUlyNPQPrvLBgqifnXOHMQhOhJUtbVSwPZpYjOD' );
define( 'LOGGED_IN_KEY', 'qgPQwweBgtBHcZyCbATXCKLvGuhcxgAmqJRegSRcflhiLxsYmcWDKSGQhCmeiZZv' );
define( 'NONCE_KEY', 'XVXIwZYwTZLOdmWlMCjfGHDfVjIECKTKvBYwqXTlHNmioEnMgEDkBdCVwiYcUjJW' );
define( 'AUTH_SALT', 'sbPkBFpkDwTpMzPqzafPZJRhParkoaWSsHFBzCgKbrrryKIiENKOjZWQwhCrjmuc' );
define( 'SECURE_AUTH_SALT', 'kJCdUhUYbHILgVrPcDlwhJAgxANHPUINtlZvVWnzbjKawEMkoFkaTDvOqegumKYM' );
define( 'LOGGED_IN_SALT', 'EXWUwiQKSCcDWoXVjJhyHuOLKqzwDNBwMkzbkIlHzIsaUHMIpQOprdAgCORapQdd' );
define( 'NONCE_SALT', 'pyhfIpekyzWTjhlPGEuLaEENGYIrwlGSygArNUvhBvHXulpUDdcICoRAFydrTtbW' );

/* Add any custom values between this line and the "stop editing" line. */

/**
 * Débogage : journalisé, jamais affiché.
 *
 * Les extensions rapatriées de la production (HelloAsso, et WooCommerce sollicité
 * trop tôt par `coulisses_filtrer_helloasso_3x`) émettent des Warning/Notice dès le
 * chargement des plugins. Affichés, ils sont écrits AVANT les en-têtes HTTP :
 * plus aucun `Set-Cookie` ne part et la connexion à /wp-admin boucle sur le
 * formulaire. On conserve donc WP_DEBUG, mais la sortie part dans
 * wp-content/debug.log. Ces constantes précèdent l'inclusion de wp-config-ddev.php,
 * qui ne définit WP_DEBUG que s'il ne l'est pas déjà.
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
@ini_set( 'display_errors', '0' );

/** Signale l'environnement aux extensions qui adaptent leur comportement (WooCommerce, Yoast…). */
define( 'WP_ENVIRONMENT_TYPE', 'local' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __FILE__ ) . '/' );

// Include for settings managed by ddev.
$ddev_settings = __DIR__ . '/wp-config-ddev.php';
if ( ! defined( 'DB_USER' ) && getenv( 'IS_DDEV_PROJECT' ) == 'true' && is_readable( $ddev_settings ) ) {
	require_once( $ddev_settings );
}

/** Include wp-settings.php */
if ( file_exists( ABSPATH . '/wp-settings.php' ) ) {
	require_once ABSPATH . '/wp-settings.php';
}
