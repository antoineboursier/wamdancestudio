<?php
namespace WamNewsletter\Render;

use WamNewsletter\Editor\NewsletterPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Assemble le document e-mail complet (§7.4, §7.6.1).
 *
 * Pipeline : `parse_blocks()` → un renderer par bloc → gabarit → HTML final.
 *
 * Deux choses sont volontairement absentes :
 *  - aucune librairie d'« inlining » CSS : les renderers écrivent les styles en
 *    ligne eux-mêmes, ce qui évite une dépendance et garde le HTML relisible ;
 *  - aucun `<link>` vers une police : les polices du thème (Outfit, Mallia…) ne
 *    sont pas chargées, le rendu reste identique partout (§7.6.5).
 *
 * Le seul `<style>` du document ne contient que des media queries, seul cas
 * autorisé par le §7.4 — c'est ce qui fait l'empilement des colonnes en mobile.
 */
class EmailRenderer {

	/**
	 * HTML complet d'une newsletter.
	 *
	 * @param int   $newsletter_id
	 * @param array $opts preview (bool), subject, preheader
	 */
	public static function render( int $newsletter_id, array $opts = array() ): string {
		$post = get_post( $newsletter_id );
		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$contenu = BlockRenderer::render_list( parse_blocks( (string) $post->post_content ) );

		$preheader = isset( $opts['preheader'] )
			? (string) $opts['preheader']
			: (string) get_post_meta( $newsletter_id, '_wam_nl_preheader', true );

		$html = self::document(
			$contenu,
			array(
				'title'     => isset( $opts['subject'] )
					? (string) $opts['subject']
					: (string) get_post_meta( $newsletter_id, '_wam_nl_subject', true ),
				'preheader' => $preheader,
			)
		);

		if ( ! empty( $opts['preview'] ) ) {
			$html = Placeholders::replace( $html, Placeholders::preview_values() );
		}

		return $html;
	}

	/**
	 * Enveloppe un contenu déjà rendu dans le gabarit.
	 *
	 * @param string $contenu HTML des blocs.
	 * @param array  $opts    title, preheader
	 */
	public static function document( string $contenu, array $opts = array() ): string {
		$c       = Brand::colors();
		$titre   = trim( (string) ( $opts['title'] ?? '' ) );
		if ( '' === $titre ) {
			$titre = Brand::site_name();
		}
		$preheader = trim( (string) ( $opts['preheader'] ?? '' ) );

		$gabarit = WAM_NL_DIR . 'templates/email/base.php';
		if ( ! is_readable( $gabarit ) ) {
			// Sans gabarit, on renvoie au moins un document valide plutôt que
			// rien : un envoi doit pouvoir partir même après un déploiement
			// partiel (le déploiement se fait par cp -r, qui ne supprime rien
			// mais peut arriver incomplet).
			return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"></head><body>' . $contenu . '</body></html>';
		}

		$donnees = array(
			'couleurs'   => $c,
			'police'     => Brand::FONT_STACK,
			'largeur'    => Brand::WIDTH_OUTER,
			'titre'      => $titre,
			'preheader'  => $preheader,
			'contenu'    => $contenu,
			'vue_url'    => Placeholders::VIEW_URL,
			'pixel'      => Placeholders::OPEN_PIXEL,
			'langue'     => self::language(),
		);

		ob_start();
		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile -- gabarit interne au plugin.
		( static function ( array $wam_nl ) use ( $gabarit ) {
			include $gabarit;
		} )( $donnees );

		return (string) ob_get_clean();
	}

	private static function language(): string {
		$locale = (string) get_locale();
		$court  = substr( str_replace( '_', '-', $locale ), 0, 5 );
		return '' !== $court ? $court : 'fr-FR';
	}

	/**
	 * Version texte brut, pour `AltBody` (§7.4).
	 *
	 * Un e-mail HTML sans alternative texte est pénalisé par les filtres
	 * anti-spam, et illisible dans les clients en mode texte. Produite depuis le
	 * HTML plutôt que depuis les blocs : on est sûr qu'elle dit la même chose.
	 */
	public static function plain_text( string $html ): string {
		// Le preheader est masqué visuellement mais présent dans le HTML : il
		// ferait doublon avec la première phrase en version texte. On retire tout
		// élément masqué, quelle que soit sa balise — le gabarit utilise un div,
		// et un td ne couvrirait pas le cas.
		$texte = (string) preg_replace( '#<(div|td|span|p)\\b[^>]*display:\\s*none[^>]*>.*?</\\1>#is', '', $html );

		// Les liens deviennent « libellé (url) » : en texte brut, un libellé
		// seul priverait de la destination.
		$texte = (string) preg_replace_callback(
			'#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
			static function ( $m ) {
				$url     = trim( $m[1] );
				$libelle = trim( wp_strip_all_tags( $m[2] ) );
				if ( '' === $libelle ) {
					return $url;
				}
				if ( $libelle === $url ) {
					return $url;
				}
				return $libelle . ' (' . $url . ')';
			},
			$texte
		);

		$texte = str_ireplace( array( '<br>', '<br/>', '<br />' ), "\n", $texte );
		$texte = (string) preg_replace( '#</(p|h1|h2|h3|h4|li|tr|table|div)>#i', "\n", $texte );
		$texte = wp_strip_all_tags( $texte );
		$texte = html_entity_decode( $texte, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Espaces insécables et tabulations ramenés à une espace simple.
		$texte = (string) preg_replace( '/[ \t\x{00A0}]+/u', ' ', $texte );
		$texte = (string) preg_replace( '/ *\n */', "\n", $texte );
		$texte = (string) preg_replace( '/\n{3,}/', "\n\n", $texte );

		return trim( $texte );
	}
}
