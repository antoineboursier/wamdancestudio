<?php
namespace WamNewsletter\Sending;

use WamNewsletter\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Configuration de l'envoi et e-mail de test.
 *
 * Point important : sur ce site, l'extension « Les coulisses du site WAM »
 * (wam-custom-plugin, maintenue par Ophélie, en LECTURE SEULE pour nous)
 * configure déjà PHPMailer sur phpmailer_init, et va jusqu'à retirer le hook
 * concurrent du thème pour éviter le doublon. On ne s'ajoute donc PAS en
 * troisième : quand son interrupteur est actif, on emprunte sa configuration
 * sans y toucher. Notre propre configuration ne sert que de repli, pour que le
 * plugin reste autonome si cette extension disparaît.
 */
class Mailer {

	/** Après les filtres usuels, mais sous le garde-fou local (PHP_INT_MAX). */
	const HOOK_PRIORITY = 20;

	public static function register_hooks(): void {
		add_action( 'phpmailer_init', array( self::class, 'configure' ), self::HOOK_PRIORITY );
		add_filter( 'wp_mail_from', array( self::class, 'from_email' ), self::HOOK_PRIORITY );
		add_filter( 'wp_mail_from_name', array( self::class, 'from_name' ), self::HOOK_PRIORITY );
	}

	/** Vrai si wam-custom-plugin pilote déjà PHPMailer. */
	public static function external_smtp_active(): bool {
		return (bool) get_option( 'coulisses_smtp_active', 0 );
	}

	/**
	 * Vrai si le garde-fou local intercepte les e-mails (Mailpit).
	 * Sert uniquement à l'affichage : aucun comportement n'en dépend.
	 */
	public static function local_guard_active(): bool {
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return true;
		}
		return defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/000-wam-local-guard.php' );
	}

	public static function configure( $phpmailer ): void {
		if ( self::external_smtp_active() ) {
			return;
		}

		$hote = (string) Settings::get( 'smtp_host' );
		$pass = Settings::smtp_password();

		// Sans hôte ou sans mot de passe, tenter une authentification vide
		// échouerait à chaque message et consommerait la limite horaire d'échecs
		// d'o2switch. On préfère mail(), qui fonctionne.
		if ( '' === $hote || '' === $pass ) {
			return;
		}

		$securite    = (string) Settings::get( 'smtp_secure' );
		$utilisateur = (string) Settings::get( 'smtp_user' );

		$phpmailer->isSMTP();
		$phpmailer->Host        = $hote;
		$phpmailer->Port        = (int) Settings::get( 'smtp_port' );
		$phpmailer->SMTPAuth    = true;
		$phpmailer->Username    = $utilisateur;
		$phpmailer->Password    = $pass;
		$phpmailer->SMTPSecure  = $securite;
		$phpmailer->SMTPAutoTLS = ( 'ssl' !== $securite );

		// Enveloppe = compte authentifié : l'hébergeur rejette un Sender
		// qui ne correspond pas au compte SMTP.
		if ( is_email( $utilisateur ) ) {
			$phpmailer->Sender = $utilisateur;
		}
	}

	public static function from_email( string $defaut ): string {
		if ( self::external_smtp_active() ) {
			return $defaut;
		}
		$adresse = (string) Settings::get( 'from_email' );
		return is_email( $adresse ) ? $adresse : $defaut;
	}

	public static function from_name( string $defaut ): string {
		if ( self::external_smtp_active() ) {
			return $defaut;
		}
		$nom = (string) Settings::get( 'from_name' );
		return '' !== $nom ? $nom : $defaut;
	}

	/**
	 * Envoie un e-mail de test en texte brut.
	 *
	 * @param string $destinataire Adresse unique ; vide = adresses de test des réglages.
	 * @return array{ok: bool, message: string}
	 */
	public static function send_test( string $destinataire = '' ): array {
		if ( '' !== $destinataire ) {
			$destinataire = sanitize_email( strtolower( trim( $destinataire ) ) );
			if ( ! is_email( $destinataire ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'Adresse de destination invalide.', 'wam-newsletter' ),
				);
			}
			$cibles = array( $destinataire );
		} else {
			$cibles = Settings::test_recipients();
		}

		if ( ! $cibles ) {
			return array(
				'ok'      => false,
				'message' => __( 'Aucune adresse de test renseignée.', 'wam-newsletter' ),
			);
		}

		$erreur  = '';
		$capture = static function ( $wp_error ) use ( &$erreur ) {
			$erreur = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $capture );

		$entetes  = array( 'Content-Type: text/plain; charset=UTF-8' );
		$reply_to = (string) Settings::get( 'reply_to' );
		if ( is_email( $reply_to ) ) {
			$entetes[] = 'Reply-To: ' . $reply_to;
		}

		$corps = sprintf(
			/* translators: %1$s nom du site, %2$s date et heure locales */
			__( "Ceci est un e-mail de test envoyé depuis %1\$s le %2\$s.\n\nSi tu le reçois, la chaîne d'envoi fonctionne.", 'wam-newsletter' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			wp_date( 'd/m/Y H:i' )
		);

		$envoye = wp_mail(
			$cibles,
			'[TEST] ' . __( 'Newsletter WAM — vérification de la chaîne d’envoi', 'wam-newsletter' ),
			$corps,
			$entetes
		);

		remove_action( 'wp_mail_failed', $capture );

		if ( $envoye ) {
			return array(
				'ok'      => true,
				/* translators: %s liste d'adresses */
				'message' => sprintf( __( 'Envoyé à %s.', 'wam-newsletter' ), implode( ', ', $cibles ) ),
			);
		}

		return array(
			'ok'      => false,
			'message' => '' !== $erreur ? $erreur : __( 'Échec sans message de PHPMailer.', 'wam-newsletter' ),
		);
	}
}
