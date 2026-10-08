<?php
namespace WamNewsletter\Subscribers;

use WamNewsletter\Install;
use WamNewsletter\Lists\Repository as Lists;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Accès aux abonné·es. Seul endroit du plugin qui écrit dans la table.
 *
 * Deux invariants tenus ici plutôt que chez les appelants, pour qu'aucun chemin
 * d'import ne puisse les contourner :
 *  - l'e-mail est toujours normalisé en minuscules (le dédoublonnage en dépend) ;
 *  - un·e abonné·e passé·e en « unsubscribed » n'est JAMAIS réabonné·e, sauf
 *    demande explicite de la personne elle-même via le formulaire
 *    (allow_resubscribe), ce qu'exige le §4.2 du cahier des charges.
 */
class Repository {

	const STATUS_SUBSCRIBED   = 'subscribed';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';
	const STATUS_BOUNCED      = 'bounced';
	const STATUS_TRASHED      = 'trashed';

	const STATUSES = array(
		self::STATUS_SUBSCRIBED,
		self::STATUS_UNSUBSCRIBED,
		self::STATUS_BOUNCED,
		self::STATUS_TRASHED,
	);

	/** Sources de consentement admises (§3). */
	const SOURCES = array( 'form', 'import', 'mailpoet', 'woocommerce', 'admin' );

	public static function table(): string {
		return Install::table( 'subscribers' );
	}

	public static function pivot(): string {
		return Install::table( 'list_subscriber' );
	}

	public static function status_label( string $statut ): string {
		$labels = array(
			self::STATUS_SUBSCRIBED   => __( 'Abonné·e', 'wam-newsletter' ),
			self::STATUS_UNSUBSCRIBED => __( 'Désabonné·e', 'wam-newsletter' ),
			self::STATUS_BOUNCED      => __( 'Rebond', 'wam-newsletter' ),
			self::STATUS_TRASHED      => __( 'Corbeille', 'wam-newsletter' ),
		);
		return $labels[ $statut ] ?? $statut;
	}

	public static function normalize_email( $valeur ): string {
		if ( ! is_scalar( $valeur ) ) {
			return '';
		}
		$e = sanitize_email( strtolower( trim( (string) $valeur ) ) );
		return is_email( $e ) ? $e : '';
	}

