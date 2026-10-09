<?php
namespace WamNewsletter\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Briques HTML communes aux renderers d'e-mail.
 *
 * Tout le rendu est en tables avec styles en ligne (§7.4). Pas de librairie
 * d'« inlining » : les renderers écrivent les styles directement, ce qui évite
 * une dépendance et rend le HTML produit relisible.
 *
 * Deux règles tenues ici une fois pour toutes :
 *  - `border-collapse: collapse` sur chaque table, sinon Outlook ajoute des
 *    espaces entre les cellules et la mise en page se décale ;
 *  - `role="presentation"` partout, pour que les lecteurs d'écran ne lisent pas
 *    la mise en page comme un tableau de données.
 */
class Html {

	/**
	 * Enveloppe un contenu dans une table pleine largeur avec ses gouttières.
	 *
	 * C'est le motif de base du gabarit : chaque bloc est sa propre table
	 * (§7.6.1), ce qui garde les blocs indépendants et autorise n'importe quel
	 * ordre sans effet de bord.
	 *
	 * @param string $contenu HTML déjà échappé.
	 * @param array  $opts    padding, align, background, class (sur la cellule : cible des media queries)
	 */
	public static function row( string $contenu, array $opts = array() ): string {
		// 12px haut + 12px bas = 24px entre deux zones par défaut (titre,
		// image, paragraphe...), quelle que soit leur nature - demande
		// d'Antoine après relecture, qui remplace l'ancien défaut à 10px.
		$padding = $opts['padding'] ?? ( '12px ' . Brand::GUTTER . 'px' );
		$align   = $opts['align'] ?? 'left';
		$classe  = isset( $opts['class'] ) ? ' class="' . esc_attr( (string) $opts['class'] ) . '"' : '';
		$fond    = isset( $opts['background'] ) ? ' bgcolor="' . esc_attr( $opts['background'] ) . '"' : '';
		$style   = 'padding:' . $padding . ';text-align:' . $align . ';';
		if ( isset( $opts['background'] ) ) {
			$style .= 'background-color:' . $opts['background'] . ';';
		}

		return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"'
			. ' style="border-collapse:collapse;width:100%;">'
			. '<tr><td' . $classe . $fond . ' style="' . esc_attr( $style ) . '">'
			. $contenu
			. '</td></tr></table>';
	}

	/**
	 * Table fantôme MSO ouvrante pour une rangée de colonnes (§7.6.4).
	 *
	 * Outlook pour Windows utilise le moteur de rendu de Word, qui ignore
	 * `display:inline-block` : sans ces commentaires conditionnels, les colonnes
	 * s'empilent au lieu de se juxtaposer. Le commentaire est invisible pour tous
	 * les autres clients.
	 *
	 * @param int[] $largeurs Largeur de chaque colonne en pixels.
	 */
	public static function mso_columns_open( array $largeurs ): string {
		$cellules = '';
		foreach ( $largeurs as $largeur ) {
			$cellules .= '<td width="' . (int) $largeur . '" valign="top">';
			$cellules .= '<![endif]-->';
			$cellules .= '<!--[if mso]></td>';
		}
		// La dernière cellule est refermée par mso_columns_close().
		$cellules = (string) preg_replace( '/<!--\[if mso\]><\/td>$/', '', $cellules );

		return '<!--[if mso]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;"><tr>' . $cellules;
	}

	public static function mso_columns_close(): string {
		return '<!--[if mso]></td></tr></table><![endif]-->';
	}

	/** Séparateur de colonnes dans la suite MSO. */
	public static function mso_column_break( int $largeur ): string {
		return '<!--[if mso]></td><td width="' . (int) $largeur . '" valign="top"><![endif]-->';
	}

