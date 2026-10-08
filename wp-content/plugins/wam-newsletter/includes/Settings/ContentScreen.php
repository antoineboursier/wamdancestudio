<?php
namespace WamNewsletter\Settings;

use WamNewsletter\Admin\Notices;
use WamNewsletter\Install;
use WamNewsletter\Integrations\ContentMap;

defined( 'ABSPATH' ) || exit;

/**
 * Onglet « Contenus » des réglages : mapping ACF (§7.3, §9).
 *
 * L'écran montre, pour chaque type de contenu, les champs ACF réellement
 * présents sur le site, **avec une valeur d'exemple** lue sur le contenu publié
 * le plus récent. C'est ce qui le rend utilisable : voir « 30/07/2026 » en face
 * de `date_stage` vaut mieux que de deviner à quoi sert un champ.
 *
 * Les champs complexes (répéteur, groupe, contenu flexible) apparaissent grisés
 * et non sélectionnables : ils n'ont pas de valeur unique à afficher dans un
 * e-mail, et le §7.3 interdit explicitement toute sur-couche pour les gérer.
 */
class ContentScreen {

	const NONCE = 'wam_nl_content_map';

	public static function register_hooks(): void {
		add_action( 'admin_post_wam_nl_save_content_map', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_wam_nl_rescan_fields', array( self::class, 'handle_rescan' ) );
	}

	public static function handle_save(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		check_admin_referer( self::NONCE );

		$brut = isset( $_POST['map'] ) ? (array) wp_unslash( $_POST['map'] ) : array();
		$map  = array();

		foreach ( $brut as $post_type => $roles ) {
			$post_type = sanitize_key( (string) $post_type );
			foreach ( (array) $roles as $role => $champ ) {
				$map[ $post_type ][ sanitize_key( (string) $role ) ] = sanitize_key( (string) $champ );
			}
		}

		ContentMap::save_map( $map );
		Notices::success( __( 'Correspondance des champs enregistrée.', 'wam-newsletter' ) );
		wp_safe_redirect( SettingsPage::url( 'contenus' ) );
		exit;
	}

	public static function handle_rescan(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		check_admin_referer( self::NONCE );

		ContentMap::rescan();
		Notices::success( __( 'Champs rescannés. Les correspondances déjà choisies ont été conservées.', 'wam-newsletter' ) );
		wp_safe_redirect( SettingsPage::url( 'contenus' ) );
		exit;
	}

	/** Contenu de l'onglet. */
	public static function render_tab(): void {
		Notices::render();

		if ( ! ContentMap::available() ) {
			echo '<div class="notice notice-warning inline"><p>';
			esc_html_e( 'ACF n’est pas actif : le bloc « Contenus WAM » retombera sur le titre, l’image à la une et l’extrait WordPress.', 'wam-newsletter' );
			echo '</p></div>';
			return;
		}

		$manquants = ContentMap::missing_fields();
		if ( $manquants ) {
			echo '<div class="notice notice-warning inline"><p>';
			esc_html_e( 'Des champs associés ont disparu des groupes ACF. Le rendu retombe sur le titre, l’image à la une et l’extrait WordPress, sans erreur. Rescannez puis corrigez :', 'wam-newsletter' );
			echo '</p><ul class="ul-disc" style="margin-left:2em">';
			foreach ( $manquants as $manquant ) {
				printf(
					'<li>%s — %s : <code>%s</code></li>',
					esc_html( ContentMap::post_type_label( $manquant['post_type'] ) ),
					esc_html( ContentMap::role_label( $manquant['role'] ) ),
					esc_html( $manquant['field'] )
				);
			}
			echo '</ul></div>';
		}

		echo '<p class="description">';
		esc_html_e(
			'Indiquez quel champ ACF joue quel rôle dans les e-mails. Les champs sont lus sur le site : si vous en ajoutez un, cliquez sur « Rescanner les champs » — aucune modification de code n’est nécessaire.',
			'wam-newsletter'
		);
		echo '</p>';

		$map = ContentMap::get_map();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="wam_nl_save_content_map" />';

		foreach ( ContentMap::post_types() as $post_type ) {
			$champs  = ContentMap::scan_fields( $post_type );
			$exemple = ContentMap::latest_post_id( $post_type );

			echo '<h2>' . esc_html( ContentMap::post_type_label( $post_type ) ) . '</h2>';

			if ( ! $champs ) {
				echo '<p class="description">' . esc_html__( 'Aucun champ ACF sur ce type de contenu.', 'wam-newsletter' ) . '</p>';
				continue;
			}

			if ( $exemple ) {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s titre du contenu */
							__( 'Valeurs d’exemple lues sur : %s', 'wam-newsletter' ),
							get_the_title( $exemple )
						)
					)
				);
			}

