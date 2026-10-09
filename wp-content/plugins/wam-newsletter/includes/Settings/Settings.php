<?php
namespace WamNewsletter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tous les réglages du plugin, dans une seule option.
 *
 * Une option unique plutôt qu'une par réglage : un seul point d'assainissement,
 * une seule lecture, et pas d'une vingtaine de lignes dans wp_options.
 *
 * Principe sur les valeurs invalides : on ne détruit pas un réglage en place.
 * Une saisie refusée est signalée à l'appelant, et l'ancienne valeur survit.
 */
class Settings {

	const OPTION = 'wam_nl_settings';

	/** Chiffrements admis par PHPMailer ; « » = aucun. */
	const SECURE_VALUES = array( 'ssl', 'tls', '' );

	/** Clés dont la valeur est une adresse e-mail unique. */
	const EMAIL_KEYS = array( 'from_email', 'reply_to', 'smtp_user' );

	/** Clés à valeur booléenne (cases à cocher). */
	const BOOL_KEYS = array( 'welcome_email_enabled', 'woo_auto_sync', 'track_opens', 'track_clicks', 'delete_data_on_uninstall' );

	/** Clés à texte riche (HTML simple autorisé). */
	const RICH_TEXT_KEYS = array( 'form_consent_text', 'welcome_email_body' );

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
		'hourly_cap'           => array( 10, 1000 ),
	);

	public static function defaults(): array {
		return array(
			// Expéditeur des envois de CE plugin (pas du courrier transactionnel du site)
			'from_name'                => 'WAM Dance Studio',
			'from_email'               => 'contact@wamdancestudio.fr',
			'reply_to'                 => 'contact@wamdancestudio.fr',
			// SMTP propre au plugin — copie locale, pour pouvoir reprendre la main
			// si l'extension qui pilote aujourd'hui PHPMailer disparaît.
			'smtp_host'                => '',
			'smtp_port'                => 465,
			'smtp_secure'              => 'ssl',
			'smtp_user'                => '',
			// Débit
			'batch_size'               => 10,
			'batch_interval'           => 60,
			'fail_threshold_batch'     => 5,
			'fail_threshold_hour'      => 20,
			// o2switch plafonne le DOMAINE à 180 e-mails par heure, tout compris
			// (factures, réservations, formulaires). Au-delà, il met en attente puis
			// jette les messages (incident du 09/10/2026, 238 non remis). 120 laisse
			// 60 par heure au courrier du site.
			'hourly_cap'               => 120,
			// Tests
			'test_recipients'          => '',
			// Rendu de l'e-mail
			// 0 = la bannière est retrouvée par son slug (voir Render\Brand).
			'banner_attachment_id'     => 0,
			// Formulaire
			'form_list_id'             => 0,
			'form_consent_text'        => "J'accepte de recevoir la newsletter de WAM Dance Studio et je peux me désinscrire à tout moment.",
			'welcome_email_enabled'    => false,
			'woo_auto_sync'            => true,
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

	/**
	 * Enregistre les valeurs valides et signale celles qui ont été refusées.
	 *
	 * @param array $valeurs
	 * @return string[] Clés soumises mais non enregistrées (inconnues ou invalides).
	 */
	public static function update( array $valeurs ): array {
		$rejetes = array();
		$propres = self::sanitize( $valeurs, $rejetes );
		update_option( self::OPTION, array_merge( self::all(), $propres ) );
		return $rejetes;
	}

	/**
	 * Préremplit les réglages encore à leur valeur par défaut.
	 *
	 * Sert à reprendre la configuration SMTP d'une autre extension sans écraser ce
	 * qui a déjà été saisi ici. Jamais de mot de passe (il vit dans wp-config.php).
	 *
	 * @param array $copie        Clés de réglages => valeurs.
	 * @param bool  $ecraser      true = remplace même une valeur déjà personnalisée.
	 * @return string[] Clés effectivement appliquées.
	 */
	public static function seed( array $copie, bool $ecraser = false ): array {
		$defauts  = self::defaults();
		$actuels  = self::all();
		$a_ecrire = array();

		foreach ( $copie as $cle => $valeur ) {
			if ( ! array_key_exists( $cle, $defauts ) ) {
				continue;
			}
			// Sans écrasement, on ne touche qu'à ce qui n'a jamais été personnalisé.
			if ( ! $ecraser && $actuels[ $cle ] !== $defauts[ $cle ] ) {
				continue;
			}
			$a_ecrire[ $cle ] = $valeur;
		}

		if ( ! $a_ecrire ) {
			return array();
		}

		$rejetes = array();
		$propres = self::sanitize( $a_ecrire, $rejetes );
		if ( $propres ) {
			update_option( self::OPTION, array_merge( self::all(), $propres ) );
		}
		return array_keys( $propres );
	}

	/**
	 * Assainit les seules clés connues. Une clé absente des defaults est ignorée :
	 * un POST bricolé ne peut donc pas injecter de réglage fantôme.
	 *
	 * @param array      $entree
	 * @param array|null $rejetes Reçoit les clés soumises mais non retenues.
	 * @return array
	 */
	public static function sanitize( array $entree, ?array &$rejetes = null ): array {
		$defauts = self::defaults();
		$sortie  = array();
		$rejetes = array();

		foreach ( $entree as $cle => $valeur ) {
			if ( ! array_key_exists( $cle, $defauts ) ) {
				$rejetes[] = (string) $cle;
				continue;
			}

			// Un POST forgé peut envoyer wam_nl[from_email][]=x : un cast (string)
			// sur un tableau émettrait un warning PHP et viderait le champ.
			if ( ! is_scalar( $valeur ) ) {
				$rejetes[] = $cle;
				continue;
			}

			if ( in_array( $cle, self::EMAIL_KEYS, true ) ) {
				$brut = trim( (string) $valeur );
				if ( '' === $brut ) {
					// Vider est une intention explicite, pas une erreur.
					$sortie[ $cle ] = '';
					continue;
				}
				$adresse = self::email( $brut );
				if ( '' === $adresse ) {
					// On ne détruit pas l'adresse en place sur une faute de frappe.
					$rejetes[] = $cle;
					continue;
				}
				$sortie[ $cle ] = $adresse;
				continue;
			}

			if ( in_array( $cle, self::BOOL_KEYS, true ) ) {
				$sortie[ $cle ] = (bool) $valeur;
				continue;
			}

			if ( in_array( $cle, self::RICH_TEXT_KEYS, true ) ) {
				$sortie[ $cle ] = wp_kses_post( (string) $valeur );
				continue;
			}

			if ( isset( self::LIMITS[ $cle ] ) ) {
				list( $min, $max ) = self::LIMITS[ $cle ];
				$sortie[ $cle ]    = min( $max, max( $min, (int) $valeur ) );
				continue;
			}

			switch ( $cle ) {
				case 'test_recipients':
					$sortie[ $cle ] = implode( ',', self::email_list( $valeur ) );
					break;

				case 'smtp_secure':
					$v              = strtolower( trim( (string) $valeur ) );
					$sortie[ $cle ] = in_array( $v, self::SECURE_VALUES, true ) ? $v : $defauts['smtp_secure'];
					break;

				case 'smtp_port':
					$p              = (int) $valeur;
					$sortie[ $cle ] = ( $p >= 1 && $p <= 65535 ) ? $p : $defauts['smtp_port'];
					break;

				case 'form_list_id':
				case 'banner_attachment_id':
					$sortie[ $cle ] = max( 0, (int) $valeur );
					break;

				default:
					$sortie[ $cle ] = sanitize_text_field( (string) $valeur );
			}
		}

		return $sortie;
	}

	/**
	 * @return string Adresse normalisée en minuscules, ou '' si invalide.
	 */
	private static function email( $valeur ): string {
		if ( ! is_scalar( $valeur ) ) {
			return '';
		}
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
