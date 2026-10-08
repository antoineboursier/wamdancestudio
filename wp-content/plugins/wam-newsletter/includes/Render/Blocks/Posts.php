<?php
namespace WamNewsletter\Render\Blocks;

use WamNewsletter\Integrations\ContentMap;
use WamNewsletter\Render\Brand;
use WamNewsletter\Render\Html;
use WamNewsletter\Render\Images;

defined( 'ABSPATH' ) || exit;

/**
 * Bloc « Contenus WAM » (wam-nl/posts) — §7.3 et §7.6.3.
 *
 * Rendu de référence par contenu :
 *   séparateur pointillé turquoise
 *   titre pleine largeur, centré, style titre de section
 *   deux colonnes de 330 px : image (290 px, cliquable) | extrait + bouton
 *   alternance zigzag d'un contenu à l'autre
 *   empilement sur mobile, image TOUJOURS au-dessus du texte
 *
 * L'alternance et l'empilement sont obtenus avec `dir="rtl"` sur la rangée et
 * `dir="ltr"` sur chaque colonne : l'ordre du source reste « image puis texte »,
 * seul l'affichage s'inverse. C'est ce qui permet d'avoir l'image à droite en
 * desktop ET au-dessus en mobile — un simple échange des colonnes dans le source
 * mettrait le texte en premier sur mobile un item sur deux.
 */
class Posts {

	const COL_WIDTH   = 300;
	const IMAGE_WIDTH = 290;

	/** Valeurs par défaut des attributs du bloc. */
	public static function defaults(): array {
		return array(
			'postType'      => 'stages',
			'mode'          => 'auto',
			'count'         => 3,
			'order'         => 'recent',
			'taxonomy'      => '',
			'term'          => 0,
			'postIds'       => array(),
			'showImage'     => true,
			'showExcerpt'   => true,
			'showDate'      => true,
			'showButton'    => true,
			'alternate'     => true,
			'buttonText'    => '',
			'buttonVariant' => 'plein',
			'buttonColor'   => '',
		);
	}

	/** Libellé par défaut du bouton, adapté au type de contenu. */
	public static function default_button_text( string $post_type ): string {
		switch ( $post_type ) {
			case 'stages':
				return __( 'Voir le stage', 'wam-newsletter' );
			case 'cours':
				return __( 'Voir le cours', 'wam-newsletter' );
			case 'page':
				return __( 'Voir la page', 'wam-newsletter' );
			default:
				return __( 'Lire l’article complet', 'wam-newsletter' );
		}
	}

	/**
	 * Contenus à afficher.
	 *
	 * @return int[] Identifiants de contenus, dans l'ordre d'affichage.
	 */
	public static function query( array $attrs ): array {
		$a         = array_merge( self::defaults(), $attrs );
		$post_type = (string) $a['postType'];

		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		if ( 'manual' === $a['mode'] ) {
			$ids = array_values( array_filter( array_map( 'intval', (array) $a['postIds'] ) ) );
			if ( ! $ids ) {
				return array();
			}
			// On vérifie l'existence et le statut, sans réordonner : l'ordre
			// choisi à la main est l'information.
			$trouves = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'publish',
					'post__in'         => $ids,
					'orderby'          => 'post__in',
					'numberposts'      => count( $ids ),
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);
			return array_map( 'intval', (array) $trouves );
		}

		$nombre = max( 1, min( 12, (int) $a['count'] ) );

		$args = array(
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'numberposts'      => $nombre,
			'fields'           => 'ids',
			'suppress_filters' => true,
		);

		if ( ! empty( $a['taxonomy'] ) && ! empty( $a['term'] ) && taxonomy_exists( (string) $a['taxonomy'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => (string) $a['taxonomy'],
					'field'    => 'term_id',
					'terms'    => (int) $a['term'],
				),
			);
		}

		if ( 'upcoming' === $a['order'] ) {
			$champ  = ContentMap::field( $post_type, 'date' );
			$format = ContentMap::date_format( $post_type );

			if ( '' !== $champ && '' !== $format ) {
				// Comparaison sur la méta BRUTE : `date_stage` est stocké en Ymd
				// alors qu'ACF le retourne en d/m/Y. Les deux formats connus
				// (Ymd et Y-m-d) se comparent correctement en chaîne.
				$aujourdhui = current_time( 'Ymd' === $format ? 'Ymd' : 'Y-m-d' );

				$args['meta_query'] = array(
					array(
						'key'     => $champ,
						'value'   => $aujourdhui,
						'compare' => '>=',
						'type'    => 'CHAR',
					),
				);
				$args['meta_key'] = $champ;
				$args['orderby']  = 'meta_value';
				$args['order']    = 'ASC';
			} else {
				// Aucun champ date exploitable (cas des cours) : on retombe sur
				// les plus récents plutôt que de rendre une liste vide.
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
			}
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		return array_map( 'intval', (array) get_posts( $args ) );
	}

