<?php
namespace WamNewsletter\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Découverte dynamique des champs ACF (§7.3, décision §14.1 n°1).
 *
 * Rien n'est codé en dur : les champs sont lus sur le site avec
 * `acf_get_field_groups()` / `acf_get_fields()`, et le rôle de chacun est stocké
 * dans l'option `wam_nl_content_map`. Quand les champs ACF évoluent, un bouton
 * « Rescanner » suffit — aucune modification de code.
 *
 * Deux pièges du site, tous deux vérifiés lors de la découverte du 07/10/2026 :
 *  - le CPT s'appelle **`stages`**, pas `stage` comme l'écrivait le cahier des
 *    charges ;
 *  - `date_stage` est **stocké en `Ymd`** mais retourné par ACF en `d/m/Y`. Le
 *    tri « à venir » compare donc la méta brute, jamais la valeur ACF.
 */
class ContentMap {

	const OPTION = 'wam_nl_content_map';

	/** Types de contenu proposés dans le bloc Contenus. */
	const POST_TYPES = array( 'post', 'cours', 'stages', 'page' );

	/** Rôles attribuables à un champ. */
	const ROLES = array( 'image', 'date', 'subtitle', 'price', 'excerpt' );

	/**
	 * Types ACF scalaires, donc sélectionnables (§7.3 : « pas de sur-couche »).
	 * Un répéteur ou un contenu flexible n'a pas de valeur unique à afficher :
	 * il apparaît grisé dans l'écran de réglages.
	 */
	const SCALAR_TYPES = array(
		'text',
		'textarea',
		'wysiwyg',
		'number',
		'range',
		'email',
		'url',
		'date_picker',
		'date_time_picker',
		'time_picker',
		'select',
		'radio',
		'button_group',
		'image',
	);

	/** Types acceptés pour chaque rôle. */
	const ROLE_TYPES = array(
		'image'    => array( 'image' ),
		'date'     => array( 'date_picker', 'date_time_picker', 'text' ),
		'subtitle' => array( 'text', 'textarea', 'select', 'radio' ),
		'price'    => array( 'number', 'range', 'text' ),
		'excerpt'  => array( 'textarea', 'wysiwyg', 'text' ),
	);

	public static function available(): bool {
		return function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' );
	}

	/** Types de contenu réellement présents sur le site. */
	public static function post_types(): array {
		return array_values( array_filter( self::POST_TYPES, 'post_type_exists' ) );
	}

	public static function post_type_label( string $post_type ): string {
		$objet = get_post_type_object( $post_type );
		return $objet ? (string) $objet->labels->name : $post_type;
	}

	public static function role_label( string $role ): string {
		$labels = array(
			'image'    => __( 'Image', 'wam-newsletter' ),
			'date'     => __( 'Date', 'wam-newsletter' ),
			'subtitle' => __( 'Sous-titre', 'wam-newsletter' ),
			'price'    => __( 'Prix', 'wam-newsletter' ),
			'excerpt'  => __( 'Extrait', 'wam-newsletter' ),
		);
		return $labels[ $role ] ?? $role;
	}

	/**
	 * Champs ACF d'un type de contenu, à plat.
	 *
	 * Les onglets, accordéons et messages sont des champs de mise en page ACF
	 * sans valeur : ils sont écartés, sinon l'écran de réglages se remplirait de
	 * lignes vides.
	 *
	 * @return array<int,array{name:string,label:string,type:string,selectable:bool}>
	 */
	public static function scan_fields( string $post_type ): array {
		if ( ! self::available() ) {
			return array();
		}

		$sortie = array();
		$vus    = array();

		foreach ( (array) \acf_get_field_groups( array( 'post_type' => $post_type ) ) as $groupe ) {
			foreach ( (array) \acf_get_fields( $groupe['key'] ) as $champ ) {
				$nom  = (string) ( $champ['name'] ?? '' );
				$type = (string) ( $champ['type'] ?? '' );

				if ( '' === $nom || in_array( $type, array( 'tab', 'accordion', 'message', 'clone' ), true ) ) {
					continue;
				}
				if ( isset( $vus[ $nom ] ) ) {
					continue;
				}
				$vus[ $nom ] = true;

				$sortie[] = array(
					'name'       => $nom,
					'label'      => (string) ( $champ['label'] ?? $nom ),
					'type'       => $type,
					'selectable' => in_array( $type, self::SCALAR_TYPES, true ),
				);
			}
		}

		return $sortie;
	}

