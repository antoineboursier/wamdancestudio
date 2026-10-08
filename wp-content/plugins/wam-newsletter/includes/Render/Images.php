<?php
namespace WamNewsletter\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Images pour l'e-mail : jamais d'AVIF ni de WebP (§7.6.4).
 *
 * ⚠️ Le thème wamV1 force TOUTES les tailles générées en AVIF
 * (`wamv1_output_image_formats` sur `image_editor_output_format`). C'est un bon
 * choix pour le site — et un défaut majeur dans un e-mail : Outlook desktop et
 * plusieurs clients n'affichent pas l'AVIF, les vignettes de la newsletter de
 * référence étaient donc invisibles pour une partie des destinataires.
 *
 * Ce fichier fait deux choses :
 *  1. il enregistre les tailles dédiées à l'e-mail ;
 *  2. il garantit une URL en JPG, en fabriquant un dérivé au besoin — sans
 *     jamais toucher à l'original ni à son usage sur le site.
 *
 * La neutralisation du filtre passe par un filtre à priorité maximale qui rend
 * un tableau vide, et non par un `remove_filter` sur la fonction du thème :
 * fonctionne quel que soit le nom de la fonction, et survit à son renommage.
 */
class Images {

	/** Vignette des contenus, recadrée en 4:3 (§7.6.4). */
	const SIZE_THUMB = 'wam_nl_thumb';

	/**
	 * Bannière d'entête.
	 *
	 * ⚠️ Le cahier des charges demandait 1240 px pour le retina. La source
	 * (`email-banner-1`) ne fait que 650 × 309 px : WordPress n'agrandit pas une
	 * image, cette taille ne produirait donc rien. On se cale sur la largeur de
	 * contenu réelle et on note la limite plutôt que d'enregistrer une taille
	 * qui n'existerait jamais sur le disque.
	 */
	const SIZE_BANNER = 'wam_nl_banner';

	/** Sous-dossier des dérivés, hors médiathèque pour rester purgeable. */
	const SUBDIR = 'wam-newsletter';