	/** Rendu complet du bloc. */
	public static function render( array $attrs = array() ): string {
		$a   = array_merge( self::defaults(), $attrs );
		$ids = self::query( $a );

		if ( ! $ids ) {
			return Html::row(
				'<p style="' . esc_attr( Html::text_style( array( 'color' => Brand::color( 'muted' ), 'align' => 'center', 'size' => 13 ) ) ) . '">'
				. esc_html( self::empty_message( $a ) )
				. '</p>',
				array( 'align' => 'center' )
			);
		}

		$html = '';
		foreach ( $ids as $index => $id ) {
			$inverse = ! empty( $a['alternate'] ) && 1 === $index % 2;
			$html   .= self::item( (int) $id, $a, $inverse );
		}
		return $html;
	}

	/**
	 * Message affiché quand la requête ne ramène rien.
	 *
	 * Dit POURQUOI, et quoi faire. « Aucun contenu à afficher » laisserait
	 * croire à une panne : le cas le plus fréquent sur ce site est un tri « les
	 * prochains » alors que tous les stages publiés sont déjà passés, et la
	 * personne qui rédige doit pouvoir le comprendre seule.
	 */
	public static function empty_message( array $a ): string {
		$post_type = (string) $a['postType'];
		$libelle   = strtolower( ContentMap::post_type_label( $post_type ) );

		if ( 'manual' === ( $a['mode'] ?? 'auto' ) ) {
			return __( 'Aucun contenu choisi : ouvrez les réglages du bloc, à droite, et sélectionnez-en.', 'wam-newsletter' );
		}

		if ( ! post_type_exists( $post_type ) ) {
			return __( 'Ce type de contenu n’existe plus sur le site. Choisissez-en un autre dans les réglages du bloc.', 'wam-newsletter' );
		}

		$publies = (int) wp_count_posts( $post_type )->publish;

		if ( 0 === $publies ) {
			/* translators: %s nom du type de contenu au pluriel */
			return sprintf( __( 'Aucun contenu publié dans « %s » pour le moment.', 'wam-newsletter' ), $libelle );
		}

		if ( 'upcoming' === ( $a['order'] ?? 'recent' ) ) {
			if ( ! ContentMap::supports_upcoming( $post_type ) ) {
				/* translators: %s nom du type de contenu au pluriel */
				return sprintf(
					__( 'Les contenus « %s » n’ont pas de date : choisissez « Les plus récents » dans les réglages du bloc.', 'wam-newsletter' ),
					$libelle
				);
			}
			/* translators: %s nom du type de contenu au pluriel */
			return sprintf(
				__( 'Aucun contenu « %s » à venir : tous ceux qui sont publiés ont déjà eu lieu. Publiez-en un nouveau, ou choisissez « Les plus récents ».', 'wam-newsletter' ),
				$libelle
			);
		}

		if ( ! empty( $a['term'] ) ) {
			return __( 'Aucun contenu dans cette catégorie. Essayez « Toutes les catégories ».', 'wam-newsletter' );
		}

		return __( 'Aucun contenu à afficher pour ce réglage.', 'wam-newsletter' );
	}

