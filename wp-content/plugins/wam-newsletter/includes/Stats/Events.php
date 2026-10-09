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

	/**
	 * Ouvertures et clics dans le temps, pour la courbe de l'écran de statistiques.
	 *
	 * Par heure tant que l'activité tient en 72 heures (le gros des réactions
	 * arrive dans les premières heures), par jour au-delà. Les créneaux vides
	 * sont présents, à zéro : une courbe qui saute les heures sans activité
	 * mentirait sur le rythme.
	 *
	 * @return array{step:string,points:array<int,array{t:string,open:int,click:int}>}
	 */
	public static function timeline( int $newsletter_id ): array {
		global $wpdb;
		$t = self::table();

		$lignes = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') AS h, type, COUNT(*) AS c
				 FROM `$t`
				 WHERE newsletter_id = %d AND type IN (%s, %s)
				 GROUP BY h, type ORDER BY h ASC",
				$newsletter_id,
				self::TYPE_OPEN,
				self::TYPE_CLICK
			),
			ARRAY_A
		);
		if ( ! $lignes ) {
			return array(
				'step'   => 'hour',
				'points' => array(),
			);
		}

		$par_heure = array();
		foreach ( $lignes as $l ) {
			$par_heure[ $l['h'] ][ $l['type'] ] = (int) $l['c'];
		}

		$debut  = strtotime( (string) array_key_first( $par_heure ) );
		$fin    = strtotime( (string) array_key_last( $par_heure ) );
		$heures = (int) floor( ( $fin - $debut ) / HOUR_IN_SECONDS );
		$pas    = $heures <= 72 ? 'hour' : 'day';

		$points = array();
		if ( 'hour' === $pas ) {
			for ( $ts = $debut; $ts <= $fin; $ts += HOUR_IN_SECONDS ) {
				$cle      = gmdate( 'Y-m-d H:00:00', $ts );
				$points[] = array(
					't'     => $cle,
					'open'  => (int) ( $par_heure[ $cle ][ self::TYPE_OPEN ] ?? 0 ),
					'click' => (int) ( $par_heure[ $cle ][ self::TYPE_CLICK ] ?? 0 ),
				);
			}
		} else {
			$par_jour = array();
			foreach ( $par_heure as $h => $v ) {
				$jour = substr( $h, 0, 10 );
				$par_jour[ $jour ]['open']  = ( $par_jour[ $jour ]['open'] ?? 0 ) + (int) ( $v[ self::TYPE_OPEN ] ?? 0 );
				$par_jour[ $jour ]['click'] = ( $par_jour[ $jour ]['click'] ?? 0 ) + (int) ( $v[ self::TYPE_CLICK ] ?? 0 );
			}
			// 60 jours au plus : au-delà, la courbe n'apprend plus rien.
			$d0 = strtotime( substr( (string) array_key_first( $par_heure ), 0, 10 ) );
			$d1 = min( strtotime( substr( (string) array_key_last( $par_heure ), 0, 10 ) ), $d0 + 59 * DAY_IN_SECONDS );
			for ( $ts = $d0; $ts <= $d1; $ts += DAY_IN_SECONDS ) {
				$jour     = gmdate( 'Y-m-d', $ts );
				$points[] = array(
					't'     => $jour . ' 00:00:00',
					'open'  => (int) ( $par_jour[ $jour ]['open'] ?? 0 ),
					'click' => (int) ( $par_jour[ $jour ]['click'] ?? 0 ),
				);
			}
		}

		return array(
			'step'   => $pas,
			'points' => $points,
		);
	}

	public static function purge_newsletter( int $newsletter_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'newsletter_id' => $newsletter_id ) );
	}
}
