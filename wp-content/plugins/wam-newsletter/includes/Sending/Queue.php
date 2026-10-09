<?php
namespace WamNewsletter\Sending;

use WamNewsletter\Install;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * File d'envoi (§8.1).
 *
 * Le dédoublonnage des destinataires n'est pas assuré par du code mais par le
 * SCHÉMA : la table porte `UNIQUE (newsletter_id, subscriber_id)`. Même si la
 * file était reconstruite deux fois, personne ne recevrait l'e-mail en double —
 * c'est le genre de garantie qu'on ne veut pas faire reposer sur la vigilance
 * d'un appelant quand il y a 1 850 destinataires en jeu.
 */
class Queue {

	const STATUS_PENDING = 'pending';
	const STATUS_SENT    = 'sent';
	const STATUS_FAILED  = 'failed';
	const STATUS_SENDING = 'sending';

	public static function table(): string {
		return Install::table( 'queue' );
	}

	/**
	 * Construit la file pour les listes choisies.
	 *
	 * @param int   $newsletter_id
	 * @param int[] $list_ids
	 * @return int Nombre de destinataires dans la file.
	 */
	public static function build( int $newsletter_id, array $list_ids ): int {
		global $wpdb;
		$t = self::table();

		$ids = Subscribers::recipient_ids( $list_ids );
		if ( ! $ids ) {
			return 0;
		}

		// Insertion par paquets de 200 : une seule requête pour 1 850 valeurs
		// dépasserait max_allowed_packet sur certains mutualisés.
		foreach ( array_chunk( $ids, 200 ) as $paquet ) {
			$valeurs = array();
			$params  = array();
			foreach ( $paquet as $subscriber_id ) {
				$valeurs[] = '(%d, %d, %s, 0)';
				$params[]  = $newsletter_id;
				$params[]  = $subscriber_id;
				$params[]  = self::STATUS_PENDING;
			}
			$sql = "INSERT IGNORE INTO `$t` (newsletter_id, subscriber_id, status, attempts) VALUES " . implode( ',', $valeurs );
			$wpdb->query( $wpdb->prepare( $sql, $params ) );
		}

		return self::counts( $newsletter_id )['total'];
	}

