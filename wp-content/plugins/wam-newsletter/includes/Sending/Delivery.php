<?php
namespace WamNewsletter\Sending;

use WamNewsletter\Admin\Notices;
use WamNewsletter\Install;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Screen as StatsScreen;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Suivi de remise réelle, par le « Suivi de la livraison » de cPanel (o2switch).
 *
 * Ce que le plugin sait sans lui : o2switch a ACCEPTÉ le message. Ce qu'il ne
 * sait pas : si le serveur du destinataire l'a reçu, refusé (adresse morte,
 * boîte pleine) ou mis en attente. Le serveur d'o2switch le sait, lui : chaque
 * remise est consignée et lisible par l'API UAPI `EmailTrack::search`.
 *
 * Configuration dans wp-config.php, JAMAIS en base (même règle que le mot de
 * passe SMTP) :
 *   define( 'WAM_NL_CPANEL_USER',  'yuqo3097' );        // identifiant cPanel
 *   define( 'WAM_NL_CPANEL_TOKEN', '…' );               // cPanel → Gérer les jetons d'API
 *   define( 'WAM_NL_CPANEL_HOST',  'cpanel.wamdancestudio.fr' ); // facultatif
 *
 * Rebonds : un REFUS DÉFINITIF du serveur distant pour une adresse inexistante
 * (5.1.x, « user unknown »…) marque la personne en rebond — c'est une vraie
 * adresse morte. Cela n'a rien à voir avec la règle « un échec SMTP ne marque
 * jamais un rebond », qui concerne les refus d'o2switch lui-même (limites,
 * panne) : ceux-là ne disent rien de l'adresse. Un refus pour cause de spam
 * (5.7.x) ne marque pas non plus : c'est notre réputation qui est en cause,
 * pas l'adresse.
 */
class Delivery {

	const HOOK  = 'wam_nl_delivery_sync';
	const GROUP = 'wam-newsletter';

	const DELIVERED = 'delivered';
	const DEFERRED  = 'deferred';
	const FAILED    = 'failed';

	public static function register_hooks(): void {
		add_action( self::HOOK, array( self::class, 'run' ), 10, 1 );
		add_action( 'admin_post_wam_nl_delivery_sync', array( self::class, 'handle_manual_sync' ) );
	}

	/**
	 * @return array{host:string,user:string,token:string}
	 */
	public static function config(): array {
		$config = array(
			'host'  => defined( 'WAM_NL_CPANEL_HOST' ) ? (string) WAM_NL_CPANEL_HOST : 'cpanel.wamdancestudio.fr',
			'user'  => defined( 'WAM_NL_CPANEL_USER' ) ? (string) WAM_NL_CPANEL_USER : '',
			'token' => defined( 'WAM_NL_CPANEL_TOKEN' ) ? (string) WAM_NL_CPANEL_TOKEN : '',
		);
		/**
		 * Permet aux tests de fournir une configuration sans toucher aux constantes.
		 *
		 * @param array $config
		 */
		return (array) apply_filters( 'wam_nl_cpanel_config', $config );
	}

	public static function configured(): bool {
		$c = self::config();
		return '' !== trim( (string) ( $c['user'] ?? '' ) ) && '' !== trim( (string) ( $c['token'] ?? '' ) ) && '' !== trim( (string) ( $c['host'] ?? '' ) );
	}