	/**
	 * Bouton « bulletproof » (§7.1.1).
	 *
	 * Rendu en table et non en `<a>` stylé : Outlook n'applique ni padding ni
	 * `border-radius` à un lien, le bouton y apparaîtrait comme du texte souligné.
	 *
	 * @param array $opts texte, url, style (plein|contour), couleur, align
	 */
	public static function button( array $opts ): string {
		$texte   = (string) ( $opts['texte'] ?? __( 'En savoir plus', 'wam-newsletter' ) );
		$url     = (string) ( $opts['url'] ?? '' );
		$variante = 'contour' === ( $opts['style'] ?? 'plein' ) ? 'contour' : 'plein';
		$couleur = Brand::to_hex( (string) ( $opts['couleur'] ?? '' ) );
		if ( '' === $couleur ) {
			$couleur = Brand::color( 'accent' );
		}
		// L'alignement est lu UNE fois : écrire `in_array( $opts['align'] ?? 'left', … ) ? $opts['align'] : 'left'`
		// émettait « Undefined array key » quand la clé manquait — « left » étant
		// dans la liste autorisée, c'est la branche vraie qui s'exécutait et qui
		// relisait la clé absente.
		$align = (string) ( $opts['align'] ?? 'left' );
		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$align = 'left';
		}

		if ( 'contour' === $variante ) {
			$fond        = 'transparent';
			$texte_color = $couleur;
			$bordure     = '2px solid ' . $couleur;
		} else {
			$fond        = $couleur;
			$texte_color = Brand::contrast_color( $couleur );
			$bordure     = '2px solid ' . $couleur;
		}

		$style_lien = sprintf(
			'display:inline-block;font-family:%s;font-size:16px;font-weight:bold;line-height:39px;'
			. 'color:%s;text-decoration:none;padding:0 24px;border-radius:4px;border:%s;'
			. 'background-color:%s;mso-line-height-rule:exactly;',
			Brand::FONT_STACK,
			$texte_color,
			$bordure,
			$fond
		);

		// Sans URL (bouton en cours de rédaction), on rend un span : un <a href="">
		// renverrait sur la page courante au clic.
		$interieur = '' === $url
			? '<span style="' . esc_attr( $style_lien ) . '">' . esc_html( $texte ) . '</span>'
			: '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" style="' . esc_attr( $style_lien ) . '">' . esc_html( $texte ) . '</a>';

		return '<table role="presentation" border="0" cellpadding="0" cellspacing="0"'
			. ' style="border-collapse:collapse;" align="' . esc_attr( $align ) . '">'
			. '<tr><td align="' . esc_attr( $align ) . '" style="border-radius:4px;">'
			. $interieur
			. '</td></tr></table>';
	}

	/**
	 * Texte que les messageries ne doivent pas transformer en lien.
	 *
	 * Gmail et Apple Mail repèrent adresses postales, dates et numéros et en font
	 * des liens bleus (vers Maps, l'agenda…), qui jurent sur le fond sombre. Un
	 * caractère invisible (`&zwnj;`) après chaque chiffre casse la détection ;
	 * la couleur posée en ligne et la règle `x-apple-data-detectors` du gabarit
	 * rattrapent les cas qui passeraient quand même.
	 */
	public static function no_autolink( string $texte, string $couleur ): string {
		$html = (string) preg_replace( '/(\d)/', '$1&zwnj;', esc_html( $texte ) );
		return '<span style="color:' . esc_attr( $couleur ) . ';text-decoration:none;">' . $html . '</span>';
	}

	/**
	 * Tronque un texte au mot, sans couper au milieu (§7.6.3).
	 */
	public static function excerpt( string $texte, int $max = 300 ): string {
		$texte = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $texte ) ) );
		if ( '' === $texte ) {
			return '';
		}
		$texte = html_entity_decode( $texte, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( mb_strlen( $texte ) <= $max ) {
			return $texte;
		}
		$coupe = mb_substr( $texte, 0, $max );
		$espace = mb_strrpos( $coupe, ' ' );
		if ( false !== $espace && $espace > (int) ( $max * 0.6 ) ) {
			$coupe = mb_substr( $coupe, 0, $espace );
		}
		return rtrim( $coupe, " ,.;:–-" ) . '…';
	}

	/**
	 * Style de paragraphe standard du corps (§7.6.4 : 16 px et non 14).
	 */
	public static function text_style( array $opts = array() ): string {
		$taille  = (int) ( $opts['size'] ?? 16 );
		$couleur = (string) ( $opts['color'] ?? Brand::color( 'text' ) );
		$align   = (string) ( $opts['align'] ?? 'left' );
		$gras    = ! empty( $opts['bold'] ) ? 'font-weight:bold;' : '';

		return sprintf(
			'margin:0;font-family:%s;font-size:%dpx;line-height:1.5;color:%s;text-align:%s;%s',
			Brand::FONT_STACK,
			$taille,
			$couleur,
			$align,
			$gras
		);
	}
}