	/** Token de 64 caractères hexadécimaux, imprévisible (§12). */
	public static function generate_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	public static function is_token( $valeur ): bool {
		return is_string( $valeur ) && 1 === preg_match( '/^[0-9a-f]{64}$/', $valeur );
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$t` WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public static function find_by_email( string $email ): ?array {
		$email = self::normalize_email( $email );
		if ( '' === $email ) {
			return null;
		}
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$t` WHERE email = %s", $email ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Recherche par token. Volontairement strict sur le format : une valeur qui
	 * n'est pas 64 caractères hexadécimaux ne peut pas être un token, on ne
	 * requête même pas la base.
	 */
	public static function find_by_token( string $token ): ?array {
		if ( ! self::is_token( $token ) ) {
			return null;
		}
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$t` WHERE token = %s", $token ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Garantit un token exploitable sur une ligne existante.
	 *
	 * Utile pour les contacts migrés : le lien de désinscription du §8.3 est
	 * obligatoire, un token manquant rendrait l'e-mail non conforme.
	 */
	public static function ensure_token( int $id ): string {
		$ligne = self::find( $id );
		if ( ! $ligne ) {
			return '';
		}
		if ( self::is_token( $ligne['token'] ) ) {
			return (string) $ligne['token'];
		}
		global $wpdb;
		$token = self::generate_token();
		$wpdb->update( self::table(), array( 'token' => $token ), array( 'id' => $id ) );
		return $token;
	}

	/**
	 * Clause WHERE commune à query() et count(), pour que les deux ne puissent
	 * pas diverger — des filtres différents donneraient une pagination fausse.
	 *
	 * @return array{0:string,1:array} SQL et paramètres à préparer.
	 */
	private static function where( array $args ): array {
		global $wpdb;

		$p    = self::pivot();
		$sql  = array( '1=1' );
		$vals = array();

		if ( ! empty( $args['status'] ) ) {
			$statuts = array_values( array_intersect( (array) $args['status'], self::STATUSES ) );
			if ( ! $statuts ) {
				return array( '1=0', array() );
			}
			$sql[] = 'status IN (' . implode( ',', array_fill( 0, count( $statuts ), '%s' ) ) . ')';
			$vals  = array_merge( $vals, $statuts );
		} elseif ( empty( $args['include_trashed'] ) ) {
			// « Tous » exclut la corbeille, comme les listes natives de WordPress.
			$sql[]  = 'status <> %s';
			$vals[] = self::STATUS_TRASHED;
		}

		if ( ! empty( $args['list_id'] ) ) {
			$sql[]  = "id IN (SELECT subscriber_id FROM `$p` WHERE list_id = %d)";
			$vals[] = (int) $args['list_id'];
		}

		if ( isset( $args['search'] ) && '' !== trim( (string) $args['search'] ) ) {
			$like   = '%' . $wpdb->esc_like( trim( (string) $args['search'] ) ) . '%';
			$sql[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$vals[] = $like;
			$vals[] = $like;
			$vals[] = $like;
		}

		return array( implode( ' AND ', $sql ), $vals );
	}

	/**
	 * @param array $args status[], list_id, search, orderby, order, per_page, page
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$t = self::table();
		list( $where, $vals ) = self::where( $args );

		$colonnes = array( 'email', 'first_name', 'last_name', 'status', 'created_at' );
		$orderby  = in_array( $args['orderby'] ?? '', $colonnes, true ) ? $args['orderby'] : 'created_at';
		$order    = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';

		$per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 25 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		// $orderby et $order sortent de listes blanches ; tout le reste est préparé.
		$sql    = "SELECT * FROM `$t` WHERE $where ORDER BY `$orderby` $order, id DESC LIMIT %d OFFSET %d";
		$vals[] = $per_page;
		$vals[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ) ?: array();
	}

	public static function count( array $args = array() ): int {
		global $wpdb;
		$t = self::table();
		list( $where, $vals ) = self::where( $args );
		$sql = "SELECT COUNT(*) FROM `$t` WHERE $where";
		return (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $sql, $vals ) ) : $wpdb->get_var( $sql ) );
	}

	/** @return array<string,int> statut => nombre */
	public static function counts_by_status(): array {
		global $wpdb;
		$t      = self::table();
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) c FROM `$t` GROUP BY status", ARRAY_A ) as $r ) {
			$counts[ $r['status'] ] = (int) $r['c'];
		}
		return $counts;
	}

	/**
	 * Listes de plusieurs abonné·es en une seule requête.
	 *
	 * L'écran affiche la colonne « listes » pour 25 lignes : une requête par
	 * ligne ferait 25 allers-retours à chaque chargement.
	 *
	 * @param int[] $ids
	 * @return array<int,array<int,string>> subscriber_id => [list_id => nom]
	 */
	public static function lists_for( array $ids ): array {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		global $wpdb;
		$p   = self::pivot();
		$l   = Lists::table();
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "SELECT ls.subscriber_id, l.id, l.name FROM `$p` ls JOIN `$l` l ON l.id = ls.list_id WHERE ls.subscriber_id IN ($in) ORDER BY l.name ASC";

		$sortie = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A ) as $r ) {
			$sortie[ (int) $r['subscriber_id'] ][ (int) $r['id'] ] = (string) $r['name'];
		}
		return $sortie;
	}

	/** @return int[] */
	public static function list_ids_of( int $subscriber_id ): array {
		global $wpdb;
		$p = self::pivot();
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT list_id FROM `$p` WHERE subscriber_id = %d", $subscriber_id ) ) );
	}

	/**
	 * @return int|WP_Error Identifiant créé.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$email = self::normalize_email( $data['email'] ?? '' );
		if ( '' === $email ) {
			return new WP_Error( 'wam_nl_email', __( 'Adresse e-mail invalide.', 'wam-newsletter' ) );
		}
		if ( self::find_by_email( $email ) ) {
			return new WP_Error( 'wam_nl_doublon', __( 'Cette adresse est déjà enregistrée.', 'wam-newsletter' ) );
		}

		$maintenant = current_time( 'mysql' );
		$statut     = in_array( $data['status'] ?? '', self::STATUSES, true ) ? $data['status'] : self::STATUS_SUBSCRIBED;
		$source     = in_array( $data['consent_source'] ?? '', self::SOURCES, true ) ? $data['consent_source'] : 'admin';

		$ligne = array(
			'email'           => $email,
			'first_name'      => sanitize_text_field( (string) ( $data['first_name'] ?? '' ) ),
			'last_name'       => sanitize_text_field( (string) ( $data['last_name'] ?? '' ) ),
			'status'          => $statut,
			'token'           => self::generate_token(),
			'consent_at'      => self::datetime( $data['consent_at'] ?? null ) ?: $maintenant,
			'consent_source'  => $source,
			'created_at'      => self::datetime( $data['created_at'] ?? null ) ?: $maintenant,
			'updated_at'      => $maintenant,
			'unsubscribed_at' => self::STATUS_UNSUBSCRIBED === $statut ? $maintenant : null,
		);

		if ( ! $wpdb->insert( self::table(), $ligne ) ) {
			return new WP_Error( 'wam_nl_insert', __( "L'enregistrement a échoué.", 'wam-newsletter' ) );
		}

		$id = (int) $wpdb->insert_id;
		if ( ! empty( $data['list_ids'] ) ) {
			self::add_to_lists( $id, (array) $data['list_ids'] );
		}
		return $id;
	}

	/**
	 * Normalise une date vers le format MySQL, ou '' si inexploitable.
	 *
	 * Les dates venant de MailPoet peuvent être nulles ou en « 0000-00-00 » :
	 * on ne veut ni l'une ni l'autre en base, et surtout pas inventer une date
	 * de consentement (§4.2).
	 */
	public static function datetime( $valeur ): string {
		if ( ! is_scalar( $valeur ) || '' === (string) $valeur ) {
			return '';
		}
		$brut = (string) $valeur;
		if ( 0 === strpos( $brut, '0000-00-00' ) ) {
			return '';
		}
		$ts = strtotime( $brut );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}

	/**
	 * Met à jour les champs fournis uniquement. Le changement de statut passe par
	 * set_status(), qui tient unsubscribed_at à jour.
	 *
	 * @return true|WP_Error
	 */
	public static function update( int $id, array $data ) {
		$existant = self::find( $id );
		if ( ! $existant ) {
			return new WP_Error( 'wam_nl_absent', __( 'Abonné·e introuvable.', 'wam-newsletter' ) );
		}

		$ligne = array();

		if ( array_key_exists( 'email', $data ) ) {
			$email = self::normalize_email( $data['email'] );
			if ( '' === $email ) {
				return new WP_Error( 'wam_nl_email', __( 'Adresse e-mail invalide.', 'wam-newsletter' ) );
			}
			$autre = self::find_by_email( $email );
			if ( $autre && (int) $autre['id'] !== $id ) {
				return new WP_Error( 'wam_nl_doublon', __( 'Cette adresse est déjà utilisée par un autre abonné·e.', 'wam-newsletter' ) );
			}
			$ligne['email'] = $email;
		}

		foreach ( array( 'first_name', 'last_name' ) as $cle ) {
			if ( array_key_exists( $cle, $data ) ) {
				$ligne[ $cle ] = sanitize_text_field( (string) $data[ $cle ] );
			}
		}

		if ( $ligne ) {
			global $wpdb;
			$ligne['updated_at'] = current_time( 'mysql' );
			$wpdb->update( self::table(), $ligne, array( 'id' => $id ) );
		}

		if ( array_key_exists( 'status', $data ) && $data['status'] !== $existant['status'] ) {
			self::set_status( $id, (string) $data['status'] );
		}

		if ( array_key_exists( 'list_ids', $data ) ) {
			self::set_lists( $id, (array) $data['list_ids'] );
		}

		return true;
	}

	public static function set_status( int $id, string $statut ): bool {
		if ( ! in_array( $statut, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		$ligne = array(
			'status'     => $statut,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( self::STATUS_UNSUBSCRIBED === $statut ) {
			$ligne['unsubscribed_at'] = current_time( 'mysql' );
		}
		return false !== $wpdb->update( self::table(), $ligne, array( 'id' => $id ) );
	}

	/**
	 * Réabonne une personne désinscrite. À n'appeler qu'après une preuve que la
	 * personne possède l'adresse (clic sur le lien envoyé par e-mail).
	 */
	public static function resubscribe( int $id, string $source = 'form' ): bool {
		global $wpdb;
		$ligne = array(
			'status'          => self::STATUS_SUBSCRIBED,
			'unsubscribed_at' => null,
			'consent_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);
		if ( in_array( $source, self::SOURCES, true ) ) {
			$ligne['consent_source'] = $source;
		}
		return false !== $wpdb->update( self::table(), $ligne, array( 'id' => $id ) );
	}

	/**
	 * Crée ou complète un·e abonné·e, sans jamais réabonner un désabonné·e.
	 *
	 * C'est le point d'entrée de tous les imports : la garantie « aucun
	 * unsubscribed réabonné » (§4.2, non négociable) est tenue ici, une seule
	 * fois, plutôt que répétée dans chaque importeur.
	 *
	 * @param array $data email, first_name, last_name, status, consent_source, consent_at, created_at, list_ids
	 * @param array $opts allow_resubscribe (formulaire uniquement), fill_names
	 * @return array{id:int,action:string,resubscribed:bool}|WP_Error
	 *               action : created | updated | unchanged
	 */
	public static function upsert( array $data, array $opts = array() ) {
		$email = self::normalize_email( $data['email'] ?? '' );
		if ( '' === $email ) {
			return new WP_Error( 'wam_nl_email', __( 'Adresse e-mail invalide.', 'wam-newsletter' ) );
		}

		$autoriser_reabo = ! empty( $opts['allow_resubscribe'] );
		$completer_noms  = ! array_key_exists( 'fill_names', $opts ) || $opts['fill_names'];
		$listes          = array_values( array_filter( array_map( 'intval', (array) ( $data['list_ids'] ?? array() ) ) ) );

		$existant = self::find_by_email( $email );

		if ( ! $existant ) {
			$data['email'] = $email;
			$id            = self::insert( $data );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			return array(
				'id'           => $id,
				'action'       => 'created',
				'resubscribed' => false,
			);
		}

		$id      = (int) $existant['id'];
		$modifie = false;
		$reabo   = false;
		$ligne   = array();

		// Noms : on complète ce qui manque, on n'écrase jamais une saisie existante.
		if ( $completer_noms ) {
			foreach ( array( 'first_name', 'last_name' ) as $cle ) {
				$nouveau = sanitize_text_field( (string) ( $data[ $cle ] ?? '' ) );
				if ( '' !== $nouveau && '' === (string) $existant[ $cle ] ) {
					$ligne[ $cle ] = $nouveau;
				}
			}
		}

		// Un token manquant (donnée héritée) est complété au passage.
		if ( ! self::is_token( $existant['token'] ) ) {
			$ligne['token'] = self::generate_token();
		}

		if ( self::STATUS_UNSUBSCRIBED === $existant['status'] && $autoriser_reabo ) {
			$ligne['status']          = self::STATUS_SUBSCRIBED;
			$ligne['unsubscribed_at'] = null;
			$ligne['consent_at']      = self::datetime( $data['consent_at'] ?? null ) ?: current_time( 'mysql' );
			if ( in_array( $data['consent_source'] ?? '', self::SOURCES, true ) ) {
				$ligne['consent_source'] = $data['consent_source'];
			}
			$reabo = true;
		}

		if ( $ligne ) {
			global $wpdb;
			$ligne['updated_at'] = current_time( 'mysql' );
			$wpdb->update( self::table(), $ligne, array( 'id' => $id ) );
			$modifie = true;
		}

		if ( $listes ) {
			$modifie = self::add_to_lists( $id, $listes ) > 0 || $modifie;
		}

		return array(
			'id'           => $id,
			'action'       => $modifie ? 'updated' : 'unchanged',
			'resubscribed' => $reabo,
		);
	}

	/** @return int Nombre d'associations réellement créées. */
	public static function add_to_lists( int $subscriber_id, array $list_ids ): int {
		global $wpdb;
		$p     = self::pivot();
		$crees = 0;
		foreach ( Lists::existing_ids( $list_ids ) as $list_id ) {
			// INSERT IGNORE : la clé primaire composite rend l'opération
			// idempotente, ce qui évite un SELECT par liste à chaque import.
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$p` (list_id, subscriber_id) VALUES (%d, %d)", $list_id, $subscriber_id ) );
			$crees += (int) $wpdb->rows_affected;
		}
		return $crees;
	}