	/**
	 * Valeur d'exemple, lue sur le contenu publié le plus récent (§7.3).
	 *
	 * C'est ce qui rend l'écran de réglages utilisable : voir « 30/07/2026 » en
	 * face de `date_stage` vaut mieux que de deviner à quoi sert un champ.
	 */
	public static function example_value( string $post_type, string $champ, int $post_id = 0 ): string {
		if ( ! $post_id ) {
			$post_id = self::latest_post_id( $post_type );
		}
		if ( ! $post_id ) {
			return '';
		}

		$valeur = function_exists( 'get_field' ) ? \get_field( $champ, $post_id ) : get_post_meta( $post_id, $champ, true );

		return self::stringify( $valeur );
	}

	/** Représentation courte et lisible d'une valeur ACF quelconque. */
	public static function stringify( $valeur ): string {
		if ( null === $valeur || false === $valeur || '' === $valeur ) {
			return '';
		}
		if ( is_bool( $valeur ) ) {
			return $valeur ? 'oui' : 'non';
		}
		if ( is_scalar( $valeur ) ) {
			$texte = trim( wp_strip_all_tags( (string) $valeur ) );
			return mb_strlen( $texte ) > 90 ? mb_substr( $texte, 0, 90 ) . '…' : $texte;
		}
		if ( is_array( $valeur ) ) {
			// Image ACF retournée en tableau.
			if ( isset( $valeur['url'] ) ) {
				return basename( (string) $valeur['url'] );
			}
			if ( isset( $valeur['label'] ) ) {
				return (string) $valeur['label'];
			}
			return sprintf( '[%d élément(s)]', count( $valeur ) );
		}
		if ( $valeur instanceof \WP_Post ) {
			return (string) get_the_title( $valeur );
		}
		return '[objet]';
	}

