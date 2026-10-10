<?php
namespace WamNewsletter\Subscribers;

use WamNewsletter\Lists\Repository as Lists;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tableau des abonné·es (§4).
 *
 * Colonnes volontairement limitées à celles du cahier des charges : e-mail,
 * prénom, nom, statut, listes, date de création. Rien d'autre.
 */
class ListTable extends WP_List_Table {

	/** Onglet courant : '' (tous) ou un statut. */
	private string $statut = '';

	private int $list_id = 0;

	private string $recherche = '';

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'abonne',
				'plural'   => 'abonnes',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'E-mail', 'wam-newsletter' ),
			'first_name' => __( 'Prénom', 'wam-newsletter' ),
			'last_name'  => __( 'Nom', 'wam-newsletter' ),
			'status'     => __( 'Statut', 'wam-newsletter' ),
			'lists'      => __( 'Listes', 'wam-newsletter' ),
			'created_at' => __( 'Inscription', 'wam-newsletter' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'email'      => array( 'email', false ),
			'first_name' => array( 'first_name', false ),
			'last_name'  => array( 'last_name', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/** Onglets de filtre, avec compteurs (§4). */
	protected function get_views(): array {
		$counts = Repository::counts_by_status();
		$total  = array_sum( $counts ) - $counts[ Repository::STATUS_TRASHED ];

		$onglets = array(
			''                             => array( __( 'Tous', 'wam-newsletter' ), $total ),
			Repository::STATUS_SUBSCRIBED   => array( __( 'Abonné·es', 'wam-newsletter' ), $counts[ Repository::STATUS_SUBSCRIBED ] ),
			Repository::STATUS_UNSUBSCRIBED => array( __( 'Désabonné·es', 'wam-newsletter' ), $counts[ Repository::STATUS_UNSUBSCRIBED ] ),
			Repository::STATUS_TRASHED      => array( __( 'Corbeille', 'wam-newsletter' ), $counts[ Repository::STATUS_TRASHED ] ),
		);

		// Les rebonds n'apparaissent que s'il y en a : en V1 le statut est posé à
		// la main, l'onglet serait donc vide la plupart du temps.
		if ( $counts[ Repository::STATUS_BOUNCED ] > 0 ) {
			$onglets[ Repository::STATUS_BOUNCED ] = array( __( 'Rebonds', 'wam-newsletter' ), $counts[ Repository::STATUS_BOUNCED ] );
		}

		$vues = array();
		foreach ( $onglets as $slug => $def ) {
			list( $libelle, $nombre ) = $def;
			$url    = Screen::url( '' === $slug ? array() : array( 'statut' => $slug ) );
			$classe = ( $slug === $this->statut ) ? ' class="current"' : '';
			$vues[ $slug ? $slug : 'all' ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				$classe,
				esc_html( $libelle ),
				esc_html( number_format_i18n( $nombre ) )
			);
		}
		return $vues;
	}

	protected function get_bulk_actions(): array {
		$actions = array();

		if ( Repository::STATUS_TRASHED === $this->statut ) {
			$actions['restore'] = __( 'Restaurer', 'wam-newsletter' );
			$actions['delete']  = __( 'Supprimer définitivement', 'wam-newsletter' );
			return $actions;
		}

		$actions['unsubscribe'] = __( 'Désabonner', 'wam-newsletter' );
		$actions['trash']       = __( 'Mettre à la corbeille', 'wam-newsletter' );

		// Une action par liste plutôt qu'un second select : le site n'a que
		// quelques listes, et ça évite d'ajouter du JavaScript pour afficher un
		// champ conditionnel dans la barre d'actions groupées.
		foreach ( Lists::options() as $id => $nom ) {
			/* translators: %s nom de la liste */
			$actions[ 'addlist-' . $id ] = sprintf( __( 'Ajouter à : %s', 'wam-newsletter' ), $nom );
		}
		foreach ( Lists::options() as $id => $nom ) {
			/* translators: %s nom de la liste */
			$actions[ 'rmlist-' . $id ] = sprintf( __( 'Retirer de : %s', 'wam-newsletter' ), $nom );
		}

		return $actions;
	}

	/** Select de filtre par liste (§4). */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$listes = Lists::options();
		if ( ! $listes ) {
			return;
		}
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="wam-nl-filtre-liste">' . esc_html__( 'Filtrer par liste', 'wam-newsletter' ) . '</label>';
		echo '<select name="liste" id="wam-nl-filtre-liste">';
		echo '<option value="0">' . esc_html__( 'Toutes les listes', 'wam-newsletter' ) . '</option>';
		foreach ( $listes as $id => $nom ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $id,
				selected( $this->list_id, (int) $id, false ),
				esc_html( $nom )
			);
		}
		echo '</select>';
		submit_button( __( 'Filtrer', 'wam-newsletter' ), '', 'filtrer', false );
		echo '</div>';
	}

	public function prepare_items(): void {
		$statuts_valides = Repository::STATUSES;

		$statut = isset( $_REQUEST['statut'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['statut'] ) ) : '';
		$this->statut    = in_array( $statut, $statuts_valides, true ) ? $statut : '';
		$this->list_id   = isset( $_REQUEST['liste'] ) ? max( 0, (int) $_REQUEST['liste'] ) : 0;
		$this->recherche = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$par_page = $this->get_items_per_page( 'wam_nl_abonnes_par_page', 25 );
		$page     = $this->get_pagenum();

		$args = array(
			'status'   => '' === $this->statut ? array() : array( $this->statut ),
			'list_id'  => $this->list_id,
			'search'   => $this->recherche,
			'orderby'  => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at',
			'order'    => isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc',
			'per_page' => $par_page,
			'page'     => $page,
		);

		$this->items = Repository::query( $args );
		$total       = Repository::count( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $par_page,
				'total_pages' => (int) ceil( $total / $par_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		// Les listes des lignes affichées, en une requête (évite 25 requêtes).
		$this->listes_par_abonne = Repository::lists_for( wp_list_pluck( $this->items, 'id' ) );
	}

	/** @var array<int,array<int,string>> */
	private array $listes_par_abonne = array();

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="abonnes[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_email( $item ): string {
		$id   = (int) $item['id'];
		$nom  = sprintf( '<strong>%s</strong>', esc_html( $item['email'] ) );
		$liens = array();

		if ( Repository::STATUS_TRASHED === $item['status'] ) {
			$liens['restore'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( Screen::action_url( 'restore', $id ) ),
				esc_html__( 'Restaurer', 'wam-newsletter' )
			);
			$liens['delete'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s)">%s</a>',
				esc_url( Screen::action_url( 'delete', $id ) ),
				esc_attr( wp_json_encode( __( 'Supprimer définitivement cet abonné·e et ses statistiques ?', 'wam-newsletter' ) ) ),
				esc_html__( 'Supprimer définitivement', 'wam-newsletter' )
			);
		} else {
			$liens['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( Screen::url( array( 'vue' => 'editer', 'abonne' => $id ) ) ),
				esc_html__( 'Modifier', 'wam-newsletter' )
			);
			if ( Repository::STATUS_SUBSCRIBED === $item['status'] ) {
				$liens['unsubscribe'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( Screen::action_url( 'unsubscribe', $id ) ),
					esc_html__( 'Désabonner', 'wam-newsletter' )
				);
			}
			$liens['trash'] = sprintf(
				'<a href="%s" class="submitdelete">%s</a>',
				esc_url( Screen::action_url( 'trash', $id ) ),
				esc_html__( 'Corbeille', 'wam-newsletter' )
			);
		}

		return $nom . $this->row_actions( $liens );
	}

	protected function column_status( $item ): string {
		$classe = 'wam-nl-pastille wam-nl-pastille--' . sanitize_html_class( $item['status'] );
		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $classe ),
			esc_html( Repository::status_label( (string) $item['status'] ) )
		);
	}

	protected function column_lists( $item ): string {
		$listes = $this->listes_par_abonne[ (int) $item['id'] ] ?? array();
		if ( ! $listes ) {
			return '<span aria-hidden="true">-</span><span class="screen-reader-text">' . esc_html__( 'Aucune liste', 'wam-newsletter' ) . '</span>';
		}
		$liens = array();
		foreach ( $listes as $id => $nom ) {
			$liens[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( Screen::url( array( 'liste' => (int) $id ) ) ),
				esc_html( $nom )
			);
		}
		return implode( ', ', $liens );
	}

	protected function column_created_at( $item ): string {
		$ts = strtotime( (string) $item['created_at'] );
		return $ts ? esc_html( wp_date( 'd/m/Y', $ts ) ) : '';
	}

	protected function column_default( $item, $column_name ): string {
		$valeur = $item[ $column_name ] ?? '';
		return '' === $valeur
			? '<span aria-hidden="true">-</span>'
			: esc_html( (string) $valeur );
	}

	public function no_items(): void {
		esc_html_e( 'Aucun·e abonné·e pour ce filtre.', 'wam-newsletter' );
	}

	/** Conserve les filtres courants dans les URLs de pagination et de tri. */
	public function current_filters(): array {
		$filtres = array();
		if ( '' !== $this->statut ) {
			$filtres['statut'] = $this->statut;
		}
		if ( $this->list_id ) {
			$filtres['liste'] = $this->list_id;
		}
		if ( '' !== $this->recherche ) {
			$filtres['s'] = $this->recherche;
		}
		return $filtres;
	}
}
