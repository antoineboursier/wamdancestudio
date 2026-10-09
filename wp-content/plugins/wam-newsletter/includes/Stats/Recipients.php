<?php
namespace WamNewsletter\Stats;

use WamNewsletter\Sending\Queue;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Suivi destinataire par destinataire d'une newsletter.
 *
 * Les données existaient déjà — la file porte le statut, les tentatives et le
 * message d'erreur SMTP exact, la table d'événements porte ouvertures, clics et
 * désinscriptions — mais rien ne les rapprochait. Sur un envoi à 50 personnes,
 * dont on n'aura pas 50 retours oraux, c'est la seule façon de savoir ce qui
 * est réellement arrivé.
 *
 * Trois tables, une seule requête : les drapeaux d'ouverture et de clic sont
 * calculés en sous-requête plutôt qu'en jointure, pour qu'un destinataire ayant
 * cliqué trois fois reste UNE ligne.
 */
class Recipients {

	const FILTERS = array( 'all', 'sent', 'failed', 'pending', 'opened', 'clicked', 'unsubscribed' );

	const PER_PAGE = 50;

	/** Libellé d'un filtre, pour les onglets de l'écran. */
	public static function filter_label( string $filtre ): string {
		$libelles = array(
			'all'          => __( 'Tous', 'wam-newsletter' ),
			'sent'         => __( 'Envoyés', 'wam-newsletter' ),
			'failed'       => __( 'Échecs', 'wam-newsletter' ),
			'pending'      => __( 'En attente', 'wam-newsletter' ),
			'opened'       => __( 'Ouverts', 'wam-newsletter' ),
			'clicked'      => __( 'Ont cliqué', 'wam-newsletter' ),
			'unsubscribed' => __( 'Désinscrits', 'wam-newsletter' ),
		);
		return $libelles[ $filtre ] ?? $filtre;
	}