	public static function latest_post_id( string $post_type ): int {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Mapping par défaut proposé à la première analyse (§14.1 n°1).
	 *
	 * Déduit du nom et du type du champ : un champ `image` prend le rôle image,
	 * un `date_picker` le rôle date, un nom contenant « sous_titre » le rôle
	 * sous-titre, etc. Antoine ajuste ensuite dans l'écran — il n'y a pas de
	 * validation préalable à demander.
	 *
	 * @return array<string,string> rôle => nom de champ
	 */
	public static function suggest( string $post_type ): array {
		$champs     = self::scan_fields( $post_type );
		$suggestion = array();

		$indices = array(
			'image'    => array( 'photo', 'image', 'visuel', 'affiche' ),
			'date'     => array( 'date' ),
			'subtitle' => array( 'sous_titre', 'soustitre', 'sous-titre', 'accroche' ),
			'price'    => array( 'prix', 'tarif', 'price' ),
			'excerpt'  => array( 'description', 'resume', 'extrait', 'presentation' ),
		);

		foreach ( self::ROLES as $role ) {
			foreach ( $champs as $champ ) {
				if ( ! $champ['selectable'] || ! in_array( $champ['type'], self::ROLE_TYPES[ $role ], true ) ) {
					continue;
				}
				if ( in_array( $champ['name'], $suggestion, true ) ) {
					continue;
				}

				// Le type seul suffit pour l'image et la date : il n'y a
				// généralement qu'un champ de ce type par contenu.
				$par_type = ( 'image' === $role && 'image' === $champ['type'] )
					|| ( 'date' === $role && in_array( $champ['type'], array( 'date_picker', 'date_time_picker' ), true ) );

				$par_nom = false;
				foreach ( $indices[ $role ] as $indice ) {
					if ( false !== strpos( $champ['name'], $indice ) ) {
						$par_nom = true;
						break;
					}
				}

				if ( $par_type || $par_nom ) {
					$suggestion[ $role ] = $champ['name'];
					break;
				}
			}
		}

		return $suggestion;
	}

	/**
	 * Mapping enregistré uniquement, sans suggestion.
	 *
	 * @return array<string,array<string,string>> post_type => rôle => champ
	 */
	public static function stored_map(): array {
		$stocke = get_option( self::OPTION, array() );
		return is_array( $stocke ) ? $stocke : array();
	}

	/**
	 * Mapping effectif : l'enregistré, complété par les suggestions pour les
	 * types de contenu jamais analysés.
	 *
	 * Le repli est ce qui rend le plugin utilisable dès l'activation, sans
	 * passer d'abord par l'écran de réglages — le §14.1 demande un mapping par
	 * défaut pré-rempli, pas une case à cocher avant de pouvoir écrire un e-mail.
	 * Un choix enregistré prime toujours sur la suggestion.
	 *
	 * @return array<string,array<string,string>> post_type => rôle => champ
	 */
	public static function get_map(): array {
		$stocke = self::stored_map();

		if ( ! self::available() ) {
			return $stocke;
		}

		foreach ( self::post_types() as $post_type ) {
			if ( ! isset( $stocke[ $post_type ] ) ) {
				$stocke[ $post_type ] = self::suggest( $post_type );
			}
		}

		return $stocke;
	}

	public static function save_map( array $map ): void {
		$propre = array();
		foreach ( $map as $post_type => $roles ) {
			if ( ! in_array( $post_type, self::POST_TYPES, true ) ) {
				continue;
			}
			$champs_connus = wp_list_pluck( self::scan_fields( $post_type ), 'name' );
			foreach ( (array) $roles as $role => $champ ) {
				if ( ! in_array( $role, self::ROLES, true ) ) {
					continue;
				}
				$champ = sanitize_key( (string) $champ );
				if ( '' === $champ ) {
					continue;
				}
				// Un champ inconnu n'est pas enregistré : ça évite qu'un POST
				// bricolé fasse lire une méta arbitraire au renderer.
				if ( ! in_array( $champ, $champs_connus, true ) ) {
					continue;
				}
				$propre[ $post_type ][ $role ] = $champ;
			}
		}
		update_option( self::OPTION, $propre );
	}

	/** Analyse et enregistre les suggestions pour tous les types de contenu. */
	public static function rescan(): array {
		$map = self::stored_map();
		foreach ( self::post_types() as $post_type ) {
			$suggestion = self::suggest( $post_type );
			// Les choix déjà faits priment sur la suggestion : un rescan ne doit
			// pas défaire un réglage.
			$map[ $post_type ] = array_merge( $suggestion, (array) ( $map[ $post_type ] ?? array() ) );
		}
		self::save_map( $map );
		return self::get_map();
	}

	/**
	 * Nom du champ portant un rôle, ou '' si aucun.
	 *
	 * Si le champ mappé a disparu des groupes ACF, on renvoie '' : le renderer
	 * retombe alors sur le titre, l'image à la une et l'extrait WordPress, sans
	 * erreur ni e-mail cassé (§7.3).
	 */
	public static function field( string $post_type, string $role ): string {
		$map   = self::get_map();
		$champ = (string) ( $map[ $post_type ][ $role ] ?? '' );
		if ( '' === $champ ) {
			return '';
		}
		if ( ! self::available() ) {
			return $champ;
		}
		$connus = wp_list_pluck( self::scan_fields( $post_type ), 'name' );
		return in_array( $champ, $connus, true ) ? $champ : '';
	}

	/**
	 * Champs mappés mais introuvables, pour l'avertissement de l'admin (§7.3).
	 *
	 * @return array<int,array{post_type:string,role:string,field:string}>
	 */
	public static function missing_fields(): array {
		if ( ! self::available() ) {
			return array();
		}
		$manquants = array();
		foreach ( self::stored_map() as $post_type => $roles ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$connus = wp_list_pluck( self::scan_fields( $post_type ), 'name' );
			foreach ( (array) $roles as $role => $champ ) {
				if ( '' !== $champ && ! in_array( $champ, $connus, true ) ) {
					$manquants[] = array(
						'post_type' => $post_type,
						'role'      => (string) $role,
						'field'     => (string) $champ,
					);
				}
			}
		}
		return $manquants;
	}

	/**
	 * Format de stockage du champ de date d'un type de contenu (§7.3, §14.1 n°2).
	 *
	 * Détecté sur les valeurs réellement en base, pas supposé : `date_stage` est
	 * un `date_picker` stocké en `Ymd` alors qu'ACF le retourne en `d/m/Y`.
	 * Comparer la valeur ACF trierait n'importe comment.
	 *
	 * @return string 'Ymd' | 'Y-m-d' | 'Y-m-d H:i:s' | '' si indéterminable.
	 */
	public static function date_format( string $post_type ): string {
		$champ = self::field( $post_type, 'date' );
		if ( '' === $champ ) {
			return '';
		}

		global $wpdb;
		$valeurs = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
				 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'
				 AND pm.meta_value <> '' LIMIT 20",
				$champ,
				$post_type
			)
		);

		foreach ( $valeurs as $valeur ) {
			$valeur = trim( (string) $valeur );
			if ( preg_match( '/^\d{8}$/', $valeur ) ) {
				return 'Ymd';
			}
			if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valeur ) ) {
				return 'Y-m-d H:i:s';
			}
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valeur ) ) {
				return 'Y-m-d';
			}
		}

		return '';
	}

	/**
	 * Vrai si le type de contenu peut être trié « à venir ».
	 *
	 * Les cours n'ont AUCUN champ date (relevé du 07/10/2026) : proposer le tri
	 * pour eux donnerait une liste vide et passerait pour un bug.
	 */
	public static function supports_upcoming( string $post_type ): bool {
		return '' !== self::date_format( $post_type );
	}
}