			// Le tri « à venir » dépend d'un champ date exploitable : on le dit
			// ici, sinon on croit à un bug quand la liste revient vide.
			$format = ContentMap::date_format( $post_type );
			if ( '' !== $format ) {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s format de stockage */
							__( 'Tri « les prochains » disponible (date stockée au format %s).', 'wam-newsletter' ),
							$format
						)
					)
				);
			} else {
				echo '<p class="description">' . esc_html__( 'Tri « les prochains » indisponible : aucun champ date associé sur ce type de contenu.', 'wam-newsletter' ) . '</p>';
			}

			echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			echo '<th style="width:28%">' . esc_html__( 'Champ ACF', 'wam-newsletter' ) . '</th>';
			echo '<th style="width:14%">' . esc_html__( 'Type', 'wam-newsletter' ) . '</th>';
			echo '<th style="width:34%">' . esc_html__( 'Valeur d’exemple', 'wam-newsletter' ) . '</th>';
			echo '<th style="width:24%">' . esc_html__( 'Rôle dans l’e-mail', 'wam-newsletter' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $champs as $champ ) {
				$valeur = $exemple ? ContentMap::example_value( $post_type, $champ['name'], $exemple ) : '';
				$classe = $champ['selectable'] ? '' : ' class="wam-nl-champ-inactif"';

				echo '<tr' . $classe . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- valeur littérale.
				printf(
					'<td><strong>%s</strong><br /><code>%s</code></td>',
					esc_html( $champ['label'] ),
					esc_html( $champ['name'] )
				);
				printf( '<td>%s</td>', esc_html( $champ['type'] ) );
				printf(
					'<td>%s</td>',
					'' !== $valeur
						? esc_html( $valeur )
						: '<span class="description">' . esc_html__( 'vide', 'wam-newsletter' ) . '</span>'
				);

				echo '<td>';
				if ( ! $champ['selectable'] ) {
					echo '<span class="description">' . esc_html__( 'champ complexe, non utilisable', 'wam-newsletter' ) . '</span>';
				} else {
					$role_actuel = '';
					foreach ( (array) ( $map[ $post_type ] ?? array() ) as $role => $nom ) {
						if ( $nom === $champ['name'] ) {
							$role_actuel = (string) $role;
							break;
						}
					}

					// Colonne en lecture seule : l'attribution se fait dans le
					// tableau ci-dessous, indexé par RÔLE. Un select par champ
					// permettrait d'attribuer deux fois le même rôle.
					echo '<span class="description">';
					echo '' !== $role_actuel
						? esc_html( ContentMap::role_label( $role_actuel ) )
						: esc_html__( '—', 'wam-newsletter' );
					echo '</span>';
				}
				echo '</td></tr>';
			}

			echo '</tbody></table>';

			// --- Attribution des rôles ---
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( ContentMap::ROLES as $role ) {
				$choix   = array( '' => __( '— Aucun —', 'wam-newsletter' ) );
				$accepte = ContentMap::ROLE_TYPES[ $role ];

				foreach ( $champs as $champ ) {
					if ( ! $champ['selectable'] || ! in_array( $champ['type'], $accepte, true ) ) {
						continue;
					}
					$choix[ $champ['name'] ] = sprintf( '%s (%s)', $champ['label'], $champ['name'] );
				}

				$actuel = (string) ( $map[ $post_type ][ $role ] ?? '' );
				$id     = 'wam_nl_map_' . $post_type . '_' . $role;

				echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( ContentMap::role_label( $role ) ) . '</label></th><td>';

				if ( 1 === count( $choix ) ) {
					echo '<span class="description">' . esc_html__( 'aucun champ de ce type sur ce contenu', 'wam-newsletter' ) . '</span>';
					printf( '<input type="hidden" name="map[%s][%s]" value="" />', esc_attr( $post_type ), esc_attr( $role ) );
				} else {
					printf( '<select id="%s" name="map[%s][%s]">', esc_attr( $id ), esc_attr( $post_type ), esc_attr( $role ) );
					foreach ( $choix as $valeur => $libelle ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( (string) $valeur ),
							selected( $actuel, (string) $valeur, false ),
							esc_html( $libelle )
						);
					}
					echo '</select>';
				}

				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		submit_button( __( 'Enregistrer les correspondances', 'wam-newsletter' ) );
		echo '</form>';

		echo '<hr /><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="wam_nl_rescan_fields" />';
		echo '<p class="description">' . esc_html__( 'À lancer après avoir ajouté ou renommé un champ ACF : les correspondances déjà choisies sont conservées.', 'wam-newsletter' ) . '</p>';
		submit_button( __( 'Rescanner les champs', 'wam-newsletter' ), 'secondary', 'submit', false );
		echo '</form>';
	}
}
