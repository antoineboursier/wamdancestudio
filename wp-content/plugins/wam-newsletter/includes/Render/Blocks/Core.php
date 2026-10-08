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
	public static function inline_text( string $html ): string {
		$html = wp_kses( $html, self::INLINE_TAGS );

		$style = 'color:' . Brand::color( 'text' ) . ';text-decoration:underline;';
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
		$contenu = self::inline_text( self::inner( $bloc ) );
		if ( '' === $contenu ) {
			return '';
		}

		$attrs = (array) ( $bloc['attrs'] ?? array() );
		$align = in_array( $attrs['align'] ?? '', array( 'center', 'right' ), true ) ? $attrs['align'] : 'left';

		$style = Html::text_style(
			array(
				'align' => $align,
				'size'  => self::font_size( $attrs, 16 ),
				'color' => self::text_color( $attrs ),
			)
		);

		return Html::row( '<p style="' . esc_attr( $style ) . '">' . $contenu . '</p>' );
	}

	/**
	 * Titre de section (§7.6.2 : jaune, 30 px, gras, centré).
	 *
	 * Les niveaux sont ramenés à h2 au maximum : la newsletter de référence
	 * contenait plusieurs h1, ce qui est un défaut relevé au §7.6.4. Le h1 est
	 * réservé à un éventuel titre unique en tête d'e-mail.
	 */
	public static function heading( array $bloc ): string {
		$contenu = self::inline_text( self::inner( $bloc ) );
		if ( '' === $contenu ) {
			return '';
		}

		$attrs  = (array) ( $bloc['attrs'] ?? array() );
		$niveau = (int) ( $attrs['level'] ?? 2 );
		$niveau = max( 2, min( 4, $niveau ) );

		$tailles = array(
			2 => 30,
			3 => 24,
			4 => 20,
		);
		$align = in_array( $attrs['textAlign'] ?? '', array( 'left', 'right' ), true ) ? $attrs['textAlign'] : 'center';

		$style = sprintf(
			'margin:0;font-family:%s;font-size:%dpx;line-height:1.25;font-weight:bold;color:%s;text-align:%s;',
			Brand::FONT_STACK,
			self::font_size( $attrs, $tailles[ $niveau ] ),
			self::text_color( $attrs, Brand::color( 'accent' ) ),
			$align
		);

		return Html::row( sprintf( '<h%1$d style="%2$s">%3$s</h%1$d>', $niveau, esc_attr( $style ), $contenu ) );
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
				$propre = self::inline_text( $texte );
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
	public static function image( array $bloc ): string {
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

		$img = sprintf(
			'<img src="%s" alt="%s" width="%d" style="display:block;width:100%%;max-width:%dpx;height:auto;border:0;outline:none;text-decoration:none;" />',
			esc_url( (string) $image['url'] ),
			esc_attr( $alt ),
			$largeur,
			$largeur
		);

		$lien = (string) ( $attrs['href'] ?? '' );
		if ( '' !== $lien ) {
			$img = '<a href="' . esc_url( $lien ) . '" target="_blank" rel="noopener" style="text-decoration:none;">' . $img . '</a>';
		}

		$align = in_array( $attrs['align'] ?? '', array( 'center', 'right' ), true ) ? $attrs['align'] : 'left';

		return Html::row( $img, array( 'align' => $align ) );
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
			return (string) $rendre_blocs( (array) ( $colonnes[0]['innerBlocks'] ?? array() ) );
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
			$contenu = (string) $rendre_blocs( (array) ( $colonne['innerBlocks'] ?? array() ) );
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

	/** Couleur de texte demandée par l'éditrice, résolue en hexadécimal. */
	private static function text_color( array $attrs, string $defaut = '' ): string {
		if ( '' === $defaut ) {
			$defaut = Brand::color( 'text' );
		}

		$libre = (string) ( $attrs['style']['color']['text'] ?? '' );
		$hex   = Brand::to_hex( $libre );
		if ( '' !== $hex ) {
			return $hex;
		}

		$slug = (string) ( $attrs['textColor'] ?? '' );
		if ( '' !== $slug ) {
			foreach ( Brand::palette() as $entree ) {
				if ( $entree['slug'] === $slug ) {
					return $entree['color'];
				}
			}
		}

		return $defaut;
	}
}
