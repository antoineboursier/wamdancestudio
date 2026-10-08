<?php
namespace WamNewsletter\Integrations;

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Migration one-shot depuis MailPoet (§4.1).
 *
 * Lit les tables MailPoet directement, sans charger son code : le plugin est
 * destiné à disparaître (lot 9), et la migration doit pouvoir tourner même une
 * fois MailPoet désactivé — ce qui est précisément le cas en local depuis que
 * son détournement de wp_mail a été découvert.
 *
 * Idempotente : relancée, elle ne crée aucun doublon et ne réabonne personne.
 */
class MailPoetMigrator {

	/**
	 * Types de segments MailPoet à ne PAS recréer en listes (§4.1).
	 *
	 * « WordPress Users » et « WooCommerce Customers » sont des listes
	 * automatiques : leur contenu est recalculé par MailPoet à chaque
	 * changement. Les recopier donnerait une liste figée et trompeuse, qui ne
	 * correspondrait plus à rien trois mois plus tard. Les personnes qui s'y
	 * trouvent sont de toute façon importées, via leurs autres listes ou via
	 * l'import WooCommerce du §4.2.
	 */
	const SEGMENTS_AUTOMATIQUES = array( 'wp_users', 'woocommerce_users', 'dynamic' );

	private static function table( string $nom ): string {
		global $wpdb;
		return $wpdb->prefix . 'mailpoet_' . $nom;
	}

