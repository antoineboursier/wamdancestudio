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

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'init', array( self::class, 'register_meta' ) );
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
				'supports'        => array( 'title', 'editor', 'revisions' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
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
