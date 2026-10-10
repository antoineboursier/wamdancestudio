<?php
namespace WamNewsletter\Editor;

use WamNewsletter\Install;
use WamNewsletter\Integrations\ContentMap;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Render\BlockRenderer;
use WamNewsletter\Render\Blocks\Posts;
use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Sending\Sender;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * API REST du panneau latéral de l'éditeur.
 *
 * Chaque route vérifie la capacité `wam_nl_manage` (§12) : les routes REST sont
 * un chemin d'accès à part entière, et c'est précisément par là que le CPT
 * restait ouvert aux professeur·es avant la correction du lot 1.
 */
class RestApi {

	const NAMESPACE_V1 = 'wam-nl/v1';

	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function permission(): bool {
		return current_user_can( Install::CAPABILITY );
	}

	public static function register_routes(): void {
		$lecture = array(
			'methods'             => 'GET',
			'permission_callback' => array( self::class, 'permission' ),
		);
		$ecriture = array(
			'methods'             => 'POST',
			'permission_callback' => array( self::class, 'permission' ),
		);

		register_rest_route( self::NAMESPACE_V1, '/lists', array_merge( $lecture, array( 'callback' => array( self::class, 'lists' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/recipients', array_merge( $ecriture, array( 'callback' => array( self::class, 'recipients' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/preview', array_merge( $ecriture, array( 'callback' => array( self::class, 'preview' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/send-test', array_merge( $ecriture, array( 'callback' => array( self::class, 'send_test' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/send', array_merge( $ecriture, array( 'callback' => array( self::class, 'send' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/resume', array_merge( $ecriture, array( 'callback' => array( self::class, 'resume' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/cancel', array_merge( $ecriture, array( 'callback' => array( self::class, 'cancel' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/progress', array_merge( $lecture, array( 'callback' => array( self::class, 'progress' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/checklist', array_merge( $ecriture, array( 'callback' => array( self::class, 'checklist_route' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/content-search', array_merge( $lecture, array( 'callback' => array( self::class, 'content_search' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/terms', array_merge( $lecture, array( 'callback' => array( self::class, 'terms' ) ) ) );
		register_rest_route( self::NAMESPACE_V1, '/posts-resolve', array_merge( $ecriture, array( 'callback' => array( self::class, 'posts_resolve' ) ) ) );
	}

	// -----------------------------------------------------------------
	// Listes et destinataires
	// -----------------------------------------------------------------

	public static function lists(): WP_REST_Response {
		$sortie = array();
		foreach ( Lists::all() as $liste ) {
			$sortie[] = array(
				'id'    => (int) $liste['id'],
				'name'  => (string) $liste['name'],
				'count' => (int) $liste['subscriber_count'],
			);
		}
		return new WP_REST_Response( $sortie );
	}

	public static function recipients( WP_REST_Request $requete ): WP_REST_Response {
		$ids = array_map( 'intval', (array) $requete->get_param( 'listIds' ) );
		return new WP_REST_Response( array( 'count' => Subscribers::count_recipients( $ids ) ) );
	}

	// -----------------------------------------------------------------
	// Aperçu et test
	// -----------------------------------------------------------------

	/**
	 * Aperçu du HTML.
	 *
	 * L'éditeur envoie le contenu COURANT, y compris non enregistré : prévisualiser
	 * uniquement la version en base obligerait à sauvegarder avant chaque coup
	 * d'œil, ce qui est exactement le genre de friction à éviter.
	 */
	public static function preview( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		$contenu = $requete->get_param( 'content' );

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}

		if ( is_string( $contenu ) && '' !== trim( $contenu ) ) {
			$html = EmailRenderer::document(
				BlockRenderer::render_list( parse_blocks( $contenu ) ),
				array(
					'title'     => (string) $requete->get_param( 'subject' ),
					'preheader' => (string) $requete->get_param( 'preheader' ),
				)
			);
			$html = \WamNewsletter\Render\Placeholders::replace( $html, \WamNewsletter\Render\Placeholders::preview_values() );
		} else {
			$html = EmailRenderer::render( $post_id, array( 'preview' => true ) );
		}

		// Les variables de personnalisation sont montrées telles quelles dans
		// l'aperçu, pour qu'on voie où elles sont, mais la politesse est nettoyée.
		return new WP_REST_Response(
			array(
				'html'  => $html,
				'text'  => EmailRenderer::plain_text( $html ),
				'empty' => '' === trim( wp_strip_all_tags( $html ) ),
			)
		);
	}

	public static function send_test( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}

		$brut          = (string) $requete->get_param( 'recipients' );
		$destinataires = array_filter( array_map( 'trim', explode( ',', $brut ) ) );

		$resultat = Sender::send_test( $post_id, $destinataires );
		return new WP_REST_Response( $resultat, $resultat['ok'] ? 200 : 400 );
	}

	// -----------------------------------------------------------------
	// Envoi
	// -----------------------------------------------------------------

	public static function send( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}

		$quand = $requete->get_param( 'scheduleAt' );
		$quand = is_string( $quand ) && '' !== trim( $quand ) ? trim( $quand ) : null;

		$resultat = Scheduler::start( $post_id, $quand );
		if ( is_wp_error( $resultat ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $resultat->get_error_message(),
				),
				400
			);
		}

		$resultat['progress'] = Scheduler::progress( $post_id );
		return new WP_REST_Response( $resultat );
	}

	public static function resume( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}

		$resultat = Scheduler::resume( $post_id );
		if ( is_wp_error( $resultat ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $resultat->get_error_message(),
				),
				400
			);
		}
		$resultat['progress'] = Scheduler::progress( $post_id );
		return new WP_REST_Response( $resultat );
	}

	public static function cancel( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}
		Scheduler::cancel( $post_id );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'message'  => __( 'Envoi arrêté.', 'wam-newsletter' ),
				'progress' => Scheduler::progress( $post_id ),
			)
		);
	}

	public static function progress( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}
		return new WP_REST_Response( Scheduler::progress( $post_id ) );
	}

	// -----------------------------------------------------------------
	// Checklist « prêt à envoyer »
	// -----------------------------------------------------------------

	public static function checklist_route( WP_REST_Request $requete ) {
		$post_id = (int) $requete->get_param( 'postId' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'wam_nl_droits', __( 'Accès refusé.', 'wam-newsletter' ), array( 'status' => 403 ) );
		}

		$donnees = self::checklist(
			$post_id,
			array(
				'subject'   => (string) $requete->get_param( 'subject' ),
				'preheader' => (string) $requete->get_param( 'preheader' ),
				'listIds'   => (array) $requete->get_param( 'listIds' ),
				'content'   => (string) $requete->get_param( 'content' ),
			)
		);
		// Durée et fin estimées, pour le départ choisi (maintenant ou programmé).
		$quand               = $requete->get_param( 'scheduleAt' );
		$donnees['estimate'] = Scheduler::estimate(
			(int) $donnees['recipients'],
			Scheduler::parse_local( is_string( $quand ) ? $quand : null )
		);
		return new WP_REST_Response( $donnees );
	}

	/**
	 * Ce qu'il reste à faire avant d'envoyer, en français simple.
	 *
	 * C'est la pièce pensée pour des personnes qui débutent sous WordPress :
	 * plutôt qu'un bouton qui refuse l'envoi avec un message d'erreur sec, la
	 * liste dit en permanence ce qui va et ce qui manque, avec la marche à
	 * suivre. Les règles sont ici, en PHP, et pas dupliquées dans le JavaScript :
	 * l'envoi applique exactement les mêmes.
	 *
	 * @param int   $post_id
	 * @param array $brouillon Valeurs courantes de l'éditeur (non enregistrées).
	 */
	public static function checklist( int $post_id, array $brouillon = array() ): array {
		$sujet = array_key_exists( 'subject', $brouillon ) && '' !== trim( (string) $brouillon['subject'] )
			? trim( (string) $brouillon['subject'] )
			: trim( (string) get_post_meta( $post_id, '_wam_nl_subject', true ) );

		$listes = array_key_exists( 'listIds', $brouillon ) && ! empty( $brouillon['listIds'] )
			? array_map( 'intval', (array) $brouillon['listIds'] )
			: Scheduler::list_ids( $post_id );

		$contenu = array_key_exists( 'content', $brouillon ) && '' !== trim( (string) $brouillon['content'] )
			? (string) $brouillon['content']
			: (string) get_post_field( 'post_content', $post_id );

		$blocs       = parse_blocks( $contenu );
		$noms        = array();
		$a_du_texte  = false;

		$aplatir = static function ( array $liste ) use ( &$aplatir, &$noms, &$a_du_texte ) {
			foreach ( $liste as $bloc ) {
				$nom = (string) ( $bloc['blockName'] ?? '' );
				if ( '' !== $nom ) {
					$noms[] = $nom;
				}
				if ( in_array( $nom, array( 'core/paragraph', 'core/heading', 'core/list' ), true )
					&& '' !== trim( wp_strip_all_tags( (string) ( $bloc['innerHTML'] ?? '' ) ) ) ) {
					$a_du_texte = true;
				}
				if ( ! empty( $bloc['innerBlocks'] ) ) {
					$aplatir( (array) $bloc['innerBlocks'] );
				}
			}
		};
		$aplatir( $blocs );

		$destinataires = Subscribers::count_recipients( $listes );

		$items = array();

		$items[] = array(
			'key'   => 'subject',
			'label' => __( 'Objet de l’e-mail', 'wam-newsletter' ),
			'ok'    => '' !== $sujet,
			'hint'  => '' !== $sujet
				? $sujet
				: __( 'À saisir juste au-dessus : c’est la ligne que les gens voient dans leur boîte.', 'wam-newsletter' ),
		);

		$items[] = array(
			'key'   => 'lists',
			'label' => __( 'Destinataires', 'wam-newsletter' ),
			'ok'    => $destinataires > 0,
			'hint'  => $destinataires > 0
				? sprintf(
					/* translators: %s nombre de personnes */
					_n( '%s personne recevra cet e-mail.', '%s personnes recevront cet e-mail.', $destinataires, 'wam-newsletter' ),
					number_format_i18n( $destinataires )
				)
				: __( 'Cochez au moins une liste ci-dessous.', 'wam-newsletter' ),
		);

		$items[] = array(
			'key'   => 'content',
			'label' => __( 'Un contenu à envoyer', 'wam-newsletter' ),
			'ok'    => $a_du_texte || in_array( 'wam-nl/posts', $noms, true ),
			'hint'  => __( 'Écrivez un texte d’introduction, ou gardez le bloc « Contenus WAM ».', 'wam-newsletter' ),
		);

		$items[] = array(
			'key'   => 'footer',
			'label' => __( 'Lien de désinscription', 'wam-newsletter' ),
			'ok'    => in_array( 'wam-nl/footer', $noms, true ),
			'hint'  => in_array( 'wam-nl/footer', $noms, true )
				? __( 'Présent dans le pied de page. Obligatoire par la loi.', 'wam-newsletter' )
				: __( 'Le bloc « Pied de page WAM » manque : il porte le lien de désinscription, obligatoire.', 'wam-newsletter' ),
		);

		$preheader = array_key_exists( 'preheader', $brouillon )
			? trim( (string) $brouillon['preheader'] )
			: trim( (string) get_post_meta( $post_id, '_wam_nl_preheader', true ) );

		// Conseil et non blocage : un preheader vide n'empêche pas l'envoi.
		$items[] = array(
			'key'      => 'preheader',
			'label'    => __( 'Texte d’aperçu', 'wam-newsletter' ),
			'ok'       => '' !== $preheader,
			'optional' => true,
			'hint'     => '' !== $preheader
				? $preheader
				: __( 'Conseillé : la phrase affichée après l’objet dans la boîte de réception.', 'wam-newsletter' ),
		);

		$bloquants = array_filter(
			$items,
			static function ( $item ) {
				return empty( $item['optional'] ) && empty( $item['ok'] );
			}
		);

		return array(
			'items'      => $items,
			'ready'      => 0 === count( $bloquants ),
			'recipients' => $destinataires,
			'status'     => Scheduler::status( $post_id ),
			'progress'   => Scheduler::progress( $post_id ),
		);
	}

	// -----------------------------------------------------------------
	// Aide à la sélection de contenus (bloc Contenus, mode manuel)
	// -----------------------------------------------------------------

	public static function content_search( WP_REST_Request $requete ): WP_REST_Response {
		$post_type = sanitize_key( (string) $requete->get_param( 'postType' ) );
		$recherche = sanitize_text_field( (string) $requete->get_param( 'search' ) );

		if ( ! in_array( $post_type, ContentMap::POST_TYPES, true ) || ! post_type_exists( $post_type ) ) {
			return new WP_REST_Response( array() );
		}

		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => 20,
				's'                => $recherche,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		$sortie = array();
		foreach ( $posts as $post ) {
			$sortie[] = array(
				'id'       => (int) $post->ID,
				'title'    => (string) get_the_title( $post ),
				'subtitle' => \WamNewsletter\Render\Blocks\Posts::subtitle( $post_type, (int) $post->ID ),
				'date'     => \WamNewsletter\Render\Blocks\Posts::formatted_date( $post_type, (int) $post->ID ),
			);
		}
		return new WP_REST_Response( $sortie );
	}

	public static function terms( WP_REST_Request $requete ): WP_REST_Response {
		$taxonomie = sanitize_key( (string) $requete->get_param( 'taxonomy' ) );
		if ( '' === $taxonomie || ! taxonomy_exists( $taxonomie ) ) {
			return new WP_REST_Response( array() );
		}

		$termes = get_terms(
			array(
				'taxonomy'   => $taxonomie,
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $termes ) ) {
			return new WP_REST_Response( array() );
		}

		$sortie = array();
		foreach ( $termes as $terme ) {
			$sortie[] = array(
				'id'   => (int) $terme->term_id,
				'name' => (string) $terme->name,
			);
		}
		return new WP_REST_Response( $sortie );
	}

	/**
	 * Résout les contenus courants du bloc Contenus en données structurées,
	 * pour « Convertir en blocs modifiables ».
	 *
	 * Ne touche à rien : c'est une lecture, exactement comme `preview`. La
	 * transformation en vrais blocs se fait côté JavaScript, à partir de ces
	 * données.
	 */
	public static function posts_resolve( WP_REST_Request $requete ): WP_REST_Response {
		$attrs = array(
			'postType' => sanitize_key( (string) $requete->get_param( 'postType' ) ),
			'mode'     => sanitize_key( (string) $requete->get_param( 'mode' ) ),
			'count'    => (int) $requete->get_param( 'count' ),
			'order'    => sanitize_key( (string) $requete->get_param( 'order' ) ),
			'taxonomy' => sanitize_key( (string) $requete->get_param( 'taxonomy' ) ),
			'term'     => (int) $requete->get_param( 'term' ),
			'postIds'  => array_map( 'intval', (array) $requete->get_param( 'postIds' ) ),
		);

		return new WP_REST_Response( Posts::resolve_items( array_filter( $attrs, static fn( $v ) => '' !== $v ) ) );
	}
}
