<?php
namespace WamNewsletter\Stats;

use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Install;
use WamNewsletter\Sending\Log;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Journal d'envoi : deux vues d'une même campagne.
 *
 *  - « Par destinataire » répond à « qui a reçu, qui a échoué et pourquoi,
 *    qui a ouvert, qui a cliqué » — indispensable dès qu'on n'a plus les
 *    retours oraux de trois ou quatre personnes ;
 *  - « Déroulé de l'envoi » répond à « que s'est-il passé » — lot par lot,
 *    pause par pause.
 *
 * L'écran est délibérément sans JavaScript : des liens et un formulaire GET
 * suffisent, et une page de diagnostic doit fonctionner même quand le reste va
 * mal.
 */
class JournalScreen {

	const PAGE_SLUG   = 'wam-nl-journal';
	const EXPORT_ACTION = 'wam_nl_journal_csv';

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 12 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( self::class, 'export' ) );
	}

	/** Page sans entrée de menu : on y arrive depuis la liste des newsletters. */
	public static function add_page(): void {
		add_submenu_page(
			'',
			__( 'Journal d’envoi', 'wam-newsletter' ),
			__( 'Journal d’envoi', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function url( int $newsletter_id, string $vue = 'destinataires', array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page'       => self::PAGE_SLUG,
					'newsletter' => $newsletter_id,
					'vue'        => $vue,
				),
				$extra
			),
			admin_url( 'admin.php' )
		);
	}

	/** Télécharge la vue filtrée en CSV. */
	public static function export(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );

		$newsletter_id = isset( $_GET['newsletter'] ) ? absint( $_GET['newsletter'] ) : 0;
		$post          = $newsletter_id ? get_post( $newsletter_id ) : null;
		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Newsletter introuvable.', 'wam-newsletter' ) );
		}

		$args = array(
			'filter' => isset( $_GET['filtre'] ) ? sanitize_key( wp_unslash( $_GET['filtre'] ) ) : 'all',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);

		$contenu = Recipients::csv( $newsletter_id, $args );
		$nom     = sprintf( 'wam-newsletter-journal-%d-%s.csv', $newsletter_id, wp_date( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $nom . '"' );
		header( 'Content-Length: ' . strlen( $contenu ) );
		echo $contenu; // phpcs:ignore WordPress.Security.EscapeOutput -- flux CSV, pas du HTML.
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}

		$newsletter_id = isset( $_GET['newsletter'] ) ? absint( $_GET['newsletter'] ) : 0;
		$post          = $newsletter_id ? get_post( $newsletter_id ) : null;

		echo '<div class="wrap">';

		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			echo '<h1>' . esc_html__( 'Journal d’envoi', 'wam-newsletter' ) . '</h1>';
			echo '<p>' . esc_html__( 'Newsletter introuvable.', 'wam-newsletter' ) . '</p>';
			printf(
				'<p><a href="%s">%s</a></p></div>',
				esc_url( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) ),
				esc_html__( 'Retour aux newsletters', 'wam-newsletter' )
			);
			return;
		}

		$vue = isset( $_GET['vue'] ) ? sanitize_key( wp_unslash( $_GET['vue'] ) ) : 'destinataires';
		if ( ! in_array( $vue, array( 'destinataires', 'deroule' ), true ) ) {
			$vue = 'destinataires';
		}

		echo '<h1>' . esc_html__( 'Journal d’envoi', 'wam-newsletter' ) . '</h1>';
		echo '<p class="description">' . esc_html( get_the_title( $newsletter_id ) ) . '</p>';

		self::onglets( $newsletter_id, $vue );
		self::resume( $newsletter_id );

		if ( 'deroule' === $vue ) {
			self::table_deroule( $newsletter_id );
		} else {
			self::table_destinataires( $newsletter_id );
		}

		printf(
			'<p><a href="%s">%s</a> &#124; <a href="%s">%s</a></p>',
			esc_url( Screen::url( $newsletter_id ) ),
			esc_html__( 'Voir les statistiques', 'wam-newsletter' ),
			esc_url( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) ),
			esc_html__( 'Retour aux newsletters', 'wam-newsletter' )
		);

		echo '</div>';
	}

	private static function onglets( int $newsletter_id, string $vue ): void {
		$vues = array(
			'destinataires' => __( 'Par destinataire', 'wam-newsletter' ),
			'deroule'       => __( 'Déroulé de l’envoi', 'wam-newsletter' ),
		);

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $vues as $cle => $libelle ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( $newsletter_id, $cle ) ),
				$cle === $vue ? ' nav-tab-active' : '',
				esc_html( $libelle )
			);
		}
		echo '</h2>';
	}

	/** Bandeau d'état : où en est l'envoi, en une ligne. */
	private static function resume( int $newsletter_id ): void {
		$progression = Scheduler::progress( $newsletter_id );

		echo '<p style="margin:16px 0;">';
		printf(
			/* translators: 1: statut, 2: envoyés, 3: échecs, 4: en attente, 5: total */
			esc_html__( 'État : %1$s — %2$s envoyé(s), %3$s échec(s), %4$s en attente, sur %5$s destinataire(s).', 'wam-newsletter' ),
			'<strong>' . esc_html( $progression['status'] ) . '</strong>',
			esc_html( number_format_i18n( $progression['sent'] ) ),
			esc_html( number_format_i18n( $progression['failed'] ) ),
			esc_html( number_format_i18n( $progression['pending'] ) ),
			esc_html( number_format_i18n( $progression['total'] ) )
		);
		if ( '' !== (string) $progression['pause'] ) {
			echo '<br /><strong>' . esc_html__( 'En pause :', 'wam-newsletter' ) . '</strong> ' . esc_html( $progression['pause'] );
		}
		echo '</p>';
	}

	private static function table_destinataires( int $newsletter_id ): void {
		$filtre = isset( $_GET['filtre'] ) ? sanitize_key( wp_unslash( $_GET['filtre'] ) ) : 'all';
		if ( ! in_array( $filtre, Recipients::FILTERS, true ) ) {
			$filtre = 'all';
		}
		$recherche = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$compteurs = Recipients::counts_by_filter( $newsletter_id );

		// Onglets de filtre
		$liens = array();
		foreach ( Recipients::FILTERS as $cle ) {
			$liens[] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( self::url( $newsletter_id, 'destinataires', array( 'filtre' => $cle ) ) ),
				$cle === $filtre ? ' class="current"' : '',
				esc_html( Recipients::filter_label( $cle ) ),
				esc_html( number_format_i18n( $compteurs[ $cle ] ) )
			);
		}
		echo '<ul class="subsubsub"><li>' . implode( ' | </li><li>', $liens ) . '</li></ul>';

		// Recherche + export
		echo '<form method="get" style="margin:12px 0;">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::PAGE_SLUG ) );
		printf( '<input type="hidden" name="newsletter" value="%d" />', (int) $newsletter_id );
		echo '<input type="hidden" name="vue" value="destinataires" />';
		printf( '<input type="hidden" name="filtre" value="%s" />', esc_attr( $filtre ) );
		printf(
			'<input type="search" name="s" value="%s" placeholder="%s" /> ',
			esc_attr( $recherche ),
			esc_attr__( 'Adresse ou nom', 'wam-newsletter' )
		);
		printf( '<button type="submit" class="button">%s</button> ', esc_html__( 'Rechercher', 'wam-newsletter' ) );

		$url_export = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => self::EXPORT_ACTION,
					'newsletter' => $newsletter_id,
					'filtre'     => $filtre,
					's'          => $recherche,
				),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_ACTION
		);
		printf( '<a href="%s" class="button">%s</a>', esc_url( $url_export ), esc_html__( 'Exporter cette vue en CSV', 'wam-newsletter' ) );
		echo '</form>';

		$args   = array(
			'filter'   => $filtre,
			'search'   => $recherche,
			'page'     => $page,
			'per_page' => Recipients::PER_PAGE,
		);
		$lignes = Recipients::query( $newsletter_id, $args );
		$total  = Recipients::count( $newsletter_id, $args );

		if ( ! $lignes ) {
			echo '<p>' . esc_html__( 'Aucun destinataire pour cette vue. La file d’envoi se remplit au lancement de l’envoi.', 'wam-newsletter' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Adresse', 'wam-newsletter' ) . '</th>';
		echo '<th>' . esc_html__( 'Nom', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:8em">' . esc_html__( 'Envoi', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:9em">' . esc_html__( 'Remise', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:11em">' . esc_html__( 'Horodatage', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:7em">' . esc_html__( 'Réactions', 'wam-newsletter' ) . '</th>';
		echo '<th>' . esc_html__( 'Message du serveur', 'wam-newsletter' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $lignes as $ligne ) {
			$reactions = array();
			if ( $ligne['opened'] ) {
				$reactions[] = __( 'ouvert', 'wam-newsletter' );
			}
			if ( $ligne['clicked'] ) {
				/* translators: %d nombre de clics */
				$reactions[] = sprintf( _n( '%d clic', '%d clics', $ligne['clicks'], 'wam-newsletter' ), $ligne['clicks'] );
			}
			if ( $ligne['unsubscribed'] ) {
				$reactions[] = __( 'désinscrit', 'wam-newsletter' );
			}

			echo '<tr>';
			echo '<td>' . esc_html( $ligne['email'] ) . '</td>';
			echo '<td>' . esc_html( $ligne['name'] ) . '</td>';
			echo '<td>' . esc_html( self::libelle_statut( $ligne ) ) . '</td>';
			echo '<td>' . esc_html( self::libelle_remise( $ligne['delivery'] ) ) . '</td>';
			echo '<td>' . esc_html( '' !== $ligne['sent_at'] ? mysql2date( 'd/m/Y H:i', $ligne['sent_at'] ) : '' ) . '</td>';
			echo '<td>' . esc_html( $reactions ? implode( ', ', $reactions ) : '-' ) . '</td>';
			// Message du serveur d'envoi, ou à défaut celui du serveur du destinataire
			// (refus, mise en attente) relevé par le suivi de remise.
			echo '<td>' . esc_html( '' !== $ligne['error'] ? $ligne['error'] : $ligne['deliveryMessage'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		self::pagination( $newsletter_id, $filtre, $recherche, $page, $total );
	}

	/** Ce que le serveur du destinataire a fait du message (suivi cPanel). */
	private static function libelle_remise( string $remise ): string {
		$libelles = array(
			\WamNewsletter\Sending\Delivery::DELIVERED => __( 'Remis', 'wam-newsletter' ),
			\WamNewsletter\Sending\Delivery::DEFERRED  => __( 'En attente', 'wam-newsletter' ),
			\WamNewsletter\Sending\Delivery::FAILED    => __( 'Refusé', 'wam-newsletter' ),
		);
		return $libelles[ $remise ] ?? '-';
	}

	private static function libelle_statut( array $ligne ): string {
		$libelles = array(
			Queue::STATUS_SENT    => __( 'Envoyé', 'wam-newsletter' ),
			Queue::STATUS_FAILED  => __( 'Échec', 'wam-newsletter' ),
			Queue::STATUS_PENDING => __( 'En attente', 'wam-newsletter' ),
		);
		$libelle  = $libelles[ $ligne['status'] ] ?? $ligne['status'];

		if ( Queue::STATUS_FAILED === $ligne['status'] && $ligne['attempts'] > 1 ) {
			/* translators: 1: libellé, 2: nombre de tentatives */
			return sprintf( __( '%1$s (%2$d tentatives)', 'wam-newsletter' ), $libelle, $ligne['attempts'] );
		}

		return $libelle;
	}

	private static function pagination( int $newsletter_id, string $filtre, string $recherche, int $page, int $total ): void {
		$pages = (int) ceil( $total / Recipients::PER_PAGE );
		if ( $pages <= 1 ) {
			return;
		}

		$liens = paginate_links(
			array(
				'base'      => self::url(
					$newsletter_id,
					'destinataires',
					array(
						'filtre' => $filtre,
						's'      => $recherche,
						'paged'  => '%#%',
					)
				),
				'format'    => '',
				'current'   => $page,
				'total'     => $pages,
				'type'      => 'plain',
			)
		);

		if ( $liens ) {
			echo '<p class="tablenav-pages" style="margin-top:12px;">' . wp_kses_post( $liens ) . '</p>';
		}
	}

	private static function table_deroule( int $newsletter_id ): void {
		$lignes = Log::query( $newsletter_id );

		if ( ! $lignes ) {
			echo '<p>' . esc_html__( 'Rien au journal pour le moment. Il se remplit tout seul au lancement de l’envoi : un lot traité, une pause, une reprise ou une fin d’envoi y laissent une ligne horodatée.', 'wam-newsletter' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th style="width:11em">' . esc_html__( 'Quand', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:12em">' . esc_html__( 'Événement', 'wam-newsletter' ) . '</th>';
		echo '<th>' . esc_html__( 'Détail', 'wam-newsletter' ) . '</th>';
		echo '<th style="width:16em">' . esc_html__( 'État de la file', 'wam-newsletter' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $lignes as $ligne ) {
			$etat = sprintf(
				/* translators: 1: envoyés, 2: échecs, 3: en attente */
				__( '%1$s envoyés &#183; %2$s échecs &#183; %3$s en attente', 'wam-newsletter' ),
				number_format_i18n( $ligne['sent'] ),
				number_format_i18n( $ligne['failed'] ),
				number_format_i18n( $ligne['pending'] )
			);

			echo '<tr>';
			echo '<td>' . esc_html( mysql2date( 'd/m/Y H:i:s', $ligne['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( Log::label( $ligne['type'] ) ) . '</td>';
			echo '<td>' . esc_html( $ligne['message'] ) . '</td>';
			echo '<td>' . wp_kses_post( $etat ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
