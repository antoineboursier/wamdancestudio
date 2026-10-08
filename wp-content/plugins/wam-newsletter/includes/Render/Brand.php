<?php
namespace WamNewsletter\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Point de résolution UNIQUE de l'identité visuelle (§7.6.1).
 *
 * Les couleurs viennent du `theme.json` du thème `wamV1`, pas d'une liste écrite
 * à la main : si la charte bouge, les e-mails suivent. Les e-mails ne
 * comprenant pas les variables CSS, chaque rôle est résolu ici en hexadécimal.
 *
 * Aucun hexadécimal ne doit apparaître ailleurs dans le plugin. Les valeurs de
 * la table FALLBACK ne servent que si le thème est absent (plugin actif sur un
 * autre thème) ou si un slug a disparu — elles sont reprises de l'e-mail
 * MailPoet de référence.
 *
 * L'e-mail est volontairement en mode sombre : c'est le mode natif du thème
 * (`:root` de tokens.css), `html.wam-theme-light` n'étant que la variante claire.
 */
class Brand {

	/** Rôle => slug de palette du thème. */
	const SLUGS = array(
		'background' => 'background-800',
		'surface'    => 'background-600',
		'text'       => 'text-normal',
		'accent'     => 'accent-yellow',
		'separator'  => 'accent-green',
		'muted'      => 'text-disabled',
	);

	/** Valeurs de secours, issues de l'e-mail de référence (§7.6.1). */
	const FALLBACK = array(
		'background' => '#131620',
		'surface'    => '#1A1D28',
		'text'       => '#F9F4EB',
		'accent'     => '#FBD150',
		'separator'  => '#00D6B2',
		'muted'      => '#8D8D8D',
	);

	/** Typographie : standard web uniquement (§7.6.5), aucune police du thème. */
	const FONT_STACK = "Arial, Helvetica, sans-serif";

	/** Largeurs du gabarit (§7.4). */
	const WIDTH_OUTER   = 660;
	const WIDTH_CONTENT = 620;
	const GUTTER        = 20;

	/**
	 * Base du slug de la bannière (§7.6.7).
	 *
	 * Volontairement sans suffixe : relevé du 08/10/2026, le fichier
	 * `email-banner-1.avif` est porté par une pièce jointe dont le slug est
	 * `email-banner-2` — WordPress numérote les slugs indépendamment des noms de
	 * fichiers. Chercher « email-banner-1 » ne trouvait donc rien.
	 */
	const BANNER_SLUG = 'email-banner';

	/** @var array<string,string>|null */
	private static ?array $cache = null;

	/**
	 * @return array<string,string> rôle => hexadécimal
	 */
	public static function colors(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$palette = self::theme_palette();
		$couleurs = array();

		foreach ( self::SLUGS as $role => $slug ) {
			$valeur = $palette[ $slug ] ?? '';
			$hex    = self::to_hex( $valeur );
			$couleurs[ $role ] = '' !== $hex ? $hex : self::FALLBACK[ $role ];
		}

		self::$cache = $couleurs;
		return $couleurs;
	}

	public static function color( string $role ): string {
		$couleurs = self::colors();
		return $couleurs[ $role ] ?? self::FALLBACK[ $role ] ?? '#000000';
	}

	/** Vide le cache. Utile aux tests et après un changement de thème. */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Palette du thème, par slug.
	 *
	 * @return array<string,string>
	 */
	public static function theme_palette(): array {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$sortie  = array();

		// wp_get_global_settings renvoie les origines (default, theme, custom) ;
		// « theme » puis « custom » doivent primer sur les couleurs du cœur.
		foreach ( array( 'default', 'theme', 'custom' ) as $origine ) {
			foreach ( (array) ( $palette[ $origine ] ?? array() ) as $entree ) {
				if ( isset( $entree['slug'], $entree['color'] ) ) {
					$sortie[ (string) $entree['slug'] ] = (string) $entree['color'];
				}
			}
		}

		// Palette à plat (certains thèmes), au cas où.
		if ( ! $sortie && isset( $palette[0] ) ) {
			foreach ( (array) $palette as $entree ) {
				if ( isset( $entree['slug'], $entree['color'] ) ) {
					$sortie[ (string) $entree['slug'] ] = (string) $entree['color'];
				}
			}
		}

		return $sortie;
	}

	/**
	 * Normalise une couleur CSS en hexadécimal à 6 chiffres.
	 *
	 * Les e-mails ne savent lire ni `var()`, ni `rgb()`, ni la notation à 3
	 * chiffres de façon fiable selon les clients : tout est ramené ici.
	 *
	 * @return string '' si la valeur n'est pas exploitable.
	 */
	public static function to_hex( string $valeur ): string {
		$valeur = trim( $valeur );
		if ( '' === $valeur ) {
			return '';
		}

		if ( preg_match( '/^#([0-9a-fA-F]{6})$/', $valeur, $m ) ) {
			return '#' . strtoupper( $m[1] );
		}

		// #abc -> #AABBCC
		if ( preg_match( '/^#([0-9a-fA-F]{3})$/', $valeur, $m ) ) {
			$c = $m[1];
			return '#' . strtoupper( $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2] );
		}

