<?php
namespace WamNewsletter\Render\Blocks;

use WamNewsletter\Render\Brand;
use WamNewsletter\Render\Html;
use WamNewsletter\Render\Images;
use WamNewsletter\Render\Placeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Renderers des blocs wam-nl/* (§7.6.2).
 *
 * Ces mêmes méthodes servent aux deux usages, et c'est voulu :
 *  - `render_callback` du bloc, donc l'aperçu dans l'éditeur ;
 *  - rendu de l'e-mail final.
 *
 * Un seul renderer par bloc, pas deux : l'éditrice voit littéralement le HTML
 * qui partira, et il n'y a aucun risque que l'aperçu et l'envoi divergent.
 */
class Custom {

	/**
	 * Entête : bannière pleine largeur de contenu (§7.6.7).
	 *
	 * `alt="WAM Dance Studio"` par défaut et non vide : la bannière porte le
	 * logo, c'est donc une image informative. C'est l'un des défauts relevés sur
	 * la newsletter de référence.
	 */
	public static function header( array $attrs = array() ): string {
		$id = (int) ( $attrs['attachmentId'] ?? 0 );
		if ( $id <= 0 ) {
			$id = Brand::banner_id();
		}

		$lien = (string) ( $attrs['url'] ?? '' );
		if ( '' === $lien ) {
			$lien = home_url( '/' );
		}

		$alt = trim( (string) ( $attrs['alt'] ?? '' ) );
		if ( '' === $alt ) {
			$alt = Brand::site_name();
		}

		// Deux bannières dédiées (ordinateur + mobile) quand l'éditrice n'a pas
		// imposé une image particulière à ce bloc et que la version mobile existe.
		$mobile_id = 0;
		if ( (int) ( $attrs['attachmentId'] ?? 0 ) <= 0 ) {
			$mobile_id = Brand::banner_mobile_id();
		}

		$image   = $id ? Images::email_image( $id, Images::SIZE_BANNER_2X ) : null;
		$mobile  = $mobile_id ? Images::email_image( $mobile_id, Images::SIZE_BANNER_2X ) : null;

		if ( ! $image ) {
			// Aucune bannière disponible : on le dit dans l'éditeur plutôt que
			// de laisser un trou silencieux dans l'e-mail.
			return Html::row(
				'<p style="' . esc_attr( Html::text_style( array( 'color' => Brand::color( 'muted' ), 'align' => 'center', 'size' => 13 ) ) ) . '">'
				. esc_html__( 'Aucune bannière trouvée. Choisissez une image dans les réglages du bloc.', 'wam-newsletter' )
				. '</p>',
				array( 'align' => 'center' )
			);
		}

		$base_style = 'border:0;outline:none;text-decoration:none;height:auto;';

		if ( $mobile ) {
			// Version ordinateur visible par défaut ; version mobile cachée. La
			// feuille mobile (≤ 620 px) inverse les deux. Un client sans media
			// queries (Outlook de bureau) affiche donc la version ordinateur.
			$img = sprintf(
				'<img class="wam-nl-banner-desktop" src="%1$s" alt="%2$s" width="%3$d" style="display:block;width:100%%;max-width:%3$dpx;%4$s" />',
				esc_url( $image['url'] ),
				esc_attr( $alt ),
				(int) Brand::WIDTH_CONTENT,
				$base_style
			);
			$img .= sprintf(
				'<img class="wam-nl-banner-mobile" src="%1$s" alt="%2$s" width="390" style="display:none;width:100%%;max-width:0;max-height:0;overflow:hidden;mso-hide:all;%3$s" />',
				esc_url( $mobile['url'] ),
				esc_attr( $alt ),
				$base_style
			);
			$classe = 'wam-nl-banner wam-nl-banner--duo';
		} else {
			// Une seule bannière : sur mobile, la feuille la zoome et la centre.
			$img = sprintf(
				'<img class="wam-nl-banner__img" src="%1$s" alt="%2$s" width="%3$d" style="display:block;width:100%%;max-width:%3$dpx;%4$s" />',
				esc_url( $image['url'] ),
				esc_attr( $alt ),
				(int) Brand::WIDTH_CONTENT,
				$base_style
			);
			$classe = 'wam-nl-banner';
		}

		$contenu = '<div class="' . esc_attr( $classe ) . '" style="margin:0;padding:0;"><a href="' . esc_url( $lien ) . '" target="_blank" rel="noopener" style="text-decoration:none;">' . $img . '</a></div>';

		return Html::row( $contenu, array( 'align' => 'center', 'padding' => '0 ' . Brand::GUTTER . 'px' ) );
	}

