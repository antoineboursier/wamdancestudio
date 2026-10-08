<?php
namespace WamNewsletter\Editor;

use WamNewsletter\Admin\Menu;
use WamNewsletter\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Le CPT porteur d'une newsletter.
 *
 * Non public : une newsletter n'a pas d'URL front. La « vue navigateur » du lot 7
 * passera par un endpoint tokenisé, pas par un permalien.
 */
class NewsletterPostType {

	const POST_TYPE = 'wam_newsletter';

	/**
	 * Métas hors contenu, pilotées depuis le panneau latéral de l'éditeur (lot 4).
	 * Préfixées par « _ » : invisibles dans les champs personnalisés, il faut donc
	 * un auth_callback explicite pour que l'API REST les accepte.
	 */
	const META = array(
		'_wam_nl_subject'      => array(
			'type'   => 'string',
			'single' => true,
		),
		'_wam_nl_preheader'    => array(
			'type'   => 'string',
			'single' => true,
		),
		'_wam_nl_list_ids'     => array(
			'type'   => 'array',
			'single' => true,
		),
		'_wam_nl_scheduled_at' => array(
			'type'   => 'string',
			'single' => true,
		),
		'_wam_nl_status'       => array(
			'type'   => 'string',
			'single' => true,
		),
	);

	/**
	 * Capacités primitives du CPT, toutes ramenées à wam_nl_manage.
	 *
	 * map_meta_cap => true traduit ensuite les capacités « méta » (edit_post,
	 * delete_post, read_post sur un objet précis) vers celles-ci.
	 *
	 * @return array<string,string>
	 */
	public static function capabilities(): array {
		$primitives = array(
			'edit_posts',
			'edit_others_posts',
			'edit_published_posts',
			'edit_private_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_published_posts',
			'delete_private_posts',
			'create_posts',
		);

		$caps = array();
		foreach ( $primitives as $primitive ) {
			$caps[ $primitive ] = Install::CAPABILITY;
		}
		$caps['read'] = 'read';

		return $caps;
	}

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'before_delete_post', array( self::class, 'purge_data' ), 10, 2 );
	}

	/**
	 * Supprime la file, les événements et le journal d'une newsletter effacée.
	 *
	 * Sans ça, ces trois tables gardaient indéfiniment les lignes de newsletters
	 * disparues : elles ne sont rattachées au post que par un identifiant, aucune
	 * clé étrangère ne fait le ménage.
	 *
	 * @param int           $post_id
	 * @param \WP_Post|null $post
	 */
	public static function purge_data( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		$type    = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( self::POST_TYPE !== $type ) {
			return;
		}

		\WamNewsletter\Sending\Queue::clear( $post_id );
		\WamNewsletter\Stats\Events::purge_newsletter( $post_id );
		\WamNewsletter\Sending\Log::purge_newsletter( $post_id );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Newsletters', 'wam-newsletter' ),
					'singular_name' => __( 'Newsletter', 'wam-newsletter' ),
					'add_new_item'  => __( 'Nouvelle newsletter', 'wam-newsletter' ),
					'edit_item'     => __( 'Modifier la newsletter', 'wam-newsletter' ),
					'all_items'     => __( 'Newsletters', 'wam-newsletter' ),
					'menu_name'     => __( 'Newsletters', 'wam-newsletter' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Menu::SLUG,
				'show_in_rest'    => true,
				// custom-fields : sans lui, WordPress retire le champ `meta` de l'API REST
				// du type de contenu, et l'objet de l'e-mail n'est jamais enregistré.
				'supports'        => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				// Toutes les capacités primitives renvoient sur wam_nl_manage.
				// Avec le 'post' par défaut, le CPT retombait sur edit_posts /
				// edit_others_posts — que portent professeur, editor, author et
				// contributor : l'entrée de menu était masquée, mais l'URL
				// edit.php?post_type=wam_newsletter et la route REST restaient
				// ouvertes en création et en modification.
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => self::capabilities(),
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	public static function register_meta(): void {
		foreach ( self::META as $cle => $def ) {
			$rest = true;
			if ( 'array' === $def['type'] ) {
				$rest = array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				);
			}

			register_post_meta(
				self::POST_TYPE,
				$cle,
				array(
					'type'          => $def['type'],
					'single'        => $def['single'],
					'show_in_rest'  => $rest,
					'auth_callback' => static function () {
						return current_user_can( Install::CAPABILITY );
					},
				)
			);
		}
	}
}
