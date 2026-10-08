<?php
namespace WamNewsletter\Editor;

use WamNewsletter\Admin\Menu;
use WamNewsletter\Admin\Notices;
use WamNewsletter\Install;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Écran « Newsletters » (§8.5).
 *
 * Colonnes utiles au suivi d'un envoi : objet, statut, listes, progression,
 * échecs. Et surtout l'action **Dupliquer** : c'est elle qui permet de faire une
 * newsletter en vingt minutes à partir de la précédente, ce que le cahier des
 * charges signale comme important.
 */
class NewsletterList {

	const NONCE = 'wam_nl_newsletters';

	public static function register_hooks(): void {
		$pt = NewsletterPostType::POST_TYPE;

		add_filter( 'manage_' . $pt . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . $pt . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_wam_nl_duplicate', array( self::class, 'handle_duplicate' ) );
		add_action( 'admin_action_wam_nl_resume', array( self::class, 'handle_resume' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );

		// L'entrée de menu parente fait doublon avec celle du CPT : WordPress
		// ajoute automatiquement la page parente comme premier sous-menu.
		add_action( 'admin_menu', array( self::class, 'dedupe_menu' ), 999 );

		add_action( 'load-toplevel_page_' . Menu::SLUG, array( self::class, 'redirect_placeholder' ) );

		// Le contenu figé n'a pas à circuler dans l'API REST : il peut peser
		// plusieurs dizaines de kilo-octets et n'intéresse que l'envoi.
		add_action( 'init', array( self::class, 'register_internal_meta' ) );
	}

	public static function register_internal_meta(): void {
		foreach ( array( '_wam_nl_rendered_html', '_wam_nl_sent_at', '_wam_nl_pause_reason', '_wam_nl_retried' ) as $cle ) {
			register_post_meta(
				NewsletterPostType::POST_TYPE,
				$cle,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
					'auth_callback' => static function () {
						return current_user_can( Install::CAPABILITY );
					},
				)
			);
		}
	}

	/** Retire le sous-menu qui répète la page parente. */
	public static function dedupe_menu(): void {
		global $submenu;
		if ( empty( $submenu[ Menu::SLUG ] ) ) {
			return;
		}
		foreach ( $submenu[ Menu::SLUG ] as $index => $entree ) {
			if ( isset( $entree[2] ) && Menu::SLUG === $entree[2] ) {
				unset( $submenu[ Menu::SLUG ][ $index ] );
			}
		}
	}

	/**
	 * La page parente n'affiche rien d'utile : on l'envoie sur la liste des
	 * newsletters, qui est ce qu'on attend en cliquant sur « Newsletter ».
	 */
	public static function redirect_placeholder(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) );
		exit;
	}

	public static function columns( $colonnes ): array {
		return array(
			'cb'            => $colonnes['cb'] ?? '',
			'title'         => __( 'Nom interne', 'wam-newsletter' ),
			'wam_subject'   => __( 'Objet', 'wam-newsletter' ),
			'wam_status'    => __( 'Statut', 'wam-newsletter' ),
			'wam_lists'     => __( 'Listes', 'wam-newsletter' ),
			'wam_progress'  => __( 'Progression', 'wam-newsletter' ),
			'date'          => __( 'Date', 'wam-newsletter' ),
		);
	}

	public static function column( $colonne, $post_id ): void {
		$post_id = (int) $post_id;

		switch ( $colonne ) {
			case 'wam_subject':
				$objet = trim( (string) get_post_meta( $post_id, '_wam_nl_subject', true ) );
				echo '' !== $objet
					? esc_html( $objet )
					: '<span class="wam-nl-manquant">' . esc_html__( 'à renseigner', 'wam-newsletter' ) . '</span>';
				break;

			case 'wam_status':
				$statut = Scheduler::status( $post_id );
				printf(
					'<span class="wam-nl-pastille wam-nl-pastille--%s">%s</span>',
					esc_attr( $statut ),
					esc_html( self::status_label( $statut ) )
				);
				$pause = (string) get_post_meta( $post_id, Scheduler::META_PAUSE, true );
				if ( '' !== $pause ) {
					echo '<br /><span class="wam-nl-raison">' . esc_html( $pause ) . '</span>';
				}
				break;

			case 'wam_lists':
				$noms  = array();
				$toutes = Lists::options();
				foreach ( Scheduler::list_ids( $post_id ) as $id ) {
					if ( isset( $toutes[ $id ] ) ) {
						$noms[] = $toutes[ $id ];
					}
				}
				echo $noms
					? esc_html( implode( ', ', $noms ) )
					: '<span aria-hidden="true">-</span>';
				break;

			case 'wam_progress':
				$counts = Queue::counts( $post_id );
				if ( 0 === $counts['total'] ) {
					echo '<span aria-hidden="true">-</span>';
					break;
				}
				printf(
					'%s / %s',
					esc_html( number_format_i18n( $counts['sent'] ) ),
					esc_html( number_format_i18n( $counts['total'] ) )
				);
				if ( $counts['failed'] > 0 ) {
					printf(
						'<br /><span class="wam-nl-echecs">%s</span>',
						esc_html(
							sprintf(
								/* translators: %s nombre d'échecs */
								_n( '%s échec', '%s échecs', $counts['failed'], 'wam-newsletter' ),
								number_format_i18n( $counts['failed'] )
							)
						)
					);
				}
				break;
		}
	}

	public static function status_label( string $statut ): string {
		$labels = array(
			Scheduler::STATUS_DRAFT     => __( 'Brouillon', 'wam-newsletter' ),
			Scheduler::STATUS_SCHEDULED => __( 'Programmée', 'wam-newsletter' ),
			Scheduler::STATUS_SENDING   => __( 'Envoi en cours', 'wam-newsletter' ),
			Scheduler::STATUS_PAUSED    => __( 'En pause', 'wam-newsletter' ),
			Scheduler::STATUS_SENT      => __( 'Envoyée', 'wam-newsletter' ),
		);
		return $labels[ $statut ] ?? $statut;
	}

	public static function row_actions( $actions, $post ): array {
		if ( ! $post instanceof \WP_Post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		$id = (int) $post->ID;

		$actions['wam_nl_duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::action_url( 'wam_nl_duplicate', $id ) ),
			esc_html__( 'Dupliquer', 'wam-newsletter' )
		);

		if ( Scheduler::STATUS_PAUSED === Scheduler::status( $id ) ) {
			$actions['wam_nl_resume'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::action_url( 'wam_nl_resume', $id ) ),
				esc_html__( 'Reprendre l’envoi', 'wam-newsletter' )
			);
		}

		if ( Queue::counts( $id )['total'] > 0 ) {
			$actions['wam_nl_stats'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( \WamNewsletter\Stats\Screen::url( $id ) ),
				esc_html__( 'Statistiques', 'wam-newsletter' )
			);
		}

		// Le journal apparaît dès qu'il y a quelque chose à y lire — donc dès le
		// premier e-mail de test, qui y laisse une ligne. C'est à ce moment-là
		// qu'on se demande « est-ce que mon test est bien parti ? », et la
		// réponse ne doit pas attendre le lancement d'un envoi réel.
		if ( Queue::counts( $id )['total'] > 0 || \WamNewsletter\Sending\Log::count( $id ) > 0 ) {
			$actions['wam_nl_journal'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( \WamNewsletter\Stats\JournalScreen::url( $id ) ),
				esc_html__( 'Journal d’envoi', 'wam-newsletter' )
			);
		}

		return $actions;
	}

	private static function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'post'   => $id,
				),
				admin_url( 'admin.php' )
			),
			self::NONCE . '_' . $action . '_' . $id
		);
	}

	/**
	 * Duplication (§8.5).
	 *
	 * Le contenu, l'objet, le texte d'aperçu et les listes sont repris. En
	 * revanche le statut, le HTML figé, la file et les dates d'envoi ne le sont
	 * PAS : une copie doit repartir d'un brouillon propre, sinon elle se croirait
	 * déjà envoyée et refuserait tout nouvel envoi.
	 */
	public static function handle_duplicate(): void {
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		if ( ! $id || ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		check_admin_referer( self::NONCE . '_wam_nl_duplicate_' . $id );

		$source = get_post( $id );
		if ( ! $source || NewsletterPostType::POST_TYPE !== $source->post_type ) {
			wp_die( esc_html__( 'Newsletter introuvable.', 'wam-newsletter' ) );
		}

		$copie = wp_insert_post(
			array(
				'post_type'    => NewsletterPostType::POST_TYPE,
				'post_status'  => 'draft',
				/* translators: %s titre d'origine */
				'post_title'   => sprintf( __( '%s (copie)', 'wam-newsletter' ), $source->post_title ),
				'post_content' => $source->post_content,
			),
			true
		);

		if ( is_wp_error( $copie ) ) {
			wp_die( esc_html( $copie->get_error_message() ) );
		}

		foreach ( array( '_wam_nl_subject', '_wam_nl_preheader' ) as $cle ) {
			$valeur = get_post_meta( $id, $cle, true );
			if ( '' !== $valeur ) {
				update_post_meta( $copie, $cle, $valeur );
			}
		}
		$listes = Scheduler::list_ids( $id );
		if ( $listes ) {
			update_post_meta( $copie, '_wam_nl_list_ids', $listes );
		}
		update_post_meta( $copie, Scheduler::META_STATUS, Scheduler::STATUS_DRAFT );

		Notices::success( __( 'Newsletter dupliquée. Vous pouvez la modifier puis l’envoyer.', 'wam-newsletter' ) );
		wp_safe_redirect( admin_url( 'post.php?post=' . $copie . '&action=edit' ) );
		exit;
	}

	public static function handle_resume(): void {
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		if ( ! $id || ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		check_admin_referer( self::NONCE . '_wam_nl_resume_' . $id );

		$resultat = Scheduler::resume( $id );
		if ( is_wp_error( $resultat ) ) {
			Notices::error( $resultat->get_error_message() );
		} else {
			Notices::success( $resultat['message'] );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) );
		exit;
	}

	/** Affiche les messages du plugin sur les écrans du CPT. */
	public static function notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || NewsletterPostType::POST_TYPE !== ( $screen->post_type ?? '' ) ) {
			return;
		}
		Notices::render();
	}
}
