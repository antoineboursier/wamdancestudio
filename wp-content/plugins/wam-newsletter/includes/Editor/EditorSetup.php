<?php
namespace WamNewsletter\Editor;

use WamNewsletter\Install;
use WamNewsletter\Render\Brand;
use WamNewsletter\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Transforme l'éditeur de blocs en éditeur d'e-mail, sur le seul CPT
 * wam_newsletter.
 *
 * Chaque filtre est strictement cantonné à ce type de contenu : les articles,
 * les pages, les cours et les stages du site continuent d'utiliser Gutenberg
 * normalement, avec tous leurs blocs et la palette complète du thème.
 *
 * Ce qui fait la différence avec « un article avec des blocs » :
 *  - le canevas est peint aux couleurs de l'e-mail, à sa largeur réelle ;
 *  - l'inserteur ne propose que des blocs qui savent se rendre en e-mail ;
 *  - une newsletter neuve arrive déjà montée (entête, intro, contenus, pied) ;
 *  - l'entête et le pied de page sont verrouillés en position ;
 *  - les réglages hors contenu (objet, listes, planification) vivent dans un
 *    panneau dédié, pas dans les champs d'un article.
 */
class EditorSetup {

	/**
	 * Blocs autorisés (§7.1).
	 *
	 * core/columns et core/column sont un ajout assumé à la liste du cahier des
	 * charges : une mise en page en colonnes est ce qui distingue le plus un
	 * éditeur d'e-mail d'un éditeur d'article, et la machinerie nécessaire
	 * (tables fantômes MSO pour Outlook) est de toute façon exigée par le §7.6.4
	 * pour le bloc Contenus. L'exposer comme bloc ne coûte donc qu'un renderer.
	 */
	const ALLOWED_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/image',
		'core/columns',
		'core/column',
		'wam-nl/header',
		'wam-nl/footer',
		'wam-nl/button',
		'wam-nl/separator',
		'wam-nl/spacer',
		'wam-nl/posts',
	);

	public static function register_hooks(): void {
		add_filter( 'allowed_block_types_all', array( self::class, 'allowed_blocks' ), 10, 2 );
		add_filter( 'block_editor_settings_all', array( self::class, 'editor_settings' ), 10, 2 );
		add_filter( 'default_content', array( self::class, 'default_content' ), 10, 2 );
		add_filter( 'enter_title_here', array( self::class, 'title_placeholder' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );

		// Les motifs et le catalogue distant n'ont aucun sens ici et ralentissent
		// l'ouverture de l'éditeur.
		add_filter( 'should_load_remote_block_patterns', array( self::class, 'no_remote_patterns' ) );
	}

	/** Vrai si l'écran courant édite une newsletter. */
	public static function is_newsletter_editor( $context = null ): bool {
		if ( $context && isset( $context->post ) && $context->post instanceof \WP_Post ) {
			return NewsletterPostType::POST_TYPE === $context->post->post_type;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->post_type ) ) {
			return NewsletterPostType::POST_TYPE === $screen->post_type;
		}

		return false;
	}

	/**
	 * @param bool|string[] $autorises
	 * @param mixed         $context
	 * @return bool|string[]
	 */
	public static function allowed_blocks( $autorises, $context = null ) {
		if ( ! self::is_newsletter_editor( $context ) ) {
			return $autorises;
		}
		return self::ALLOWED_BLOCKS;
	}

	public static function no_remote_patterns( $charger ) {
		return self::is_newsletter_editor() ? false : $charger;
	}

	/**
	 * Injecte le thème d'e-mail et la peinture du canevas.
	 *
	 * @param array $settings
	 * @param mixed $context
	 * @return array
	 */
	public static function editor_settings( $settings, $context = null ) {
		if ( ! self::is_newsletter_editor( $context ) ) {
			return $settings;
		}

		// 1. Les choix proposés par les panneaux.
		$features = is_array( $settings['__experimentalFeatures'] ?? null ) ? $settings['__experimentalFeatures'] : array();
		$settings['__experimentalFeatures'] = array_replace_recursive( $features, EmailTheme::settings() );

		// Doublons historiques lus par certains contrôles : on les aligne, sinon
		// la palette du thème du site réapparaît dans le sélecteur de couleur.
		$settings['colors']            = Brand::palette();
		$settings['gradients']         = array();
		$settings['fontSizes']         = EmailTheme::FONT_SIZES;
		$settings['disableCustomColors']    = true;
		$settings['disableCustomGradients'] = true;
		$settings['disableCustomFontSizes'] = false;
		$settings['enableCustomUnits']      = array( 'px' );
		$settings['enableCustomSpacing']    = true;

		// 2. La peinture du canevas. On repart de zéro : les styles du thème
		// wamV1 (polices décoratives, fonds clairs) donneraient un aperçu
		// trompeur de ce que recevront les abonné·es.
		$settings['styles'] = array(
			array(
				'css'            => EmailTheme::canvas_css(),
				'__unstableType' => 'theme',
			),
		);

		// 3. Ménage de ce qui ne s'applique pas à un e-mail.
		$settings['supportsLayout'] = true;
		$settings['templateLock']   = false;

		// Motifs : on garde les NÔTRES et on écarte ceux du cœur et du thème.
		// Les motifs du cœur parlent de pages (couvertures, grilles de requête,
		// média plein écran) et proposeraient des blocs qui ne savent pas se
		// rendre dans une boîte de réception. Les nôtres sont des sections
		// d'e-mail déjà réglées, à insérer puis à retoucher.
		$settings['__experimentalBlockPatterns'] = array_values(
			array_filter(
				(array) ( $settings['__experimentalBlockPatterns'] ?? array() ),
				static function ( $motif ) {
					return isset( $motif['name'] ) && 0 === strpos( (string) $motif['name'], 'wam-newsletter/' );
				}
			)
		);

		$settings['__experimentalBlockPatternCategories'] = array_values(
			array_filter(
				(array) ( $settings['__experimentalBlockPatternCategories'] ?? array() ),
				static function ( $categorie ) {
					return \WamNewsletter\Blocks\Patterns::CATEGORY === ( $categorie['name'] ?? '' );
				}
			)
		);

		return $settings;
	}

	/**
	 * Gabarit par défaut d'une newsletter neuve (§7.1).
	 *
	 * L'entête et le pied de page portent `lock: {move, remove}` : ils restent
	 * modifiables (choix de la bannière, par exemple) mais ne peuvent être ni
	 * déplacés ni supprimés. Le pied porte le lien de désinscription, qui est une
	 * obligation légale — il ne doit pas pouvoir disparaître par inadvertance.
	 *
	 * @param string   $contenu
	 * @param \WP_Post $post
	 */
	public static function default_content( $contenu, $post = null ) {
		if ( ! $post instanceof \WP_Post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			return $contenu;
		}
		if ( '' !== trim( (string) $contenu ) ) {
			return $contenu;
		}
		return self::default_blocks();
	}

	/** Markup de blocs du gabarit par défaut. */
	public static function default_blocks(): string {
		$intro = __( 'Bonjour {prenom}, voici les nouvelles du studio…', 'wam-newsletter' );

		$blocs = array(
			'<!-- wp:wam-nl/header {"lock":{"move":true,"remove":true}} /-->',
			'<!-- wp:paragraph -->' . "\n" . '<p>' . esc_html( $intro ) . '</p>' . "\n" . '<!-- /wp:paragraph -->',
			'<!-- wp:wam-nl/posts {"postType":"stages","mode":"auto","count":3,"order":"upcoming"} /-->',
			'<!-- wp:wam-nl/footer {"lock":{"move":true,"remove":true}} /-->',
		);

		return implode( "\n\n", $blocs );
	}

	/**
	 * @param string   $placeholder
	 * @param \WP_Post $post
	 */
	public static function title_placeholder( $placeholder, $post = null ) {
		if ( $post instanceof \WP_Post && NewsletterPostType::POST_TYPE === $post->post_type ) {
			// Le titre est un repère interne : l'objet réel de l'e-mail est une
			// méta, réglée dans le panneau latéral. Le dire ici évite qu'on
			// cherche l'objet dans le titre.
			return __( 'Nom interne de la newsletter (l’objet se règle à droite)', 'wam-newsletter' );
		}
		return $placeholder;
	}

	public static function enqueue(): void {
		if ( ! self::is_newsletter_editor() ) {
			return;
		}

		$blocs = 'blocks/newsletter/index.js';
		if ( file_exists( WAM_NL_DIR . $blocs ) ) {
			wp_enqueue_script(
				'wam-nl-blocks',
				WAM_NL_URL . $blocs,
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n', 'wp-data' ),
				(string) filemtime( WAM_NL_DIR . $blocs ),
				true
			);
		}

		$sidebar = 'blocks/editor/index.js';
		if ( file_exists( WAM_NL_DIR . $sidebar ) ) {
			wp_enqueue_script(
				'wam-nl-editor',
				WAM_NL_URL . $sidebar,
				array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch', 'wp-compose' ),
				(string) filemtime( WAM_NL_DIR . $sidebar ),
				true
			);
			wp_set_script_translations( 'wam-nl-editor', 'wam-newsletter' );
		}

		$css = 'assets/admin/wam-nl-editor.css';
		if ( file_exists( WAM_NL_DIR . $css ) ) {
			wp_enqueue_style(
				'wam-nl-editor',
				WAM_NL_URL . $css,
				array(),
				(string) filemtime( WAM_NL_DIR . $css )
			);
		}

		wp_localize_script( 'wam-nl-editor', 'wamNlEditor', self::editor_data() );
	}

	/** Données passées au panneau latéral. */
	public static function editor_data(): array {
		$couleurs = Brand::colors();

		return array(
			'restNamespace'   => RestApi::NAMESPACE_V1,
			'postType'        => NewsletterPostType::POST_TYPE,
			'metaKeys'        => array(
				'subject'   => '_wam_nl_subject',
				'preheader' => '_wam_nl_preheader',
				'listIds'   => '_wam_nl_list_ids',
				'scheduled' => '_wam_nl_scheduled_at',
				'status'    => '_wam_nl_status',
			),
			'colors'          => $couleurs,
			'widthDesktop'    => Brand::WIDTH_OUTER,
			'widthMobile'     => 375,
			'testRecipients'  => implode( ', ', Settings::test_recipients() ),
			'canManage'       => current_user_can( Install::CAPABILITY ),
			'listsScreenUrl'  => admin_url( 'admin.php?page=wam-nl-lists' ),
			'settingsUrl'     => admin_url( 'admin.php?page=wam-nl-settings' ),
		);
	}
}