	/**
	 * Pied de page (§7.6.2).
	 *
	 * Texte repris de la newsletter de référence, sans le lien « Gestion de
	 * l'abonnement » (une seule liste publique, la désinscription suffit).
	 * L'adresse est du texte simple : laissée en lien, les clients de messagerie
	 * la transforment en lien Google Maps automatique.
	 */
	public static function footer( array $attrs = array() ): string {
		$c       = Brand::colors();
		$style_p = Html::text_style(
			array(
				'size'  => 12,
				'color' => $c['muted'],
				'align' => 'center',
			)
		);
		$style_lien = 'color:' . $c['text'] . ';text-decoration:underline;';

		$desabo = sprintf(
			'<a href="%s" target="_blank" rel="noopener" style="%s">%s</a>',
			Placeholders::UNSUB_URL,
			esc_attr( $style_lien ),
			esc_html__( 'Se désabonner', 'wam-newsletter' )
		);

		$site = sprintf(
			'<a href="%s" target="_blank" rel="noopener" style="%s">%s</a>',
			esc_url( home_url( '/' ) ),
			esc_attr( $style_lien ),
			esc_html( Brand::site_host() )
		);

		$ligne = sprintf(
			'%s &#124; %s - %s - %s',
			$desabo,
			esc_html( Brand::site_name() ),
			Html::no_autolink( Brand::address(), $c['muted'] ),
			$site
		);

		$extra = trim( (string) ( $attrs['extra'] ?? '' ) );
		$bloc  = '<p style="' . esc_attr( $style_p ) . '">' . $ligne . '</p>';
		if ( '' !== $extra ) {
			$bloc = '<p style="' . esc_attr( $style_p ) . '">' . esc_html( $extra ) . '</p>' . $bloc;
		}

		return Html::row( $bloc, array( 'align' => 'center', 'padding' => '24px ' . Brand::GUTTER . 'px' ) );
	}

	/** Bouton éditable (§7.1.1). */
	public static function button( array $attrs = array() ): string {
		$contenu = Html::button(
			array(
				'texte'   => (string) ( $attrs['text'] ?? __( 'En savoir plus', 'wam-newsletter' ) ),
				'url'     => (string) ( $attrs['url'] ?? '' ),
				'style'   => (string) ( $attrs['variant'] ?? 'plein' ),
				'couleur' => (string) ( $attrs['color'] ?? '' ),
				'align'   => (string) ( $attrs['align'] ?? 'left' ),
			)
		);
		// Lecture unique, pour la même raison que dans Html::button().
		$align = (string) ( $attrs['align'] ?? 'left' );
		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$align = 'left';
		}
		// 30px au-dessus et en dessous (12px avant) : un bouton collé au texte se
		// lit mal au pouce. Encore augmenté sur mobile par la classe de la cellule.
		return Html::row(
			$contenu,
			array(
				'align'   => $align,
				'padding' => '30px ' . Brand::GUTTER . 'px',
				'class'   => 'wam-nl-row-bouton',
			)
		);
	}

	/** Séparateur pointillé turquoise (§7.6.2). */
	public static function separator( array $attrs = array() ): string {
		$couleur = Brand::to_hex( (string) ( $attrs['color'] ?? '' ) );
		if ( '' === $couleur ) {
			$couleur = Brand::color( 'separator' );
		}

		$trait = sprintf(
			'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%%" style="border-collapse:collapse;width:100%%;">'
			. '<tr><td style="border-top:2px dotted %s;font-size:0;line-height:0;">&nbsp;</td></tr></table>',
			esc_attr( $couleur )
		);

		// 30px autour du séparateur : 40px (demande d'Antoine du 08/10) donnait,
		// avec les 12px de la rangée suivante, 52px jusqu'au titre ; un cran de moins
		// (retour du 09/10) donne 42px. Toujours plus large que la valeur de secours
		// du §7.6.2 (13px), qui venait de l'e-mail de référence MailPoet.
		return Html::row( $trait, array( 'padding' => '30px ' . Brand::GUTTER . 'px' ) );
	}

	/**
	 * Espacement vertical.
	 *
	 * Une cellule avec `height` ET `font-size:0;line-height:0` : sans ces deux
	 * dernières propriétés, Outlook impose la hauteur d'une ligne de texte et
	 * l'espacement ne descend jamais en dessous d'environ 18 px.
	 */
	public static function spacer( array $attrs = array() ): string {
		$hauteur = (int) ( $attrs['height'] ?? 24 );
		$hauteur = max( 4, min( 120, $hauteur ) );

		return sprintf(
			'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%%" style="border-collapse:collapse;width:100%%;">'
			. '<tr><td height="%1$d" style="height:%1$dpx;font-size:0;line-height:0;">&nbsp;</td></tr></table>',
			$hauteur
		);
	}
}
