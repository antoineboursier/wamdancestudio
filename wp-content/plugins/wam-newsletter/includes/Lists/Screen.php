<?php
namespace WamNewsletter\Lists;

use WamNewsletter\Admin\Menu;
use WamNewsletter\Admin\Notices;
use WamNewsletter\Install;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WamNewsletter\Subscribers\Screen as SubscribersScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Écran « Listes » : CRUD simple (§5).
 *
 * Pas de WP_List_Table ici : le site a trois ou quatre listes, un tableau
 * nu avec son formulaire sur la même page est plus lisible qu'un écran paginé.
 */
class Screen {

	const PAGE_SLUG = 'wam-nl-lists';

	const NONCE = 'wam_nl_lists';

	private static string $hook = '';

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 10 );
	}

	public static function add_page(): void {
		self::$hook = (string) add_submenu_page(
			Menu::SLUG,
			__( 'Listes', 'wam-newsletter' ),
			__( 'Listes', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
		if ( self::$hook ) {
			add_action( 'load-' . self::$hook, array( self::class, 'handle_actions' ) );
		}
	}

	public static function url( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}

	public static function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			self::url(
				array(
					'wam_action' => $action,
					'liste'      => $id,
				)
			),
			self::NONCE . '_' . $action . '_' . $id
		);
	}

	private static function redirect( array $args = array() ): void {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public static function handle_actions(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			return;
		}

		if ( isset( $_POST['wam_nl_save_list'] ) ) {
			check_admin_referer( self::NONCE . '_save' );

			$id          = isset( $_POST['liste'] ) ? (int) $_POST['liste'] : 0;
			$nom         = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';
			$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

			$resultat = $id ? Repository::update( $id, $nom, $description ) : Repository::insert( $nom, $description );

			if ( is_wp_error( $resultat ) ) {
				Notices::error( $resultat->get_error_message() );
				self::redirect( $id ? array( 'liste' => $id ) : array() );
			}

			Notices::success( $id ? __( 'Liste mise à jour.', 'wam-newsletter' ) : __( 'Liste créée.', 'wam-newsletter' ) );
			self::redirect();
		}

		$action = isset( $_GET['wam_action'] ) ? sanitize_key( wp_unslash( $_GET['wam_action'] ) ) : '';
		$id     = isset( $_GET['liste'] ) ? (int) $_GET['liste'] : 0;

		if ( 'delete' === $action && $id ) {
			check_admin_referer( self::NONCE . '_delete_' . $id );

			$liste = Repository::find( $id );
			if ( ! $liste ) {
				Notices::error( __( 'Liste introuvable.', 'wam-newsletter' ) );
				self::redirect();
			}

			Repository::delete( $id );
			Notices::success(
				sprintf(
					/* translators: %s nom de la liste */
					__( 'Liste « %s » supprimée. Les abonné·es sont conservé·es.', 'wam-newsletter' ),
					$liste['name']
				)
			);
			self::redirect();
		}
	}

	public static function render(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}

		$id      = isset( $_GET['liste'] ) ? (int) $_GET['liste'] : 0;
		$edition = $id ? Repository::find( $id ) : null;
		$listes  = Repository::all();

		echo '<div class="wrap wam-nl">';
		echo '<h1>' . esc_html__( 'Listes', 'wam-newsletter' ) . '</h1>';
		Notices::render();

		echo '<p class="description">' . esc_html__( 'Supprimer une liste ne supprime aucun·e abonné·e : seule l’association est retirée.', 'wam-newsletter' ) . '</p>';

		echo '<div class="wam-nl-colonnes">';

		// --- Tableau ---
		echo '<div class="wam-nl-colonne-principale">';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Nom', 'wam-newsletter' ) . '</th>';
		echo '<th>' . esc_html__( 'Description', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:12em">' . esc_html__( 'Abonné·es actifs', 'wam-newsletter' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $listes ) {
			echo '<tr><td colspan="3">' . esc_html__( 'Aucune liste. Créez-en une avec le formulaire à droite.', 'wam-newsletter' ) . '</td></tr>';
		}

		foreach ( $listes as $liste ) {
			$lid     = (int) $liste['id'];
			$actions = array(
				sprintf( '<a href="%s">%s</a>', esc_url( self::url( array( 'liste' => $lid ) ) ), esc_html__( 'Modifier', 'wam-newsletter' ) ),
				sprintf(
					'<a href="%s" class="submitdelete" onclick="return confirm(%s)">%s</a>',
					esc_url( self::action_url( 'delete', $lid ) ),
					esc_js( wp_json_encode( __( 'Supprimer cette liste ? Les abonné·es sont conservé·es.', 'wam-newsletter' ) ) ),
					esc_html__( 'Supprimer', 'wam-newsletter' )
				),
			);

			echo '<tr>';
			echo '<td><strong>' . esc_html( $liste['name'] ) . '</strong><div class="row-actions">' . implode( ' | ', $actions ) . '</div></td>';
			echo '<td>' . esc_html( (string) $liste['description'] ) . '</td>';
			printf(
				'<td><a href="%s">%s</a></td>',
				esc_url( SubscribersScreen::url( array( 'liste' => $lid, 'statut' => Subscribers::STATUS_SUBSCRIBED ) ) ),
				esc_html( number_format_i18n( (int) $liste['subscriber_count'] ) )
			);
			echo '</tr>';
		}

		echo '</tbody></table></div>';

		// --- Formulaire ---
		echo '<div class="wam-nl-colonne-laterale">';
		echo '<h2>' . esc_html( $edition ? __( 'Modifier la liste', 'wam-newsletter' ) : __( 'Nouvelle liste', 'wam-newsletter' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( self::url() ) . '">';
		wp_nonce_field( self::NONCE . '_save' );
		echo '<input type="hidden" name="liste" value="' . esc_attr( (string) ( $edition ? (int) $edition['id'] : 0 ) ) . '" />';

		echo '<p><label for="nom"><strong>' . esc_html__( 'Nom', 'wam-newsletter' ) . '</strong></label><br />';
		echo '<input type="text" name="nom" id="nom" class="regular-text" required value="' . esc_attr( (string) ( $edition['name'] ?? '' ) ) . '" /></p>';

		echo '<p><label for="description"><strong>' . esc_html__( 'Description', 'wam-newsletter' ) . '</strong></label><br />';
		echo '<textarea name="description" id="description" rows="3" class="large-text">' . esc_textarea( (string) ( $edition['description'] ?? '' ) ) . '</textarea></p>';

		submit_button( $edition ? __( 'Mettre à jour', 'wam-newsletter' ) : __( 'Créer la liste', 'wam-newsletter' ), 'primary', 'wam_nl_save_list' );
		if ( $edition ) {
			printf( '<a href="%s" class="button-link">%s</a>', esc_url( self::url() ), esc_html__( 'Annuler', 'wam-newsletter' ) );
		}
		echo '</form></div>';

		echo '</div></div>';
	}
}