		// rgb() / rgba() : l'alpha est ignoré, un e-mail n'a pas de transparence fiable.
		if ( preg_match( '/^rgba?\(\s*([0-9.]+)[\s,]+([0-9.]+)[\s,]+([0-9.]+)/i', $valeur, $m ) ) {
			return sprintf( '#%02X%02X%02X', (int) $m[1], (int) $m[2], (int) $m[3] );
		}

		return '';
	}

	/**
	 * Couleur de texte lisible sur un fond donné (§7.1.1).
	 *
	 * Luminance relative WCAG plutôt qu'une moyenne des canaux : le jaune
	 * d'accent (#FBD150) est clair pour l'œil mais sa moyenne RVB le ferait
	 * passer pour moyen, et le texte blanc dessus serait illisible.
	 */
	public static function contrast_color( string $fond ): string {
		$hex = self::to_hex( $fond );
		if ( '' === $hex ) {
			return self::color( 'text' );
		}

		$canaux = array(
			hexdec( substr( $hex, 1, 2 ) ) / 255,
			hexdec( substr( $hex, 3, 2 ) ) / 255,
			hexdec( substr( $hex, 5, 2 ) ) / 255,
		);
		foreach ( $canaux as &$c ) {
			$c = ( $c <= 0.03928 ) ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];

		return $luminance > 0.45 ? self::color( 'background' ) : self::color( 'text' );
	}

	/** Palette proposée dans l'éditeur pour les boutons (§7.1.1). */
	public static function palette(): array {
		$couleurs = self::colors();
		return array(
			array(
				'slug'  => 'accent',
				'name'  => __( 'Jaune WAM', 'wam-newsletter' ),
				'color' => $couleurs['accent'],
			),
			array(
				'slug'  => 'separator',
				'name'  => __( 'Turquoise WAM', 'wam-newsletter' ),
				'color' => $couleurs['separator'],
			),
			array(
				'slug'  => 'text',
				'name'  => __( 'Crème', 'wam-newsletter' ),
				'color' => $couleurs['text'],
			),
			array(
				'slug'  => 'background',
				'name'  => __( 'Fond sombre', 'wam-newsletter' ),
				'color' => $couleurs['background'],
			),
			array(
				'slug'  => 'surface',
				'name'  => __( 'Fond sombre clair', 'wam-newsletter' ),
				'color' => $couleurs['surface'],
			),
			array(
				'slug'  => 'muted',
				'name'  => __( 'Gris discret', 'wam-newsletter' ),
				'color' => $couleurs['muted'],
			),
		);
	}

	public static function site_name(): string {
		$nom = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		return '' !== trim( $nom ) ? $nom : 'WAM Dance Studio';
	}

	/**
	 * Adresse postale affichée au pied de page (§7.6.2).
	 *
	 * Lue chez wam-custom-plugin si la fonction existe (lecture seule), sinon la
	 * valeur de l'e-mail de référence. ⚠️ `wam_adresse_lieu()` peut renvoyer une
	 * chaîne vide : tester function_exists ne suffit pas, c'est le piège relevé
	 * sur `wam_nom_lieu()` en v1.6.1.
	 */
	public static function address(): string {
		if ( function_exists( 'wam_adresse_lieu' ) ) {
			$adresse = trim( (string) \wam_adresse_lieu() );
			if ( '' !== $adresse ) {
				return $adresse;
			}
		}
		return '202 rue Jean Jaurès à Villeneuve d’Ascq';
	}

	public static function site_host(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? preg_replace( '/^www\./', '', $host ) : 'wamdancestudio.fr';
	}

	/**
	 * Pièce jointe de la bannière par défaut (§7.6.7).
	 *
	 * Retrouvée par son slug plutôt que par un identifiant codé en dur : le
	 * relevé de découverte n'avait pas pu lire l'identifiant, et une base
	 * restaurée ailleurs ne porterait pas le même.
	 */
	public static function banner_id(): int {
		$reglage = (int) \WamNewsletter\Settings\Settings::get( 'banner_attachment_id' );
		if ( $reglage > 0 && 'attachment' === get_post_type( $reglage ) ) {
			return $reglage;
		}

		global $wpdb;
		$comme = $wpdb->esc_like( self::BANNER_SLUG ) . '%';

		// Par slug d'abord, la plus récente : le site en compte deux
		// (`email-banner` et `email-banner-2`), la dernière téléversée est la
		// bonne.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name LIKE %s ORDER BY ID DESC LIMIT 1",
				$comme
			)
		);
		if ( $id > 0 ) {
			return $id;
		}

		// Repli par nom de fichier : un slug renommé à la main ne doit pas
		// faire disparaître la bannière de l'entête.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
				'%' . $comme
			)
		);
	}
}
