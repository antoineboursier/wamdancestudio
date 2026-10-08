<?php
namespace WamNewsletter\Render;

use WamNewsletter\Render\Blocks\Core;
use WamNewsletter\Render\Blocks\Custom;
use WamNewsletter\Render\Blocks\Posts;

defined( 'ABSPATH' ) || exit;

/**
 * Aiguillage : un bloc analysé par `parse_blocks()` vers son renderer (§7.4).
 *
 * Un bloc inconnu rend une chaîne vide plutôt qu'une erreur : une newsletter
 * rédigée avant un changement de liste blanche doit continuer de partir, même si
 * un bloc n'est plus reconnu. Un e-mail amputé d'un bloc vaut mieux qu'un envoi
 * qui échoue sur 1 850 destinataires.
 */
class BlockRenderer {

	/**
	 * @param array $blocs Sortie de parse_blocks().
	 */
	public static function render_list( array $blocs ): string {
		$html = '';
		foreach ( $blocs as $bloc ) {
			$html .= self::render( (array) $bloc );
		}
		return $html;
	}

	public static function render( array $bloc ): string {
		$nom = (string) ( $bloc['blockName'] ?? '' );
		if ( '' === $nom ) {
			// Bloc « classique » (HTML libre entre deux blocs) : ignoré, son
			// contenu n'est le plus souvent qu'un saut de ligne.
			return '';
		}

		$attrs = (array) ( $bloc['attrs'] ?? array() );

		switch ( $nom ) {
			case 'wam-nl/header':
				return Custom::header( $attrs );

			case 'wam-nl/footer':
				return Custom::footer( $attrs );

			case 'wam-nl/button':
				return Custom::button( $attrs );

			case 'wam-nl/separator':
				return Custom::separator( $attrs );

			case 'wam-nl/spacer':
				return Custom::spacer( $attrs );

			case 'wam-nl/posts':
				return Posts::render( $attrs );

			case 'core/paragraph':
				return Core::paragraph( $bloc );

			case 'core/heading':
				return Core::heading( $bloc );

			case 'core/list':
				return Core::list_block( $bloc );

			case 'core/image':
				return Core::image( $bloc );

			case 'core/columns':
				return Core::columns( $bloc, array( self::class, 'render_list' ) );

			case 'core/column':
				// Rendu par core/columns : une colonne isolée n'a pas de sens.
				return self::render_list( (array) ( $bloc['innerBlocks'] ?? array() ) );

			default:
				return '';
		}
	}

	/**
	 * Rend le contenu d'une newsletter, marqueurs d'aperçu résolus.
	 *
	 * Utilisé par l'aperçu et par `render_callback` des blocs : aucun lien de
	 * désinscription actif, aucun pixel d'ouverture.
	 */
	public static function preview( string $contenu ): string {
		$html = self::render_list( parse_blocks( $contenu ) );
		return Placeholders::replace( $html, Placeholders::preview_values() );
	}
}
