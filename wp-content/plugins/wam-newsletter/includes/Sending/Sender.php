<?php
namespace WamNewsletter\Sending;

use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Render\Placeholders;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WamNewsletter\Tracking\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Expédition d'UN message à UNE personne (§8.1 : jamais de copie cachée groupée).
 *
 * C'est le seul endroit où le HTML figé devient un e-mail personnalisé :
 * marqueurs résolus, liens réécrits pour le suivi, variables remplacées,
 * en-têtes de désinscription posés. La file (lot 7) ne fait qu'appeler cette
 * méthode en boucle.
 */
class Sender {

	const META_HTML = '_wam_nl_rendered_html';

	/**
	 * Prépare le HTML pour une personne donnée.
	 *
	 * @param array  $abonne        Ligne de la table des abonné·es.
	 * @param int    $newsletter_id
	 * @param string $html          HTML figé de la newsletter.
	 */
	public static function personalize( array $abonne, int $newsletter_id, string $html ): string {
		$token = (string) ( $abonne['token'] ?? '' );
		if ( ! Subscribers::is_token( $token ) ) {
			$token = Subscribers::ensure_token( (int) $abonne['id'] );
		}

		// 1. Suivi des clics, avant la résolution des marqueurs : la réécriture
		// épargne volontairement les URL qui contiennent encore un marqueur.
		if ( Settings::get( 'track_clicks' ) ) {
			$html = Links::rewrite( $html, $newsletter_id, $token );
		}

		// 2. Marqueurs techniques.
		$pixel = Settings::get( 'track_opens' ) ? Links::open_pixel( $newsletter_id, $token ) : '';
		$html  = Placeholders::replace(
			$html,
			array(
				Placeholders::UNSUB_URL  => Links::unsubscribe_url( $token ),
				Placeholders::VIEW_URL   => Links::view_url( $newsletter_id, $token ),
				Placeholders::OPEN_PIXEL => $pixel,
			)
		);

		// 3. Personnalisation, puis nettoyage de la ponctuation orpheline
		// (« Bonjour , » quand le prénom est vide).
		$html = Placeholders::replace( $html, Placeholders::person_values( $abonne ) );

		return Placeholders::tidy( $html );
	}

