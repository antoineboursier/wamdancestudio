<?php
namespace WamNewsletter\Editor;

use WamNewsletter\Render\Brand;

defined( 'ABSPATH' ) || exit;

/**
 * Le « theme.json » de l'e-mail, injecté dans l'éditeur de blocs.
 *
 * C'est la pièce qui transforme l'éditeur d'article en éditeur d'e-mail, et
 * c'est exactement l'architecture retenue par MailPoet 5.34 (son paquet
 * `woocommerce/email-editor` embarque un theme.json dédié, avec
 * `layout.contentSize` à 660 px, des polices web-safe et des espacements en px).
 *
 * Deux sorties distinctes, à ne pas confondre :
 *  - settings() : ce que les PANNEAUX de l'éditeur proposent (palette, tailles
 *    de police, espacements). Détermine les choix offerts, pas l'apparence.
 *  - canvas_css() : ce que le canevas AFFICHE. C'est ce qui fait que la zone
 *    d'édition ressemble à l'e-mail réel — fond sombre, 660 px, Arial — au lieu
 *    de ressembler à un article du site.
 *
 * Les couleurs viennent toutes de Brand, donc du theme.json du thème (§7.6.1).
 */
class EmailTheme {

	/** Tailles de police proposées, en px (les em ne sont pas fiables en e-mail). */
	const FONT_SIZES = array(
		array(
			'slug' => 'small',
			'name' => 'Petit',
			'size' => '13px',
		),
		array(
			'slug' => 'medium',
			'name' => 'Normal',
			'size' => '16px',
		),
		array(
			'slug' => 'large',
			'name' => 'Grand',
			'size' => '22px',
		),
		array(
			'slug' => 'x-large',
			'name' => 'Titre',
			'size' => '30px',
		),
	);

	/** Espacements proposés, en px. */
	const SPACING_SIZES = array(
		array(
			'slug' => '10',
			'name' => 'Très petit',
			'size' => '8px',
		),
		array(
			'slug' => '20',
			'name' => 'Petit',
			'size' => '16px',
		),
		array(
			'slug' => '30',
			'name' => 'Moyen',
			'size' => '24px',
		),
		array(
			'slug' => '40',
			'name' => 'Grand',
			'size' => '40px',
		),
	);

	/**
	 * Réglages passés à l'éditeur, au format `__experimentalFeatures`.
	 *
	 * Tout ce qui n'a pas de sens dans un e-mail est coupé : dégradés, duotone,
	 * couleurs libres, marges négatives, unités relatives. Un dégradé choisi dans
	 * l'éditeur ne s'afficherait dans aucun client lourd — mieux vaut ne pas le
	 * proposer que de livrer une newsletter cassée chez la moitié des gens.
	 */
	public static function settings(): array {
		return array(
			'color'      => array(
				'palette'          => array( 'theme' => Brand::palette() ),
				'gradients'        => array( 'theme' => array() ),
				'custom'           => false,
				'customGradient'   => false,
				'defaultGradients' => false,
				'defaultPalette'   => false,
				'customDuotone'    => false,
				'defaultDuotone'   => false,
				'background'       => true,
				'text'             => true,
				'link'             => true,
			),
			'typography' => array(
				'fontFamilies'     => array(
					'theme' => array(
						array(
							'slug'       => 'email',
							'name'       => 'Arial (standard e-mail)',
							'fontFamily' => Brand::FONT_STACK,
						),
					),
				),
				'fontSizes'        => array( 'theme' => self::FONT_SIZES ),
				'customFontSize'   => true,
				'defaultFontSizes' => false,
				'fontStyle'        => true,
				'fontWeight'       => true,
				'lineHeight'       => true,
				'textDecoration'   => true,
				'textTransform'    => false,
				'letterSpacing'    => false,
				'dropCap'          => false,
			),
			'spacing'    => array(
				'units'                => array( 'px' ),
				'padding'              => true,
				'margin'               => false,
				'blockGap'             => false,
				'spacingSizes'         => array( 'theme' => self::SPACING_SIZES ),
				'defaultSpacingSizes'  => false,
				'customSpacingSize'    => true,
			),
			'border'     => array(
				'color'  => true,
				'radius' => true,
				'style'  => true,
				'width'  => true,
			),
			'layout'     => array(
				'contentSize'                   => Brand::WIDTH_OUTER . 'px',
				'wideSize'                      => Brand::WIDTH_OUTER . 'px',
				'allowEditing'                  => false,
				'allowCustomContentAndWideSize' => false,
			),
			'blocks'     => array(),
		);
	}

	/**
	 * CSS appliqué DANS le canevas d'édition.
	 *
	 * Écrit en ciblant `body` et les classes de bloc : WordPress ré-étend ces
	 * sélecteurs sous `.editor-styles-wrapper`, exactement comme pour
	 * `add_editor_style()`. Les valeurs reprennent le gabarit du §7.6.2, pour que
	 * ce que voit l'éditrice corresponde à ce qui partira.
	 */
	public static function canvas_css(): string {
		$c      = Brand::colors();
		$police = Brand::FONT_STACK;
		$largeur = Brand::WIDTH_CONTENT;

		return "
		body {
			background-color: {$c['background']};
			color: {$c['text']};
			font-family: {$police};
			font-size: 16px;
			line-height: 1.5;
		}

		body a,
		body a:hover {
			color: {$c['text']};
			text-decoration: underline;
		}

		/* Titres de section : jaune, centrés, 30 px (§7.6.2). Un seul h1 au plus
		   dans l'e-mail final, les titres de section sortent en h2. */
		body h1,
		body h2,
		body h3,
		body h4 {
			color: {$c['accent']};
			font-family: {$police};
			font-weight: 700;
			line-height: 1.25;
			text-align: center;
			margin: 0 0 16px;
		}

		body h1 { font-size: 32px; }
		body h2 { font-size: 30px; }
		body h3 { font-size: 24px; }
		body h4 { font-size: 20px; }

		body p { margin: 0 0 16px; }

		body ul,
		body ol {
			margin: 0 0 16px;
			padding-left: 24px;
		}

		body img {
			max-width: 100%;
			height: auto;
		}

		/* Repère de la zone de contenu réelle (620 px), pour que la largeur
		   utile soit visible pendant la rédaction. */
		body .wp-block-image img {
			max-width: {$largeur}px;
		}

		body .wam-nl-editor-note {
			color: {$c['muted']};
			font-size: 13px;
			text-align: center;
		}
		";
	}
}
