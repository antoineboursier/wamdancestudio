<?php
namespace WamNewsletter\Integrations;

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Import des client·es WooCommerce (§4.2).
 *
 * ⚠️ Contexte à connaître avant de toucher à ce fichier.
 *
 * Le §4.2 parlait d'« adhérent·es ayant signé leur accord ». La phase de
 * découverte a établi qu'AUCUN accord de communication n'est enregistré sur ce
 * site : la case à cocher du checkout de wam-custom-plugin valide le formulaire
 * sans rien persister. Antoine a arbitré le 07/10/2026, réserve RGPD exposée :
 * on importe tous les client·es, sans filtre de consentement.
 *
 * Ce que le code garantit en conséquence, et qui n'est PAS négociable :
 *  - consent_source = 'woocommerce' et consent_at = date d'import. On n'invente
 *    aucune date d'accord : l'origine de chaque contact doit rester lisible
 *    des années plus tard ;
 *  - aucun·e désabonné·e n'est jamais réabonné·e, même en repassant commande
 *    (garanti par Subscribers::upsert, appelé sans allow_resubscribe) ;
 *  - idempotence et dédoublonnage sur l'e-mail ;
 *  - mode à blanc disponible, pour voir les volumes avant d'écrire.
 */
class WooCommerceImporter {

	const LIST_NAME = 'Adhérent·es';

