<?php
namespace WamNewsletter\Lists;

use WamNewsletter\Install;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Listes de diffusion. CRUD volontairement minimal (§5).
 *
 * Supprimer une liste ne supprime jamais d'abonné·e : seule l'association part.
 */
class Repository {

	public static function table(): string {
		return Install::table( 'lists' );
	}

	public static function pivot(): string {
		return Install::table( 'list_subscriber' );
	}

	/**
	 * @return array<int,array<string,mixed>> Listes avec leur nombre d'abonné·es actifs.
	 */
	public static function all(): array {
		global $wpdb;
		$t      = self::table();
		$listes = (array) $wpdb->get_results( "SELECT * FROM `$t` ORDER BY name ASC", ARRAY_A );
		$counts = self::active_counts();
		foreach ( $listes as &$liste ) {
			$liste['subscriber_count'] = $counts[ (int) $liste['id'] ] ?? 0;
		}
		return $listes;
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$t` WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public static function find_by_name( string $nom ): ?array {
		$nom = trim( $nom );
		if ( '' === $nom ) {
			return null;
		}
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$t` WHERE name = %s", $nom ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Filtre une série d'identifiants sur ceux qui existent réellement.
	 *
	 * Appelé avant toute écriture dans la table pivot : sans ce filtre, un POST
	 * bricolé ou une liste supprimée entre-temps laisserait des associations
	 * orphelines, et le compteur de destinataires deviendrait faux.
	 *
	 * @param int[] $ids
	 * @return int[]
	 */
	public static function existing_ids( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		global $wpdb;
		$t  = self::table();
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `$t` WHERE id IN ($in)", $ids ) ) );
	}

	/**
	 * @return int|WP_Error
	 */
	public static function insert( string $nom, string $description = '' ) {
		$nom = sanitize_text_field( trim( $nom ) );
		if ( '' === $nom ) {
			return new WP_Error( 'wam_nl_liste_nom', __( 'Le nom de la liste est obligatoire.', 'wam-newsletter' ) );
		}
		if ( self::find_by_name( $nom ) ) {
			return new WP_Error( 'wam_nl_liste_doublon', __( 'Une liste porte déjà ce nom.', 'wam-newsletter' ) );
		}

		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			array(
				'name'        => $nom,
				'description' => sanitize_textarea_field( $description ),
				'created_at'  => current_time( 'mysql' ),
			)
		);
		if ( ! $ok ) {
			return new WP_Error( 'wam_nl_liste_insert', __( "La création de la liste a échoué.", 'wam-newsletter' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function update( int $id, string $nom, string $description = '' ) {
		if ( ! self::find( $id ) ) {
			return new WP_Error( 'wam_nl_liste_absente', __( 'Liste introuvable.', 'wam-newsletter' ) );
		}
		$nom = sanitize_text_field( trim( $nom ) );
		if ( '' === $nom ) {
			return new WP_Error( 'wam_nl_liste_nom', __( 'Le nom de la liste est obligatoire.', 'wam-newsletter' ) );
		}
		$autre = self::find_by_name( $nom );
		if ( $autre && (int) $autre['id'] !== $id ) {
			return new WP_Error( 'wam_nl_liste_doublon', __( 'Une liste porte déjà ce nom.', 'wam-newsletter' ) );
		}

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'name'        => $nom,
				'description' => sanitize_textarea_field( $description ),
			),
			array( 'id' => $id )
		);
		return true;
	}

	/**
	 * Trouve ou crée. Utilisé par les imports, qui doivent rester idempotents.
	 *
	 * @return int 0 si la création a échoué.
	 */
	public static function ensure( string $nom, string $description = '' ): int {
		$existante = self::find_by_name( sanitize_text_field( trim( $nom ) ) );
		if ( $existante ) {
			return (int) $existante['id'];
		}
		$id = self::insert( $nom, $description );
		return is_wp_error( $id ) ? 0 : $id;
	}

	/** Retire seulement les associations : aucun·e abonné·e n'est supprimé·e (§5). */
	public static function delete( int $id ): bool {
		global $wpdb;
		$p = self::pivot();
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$p` WHERE list_id = %d", $id ) );
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * Nombre d'abonné·es ACTIFS par liste.
	 *
	 * Volontairement « actifs » et non « associés » : un contact désabonné peut
	 * rester rattaché à une liste (les imports ne le détachent pas), mais il ne
	 * recevra rien. Afficher le total brut donnerait une attente de volume fausse.
	 *
	 * @return array<int,int> list_id => nombre
	 */
	public static function active_counts(): array {
		global $wpdb;
		$p   = self::pivot();
		$s   = Subscribers::table();
		$sql = "SELECT ls.list_id, COUNT(*) c FROM `$p` ls JOIN `$s` s ON s.id = ls.subscriber_id WHERE s.status = %s GROUP BY ls.list_id";

		$sortie = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, Subscribers::STATUS_SUBSCRIBED ), ARRAY_A ) as $r ) {
			$sortie[ (int) $r['list_id'] ] = (int) $r['c'];
		}
		return $sortie;
	}

	public static function active_count( int $list_id ): int {
		$counts = self::active_counts();
		return $counts[ $list_id ] ?? 0;
	}

	/** @return array<int,string> id => nom, pour les selects. */
	public static function options(): array {
		$sortie = array();
		foreach ( self::all() as $liste ) {
			$sortie[ (int) $liste['id'] ] = (string) $liste['name'];
		}
		return $sortie;
	}
}