	/**
	 * En-têtes d'un envoi (§8.3).
	 *
	 * `List-Unsubscribe` et `List-Unsubscribe-Post` ne sont pas décoratifs :
	 * Gmail et Yahoo exigent la désinscription en un clic pour les expéditeurs
	 * de masse, et leur absence dégrade la délivrabilité de tout le domaine.
	 *
	 * @return string[]
	 */
	public static function headers( string $token ): array {
		$entetes = array( 'Content-Type: text/html; charset=UTF-8' );

		$reply = (string) Settings::get( 'reply_to' );
		if ( is_email( $reply ) ) {
			$entetes[] = 'Reply-To: ' . $reply;
		}

		if ( Subscribers::is_token( $token ) ) {
			$entetes[] = 'List-Unsubscribe: <' . Links::unsubscribe_url( $token ) . '>, <' . Links::unsubscribe_mailto() . '>';
			$entetes[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		return $entetes;
	}

	/**
	 * Envoie un message.
	 *
	 * @param array  $abonne
	 * @param int    $newsletter_id
	 * @param string $html    HTML figé.
	 * @param string $sujet
	 * @return array{ok:bool,error:string}
	 */
	public static function send_one( array $abonne, int $newsletter_id, string $html, string $sujet ): array {
		$email = Subscribers::normalize_email( $abonne['email'] ?? '' );
		if ( '' === $email ) {
			return array(
				'ok'    => false,
				'error' => 'adresse invalide',
			);
		}

		$corps = self::personalize( $abonne, $newsletter_id, $html );
		$texte = EmailRenderer::plain_text( $corps );
		$sujet = Placeholders::tidy( Placeholders::replace( $sujet, Placeholders::person_values( $abonne ) ) );

		$token   = (string) ( $abonne['token'] ?? '' );
		$entetes = self::headers( $token );

		// AltBody : wp_mail ne l'expose pas, il faut passer par PHPMailer. Le
		// hook est posé puis retiré immédiatement pour ne pas coller cette
		// version texte à tous les e-mails suivants du site.
		$poser_texte = static function ( $phpmailer ) use ( $texte ) {
			$phpmailer->AltBody = $texte;
		};
		add_action( 'phpmailer_init', $poser_texte, 30 );

		$erreur  = '';
		$capture = static function ( $wp_error ) use ( &$erreur ) {
			$erreur = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $capture );

		$envoye = Mailer::with_sender(
			static function () use ( $email, $sujet, $corps, $entetes ) {
				return wp_mail( $email, $sujet, $corps, $entetes );
			}
		);

		remove_action( 'wp_mail_failed', $capture );
		remove_action( 'phpmailer_init', $poser_texte, 30 );

		if ( $envoye ) {
			return array(
				'ok'    => true,
				'error' => '',
			);
		}

		return array(
			'ok'    => false,
			'error' => '' !== $erreur ? $erreur : 'échec sans message de PHPMailer',
		);
	}

	/**
	 * Envoi de test (§7.5).
	 *
	 * Objet préfixé `[TEST]`, et les destinataires de test ne sont pas des
	 * abonné·es : on fabrique une ligne factice, sans token, pour qu'aucun
	 * événement ne soit enregistré et que les statistiques restent propres.
	 *
	 * @param int      $newsletter_id
	 * @param string[] $destinataires Vide = adresses de test des réglages.
	 * @return array{ok:bool,message:string,sent:int}
	 */
	public static function send_test( int $newsletter_id, array $destinataires = array() ): array {
		$post = get_post( $newsletter_id );
		if ( ! $post ) {
			return array(
				'ok'      => false,
				'message' => __( 'Newsletter introuvable.', 'wam-newsletter' ),
				'sent'    => 0,
			);
		}

		$sujet = trim( (string) get_post_meta( $newsletter_id, '_wam_nl_subject', true ) );
		if ( '' === $sujet ) {
			return array(
				'ok'      => false,
				'message' => __( 'Renseignez d’abord l’objet de la newsletter.', 'wam-newsletter' ),
				'sent'    => 0,
			);
		}

		$cibles = array();
		foreach ( $destinataires as $brut ) {
			$adresse = Subscribers::normalize_email( $brut );
			if ( '' !== $adresse ) {
				$cibles[] = $adresse;
			}
		}
		if ( ! $cibles ) {
			$cibles = Settings::test_recipients();
		}
		$cibles = array_values( array_unique( $cibles ) );

		if ( ! $cibles ) {
			return array(
				'ok'      => false,
				'message' => __( 'Aucune adresse de test. Renseignez-en dans les réglages ou saisissez-en une ici.', 'wam-newsletter' ),
				'sent'    => 0,
			);
		}

		$html = EmailRenderer::render( $newsletter_id );
		if ( '' === trim( $html ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Le rendu de la newsletter est vide.', 'wam-newsletter' ),
				'sent'    => 0,
			);
		}

		$envoyes = 0;
		$erreurs = array();

		foreach ( $cibles as $adresse ) {
			// Ligne factice : pas de token, donc pas de lien de désinscription
			// actif ni de pixel. Un test ne doit rien enregistrer (§7.5).
			$factice = array(
				'id'         => 0,
				'email'      => $adresse,
				'first_name' => __( 'Prénom', 'wam-newsletter' ),
				'last_name'  => __( 'Nom', 'wam-newsletter' ),
				'token'      => '',
			);

			$corps = Placeholders::replace( $html, Placeholders::preview_values() );
			$corps = Placeholders::tidy( Placeholders::replace( $corps, Placeholders::person_values( $factice ) ) );
			$texte = EmailRenderer::plain_text( $corps );

			// L'objet est personnalisé comme le corps : un test doit montrer la
			// ligne telle qu'elle apparaîtra dans la boîte de réception, sinon on
			// découvre un « Bonjour {prenom} » littéral après l'envoi réel.
			$sujet_test = '[TEST] ' . Placeholders::tidy(
				Placeholders::replace( $sujet, Placeholders::person_values( $factice ) )
			);

			$poser_texte = static function ( $phpmailer ) use ( $texte ) {
				$phpmailer->AltBody = $texte;
			};
			add_action( 'phpmailer_init', $poser_texte, 30 );

			$erreur  = '';
			$capture = static function ( $wp_error ) use ( &$erreur ) {
				$erreur = $wp_error->get_error_message();
			};
			add_action( 'wp_mail_failed', $capture );

			$entetes = array( 'Content-Type: text/html; charset=UTF-8' );
			$reply   = (string) Settings::get( 'reply_to' );
			if ( is_email( $reply ) ) {
				$entetes[] = 'Reply-To: ' . $reply;
			}

			$ok = Mailer::with_sender(
				static function () use ( $adresse, $sujet_test, $corps, $entetes ) {
					return wp_mail( $adresse, $sujet_test, $corps, $entetes );
				}
			);

			remove_action( 'wp_mail_failed', $capture );
			remove_action( 'phpmailer_init', $poser_texte, 30 );

			if ( $ok ) {
				++$envoyes;
			} else {
				$erreurs[] = $adresse . ' : ' . ( '' !== $erreur ? $erreur : __( 'échec inconnu', 'wam-newsletter' ) );
			}
		}

		// Les tests laissent aussi une trace : « est-ce que mon test est bien
		// parti ? » est la première question qu'on se pose, et la réponse ne doit
		// pas dépendre d'un message à l'écran qu'on a déjà fermé.
		Log::record(
			$newsletter_id,
			Log::TYPE_TEST,
			sprintf(
				/* translators: 1: nombre envoyé, 2: adresses, 3: erreurs éventuelles */
				__( '%1$d test(s) vers %2$s.%3$s', 'wam-newsletter' ),
				$envoyes,
				implode( ', ', $cibles ),
				$erreurs ? ' ' . __( 'Échecs :', 'wam-newsletter' ) . ' ' . implode( ' / ', $erreurs ) : ''
			)
		);

		if ( $envoyes > 0 && ! $erreurs ) {
			return array(
				'ok'      => true,
				/* translators: %s liste d'adresses */
				'message' => sprintf( __( 'Test envoyé à %s.', 'wam-newsletter' ), implode( ', ', $cibles ) ),
				'sent'    => $envoyes,
			);
		}

		if ( $envoyes > 0 ) {
			return array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: 1: nombre envoyé, 2: liste d'erreurs */
					__( '%1$d test(s) envoyé(s), avec des échecs : %2$s', 'wam-newsletter' ),
					$envoyes,
					implode( ' ⏐ ', $erreurs )
				),
				'sent'    => $envoyes,
			);
		}

		return array(
			'ok'      => false,
			'message' => implode( ' ⏐ ', $erreurs ),
			'sent'    => 0,
		);
	}
}
