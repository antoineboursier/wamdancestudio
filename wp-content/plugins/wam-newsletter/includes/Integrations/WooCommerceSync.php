<?php
namespace WamNewsletter\Integrations;

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Quality;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Alimentation automatique de la liste « Adhérent·es » depuis WooCommerce.
 *
 * Chaque commande payée ajoute l'adresse de facturation à la liste, comme le
 * fait l'import manuel (même liste, même source « woocommerce », même décision
 * d'Antoine du 07/10/2026 : tou·tes les client·es, sans consentement explicite).
 * Les réservations Bookly passent par WooCommerce : elles sont couvertes.
 *
 * Garanties, identiques à l'import :
 *  - un·e désabonné·e n'est JAMAIS réabonné·e (garanti dans Repository::upsert) ;
 *  - robots, adresses techniques, domaines sans messagerie sont écartés ;
 *  - les fautes de domaine évidentes (gmai.com…) sont corrigées avant écriture ;
 *  - idempotent : la même commande rejouée ne change rien.
 *
 * ⚠️ Une erreur ici ne doit JAMAIS gêner une commande : le hook s'exécute au
 * moment du paiement, souvent depuis un webhook (HelloAsso). Tout est enveloppé.
 */
class WooCommerceSync {

	/** Statuts « payé » : les mêmes que l'import manuel (WooCommerceImporter). */
	const HOOKS = array(
		'woocommerce_order_status_completed',
		'woocommerce_order_status_processing',
		'woocommerce_order_status_on-hold',
	);

	public static function register_hooks(): void {
		foreach ( self::HOOKS as $hook ) {
			add_action( $hook, array( self::class, 'handle' ), 20, 1 );
		}
	}

	/** @param int|string $order_id */
	public static function handle( $order_id ): void {
		if ( ! Settings::get( 'woo_auto_sync' ) ) {
			return;
		}
		try {
			self::sync_order( (int) $order_id );
		} catch ( \Throwable $e ) {
			// Silencieux pour la cliente, tracé pour nous.
			error_log( 'wam-newsletter : synchro WooCommerce de la commande ' . (int) $order_id . ' en échec : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Ajoute l'adresse de la commande à la liste.
	 *
	 * @return array{status:string,detail:string}
	 *         status : absent | rejected | created | updated | unchanged
	 */
	public static function sync_order( int $order_id ): array {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return array( 'status' => 'absent', 'detail' => 'WooCommerce inactif' );
		}
		$commande = wc_get_order( $order_id );
		if ( ! $commande ) {
			return array( 'status' => 'absent', 'detail' => 'commande introuvable' );
		}

		$email = (string) $commande->get_billing_email();
		if ( '' === trim( $email ) ) {
			return array( 'status' => 'rejected', 'detail' => 'pas d’adresse' );
		}

		$prenom  = (string) $commande->get_billing_first_name();
		$nom     = (string) $commande->get_billing_last_name();
		$verdict = Quality::assess( $email, $prenom, $nom );

		if ( Quality::REJECT === $verdict['verdict'] ) {
			return array( 'status' => 'rejected', 'detail' => $verdict['reason'] );
		}

		$liste = Lists::ensure(
			WooCommerceImporter::LIST_NAME,
			__( 'Client·es WooCommerce importé·es depuis les commandes et les comptes.', 'wam-newsletter' )
		);

		$resultat = Subscribers::upsert(
			array(
				'email'          => $verdict['email'],
				'first_name'     => $prenom,
				'last_name'      => $nom,
				'consent_source' => 'woocommerce',
				'consent_at'     => current_time( 'mysql' ),
				'list_ids'       => $liste ? array( $liste ) : array(),
			)
		);

		if ( is_wp_error( $resultat ) ) {
			return array( 'status' => 'rejected', 'detail' => $resultat->get_error_message() );
		}

		return array(
			'status' => (string) $resultat['action'],
			'detail' => '',
		);
	}
}