	public static function table_exists( string $nom ): bool {
		global $wpdb;
		$t = self::table( $nom );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	public static function available(): bool {
		return self::table_exists( 'subscribers' ) && self::table_exists( 'segments' );
	}

	/**
	 * Segments MailPoet à recréer en listes.
	 *
	 * @return array<int,array{id:int,name:string,type:string}>
	 */
	public static function importable_segments(): array {
		if ( ! self::available() ) {
			return array();
		}
		global $wpdb;
		$t   = self::table( 'segments' );
		$in  = implode( ',', array_fill( 0, count( self::SEGMENTS_AUTOMATIQUES ), '%s' ) );
		$sql = "SELECT id, name, type FROM `$t` WHERE deleted_at IS NULL AND type NOT IN ($in) ORDER BY id ASC";

		$sortie = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, self::SEGMENTS_AUTOMATIQUES ), ARRAY_A ) as $r ) {
			$sortie[] = array(
				'id'   => (int) $r['id'],
				'name' => (string) $r['name'],
				'type' => (string) $r['type'],
			);
		}
		return $sortie;
	}

	/** Nombre d'abonné·es MailPoet éligibles : statut « subscribed », non supprimés. */
	public static function eligible_count(): int {
		if ( ! self::available() ) {
			return 0;
		}
		global $wpdb;
		$t = self::table( 'subscribers' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE status = %s AND deleted_at IS NULL", 'subscribed' ) );
	}

	/**
	 * Lance la migration.
	 *
	 * @param array $opts dry_run (bool), batch (int)
	 * @return array{created:int,updated:int,skipped:int,invalid:int,total:int,lists:array<string,int>,errors:string[],dry_run:bool}
	 */
	public static function run( array $opts = array() ): array {
		$a_blanc = ! empty( $opts['dry_run'] );
		$rapport = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'invalid' => 0,
			'total'   => 0,
			'lists'   => array(),
			'errors'  => array(),
			'dry_run' => $a_blanc,
			'suppressed' => 0,
		);

		if ( ! self::available() ) {
			$rapport['errors'][] = __( 'Tables MailPoet introuvables : rien à migrer.', 'wam-newsletter' );
			return $rapport;
		}

		global $wpdb;

		// 1. Les listes. En mode à blanc on ne crée rien : on associe l'identifiant
		// de segment à 0, et la correspondance des listes reste vide côté plugin.
		$segments = self::importable_segments();
		$map      = array();
		foreach ( $segments as $segment ) {
			$list_id                       = $a_blanc ? 0 : Lists::ensure( $segment['name'], __( 'Importée depuis MailPoet.', 'wam-newsletter' ) );
			$map[ $segment['id'] ]         = $list_id;
			$rapport['lists'][ $segment['name'] ] = 0;
		}

		// 2. Les abonné·es, par pages : 1 843 lignes tiennent en mémoire, mais la
		// table en compte 2 766 et rien ne garantit qu'elle ne grossira pas.
		$taille    = max( 50, min( 1000, (int) ( $opts['batch'] ?? 500 ) ) );
		$t_abonnes = self::table( 'subscribers' );
		$t_pivot   = self::table( 'subscriber_segment' );
		$offset    = 0;

		do {
			$lignes = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, email, first_name, last_name, created_at, confirmed_at, last_subscribed_at
					 FROM `$t_abonnes`
					 WHERE status = %s AND deleted_at IS NULL
					 ORDER BY id ASC LIMIT %d OFFSET %d",
					'subscribed',
					$taille,
					$offset
				),
				ARRAY_A
			);

			foreach ( $lignes as $mp ) {
				++$rapport['total'];

				$email = Subscribers::normalize_email( $mp['email'] );
				if ( '' === $email ) {
					++$rapport['invalid'];
					continue;
				}

				// Segments de cette personne, limités à ceux qu'on recrée.
				$segments_perso = array();
				if ( self::table_exists( 'subscriber_segment' ) && $map ) {
					$ids_segments = array_keys( $map );
					$in           = implode( ',', array_fill( 0, count( $ids_segments ), '%d' ) );
					$segments_perso = array_map(
						'intval',
						(array) $wpdb->get_col(
							$wpdb->prepare(
								"SELECT segment_id FROM `$t_pivot` WHERE subscriber_id = %d AND status = %s AND segment_id IN ($in)",
								array_merge( array( (int) $mp['id'], 'subscribed' ), $ids_segments )
							)
						)
					);
				}

				$listes = array();
				foreach ( $segments_perso as $segment_id ) {
					if ( ! empty( $map[ $segment_id ] ) ) {
						$listes[] = $map[ $segment_id ];
					}
					foreach ( $segments as $segment ) {
						if ( $segment['id'] === $segment_id ) {
							++$rapport['lists'][ $segment['name'] ];
						}
					}
				}

				// consent_at : date d'abonnement MailPoet si disponible, sinon
				// création. On ne fabrique jamais une date de consentement (§4.2).
				$consentement = Subscribers::datetime( $mp['last_subscribed_at'] )
					?: Subscribers::datetime( $mp['confirmed_at'] )
					?: Subscribers::datetime( $mp['created_at'] );

				if ( $a_blanc ) {
					$existant = Subscribers::find_by_email( $email );
					if ( ! $existant ) {
						++$rapport['created'];
					} elseif ( Subscribers::STATUS_UNSUBSCRIBED === $existant['status'] ) {
						++$rapport['skipped'];
					} else {
						++$rapport['updated'];
					}
					continue;
				}

				$resultat = Subscribers::upsert(
					array(
						'email'          => $email,
						'first_name'     => (string) $mp['first_name'],
						'last_name'      => (string) $mp['last_name'],
						'consent_source' => 'mailpoet',
						'consent_at'     => $consentement,
						'created_at'     => Subscribers::datetime( $mp['created_at'] ),
						'list_ids'       => $listes,
					)
				);

				if ( is_wp_error( $resultat ) ) {
					++$rapport['invalid'];
					continue;
				}

				if ( 'created' === $resultat['action'] ) {
					++$rapport['created'];
				} elseif ( 'updated' === $resultat['action'] ) {
					++$rapport['updated'];
				} else {
					++$rapport['skipped'];
				}
			}

			$offset += $taille;
		} while ( count( $lignes ) === $taille );

		$rapport['suppressed'] = self::import_suppressions( $a_blanc );

		return $rapport;
	}

	/**
	 * Reprend la liste de suppression de MailPoet : désabonné·es et rebonds.
	 *
	 * Ces personnes ne reçoivent rien, mais elles DOIVENT exister chez nous avec
	 * leur statut. Sinon l'import WooCommerce, qui rattrape tout client absent de
	 * la table, les recréerait comme abonné·es et la première newsletter partirait
	 * vers quelqu'un qui s'était désinscrit·e (RGPD), ou vers une adresse en
	 * rebond (qui pèse sur la limite d'échecs horaire d'o2switch).
	 *
	 * @return int Nombre de personnes ajoutées à la liste de suppression.
	 */
	private static function import_suppressions( bool $a_blanc ): int {
		global $wpdb;
		$t      = self::table( 'subscribers' );
		$ajoute = 0;
		$offset = 0;
		$taille = 500;

		do {
			$lignes = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT email, first_name, last_name, status, created_at
					 FROM `$t`
					 WHERE status IN (%s, %s) AND deleted_at IS NULL
					 ORDER BY id ASC LIMIT %d OFFSET %d",
					'unsubscribed',
					'bounced',
					$taille,
					$offset
				),
				ARRAY_A
			);

			foreach ( $lignes as $mp ) {
				$email = Subscribers::normalize_email( $mp['email'] );
				if ( '' === $email || Subscribers::find_by_email( $email ) ) {
					continue;
				}
				++$ajoute;
				if ( $a_blanc ) {
					continue;
				}
				Subscribers::insert(
					array(
						'email'          => $email,
						'first_name'     => (string) $mp['first_name'],
						'last_name'      => (string) $mp['last_name'],
						'status'         => 'bounced' === $mp['status'] ? Subscribers::STATUS_BOUNCED : Subscribers::STATUS_UNSUBSCRIBED,
						'consent_source' => 'mailpoet',
						'created_at'     => Subscribers::datetime( $mp['created_at'] ),
					)
				);
			}

			$offset += $taille;
		} while ( count( $lignes ) === $taille );

		return $ajoute;
	}

	/**
	 * Sauvegarde CSV des listes MailPoet, avant la bascule du lot 9.
	 *
	 * Volontairement indépendante du plugin : elle relit MailPoet, pas nos
	 * tables, pour que le fichier témoigne de l'état d'origine.
	 */
	public static function export_backup(): string {
		if ( ! self::available() ) {
			return '';
		}
		global $wpdb;

		$t_abonnes = self::table( 'subscribers' );
		$t_pivot   = self::table( 'subscriber_segment' );
		$t_segs    = self::table( 'segments' );

		$flux = fopen( 'php://temp', 'r+' );
		fwrite( $flux, "\xEF\xBB\xBF" );
		fputcsv( $flux, array( 'email', 'prenom', 'nom', 'statut_mailpoet', 'segments', 'cree_le', 'abonne_le' ), ';' );

		$offset = 0;
		do {
			$lignes = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, email, first_name, last_name, status, created_at, last_subscribed_at
					 FROM `$t_abonnes` WHERE deleted_at IS NULL ORDER BY id ASC LIMIT %d OFFSET %d",
					500,
					$offset
				),
				ARRAY_A
			);
			foreach ( $lignes as $r ) {
				$noms = (array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT s.name FROM `$t_pivot` ss JOIN `$t_segs` s ON s.id = ss.segment_id WHERE ss.subscriber_id = %d AND ss.status = %s",
						(int) $r['id'],
						'subscribed'
					)
				);
				fputcsv(
					$flux,
					array(
						(string) $r['email'],
						(string) $r['first_name'],
						(string) $r['last_name'],
						(string) $r['status'],
						implode( ' | ', $noms ),
						(string) $r['created_at'],
						(string) $r['last_subscribed_at'],
					),
					';'
				);
			}
			$offset += 500;
		} while ( count( $lignes ) === 500 );

		rewind( $flux );
		$contenu = (string) stream_get_contents( $flux );
		fclose( $flux );
		return $contenu;
	}
}
