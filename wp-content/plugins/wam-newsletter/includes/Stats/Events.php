<?php
namespace WamNewsletter\Stats;

use WamNewsletter\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Journal des événements : ouvertures, clics, désinscriptions (§3, §10).
 */
class Events {

	const TYPE_OPEN        = 'open';
	const TYPE_CLICK       = 'click';
	const TYPE_UNSUBSCRIBE = 'unsubscribe';

	const TYPES = array( self::TYPE_OPEN, self::TYPE_CLICK, self::TYPE_UNSUBSCRIBE );

	public static function table(): string {
		return Install::table( 'events' );
	}

	public static function record( int $newsletter_id, int $subscriber_id, string $type, string $url = '' ): bool {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return false;
		}

		global $wpdb;
		return (bool) $wpdb->insert(
			self::table(),
			array(
				'newsletter_id' => $newsletter_id,
				'subscriber_id' => $subscriber_id,
				'type'          => $type,
				'url'           => '' !== $url ? $url : null,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	public static function count( int $newsletter_id, string $type ): int {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE newsletter_id = %d AND type = %s", $newsletter_id, $type )
		);
	}

	/** Nombre de personnes distinctes, et non d'événements. */
	public static function count_unique( int $newsletter_id, string $type ): int {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT subscriber_id) FROM `$t` WHERE newsletter_id = %d AND type = %s", $newsletter_id, $type )
		);
	}

	/**
	 * Liens les plus cliqués.
	 *
	 * @return array<int,array{url:string,total:int,uniques:int}>
	 */
	public static function top_links( int $newsletter_id, int $limite = 10 ): array {
		global $wpdb;
		$t = self::table();

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url, COUNT(*) total, COUNT(DISTINCT subscriber_id) uniques
				 FROM `$t`
				 WHERE newsletter_id = %d AND type = %s AND url IS NOT NULL AND url <> ''
				 GROUP BY url ORDER BY total DESC LIMIT %d",
				$newsletter_id,
				self::TYPE_CLICK,
				max( 1, min( 50, $limite ) )
			),
			ARRAY_A
		);

		return array_map(
			static function ( $r ) {
				return array(
					'url'     => (string) $r['url'],
					'total'   => (int) $r['total'],
					'uniques' => (int) $r['uniques'],
				);
			},
			$lignes
		);
	}

	/**
	 * Un événement n'est enregistré qu'une fois par personne et par type pour
	 * les ouvertures.
	 *
	 * Apple Mail précharge les images : sans ce garde-fou, une seule personne
	 * pourrait générer des dizaines d'ouvertures et le taux affiché deviendrait
	 * absurde. Les clics, eux, sont tous conservés (total et uniques sont deux
	 * chiffres utiles).
	 */
	public static function record_open_once( int $newsletter_id, int $subscriber_id ): bool {
		global $wpdb;
		$t = self::table();

		$existe = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$t` WHERE newsletter_id = %d AND subscriber_id = %d AND type = %s",
				$newsletter_id,
				$subscriber_id,
				self::TYPE_OPEN
			)
		);
		if ( $existe > 0 ) {
			return false;
		}

		return self::record( $newsletter_id, $subscriber_id, self::TYPE_OPEN );
	}

	public static function purge_newsletter( int $newsletter_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'newsletter_id' => $newsletter_id ) );
	}
}