	/** Un contenu. */
	private static function item( int $id, array $a, bool $inverse ): string {
		$post_type = (string) $a['postType'];
		$titre     = trim( (string) get_the_title( $id ) );
		$lien      = (string) get_permalink( $id );
		$c         = Brand::colors();

		$html = Custom::separator();

		// --- Titre, pleine largeur, centré (§7.6.3 point 2) ---
		$style_titre = sprintf(
			'margin:0;font-family:%s;font-size:30px;line-height:1.25;font-weight:bold;color:%s;text-align:center;',
			Brand::FONT_STACK,
			$c['accent']
		);
		$titre_html = sprintf(
			'<a href="%s" target="_blank" rel="noopener" style="color:%s;text-decoration:none;">%s</a>',
			esc_url( $lien ),
			esc_attr( $c['accent'] ),
			esc_html( $titre )
		);
		$html .= Html::row( '<h2 style="' . esc_attr( $style_titre ) . '">' . $titre_html . '</h2>', array( 'align' => 'center' ) );

		// --- Sous-titre et date, s'ils existent ---
		$meta_lignes = array();

		$champ_sous_titre = ContentMap::field( $post_type, 'subtitle' );
		if ( '' !== $champ_sous_titre ) {
			$sous_titre = ContentMap::stringify( self::field_value( $champ_sous_titre, $id ) );
			if ( '' !== $sous_titre ) {
				$meta_lignes[] = esc_html( $sous_titre );
			}
		}

		if ( ! empty( $a['showDate'] ) ) {
			$date = self::formatted_date( $post_type, $id );
			if ( '' !== $date ) {
				$meta_lignes[] = esc_html( $date );
			}
		}

		if ( $meta_lignes ) {
			$style_meta = Html::text_style(
				array(
					'size'  => 14,
					'color' => $c['muted'],
					'align' => 'center',
				)
			);
			$html .= Html::row(
				'<p style="' . esc_attr( $style_meta ) . '">' . implode( ' &#183; ', $meta_lignes ) . '</p>',
				array(
					'align'   => 'center',
					'padding' => '0 ' . Brand::GUTTER . 'px 6px',
				)
			);
		}

		// --- Deux colonnes : image | texte ---
		$colonne_image = ! empty( $a['showImage'] ) ? self::image_column( $id, $post_type, $titre, $lien ) : '';
		$colonne_texte = self::text_column( $id, $post_type, $a, $lien );

		if ( '' === $colonne_image ) {
			// Sans image, le texte prend toute la largeur : une colonne vide
			// laisserait un trou de 300 px.
			return $html . Html::row( $colonne_texte );
		}

		$largeurs = array( self::COL_WIDTH, self::COL_WIDTH );
		$dir      = $inverse ? 'rtl' : 'ltr';

		$rangee  = '<div dir="' . esc_attr( $dir ) . '" style="font-size:0;">';
		$rangee .= Html::mso_columns_open( $largeurs );
		$rangee .= self::column_wrapper( $colonne_image, self::COL_WIDTH );
		$rangee .= Html::mso_column_break( self::COL_WIDTH );
		$rangee .= self::column_wrapper( $colonne_texte, self::COL_WIDTH );
		$rangee .= Html::mso_columns_close();
		$rangee .= '</div>';

		return $html . Html::row( $rangee );
	}

	/**
	 * Une colonne.
	 *
	 * `dir="ltr"` est reposé ici : la rangée peut être en rtl pour inverser
	 * l'ordre visuel, mais le texte lui-même doit rester lu de gauche à droite.
	 */
	private static function column_wrapper( string $contenu, int $largeur ): string {
		return sprintf(
			'<div class="wam-nl-col" dir="ltr" style="display:inline-block;vertical-align:top;width:100%%;max-width:%dpx;">'
			. '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%%" style="border-collapse:collapse;width:100%%;">'
			. '<tr><td style="vertical-align:top;padding:0 10px;">%s</td></tr></table></div>',
			$largeur,
			$contenu
		);
	}

