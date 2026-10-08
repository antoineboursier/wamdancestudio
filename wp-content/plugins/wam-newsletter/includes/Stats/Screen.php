<?php
namespace WamNewsletter\Stats;

use WamNewsletter\Admin\Menu;
use WamNewsletter\Editor\NewsletterList;
use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Install;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Statistiques d'une newsletter (§10).
 *
 * Les quatre chiffres fiables (envoyés, échecs, clics, désinscriptions) sont
 * présentés comme tels. Les ouvertures sont affichées avec la mention
 * « indicatif » exigée par le cahier des charges : Apple Mail précharge les
 * images, ce qui gonfle le taux, et d'autres clients les bloquent, ce qui le
 * sous-estime. Annoncer un taux d'ouverture comme une mesure serait faux.
 *
 * Ce que le §10 demande explicitement de NE PAS implémenter, et qui est donc
 * absent : le « scroll » (aucun client mail n'exécute de JavaScript) et
 * l'arrivée en spam (non mesurable depuis l'expéditeur).
 */
class Screen {

	const PAGE_SLUG = 'wam-nl-stats';

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 12 );
	}

	/**
	 * Page sans entrée de menu visible : on y arrive depuis la liste des
	 * newsletters. Une entrée « Statistiques » sans newsletter choisie
	 * n'afficherait rien d'utile.
	 */
	public static function add_page(): void {
		add_submenu_page(
			'',
			__( 'Statistiques de la newsletter', 'wam-newsletter' ),
			__( 'Statistiques', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function url( int $newsletter_id ): string {
		return add_query_arg(
			array(
				'page'       => self::PAGE_SLUG,
				'newsletter' => $newsletter_id,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}

		$id   = isset( $_GET['newsletter'] ) ? (int) $_GET['newsletter'] : 0;
		$post = $id ? get_post( $id ) : null;

		echo '<div class="wrap wam-nl">';

		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			echo '<h1>' . esc_html__( 'Statistiques', 'wam-newsletter' ) . '</h1>';
			echo '<p>' . esc_html__( 'Newsletter introuvable.', 'wam-newsletter' ) . '</p>';
			printf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) ),
				esc_html__( 'Retour aux newsletters', 'wam-newsletter' )
			);
			echo '</div>';
			return;
		}

		$counts  = Queue::counts( $id );
		$statut  = Scheduler::status( $id );
		$sujet   = (string) get_post_meta( $id, '_wam_nl_subject', true );
		$envoyee = (string) get_post_meta( $id, Scheduler::META_SENT_AT, true );

		$clics_total   = Events::count( $id, Events::TYPE_CLICK );
		$clics_uniques = Events::count_unique( $id, Events::TYPE_CLICK );
		$ouvertures    = Events::count_unique( $id, Events::TYPE_OPEN );
		$desinscrits   = Events::count( $id, Events::TYPE_UNSUBSCRIBE );

		echo '<h1>' . esc_html__( 'Statistiques', 'wam-newsletter' ) . '</h1>';
		echo '<p class="description">' . esc_html( $post->post_title );
		if ( '' !== $sujet ) {
			echo ' - ' . esc_html( $sujet );
		}
		echo '</p>';

		printf(
			'<p><span class="wam-nl-pastille wam-nl-pastille--%s">%s</span>',
			esc_attr( $statut ),
			esc_html( NewsletterList::status_label( $statut ) )
		);
		if ( '' !== $envoyee ) {
			$ts = strtotime( $envoyee );
			if ( $ts ) {
				echo ' <span class="description">' . esc_html( sprintf( /* translators: %s date */ __( 'terminée le %s', 'wam-newsletter' ), wp_date( 'd/m/Y H:i', $ts ) ) ) . '</span>';
			}
		}
		echo '</p>';

		// --- Chiffres fiables ---
		echo '<h2>' . esc_html__( 'Envoi', 'wam-newsletter' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:640px"><tbody>';
		self::ligne( __( 'Destinataires', 'wam-newsletter' ), number_format_i18n( $counts['total'] ) );
		self::ligne( __( 'Envoyés', 'wam-newsletter' ), number_format_i18n( $counts['sent'] ) );
		self::ligne( __( 'En attente', 'wam-newsletter' ), number_format_i18n( $counts['pending'] ) );
		self::ligne( __( 'Échecs', 'wam-newsletter' ), number_format_i18n( $counts['failed'] ) );
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Réactions', 'wam-newsletter' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:640px"><tbody>';

		if ( Settings::get( 'track_clicks' ) ) {
			self::ligne(
				__( 'Personnes ayant cliqué', 'wam-newsletter' ),
				number_format_i18n( $clics_uniques ) . self::taux( $clics_uniques, $counts['sent'] )
			);
			self::ligne( __( 'Clics au total', 'wam-newsletter' ), number_format_i18n( $clics_total ) );
		} else {
			self::ligne( __( 'Clics', 'wam-newsletter' ), esc_html__( 'suivi désactivé dans les réglages', 'wam-newsletter' ) );
		}

		if ( Settings::get( 'track_opens' ) ) {
			self::ligne(
				__( 'Ouvertures (indicatif)', 'wam-newsletter' ),
				number_format_i18n( $ouvertures ) . self::taux( $ouvertures, $counts['sent'] )
			);
		} else {
			self::ligne( __( 'Ouvertures', 'wam-newsletter' ), esc_html__( 'suivi désactivé dans les réglages', 'wam-newsletter' ) );
		}

		self::ligne( __( 'Désinscriptions', 'wam-newsletter' ), number_format_i18n( $desinscrits ) );
		echo '</tbody></table>';

		echo '<p class="description" style="max-width:640px">';
		esc_html_e(
			'Le nombre d’ouvertures est indicatif, jamais une mesure : Apple Mail précharge les images (ce qui le gonfle) et d’autres clients les bloquent (ce qui le sous-estime). Les clics et les désinscriptions, eux, sont fiables.',
			'wam-newsletter'
		);
		echo '</p>';

		// --- Liens cliqués ---
		$liens = Events::top_links( $id );
		if ( $liens ) {
			echo '<h2>' . esc_html__( 'Liens les plus cliqués', 'wam-newsletter' ) . '</h2>';
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Lien', 'wam-newsletter' ) . '</th>';
			echo '<th style="width:8em">' . esc_html__( 'Clics', 'wam-newsletter' ) . '</th>';
			echo '<th style="width:10em">' . esc_html__( 'Personnes', 'wam-newsletter' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $liens as $lien ) {
				printf(
					'<tr><td><a href="%1$s" target="_blank" rel="noopener">%1$s</a></td><td>%2$s</td><td>%3$s</td></tr>',
					esc_url( $lien['url'] ),
					esc_html( number_format_i18n( $lien['total'] ) ),
					esc_html( number_format_i18n( $lien['uniques'] ) )
				);
			}
			echo '</tbody></table>';
		}

		// --- Échecs ---
		$echecs = Queue::failures( $id );
		if ( $echecs ) {
			echo '<h2>' . esc_html__( 'Adresses en échec', 'wam-newsletter' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Un échec d’envoi ne veut pas dire que l’adresse est morte : c’est souvent une limite du serveur d’envoi. Aucune adresse n’est marquée en rebond automatiquement.', 'wam-newsletter' ) . '</p>';
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th style="width:24em">' . esc_html__( 'Adresse', 'wam-newsletter' ) . '</th>';
			echo '<th>' . esc_html__( 'Message', 'wam-newsletter' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $echecs as $echec ) {
				printf(
					'<tr><td>%s</td><td>%s</td></tr>',
					esc_html( $echec['email'] ),
					esc_html( $echec['error'] )
				);
			}
			echo '</tbody></table>';
		}

		printf(
			'<p style="margin-top:24px"><a href="%s" class="button">%s</a> <a href="%s" class="button">%s</a></p>',
			esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ),
			esc_html__( 'Ouvrir la newsletter', 'wam-newsletter' ),
			esc_url( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) ),
			esc_html__( 'Retour aux newsletters', 'wam-newsletter' )
		);

		echo '</div>';
	}

	private static function ligne( string $libelle, string $valeur ): void {
		printf(
			'<tr><th scope="row" style="width:16em">%s</th><td>%s</td></tr>',
			esc_html( $libelle ),
			wp_kses_post( $valeur )
		);
	}

	/** Taux entre parenthèses, calculé sur les messages réellement envoyés. */
	private static function taux( int $valeur, int $base ): string {
		if ( $base <= 0 ) {
			return '';
		}
		return ' <span class="description">(' . number_format_i18n( round( $valeur / $base * 100, 1 ), 1 ) . ' %)</span>';
	}
}
