<?php
namespace WamNewsletter\Sending;

use WamNewsletter\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Journal du déroulé d'un envoi.
 *
 * La file dit OÙ en est chaque destinataire ; elle ne dit pas ce qui s'est
 * passé. Quand un envoi s'arrête à 18 sur 50, la question n'est pas « qui
 * manque » mais « pourquoi ça s'est arrêté » — et sans trace horodatée des
 * lots, des pauses et des reprises, on ne peut que deviner.
 *
 * Les compteurs sont des colonnes et non du JSON : on veut pouvoir lire une
 * ligne de journal d'un coup d'œil, et au besoin l'interroger en SQL, sans
 * décoder quoi que ce soit.
 */
class Log {

	const TYPE_START     = 'start';
	const TYPE_SCHEDULED = 'scheduled';
	const TYPE_BATCH     = 'batch';
	const TYPE_PAUSE     = 'pause';
	const TYPE_RESUME    = 'resume';
	const TYPE_RETRY     = 'retry';
	const TYPE_FINISH    = 'finish';
	const TYPE_CANCEL    = 'cancel';
	const TYPE_TEST      = 'test';
	const TYPE_DELIVERY  = 'delivery';

	const TYPES = array(
		self::TYPE_START,
		self::TYPE_SCHEDULED,
		self::TYPE_BATCH,
		self::TYPE_PAUSE,
		self::TYPE_RESUME,
		self::TYPE_RETRY,
		self::TYPE_FINISH,
		self::TYPE_CANCEL,
		self::TYPE_TEST,
		self::TYPE_DELIVERY,
	);

	public static function table(): string {
		return Install::table( 'log' );
	}

	/** Libellé lisible d'un type, pour l'écran. */
	public static function label( string $type ): string {
		$libelles = array(
			self::TYPE_START     => __( 'Envoi lancé', 'wam-newsletter' ),
			self::TYPE_SCHEDULED => __( 'Envoi programmé', 'wam-newsletter' ),
			self::TYPE_BATCH     => __( 'Lot traité', 'wam-newsletter' ),
			self::TYPE_PAUSE     => __( 'Mise en pause', 'wam-newsletter' ),
			self::TYPE_RESUME    => __( 'Reprise', 'wam-newsletter' ),
			self::TYPE_RETRY     => __( 'Relance des échecs', 'wam-newsletter' ),
			self::TYPE_FINISH    => __( 'Envoi terminé', 'wam-newsletter' ),
			self::TYPE_CANCEL    => __( 'Arrêt manuel', 'wam-newsletter' ),
			self::TYPE_TEST      => __( 'E-mail de test', 'wam-newsletter' ),
			self::TYPE_DELIVERY  => __( 'Remise vérifiée', 'wam-newsletter' ),
		);
		return $libelles[ $type ] ?? $type;
	}

	/**
	 * Enregistre une entrée.
	 *
	 * Volontairement silencieux en cas de type inconnu plutôt que fatal : le
	 * journal est un outil d'observation, il ne doit jamais faire échouer un
	 * envoi en cours.
	 *
	 * @param array{sent?:int,failed?:int,pending?:int} $compteurs
	 */
	public static function record( int $newsletter_id, string $type, string $message = '', array $compteurs = array() ): bool {
		if ( $newsletter_id <= 0 || ! in_array( $type, self::TYPES, true ) ) {
			return false;
		}

		global $wpdb;

		// Heure du site, comme toutes les colonnes datetime du plugin : un
		// journal qu'il faut convertir mentalement avant de le lire ne sert à
		// rien pendant un incident.
		$ok = $wpdb->insert(
			self::table(),
			array(
				'newsletter_id' => $newsletter_id,
				'type'          => $type,
				'message'       => wp_strip_all_tags( $message ),
				'sent'          => max( 0, (int) ( $compteurs['sent'] ?? 0 ) ),
				'failed'        => max( 0, (int) ( $compteurs['failed'] ?? 0 ) ),
				'pending'       => max( 0, (int) ( $compteurs['pending'] ?? 0 ) ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return (bool) $ok;
	}

	/**
	 * Entrées d'une newsletter, la plus récente d'abord.
	 *
	 * @return array<int,array{id:int,type:string,message:string,sent:int,failed:int,pending:int,created_at:string}>
	 */
	public static function query( int $newsletter_id, int $limite = 500 ): array {
		if ( $newsletter_id <= 0 ) {
			return array();
		}

		global $wpdb;
		$t = self::table();

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `$t` WHERE newsletter_id = %d ORDER BY id DESC LIMIT %d",
				$newsletter_id,
				max( 1, min( 2000, $limite ) )
			),
			ARRAY_A
		);

		return array_map(
			static function ( $r ) {
				return array(
					'id'         => (int) $r['id'],
					'type'       => (string) $r['type'],
					'message'    => (string) $r['message'],
					'sent'       => (int) $r['sent'],
					'failed'     => (int) $r['failed'],
					'pending'    => (int) $r['pending'],
					'created_at' => (string) $r['created_at'],
				);
			},
			$lignes
		);
	}

	/** Horodatage (heure du site) de la dernière entrée d'un type, ou ''. */
	public static function last_of_type( int $newsletter_id, string $type ): string {
		global $wpdb;
		$t = self::table();
		return (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(created_at) FROM `$t` WHERE newsletter_id = %d AND type = %s", $newsletter_id, $type )
		);
	}

	public static function count( int $newsletter_id ): int {
		if ( $newsletter_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE newsletter_id = %d", $newsletter_id ) );
	}

	public static function purge_newsletter( int $newsletter_id ): void {
		if ( $newsletter_id <= 0 ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'newsletter_id' => $newsletter_id ), array( '%d' ) );
	}
}
