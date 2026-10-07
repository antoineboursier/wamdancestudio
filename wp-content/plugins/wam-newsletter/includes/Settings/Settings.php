<?php
namespace WamNewsletter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tous les réglages du plugin, dans une seule option.
 *
 * Une option unique plutôt qu'une par réglage : un seul point d'assainissement,
 * une seule lecture, et pas d'une vingtaine de lignes dans wp_options.
 */
class Settings {

	const OPTION = 'wam_nl_settings';

	/** Chiffrements admis par PHPMailer ; « » = aucun. */
	const SECURE_VALUES = array( 'ssl', 'tls', '' );

	/**
	 * Bornes des réglages de débit.
	 *
	 * Ce ne sont pas des précautions de façade : un batch_size à 0 n'enverrait
	 * jamais rien, et un intervalle à 0 martèlerait le SMTP d'o2switch, dont la
	 * limite porte sur le nombre d'échecs par heure. Les valeurs par défaut sont
	 * provisoires, à ajuster après le test de montée en charge du lot 7.
	 */
	const LIMITS = array(
		'batch_size'           => array( 1, 200 ),
		'batch_interval'       => array( 10, 3600 ),
		'fail_threshold_batch' => array( 1, 1000 ),
		'fail_threshold_hour'  => array( 1, 1000 ),
	);

	public static function defaults(): array {
		return array(
			// Expéditeur
			'from_name'                => 'WAM Dance Studio',
			'from_email'               => 'contact@wamdancestudio.fr',
			'reply_to'                 => 'contact@wamdancestudio.fr',
			// SMTP de repli — inutilisé si wam-custom-plugin pilote déjà PHPMailer
			'smtp_host'                => '',
			'smtp_port'                => 465,
			'smtp_secure'              => 'ssl',
			'smtp_user'                => 'contact@wamdancestudio.fr',
			// Débit
			'batch_size'               => 20,
			'batch_interval'           => 60,
			'fail_threshold_batch'     => 5,
			'fail_threshold_hour'      => 20,
			// Tests
			'test_recipients'          => '',
			// Formulaire
			'form_list_id'             => 0,
			'form_consent_text'        => "J'accepte de recevoir la newsletter de WAM Dance Studio et je peux me désinscrire à tout moment.",
			'welcome_email_enabled'    => false,
			'welcome_email_subject'    => 'Bienvenue dans la newsletter WAM',
			'welcome_email_body'       => '',
			// Suivi
			'track_opens'              => true,
			'track_clicks'             => true,
			// Désinstallation
			'delete_data_on_uninstall' => false,
		);
	}

	public static function all(): array {
		$stocke = get_option( self::OPTION, array() );
		return is_array( $stocke ) ? array_merge( self::defaults(), $stocke ) : self::defaults();
	}

	/**
	 * @return mixed|null null si la clé n'existe pas dans les defaults.
	 */
	public static function get( string $cle ) {
		$tout = self::all();
		return array_key_exists( $cle, $tout ) ? $tout[ $cle ] : null;
	}

	public static function update( array $valeurs ): void {
		update_option( self::OPTION, array_merge( self::all(), self::sanitize( $valeurs ) ) );
	}

	/**
	 * Assainit les seules clés connues. Une clé absente des defaults est ignorée :
	 * un POST bricolé ne peut donc pas injecter de réglage fantôme.
	 */
	public static function sanitize( array $entree ): array {
		$defauts = self::defaults();
		$sortie  = array();

		foreach ( $entree as $cle => $valeur ) {
			if ( ! array_key_exists( $cle, $defauts ) ) {
				continue;
			}

			switch ( $cle ) {
				case 'from_email':
				case 'reply_to':
				case 'smtp_user':
					$sortie[ $cle ] = self::email( $valeur );
					break;

				case 'test_recipients':
					$sortie[ $cle ] = implode( ',', self::email_list( $valeur ) );
					break;

				case 'smtp_secure':
					// 'x' comme valeur impossible : une entrée non-chaîne retombe
					// ainsi sur le défaut au lieu de passer pour « aucun chiffrement ».
					$v              = is_string( $valeur ) ? strtolower( trim( $valeur ) ) : 'x';
					$sortie[ $cle ] = in_array( $v, self::SECURE_VALUES, true ) ? $v : $defauts['smtp_secure'];
					break;

				case 'smtp_port':
					$p              = (int) $valeur;
					$sortie[ $cle ] = ( $p >= 1 && $p <= 65535 ) ? $p : $defauts['smtp_port'];
					break;

				case 'form_list_id':
					$sortie[ $cle ] = max( 0, (int) $valeur );
					break;

				case 'form_consent_text':
				case 'welcome_email_body':
					$sortie[ $cle ] = wp_kses_post( (string) $valeur );
					break;

				case 'welcome_email_enabled':
				case 'track_opens':
				case 'track_clicks':
				case 'delete_data_on_uninstall':
					$sortie[ $cle ] = (bool) $valeur;
					break;

				default:
					if ( isset( self::LIMITS[ $cle ] ) ) {
						list( $min, $max ) = self::LIMITS[ $cle ];
						$sortie[ $cle ]    = min( $max, max( $min, (int) $valeur ) );
						break;
					}
					$sortie[ $cle ] = sanitize_text_field( (string) $valeur );
			}
		}

		return $sortie;
	}

	/**
	 * @return string Adresse normalisée en minuscules, ou '' si invalide.
	 */
	private static function email( $valeur ): string {
		$v = sanitize_email( strtolower( trim( (string) $valeur ) ) );
		return is_email( $v ) ? $v : '';
	}

	/**
	 * @return string[] Adresses valides, normalisées, dédoublonnées, dans l'ordre de saisie.
	 */
	private static function email_list( $valeur ): array {
		$morceaux = is_array( $valeur ) ? $valeur : explode( ',', (string) $valeur );
		$valides  = array();
		foreach ( $morceaux as $m ) {
			$e = self::email( $m );
			if ( '' !== $e ) {
				$valides[] = $e;
			}
		}
		return array_values( array_unique( $valides ) );
	}

	public static function test_recipients(): array {
		return self::email_list( self::get( 'test_recipients' ) );
	}

	/**
	 * Le mot de passe SMTP ne transite jamais par la base : il vit dans
	 * wp-config.php, hors du dépôt et hors des sauvegardes de base.
	 */
	public static function smtp_password(): string {
		return defined( 'WAM_NL_SMTP_PASSWORD' ) ? (string) constant( 'WAM_NL_SMTP_PASSWORD' ) : '';
	}
}
