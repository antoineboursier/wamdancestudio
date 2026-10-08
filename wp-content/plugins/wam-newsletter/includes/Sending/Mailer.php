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
 * plugin reste autonome si cette extension disparaît — d'où la copie locale des
 * réglages (external_smtp_snapshot) qui permet de reprendre la main sans
 * ressaisie.
 *
 * L'expéditeur n'est JAMAIS réécrit globalement : un plugin de newsletter n'a pas
 * à décider de l'expéditeur des factures WooCommerce ou des notifications Bookly.
 * Les filtres ne vivent que le temps d'un envoi du plugin (with_sender).
 */
class Mailer {

	/** Après les filtres usuels, mais sous le garde-fou local (PHP_INT_MAX). */
	const HOOK_PRIORITY = 20;

	/**
	 * Options de wam-custom-plugin => clés de nos réglages.
	 * Le mot de passe (`smtp_pass`) est volontairement absent : le cahier des
	 * charges interdit de le stocker en base, il vit dans WAM_NL_SMTP_PASSWORD.
	 */
	const EXTERNAL_MAP = array(
		'smtp_host'       => 'smtp_host',
		'smtp_port'       => 'smtp_port',
		'smtp_secure'     => 'smtp_secure',
		'smtp_user'       => 'smtp_user',
		'smtp_from_email' => 'from_email',
		'smtp_from_name'  => 'from_name',
	);

	public static function register_hooks(): void {
		add_action( 'phpmailer_init', array( self::class, 'configure' ), self::HOOK_PRIORITY );
	}

	/** Vrai si wam-custom-plugin pilote déjà PHPMailer. */
	public static function external_smtp_active(): bool {
		return (bool) get_option( 'coulisses_smtp_active', 0 );
	}

	/**
	 * Vrai si c'est CE plugin qui configure réellement le transport.
	 *
	 * Sans hôte, sans utilisateur ou sans mot de passe, tenter une authentification
	 * incomplète échouerait à chaque message et consommerait la limite horaire
	 * d'échecs d'o2switch : on préfère mail(), qui fonctionne. C'est la même
	 * exigence que celle de wam-custom-plugin (hôte + identifiant).
	 */
	public static function owns_transport(): bool {
		if ( self::external_smtp_active() ) {
			return false;
		}
		return '' !== (string) Settings::get( 'smtp_host' )
			&& '' !== (string) Settings::get( 'smtp_user' )
			&& '' !== Settings::smtp_password();
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

	/**
	 * Copie des réglages SMTP d'une autre extension, pour préremplir les nôtres.
	 *
	 * Lecture seule sur ses options. Le mot de passe n'est jamais copié.
	 *
	 * @return array Clés de nos réglages => valeurs, limitées aux valeurs non vides.
	 */
	public static function external_smtp_snapshot(): array {
		$copie = array();
		foreach ( self::EXTERNAL_MAP as $source => $cible ) {
			$valeur = get_option( $source, '' );
			if ( ! is_scalar( $valeur ) || '' === (string) $valeur ) {
				continue;
			}
			$copie[ $cible ] = $valeur;
		}
		return $copie;
	}

	public static function configure( $phpmailer ): void {
		if ( ! self::owns_transport() ) {
			return;
		}

		$securite    = (string) Settings::get( 'smtp_secure' );
		$utilisateur = (string) Settings::get( 'smtp_user' );

		$phpmailer->isSMTP();
		$phpmailer->Host        = (string) Settings::get( 'smtp_host' );
		$phpmailer->Port        = (int) Settings::get( 'smtp_port' );
		$phpmailer->SMTPAuth    = true;
		$phpmailer->Username    = $utilisateur;
		$phpmailer->Password    = Settings::smtp_password();
		$phpmailer->SMTPSecure  = $securite;
		$phpmailer->SMTPAutoTLS = ( 'ssl' !== $securite );

		// Enveloppe = compte authentifié : l'hébergeur rejette un Sender
		// qui ne correspond pas au compte SMTP.
		if ( is_email( $utilisateur ) ) {
			$phpmailer->Sender = $utilisateur;
		}
	}

	/**
	 * Exécute un envoi du plugin avec SON expéditeur, puis retire les filtres.
	 *
	 * C'est ce qui garde l'autorité du plugin sur son propre courrier et seulement
	 * sur lui : hors de cette fenêtre, l'expéditeur du site n'est pas touché.
	 *
	 * @param callable $envoi
	 * @return mixed Ce que renvoie $envoi.
	 */
	public static function with_sender( callable $envoi ) {
		add_filter( 'wp_mail_from', array( self::class, 'from_email' ), self::HOOK_PRIORITY );
		add_filter( 'wp_mail_from_name', array( self::class, 'from_name' ), self::HOOK_PRIORITY );
		try {
			return $envoi();
		} finally {
			remove_filter( 'wp_mail_from', array( self::class, 'from_email' ), self::HOOK_PRIORITY );
			remove_filter( 'wp_mail_from_name', array( self::class, 'from_name' ), self::HOOK_PRIORITY );
		}
	}

	public static function from_email( string $defaut ): string {
		$adresse = (string) Settings::get( 'from_email' );
		return is_email( $adresse ) ? $adresse : $defaut;
	}

	public static function from_name( string $defaut ): string {
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

		$sujet = '[TEST] ' . __( 'Newsletter WAM - vérification de la chaîne d’envoi', 'wam-newsletter' );

		$envoye = self::with_sender(
			static function () use ( $cibles, $sujet, $corps, $entetes ) {
				return wp_mail( $cibles, $sujet, $corps, $entetes );
			}
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
