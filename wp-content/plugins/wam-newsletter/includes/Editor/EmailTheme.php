<?php
namespace WamNewsletter\Editor;

use WamNewsletter\Render\Brand;
use WamNewsletter\Render\Spacing;

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
	 * Reflet des règles de l'e-mail, généré depuis Render\Spacing : les mêmes
	 * valeurs écrivent l'e-mail et dessinent le canevas. Composer un espacement
	 * dans l'éditeur, c'est donc voir celui que recevront les abonné·es.
	 *
	 * Deux familles de blocs :
	 *  - les blocs WAM (en-tête, pied, bouton, séparateur, espacement, Contenus)
	 *    sont déjà affichés par le moteur de l'e-mail (rendu serveur) : rien à
	 *    reproduire, seulement à ne pas déranger ;
	 *  - les blocs natifs (paragraphe, titre, liste, image, colonnes) sont dessinés
	 *    par WordPress : on leur applique ici les rangées de l'e-mail (padding
	 *    haut/bas + gouttières, aucune marge entre blocs).
	 *
	 * Écrit en ciblant `body` : WordPress ré-étend ces sélecteurs sous
	 * `.editor-styles-wrapper`, comme pour `add_editor_style()`.
	 */
	public static function canvas_css(): string {
		$c       = Brand::colors();
		$police  = Brand::FONT_STACK;
		$largeur = Brand::WIDTH_CONTENT;
		$g       = Brand::GUTTER;

		$rangee     = Spacing::ROW_Y;
		$texte      = Spacing::TEXT_SIZE;
		$interligne = Spacing::TEXT_LINE;
		$titre_lh   = Spacing::HEADING_LINE;
		$h          = Spacing::HEADING_SIZES;
		$retrait    = $g + Spacing::LIST_INDENT;
		$item       = Spacing::LIST_ITEM_GAP;
		$img_avant  = Spacing::IMAGE_BEFORE;
		$img_apres  = Spacing::IMAGE_AFTER;
		$boite_y    = Spacing::BOX_ROW_Y;
		$boite_py   = Spacing::BOX_PAD_Y;
		$boite_px   = Spacing::BOX_PAD_X;
		$colonnes   = Spacing::COLUMN_GAP;
		$mobile     = Spacing::MOBILE_MAX;
		$m_img      = Spacing::MOBILE_IMAGE_AFTER;
		$m_boite    = Spacing::MOBILE_BOX_ROW_Y;

		// Un bloc à fond, à bordure ou arrondi devient une boîte dans l'e-mail.
		$boites = implode(
			', ',
			array(
				'body .wp-block-paragraph.has-background',
				'body .wp-block-heading.has-background',
				'body .wp-block-paragraph.has-border-color',
				'body .wp-block-heading.has-border-color',
				'body .wp-block-paragraph[style*="border"]',
				'body .wp-block-heading[style*="border"]',
			)
		);

		return "
		body {
			background-color: {$c['background']};
			color: {$c['text']};
			font-family: {$police};
			font-size: {$texte}px;
			line-height: {$interligne};
		}

		body a,
		body a:hover {
			color: {$c['text']};
			text-decoration: underline;
		}

		/* Aucune marge entre blocs : dans l'e-mail, l'écart vient uniquement du
		   padding des rangées (2 × {$rangee} px entre deux blocs ordinaires). */
		body .is-root-container > *,
		body .is-layout-flow > *,
		body .wp-block-column > * {
			margin-top: 0;
			margin-bottom: 0;
		}

		/* Le padding compte DANS la largeur, comme dans les cellules de l'e-mail :
		   sinon les {$g} px de gouttière s'ajoutent aux " . Brand::WIDTH_OUTER . " px. */
		body .wp-block-paragraph,
		body .wp-block-heading,
		body .wp-block-list,
		body .wp-block-image,
		body .wp-block-columns {
			box-sizing: border-box;
		}

		/* Rangée ordinaire : {$rangee} px en haut et en bas, {$g} px de gouttière. */
		body .wp-block-paragraph,
		body .wp-block-heading {
			padding: {$rangee}px {$g}px;
			margin-top: 0;
			margin-bottom: 0;
		}

		body .wp-block-paragraph {
			font-size: {$texte}px;
			line-height: {$interligne};
		}

		/* Titres : jaune, gras, centrés par défaut. Le h1 part en h2 dans l'e-mail. */
		body .wp-block-heading {
			color: {$c['accent']};
			font-family: {$police};
			font-weight: 700;
			line-height: {$titre_lh};
			text-align: center;
		}
		body h1.wp-block-heading,
		body h2.wp-block-heading { font-size: {$h[2]}px; }
		body h3.wp-block-heading { font-size: {$h[3]}px; }
		body h4.wp-block-heading,
		body h5.wp-block-heading,
		body h6.wp-block-heading { font-size: {$h[4]}px; }
		body .wp-block-heading.has-text-align-left { text-align: left; }
		body .wp-block-heading.has-text-align-right { text-align: right; }

		/* Listes : rangée + retrait des puces, {$item} px entre deux éléments. */
		body .wp-block-list {
			padding: {$rangee}px {$g}px {$rangee}px {$retrait}px;
			margin-top: 0;
			margin-bottom: 0;
			font-size: {$texte}px;
			line-height: {$interligne};
		}
		body .wp-block-list li {
			margin: 0 0 {$item}px;
		}

		/* Image : {$img_avant} px au-dessus quand un bloc la précède, {$img_apres} px
		   en dessous quand un bloc la suit ; {$rangee} px en tête ou en fin. */
		body .wp-block-image {
			padding: {$img_avant}px {$g}px {$img_apres}px;
			margin-top: 0;
			margin-bottom: 0;
		}
		body .is-root-container > .wp-block-image:first-child { padding-top: {$rangee}px; }
		body .is-root-container > .wp-block-image:last-child { padding-bottom: {$rangee}px; }
		body .wp-block-image img {
			max-width: {$largeur}px;
			height: auto;
		}
		/* Dans une colonne : rien au-dessus, de l'air en dessous (empilement mobile). */
		body .wp-block-column .wp-block-image {
			padding-top: {$rangee}px;
			padding-bottom: {$img_apres}px;
		}

		/* Boîte (fond, bordure, arrondi) : {$boite_y} px d'air autour, {$boite_py} × {$boite_px} px
		   dedans par défaut (un espacement choisi dans le panneau l'emporte). */
		{$boites} {
			margin-top: {$boite_y}px !important;
			margin-bottom: {$boite_y}px !important;
			margin-left: auto !important;
			margin-right: auto !important;
			max-width: min({$largeur}px, calc(100% - " . ( 2 * $g ) . "px));
			padding: {$boite_py}px {$boite_px}px;
		}

		/* Colonnes : une rangée, {$colonnes} px entre colonnes. Chaque bloc d'une
		   colonne garde sa propre rangée, comme dans l'e-mail. */
		body .wp-block-columns {
			padding: {$rangee}px {$g}px;
			margin-top: 0;
			margin-bottom: 0;
			column-gap: {$colonnes}px;
			row-gap: 0;
		}

		/* L'e-mail n'empile les colonnes qu'à {$mobile} px ; l'éditeur le fait dès
		   781 px. Entre les deux, on garde les colonnes côte à côte. */
		@media (min-width: " . ( $mobile + 1 ) . "px) and (max-width: 781px) {
			body .wp-block-columns:not(.is-not-stacked-on-mobile) {
				flex-wrap: nowrap !important;
			}
			body .wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column {
				flex-basis: 0 !important;
				flex-grow: 1;
			}
		}

		/* Aperçu « Mobile » de l'éditeur : mêmes règles que l'e-mail sur téléphone. */
		@media only screen and (max-width: {$mobile}px) {
			body .wp-block-image,
			body .wp-block-column .wp-block-image,
			body .is-root-container > .wp-block-image:last-child {
				padding-bottom: {$m_img}px;
			}
			{$boites} {
				margin-top: {$m_boite}px !important;
				margin-bottom: {$m_boite}px !important;
			}
		}
		" . Spacing::mobile_css() . "

		body .wam-nl-editor-note {
			color: {$c['muted']};
			font-size: 13px;
			text-align: center;
		}
		";
	}
}