	/**
	 * Construit la clause WHERE commune à query() et count().
	 *
	 * Partagée volontairement : deux clauses séparées finiraient par divenger,
	 * et un comptage qui ne correspond pas à la liste affichée est pire que pas
	 * de comptage du tout.
	 *
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private static function where( int $newsletter_id, array $args ): array {
		$events = Events::table();

		$clauses = array( 'q.newsletter_id = %d' );
		$valeurs = array( $newsletter_id );

		$filtre = (string) ( $args['filter'] ?? 'all' );
		if ( ! in_array( $filtre, self::FILTERS, true ) ) {
			$filtre = 'all';
		}

		switch ( $filtre ) {
			case 'sent':
			case 'failed':
			case 'pending':
				$clauses[] = 'q.status = %s';
				$valeurs[] = $filtre;
				break;
			case 'opened':
			case 'clicked':
			case 'unsubscribed':
				$types     = array(
					'opened'       => Events::TYPE_OPEN,
					'clicked'      => Events::TYPE_CLICK,
					'unsubscribed' => Events::TYPE_UNSUBSCRIBE,
				);
				$clauses[] = "EXISTS ( SELECT 1 FROM `$events` e WHERE e.newsletter_id = q.newsletter_id AND e.subscriber_id = q.subscriber_id AND e.type = %s )";
				$valeurs[] = $types[ $filtre ];
				break;
		}

		$recherche = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $recherche ) {
			global $wpdb;
			$comme     = '%' . $wpdb->esc_like( $recherche ) . '%';
			$clauses[] = '( s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s )';
			$valeurs[] = $comme;
			$valeurs[] = $comme;
			$valeurs[] = $comme;
		}

		return array( implode( ' AND ', $clauses ), $valeurs );
	}

	/**
	 * @param array{filter?:string,search?:string,per_page?:int,page?:int} $args
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( int $newsletter_id, array $args = array() ): array {
		if ( $newsletter_id <= 0 ) {
			return array();
		}

		global $wpdb;
		$q      = Queue::table();
		$s      = Subscribers::table();
		$events = Events::table();

		list( $where, $valeurs ) = self::where( $newsletter_id, $args );

		$par_page = max( 1, min( 500, (int) ( $args['per_page'] ?? self::PER_PAGE ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $par_page;

		// Tri sur q.id et non sur sent_at : les lignes en attente n'ont pas de
		// date, et un ORDER BY sur une colonne NULL rendrait la pagination
		// instable (une même ligne pourrait apparaître sur deux pages).
		$sql = "SELECT q.id, q.subscriber_id, q.status, q.attempts, q.last_error, q.sent_at, q.delivery, q.delivery_message,
				s.email, s.first_name, s.last_name, s.status AS subscriber_status,
				( SELECT COUNT(*) FROM `$events` eo WHERE eo.newsletter_id = q.newsletter_id AND eo.subscriber_id = q.subscriber_id AND eo.type = '" . Events::TYPE_OPEN . "' ) AS opens,
				( SELECT COUNT(*) FROM `$events` ec WHERE ec.newsletter_id = q.newsletter_id AND ec.subscriber_id = q.subscriber_id AND ec.type = '" . Events::TYPE_CLICK . "' ) AS clicks,
				( SELECT COUNT(*) FROM `$events` eu WHERE eu.newsletter_id = q.newsletter_id AND eu.subscriber_id = q.subscriber_id AND eu.type = '" . Events::TYPE_UNSUBSCRIBE . "' ) AS unsubs
			FROM `$q` q
			JOIN `$s` s ON s.id = q.subscriber_id
			WHERE $where
			ORDER BY q.id ASC
			LIMIT %d OFFSET %d";

		$valeurs[] = $par_page;
		$valeurs[] = $offset;

		$lignes = (array) $wpdb->get_results( $wpdb->prepare( $sql, $valeurs ), ARRAY_A );

		return array_map(
			static function ( $r ) {
				$prenom = (string) $r['first_name'];
				$nom    = (string) $r['last_name'];
				return array(
					'id'               => (int) $r['id'],
					'subscriber_id'    => (int) $r['subscriber_id'],
					'email'            => (string) $r['email'],
					'name'             => trim( $prenom . ' ' . $nom ),
					'status'           => (string) $r['status'],
					'subscriberStatus' => (string) $r['subscriber_status'],
					'attempts'         => (int) $r['attempts'],
					'error'            => (string) $r['last_error'],
					'sent_at'          => (string) $r['sent_at'],
					'delivery'         => (string) ( $r['delivery'] ?? '' ),
					'deliveryMessage'  => (string) ( $r['delivery_message'] ?? '' ),
					'opens'            => (int) $r['opens'],
					'clicks'           => (int) $r['clicks'],
					'opened'           => (int) $r['opens'] > 0,
					'clicked'          => (int) $r['clicks'] > 0,
					'unsubscribed'     => (int) $r['unsubs'] > 0,
				);
			},
			$lignes
		);
	}

	public static function count( int $newsletter_id, array $args = array() ): int {
		if ( $newsletter_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		$q = Queue::table();
		$s = Subscribers::table();

		list( $where, $valeurs ) = self::where( $newsletter_id, $args );

		$sql = "SELECT COUNT(*) FROM `$q` q JOIN `$s` s ON s.id = q.subscriber_id WHERE $where";

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $valeurs ) );
	}

	/** Compteur par onglet, pour afficher les effectifs sans recharger la page. */
	public static function counts_by_filter( int $newsletter_id ): array {
		$compteurs = array();
		foreach ( self::FILTERS as $filtre ) {
			$compteurs[ $filtre ] = self::count( $newsletter_id, array( 'filter' => $filtre ) );
		}
		return $compteurs;
	}

	public static function csv_header(): array {
		return array( 'email', 'nom', 'statut_envoi', 'envoye_le', 'tentatives', 'erreur', 'ouvertures', 'clics', 'desinscrit', 'statut_abonne' );
	}

	/**
	 * Export de la vue filtrée.
	 *
	 * Mêmes conventions que l'export des abonné·es : BOM UTF-8 pour qu'Excel
	 * sous Windows n'affiche pas « PrÃ©nom », et point-virgule comme séparateur.
	 * Parcours par pages pour ne pas charger 1 850 lignes d'un coup sur un
	 * mutualisé.
	 */
	public static function csv( int $newsletter_id, array $args = array() ): string {
		$flux = fopen( 'php://temp', 'r+' );

		fwrite( $flux, "\xEF\xBB\xBF" );
		fputcsv( $flux, self::csv_header(), ';' );

		$page     = 1;
		$par_page = 500;
		do {
			$lignes = self::query(
				$newsletter_id,
				array_merge( $args, array( 'per_page' => $par_page, 'page' => $page ) )
			);
			foreach ( $lignes as $ligne ) {
				fputcsv(
					$flux,
					array(
						$ligne['email'],
						$ligne['name'],
						$ligne['status'],
						$ligne['sent_at'],
						$ligne['attempts'],
						$ligne['error'],
						$ligne['opens'],
						$ligne['clicks'],
						$ligne['unsubscribed'] ? 'oui' : 'non',
						$ligne['subscriberStatus'],
					),
					';'
				);
			}
			++$page;
		} while ( count( $lignes ) === $par_page );

		rewind( $flux );
		$contenu = (string) stream_get_contents( $flux );
		fclose( $flux );
		return $contenu;
	}
}
