<?php
namespace WamNewsletter\Stats;

use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Install;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Chiffres clés de la newsletter, en lecture seule, pour les outils externes
 * (WAMBO, tableurs…).
 *
 *   GET /wp-json/wam-nl/v1/kpis                    vue d'ensemble + dernières newsletters
 *   GET /wp-json/wam-nl/v1/kpis/newsletters/{id}   détail d'une newsletter
 *
 * Paramètres de la vue d'ensemble :
 *   - limit  : nombre de newsletters (20 par défaut, 100 au plus) ;
 *   - status : `sent` (défaut), ou `all` pour inclure brouillons et envois en cours.
 *
 * Aucune donnée personnelle ne sort d'ici : seulement des nombres, des objets
 * d'e-mail et des URL de liens cliqués. C'est voulu : le proxy WAMBO relaie
 * `wam-nl/*` sans filtrage par rôle, cette route doit donc rester anonyme par
 * construction. La liste nominative des destinataires reste dans l'admin.
 *
 * Même capacité que le reste de l'API (`wam_nl_manage`) : le compte de service
 * du proxy est administrateur, il l'a déjà.
 *
 * Les calculs reprennent ceux de l'écran Statistiques ({@see Screen::render()}) :
 * taux rapportés aux e-mails effectivement envoyés, ouvertures et clics comptés
 * en personnes distinctes. Les ouvertures restent indicatives (Apple Mail
 * précharge les images, d'autres clients les bloquent).
 */
class Api {

	const LIMIT_DEFAULT = 20;
	const LIMIT_MAX     = 100;

	/** Fenêtre des indicateurs d'évolution de la base (nouveaux, départs). */
	const RECENT_DAYS = 30;

	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function permission(): bool {
		return current_user_can( Install::CAPABILITY );
	}

	public static function register_routes(): void {
		register_rest_route(
			'wam-nl/v1',
			'/kpis',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( self::class, 'permission' ),
				'callback'            => array( self::class, 'overview' ),
				'args'                => array(
					'limit'  => array(
						'type'              => 'integer',
						'required'          => false,
						'minimum'           => 1,
					),
					'status' => array(
						'type'     => 'string',
						'required' => false,
						'enum'     => array( 'sent', 'all' ),
					),
				),
			)
		);

		register_rest_route(
			'wam-nl/v1',
			'/kpis/newsletters/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( self::class, 'permission' ),
				'callback'            => array( self::class, 'detail' ),
			)
		);
	}

	// -----------------------------------------------------------------
	// Routes
	// -----------------------------------------------------------------

	public static function overview( WP_REST_Request $requete ): WP_REST_Response {
		$limite = (int) $requete->get_param( 'limit' );
		$limite = $limite > 0 ? min( self::LIMIT_MAX, $limite ) : self::LIMIT_DEFAULT;
		$statut = 'all' === $requete->get_param( 'status' ) ? 'all' : 'sent';

		$newsletters = array_map( array( self::class, 'newsletter_kpis' ), self::newsletter_ids( $limite, $statut ) );

		return new WP_REST_Response(
			array(
				'generated_at' => self::iso( current_time( 'mysql' ) ),
				'tracking'     => self::tracking(),
				'subscribers'  => self::subscribers(),
				'lists'        => self::lists(),
				'totals'       => self::totals(),
				'newsletters'  => $newsletters,
			)
		);
	}

	public static function detail( WP_REST_Request $requete ) {
		$id   = (int) $requete->get_param( 'id' );
		$post = $id ? get_post( $id ) : null;

		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'wam_nl_introuvable', __( 'Newsletter introuvable.', 'wam-newsletter' ), array( 'status' => 404 ) );
		}

		$kpis              = self::newsletter_kpis( $id );
		$kpis['top_links'] = Events::top_links( $id, 20 );
		$kpis['timeline']  = Events::timeline( $id );

		return new WP_REST_Response( $kpis );
	}

	// -----------------------------------------------------------------
	// Blocs de la réponse
	// -----------------------------------------------------------------

	/** Le suivi peut être coupé dans les réglages : un taux à 0 % mentirait alors. */
	private static function tracking(): array {
		return array(
			'opens'  => (bool) Settings::get( 'track_opens' ),
			'clicks' => (bool) Settings::get( 'track_clicks' ),
		);
	}

	private static function subscribers(): array {
		global $wpdb;
		$t      = Subscribers::table();
		$depuis = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::RECENT_DAYS * DAY_IN_SECONDS );

		$par_statut = Subscribers::counts_by_status();

		// Les adresses mises à la corbeille ne sont plus des contacts : elles
		// sortent du total pour ne pas gonfler la base affichée.
		$total = array_sum( $par_statut ) - (int) ( $par_statut[ Subscribers::STATUS_TRASHED ] ?? 0 );

		$sources = array();
		$lignes  = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT consent_source AS source, COUNT(*) AS c FROM `$t` WHERE status = %s GROUP BY consent_source ORDER BY c DESC",
				Subscribers::STATUS_SUBSCRIBED
			),
			ARRAY_A
		);
		foreach ( $lignes as $l ) {
			$sources[ '' !== (string) $l['source'] ? (string) $l['source'] : 'inconnue' ] = (int) $l['c'];
		}

		return array(
			'total'     => $total,
			'by_status' => $par_statut,
			'by_source' => $sources,
			'recent'    => array(
				'days'         => self::RECENT_DAYS,
				'new'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE created_at >= %s AND status <> %s", $depuis, Subscribers::STATUS_TRASHED ) ),
				'unsubscribed' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE unsubscribed_at >= %s", $depuis ) ),
			),
		);
	}

	private static function lists(): array {
		$sortie = array();
		foreach ( Lists::all() as $liste ) {
			$sortie[] = array(
				'id'          => (int) $liste['id'],
				'name'        => (string) $liste['name'],
				'description' => (string) ( $liste['description'] ?? '' ),
				'subscribers' => (int) $liste['subscriber_count'],
			);
		}
		return $sortie;
	}

	/**
	 * Cumul sur toutes les newsletters envoyées, quelle que soit la limite.
	 *
	 * Les taux moyens sont pondérés (somme des ouvertures / somme des envois) :
	 * une moyenne des taux donnerait autant de poids à un envoi de 20 personnes
	 * qu'à un envoi de 400.
	 */
	private static function totals(): array {
		global $wpdb;
		$ids = self::newsletter_ids( 0, 'sent' );

		$base = array(
			'newsletters'  => count( $ids ),
			'sent'         => 0,
			'opens'        => 0,
			'clicks'       => 0,
			'unsubscribes' => 0,
		);
		if ( ! $ids ) {
			return $base + array(
				'open_rate'        => null,
				'click_rate'       => null,
				'unsubscribe_rate' => null,
			);
		}

		$liste = implode( ',', array_map( 'intval', $ids ) );
		$q     = Queue::table();
		$e     = Events::table();

		$base['sent'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `$q` WHERE newsletter_id IN ($liste) AND status = %s", Queue::STATUS_SENT ) // phpcs:ignore WordPress.DB.PreparedSQL -- liste d'entiers castés.
		);

		// Personnes distinctes PAR newsletter, puis somme : une même personne qui
		// ouvre trois newsletters compte trois fois, comme sur chaque écran.
		$par_type = (array) $wpdb->get_results(
			"SELECT type, SUM(n) AS n FROM (
				SELECT newsletter_id, type, COUNT(DISTINCT subscriber_id) AS n
				FROM `$e` WHERE newsletter_id IN ($liste) AND type IN ('open','click')
				GROUP BY newsletter_id, type
			) x GROUP BY type", // phpcs:ignore WordPress.DB.PreparedSQL -- aucune donnée externe.
			ARRAY_A
		);
		foreach ( $par_type as $r ) {
			if ( Events::TYPE_OPEN === $r['type'] ) {
				$base['opens'] = (int) $r['n'];
			} elseif ( Events::TYPE_CLICK === $r['type'] ) {
				$base['clicks'] = (int) $r['n'];
			}
		}

		$base['unsubscribes'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `$e` WHERE newsletter_id IN ($liste) AND type = %s", Events::TYPE_UNSUBSCRIBE ) // phpcs:ignore WordPress.DB.PreparedSQL -- liste d'entiers castés.
		);

		$suivi = self::tracking();
		return $base + array(
			'open_rate'        => $suivi['opens'] ? self::rate( $base['opens'], $base['sent'] ) : null,
			'click_rate'       => $suivi['clicks'] ? self::rate( $base['clicks'], $base['sent'] ) : null,
			'unsubscribe_rate' => self::rate( $base['unsubscribes'], $base['sent'] ),
		);
	}

	/** Chiffres d'une newsletter : ceux des tuiles de l'écran Statistiques. */
	public static function newsletter_kpis( int $id ): array {
		$counts        = Queue::counts( $id );
		$envoyes       = (int) $counts['sent'];
		$ouvertures    = Events::count_unique( $id, Events::TYPE_OPEN );
		$clics_uniques = Events::count_unique( $id, Events::TYPE_CLICK );
		$clics_total   = Events::count( $id, Events::TYPE_CLICK );
		$desinscrits   = Events::count( $id, Events::TYPE_UNSUBSCRIBE );
		$suivi         = self::tracking();

		$noms_listes = array();
		foreach ( Lists::all() as $liste ) {
			$noms_listes[ (int) $liste['id'] ] = (string) $liste['name'];
		}
		$listes = array();
		foreach ( Scheduler::list_ids( $id ) as $list_id ) {
			$listes[] = array(
				'id'   => $list_id,
				'name' => $noms_listes[ $list_id ] ?? '',
			);
		}

		$envoyee = (string) get_post_meta( $id, Scheduler::META_SENT_AT, true );

		return array(
			'id'         => $id,
			'title'      => (string) get_the_title( $id ),
			'subject'    => (string) get_post_meta( $id, '_wam_nl_subject', true ),
			'preheader'  => (string) get_post_meta( $id, '_wam_nl_preheader', true ),
			'status'     => Scheduler::status( $id ),
			'sent_at'    => '' !== $envoyee ? self::iso( $envoyee ) : null,
			'lists'      => $listes,
			'recipients' => array(
				'total'   => (int) $counts['total'],
				'sent'    => $envoyes,
				'failed'  => (int) $counts['failed'],
				'pending' => (int) $counts['pending'],
			),
			'opens'      => array(
				'unique' => $suivi['opens'] ? $ouvertures : null,
				'rate'   => $suivi['opens'] ? self::rate( $ouvertures, $envoyes ) : null,
			),
			'clicks'     => array(
				'unique'          => $suivi['clicks'] ? $clics_uniques : null,
				'total'           => $suivi['clicks'] ? $clics_total : null,
				'rate'            => $suivi['clicks'] ? self::rate( $clics_uniques, $envoyes ) : null,
				// Part des personnes qui ont ouvert et ont aussi cliqué.
				'click_to_open'   => $suivi['clicks'] && $suivi['opens'] ? self::rate( $clics_uniques, $ouvertures ) : null,
			),
			'unsubscribes' => array(
				'count' => $desinscrits,
				'rate'  => self::rate( $desinscrits, $envoyes ),
			),
			// Liens d'administration : ils demandent une session WordPress.
			'links'      => array(
				'stats' => Screen::url( $id ),
				'edit'  => (string) get_edit_post_link( $id, 'raw' ),
			),
		);
	}

	// -----------------------------------------------------------------
	// Outils
	// -----------------------------------------------------------------

	/**
	 * @param int    $limite 0 = toutes.
	 * @param string $statut `sent` ou `all`.
	 * @return int[]
	 */
	private static function newsletter_ids( int $limite, string $statut ): array {
		$args = array(
			'post_type'        => NewsletterPostType::POST_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'numberposts'      => $limite > 0 ? $limite : -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'orderby'          => 'date',
			'order'            => 'DESC',
		);

		if ( 'sent' === $statut ) {
			// Les plus récemment envoyées d'abord : la date du post est celle de
			// sa création, pas de son envoi.
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery -- peu de newsletters.
				array(
					'key'   => Scheduler::META_STATUS,
					'value' => Scheduler::STATUS_SENT,
				),
			);
			$args['meta_key'] = Scheduler::META_SENT_AT; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = 'meta_value';
		}

		return array_map( 'intval', get_posts( $args ) );
	}

	/** Ratio arrondi à 4 décimales (0.4321 = 43,21 %), null si rien n'a été envoyé. */
	private static function rate( int $part, int $base ): ?float {
		return $base > 0 ? round( $part / $base, 4 ) : null;
	}

	/** Dates stockées en heure locale du site : on les sort avec leur fuseau. */
	private static function iso( string $mysql ): string {
		try {
			return ( new \DateTimeImmutable( $mysql, wp_timezone() ) )->format( DATE_ATOM );
		} catch ( \Exception $e ) {
			return $mysql;
		}
	}
}