	/**
	 * Statuts de commande retenus.
	 *
	 * Une commande annulée ou en échec ne témoigne d'aucune relation réelle :
	 * l'inclure ferait entrer des paniers abandonnés dans la newsletter.
	 */
	const ORDER_STATUSES = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );

	public static function available(): bool {
		return class_exists( 'WooCommerce' );
	}

	/** Vrai si WooCommerce stocke les commandes dans ses propres tables (HPOS). */
	public static function hpos_active(): bool {
		global $wpdb;
		$t = $wpdb->prefix . 'wc_orders';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * Contacts à importer : comptes de rôle « customer » + e-mails de facturation
	 * des commandes retenues.
	 *
	 * Les deux sources sont nécessaires : une bonne partie des inscriptions se
	 * fait sans création de compte, et à l'inverse un compte peut exister sans
	 * commande. Le dédoublonnage se fait sur l'e-mail normalisé, les noms venant
	 * du compte étant prioritaires (ils sont tenus à jour par la personne).
	 *
	 * @return array<string,array{email:string,first_name:string,last_name:string}>
	 */
	public static function collect(): array {
		global $wpdb;
		$contacts = array();

		// 1. E-mails de facturation des commandes.
		if ( self::hpos_active() ) {
			$in     = implode( ',', array_fill( 0, count( self::ORDER_STATUSES ), '%s' ) );
			$t      = $wpdb->prefix . 'wc_orders';
			$ta     = $wpdb->prefix . 'wc_order_addresses';
			$lignes = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT o.billing_email AS email, a.first_name, a.last_name
					 FROM `$t` o
					 LEFT JOIN `$ta` a ON a.order_id = o.id AND a.address_type = 'billing'
					 WHERE o.billing_email <> '' AND o.status IN ($in)
					 ORDER BY o.id ASC",
					self::ORDER_STATUSES
				),
				ARRAY_A
			);
		} else {
			$in     = implode( ',', array_fill( 0, count( self::ORDER_STATUSES ), '%s' ) );
			$lignes = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value AS email,
					        (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_billing_first_name') AS first_name,
					        (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_billing_last_name') AS last_name
					 FROM {$wpdb->posts} p
					 JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_billing_email'
					 WHERE p.post_type = 'shop_order' AND pm.meta_value <> '' AND p.post_status IN ($in)
					 ORDER BY p.ID ASC",
					self::ORDER_STATUSES
				),
				ARRAY_A
			);
		}

		foreach ( $lignes as $r ) {
			$email = Subscribers::normalize_email( $r['email'] );
			if ( '' === $email ) {
				continue;
			}
			// Commandes parcourues par identifiant croissant : la dernière
			// commande gagne, ce qui donne le nom le plus récent.
			$contacts[ $email ] = array(
				'email'      => $email,
				'first_name' => (string) ( $r['first_name'] ?? '' ),
				'last_name'  => (string) ( $r['last_name'] ?? '' ),
			);
		}

		// 2. Comptes de rôle « customer ». Prioritaires sur les noms.
		$comptes = get_users(
			array(
				'role'   => 'customer',
				'fields' => array( 'ID', 'user_email' ),
				'number' => -1,
			)
		);
		foreach ( $comptes as $compte ) {
			$email = Subscribers::normalize_email( $compte->user_email );
			if ( '' === $email ) {
				continue;
			}
			$prenom = (string) get_user_meta( $compte->ID, 'first_name', true );
			$nom    = (string) get_user_meta( $compte->ID, 'last_name', true );
			$connu  = $contacts[ $email ] ?? array(
				'email'      => $email,
				'first_name' => '',
				'last_name'  => '',
			);
			if ( '' !== $prenom ) {
				$connu['first_name'] = $prenom;
			}
			if ( '' !== $nom ) {
				$connu['last_name'] = $nom;
			}
			$contacts[ $email ] = $connu;
		}

		return $contacts;
	}

	/**
	 * @param array $opts dry_run (bool)
	 * @return array{created:int,updated:int,skipped:int,invalid:int,total:int,list:string,list_id:int,errors:string[],dry_run:bool}
	 */
	public static function run( array $opts = array() ): array {
		$a_blanc = ! empty( $opts['dry_run'] );
		$rapport = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'invalid' => 0,
			'total'   => 0,
			'list'    => self::LIST_NAME,
			'list_id' => 0,
			'errors'  => array(),
			'dry_run' => $a_blanc,
		);

		if ( ! self::available() ) {
			$rapport['errors'][] = __( "WooCommerce n'est pas actif : rien à importer.", 'wam-newsletter' );
			return $rapport;
		}

		$contacts         = self::collect();
		$rapport['total'] = count( $contacts );

		if ( ! $contacts ) {
			return $rapport;
		}

		if ( ! $a_blanc ) {
			$rapport['list_id'] = Lists::ensure(
				self::LIST_NAME,
				__( 'Client·es WooCommerce importé·es depuis les commandes et les comptes.', 'wam-newsletter' )
			);
			if ( ! $rapport['list_id'] ) {
				$rapport['errors'][] = __( "La liste « Adhérent·es » n'a pas pu être créée.", 'wam-newsletter' );
				return $rapport;
			}
		}

		// Date d'import, calculée une fois : tous les contacts d'un même import
		// partagent la même date, ce qui rend le lot identifiable a posteriori.
		$maintenant = current_time( 'mysql' );

		foreach ( $contacts as $contact ) {
			if ( $a_blanc ) {
				$existant = Subscribers::find_by_email( $contact['email'] );
				if ( ! $existant ) {
					++$rapport['created'];
				} elseif ( Subscribers::STATUS_UNSUBSCRIBED === $existant['status'] ) {
					++$rapport['skipped'];
				} else {
					++$rapport['updated'];
				}
				continue;
			}

			$resultat = Subscribers::upsert(
				array(
					'email'          => $contact['email'],
					'first_name'     => $contact['first_name'],
					'last_name'      => $contact['last_name'],
					'consent_source' => 'woocommerce',
					'consent_at'     => $maintenant,
					'list_ids'       => array( $rapport['list_id'] ),
				)
			);

			if ( is_wp_error( $resultat ) ) {
				++$rapport['invalid'];
				continue;
			}

			if ( 'created' === $resultat['action'] ) {
				++$rapport['created'];
			} elseif ( 'updated' === $resultat['action'] ) {
				++$rapport['updated'];
			} else {
				++$rapport['skipped'];
			}
		}

		return $rapport;
	}
}
