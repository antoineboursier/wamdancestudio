<?php
namespace WamNewsletter\CLI;

use WamNewsletter\Integrations\Cutover;
use WamNewsletter\Integrations\MailPoetMigrator;
use WamNewsletter\Integrations\WooCommerceImporter;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Subscribers\Csv;
use WamNewsletter\Subscribers\Repository as Subscribers;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Commandes WP-CLI du plugin (§4.1, §4.2).
 *
 * La ligne de commande est le chemin recommandé pour les imports de masse : sur
 * un mutualisé, 1 850 insertions dans une requête HTTP finissent par taper dans
 * le temps d'exécution maximal, alors qu'en CLI il n'y a pas de limite.
 */
class Commands {

	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		WP_CLI::add_command( 'wam-nl migrate-mailpoet', array( self::class, 'migrate_mailpoet' ) );
		WP_CLI::add_command( 'wam-nl import-woocommerce', array( self::class, 'import_woocommerce' ) );
		WP_CLI::add_command( 'wam-nl export', array( self::class, 'export' ) );
		WP_CLI::add_command( 'wam-nl backup-mailpoet', array( self::class, 'backup_mailpoet' ) );
		WP_CLI::add_command( 'wam-nl cutover-check', array( self::class, 'cutover_check' ) );
		WP_CLI::add_command( 'wam-nl stats', array( self::class, 'stats' ) );
	}

	/**
	 * Vérifie qu'on peut retirer MailPoet sans rien perdre (lot 9).
	 *
	 * Ne désactive et ne supprime rien : la suppression d'un plugin de
	 * production se décide à la main, sur la production, et n'est pas réversible
	 * une fois ses tables effacées.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wam-nl cutover-check
	 */
	public static function cutover_check(): void {
		$rapport = Cutover::report();

		WP_CLI::log( 'Contrôles :' );
		foreach ( $rapport['checks'] as $check ) {
			WP_CLI::log( sprintf( '  [%s] %s - %s', $check['ok'] ? 'OK' : '!!', $check['label'], $check['detail'] ) );
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Marche à suivre :' );
		foreach ( $rapport['steps'] as $etape ) {
			WP_CLI::log( '  ' . $etape );
		}

		WP_CLI::log( '' );
		if ( $rapport['ready'] ) {
			WP_CLI::success( 'Tous les contrôles passent. La bascule peut être engagée à la main, en suivant les étapes ci-dessus.' );
			return;
		}
		WP_CLI::warning( 'Des contrôles ne passent pas : ne retirez pas MailPoet en l’état.' );
	}

	/**
	 * Migre les abonné·es depuis MailPoet.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Compte sans rien écrire.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wam-nl migrate-mailpoet --dry-run
	 *     wp wam-nl migrate-mailpoet
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public static function migrate_mailpoet( $args, $assoc_args ): void {
		$a_blanc = isset( $assoc_args['dry-run'] );

		if ( ! MailPoetMigrator::available() ) {
			WP_CLI::error( 'Tables MailPoet introuvables.' );
		}

		WP_CLI::log( sprintf( 'Abonné·es MailPoet éligibles (statut subscribed) : %d', MailPoetMigrator::eligible_count() ) );
		foreach ( MailPoetMigrator::importable_segments() as $segment ) {
			WP_CLI::log( sprintf( '  liste à recréer : %s', $segment['name'] ) );
		}

		$rapport = MailPoetMigrator::run( array( 'dry_run' => $a_blanc ) );

		foreach ( $rapport['errors'] as $erreur ) {
			WP_CLI::warning( $erreur );
		}

		self::print_report( $rapport );
		foreach ( $rapport['lists'] as $nom => $nombre ) {
			WP_CLI::log( sprintf( '  %s : %d', $nom, $nombre ) );
		}

		if ( $a_blanc ) {
			WP_CLI::success( 'Simulation terminée, rien n’a été écrit.' );
			return;
		}
		WP_CLI::success( 'Migration terminée.' );
	}

	/**
	 * Importe les client·es WooCommerce.
	 *
	 * Rappel : aucun accord de communication n'est enregistré sur ce site. Cet
	 * import reprend tou·tes les client·es, sur décision du 07/10/2026. Les
	 * désabonné·es ne sont jamais réabonné·es.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Compte sans rien écrire.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wam-nl import-woocommerce --dry-run
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public static function import_woocommerce( $args, $assoc_args ): void {
		$a_blanc = isset( $assoc_args['dry-run'] );

		if ( ! WooCommerceImporter::available() ) {
			WP_CLI::error( 'WooCommerce n’est pas actif.' );
		}

		$rapport = WooCommerceImporter::run( array( 'dry_run' => $a_blanc ) );

		foreach ( $rapport['errors'] as $erreur ) {
			WP_CLI::warning( $erreur );
		}

		WP_CLI::log( sprintf( 'Liste cible : %s', $rapport['list'] ) );
		self::print_report( $rapport );

		if ( $a_blanc ) {
			WP_CLI::success( 'Simulation terminée, rien n’a été écrit.' );
			return;
		}
		WP_CLI::success( 'Import terminé.' );
	}

	/**
	 * Exporte les abonné·es au format CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<statut>]
	 * : subscribed, unsubscribed, bounced ou trashed.
	 *
	 * [--list=<id>]
	 * : Identifiant de liste.
	 *
	 * [--path=<fichier>]
	 * : Fichier de destination. Sans ce réglage, le CSV part sur la sortie standard.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public static function export( $args, $assoc_args ): void {
		$statut = isset( $assoc_args['status'] ) ? (string) $assoc_args['status'] : '';
		$csv    = Csv::export(
			array(
				'status'  => in_array( $statut, Subscribers::STATUSES, true ) ? array( $statut ) : array(),
				'list_id' => isset( $assoc_args['list'] ) ? (int) $assoc_args['list'] : 0,
			)
		);

		if ( empty( $assoc_args['path'] ) ) {
			WP_CLI::line( $csv );
			return;
		}

		$chemin = (string) $assoc_args['path'];
		if ( false === file_put_contents( $chemin, $csv ) ) {
			WP_CLI::error( sprintf( 'Écriture impossible dans %s', $chemin ) );
		}
		WP_CLI::success( sprintf( 'Export écrit dans %s', $chemin ) );
	}

	/**
	 * Écrit une sauvegarde CSV des listes MailPoet, avant la bascule (lot 9).
	 *
	 * ## OPTIONS
	 *
	 * [--path=<fichier>]
	 * : Fichier de destination. Sans ce réglage, le CSV part sur la sortie standard.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public static function backup_mailpoet( $args, $assoc_args ): void {
		if ( ! MailPoetMigrator::available() ) {
			WP_CLI::error( 'Tables MailPoet introuvables.' );
		}
		$csv = MailPoetMigrator::export_backup();

		if ( empty( $assoc_args['path'] ) ) {
			WP_CLI::line( $csv );
			return;
		}

		$chemin = (string) $assoc_args['path'];
		if ( false === file_put_contents( $chemin, $csv ) ) {
			WP_CLI::error( sprintf( 'Écriture impossible dans %s', $chemin ) );
		}
		WP_CLI::success( sprintf( 'Sauvegarde écrite dans %s', $chemin ) );
	}

	/** Affiche les compteurs du plugin. */
	public static function stats(): void {
		$counts = Subscribers::counts_by_status();
		WP_CLI::log( 'Abonné·es par statut :' );
		foreach ( $counts as $statut => $nombre ) {
			WP_CLI::log( sprintf( '  %-14s %d', $statut, $nombre ) );
		}
		WP_CLI::log( 'Listes :' );
		foreach ( Lists::all() as $liste ) {
			WP_CLI::log( sprintf( '  [%d] %-32s %d actif·ves', $liste['id'], $liste['name'], $liste['subscriber_count'] ) );
		}
	}

	private static function print_report( array $rapport ): void {
		WP_CLI::log(
			sprintf(
				'Traité·es : %d - créé·es : %d, mis·es à jour : %d, inchangé·es : %d, invalides : %d',
				$rapport['total'],
				$rapport['created'],
				$rapport['updated'],
				$rapport['skipped'],
				$rapport['invalid']
			)
		);
	}
}