	const META_CACHE = '_wam_nl_jpeg';

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_sizes' ) );
	}

	public static function register_sizes(): void {
		add_image_size( self::SIZE_THUMB, 580, 435, true );
		add_image_size( self::SIZE_BANNER, Brand::WIDTH_CONTENT, 0, false );
	}

	/** Extensions que les clients de messagerie n'affichent pas de façon fiable. */
	private static function needs_conversion( string $url ): bool {
		$chemin = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext    = strtolower( (string) pathinfo( $chemin, PATHINFO_EXTENSION ) );
		return in_array( $ext, array( 'avif', 'webp' ), true );
	}

	/**
	 * Image prête pour l'e-mail.
	 *
	 * @param int    $attachment_id
	 * @param string $taille
	 * @return array{url:string,width:int,height:int,alt:string}|null
	 */
	public static function email_image( int $attachment_id, string $taille = self::SIZE_THUMB ): ?array {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$src = wp_get_attachment_image_src( $attachment_id, $taille );
		if ( ! $src || empty( $src[0] ) ) {
			return null;
		}

		$url     = (string) $src[0];
		$largeur = (int) ( $src[1] ?? 0 );
		$hauteur = (int) ( $src[2] ?? 0 );

		if ( self::needs_conversion( $url ) ) {
			$derive = self::derive_jpeg( $attachment_id, $taille );
			if ( $derive ) {
				$url     = $derive['url'];
				$largeur = $derive['width'];
				$hauteur = $derive['height'];
			}
		}

		return array(
			'url'    => $url,
			'width'  => $largeur,
			'height' => $hauteur,
			'alt'    => self::alt( $attachment_id ),
		);
	}

	/**
	 * Texte alternatif, avec repli sur le titre de la pièce jointe (§7.6.4).
	 *
	 * Un `alt` vide sur une image porteuse d'information est un défaut relevé sur
	 * la newsletter de référence ; les appelants peuvent fournir un meilleur
	 * repli (titre du contenu) via override_alt().
	 */
	public static function alt( int $attachment_id ): string {
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt ) {
			return $alt;
		}
		$titre = get_the_title( $attachment_id );
		return is_string( $titre ) ? trim( $titre ) : '';
	}

	/**
	 * Fabrique (ou retrouve) un dérivé JPG.
	 *
	 * @return array{url:string,path:string,width:int,height:int}|null
	 */
	public static function derive_jpeg( int $attachment_id, string $taille ) {
		$cache = (array) get_post_meta( $attachment_id, self::META_CACHE, true );
		if ( isset( $cache[ $taille ]['path'] ) && file_exists( $cache[ $taille ]['path'] ) ) {
			return $cache[ $taille ];
		}

		$source = get_attached_file( $attachment_id );
		if ( ! $source || ! file_exists( $source ) ) {
			return null;
		}

		$dimensions = self::size_dimensions( $taille );
		$dossier    = self::dir();
		if ( ! $dossier ) {
			return null;
		}

		// Filtre de conversion neutralisé le temps de l'opération : sans ça,
		// l'éditeur d'image du thème nous rendrait un AVIF alors qu'on demande
		// explicitement un JPG.
		$neutraliser = static function () {
			return array();
		};
		add_filter( 'image_editor_output_format', $neutraliser, PHP_INT_MAX );

		try {
			$editeur = wp_get_image_editor( $source );
			if ( is_wp_error( $editeur ) ) {
				return null;
			}

			if ( $dimensions['width'] || $dimensions['height'] ) {
				$editeur->resize( $dimensions['width'] ?: null, $dimensions['height'] ?: null, $dimensions['crop'] );
			}
			$editeur->set_quality( 82 );

			$nom     = sanitize_file_name( pathinfo( $source, PATHINFO_FILENAME ) );
			$cible   = $dossier['path'] . '/' . $nom . '-' . sanitize_key( $taille ) . '.jpg';
			$sauve   = $editeur->save( $cible, 'image/jpeg' );

			if ( is_wp_error( $sauve ) || empty( $sauve['path'] ) ) {
				return null;
			}
		} finally {
			remove_filter( 'image_editor_output_format', $neutraliser, PHP_INT_MAX );
		}

		$entree = array(
			'path'   => (string) $sauve['path'],
			'url'    => $dossier['url'] . '/' . basename( (string) $sauve['path'] ),
			'width'  => (int) ( $sauve['width'] ?? 0 ),
			'height' => (int) ( $sauve['height'] ?? 0 ),
		);

		$cache[ $taille ] = $entree;
		update_post_meta( $attachment_id, self::META_CACHE, $cache );

		return $entree;
	}

	/** @return array{width:int,height:int,crop:bool} */
	private static function size_dimensions( string $taille ): array {
		if ( self::SIZE_THUMB === $taille ) {
			return array(
				'width'  => 580,
				'height' => 435,
				'crop'   => true,
			);
		}
		if ( self::SIZE_BANNER === $taille ) {
			return array(
				'width'  => Brand::WIDTH_CONTENT,
				'height' => 0,
				'crop'   => false,
			);
		}

		$tailles = wp_get_registered_image_subsizes();
		if ( isset( $tailles[ $taille ] ) ) {
			return array(
				'width'  => (int) $tailles[ $taille ]['width'],
				'height' => (int) $tailles[ $taille ]['height'],
				'crop'   => ! empty( $tailles[ $taille ]['crop'] ),
			);
		}

		return array(
			'width'  => Brand::WIDTH_CONTENT,
			'height' => 0,
			'crop'   => false,
		);
	}

	/** @return array{path:string,url:string}|null */
	private static function dir(): ?array {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}
		$chemin = $uploads['basedir'] . '/' . self::SUBDIR;
		if ( ! file_exists( $chemin ) && ! wp_mkdir_p( $chemin ) ) {
			return null;
		}
		return array(
			'path' => $chemin,
			'url'  => $uploads['baseurl'] . '/' . self::SUBDIR,
		);
	}

	/**
	 * Supprime les dérivés d'une pièce jointe. Appelé si la bannière change.
	 */
	public static function purge( int $attachment_id ): void {
		$cache = (array) get_post_meta( $attachment_id, self::META_CACHE, true );
		foreach ( $cache as $entree ) {
			if ( ! empty( $entree['path'] ) && file_exists( $entree['path'] ) ) {
				wp_delete_file( $entree['path'] );
			}
		}
		delete_post_meta( $attachment_id, self::META_CACHE );
	}
}