	public static function remove_from_lists( int $subscriber_id, array $list_ids ): void {
		$ids = array_values( array_filter( array_map( 'intval', $list_ids ) ) );
		if ( ! $ids ) {
			return;
		}
		global $wpdb;
		$p  = self::pivot();
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$p` WHERE subscriber_id = %d AND list_id IN ($in)", array_merge( array( $subscriber_id ), $ids ) ) );
	}

	public static function set_lists( int $subscriber_id, array $list_ids ): void {
		global $wpdb;
		$p = self::pivot();
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$p` WHERE subscriber_id = %d", $subscriber_id ) );
		self::add_to_lists( $subscriber_id, $list_ids );
	}

	/**
	 * Suppression définitive : efface aussi les événements et les entrées de file
	 * liés, comme l'exige la checklist RGPD du §12.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . self::pivot() . '` WHERE subscriber_id = %d', $id ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . Install::table( 'events' ) . '` WHERE subscriber_id = %d', $id ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . Install::table( 'queue' ) . '` WHERE subscriber_id = %d', $id ) );
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * Destinataires d'un envoi : abonné·es actifs d'au moins une des listes,
	 * dédoublonnés (§8.1).
	 *
	 * @param int[] $list_ids
	 * @return int[]
	 */
	public static function recipient_ids( array $list_ids ): array {
		$ids = Lists::existing_ids( $list_ids );
		if ( ! $ids ) {
			return array();
		}
		global $wpdb;
		$t   = self::table();
		$p   = self::pivot();
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "SELECT DISTINCT s.id FROM `$t` s JOIN `$p` ls ON ls.subscriber_id = s.id WHERE ls.list_id IN ($in) AND s.status = %s ORDER BY s.id ASC";
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( $ids, array( self::STATUS_SUBSCRIBED ) ) ) ) );
	}

	public static function count_recipients( array $list_ids ): int {
		$ids = Lists::existing_ids( $list_ids );
		if ( ! $ids ) {
			return 0;
		}
		global $wpdb;
		$t   = self::table();
		$p   = self::pivot();
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "SELECT COUNT(DISTINCT s.id) FROM `$t` s JOIN `$p` ls ON ls.subscriber_id = s.id WHERE ls.list_id IN ($in) AND s.status = %s";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( $ids, array( self::STATUS_SUBSCRIBED ) ) ) );
	}
}