	/**
	 * Trois relevés après la fin d'un envoi : la plupart des remises sont
	 * connues en quelques minutes, les mises en attente se résolvent dans la
	 * journée.
	 */
	public static function schedule_followups( int $newsletter_id ): void {
		if ( ! self::configured() || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		foreach ( array( 15 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, DAY_IN_SECONDS ) as $delai ) {
			as_schedule_single_action( time() + $delai, self::HOOK, array( $newsletter_id ), self::GROUP );
		}
	}

	/** @param int|string $newsletter_id */
	public static function run( $newsletter_id ): void {
		try {
			$resultat = self::sync( (int) $newsletter_id );
			if ( is_wp_error( $resultat ) ) {
				error_log( 'wam-newsletter : relevé de remise impossible : ' . $resultat->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		} catch ( \Throwable $e ) {
			error_log( 'wam-newsletter : relevé de remise en échec : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/** Bouton « Vérifier la remise » de l'écran de statistiques. */
	public static function handle_manual_sync(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		$id = isset( $_POST['newsletter'] ) ? (int) $_POST['newsletter'] : 0;
		check_admin_referer( 'wam_nl_delivery_sync_' . $id );

		$resultat = self::sync( $id );
		if ( is_wp_error( $resultat ) ) {
			Notices::error( $resultat->get_error_message() );
		} else {
			Notices::success( self::summary( $resultat ) );
		}
		wp_safe_redirect( StatsScreen::url( $id ) );
		exit;
	}

	/** @param array{delivered:int,deferred:int,failed:int,bounced:int,unknown:int} $r */
	public static function summary( array $r ): string {
		return sprintf(
			/* translators: 1: remis, 2: en attente, 3: refusés, 4: rebonds, 5: sans trace */
			__( 'Remise vérifiée auprès du serveur d’envoi : %1$s remis, %2$s en attente, %3$s refusés (dont %4$s adresses inexistantes passées en rebond), %5$s sans trace.', 'wam-newsletter' ),
			number_format_i18n( $r['delivered'] ),
			number_format_i18n( $r['deferred'] ),
			number_format_i18n( $r['failed'] ),
			number_format_i18n( $r['bounced'] ),
			number_format_i18n( $r['unknown'] )
		);
	}

	/**
	 * Interroge cPanel et met à jour la file d'une newsletter.
	 *
	 * @return array{delivered:int,deferred:int,failed:int,bounced:int,unknown:int}|WP_Error
	 */
	public static function sync( int $newsletter_id ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'wam_nl_cpanel', __( 'Suivi de remise non configuré : il manque WAM_NL_CPANEL_USER ou WAM_NL_CPANEL_TOKEN dans wp-config.php.', 'wam-newsletter' ) );
		}

		global $wpdb;
		$q = Queue::table();
		$s = Subscribers::table();

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.subscriber_id, q.sent_at, q.delivery, s.email, s.status AS subscriber_status
				 FROM `$q` q JOIN `$s` s ON s.id = q.subscriber_id
				 WHERE q.newsletter_id = %d AND q.status = %s AND q.sent_at IS NOT NULL",
				$newsletter_id,
				Queue::STATUS_SENT
			),
			ARRAY_A
		);

		$rapport = array(
			'delivered' => 0,
			'deferred'  => 0,
			'failed'    => 0,
			'bounced'   => 0,
			'unknown'   => 0,
		);
		if ( ! $lignes ) {
			return $rapport;
		}

		// Fenêtre : depuis le premier envoi de la file, moins une marge de 5 minutes.
		$premier = null;
		foreach ( $lignes as $l ) {
			$ts = self::local_timestamp( (string) $l['sent_at'] );
			if ( $ts && ( null === $premier || $ts < $premier ) ) {
				$premier = $ts;
			}
		}

		$traces = self::fetch( (int) $premier - 300 );
		if ( is_wp_error( $traces ) ) {
			return $traces;
		}

		$par_adresse = self::best_by_recipient( $traces );

		foreach ( $lignes as $l ) {
			$email = strtolower( (string) $l['email'] );
			if ( ! isset( $par_adresse[ $email ] ) ) {
				++$rapport['unknown'];
				continue;
			}
			$trace = $par_adresse[ $email ];
			++$rapport[ $trace['status'] ];

			$wpdb->update(
				$q,
				array(
					'delivery'         => $trace['status'],
					'delivery_message' => '' !== $trace['message'] ? mb_substr( $trace['message'], 0, 500 ) : null,
					'delivery_at'      => $trace['time'] ? wp_date( 'Y-m-d H:i:s', $trace['time'] ) : current_time( 'mysql' ),
				),
				array( 'id' => (int) $l['id'] )
			);

			if ( self::FAILED === $trace['status'] && self::is_hard_bounce( $trace['message'] ) && Subscribers::STATUS_SUBSCRIBED === $l['subscriber_status'] ) {
				Subscribers::set_status( (int) $l['subscriber_id'], Subscribers::STATUS_BOUNCED );
				++$rapport['bounced'];
			}
		}

		Log::record( $newsletter_id, Log::TYPE_DELIVERY, self::summary( $rapport ), Queue::counts( $newsletter_id ) );

		return $rapport;
	}

	/** Une date « heure du site » (format MySQL) en horodatage Unix. */
	private static function local_timestamp( string $mysql ): int {
		if ( '' === $mysql ) {
			return 0;
		}
		try {
			return ( new \DateTimeImmutable( $mysql, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Appel UAPI `EmailTrack::search`, remises distantes depuis $depuis.
	 *
	 * @return array<int,array{recipient:string,sender:string,status:string,message:string,time:int}>|WP_Error
	 */
	public static function fetch( int $depuis ) {
		$c   = self::config();
		$url = add_query_arg(
			array(
				'success'             => 1,
				'defer'               => 1,
				'failure'             => 1,
				'inprogress'          => 0,
				'deliverytype'        => 'remote',
				'max_results_by_type' => 5000,
				'api.filter_column_0' => 'sendunixtime',
				'api.filter_type_0'   => 'gt',
				'api.filter_term_0'   => max( 0, $depuis ),
			),
			'https://' . $c['host'] . ':2083/execute/EmailTrack/search'
		);

		$reponse = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'cpanel ' . $c['user'] . ':' . $c['token'] ),
			)
		);
		if ( is_wp_error( $reponse ) ) {
			return new WP_Error( 'wam_nl_cpanel', sprintf( /* translators: %s erreur */ __( 'cPanel injoignable : %s', 'wam-newsletter' ), $reponse->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $reponse );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'wam_nl_cpanel', __( 'cPanel refuse l’accès : vérifiez l’identifiant et le jeton d’API.', 'wam-newsletter' ) );
		}
		if ( $code >= 400 ) {
			return new WP_Error( 'wam_nl_cpanel', sprintf( /* translators: %d code HTTP */ __( 'cPanel a répondu par une erreur HTTP %d.', 'wam-newsletter' ), $code ) );
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $reponse ), true );
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'wam_nl_cpanel', __( 'Réponse de cPanel illisible.', 'wam-newsletter' ) );
		}
		// UAPI renvoie { status, data, errors } ; certaines versions l'enveloppent dans « result ».
		$resultat = isset( $json['result'] ) && is_array( $json['result'] ) ? $json['result'] : $json;
		if ( empty( $resultat['status'] ) ) {
			$erreurs = implode( ' ', array_map( 'strval', (array) ( $resultat['errors'] ?? array() ) ) );
			return new WP_Error( 'wam_nl_cpanel', sprintf( /* translators: %s message */ __( 'cPanel a refusé la requête : %s', 'wam-newsletter' ), '' !== $erreurs ? $erreurs : __( 'sans précision', 'wam-newsletter' ) ) );
		}

		$traces = array();
		foreach ( (array) ( $resultat['data'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$statut = self::map_type( (string) ( $r['type'] ?? '' ) );
			if ( '' === $statut ) {
				continue;
			}
			$traces[] = array(
				'recipient' => strtolower( trim( (string) ( $r['recipient'] ?? $r['deliveredto'] ?? '' ) ) ),
				'sender'    => strtolower( trim( (string) ( $r['email'] ?? $r['sender'] ?? '' ) ) ),
				'status'    => $statut,
				'message'   => trim( (string) ( $r['message'] ?? '' ) ),
				'time'      => (int) ( $r['actionunixtime'] ?? $r['sendunixtime'] ?? 0 ),
			);
		}
		return self::only_our_sender( $traces );
	}

	/** Garde les remises envoyées depuis l'adresse de la newsletter, quand cPanel la donne. */
	private static function only_our_sender( array $traces ): array {
		$nos = array_filter(
			array(
				strtolower( (string) Settings::get( 'from_email' ) ),
				strtolower( (string) Settings::get( 'smtp_user' ) ),
			)
		);
		if ( ! $nos ) {
			return $traces;
		}
		return array_values(
			array_filter(
				$traces,
				static function ( $t ) use ( $nos ) {
					return '' === $t['sender'] || in_array( $t['sender'], $nos, true );
				}
			)
		);
	}

	private static function map_type( string $type ): string {
		$type = strtolower( $type );
		if ( 'success' === $type ) {
			return self::DELIVERED;
		}
		if ( in_array( $type, array( 'defer', 'deferred' ), true ) ) {
			return self::DEFERRED;
		}
		if ( in_array( $type, array( 'failure', 'failed', 'reject', 'rejected', 'error' ), true ) ) {
			return self::FAILED;
		}
		return '';
	}

	/**
	 * Une seule issue par adresse : la remise l'emporte sur un refus, qui
	 * l'emporte sur une mise en attente ; à égalité, la plus récente.
	 *
	 * @return array<string,array{status:string,message:string,time:int}>
	 */
	public static function best_by_recipient( array $traces ): array {
		$rang   = array(
			self::DEFERRED  => 1,
			self::FAILED    => 2,
			self::DELIVERED => 3,
		);
		$sortie = array();
		foreach ( $traces as $t ) {
			$cle = $t['recipient'];
			if ( '' === $cle ) {
				continue;
			}
			$actuel = $sortie[ $cle ] ?? null;
			if ( null === $actuel
				|| $rang[ $t['status'] ] > $rang[ $actuel['status'] ]
				|| ( $rang[ $t['status'] ] === $rang[ $actuel['status'] ] && $t['time'] > $actuel['time'] ) ) {
				$sortie[ $cle ] = array(
					'status'  => $t['status'],
					'message' => $t['message'],
					'time'    => $t['time'],
				);
			}
		}
		return $sortie;
	}

	/**
	 * Refus définitif pour une adresse qui n'existe pas (et seulement celui-là).
	 * Les refus pour spam ou réputation (5.7.x) et les boîtes pleines n'en sont pas.
	 */
	public static function is_hard_bounce( string $message ): bool {
		if ( preg_match( '/\b5\.7\.\d+|spam|blocked|blacklist|reputation|policy|mailbox (is )?full|quota|over quota|4\.\d\.\d/i', $message ) ) {
			return false;
		}
		return (bool) preg_match( '/\b5\.1\.(1|2|3|10)\b|user unknown|unknown user|no such (user|recipient|mailbox)|does not exist|doesn\'t exist|invalid recipient|mailbox not found|account (has been )?disabled|domain not found|host or domain name not found|unrouteable address|all relevant mx records point to non-existent hosts/i', $message );
	}
}