	/**
	 * Colonne image.
	 *
	 * Recadrage uniforme par `wam_nl_thumb` (4:3) : la newsletter de référence
	 * mélangeait une photo portrait rendue en 191 px et des visuels paysage en
	 * 290 px, ce qui donnait des lignes bancales (§7.6.4).
	 */
	private static function image_column( int $id, string $post_type, string $titre, string $lien ): string {
		$attachment = 0;

		$champ_image = ContentMap::field( $post_type, 'image' );
		if ( '' !== $champ_image ) {
			$valeur = self::field_value( $champ_image, $id );
			if ( is_array( $valeur ) && isset( $valeur['ID'] ) ) {
				$attachment = (int) $valeur['ID'];
			} elseif ( is_numeric( $valeur ) ) {
				$attachment = (int) $valeur;
			}
		}

		// Repli sur l'image à la une : les stages n'ont pas de champ image ACF.
		if ( ! $attachment ) {
			$attachment = (int) get_post_thumbnail_id( $id );
		}
		if ( ! $attachment ) {
			return '';
		}

		$image = Images::email_image( $attachment, Images::SIZE_THUMB );
		if ( ! $image ) {
			return '';
		}

		// alt de la médiathèque, repli sur le titre du contenu (§7.6.4).
		$alt = '' !== trim( (string) $image['alt'] ) ? (string) $image['alt'] : $titre;

		$img = sprintf(
			'<img src="%s" alt="%s" width="%d" style="display:block;width:100%%;max-width:%dpx;height:auto;border:0;outline:none;text-decoration:none;border-radius:4px;" />',
			esc_url( (string) $image['url'] ),
			esc_attr( $alt ),
			self::IMAGE_WIDTH,
			self::IMAGE_WIDTH
		);

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener" style="text-decoration:none;">%s</a>',
			esc_url( $lien ),
			$img
		);
	}

	/** Colonne texte : extrait tronqué + bouton. */
	private static function text_column( int $id, string $post_type, array $a, string $lien ): string {
		$html = '';

		if ( ! empty( $a['showExcerpt'] ) ) {
			$extrait = self::excerpt( $id, $post_type );
			if ( '' !== $extrait ) {
				$html .= '<p style="' . esc_attr( Html::text_style() ) . '">' . esc_html( $extrait ) . '</p>';
			}
		}

		$champ_prix = ContentMap::field( $post_type, 'price' );
		if ( '' !== $champ_prix ) {
			$prix = ContentMap::stringify( self::field_value( $champ_prix, $id ) );
			if ( '' !== $prix ) {
				$html .= '<p style="' . esc_attr( Html::text_style( array( 'bold' => true ) ) ) . '">'
					. esc_html( sprintf( /* translators: %s montant */ __( 'Dès %s €', 'wam-newsletter' ), $prix ) )
					. '</p>';
			}
		}

		if ( ! empty( $a['showButton'] ) ) {
			$texte = trim( (string) $a['buttonText'] );
			if ( '' === $texte ) {
				$texte = self::default_button_text( $post_type );
			}
			$html .= '<div style="margin-top:12px;">' . Html::button(
				array(
					'texte'   => $texte,
					'url'     => $lien,
					'style'   => (string) $a['buttonVariant'],
					'couleur' => (string) $a['buttonColor'],
					'align'   => 'left',
				)
			) . '</div>';
		}

		return $html;
	}

	/**
	 * Extrait d'un contenu, ~300 caractères coupés au mot (§7.6.3).
	 *
	 * Ordre des sources : champ ACF portant le rôle « extrait », puis extrait
	 * WordPress saisi, puis contenu. Le repli garantit qu'un champ mappé disparu
	 * ne casse rien (§7.3).
	 */
	public static function excerpt( int $id, string $post_type ): string {
		$champ = ContentMap::field( $post_type, 'excerpt' );
		if ( '' !== $champ ) {
			$valeur = self::field_value( $champ, $id );
			if ( is_scalar( $valeur ) && '' !== trim( (string) $valeur ) ) {
				return Html::excerpt( (string) $valeur );
			}
		}

		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			return Html::excerpt( (string) $post->post_excerpt );
		}
		return Html::excerpt( (string) $post->post_content );
	}

	/**
	 * Date formatée pour l'affichage.
	 *
	 * Reconstruite depuis la méta BRUTE et son format détecté : la valeur
	 * retournée par ACF est en d/m/Y, inexploitable pour un `DateTime` sans
	 * connaître le format, et le piège a déjà coûté un bug dans le pied de page
	 * du thème.
	 */
	public static function formatted_date( string $post_type, int $id ): string {
		$champ = ContentMap::field( $post_type, 'date' );
		if ( '' === $champ ) {
			return '';
		}

		$brut = trim( (string) get_post_meta( $id, $champ, true ) );
		if ( '' === $brut ) {
			return '';
		}

		$format = ContentMap::date_format( $post_type );
		$dt     = null;

		if ( 'Ymd' === $format ) {
			$dt = \DateTimeImmutable::createFromFormat( 'Ymd', $brut, wp_timezone() );
		} elseif ( 'Y-m-d' === $format ) {
			$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $brut, wp_timezone() );
		} elseif ( 'Y-m-d H:i:s' === $format ) {
			$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $brut, wp_timezone() );
		}

		if ( ! $dt ) {
			$ts = strtotime( $brut );
			if ( ! $ts ) {
				return '';
			}
			return wp_date( 'j F Y', $ts );
		}

		return (string) wp_date( 'j F Y', $dt->getTimestamp() );
	}

	/** Lecture d'un champ ACF, avec repli sur la méta brute. */
	private static function field_value( string $champ, int $id ) {
		if ( function_exists( 'get_field' ) ) {
			return \get_field( $champ, $id );
		}
		return get_post_meta( $id, $champ, true );
	}
}
