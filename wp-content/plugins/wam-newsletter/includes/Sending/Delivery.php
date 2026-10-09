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
 * Configuration, au choix :
 *  - depuis Réglages → Suivi : identifiant en clair, jeton CHIFFRÉ en base
 *    (AES-256-GCM, clé dérivée des clés secrètes de wp-config.php) ;
 *  - ou dans wp-config.php, prioritaire s'il est renseigné :
 *      define( 'WAM_NL_CPANEL_USER',  'yuqo3097' );
 *      define( 'WAM_NL_CPANEL_TOKEN', '…' );
 *      define( 'WAM_NL_CPANEL_HOST',  'mail.wamdancestudio.fr' ); // facultatif
 *
 * Hôte : `mail.wamdancestudio.fr` et non `cpanel.…`. Les deux pointent sur le serveur
 * réel (109.234.167.117), mais le certificat TLS de cPanel ne couvre que `mail.`,
 * `www.` et le domaine nu : avec `cpanel.…`, cURL refuse (erreur 60) et il faudrait
 * désactiver la vérification du certificat, ce que l'on évite. Le domaine nu et
 * `www.` pointent, eux, sur le frontal web où le port 2083 est fermé.
 *
 * Le chiffrement protège le jeton d'une fuite de la base seule (sauvegarde,
 * export, copie locale) ; il ne protège pas d'un accès aux fichiers ET à la
 * base. Corollaire utile : la copie locale (autres clés secrètes) ne peut pas
 * relire le jeton de la prod.
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

	const OPTION = 'wam_nl_cpanel';

	/** Écart toléré entre notre envoi à une personne et le départ d'une trace cPanel. */
	const MATCH_WINDOW = 600;

	/** Plafond de lignes demandé à cPanel, par type (remis, différé, refusé). */
	const MAX_RESULTS = 5000;

	/** Vrai si la dernière réponse de cPanel a atteint le plafond. */
	private static $capped = false;

	const DELIVERED = 'delivered';
	const DEFERRED  = 'deferred';
	const FAILED    = 'failed';

	public static function register_hooks(): void {
		add_action( self::HOOK, array( self::class, 'run' ), 10, 1 );
		add_action( 'admin_post_wam_nl_delivery_sync', array( self::class, 'handle_manual_sync' ) );
		add_action( 'admin_post_wam_nl_resend_undelivered', array( self::class, 'handle_resend' ) );
	}

	/**
	 * @return array{host:string,user:string,token:string}
	 */
	public static function config(): array {
		$enregistre = self::stored();
		$config     = array(
			'host'  => defined( 'WAM_NL_CPANEL_HOST' ) ? (string) WAM_NL_CPANEL_HOST : 'mail.wamdancestudio.fr',
			'user'  => $enregistre['user'],
			'token' => $enregistre['token'],
		);
		if ( defined( 'WAM_NL_CPANEL_USER' ) && defined( 'WAM_NL_CPANEL_TOKEN' ) && '' !== (string) WAM_NL_CPANEL_TOKEN ) {
			$config['user']  = (string) WAM_NL_CPANEL_USER;
			$config['token'] = (string) WAM_NL_CPANEL_TOKEN;
		}
		/**
		 * Permet aux tests de fournir une configuration sans toucher aux constantes.
		 *
		 * @param array $config
		 */
		return (array) apply_filters( 'wam_nl_cpanel_config', $config );
	}

	/** D'où vient la configuration : 'constants', 'admin' ou ''. */
	public static function source(): string {
		if ( defined( 'WAM_NL_CPANEL_USER' ) && defined( 'WAM_NL_CPANEL_TOKEN' ) && '' !== (string) WAM_NL_CPANEL_TOKEN ) {
			return 'constants';
		}
		$s = self::stored();
		return ( '' !== $s['user'] && '' !== $s['token'] ) ? 'admin' : '';
	}

	/**
	 * Identifiant et jeton enregistrés depuis l'admin, jeton déchiffré.
	 *
	 * @return array{user:string,token:string,has_token:bool,unreadable:bool}
	 */
	public static function stored(): array {
		$o     = get_option( self::OPTION, array() );
		$o     = is_array( $o ) ? $o : array();
		$brut  = (string) ( $o['token'] ?? '' );
		$jeton = '' !== $brut ? self::decrypt( $brut ) : '';
		return array(
			'user'       => (string) ( $o['user'] ?? '' ),
			'token'      => $jeton,
			'has_token'  => '' !== $brut,
			'unreadable' => '' !== $brut && '' === $jeton,
		);
	}

	/**
	 * Enregistre l'identifiant, et le jeton s'il est fourni (vide = inchangé).
	 *
	 * @return true|WP_Error
	 */
	public static function store( string $user, string $token, bool $forget = false ) {
		$o = get_option( self::OPTION, array() );
		$o = is_array( $o ) ? $o : array();

		$user = trim( $user );
		if ( '' !== $user && ! preg_match( '/^[a-z0-9_.-]{1,64}$/i', $user ) ) {
			return new WP_Error( 'wam_nl_cpanel', __( 'Identifiant cPanel invalide (lettres, chiffres, tiret, point, souligné).', 'wam-newsletter' ) );
		}
		$o['user'] = $user;

		$token = trim( $token );
		if ( $forget && '' === $token ) {
			$o['token'] = '';
		} else {
			if ( '' !== $token ) {
				if ( ! preg_match( '/^[A-Za-z0-9]{16,128}$/', $token ) ) {
					return new WP_Error( 'wam_nl_cpanel', __( 'Jeton d’API invalide : lettres et chiffres uniquement, au moins 16 caractères (cPanel en donne 32).', 'wam-newsletter' ) );
				}
				$chiffre = self::encrypt( $token );
				if ( '' === $chiffre ) {
					return new WP_Error( 'wam_nl_cpanel', __( 'Chiffrement indisponible sur ce serveur (extension OpenSSL) : utilisez wp-config.php.', 'wam-newsletter' ) );
				}
				$o['token'] = $chiffre;
			}
		}

		update_option( self::OPTION, $o, false );
		return true;
	}

	/** Clé de 32 octets dérivée des clés secrètes du site (wp-config.php). */
	private static function key(): string {
		return hash_hmac( 'sha256', 'wam-newsletter|cpanel-token', wp_salt( 'secure_auth' ) . wp_salt( 'auth' ), true );
	}

	public static function encrypt( string $clair ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$chiffe = openssl_encrypt( $clair, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $chiffe ) {
			return '';
		}
		return 'v1:' . base64_encode( $iv . $tag . $chiffe ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** Chaîne vide si illisible (clés du site changées, valeur altérée). */
	public static function decrypt( string $valeur ): string {
		if ( 0 !== strpos( $valeur, 'v1:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$brut = base64_decode( substr( $valeur, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $brut || strlen( $brut ) < 29 ) {
			return '';
		}
		$clair = openssl_decrypt( substr( $brut, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $brut, 0, 12 ), substr( $brut, 12, 16 ) );
		return false === $clair ? '' : $clair;
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
		// finish() peut repasser (relance des échecs) : on remplace, on n'empile pas.
		self::unschedule( $newsletter_id );
		foreach ( array( 15 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, DAY_IN_SECONDS ) as $delai ) {
			as_schedule_single_action( time() + $delai, self::HOOK, array( $newsletter_id ), self::GROUP );
		}
	}

	public static function unschedule( int $newsletter_id ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( $newsletter_id ), self::GROUP );
		}
	}

	/** @param int|string $newsletter_id */
	public static function run( $newsletter_id ): void {
		try {
			$resultat = self::sync( (int) $newsletter_id );
			if ( is_wp_error( $resultat ) ) {
				error_log( 'wam-newsletter : relevé de remise impossible : ' . $resultat->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			} else {
				self::maybe_auto_resend( (int) $newsletter_id );
			}
		} catch ( \Throwable $e ) {
			error_log( 'wam-newsletter : relevé de remise en échec : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/** Délai après la fin de l'envoi avant tout renvoi automatique (relevé de +2 h). */
	const AUTO_RESEND_AFTER = 90 * MINUTE_IN_SECONDS;

	/** Âge minimum d'un envoi pour le considérer « sans trace ». */
	const AUTO_RESEND_MIN_AGE = 30 * MINUTE_IN_SECONDS;

	/** Au-delà de cette part de « sans trace », on suspecte le suivi et on ne renvoie rien. */
	const AUTO_RESEND_MAX_SHARE = 0.5;

	/**
	 * Renvoi automatique, une seule fois par personne, aux destinataires que le
	 * serveur d'envoi a jetés. Appelé après un relevé programmé réussi.
	 *
	 * @return int|string Nombre remis en file, ou raison du refus (pour les tests et le journal).
	 */
	public static function maybe_auto_resend( int $newsletter_id ) {
		if ( empty( Settings::all()['auto_resend'] ) ) {
			return 'off';
		}
		if ( Scheduler::STATUS_SENT !== Scheduler::status( $newsletter_id ) ) {
			return 'not_sent';
		}
		$fin = self::local_timestamp( (string) get_post_meta( $newsletter_id, Scheduler::META_SENT_AT, true ) );
		if ( ! $fin || Scheduler::now() - $fin < self::AUTO_RESEND_AFTER ) {
			return 'too_early';
		}
		if ( self::$capped ) {
			return 'capped';
		}
		$ids = Queue::undelivered_ids( $newsletter_id, self::AUTO_RESEND_MIN_AGE );
		if ( ! $ids ) {
			return 0;
		}
		$envoyes = (int) ( Queue::counts( $newsletter_id )['sent'] ?? 0 );
		if ( $envoyes <= 0 || count( $ids ) > self::AUTO_RESEND_MAX_SHARE * $envoyes ) {
			Log::record(
				$newsletter_id,
				Log::TYPE_DELIVERY,
				sprintf(
					/* translators: 1: sans trace, 2: envoyés */
					__( 'Renvoi automatique annulé : %1$d destinataire(s) sans trace sur %2$d envoyés, c’est trop pour être des messages jetés. Le suivi de remise est peut-être en panne : vérifiez avant de renvoyer à la main.', 'wam-newsletter' ),
					count( $ids ),
					$envoyes
				),
				Queue::counts( $newsletter_id )
			);
			return 'too_many';
		}
		$n = Scheduler::resend_undelivered( $newsletter_id, self::AUTO_RESEND_MIN_AGE );
		return is_wp_error( $n ) ? $n->get_error_code() : $n;
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

	/** Bouton « Renvoyer aux destinataires sans trace » de l'écran de statistiques. */
	public static function handle_resend(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		$id = isset( $_POST['newsletter'] ) ? (int) $_POST['newsletter'] : 0;
		check_admin_referer( 'wam_nl_resend_' . $id );

		$resultat = Scheduler::resend_undelivered( $id );
		if ( is_wp_error( $resultat ) ) {
			Notices::error( $resultat->get_error_message() );
		} elseif ( 0 === $resultat ) {
			Notices::warning( __( 'Personne à qui renvoyer : chaque destinataire a une trace de remise ou une ouverture.', 'wam-newsletter' ) );
		} else {
			Notices::success(
				sprintf(
					/* translators: %d nombre de destinataires */
					__( 'Renvoi lancé pour %d destinataire(s), au rythme du plafond horaire.', 'wam-newsletter' ),
					$resultat
				)
			);
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
			return new WP_Error( 'wam_nl_cpanel', __( 'Suivi de remise non configuré : renseignez l’identifiant et le jeton cPanel dans Réglages → Suivi.', 'wam-newsletter' ) );
		}

		global $wpdb;
		$q = Queue::table();
		$s = Subscribers::table();

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.subscriber_id, q.sent_at, q.delivery, q.delivery_message, s.email, s.status AS subscriber_status
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

		// Heure d'envoi propre à chaque destinataire. Sans heure lisible, la ligne
		// n'est rapprochée de rien : on ne devine jamais.
		$premier = 0;
		foreach ( $lignes as $i => $l ) {
			$lignes[ $i ]['ts'] = self::local_timestamp( (string) $l['sent_at'] );
			if ( $lignes[ $i ]['ts'] && ( ! $premier || $lignes[ $i ]['ts'] < $premier ) ) {
				$premier = $lignes[ $i ]['ts'];
			}
		}
		if ( ! $premier ) {
			$rapport['unknown'] = count( $lignes );
			return $rapport;
		}

		$traces = self::fetch( $premier - self::MATCH_WINDOW );
		if ( is_wp_error( $traces ) ) {
			return $traces;
		}

		// La même adresse reçoit d'autres messages de contact@ (factures, Bookly,
		// tests, newsletters suivantes) : seule une trace partie à quelques minutes
		// de NOTRE envoi à cette personne est la nôtre.
		$par_adresse = array();
		foreach ( $traces as $t ) {
			$par_adresse[ $t['recipient'] ][] = $t;
		}

		foreach ( $lignes as $l ) {
			$email     = strtolower( (string) $l['email'] );
			$candidats = array();
			if ( $l['ts'] ) {
				foreach ( $par_adresse[ $email ] ?? array() as $t ) {
					$depart = $t['sent'] ? $t['sent'] : $t['time'];
					if ( abs( $depart - $l['ts'] ) <= self::MATCH_WINDOW ) {
						$candidats[] = $t;
					}
				}
			}
			$meilleure = self::best_by_recipient( $candidats );
			if ( ! isset( $meilleure[ $email ] ) ) {
				++$rapport['unknown'];
				continue;
			}
			$trace = $meilleure[ $email ];
			++$rapport[ $trace['status'] ];

			$message = '' !== $trace['message'] ? mb_substr( $trace['message'], 0, 500 ) : null;
			if ( $trace['status'] !== (string) $l['delivery'] || (string) $message !== (string) $l['delivery_message'] ) {
				$wpdb->update(
					$q,
					array(
						'delivery'         => $trace['status'],
						'delivery_message' => $message,
						'delivery_at'      => $trace['time'] ? wp_date( 'Y-m-d H:i:s', $trace['time'] ) : current_time( 'mysql' ),
					),
					array( 'id' => (int) $l['id'] )
				);
			}

			if ( self::FAILED === $trace['status'] && self::is_hard_bounce( $trace['message'] ) && Subscribers::STATUS_SUBSCRIBED === $l['subscriber_status'] ) {
				Subscribers::set_status( (int) $l['subscriber_id'], Subscribers::STATUS_BOUNCED );
				++$rapport['bounced'];
			}
		}

		$resume = self::summary( $rapport );
		if ( self::$capped ) {
			$resume .= ' ' . __( 'Attention : cPanel a plafonné sa réponse, certaines remises peuvent manquer.', 'wam-newsletter' );
		}
		Log::record( $newsletter_id, Log::TYPE_DELIVERY, $resume, Queue::counts( $newsletter_id ) );

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
				'cpanel_jsonapi_user'       => $c['user'],
				'cpanel_jsonapi_apiversion' => 2,
				'cpanel_jsonapi_module'     => 'EmailTrack',
				'cpanel_jsonapi_func'       => 'search',
				'success'                   => 1,
				'defer'                     => 1,
				'failure'                   => 1,
				'inprogress'                => 0,
				'deliverytype'              => 'remote',
				'max_results_by_type'       => self::MAX_RESULTS,
			),
			'https://' . $c['host'] . ':2083/json-api/cpanel'
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
		// API2 : { cpanelresult: { data: [...], event: { result: 1 }, error?: "…" } }.
		$resultat = isset( $json['cpanelresult'] ) && is_array( $json['cpanelresult'] ) ? $json['cpanelresult'] : array();
		$echec    = isset( $resultat['event']['result'] ) && ! $resultat['event']['result'];
		if ( ! $resultat || $echec || ! empty( $resultat['error'] ) ) {
			$erreur = (string) ( $resultat['error'] ?? ( $resultat['data'][0]['reason'] ?? '' ) );
			return new WP_Error( 'wam_nl_cpanel', sprintf( /* translators: %s message */ __( 'cPanel a refusé la requête : %s', 'wam-newsletter' ), '' !== $erreur ? $erreur : __( 'sans précision', 'wam-newsletter' ) ) );
		}

		$traces       = array();
		$par_type     = array();
		self::$capped = false;
		foreach ( (array) ( $resultat['data'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$statut = self::map_type( (string) ( $r['type'] ?? '' ) );
			if ( '' === $statut ) {
				continue;
			}
			$par_type[ $statut ] = ( $par_type[ $statut ] ?? 0 ) + 1;
			$heure  = (int) ( $r['actionunixtime'] ?? $r['sendunixtime'] ?? 0 );
			$depart = (int) ( $r['sendunixtime'] ?? 0 );
			// Pas de filtre côté cPanel : on écarte ici ce qui est parti avant l'envoi.
			if ( ( $depart ? $depart : $heure ) < $depuis ) {
				continue;
			}
			$traces[] = array(
				'recipient' => strtolower( trim( (string) ( $r['recipient'] ?? '' ) ) ),
				'sender'    => strtolower( trim( (string) ( $r['sender'] ?? '' ) ) ),
				'status'    => $statut,
				'message'   => trim( (string) ( $r['message'] ?? '' ) ),
				'time'      => $heure,
				'sent'      => $depart,
			);
		}
		self::$capped = $par_type && max( $par_type ) >= self::MAX_RESULTS;
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
		// 5.7.x (refus de politique), spam, réputation, boîte pleine, et tout code
		// temporaire 4xx / 4.x.x. Ancré : « 10.4.1.20 » dans une adresse IP ne doit
		// pas passer pour un code 4.1.20, ni « 5.1.10 » pour un 1.10.
		if ( preg_match( '/(?<![\d.])5\.7\.\d+|spam|blacklist|blocklist|reputation|mailbox (is )?full|quota/i', $message )
			|| preg_match( '/^\s*4\d\d\b|(?<![\d.])4\.\d{1,3}\.\d{1,3}(?![\d.])/', $message ) ) {
			return false;
		}
		return (bool) preg_match( '/\b5\.1\.(1|2|3|10)\b|user unknown|unknown user|no such (user|recipient|mailbox)|does not exist|doesn\'t exist|invalid recipient|mailbox not found|account (has been )?disabled|domain not found|host or domain name not found|unrouteable address|all relevant mx records point to non-existent hosts/i', $message );
	}
}
