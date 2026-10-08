<?php
namespace WamNewsletter\Blocks;

use WamNewsletter\Render\Blocks\Custom;
use WamNewsletter\Render\Blocks\Posts;
use WamNewsletter\Render\BlockRenderer;
use WamNewsletter\Render\Placeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Enregistrement des blocs wam-nl/*.
 *
 * Tous sont des blocs dynamiques (`save` rend null côté JS) : le contenu de la
 * newsletter ne stocke que les attributs, le HTML est produit par les renderers.
 * Conséquence utile : une correction du rendu e-mail s'applique aux newsletters
 * déjà rédigées, sans réédition.
 *
 * Le `render_callback` sert l'aperçu de l'éditeur. Il résout les marqueurs en
 * valeurs inertes : un aperçu ne doit pas offrir un lien de désinscription
 * fonctionnel ni déclencher un pixel d'ouverture.
 */
class Blocks {

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$registre = \WP_Block_Type_Registry::get_instance();

		foreach ( self::definitions() as $nom => $def ) {
			// Un bloc déjà enregistré ne doit pas l'être deux fois : WordPress
			// émet sinon un avis « déjà enregistré » à chaque passage, et le
			// debug.log se remplit. Arrive dès qu'un script rejoue `init`.
			if ( $registre->is_registered( $nom ) ) {
				continue;
			}

			register_block_type(
				$nom,
				array(
					'api_version'     => 2,
					'title'           => $def['title'],
					'description'     => $def['description'] ?? '',
					'category'        => 'wam-newsletter',
					'icon'            => $def['icon'] ?? 'email-alt',
					'attributes'      => $def['attributes'],
					'render_callback' => $def['render'],
					'supports'        => array(
						'html'      => false,
						'reusable'  => false,
						'className' => false,
						'lock'      => true,
					),
				)
			);
		}

		add_filter( 'block_categories_all', array( self::class, 'add_category' ), 10, 2 );
	}

	/**
	 * Catégorie dédiée dans l'inserteur.
	 *
	 * Sans elle, les blocs WAM se noieraient parmi les blocs du cœur. Les
	 * regrouper est l'une des choses qui fait la différence entre « des blocs en
	 * vrac » et un éditeur d'e-mail.
	 */
	public static function add_category( $categories, $context = null ) {
		foreach ( (array) $categories as $categorie ) {
			if ( 'wam-newsletter' === ( $categorie['slug'] ?? '' ) ) {
				return $categories;
			}
		}

		return array_merge(
			array(
				array(
					'slug'  => 'wam-newsletter',
					'title' => __( 'Newsletter WAM', 'wam-newsletter' ),
					'icon'  => null,
				),
			),
			(array) $categories
		);
	}

	/**
	 * Un aperçu de bloc, marqueurs rendus inertes.
	 *
	 * @param callable $renderer
	 */
	private static function preview( callable $renderer, array $attrs ): string {
		return Placeholders::replace( (string) $renderer( $attrs ), Placeholders::preview_values() );
	}

	/** @return array<string,array<string,mixed>> */
	public static function definitions(): array {
		return array(
			'wam-nl/header'    => array(
				'title'       => __( 'Entête WAM', 'wam-newsletter' ),
				'description' => __( 'Bannière en tête de la newsletter. Verrouillée en position.', 'wam-newsletter' ),
				'icon'        => 'format-image',
				'attributes'  => array(
					'attachmentId' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'url'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'alt'          => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => static function ( $attrs ) {
					return self::preview( array( Custom::class, 'header' ), (array) $attrs );
				},
			),

			'wam-nl/footer'    => array(
				'title'       => __( 'Pied de page WAM', 'wam-newsletter' ),
				'description' => __( 'Lien de désinscription et coordonnées. Obligatoire, verrouillé en position.', 'wam-newsletter' ),
				'icon'        => 'editor-insertmore',
				'attributes'  => array(
					'extra' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => static function ( $attrs ) {
					return self::preview( array( Custom::class, 'footer' ), (array) $attrs );
				},
			),

			'wam-nl/button'    => array(
				'title'       => __( 'Bouton', 'wam-newsletter' ),
				'description' => __( 'Bouton compatible Outlook, rendu en table.', 'wam-newsletter' ),
				'icon'        => 'button',
				'attributes'  => array(
					'text'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'url'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'variant' => array(
						'type'    => 'string',
						'default' => 'plein',
					),
					'color'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'align'   => array(
						'type'    => 'string',
						'default' => 'left',
					),
				),
				'render'      => static function ( $attrs ) {
					return self::preview( array( Custom::class, 'button' ), (array) $attrs );
				},
			),

			'wam-nl/separator' => array(
				'title'       => __( 'Séparateur', 'wam-newsletter' ),
				'description' => __( 'Filet pointillé turquoise.', 'wam-newsletter' ),
				'icon'        => 'minus',
				'attributes'  => array(
					'color' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => static function ( $attrs ) {
					return self::preview( array( Custom::class, 'separator' ), (array) $attrs );
				},
			),

			'wam-nl/spacer'    => array(
				'title'       => __( 'Espacement', 'wam-newsletter' ),
				'description' => __( 'Hauteur vide, fiable dans Outlook.', 'wam-newsletter' ),
				'icon'        => 'image-flip-vertical',
				'attributes'  => array(
					'height' => array(
						'type'    => 'number',
						'default' => 24,
					),
				),
				'render'      => static function ( $attrs ) {
					return self::preview( array( Custom::class, 'spacer' ), (array) $attrs );
				},
			),

			'wam-nl/posts'     => array(
				'title'       => __( 'Contenus WAM', 'wam-newsletter' ),
				'description' => __( 'Articles, cours ou stages, en automatique ou choisis à la main.', 'wam-newsletter' ),
				'icon'        => 'list-view',
				'attributes'  => array(
					'postType'      => array(
						'type'    => 'string',
						'default' => 'stages',
					),
					'mode'          => array(
						'type'    => 'string',
						'default' => 'auto',
					),
					'count'         => array(
						'type'    => 'number',
						'default' => 3,
					),
					'order'         => array(
						'type'    => 'string',
						'default' => 'recent',
					),
					'taxonomy'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'term'          => array(
						'type'    => 'number',
						'default' => 0,
					),
					'postIds'       => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'number' ),
					),
					'showImage'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showExcerpt'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showDate'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showButton'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'alternate'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'buttonText'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonVariant' => array(
						'type'    => 'string',
						'default' => 'plein',
					),
					'buttonColor'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => static function ( $attrs ) {
					return self::preview( array( Posts::class, 'render' ), (array) $attrs );
				},
			),
		);
	}

	/** Noms des blocs du plugin. */
	public static function names(): array {
		return array_keys( self::definitions() );
	}

	/** Rend un contenu complet en aperçu. Utilisé par l'API REST. */
	public static function render_content( string $contenu ): string {
		return BlockRenderer::preview( $contenu );
	}
}
