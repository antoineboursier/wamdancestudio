<?php
namespace WamNewsletter\Render\Blocks;

use WamNewsletter\Render\Brand;
use WamNewsletter\Render\Html;
use WamNewsletter\Render\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Renderers des blocs du cœur autorisés dans une newsletter.
 *
 * Le HTML enregistré par Gutenberg est du HTML de page : classes utilitaires,
 * `<figure>`, `wp-block-*`. Rien de tout cela n'est exploitable en e-mail, où il
 * faut des tables et des styles en ligne. Chaque renderer repart donc du contenu
 * textuel et le réécrit, plutôt que de rafistoler le markup d'origine.
 */
class Core {

	/** Balises conservées dans un texte. Tout le reste est retiré. */
	const INLINE_TAGS = array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'br'     => array(),
		's'      => array(),
		'a'      => array(
			'href'   => true,
			'title'  => true,
			'target' => true,
			'rel'    => true,
		),
	);

	/**
	 * Nettoie un fragment de texte riche et style ses liens.
	 *
	 * Les liens doivent porter leur couleur en ligne : plusieurs clients
	 * (Gmail, Outlook) appliquent sinon un bleu par défaut, illisible sur le
	 * fond sombre de l'e-mail.
	 */
	public static function inline_text( string $html, string $couleur_lien = '' ): string {
		$html = wp_kses( $html, self::INLINE_TAGS );

		// Couleur des liens choisie dans l'éditeur, sinon le blanc (crème) du
		// texte : jamais le bleu par défaut des clients de messagerie.
		$style = 'color:' . ( '' !== $couleur_lien ? $couleur_lien : Brand::color( 'text' ) ) . ';text-decoration:underline;';
		$html  = (string) preg_replace_callback(
			'/<a\s+([^>]*)>/i',
			static function ( $m ) use ( $style ) {
				$attrs = preg_replace( '/\s*style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $m[1] );
				$attrs = trim( (string) $attrs );
				if ( false === stripos( $attrs, 'target=' ) ) {
					$attrs .= ' target="_blank" rel="noopener"';
				}
				return '<a ' . $attrs . ' style="' . esc_attr( $style ) . '">';
			},
			$html
		);

		return trim( $html );
	}

	/** Contenu textuel d'un bloc, depuis son HTML enregistré. */
	private static function inner( array $bloc ): string {
		$html = (string) ( $bloc['innerHTML'] ?? '' );
		if ( '' === trim( $html ) ) {
			return '';
		}
		// On retire l'enveloppe du bloc (<p>, <h2>, <figure>…) pour ne garder que
		// son contenu : l'enveloppe est réécrite par le renderer avec ses styles.
		$html = (string) preg_replace( '#^\s*<(p|h[1-6]|figure|div)\b[^>]*>#i', '', $html );
		$html = (string) preg_replace( '#</(p|h[1-6]|figure|div)>\s*$#i', '', $html );
		return trim( $html );
	}

	public static function paragraph( array $bloc ): string {
		$attrs   = (array) ( $bloc['attrs'] ?? array() );
		$contenu = self::inline_text( self::inner( $bloc ), self::link_color( $attrs ) );
		if ( '' === $contenu ) {
			return '';
		}

		$align = in_array( $attrs['align'] ?? '', array( 'center', 'right' ), true ) ? $attrs['align'] : 'left';

		// Sur un fond coloré, le texte par défaut est celui qui reste lisible dessus.
		$fond    = self::background_of( $attrs );
		$couleur = self::text_color( $attrs, '' !== $fond ? Brand::contrast_color( $fond ) : '' );

		$style = Html::text_style(
			array(
				'align' => $align,
				'size'  => self::font_size( $attrs, 16 ),
				'color' => $couleur,
			)
		);

		return self::boxed_row( '<p style="' . esc_attr( $style ) . '">' . $contenu . '</p>', $attrs, $couleur );
	}

	/**
	 * Titre de section (§7.6.2 : jaune, 30 px, gras, centré).
	 *
	 * Les niveaux sont ramenés à h2 au maximum : la newsletter de référence
	 * contenait plusieurs h1, ce qui est un défaut relevé au §7.6.4. Le h1 est
	 * réservé à un éventuel titre unique en tête d'e-mail.
	 */
	public static function heading( array $bloc ): string {
		$attrs   = (array) ( $bloc['attrs'] ?? array() );
		$contenu = self::inline_text( self::inner( $bloc ), self::link_color( $attrs ) );
		if ( '' === $contenu ) {
			return '';
		}

		$niveau = (int) ( $attrs['level'] ?? 2 );
		$niveau = max( 2, min( 4, $niveau ) );

		$tailles = array(
			2 => 30,
			3 => 24,
			4 => 20,
		);
		$align = in_array( $attrs['textAlign'] ?? '', array( 'left', 'right' ), true ) ? $attrs['textAlign'] : 'center';

		$fond    = self::background_of( $attrs );
		$couleur = self::text_color( $attrs, '' !== $fond ? Brand::contrast_color( $fond ) : Brand::color( 'accent' ) );

		$style = sprintf(
			'margin:0;font-family:%s;font-size:%dpx;line-height:1.25;font-weight:bold;color:%s;text-align:%s;',
			Brand::FONT_STACK,
			self::font_size( $attrs, $tailles[ $niveau ] ),
			$couleur,
			$align
		);

		return self::boxed_row( sprintf( '<h%1$d style="%2$s">%3$s</h%1$d>', $niveau, esc_attr( $style ), $contenu ), $attrs, $couleur );
	}

	/** Liste à puces ou numérotée. */
	public static function list_block( array $bloc ): string {
		$html = (string) ( $bloc['innerHTML'] ?? '' );
		if ( '' === trim( $html ) ) {
			return '';
		}

		$attrs    = (array) ( $bloc['attrs'] ?? array() );
		$ordonnee = ! empty( $attrs['ordered'] );
		$balise   = $ordonnee ? 'ol' : 'ul';

		// Les éléments sont des blocs imbriqués (core/list-item) depuis WP 6.0,
		// mais restent du HTML plat sur les contenus plus anciens : on lit les
		// <li> du HTML rendu, ce qui couvre les deux cas.
		$rendu = '';
		if ( ! empty( $bloc['innerBlocks'] ) ) {
			foreach ( (array) $bloc['innerBlocks'] as $item ) {
				$rendu .= (string) ( $item['innerHTML'] ?? '' );
			}
		} else {
			$rendu = $html;
		}

		if ( preg_match_all( '#<li\b[^>]*>(.*?)</li>#is', $rendu, $m ) ) {
			$elements = '';
			$style_li = Html::text_style( array( 'size' => self::font_size( $attrs, 16 ) ) ) . 'margin:0 0 8px;';
			foreach ( $m[1] as $texte ) {
				$propre = self::inline_text( $texte, self::link_color( $attrs ) );
				if ( '' === $propre ) {
					continue;
				}
				$elements .= '<li style="' . esc_attr( $style_li ) . '">' . $propre . '</li>';
			}
			if ( '' === $elements ) {
				return '';
			}

			$style_liste = sprintf(
				'margin:0;padding:0 0 0 24px;font-family:%s;color:%s;',
				Brand::FONT_STACK,
				self::text_color( $attrs )
			);

			return Html::row( sprintf( '<%1$s style="%2$s">%3$s</%1$s>', $balise, esc_attr( $style_liste ), $elements ) );
		}

		return '';
	}

	/**
	 * Image du contenu.
	 *
	 * Passe systématiquement par Images::email_image() : une image du site est
	 * en AVIF, invisible dans Outlook desktop (§7.6.4).
	 */
	/** Profondeur de colonnes en cours de rendu (une image de colonne s'empile sur mobile). */
	private static int $dans_colonne = 0;

	public static function image( array $bloc, bool $contenu_avant = false, bool $contenu_apres = false ): string {
		// Dans une colonne, l'image se retrouve au-dessus d'un autre bloc dès que les
		// colonnes s'empilent (mobile) : elle a toujours besoin d'air en dessous.
		if ( self::$dans_colonne > 0 ) {
			$contenu_apres = true;
		}
		$attrs = (array) ( $bloc['attrs'] ?? array() );
		$id    = (int) ( $attrs['id'] ?? 0 );

		$image = $id ? Images::email_image( $id, Images::SIZE_BANNER ) : null;

		if ( ! $image ) {
			// Image externe collée à la main : on la sert telle quelle, en
			// prenant l'URL du HTML enregistré.
			if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', (string) ( $bloc['innerHTML'] ?? '' ), $m ) ) {
				$image = array(
					'url'    => $m[1],
					'width'  => 0,
					'height' => 0,
					'alt'    => '',
				);
			} else {
				return '';
			}
		}

		$alt = trim( (string) ( $attrs['alt'] ?? '' ) );
		if ( '' === $alt ) {
			$alt = (string) $image['alt'];
		}

		$largeur = (int) ( $image['width'] ?: Brand::WIDTH_CONTENT );
		$largeur = min( $largeur, Brand::WIDTH_CONTENT );

		// Largeur choisie dans l'éditeur (« 186px ») : elle était ignorée, l'image
		// s'étalait sur toute la largeur.
		$voulue = self::px( $attrs['width'] ?? null, Brand::WIDTH_CONTENT );
		if ( null !== $voulue && $voulue > 0 ) {
			$largeur = $voulue;
		}

		$align = in_array( $attrs['align'] ?? '', array( 'center', 'right' ), true ) ? $attrs['align'] : 'left';

		// Une image `display:block` plus étroite que sa cellule ne suit pas le
		// `text-align` de la cellule : l'alignement passe par ses marges.
		$marges = array(
			'left'   => 'margin:0;',
			'center' => 'margin:0 auto;',
			'right'  => 'margin:0 0 0 auto;',
		);

		$img = sprintf(
			'<img src="%s" alt="%s" width="%d" style="display:block;width:100%%;max-width:%dpx;height:auto;border:0;outline:none;text-decoration:none;%s" />',
			esc_url( (string) $image['url'] ),
			esc_attr( $alt ),
			$largeur,
			$largeur,
			$marges[ $align ]
		);

		$lien = (string) ( $attrs['href'] ?? '' );
		if ( '' !== $lien ) {
			$img = '<a href="' . esc_url( $lien ) . '" target="_blank" rel="noopener" style="text-decoration:none;">' . $img . '</a>';
		}

		// 12px d'espacement de base + 24px d'air au-dessus et 36px en dessous du
		// côté où il y a du contenu (retour d'Antoine : l'image manquait d'air
		// sous elle, surtout sur mobile, où la classe ajoute encore de la marge).
		$haut = $contenu_avant ? 36 : 12;
		$bas  = $contenu_apres ? 48 : 12;

		return Html::row(
			$img,
			array(
				'align'   => $align,
				'padding' => $haut . 'px ' . Brand::GUTTER . 'px ' . $bas . 'px',
				'class'   => 'wam-nl-row-image',
			)
		);
	}

	/**
	 * Colonnes, avec tables fantômes pour Outlook (§7.6.4).
	 *
	 * Ajout assumé à la liste de blocs du cahier des charges : c'est ce qui
	 * permet une mise en page d'e-mail digne de ce nom. Sur mobile, les colonnes
	 * s'empilent grâce à la classe `wam-nl-col`, dont la media query vit dans le
	 * <style> du gabarit (seul endroit où une media query est permise, §7.4).
	 *
	 * @param callable $rendre_blocs Rappel pour rendre les blocs enfants.
	 */
	public static function columns( array $bloc, callable $rendre_blocs ): string {
		$colonnes = array();
		foreach ( (array) ( $bloc['innerBlocks'] ?? array() ) as $colonne ) {
			if ( 'core/column' !== ( $colonne['blockName'] ?? '' ) ) {
				continue;
			}
			$colonnes[] = $colonne;
		}

		$nombre = count( $colonnes );
		if ( 0 === $nombre ) {
			return '';
		}
		if ( 1 === $nombre ) {
			return self::rendre_colonne( $rendre_blocs, (array) ( $colonnes[0]['innerBlocks'] ?? array() ) );
		}

		$dispo    = Brand::WIDTH_CONTENT;
		$gouttiere = 20;
		$utile    = $dispo - $gouttiere * ( $nombre - 1 );
		$largeurs = array();

		foreach ( $colonnes as $colonne ) {
			$part = (string) ( $colonne['attrs']['width'] ?? '' );
			if ( '' !== $part && false !== strpos( $part, '%' ) ) {
				$largeurs[] = (int) round( $utile * ( (float) $part / 100 ) );
			} else {
				$largeurs[] = (int) floor( $utile / $nombre );
			}
		}

		$cellules = '';
		foreach ( $colonnes as $index => $colonne ) {
			$contenu = self::rendre_colonne( $rendre_blocs, (array) ( $colonne['innerBlocks'] ?? array() ) );
			$largeur = $largeurs[ $index ];

			if ( $index > 0 ) {
				$cellules .= Html::mso_column_break( $largeur );
			}

			$cellules .= sprintf(
				'<div class="wam-nl-col" style="display:inline-block;vertical-align:top;width:100%%;max-width:%dpx;">'
				. '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%%" style="border-collapse:collapse;width:100%%;">'
				. '<tr><td style="vertical-align:top;">%s</td></tr></table></div>',
				$largeur,
				$contenu
			);

			// Gouttière entre colonnes, hors de la dernière.
			if ( $index < $nombre - 1 ) {
				$cellules .= '<div class="wam-nl-gutter" style="display:inline-block;width:' . (int) $gouttiere . 'px;font-size:0;line-height:0;">&nbsp;</div>';
			}
		}

		$interieur = Html::mso_columns_open( $largeurs ) . $cellules . Html::mso_columns_close();

		return Html::row( '<div style="font-size:0;">' . $interieur . '</div>' );
	}

	/** Rend le contenu d'une colonne en le signalant à image() (marge basse). */
	private static function rendre_colonne( callable $rendre_blocs, array $blocs ): string {
		++self::$dans_colonne;
		try {
			return (string) $rendre_blocs( $blocs );
		} finally {
			--self::$dans_colonne;
		}
	}

	/**
	 * Taille de police demandée par l'éditrice, bornée.
	 *
	 * L'éditeur peut produire `fontSize` (slug de la palette) ou
	 * `style.typography.fontSize` (valeur libre) : les deux sont lues.
	 */
	private static function font_size( array $attrs, int $defaut ): int {
		$libre = (string) ( $attrs['style']['typography']['fontSize'] ?? '' );
		if ( '' !== $libre && preg_match( '/^(\d+)/', $libre, $m ) ) {
			return max( 10, min( 48, (int) $m[1] ) );
		}

		$slug = (string) ( $attrs['fontSize'] ?? '' );
		if ( '' !== $slug ) {
			foreach ( \WamNewsletter\Editor\EmailTheme::FONT_SIZES as $taille ) {
				if ( $taille['slug'] === $slug && preg_match( '/^(\d+)/', $taille['size'], $m ) ) {
					return (int) $m[1];
				}
			}
		}

		return $defaut;
	}

	/**
	 * Couleur demandée par l'éditrice, résolue en hexadécimal.
	 *
	 * Gutenberg stocke soit une valeur libre (`#hex`, `var:preset|color|x`), soit
	 * un slug de palette. Les deux arrivent ici.
	 */
	private static function color_value( string $libre, string $slug ): string {
		$hex = Brand::to_hex( $libre );
		if ( '' !== $hex ) {
			return $hex;
		}
		if ( '' !== $libre ) {
			$hex = Brand::color_from_slug( $libre );
			if ( '' !== $hex ) {
				return $hex;
			}
		}
		return '' !== $slug ? Brand::color_from_slug( $slug ) : '';
	}

	/** Couleur de texte demandée par l'éditrice, résolue en hexadécimal. */
	private static function text_color( array $attrs, string $defaut = '' ): string {
		if ( '' === $defaut ) {
			$defaut = Brand::color( 'text' );
		}
		$couleur = self::color_value( (string) ( $attrs['style']['color']['text'] ?? '' ), (string) ( $attrs['textColor'] ?? '' ) );
		return '' !== $couleur ? $couleur : $defaut;
	}

	/** Couleur des liens choisie pour le bloc (« Lien » dans le panneau Couleur), ou ''. */
	private static function link_color( array $attrs ): string {
		return self::color_value( (string) ( $attrs['style']['elements']['link']['color']['text'] ?? '' ), '' );
	}

	/** Couleur de fond demandée, ou '' si le bloc n'en a pas. */
	private static function background_of( array $attrs ): string {
		return self::color_value( (string) ( $attrs['style']['color']['background'] ?? '' ), (string) ( $attrs['backgroundColor'] ?? '' ) );
	}

	/** Longueur CSS en pixels (28px, 1.5rem, 12), ou null si elle n'est pas exploitable. */
	private static function px( $valeur, int $max ): ?int {
		if ( is_int( $valeur ) || is_float( $valeur ) ) {
			return max( 0, min( $max, (int) round( $valeur ) ) );
		}
		if ( is_string( $valeur ) && preg_match( '/^(\d+(?:\.\d+)?)\s*(px|rem|em)?$/i', trim( $valeur ), $m ) ) {
			$n = (float) $m[1];
			if ( ! empty( $m[2] ) && in_array( strtolower( $m[2] ), array( 'rem', 'em' ), true ) ) {
				$n *= 16;
			}
			return max( 0, min( $max, (int) round( $n ) ) );
		}
		return null;
	}

	/** Rayon de bordure : une valeur, ou les quatre coins (topLeft, topRight…). */
	private static function radius_css( $rayon ): string {
		if ( is_array( $rayon ) ) {
			$coins = array(
				self::px( $rayon['topLeft'] ?? 0, 100 ) ?? 0,
				self::px( $rayon['topRight'] ?? 0, 100 ) ?? 0,
				self::px( $rayon['bottomRight'] ?? 0, 100 ) ?? 0,
				self::px( $rayon['bottomLeft'] ?? 0, 100 ) ?? 0,
			);
			return array_sum( $coins ) > 0 ? implode( 'px ', $coins ) . 'px' : '';
		}
		$n = self::px( $rayon, 100 ) ?? 0;
		return $n > 0 ? $n . 'px' : '';
	}

	/** Espacement intérieur d'un bloc à fond ou à bordure (14px 20px par défaut). */
	private static function padding_css( $padding ): string {
		if ( is_array( $padding ) ) {
			return implode(
				'px ',
				array(
					self::px( $padding['top'] ?? null, 80 ) ?? 14,
					self::px( $padding['right'] ?? null, 80 ) ?? 20,
					self::px( $padding['bottom'] ?? null, 80 ) ?? 14,
					self::px( $padding['left'] ?? null, 80 ) ?? 20,
				)
			) . 'px';
		}
		$n = self::px( $padding, 80 );
		return null !== $n && $n > 0 ? $n . 'px' : '14px 20px';
	}

	/**
	 * Enveloppe un texte dans une boîte quand le bloc a un fond, une bordure ou un
	 * arrondi ; sinon rend la rangée ordinaire.
	 *
	 * Une table imbriquée en `border-collapse:separate` : c'est la condition pour
	 * que `border-radius` s'applique à une cellule. Outlook de bureau ignore
	 * l'arrondi (il garde le fond et la bordure), les autres clients le rendent.
	 * La rangée extérieure prend plus d'air que la normale, encore augmenté sur
	 * mobile par la classe `wam-nl-row-box`.
	 */
	private static function boxed_row( string $interieur, array $attrs, string $couleur_texte ): string {
		$fond     = self::background_of( $attrs );
		$bordure  = (array) ( $attrs['style']['border'] ?? array() );
		$largeur  = is_array( $bordure['width'] ?? null )
			? ( self::px( $bordure['width']['top'] ?? 0, 20 ) ?? 0 )
			: ( self::px( $bordure['width'] ?? 0, 20 ) ?? 0 );
		$rayon    = self::radius_css( $bordure['radius'] ?? null );

		if ( '' === $fond && 0 === $largeur && '' === $rayon ) {
			return Html::row( $interieur );
		}

		$couleur_bordure = self::color_value( (string) ( $bordure['color'] ?? '' ), (string) ( $attrs['borderColor'] ?? '' ) );
		if ( '' === $couleur_bordure ) {
			$couleur_bordure = $couleur_texte;
		}

		$style = 'padding:' . self::padding_css( $attrs['style']['spacing']['padding'] ?? null ) . ';';
		if ( '' !== $fond ) {
			$style .= 'background-color:' . $fond . ';';
		}
		if ( $largeur > 0 ) {
			$style .= 'border:' . $largeur . 'px solid ' . $couleur_bordure . ';';
		}
		if ( '' !== $rayon ) {
			$style .= 'border-radius:' . $rayon . ';';
		}

		$boite = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;width:100%;">'
			. '<tr><td' . ( '' !== $fond ? ' bgcolor="' . esc_attr( $fond ) . '"' : '' ) . ' style="' . esc_attr( $style ) . '">'
			. $interieur
			. '</td></tr></table>';

		return Html::row(
			$boite,
			array(
				'padding' => '20px ' . Brand::GUTTER . 'px',
				'class'   => 'wam-nl-row-box',
			)
		);
	}
}
