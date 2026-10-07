<?php
namespace WamNewsletter\Subscribers;

use WamNewsletter\Lists\Repository as Lists;

defined( 'ABSPATH' ) || exit;

/**
 * Import et export CSV des abonné·es (§4).
 *
 * Deux détails qui font toute la différence sur un fichier produit par Excel en
 * France : le séparateur est souvent un point-virgule, et le fichier commence
 * par un BOM UTF-8 qui colle trois octets invisibles au premier en-tête. Les
 * deux sont gérés, sinon la colonne « email » n'est jamais reconnue.
 */
class Csv {

	/** Entêtes acceptés pour chaque champ, en minuscules sans accent. */
	const ALIASES = array(
		'email'      => array( 'email', 'e-mail', 'mail', 'adresse email', 'adresse e-mail', 'courriel' ),
		'first_name' => array( 'prenom', 'first_name', 'firstname', 'first name' ),
		'last_name'  => array( 'nom', 'last_name', 'lastname', 'last name', 'nom de famille' ),
	);

	/**
	 * Importe un fichier CSV.
	 *
	 * @param string $chemin Fichier lisible sur le disque.
	 * @param array  $opts   list_ids (int[]), consent_source (string), dry_run (bool)
	 * @return array{created:int,updated:int,skipped:int,invalid:int,total:int,errors:string[]}
	 */
	public static function import( string $chemin, array $opts = array() ): array {
		$rapport = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'invalid' => 0,
			'total'   => 0,
			'errors'  => array(),
		);

		if ( ! is_readable( $chemin ) ) {
			$rapport['errors'][] = __( 'Fichier illisible.', 'wam-newsletter' );
			return $rapport;
		}

		$handle = fopen( $chemin, 'r' );
		if ( ! $handle ) {
			$rapport['errors'][] = __( 'Fichier illisible.', 'wam-newsletter' );
			return $rapport;
		}

		$listes   = Lists::existing_ids( (array) ( $opts['list_ids'] ?? array() ) );
		$source   = in_array( $opts['consent_source'] ?? '', Repository::SOURCES, true ) ? $opts['consent_source'] : 'import';
		$a_blanc  = ! empty( $opts['dry_run'] );
		$vus      = array();
		$colonnes = null;
		$sep      = null;

		while ( false !== ( $brut = fgets( $handle ) ) ) {
			if ( null === $sep ) {
				$sep = self::detect_separator( $brut );
			}

			$ligne = str_getcsv( rtrim( $brut, "\r\n" ), $sep );
			if ( ! is_array( $ligne ) ) {
				continue;
			}

			if ( null === $colonnes ) {
				$colonnes = self::map_header( $ligne );
				if ( null === $colonnes ) {
					$rapport['errors'][] = __( 'Aucune colonne « email » trouvée dans la première ligne.', 'wam-newsletter' );
					fclose( $handle );
					return $rapport;
				}
				continue;
			}

			// Ligne vide : fgets rend "\n", str_getcsv rend [null].
			if ( 1 === count( $ligne ) && ( null === $ligne[0] || '' === trim( (string) $ligne[0] ) ) ) {
				continue;
			}

			++$rapport['total'];

			$email = Repository::normalize_email( $ligne[ $colonnes['email'] ] ?? '' );
			if ( '' === $email ) {
				++$rapport['invalid'];
				continue;
			}

			// Doublon interne au fichier : compté une fois, importé une fois.
			if ( isset( $vus[ $email ] ) ) {
				++$rapport['skipped'];
				continue;
			}
			$vus[ $email ] = true;

			if ( $a_blanc ) {
				$existant = Repository::find_by_email( $email );
				if ( ! $existant ) {
					++$rapport['created'];
				} else {
					++$rapport['updated'];
				}
				continue;
			}

			$resultat = Repository::upsert(
				array(
					'email'          => $email,
					'first_name'     => isset( $colonnes['first_name'] ) ? (string) ( $ligne[ $colonnes['first_name'] ] ?? '' ) : '',
					'last_name'      => isset( $colonnes['last_name'] ) ? (string) ( $ligne[ $colonnes['last_name'] ] ?? '' ) : '',
					'consent_source' => $source,
					'list_ids'       => $listes,
				)
			);

			if ( is_wp_error( $resultat ) ) {
				++$rapport['invalid'];
				continue;
			}

			if ( 'created' === $resultat['action'] ) {
				++$rapport['created'];
			} elseif ( 'updated' === $resultat['action'] ) {
				++$rapport['updated'];
			} else {
				++$rapport['skipped'];
			}
		}