	/**
	 * Lot suivant à traiter.
	 *
	 * @return array<int,array{id:int,subscriber_id:int,attempts:int}>
	 */
	public static function next_batch( int $newsletter_id, int $taille ): array {
		global $wpdb;
		$t      = self::table();
		$taille = max( 1, min( 200, $taille ) );

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, subscriber_id, attempts FROM `$t`
				 WHERE newsletter_id = %d AND status = %s
				 ORDER BY id ASC LIMIT %d",
				$newsletter_id,
				self::STATUS_PENDING,
				$taille
			),
			ARRAY_A
		);

		return array_map(
			static function ( $r ) {
				return array(
					'id'            => (int) $r['id'],
					'subscriber_id' => (int) $r['subscriber_id'],
					'attempts'      => (int) $r['attempts'],
				);
			},
			$lignes
		);
	}

	/**
	 * Réserve une ligne avant l'envoi.
	 *
	 * La ligne passe de « pending » à « sending » en une seule requête : si deux
	 * exécutions se chevauchent, une seule gagne et la personne ne reçoit pas
	 * l'e-mail en double. Une ligne restée « sending » (processus tué en plein
	 * envoi) n'est JAMAIS renvoyée d'office : on ne sait pas si le message est
	 * parti, et un doublon est pire qu'un oubli signalé (cf. release_stale()).
	 */
	public static function claim( int $id ): bool {
		global $wpdb;
		$t = self::table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$t` SET status = %s, sent_at = %s WHERE id = %d AND status = %s",
				self::STATUS_SENDING,
				current_time( 'mysql' ),
				$id,
				self::STATUS_PENDING
			)
		);
		return 1 === (int) $wpdb->rows_affected;
	}

	/**
	 * Lignes restées « sending » depuis trop longtemps : le processus est mort
	 * pendant l'envoi. Elles passent en échec avec 2 tentatives, donc la reprise
	 * automatique ne les rejoue pas, et le journal montre l'adresse à vérifier.
	 *
	 * @return int Nombre de lignes libérées.
	 */
	public static function release_stale( int $newsletter_id, int $secondes = 600 ): int {
		global $wpdb;
		$t      = self::table();
		$depuis = current_datetime()->modify( '-' . max( 60, $secondes ) . ' seconds' )->format( 'Y-m-d H:i:s' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$t` SET status = %s, attempts = 2, last_error = %s
				 WHERE newsletter_id = %d AND status = %s AND sent_at < %s",
				self::STATUS_FAILED,
				__( 'Envoi interrompu : on ignore si le message est parti, à vérifier avant de renvoyer.', 'wam-newsletter' ),
				$newsletter_id,
				self::STATUS_SENDING,
				$depuis
			)
		);
		return (int) $wpdb->rows_affected;
	}

	/** Cette personne figure-t-elle dans la file de cet envoi ? */
	public static function has( int $newsletter_id, int $subscriber_id ): bool {
		global $wpdb;
		$t = self::table();
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM `$t` WHERE newsletter_id = %d AND subscriber_id = %d LIMIT 1", $newsletter_id, $subscriber_id )
		);
	}

	/** Dernière newsletter réellement envoyée à cette personne (0 si aucune). */
	public static function last_sent_newsletter( int $subscriber_id ): int {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT newsletter_id FROM `$t` WHERE subscriber_id = %d AND status = %s ORDER BY sent_at DESC, id DESC LIMIT 1",
				$subscriber_id,
				self::STATUS_SENT
			)
		);
	}

	public static function mark_sent( int $id ): void {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => self::STATUS_SENT,
				'sent_at'    => current_time( 'mysql' ),
				'last_error' => null,
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Ligne écartée sans tentative d'envoi (personne désabonnée entre-temps).
	 * Sans horodatage, elle ne compte pas dans les échecs de l'heure, et deux
	 * tentatives simulées la sortent de la reprise automatique.
	 */
	public static function mark_skipped( int $id, string $raison ): void {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => self::STATUS_FAILED,
				'attempts'   => 2,
				'sent_at'    => null,
				'last_error' => mb_substr( $raison, 0, 500 ),
			),
			array( 'id' => $id )
		);
	}

	public static function mark_failed( int $id, string $erreur, int $tentatives ): void {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => self::STATUS_FAILED,
				'attempts'   => $tentatives,
				'last_error' => mb_substr( $erreur, 0, 500 ),
			),
			array( 'id' => $id )
		);
	}

	/** @return array{pending:int,sent:int,failed:int,total:int} */
	public static function counts( int $newsletter_id ): array {
		global $wpdb;
		$t = self::table();

		$counts = array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
		);

		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) c FROM `$t` WHERE newsletter_id = %d GROUP BY status", $newsletter_id ), ARRAY_A ) as $r ) {
			$counts[ (string) $r['status'] ] = (int) $r['c'];
		}

		$counts['total'] = array_sum( $counts );
		return $counts;
	}

	/**
	 * Remet les échecs en attente, une seule fois (§8.2).
	 *
	 * `attempts < 2` : un échec rejoué indéfiniment consommerait la limite
	 * horaire d'o2switch sans jamais aboutir. Une adresse qui échoue deux fois
	 * est laissée en échec, à regarder à la main.
	 */
	public static function requeue_failed( int $newsletter_id ): int {
		global $wpdb;
		$t = self::table();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$t` SET status = %s WHERE newsletter_id = %d AND status = %s AND attempts < 2",
				self::STATUS_PENDING,
				$newsletter_id,
				self::STATUS_FAILED
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Échecs sur l'heure glissante (§8.2, seuil M).
	 *
	 * Mesuré sur `sent_at` des lignes en échec : la colonne est renseignée à
	 * chaque tentative, qu'elle réussisse ou non.
	 */
	public static function recent_failures( int $newsletter_id, int $secondes = 3600, string $apres = '' ): int {
		global $wpdb;
		$t = self::table();

		// ⚠️ Heure LOCALE et non UTC : les colonnes datetime du plugin sont
		// écrites avec current_time('mysql'), donc en heure du site. Comparer à
		// gmdate() décalait la fenêtre de l'écart horaire (deux heures en été),
		// ce qui élargissait silencieusement le seuil de pause automatique.
		$depuis = current_datetime()->modify( '-' . max( 0, $secondes ) . ' seconds' )->format( 'Y-m-d H:i:s' );

		// $apres : instant de la dernière reprise. Les échecs antérieurs ont déjà
		// déclenché la pause ; les recompter remettrait l'envoi en pause dès le
		// premier lot repris, même réussi.
		if ( '' !== $apres && $apres > $depuis ) {
			$depuis = $apres;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$t` WHERE newsletter_id = %d AND status = %s AND sent_at IS NOT NULL AND sent_at >= %s",
				$newsletter_id,
				self::STATUS_FAILED,
				$depuis
			)
		);
	}

	/**
	 * Messages partis (ou tentés) sur l'heure glissante, TOUTES newsletters
	 * confondues : c'est ce que compte o2switch pour son plafond horaire.
	 *
	 * @return array{count:int,oldest:string} oldest = plus ancien envoi de la fenêtre (heure du site)
	 */
	public static function sent_in_last_hour(): array {
		global $wpdb;
		$t      = self::table();
		$depuis = current_datetime()->modify( '-3600 seconds' )->format( 'Y-m-d H:i:s' );
		$ligne  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS n, MIN(sent_at) AS oldest FROM `$t` WHERE status IN (%s, %s, %s) AND sent_at IS NOT NULL AND sent_at >= %s",
				self::STATUS_SENT,
				self::STATUS_FAILED,
				self::STATUS_SENDING,
				$depuis
			),
			ARRAY_A
		);
		return array(
			'count'  => (int) ( $ligne['n'] ?? 0 ),
			'oldest' => (string) ( $ligne['oldest'] ?? '' ),
		);
	}

	/**
	 * Destinataires « sans trace » : marqués envoyés, mais absents du suivi de
	 * livraison d'o2switch (jetés par le serveur avant tout relais) et sans la
	 * moindre ouverture ni clic. Ce sont ceux qui n'ont rien reçu.
	 *
	 * Une ligne déjà renvoyée une fois (`resent`) n'est jamais reproposée.
	 *
	 * @param int $min_age Âge minimum de l'envoi, en secondes (laisse au suivi le temps de voir passer le message).
	 */
	public static function undelivered_ids( int $newsletter_id, int $min_age = 0 ): array {
		global $wpdb;
		$t      = self::table();
		$e      = \WamNewsletter\Stats\Events::table();
		$limite = current_datetime()->modify( '-' . max( 0, $min_age ) . ' seconds' )->format( 'Y-m-d H:i:s' );
		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT q.id FROM `$t` q
					 WHERE q.newsletter_id = %d AND q.status = %s AND q.delivery IS NULL AND q.resent = 0
					   AND q.sent_at IS NOT NULL AND q.sent_at <= %s
					   AND NOT EXISTS ( SELECT 1 FROM `$e` ev WHERE ev.newsletter_id = q.newsletter_id AND ev.subscriber_id = q.subscriber_id AND ev.type IN ('open','click') )",
					$newsletter_id,
					self::STATUS_SENT,
					$limite
				)
			)
		);
	}

	/** Remet en file les lignes données (renvoi des non-remis), une seule fois par ligne. */
	public static function requeue_ids( array $ids ): int {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$t  = self::table();
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `$t` SET status = %s, sent_at = NULL, attempts = 0, resent = 1, last_error = %s WHERE status = %s AND resent = 0 AND id IN ($in)",
				array_merge( array( self::STATUS_PENDING, 'Renvoi : non remis au premier envoi.', self::STATUS_SENT ), $ids )
			)
		);
		return (int) $wpdb->rows_affected;
	}

	/** Horodate une tentative, pour que recent_failures() puisse la compter. */
	public static function touch_attempt( int $id ): void {
		global $wpdb;
		$wpdb->update( self::table(), array( 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
	}

	public static function clear( int $newsletter_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'newsletter_id' => $newsletter_id ) );
	}

	/**
	 * Adresses en échec, pour l'écran de statistiques.
	 *
	 * @return array<int,array{email:string,error:string}>
	 */
	public static function failures( int $newsletter_id, int $limite = 50 ): array {
		global $wpdb;
		$t = self::table();
		$s = Subscribers::table();

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sub.email, q.last_error FROM `$t` q
				 JOIN `$s` sub ON sub.id = q.subscriber_id
				 WHERE q.newsletter_id = %d AND q.status = %s
				 ORDER BY q.id ASC LIMIT %d",
				$newsletter_id,
				self::STATUS_FAILED,
				max( 1, min( 500, $limite ) )
			),
			ARRAY_A
		);

		return array_map(
			static function ( $r ) {
				return array(
					'email' => (string) $r['email'],
					'error' => (string) $r['last_error'],
				);
			},
			$lignes
		);
	}
}
