<?php
namespace WamNewsletter\Sending;

use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Pilotage de l'envoi par lots (§8.1, §8.2).
 *
 * S'appuie sur Action Scheduler, fourni par WooCommerce (déjà présent) : une
 * action traite un lot puis se replanifie. Pas de boucle longue ni de cron
 * maison — un envoi de 1 850 messages dure environ une heure et demie, aucune
 * requête HTTP ne tiendrait.
 *
 * ⚠️ La protection contre les échecs (§8.2) est le point critique de ce
 * fichier. La limite d'o2switch porte sur le NOMBRE D'ÉCHECS PAR HEURE : un
 * envoi qui s'entête après des refus successifs fait basculer tout le domaine.
 * D'où la mise en pause automatique, et le fait qu'un échec SMTP ne marque
 * JAMAIS une adresse en rebond — elle n'y est pour rien.
 */
class Scheduler {

	const HOOK  = 'wam_nl_process_queue';
	const GROUP = 'wam-newsletter';

	const STATUS_DRAFT     = 'draft';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_SENDING   = 'sending';
	const STATUS_PAUSED    = 'paused';
	const STATUS_SENT      = 'sent';

	const META_STATUS   = '_wam_nl_status';
	const META_HTML     = '_wam_nl_rendered_html';
	const META_SENT_AT  = '_wam_nl_sent_at';
	const META_PAUSE    = '_wam_nl_pause_reason';
	const META_RETRIED  = '_wam_nl_retried';
	const META_RESUMED  = '_wam_nl_resumed_at';

	/** Durée maximale d'un lot (secondes) : Action Scheduler coupe vers 30 s. */
	const BATCH_BUDGET = 20;

	/** Au-delà, un verrou est considéré comme abandonné par un processus mort. */
	const LOCK_TTL = 600;

	public static function register_hooks(): void {
		add_action( self::HOOK, array( self::class, 'process' ), 10, 1 );
	}

