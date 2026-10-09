<?php
namespace WamNewsletter\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Règles de mise en page de l'e-mail : espacements, tailles, interlignes.
 *
 * Source unique pour DEUX lecteurs :
 *  - les renderers (BlockRenderer, Core, Custom, gabarit) qui écrivent l'e-mail ;
 *  - EmailTheme::canvas_css(), qui reproduit ces mêmes règles dans le canevas
 *    de l'éditeur, pour qu'on compose en voyant les espacements réels.
 * Changer une valeur ici la change dans l'e-mail ET dans l'éditeur.
 *
 * Toutes les valeurs sont en px. Une « rangée » est une ligne de l'e-mail : un
 * bloc entouré de son padding (haut/bas) et des gouttières (gauche/droite).
 */
class Spacing {

	/** Padding haut et bas d'une rangée ordinaire : 12 + 12 = 24 px entre deux blocs. */
	const ROW_Y = 12;

	/** Taille et interligne du texte courant (paragraphes, listes). */
	const TEXT_SIZE   = 16;
	const TEXT_LINE   = 1.5;

	/** Titres : tailles par niveau (h1 est ramené à h2), interligne. */
	const HEADING_SIZES = array(
		2 => 30,
		3 => 24,
		4 => 20,
	);
	const HEADING_LINE  = 1.25;

	/** Listes : retrait des puces et écart entre deux éléments. */
	const LIST_INDENT   = 24;
	const LIST_ITEM_GAP = 8;

	/** Image : air au-dessus quand un bloc la précède, en dessous quand un bloc la suit. */
	const IMAGE_BEFORE = 36;
	const IMAGE_AFTER  = 48;

	/** Bloc à fond / bordure / arrondi : air autour de la boîte, et dans la boîte. */
	const BOX_ROW_Y = 20;
	const BOX_PAD_Y = 14;
	const BOX_PAD_X = 20;

	/** Blocs WAM. */
	const BUTTON_Y    = 30;
	const SEPARATOR_Y = 30;
	const FOOTER_Y    = 24;
	const SPACER      = 24;

	/** Écart entre deux colonnes. */
	const COLUMN_GAP = 20;

	/** Mobile (≤ MOBILE_MAX px) : plus d'air, les blocs s'empilent. */
	const MOBILE_MAX          = 620;
	const MOBILE_IMAGE_AFTER  = 56;
	const MOBILE_BOX_ROW_Y    = 28;
	const MOBILE_BUTTON_Y     = 36;
	const MOBILE_STACK        = 16;

	/** Padding d'une rangée « haut/bas + gouttières ». */
	public static function row( int $y ): string {
		return $y . 'px ' . Brand::GUTTER . 'px';
	}

	/**
	 * Règles mobiles de l'e-mail (seule partie non inline, §7.4).
	 *
	 * Écrites une seule fois et affichées à deux endroits : dans le <style> du
	 * gabarit de l'e-mail, et dans le canevas de l'éditeur, où l'aperçu
	 * « Mobile » les déclenche à l'identique.
	 */
	public static function mobile_css(): string {
		return '	@media only screen and (max-width: ' . self::MOBILE_MAX . 'px) {
		.wam-nl-col {
			display: block !important;
			width: 100% !important;
			max-width: 100% !important;
		}
		.wam-nl-gutter {
			display: none !important;
			width: 0 !important;
		}
		.wam-nl-stack-space {
			height: ' . self::MOBILE_STACK . 'px !important;
		}
		/* Plus d’air sur mobile, où les blocs s’empilent et se touchent vite. */
		.wam-nl-row-image {
			padding-bottom: ' . self::MOBILE_IMAGE_AFTER . 'px !important;
		}
		.wam-nl-row-box {
			padding-top: ' . self::MOBILE_BOX_ROW_Y . 'px !important;
			padding-bottom: ' . self::MOBILE_BOX_ROW_Y . 'px !important;
		}
		.wam-nl-row-bouton {
			padding-top: ' . self::MOBILE_BUTTON_Y . 'px !important;
			padding-bottom: ' . self::MOBILE_BUTTON_Y . 'px !important;
		}
		/* Bannière : pleine largeur d’écran, puis agrandie à 140 % et centrée.
		   Le logo est zoomé, les côtés sont rognés par overflow. */
		.wam-nl-banner {
			margin: 0 -' . Brand::GUTTER . 'px !important;
			overflow: hidden !important;
		}
		.wam-nl-banner__img {
			width: 140% !important;
			max-width: none !important;
			margin-left: -20% !important;
		}
		/* Deux bannières dédiées : la version ordinateur s’efface, la version
		   mobile prend toute la largeur d’écran. */
		.wam-nl-banner-desktop {
			display: none !important;
		}
		.wam-nl-banner-mobile {
			display: block !important;
			width: 100% !important;
			max-width: none !important;
			max-height: none !important;
		}
	}';
	}
}