		fclose( $handle );
		return $rapport;
	}

	/**
	 * Devine le séparateur sur la ligne d'en-tête.
	 *
	 * On compte les occurrences hors guillemets plutôt que de prendre le plus
	 * fréquent bêtement : un nom contenant une virgule fausserait le compte.
	 */
	private static function detect_separator( string $ligne ): string {
		$candidats = array( ';', ',', "\t" );
		$meilleur  = ',';
		$max       = 0;
		foreach ( $candidats as $sep ) {
			$n = count( str_getcsv( rtrim( $ligne, "\r\n" ), $sep ) );
			if ( $n > $max ) {
				$max      = $n;
				$meilleur = $sep;
			}
		}
		return $meilleur;
	}

	/**
	 * @return array<string,int>|null Champ => index de colonne, ou null sans e-mail.
	 */
	private static function map_header( array $ligne ): ?array {
		$map = array();
		foreach ( $ligne as $index => $cellule ) {
			$cle = self::normalize_header( (string) $cellule );
			foreach ( self::ALIASES as $champ => $alias ) {
				if ( in_array( $cle, $alias, true ) && ! isset( $map[ $champ ] ) ) {
					$map[ $champ ] = (int) $index;
				}
			}
		}
		return isset( $map['email'] ) ? $map : null;
	}

	/** Minuscules, sans BOM, sans accent, sans espaces superflus. */
	private static function normalize_header( string $valeur ): string {
		$valeur = preg_replace( '/^\xEF\xBB\xBF/', '', $valeur );
		$valeur = strtolower( trim( $valeur ) );
		$valeur = remove_accents( $valeur );
		return preg_replace( '/\s+/', ' ', $valeur );
	}

	/** @return string[] Ligne d'en-tête de l'export. */
	public static function export_header(): array {
		return array( 'email', 'prenom', 'nom', 'statut', 'listes', 'source', 'consentement', 'creation' );
	}

	/**
	 * Transforme une ligne d'abonné·e en ligne de CSV.
	 *
	 * @param array                $abonne
	 * @param array<int,string>    $listes Noms des listes de cet abonné·e.
	 * @return string[]
	 */
	public static function export_row( array $abonne, array $listes = array() ): array {
		return array(
			(string) $abonne['email'],
			(string) $abonne['first_name'],
			(string) $abonne['last_name'],
			(string) $abonne['status'],
			implode( ' | ', $listes ),
			(string) $abonne['consent_source'],
			(string) ( $abonne['consent_at'] ?? '' ),
			(string) ( $abonne['created_at'] ?? '' ),
		);
	}

	/**
	 * Construit le contenu CSV d'une vue filtrée.
	 *
	 * Parcours par pages de 500 : l'export complet porte sur ~1 850 lignes
	 * aujourd'hui, mais charger toute la table d'un coup finirait par saturer la
	 * mémoire d'un mutualisé le jour où elle grossit.
	 *
	 * @param array $args Mêmes filtres que Repository::query().
	 */
	public static function export( array $args = array() ): string {
		$flux = fopen( 'php://temp', 'r+' );

		// BOM UTF-8 : sans lui, Excel sous Windows affiche « PrÃ©nom ».
		fwrite( $flux, "\xEF\xBB\xBF" );
		fputcsv( $flux, self::export_header(), ';' );

		$page     = 1;
		$par_page = 500;
		do {
			$lignes = Repository::query(
				array_merge(
					$args,
					array(
						'per_page' => $par_page,
						'page'     => $page,
						'orderby'  => 'email',
						'order'    => 'asc',
					)
				)
			);
			if ( ! $lignes ) {
				break;
			}
			$listes = Repository::lists_for( wp_list_pluck( $lignes, 'id' ) );
			foreach ( $lignes as $ligne ) {
				fputcsv( $flux, self::export_row( $ligne, $listes[ (int) $ligne['id'] ] ?? array() ), ';' );
			}
			++$page;
		} while ( count( $lignes ) === $par_page );

		rewind( $flux );
		$contenu = (string) stream_get_contents( $flux );
		fclose( $flux );
		return $contenu;
	}

	public static function filename( string $prefixe = 'abonnes' ): string {
		return sprintf( 'wam-newsletter-%s-%s.csv', $prefixe, wp_date( 'Y-m-d' ) );
	}
}
