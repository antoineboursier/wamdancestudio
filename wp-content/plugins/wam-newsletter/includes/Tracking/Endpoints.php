<?php
namespace WamNewsletter\Tracking;

use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints publics : désinscription, vue navigateur, pixel, clics
 * (§8.3, §8.4, §10, §12).
 *
 * Trois règles tenues partout ici :
 *  - aucun accès sans token valide ;
 *  - réponse IDENTIQUE si le token est inconnu, pour qu'on ne puisse pas
 *    deviner par essais quelles adresses sont inscrites ;
 *  - jamais de cache : ces URL portent un token personnel, une page mise en
 *    cache serait servie à la personne suivante.
 *
 * Branché sur `template_redirect` plutôt que sur une règle de réécriture : pas
 * de permaliens à vider au déploiement, ce qui a déjà coûté une action manuelle
 * oubliée en production sur le `llms.txt`.
 */
class Endpoints {

	/** GIF transparent de 1×1, en dur : pas de fichier à servir. */
	const PIXEL_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	public static function register_hooks(): void {
		add_action( 'template_redirect', array( self::class, 'dispatch' ), 1 );
		add_action( 'init', array( self::class, 'no_cache' ), 1 );
	}

	/** Vrai si la requête courante cible un endpoint du plugin. */
	public static function is_endpoint(): bool {
		foreach ( array( Links::PARAM_UNSUB, Links::PARAM_VIEW, Links::PARAM_OPEN, Links::PARAM_CLICK ) as $param ) {
			if ( isset( $_GET[ $param ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Exclut ces URL du cache (§12).
	 *
	 * LiteSpeed est actif en production : sans cette exclusion, la page de
	 * confirmation de désinscription de la première personne serait servie à
	 * toutes les suivantes.
	 */
	public static function no_cache(): void {
		if ( ! self::is_endpoint() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'wam-newsletter endpoint' );
		nocache_headers();
	}

	public static function dispatch(): void {
		if ( isset( $_GET[ Links::PARAM_OPEN ] ) ) {
			self::handle_open();
		}
		if ( isset( $_GET[ Links::PARAM_CLICK ] ) ) {
			self::handle_click();
		}
		if ( isset( $_GET[ Links::PARAM_UNSUB ] ) ) {
			self::handle_unsubscribe();
		}
		if ( isset( $_GET[ Links::PARAM_VIEW ] ) ) {
			self::handle_view();
		}
	}

	private static function token_param( string $cle = 't' ): string {
		return isset( $_GET[ $cle ] ) ? sanitize_text_field( wp_unslash( $_GET[ $cle ] ) ) : '';
	}

	/**
	 * Pixel d'ouverture.
	 *
	 * Le GIF est servi quoi qu'il arrive, token valide ou non : une réponse
	 * différente révélerait qu'une adresse est inscrite.
	 */
	private static function handle_open(): void {
		$newsletter_id = (int) $_GET[ Links::PARAM_OPEN ];
		$abonne        = Subscribers::find_by_token( self::token_param() );

		if ( $abonne && $newsletter_id > 0 && Settings::get( 'track_opens' ) ) {
			Events::record_open_once( $newsletter_id, (int) $abonne['id'] );
		}

		nocache_headers();
		header( 'Content-Type: image/gif' );
		header( 'Content-Length: 43' );
		echo base64_decode( self::PIXEL_GIF ); // phpcs:ignore WordPress.Security.EscapeOutput -- image binaire.
		exit;
	}

	/**
	 * Clic : enregistre puis redirige en 302.
	 *
	 * La signature est vérifiée AVANT la redirection. Sans elle, l'URL
	 * `?wam_nl_click=1&u=https://site-malveillant` ferait de notre domaine un
	 * relais de hameçonnage.
	 */
	private static function handle_click(): void {
		$newsletter_id = (int) $_GET[ Links::PARAM_CLICK ];
		$token         = self::token_param();
		$destination   = isset( $_GET['u'] ) ? rawurldecode( (string) wp_unslash( $_GET['u'] ) ) : '';
		$signature     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$valide = '' !== $destination
			&& Links::verify_click( $newsletter_id, $token, $destination, $signature )
			&& preg_match( '#^https?://#i', $destination );

		if ( ! $valide ) {
			// Signature absente ou fausse : on renvoie à l'accueil plutôt que
			// d'afficher une erreur qui n'apprendrait rien à la personne.
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		$abonne = Subscribers::find_by_token( $token );
		if ( $abonne && Settings::get( 'track_clicks' ) ) {
			Events::record( $newsletter_id, (int) $abonne['id'], Events::TYPE_CLICK, $destination );
		}

		// wp_redirect et non wp_safe_redirect : la destination est signée par
		// nous, et peut légitimement sortir du domaine (réseaux sociaux…).
		wp_redirect( $destination, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect -- URL signée en HMAC, vérifiée ci-dessus.
		exit;
	}

	/**
	 * Désinscription (§8.4).
	 *
	 * En POST : désinscription immédiate sans confirmation, c'est ce qu'exige
	 * `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
	 * En GET : même traitement puis page de confirmation — les clients qui
	 * préchargent les liens le font en GET, mais une désinscription demandée est
	 * une demande explicite, et la page offre le réabonnement.
	 */
	private static function handle_unsubscribe(): void {
		$token  = sanitize_text_field( wp_unslash( (string) $_GET[ Links::PARAM_UNSUB ] ) );
		$abonne = Subscribers::find_by_token( $token );
		$un_clic = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] );

		if ( $abonne && Subscribers::STATUS_UNSUBSCRIBED !== $abonne['status'] ) {
			Subscribers::set_status( (int) $abonne['id'], Subscribers::STATUS_UNSUBSCRIBED );
			Events::record( 0, (int) $abonne['id'], Events::TYPE_UNSUBSCRIBE );
		}

		if ( $un_clic ) {
			// Réponse volontairement muette et toujours 200 : le serveur de
			// messagerie n'affiche rien, et un code d'erreur sur un token
			// inconnu révélerait quelque chose.
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo 'OK';
			exit;
		}

		self::render_unsubscribe_page( null !== $abonne );
	}

	/**
	 * Page de confirmation de désinscription.
	 *
	 * Le même message est affiché que le token soit connu ou non : sinon, un
	 * robot pourrait tester des tokens et distinguer les réponses.
	 */
	private static function render_unsubscribe_page( bool $connu ): void {
		$titre   = __( 'Désinscription', 'wam-newsletter' );
		$message = __( 'Tu as bien été désinscrit·e de la newsletter de WAM Dance Studio.', 'wam-newsletter' );

		$page_newsletter = home_url( '/newsletter/' );

		$corps  = '<p>' . esc_html( $message ) . '</p>';
		$corps .= '<p>' . esc_html__( 'Tu peux te réabonner à tout moment :', 'wam-newsletter' ) . ' ';
		$corps .= '<a href="' . esc_url( $page_newsletter ) . '">' . esc_html__( 'revenir au formulaire d’inscription', 'wam-newsletter' ) . '</a>.</p>';
		$corps .= '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Retour au site', 'wam-newsletter' ) . '</a></p>';

		unset( $connu );

		wp_die(
			wp_kses_post( $corps ),
			esc_html( $titre ),
			array(
				'response'  => 200,
				'back_link' => false,
			)
		);
	}

	/**
	 * « Voir dans le navigateur ».
	 *
	 * Sert le HTML figé de l'envoi quand il existe : c'est ce que la personne a
	 * reçu. Rendre à nouveau depuis les blocs afficherait les contenus
	 * d'aujourd'hui, donc une page différente de son e-mail.
	 */
	private static function handle_view(): void {
		$newsletter_id = (int) $_GET[ Links::PARAM_VIEW ];
		$abonne        = Subscribers::find_by_token( self::token_param() );
		$post          = $newsletter_id ? get_post( $newsletter_id ) : null;

		$autorise = $abonne
			&& $post
			&& NewsletterPostType::POST_TYPE === $post->post_type;

		if ( ! $autorise ) {
			status_header( 404 );
			nocache_headers();
			wp_die(
				esc_html__( 'Cette newsletter n’est pas disponible.', 'wam-newsletter' ),
				esc_html__( 'Introuvable', 'wam-newsletter' ),
				array(
					'response'  => 404,
					'back_link' => false,
				)
			);
		}

		$html = (string) get_post_meta( $newsletter_id, '_wam_nl_rendered_html', true );
		if ( '' === $html ) {
			$html = EmailRenderer::render( $newsletter_id );
		}

		$html = \WamNewsletter\Render\Placeholders::replace(
			$html,
			array(
				\WamNewsletter\Render\Placeholders::UNSUB_URL  => Links::unsubscribe_url( (string) $abonne['token'] ),
				\WamNewsletter\Render\Placeholders::VIEW_URL   => Links::view_url( $newsletter_id, (string) $abonne['token'] ),
				\WamNewsletter\Render\Placeholders::OPEN_PIXEL => '',
			)
		);
		$html = \WamNewsletter\Render\Placeholders::tidy(
			\WamNewsletter\Render\Placeholders::replace( $html, \WamNewsletter\Render\Placeholders::person_values( $abonne ) )
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- document e-mail déjà construit et échappé.
		exit;
	}
}
