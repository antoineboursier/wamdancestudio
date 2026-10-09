<?php
namespace WamNewsletter\Subscribers;

use WamNewsletter\Admin\Menu;
use WamNewsletter\Admin\Notices;
use WamNewsletter\Install;
use WamNewsletter\Integrations\MailPoetMigrator;
use WamNewsletter\Integrations\WooCommerceImporter;
use WamNewsletter\Lists\Repository as Lists;

defined( 'ABSPATH' ) || exit;

/**
 * Écran « Abonné·es » : tableau, fiche, import CSV, outils de migration.
 *
 * Toutes les actions passent par le hook `load-` de la page plutôt que par
 * admin-post.php : l'export CSV doit émettre ses en-têtes avant le moindre
 * octet de HTML, et c'est le seul endroit où on en a la garantie tout en
 * gardant une seule URL pour l'écran.
 */
class Screen {

	const PAGE_SLUG = 'wam-nl-subscribers';

	const NONCE = 'wam_nl_subscribers';

	private static string $hook = '';

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 10 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_page(): void {
		self::$hook = (string) add_submenu_page(
			Menu::SLUG,
			__( 'Abonné·es', 'wam-newsletter' ),
			__( 'Abonné·es', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);

		if ( self::$hook ) {
			add_action( 'load-' . self::$hook, array( self::class, 'handle_actions' ) );
			add_filter( 'set-screen-option', array( self::class, 'save_screen_option' ), 10, 3 );
			add_action( 'load-' . self::$hook, array( self::class, 'add_screen_options' ) );
		}
	}

	public static function add_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Abonné·es par page', 'wam-newsletter' ),
				'default' => 25,
				'option'  => 'wam_nl_abonnes_par_page',
			)
		);
	}

	public static function save_screen_option( $statut, $option, $valeur ) {
		return 'wam_nl_abonnes_par_page' === $option ? max( 1, min( 500, (int) $valeur ) ) : $statut;
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$rel = 'assets/admin/wam-nl-admin.css';
		if ( ! file_exists( WAM_NL_DIR . $rel ) ) {
			return;
		}
		wp_enqueue_style( 'wam-nl-admin', WAM_NL_URL . $rel, array(), (string) filemtime( WAM_NL_DIR . $rel ) );
	}

	/** @param array<string,mixed> $args */
	public static function url( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/** URL d'action unitaire, signée par un nonce. */
	public static function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			self::url(
				array(
					'wam_action' => $action,
					'abonne'     => $id,
				)
			),
			self::NONCE . '_' . $action . '_' . $id
		);
	}

	private static function redirect( array $args = array() ): void {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/**
	 * Toutes les écritures de l'écran. Capacité vérifiée d'abord, nonce ensuite,
	 * pour chaque branche sans exception (§12).
	 */
	public static function handle_actions(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			return;
		}

		// --- Actions unitaires (liens de ligne), en GET signé ---
		$action = isset( $_GET['wam_action'] ) ? sanitize_key( wp_unslash( $_GET['wam_action'] ) ) : '';
		$id     = isset( $_GET['abonne'] ) ? (int) $_GET['abonne'] : 0;

		if ( $action && $id ) {
			check_admin_referer( self::NONCE . '_' . $action . '_' . $id );
			self::run_single_action( $action, $id );
		}

		// --- Enregistrement de la fiche ---
		if ( isset( $_POST['wam_nl_save_subscriber'] ) ) {
			check_admin_referer( self::NONCE . '_save' );
			self::save_subscriber();
		}

		// --- Actions groupées ---
		if ( isset( $_POST['abonnes'] ) || isset( $_POST['action'] ) || isset( $_POST['action2'] ) ) {
			self::run_bulk_action();
		}

		// --- Import CSV ---
		if ( isset( $_POST['wam_nl_import_csv'] ) ) {
			check_admin_referer( self::NONCE . '_import' );
			self::run_import();
		}

		// --- Outils de migration ---
		if ( isset( $_POST['wam_nl_tool'] ) ) {
			check_admin_referer( self::NONCE . '_tools' );
			self::run_tool( sanitize_key( wp_unslash( $_POST['wam_nl_tool'] ) ) );
		}

		// --- Export CSV (doit précéder toute sortie HTML) ---
		if ( isset( $_GET['wam_export'] ) ) {
			check_admin_referer( self::NONCE . '_export' );
			self::run_export();
		}
	}

	private static function run_single_action( string $action, int $id ): void {
		$abonne = Repository::find( $id );
		if ( ! $abonne ) {
			Notices::error( __( 'Abonné·e introuvable.', 'wam-newsletter' ) );
			self::redirect();
		}

		switch ( $action ) {
			case 'unsubscribe':
				Repository::set_status( $id, Repository::STATUS_UNSUBSCRIBED );
				Notices::success( __( 'Abonné·e désabonné·e.', 'wam-newsletter' ) );
				break;

			case 'trash':
				Repository::set_status( $id, Repository::STATUS_TRASHED );
				Notices::success( __( 'Déplacé·e dans la corbeille.', 'wam-newsletter' ) );
				break;

			case 'restore':
				Repository::set_status( $id, Repository::STATUS_SUBSCRIBED );
				Notices::success( __( 'Abonné·e restauré·e.', 'wam-newsletter' ) );
				break;

			case 'delete':
				// Suppression définitive réservée à la corbeille (§4) : sinon un
				// clic malheureux depuis la liste principale effacerait aussi les
				// statistiques, sans retour possible.
				if ( Repository::STATUS_TRASHED !== $abonne['status'] ) {
					Notices::error( __( 'La suppression définitive n’est possible que depuis la corbeille.', 'wam-newsletter' ) );
					break;
				}
				Repository::delete( $id );
				Notices::success( __( 'Abonné·e supprimé·e définitivement.', 'wam-newsletter' ) );
				break;

			default:
				Notices::error( __( 'Action inconnue.', 'wam-newsletter' ) );
		}

		self::redirect( self::kept_filters() );
	}

	private static function run_bulk_action(): void {
		$table  = new ListTable();
		$action = $table->current_action();
		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'bulk-abonnes' );

		$ids = isset( $_POST['abonnes'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['abonnes'] ) ) : array();
		$ids = array_values( array_filter( $ids ) );
		if ( ! $ids ) {
			Notices::error( __( 'Aucun·e abonné·e sélectionné·e.', 'wam-newsletter' ) );
			self::redirect( self::kept_filters() );
		}

		$n = 0;

		if ( 0 === strpos( $action, 'addlist-' ) ) {
			$list_id = (int) substr( $action, strlen( 'addlist-' ) );
			foreach ( $ids as $id ) {
				Repository::add_to_lists( $id, array( $list_id ) );
				++$n;
			}
			/* translators: %d nombre d'abonné·es */
			Notices::success( sprintf( _n( '%d abonné·e ajouté·e à la liste.', '%d abonné·es ajouté·es à la liste.', $n, 'wam-newsletter' ), $n ) );
			self::redirect( self::kept_filters() );
		}

		if ( 0 === strpos( $action, 'rmlist-' ) ) {
			$list_id = (int) substr( $action, strlen( 'rmlist-' ) );
			foreach ( $ids as $id ) {
				Repository::remove_from_lists( $id, array( $list_id ) );
				++$n;
			}
			/* translators: %d nombre d'abonné·es */
			Notices::success( sprintf( _n( '%d abonné·e retiré·e de la liste.', '%d abonné·es retiré·es de la liste.', $n, 'wam-newsletter' ), $n ) );
			self::redirect( self::kept_filters() );
		}

		switch ( $action ) {
			case 'unsubscribe':
				foreach ( $ids as $id ) {
					Repository::set_status( $id, Repository::STATUS_UNSUBSCRIBED );
					++$n;
				}
				/* translators: %d nombre d'abonné·es */
				Notices::success( sprintf( _n( '%d abonné·e désabonné·e.', '%d abonné·es désabonné·es.', $n, 'wam-newsletter' ), $n ) );
				break;

			case 'trash':
				foreach ( $ids as $id ) {
					Repository::set_status( $id, Repository::STATUS_TRASHED );
					++$n;
				}
				/* translators: %d nombre d'abonné·es */
				Notices::success( sprintf( _n( '%d abonné·e mis·e à la corbeille.', '%d abonné·es mis·es à la corbeille.', $n, 'wam-newsletter' ), $n ) );
				break;

			case 'restore':
				foreach ( $ids as $id ) {
					Repository::set_status( $id, Repository::STATUS_SUBSCRIBED );
					++$n;
				}
				/* translators: %d nombre d'abonné·es */
				Notices::success( sprintf( _n( '%d abonné·e restauré·e.', '%d abonné·es restauré·es.', $n, 'wam-newsletter' ), $n ) );
				break;

			case 'delete':
				foreach ( $ids as $id ) {
					$abonne = Repository::find( $id );
					if ( $abonne && Repository::STATUS_TRASHED === $abonne['status'] ) {
						Repository::delete( $id );
						++$n;
					}
				}
				/* translators: %d nombre d'abonné·es */
				Notices::success( sprintf( _n( '%d abonné·e supprimé·e définitivement.', '%d abonné·es supprimé·es définitivement.', $n, 'wam-newsletter' ), $n ) );
				break;
		}

		self::redirect( self::kept_filters() );
	}

	private static function save_subscriber(): void {
		$id      = isset( $_POST['abonne'] ) ? (int) $_POST['abonne'] : 0;
		$listes  = isset( $_POST['listes'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['listes'] ) ) : array();
		$statut  = isset( $_POST['statut'] ) ? sanitize_key( wp_unslash( $_POST['statut'] ) ) : Repository::STATUS_SUBSCRIBED;
		$donnees = array(
			'email'      => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
			'first_name' => isset( $_POST['prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['prenom'] ) ) : '',
			'last_name'  => isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '',
			'status'     => in_array( $statut, Repository::STATUSES, true ) ? $statut : Repository::STATUS_SUBSCRIBED,
			'list_ids'   => $listes,
		);

		if ( $id ) {
			$resultat = Repository::update( $id, $donnees );
		} else {
			$donnees['consent_source'] = 'admin';
			$resultat                  = Repository::insert( $donnees );
		}

		if ( is_wp_error( $resultat ) ) {
			Notices::error( $resultat->get_error_message() );
			self::redirect(
				array(
					'vue'    => 'editer',
					'abonne' => $id,
				)
			);
		}

		Notices::success( $id ? __( 'Abonné·e mis·e à jour.', 'wam-newsletter' ) : __( 'Abonné·e ajouté·e.', 'wam-newsletter' ) );
		self::redirect();
	}

	private static function run_import(): void {
		if ( empty( $_FILES['fichier']['tmp_name'] ) || ! is_uploaded_file( $_FILES['fichier']['tmp_name'] ) ) {
			Notices::error( __( 'Aucun fichier reçu.', 'wam-newsletter' ) );
			self::redirect( array( 'vue' => 'importer' ) );
		}

		$listes   = isset( $_POST['listes'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['listes'] ) ) : array();
		$a_blanc  = ! empty( $_POST['dry_run'] );
		$rapport  = Csv::import(
			$_FILES['fichier']['tmp_name'],
			array(
				'list_ids'       => $listes,
				'consent_source' => 'import',
				'dry_run'        => $a_blanc,
			)
		);

		foreach ( $rapport['errors'] as $erreur ) {
			Notices::error( $erreur );
		}
		if ( ! $rapport['errors'] ) {
			Notices::success( self::format_report( $rapport, $a_blanc ) );
		}
		self::redirect( array( 'vue' => 'importer' ) );
	}

	private static function run_tool( string $outil ): void {
		$a_blanc = ! empty( $_POST['dry_run'] );

		if ( 'mailpoet' === $outil ) {
			$rapport = MailPoetMigrator::run( array( 'dry_run' => $a_blanc ) );
			foreach ( $rapport['errors'] as $erreur ) {
				Notices::error( $erreur );
			}
			if ( ! $rapport['errors'] ) {
				$detail = array();
				foreach ( $rapport['lists'] as $nom => $nombre ) {
					$detail[] = sprintf( '%s : %s', $nom, number_format_i18n( $nombre ) );
				}
				Notices::success(
					self::format_report( $rapport, $a_blanc )
					. ( $detail ? ' - ' . __( 'Listes', 'wam-newsletter' ) . ' : ' . implode( ' ⏐ ', $detail ) : '' )
				);
			}
			self::redirect( array( 'vue' => 'outils' ) );
		}

		if ( 'woocommerce' === $outil ) {
			$rapport = WooCommerceImporter::run( array( 'dry_run' => $a_blanc ) );
			foreach ( $rapport['errors'] as $erreur ) {
				Notices::error( $erreur );
			}
			if ( ! $rapport['errors'] ) {
				Notices::success( self::format_report( $rapport, $a_blanc ) );
			}
			self::redirect( array( 'vue' => 'outils' ) );
		}

		if ( 'quality' === $outil ) {
			$rapport = Quality::scan( $a_blanc );
			$detail  = array();
			foreach ( array(
				'fix'    => __( 'corrigées', 'wam-newsletter' ),
				'merge'  => __( 'doublons', 'wam-newsletter' ),
				'reject' => __( 'écartées', 'wam-newsletter' ),
				'review' => __( 'à vérifier', 'wam-newsletter' ),
			) as $cle => $libelle ) {
				if ( $rapport['samples'][ $cle ] ) {
					$detail[] = $libelle . ' : ' . implode( ' ; ', array_slice( $rapport['samples'][ $cle ], 0, 8 ) );
				}
			}
			Notices::success(
				sprintf(
					/* translators: 1: préfixe, 2: analysées, 3: corrigées, 4: doublons, 5: écartées, 6: à vérifier */
					__( '%1$s : %2$s adresse(s) analysée(s) - %3$s corrigée(s), %4$s doublon(s) fusionné(s), %5$s écartée(s) à la corbeille, %6$s à vérifier à la main.', 'wam-newsletter' ),
					$a_blanc ? __( 'Simulation (rien n’a été écrit)', 'wam-newsletter' ) : __( 'Contrôle terminé', 'wam-newsletter' ),
					number_format_i18n( $rapport['scanned'] ),
					number_format_i18n( $rapport['fixed'] ),
					number_format_i18n( $rapport['merged'] ),
					number_format_i18n( $rapport['rejected'] ),
					number_format_i18n( $rapport['review'] )
				) . ( $detail ? ' - ' . implode( ' | ', $detail ) : '' )
			);
			self::redirect( array( 'vue' => 'outils' ) );
		}

		if ( 'purge_bots' === $outil ) {
			$rapport = WooCommerceImporter::purge_bots( $a_blanc );
			Notices::success(
				$a_blanc
					? sprintf(
						/* translators: %s nombre de contacts */
						__( 'Simulation : %s contact(s) ressemblent à des robots et seraient mis à la corbeille.', 'wam-newsletter' ),
						number_format_i18n( $rapport['found'] )
					)
					: sprintf(
						/* translators: %s nombre de contacts */
						__( '%s contact(s) ressemblant à des robots ont été mis à la corbeille (récupérables depuis l’onglet Corbeille).', 'wam-newsletter' ),
						number_format_i18n( $rapport['trashed'] )
					)
			);
			self::redirect( array( 'vue' => 'outils' ) );
		}

		if ( 'mailpoet_backup' === $outil ) {
			$csv = MailPoetMigrator::export_backup();
			if ( '' === $csv ) {
				Notices::error( __( 'Tables MailPoet introuvables.', 'wam-newsletter' ) );
				self::redirect( array( 'vue' => 'outils' ) );
			}
			self::send_csv( $csv, Csv::filename( 'sauvegarde-mailpoet' ) );
		}

		Notices::error( __( 'Outil inconnu.', 'wam-newsletter' ) );
		self::redirect( array( 'vue' => 'outils' ) );
	}

	private static function format_report( array $rapport, bool $a_blanc ): string {
		$prefixe = $a_blanc ? __( 'Simulation (rien n’a été écrit)', 'wam-newsletter' ) : __( 'Import terminé', 'wam-newsletter' );
		return sprintf(
			/* translators: 1: préfixe, 2: total, 3: créés, 4: mis à jour, 5: ignorés, 6: invalides */
			__( '%1$s : %2$s ligne(s) traitée(s) - %3$s créé·e(s), %4$s mis·e(s) à jour, %5$s inchangé·e(s), %6$s invalide(s).', 'wam-newsletter' ),
			$prefixe,
			number_format_i18n( $rapport['total'] ),
			number_format_i18n( $rapport['created'] ),
			number_format_i18n( $rapport['updated'] ),
			number_format_i18n( $rapport['skipped'] ),
			number_format_i18n( $rapport['invalid'] )
		);
	}

	private static function run_export(): void {
		$statut = isset( $_GET['statut'] ) ? sanitize_key( wp_unslash( $_GET['statut'] ) ) : '';
		$args   = array(
			'status'  => in_array( $statut, Repository::STATUSES, true ) ? array( $statut ) : array(),
			'list_id' => isset( $_GET['liste'] ) ? (int) $_GET['liste'] : 0,
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		self::send_csv( Csv::export( $args ), Csv::filename() );
	}

	private static function send_csv( string $contenu, string $nom ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $nom . '"' );
		header( 'Content-Length: ' . strlen( $contenu ) );
		echo $contenu; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV binaire, déjà construit par fputcsv.
		exit;
	}

	/** Filtres à conserver après une action, pour revenir sur la même vue. */
	private static function kept_filters(): array {
		$filtres = array();
		foreach ( array( 'statut', 'liste', 's' ) as $cle ) {
			if ( isset( $_REQUEST[ $cle ] ) && '' !== $_REQUEST[ $cle ] ) {
				$filtres[ $cle ] = sanitize_text_field( wp_unslash( $_REQUEST[ $cle ] ) );
			}
		}
		return $filtres;
	}

	// -----------------------------------------------------------------
	// Rendu
	// -----------------------------------------------------------------

	public static function render(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}

		$vue = isset( $_GET['vue'] ) ? sanitize_key( wp_unslash( $_GET['vue'] ) ) : 'liste';

		echo '<div class="wrap wam-nl">';

		if ( 'editer' === $vue ) {
			self::render_form();
		} elseif ( 'importer' === $vue ) {
			self::render_import();
		} elseif ( 'outils' === $vue ) {
			self::render_tools();
		} else {
			self::render_list();
		}

		echo '</div>';
	}

	private static function render_list(): void {
		$table = new ListTable();
		$table->prepare_items();

		$export_url = wp_nonce_url(
			self::url( array_merge( self::kept_filters(), array( 'wam_export' => 1 ) ) ),
			self::NONCE . '_export'
		);

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Abonné·es', 'wam-newsletter' ) . '</h1>';
		printf(
			' <a href="%s" class="page-title-action">%s</a>',
			esc_url( self::url( array( 'vue' => 'editer' ) ) ),
			esc_html__( 'Ajouter', 'wam-newsletter' )
		);
		printf(
			' <a href="%s" class="page-title-action">%s</a>',
			esc_url( self::url( array( 'vue' => 'importer' ) ) ),
			esc_html__( 'Importer un CSV', 'wam-newsletter' )
		);
		printf(
			' <a href="%s" class="page-title-action">%s</a>',
			esc_url( $export_url ),
			esc_html__( 'Exporter cette vue', 'wam-newsletter' )
		);
		printf(
			' <a href="%s" class="page-title-action">%s</a>',
			esc_url( self::url( array( 'vue' => 'outils' ) ) ),
			esc_html__( 'Migration et imports', 'wam-newsletter' )
		);
		echo '<hr class="wp-header-end">';

		Notices::render();

		$table->views();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		foreach ( self::kept_filters() as $cle => $valeur ) {
			if ( 's' === $cle ) {
				continue;
			}
			printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $cle ), esc_attr( $valeur ) );
		}
		$table->search_box( __( 'Rechercher', 'wam-newsletter' ), 'wam-nl-recherche' );
		echo '</form>';

		echo '<form method="post">';
		wp_nonce_field( 'bulk-abonnes' );
		foreach ( self::kept_filters() as $cle => $valeur ) {
			printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $cle ), esc_attr( $valeur ) );
		}
		$table->display();
		echo '</form>';
	}

	private static function render_form(): void {
		$id     = isset( $_GET['abonne'] ) ? (int) $_GET['abonne'] : 0;
		$abonne = $id ? Repository::find( $id ) : null;

		if ( $id && ! $abonne ) {
			echo '<h1>' . esc_html__( 'Abonné·e introuvable', 'wam-newsletter' ) . '</h1>';
			return;
		}

		$listes_abonne = $id ? Repository::list_ids_of( $id ) : array();

		echo '<h1>' . esc_html( $id ? __( 'Modifier l’abonné·e', 'wam-newsletter' ) : __( 'Ajouter un·e abonné·e', 'wam-newsletter' ) ) . '</h1>';
		Notices::render();

		echo '<form method="post" action="' . esc_url( self::url() ) . '">';
		wp_nonce_field( self::NONCE . '_save' );
		echo '<input type="hidden" name="abonne" value="' . esc_attr( (string) $id ) . '" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::field_row(
			'email',
			__( 'E-mail', 'wam-newsletter' ),
			'<input type="email" name="email" id="email" class="regular-text" required value="' . esc_attr( (string) ( $abonne['email'] ?? '' ) ) . '" />'
		);
		self::field_row(
			'prenom',
			__( 'Prénom', 'wam-newsletter' ),
			'<input type="text" name="prenom" id="prenom" class="regular-text" value="' . esc_attr( (string) ( $abonne['first_name'] ?? '' ) ) . '" />'
		);
		self::field_row(
			'nom',
			__( 'Nom', 'wam-newsletter' ),
			'<input type="text" name="nom" id="nom" class="regular-text" value="' . esc_attr( (string) ( $abonne['last_name'] ?? '' ) ) . '" />'
		);

		$select = '<select name="statut" id="statut">';
		foreach ( Repository::STATUSES as $statut ) {
			$select .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $statut ),
				selected( $abonne['status'] ?? Repository::STATUS_SUBSCRIBED, $statut, false ),
				esc_html( Repository::status_label( $statut ) )
			);
		}
		$select .= '</select>';
		self::field_row( 'statut', __( 'Statut', 'wam-newsletter' ), $select );

		$cases = '';
		foreach ( Lists::all() as $liste ) {
			$cases .= sprintf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="listes[]" value="%d"%s /> %s <span class="description">(%s)</span></label>',
				(int) $liste['id'],
				checked( in_array( (int) $liste['id'], $listes_abonne, true ), true, false ),
				esc_html( $liste['name'] ),
				esc_html( number_format_i18n( (int) $liste['subscriber_count'] ) )
			);
		}
		if ( '' === $cases ) {
			$cases = '<p class="description">' . esc_html__( 'Aucune liste pour le moment.', 'wam-newsletter' ) . '</p>';
		}
		self::field_row( '', __( 'Listes', 'wam-newsletter' ), $cases );

		if ( $abonne ) {
			$source = (string) $abonne['consent_source'];
			$date   = (string) $abonne['consent_at'];
			$ts     = $date ? strtotime( $date ) : 0;
			self::field_row(
				'',
				__( 'Consentement', 'wam-newsletter' ),
				'<p class="description">' . esc_html(
					sprintf(
						/* translators: 1: source, 2: date */
						__( 'Source : %1$s - enregistré le %2$s', 'wam-newsletter' ),
						$source ?: '-',
						$ts ? wp_date( 'd/m/Y H:i', $ts ) : '-'
					)
				) . '</p>'
			);
		}

		echo '</tbody></table>';
		submit_button( $id ? __( 'Mettre à jour', 'wam-newsletter' ) : __( 'Ajouter', 'wam-newsletter' ), 'primary', 'wam_nl_save_subscriber' );
		printf( '<a href="%s" class="button-link">%s</a>', esc_url( self::url() ), esc_html__( 'Retour à la liste', 'wam-newsletter' ) );
		echo '</form>';
	}

	private static function field_row( string $id, string $label, string $champ ): void {
		echo '<tr><th scope="row">';
		if ( $id ) {
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo esc_html( $label );
		}
		echo '</th><td>' . $champ . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML construit et échappé ci-dessus.
	}

	private static function render_import(): void {
		echo '<h1>' . esc_html__( 'Importer des abonné·es depuis un CSV', 'wam-newsletter' ) . '</h1>';
		Notices::render();

		echo '<p class="description">' . esc_html__( 'Colonnes reconnues : email (obligatoire), prénom, nom. Séparateur virgule, point-virgule ou tabulation. Le dédoublonnage se fait sur l’e-mail.', 'wam-newsletter' ) . '</p>';

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( self::url( array( 'vue' => 'importer' ) ) ) . '">';
		wp_nonce_field( self::NONCE . '_import' );
		echo '<table class="form-table" role="presentation"><tbody>';

		self::field_row( 'fichier', __( 'Fichier CSV', 'wam-newsletter' ), '<input type="file" name="fichier" id="fichier" accept=".csv,text/csv" required />' );

		$cases = '';
		foreach ( Lists::all() as $liste ) {
			$cases .= sprintf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="listes[]" value="%d" /> %s</label>',
				(int) $liste['id'],
				esc_html( $liste['name'] )
			);
		}
		if ( '' === $cases ) {
			$cases = '<p class="description">' . esc_html__( 'Créez d’abord une liste pour y ranger les contacts importés.', 'wam-newsletter' ) . '</p>';
		}
		self::field_row( '', __( 'Ajouter aux listes', 'wam-newsletter' ), $cases );

		self::field_row(
			'',
			__( 'Simulation', 'wam-newsletter' ),
			'<label><input type="checkbox" name="dry_run" value="1" checked /> ' . esc_html__( 'Compter sans rien écrire', 'wam-newsletter' ) . '</label>'
		);

		echo '</tbody></table>';
		submit_button( __( 'Importer', 'wam-newsletter' ), 'primary', 'wam_nl_import_csv' );
		printf( '<a href="%s" class="button-link">%s</a>', esc_url( self::url() ), esc_html__( 'Retour à la liste', 'wam-newsletter' ) );
		echo '</form>';
	}

	private static function render_tools(): void {
		echo '<h1>' . esc_html__( 'Migration et imports', 'wam-newsletter' ) . '</h1>';
		Notices::render();

		// --- MailPoet ---
		echo '<h2>' . esc_html__( 'Migration depuis MailPoet', 'wam-newsletter' ) . '</h2>';
		if ( ! MailPoetMigrator::available() ) {
			echo '<p>' . esc_html__( 'Aucune table MailPoet trouvée sur ce site.', 'wam-newsletter' ) . '</p>';
		} else {
			$segments = MailPoetMigrator::importable_segments();
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s nombre d'abonné·es */
						__( '%s abonné·e(s) au statut « subscribed » dans MailPoet.', 'wam-newsletter' ),
						number_format_i18n( MailPoetMigrator::eligible_count() )
					)
				)
			);
			echo '<p>' . esc_html__( 'Listes qui seront recréées :', 'wam-newsletter' ) . '</p><ul class="ul-disc">';
			foreach ( $segments as $segment ) {
				echo '<li>' . esc_html( $segment['name'] ) . '</li>';
			}
			echo '</ul>';
			echo '<p class="description">' . esc_html__( 'Les listes automatiques de MailPoet (WordPress Users, WooCommerce Customers) ne sont pas recréées : leur contenu était recalculé en continu, une copie figée serait trompeuse.', 'wam-newsletter' ) . '</p>';

			self::tool_form( 'mailpoet', __( 'Migrer depuis MailPoet', 'wam-newsletter' ) );
			self::tool_form( 'mailpoet_backup', __( 'Télécharger une sauvegarde CSV de MailPoet', 'wam-newsletter' ), false );
		}

		// --- WooCommerce ---
		echo '<hr /><h2>' . esc_html__( 'Import des client·es WooCommerce', 'wam-newsletter' ) . '</h2>';
		if ( ! WooCommerceImporter::available() ) {
			echo '<p>' . esc_html__( 'WooCommerce n’est pas actif.', 'wam-newsletter' ) . '</p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>';
			echo esc_html__( 'Aucun accord de communication n’est enregistré par le site : cet import reprend donc tou·tes les client·es, sur décision d’Antoine du 07/10/2026. Chaque contact est marqué « woocommerce » avec la date d’import, et un·e désabonné·e n’est jamais réabonné·e.', 'wam-newsletter' );
			echo '</p></div>';
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s nom de la liste */
						__( 'Les contacts sont ajoutés à la liste « %s ».', 'wam-newsletter' ),
						WooCommerceImporter::LIST_NAME
					)
				)
			);
			self::tool_form( 'woocommerce', __( 'Importer depuis WooCommerce', 'wam-newsletter' ) );

			echo '<h3>' . esc_html__( 'Contrôle qualité des adresses', 'wam-newsletter' ) . '</h3>';
			echo '<p>' . esc_html__( 'Passe en revue les abonné·es actifs : corrige les fautes de domaine évidentes (gmai.com, hotmail.ff…), fusionne les doublons qui en résultent, met à la corbeille les robots, les adresses techniques et les domaines qui ne reçoivent pas de courrier, et signale les cas douteux sans y toucher. Les adresses ajoutées à la main ne sont jamais modifiées. Rien n’est supprimé définitivement.', 'wam-newsletter' ) . '</p>';
			self::tool_form( 'quality', __( 'Lancer le contrôle qualité', 'wam-newsletter' ) );

			echo '<h3>' . esc_html__( 'Commandes de robots', 'wam-newsletter' ) . '</h3>';
			echo '<p>' . esc_html__( 'WooCommerce garde des commandes de robots (test de cartes bancaires, spam) : noms aléatoires, adresses en .ru ou .top, alphabet cyrillique… Ce bouton met à la corbeille ceux déjà importés. Rien n’est supprimé définitivement.', 'wam-newsletter' ) . '</p>';
			self::tool_form( 'purge_bots', __( 'Écarter les robots déjà importés', 'wam-newsletter' ) );
		}

		printf( '<p><a href="%s" class="button-link">%s</a></p>', esc_url( self::url() ), esc_html__( 'Retour à la liste', 'wam-newsletter' ) );
	}

	private static function tool_form( string $outil, string $libelle, bool $avec_simulation = true ): void {
		echo '<form method="post" action="' . esc_url( self::url( array( 'vue' => 'outils' ) ) ) . '" style="margin-bottom:16px">';
		wp_nonce_field( self::NONCE . '_tools' );
		echo '<input type="hidden" name="wam_nl_tool" value="' . esc_attr( $outil ) . '" />';
		if ( $avec_simulation ) {
			echo '<label style="margin-right:12px"><input type="checkbox" name="dry_run" value="1" checked /> ' . esc_html__( 'Simulation (rien n’est écrit)', 'wam-newsletter' ) . '</label>';
		}
		submit_button( $libelle, 'secondary', 'submit', false );
		echo '</form>';
	}
}
