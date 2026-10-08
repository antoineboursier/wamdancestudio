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

		$image = $id ? Images::email_image( $id, Images::SIZE_BANNER ) : null;

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

		$img = sprintf(
			'<img src="%s" alt="%s" width="%d" style="display:block;width:100%%;max-width:%dpx;height:auto;border:0;outline:none;text-decoration:none;" />',
			esc_url( $image['url'] ),
			esc_attr( $alt ),
			(int) Brand::WIDTH_CONTENT,
			(int) Brand::WIDTH_CONTENT
		);

		$contenu = '<a href="' . esc_url( $lien ) . '" target="_blank" rel="noopener" style="text-decoration:none;">' . $img . '</a>';

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
			esc_html( Brand::address() ),
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
		return Html::row( $contenu, array( 'align' => $align ) );
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

		// 40px autour du séparateur (demande d'Antoine) - plus large que la
		// valeur de secours du §7.6.2 (13px), qui venait de l'e-mail de
		// référence MailPoet et n'est donc pas une contrainte figée.
		return Html::row( $trait, array( 'padding' => '40px ' . Brand::GUTTER . 'px' ) );
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