	public static function available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_unschedule_all_actions' );
	}

	public static function status( int $newsletter_id ): string {
		$statut = (string) get_post_meta( $newsletter_id, self::META_STATUS, true );
		return '' !== $statut ? $statut : self::STATUS_DRAFT;
	}

	public static function set_status( int $newsletter_id, string $statut ): void {
		update_post_meta( $newsletter_id, self::META_STATUS, $statut );
	}

	/** @return int[] */
	public static function list_ids( int $newsletter_id ): array {
		$ids = get_post_meta( $newsletter_id, '_wam_nl_list_ids', true );
		return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * Lance ou planifie un envoi.
	 *
   * @param int         $newsletter_id
	 * @param string|null $quand Date « Y-m-d H:i:s » locale, ou null pour maintenant.
	 * @return array{ok:bool,message:string,total:int}|WP_Error
	 */
	public static function start( int $newsletter_id, ?string $quand = null ) {
		$post = get_post( $newsletter_id );
		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'wam_nl_absent', __( 'Newsletter introuvable.', 'wam-newsletter' ) );
		}

		$statut = self::status( $newsletter_id );
		if ( in_array( $statut, array( self::STATUS_SENDING, self::STATUS_SENT ), true ) ) {
			return new WP_Error( 'wam_nl_deja', __( 'Cette newsletter est déjà en cours d’envoi ou envoyée.', 'wam-newsletter' ) );
		}

		// ⚠️ Reconstruire la file efface qui a déjà reçu l'e-mail : relancer un
		// envoi interrompu le renverrait à tout le monde. Une pause se reprend avec
		// « Reprendre », jamais avec « Envoyer ».
		if ( self::STATUS_PAUSED === $statut ) {
			$deja = Queue::counts( $newsletter_id );
			if ( $deja['sent'] > 0 || $deja['failed'] > 0 || ( $deja['sending'] ?? 0 ) > 0 ) {
				return new WP_Error( 'wam_nl_en_pause', __( 'Cet envoi est en pause et a déjà commencé : utilisez « Reprendre l’envoi », sinon les personnes déjà servies le recevraient une seconde fois.', 'wam-newsletter' ) );
			}
		}

		// Reprogrammer un envoi déjà programmé : l'ancienne action serait sinon
		// conservée et deux chaînes tourneraient en parallèle.
		if ( self::STATUS_SCHEDULED === $statut ) {
			self::unschedule( $newsletter_id );
		}

		$sujet = trim( (string) get_post_meta( $newsletter_id, '_wam_nl_subject', true ) );
		if ( '' === $sujet ) {
			return new WP_Error( 'wam_nl_objet', __( 'L’objet est obligatoire avant tout envoi.', 'wam-newsletter' ) );
		}

		$listes = self::list_ids( $newsletter_id );
		if ( ! $listes ) {
			return new WP_Error( 'wam_nl_listes', __( 'Choisissez au moins une liste de destinataires.', 'wam-newsletter' ) );
		}

		$destinataires = Subscribers::count_recipients( $listes );
		if ( 0 === $destinataires ) {
			return new WP_Error( 'wam_nl_vide', __( 'Aucun·e abonné·e actif·ve dans les listes choisies.', 'wam-newsletter' ) );
		}

		if ( ! self::available() ) {
			return new WP_Error( 'wam_nl_scheduler', __( 'Action Scheduler est indisponible : l’envoi par lots ne peut pas démarrer.', 'wam-newsletter' ) );
		}

		// Le HTML est FIGÉ ici (§7.3) : tout le monde reçoit la même chose, même
		// si un stage est publié au milieu de l'envoi.
		$html = EmailRenderer::render( $newsletter_id );
		if ( '' === trim( $html ) ) {
			return new WP_Error( 'wam_nl_rendu', __( 'Le rendu de la newsletter est vide.', 'wam-newsletter' ) );
		}
		update_post_meta( $newsletter_id, self::META_HTML, $html );

		Queue::clear( $newsletter_id );
		$total = Queue::build( $newsletter_id, $listes );

		delete_post_meta( $newsletter_id, self::META_PAUSE );
		delete_post_meta( $newsletter_id, self::META_RETRIED );
		delete_post_meta( $newsletter_id, self::META_RESUMED );

		// L'envoi est définitif : la newsletter quitte l'état « brouillon » et
		// devient « publiée ». Avant ce point, rien n'est public ni verrouillé.
		self::publish( $newsletter_id );

		$horodatage = self::timestamp( $quand );

		if ( $horodatage > time() + 30 ) {
			self::set_status( $newsletter_id, self::STATUS_SCHEDULED );
			self::schedule( $newsletter_id, $horodatage );
			Log::record(
				$newsletter_id,
				Log::TYPE_SCHEDULED,
				sprintf(
					/* translators: 1: date, 2: nombre de destinataires */
					__( 'Programmé le %1$s pour %2$d destinataire(s).', 'wam-newsletter' ),
					wp_date( 'd/m/Y H:i', $horodatage ),
					$total
				),
				array( 'pending' => $total )
			);
			return array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: 1: date, 2: nombre de destinataires */
					__( 'Envoi programmé le %1$s pour %2$s destinataire(s).', 'wam-newsletter' ),
					wp_date( 'd/m/Y H:i', $horodatage ),
					number_format_i18n( $total )
				),
				'total'   => $total,
			);
		}

		self::set_status( $newsletter_id, self::STATUS_SENDING );
		self::schedule( $newsletter_id, time() + 5 );
		Log::record(
			$newsletter_id,
			Log::TYPE_START,
			sprintf(
				/* translators: 1: nombre de destinataires, 2: taille de lot, 3: intervalle */
				__( 'File construite : %1$d destinataire(s), par lots de %2$d toutes les %3$d secondes.', 'wam-newsletter' ),
				$total,
				max( 1, (int) Settings::get( 'batch_size' ) ),
				max( 10, (int) Settings::get( 'batch_interval' ) )
			),
			array( 'pending' => $total )
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s nombre de destinataires */
				__( 'Envoi lancé pour %s destinataire(s).', 'wam-newsletter' ),
				number_format_i18n( $total )
			),
			'total'   => $total,
		);
	}

	/** Passe l'article en « publié » (sans effet s'il l'est déjà). */
	private static function publish( int $newsletter_id ): void {
		if ( 'publish' !== get_post_status( $newsletter_id ) ) {
			wp_update_post(
				array(
					'ID'          => $newsletter_id,
					'post_status' => 'publish',
				)
			);
		}
	}

	private static function timestamp( ?string $quand ): int {
		if ( ! $quand ) {
			return time();
		}
		$ts = strtotime( $quand . ' ' . wp_timezone_string() );
		return $ts ?: time();
	}

	private static function schedule( int $newsletter_id, int $horodatage ): void {
		if ( ! self::available() ) {
			return;
		}
		as_schedule_single_action( $horodatage, self::HOOK, array( $newsletter_id ), self::GROUP );
	}

	public static function unschedule( int $newsletter_id ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( $newsletter_id ), self::GROUP );
		}
	}

	/**
	 * Traite un lot, puis se replanifie.
	 *
	 * @param int $newsletter_id
	 */
	public static function process( $newsletter_id ): void {
		$newsletter_id = (int) $newsletter_id;
		$statut        = self::status( $newsletter_id );

		if ( self::STATUS_SCHEDULED === $statut ) {
			self::set_status( $newsletter_id, self::STATUS_SENDING );
			$statut = self::STATUS_SENDING;
		}

		if ( self::STATUS_SENDING !== $statut ) {
			// Mise en pause ou annulée entre deux lots : on s'arrête sans rien
			// replanifier.
			return;
		}

		// Une seule exécution à la fois par newsletter : sinon deux lots lisent les
		// mêmes lignes « pending » et les mêmes personnes reçoivent l'e-mail deux fois.
		if ( ! self::lock( $newsletter_id ) ) {
			return;
		}

		try {
			self::run_batch( $newsletter_id );
		} finally {
			self::unlock( $newsletter_id );
		}
	}

	/**
	 * Verrou atomique : add_option échoue si l'option existe déjà. Un verrou plus
	 * vieux que LOCK_TTL appartient à un processus mort et est repris.
	 */
	private static function lock( int $newsletter_id ): bool {
		$cle = 'wam_nl_lock_' . $newsletter_id;
		if ( add_option( $cle, time(), '', 'no' ) ) {
			return true;
		}
		wp_cache_delete( $cle, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$pris_depuis = (int) get_option( $cle, 0 );
		if ( $pris_depuis > 0 && ( time() - $pris_depuis ) > self::LOCK_TTL ) {
			delete_option( $cle );
			return add_option( $cle, time(), '', 'no' );
		}
		return false;
	}

	private static function unlock( int $newsletter_id ): void {
		delete_option( 'wam_nl_lock_' . $newsletter_id );
	}

	private static function run_batch( int $newsletter_id ): void {
		$reglages = Settings::all();
		$taille   = max( 1, (int) $reglages['batch_size'] );
		$seuil_k  = max( 1, (int) $reglages['fail_threshold_batch'] );
		$seuil_m  = max( 1, (int) $reglages['fail_threshold_hour'] );

		$html  = (string) get_post_meta( $newsletter_id, self::META_HTML, true );
		$sujet = (string) get_post_meta( $newsletter_id, '_wam_nl_subject', true );

		if ( '' === $html ) {
			self::pause( $newsletter_id, __( 'HTML figé introuvable : envoi interrompu.', 'wam-newsletter' ) );
			return;
		}

		$intervalle = max( 10, (int) $reglages['batch_interval'] );

		// Lignes laissées « sending » par un processus mort : on les sort du jeu
		// (sans les renvoyer) pour que l'envoi puisse se terminer.
		$orphelines = Queue::release_stale( $newsletter_id );
		if ( $orphelines > 0 ) {
			Log::record(
				$newsletter_id,
				Log::TYPE_BATCH,
				sprintf(
					/* translators: %d nombre d'adresses */
					__( '%d adresse(s) restée(s) en cours d’envoi après une interruption : non renvoyées, à vérifier dans le journal.', 'wam-newsletter' ),
					$orphelines
				),
				Queue::counts( $newsletter_id )
			);
		}

		// Filet de sécurité : si PHP meurt en plein lot (délai dépassé, mémoire),
		// la fin de cette fonction n'est jamais atteinte et la chaîne s'arrêterait
		// sans bruit. On planifie donc d'emblée un passage de rattrapage, que le
		// déroulé normal remplace à la fin du lot.
		self::unschedule( $newsletter_id );
		self::schedule( $newsletter_id, time() + $intervalle + 180 );

		$lot = Queue::next_batch( $newsletter_id, $taille );

		if ( ! $lot ) {
			self::finish( $newsletter_id );
			return;
		}

		$echecs  = 0;
		$envoyes = 0;
		$traites = 0;
		$debut   = microtime( true );
		$depuis_reprise = (string) get_post_meta( $newsletter_id, self::META_RESUMED, true );

		foreach ( $lot as $entree ) {
			// Budget de temps : mieux vaut un lot plus court qu'un lot tué à mi-chemin.
			if ( ( microtime( true ) - $debut ) > self::BATCH_BUDGET ) {
				break;
			}

			// Réservation atomique : si une autre exécution a déjà pris la ligne, on passe.
			if ( ! Queue::claim( $entree['id'] ) ) {
				continue;
			}
			++$traites;

			try {
				$abonne = Subscribers::find( $entree['subscriber_id'] );

				// Désabonné·e entre la construction de la file et l'envoi : on ne lui
				// écrit pas. La file garde la trace, sans rien envoyer, et ce n'est pas
				// un échec SMTP (il ne compte pas dans les seuils).
				if ( ! $abonne || Subscribers::STATUS_SUBSCRIBED !== $abonne['status'] ) {
					Queue::mark_skipped( $entree['id'], __( 'Destinataire non abonné·e au moment de l’envoi.', 'wam-newsletter' ) );
					continue;
				}

				$resultat = Sender::send_one( $abonne, $newsletter_id, $html, $sujet );
			} catch ( \Throwable $e ) {
				$resultat = array(
					'ok'    => false,
					'error' => 'Exception : ' . $e->getMessage(),
				);
			}

			if ( $resultat['ok'] ) {
				Queue::mark_sent( $entree['id'] );
				++$envoyes;
				continue;
			}

			// Un échec SMTP n'est JAMAIS un rebond (§8.2) : une limite serveur
			// côté WAM ne dit rien de la validité de l'adresse. Le statut
			// « bounced » reste posé à la main en V1.
			Queue::mark_failed( $entree['id'], (string) $resultat['error'], $entree['attempts'] + 1 );
			++$echecs;
		}

		// Une ligne par lot : c'est ce qui permet, après coup, de dire à quelle
		// vitesse l'envoi est réellement parti et où il a commencé à dérailler.
		$apres = Queue::counts( $newsletter_id );
		Log::record(
			$newsletter_id,
			Log::TYPE_BATCH,
			sprintf(
				/* translators: 1: taille du lot, 2: envoyés, 3: échecs, 4: durée en secondes */
				__( 'Lot de %1$d traité : %2$d envoyé(s), %3$d échec(s), en %4$s s.', 'wam-newsletter' ),
				$traites,
				$envoyes,
				$echecs,
				number_format_i18n( round( microtime( true ) - $debut, 1 ), 1 )
			),
			$apres
		);

		if ( $echecs >= $seuil_k ) {
			self::pause(
				$newsletter_id,
				sprintf(
					/* translators: 1: nombre d'échecs, 2: seuil */
					__( '%1$d échec(s) dans un même lot (seuil : %2$d).', 'wam-newsletter' ),
					$echecs,
					$seuil_k
				)
			);
			return;
		}

		if ( Queue::recent_failures( $newsletter_id, 3600, $depuis_reprise ) >= $seuil_m ) {
			self::pause(
				$newsletter_id,
				sprintf(
					/* translators: %d seuil horaire */
					__( 'Trop d’échecs sur l’heure écoulée (seuil : %d).', 'wam-newsletter' ),
					$seuil_m
				)
			);
			return;
		}

		// Remplace le passage de rattrapage posé en début de lot.
		self::unschedule( $newsletter_id );
		self::schedule( $newsletter_id, time() + $intervalle );
	}

	/**
	 * Fin de file : une seule reprise automatique des échecs (§8.2).
	 */
	private static function finish( int $newsletter_id ): void {
		$deja_rejoue = (bool) get_post_meta( $newsletter_id, self::META_RETRIED, true );

		if ( ! $deja_rejoue ) {
			$remis = Queue::requeue_failed( $newsletter_id );
			update_post_meta( $newsletter_id, self::META_RETRIED, 1 );
			if ( $remis > 0 ) {
				$intervalle = max( 10, (int) Settings::get( 'batch_interval' ) );
				self::unschedule( $newsletter_id );
				self::schedule( $newsletter_id, time() + $intervalle );
				Log::record(
					$newsletter_id,
					Log::TYPE_RETRY,
					sprintf(
						/* translators: %d nombre d'adresses remises en file */
						__( '%d adresse(s) en échec remise(s) en file pour une seule nouvelle tentative.', 'wam-newsletter' ),
						$remis
					),
					Queue::counts( $newsletter_id )
				);
				return;
			}
		}

		self::set_status( $newsletter_id, self::STATUS_SENT );
		update_post_meta( $newsletter_id, self::META_SENT_AT, current_time( 'mysql' ) );
		self::unschedule( $newsletter_id );

		$counts = Queue::counts( $newsletter_id );
		Log::record(
			$newsletter_id,
			Log::TYPE_FINISH,
			sprintf(
				/* translators: 1: envoyés, 2: échecs */
				__( 'File terminée : %1$d envoyé(s), %2$d échec(s).', 'wam-newsletter' ),
				$counts['sent'],
				$counts['failed']
			),
			$counts
		);
	}

	/**
	 * Met l'envoi en pause et prévient l'administration (§8.2).
	 */
	public static function pause( int $newsletter_id, string $raison ): void {
		self::set_status( $newsletter_id, self::STATUS_PAUSED );
		update_post_meta( $newsletter_id, self::META_PAUSE, $raison );
		self::unschedule( $newsletter_id );

		$counts = Queue::counts( $newsletter_id );
		Log::record( $newsletter_id, Log::TYPE_PAUSE, $raison, $counts );

		$sujet  = sprintf(
			/* translators: %s nom du site */
			__( '[%s] Envoi de newsletter mis en pause', 'wam-newsletter' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$corps = sprintf(
			/* translators: 1: titre, 2: raison, 3: envoyés, 4: échecs, 5: restants, 6: URL */
			__(
				"L'envoi de la newsletter « %1\$s » a été mis en pause automatiquement.\n\nRaison : %2\$s\n\nEnvoyés : %3\$d\nÉchecs : %4\$d\nRestants : %5\$d\n\nReprendre depuis l'administration : %6\$s",
				'wam-newsletter'
			),
			get_the_title( $newsletter_id ),
			$raison,
			$counts['sent'],
			$counts['failed'],
			$counts['pending'],
			admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE )
		);

		// Envoi direct, hors Mailer::with_sender : si le transport est en train
		// d'échouer, autant ne pas ajouter nos propres filtres d'expéditeur.
		wp_mail( (string) get_option( 'admin_email' ), $sujet, $corps );
	}

	/**
	 * @return array{ok:bool,message:string}|WP_Error
	 */
	public static function resume( int $newsletter_id ) {
		if ( self::STATUS_PAUSED !== self::status( $newsletter_id ) ) {
			return new WP_Error( 'wam_nl_pas_en_pause', __( 'Cette newsletter n’est pas en pause.', 'wam-newsletter' ) );
		}

		$counts = Queue::counts( $newsletter_id );
		if ( 0 === $counts['pending'] ) {
			// Plus rien en attente : on relance les échecs plutôt que de
			// reprendre sur une file vide, qui se terminerait aussitôt.
			Queue::requeue_failed( $newsletter_id );
		}

		delete_post_meta( $newsletter_id, self::META_PAUSE );
		// Les échecs d'avant la reprise ont déjà provoqué la pause : le seuil
		// horaire ne doit compter que ce qui arrive après.
		update_post_meta( $newsletter_id, self::META_RESUMED, current_time( 'mysql' ) );
		self::set_status( $newsletter_id, self::STATUS_SENDING );
		self::unschedule( $newsletter_id );
		self::schedule( $newsletter_id, time() + 5 );
		Log::record( $newsletter_id, Log::TYPE_RESUME, __( 'Envoi repris depuis l’administration.', 'wam-newsletter' ), Queue::counts( $newsletter_id ) );

		return array(
			'ok'      => true,
			'message' => __( 'Envoi repris.', 'wam-newsletter' ),
		);
	}

	/** Arrête un envoi en cours ou programmé, sans vider la file. */
	public static function cancel( int $newsletter_id ): void {
		self::unschedule( $newsletter_id );
		self::set_status( $newsletter_id, self::STATUS_PAUSED );
		update_post_meta( $newsletter_id, self::META_PAUSE, __( 'Arrêté manuellement.', 'wam-newsletter' ) );
		Log::record( $newsletter_id, Log::TYPE_CANCEL, __( 'Arrêt demandé depuis l’administration.', 'wam-newsletter' ), Queue::counts( $newsletter_id ) );
	}

	/** Progression, pour l'écran des newsletters et le panneau latéral. */
	public static function progress( int $newsletter_id ): array {
		$counts = Queue::counts( $newsletter_id );
		return array(
			'status'    => self::status( $newsletter_id ),
			'sent'      => $counts['sent'],
			'failed'    => $counts['failed'],
			'pending'   => $counts['pending'],
			'total'     => $counts['total'],
			'pause'     => (string) get_post_meta( $newsletter_id, self::META_PAUSE, true ),
			'sentAt'    => (string) get_post_meta( $newsletter_id, self::META_SENT_AT, true ),
			'nextRunAt' => self::next_run( $newsletter_id ),
		);
	}

	private static function next_run( int $newsletter_id ): int {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return 0;
		}
		$suivant = as_next_scheduled_action( self::HOOK, array( $newsletter_id ), self::GROUP );
		return is_int( $suivant ) ? $suivant : 0;
	}
}
