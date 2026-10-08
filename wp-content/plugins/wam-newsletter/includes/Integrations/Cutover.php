<?php
namespace WamNewsletter\Integrations;

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Contrôle de bascule avant retrait de MailPoet (lot 9).
 *
 * Volontairement en LECTURE SEULE : ce fichier ne désactive ni ne supprime
 * jamais rien. Supprimer un plugin de production est une décision qui appartient
 * à Antoine, se prend sur la prod et pas depuis un clone local, et n'est pas
 * réversible une fois les tables effacées. Ce qu'on peut faire ici, c'est
 * vérifier que plus rien ne dépend de MailPoet et dire ce qu'il reste à faire.
 */
class Cutover {

	/**
	 * Rapport de bascule.
	 *
	 * @return array{ready:bool,checks:array<int,array{label:string,ok:bool,detail:string}>,steps:string[]}
	 */
	public static function report(): array {
		$checks = array();

		// 1. MailPoet est-il encore là, et avec combien d'abonné·es ?
		$mailpoet_present = MailPoetMigrator::available();
		$eligibles        = $mailpoet_present ? MailPoetMigrator::eligible_count() : 0;

		$checks[] = array(
			'label'  => __( 'Tables MailPoet encore présentes', 'wam-newsletter' ),
			'ok'     => true,
			'detail' => $mailpoet_present
				? sprintf(
					/* translators: %s nombre d'abonné·es */
					__( 'oui - %s abonné·e(s) au statut « subscribed »', 'wam-newsletter' ),
					number_format_i18n( $eligibles )
				)
				: __( 'non - déjà retirées', 'wam-newsletter' ),
		);

		// 2. La migration a-t-elle tout repris ?
		$migres = self::migrated_count();
		$manque = $mailpoet_present ? self::missing_from_plugin() : 0;

		$checks[] = array(
			'label'  => __( 'Abonné·es MailPoet repris dans le plugin', 'wam-newsletter' ),
			'ok'     => 0 === $manque,
			'detail' => 0 === $manque
				? sprintf(
					/* translators: %s nombre */
					__( 'tous repris (%s portent la source « mailpoet »)', 'wam-newsletter' ),
					number_format_i18n( $migres )
				)
				: sprintf(
					/* translators: %s nombre manquant */
					__( '%s adresse(s) de MailPoet absente(s) du plugin - relancer la migration', 'wam-newsletter' ),
					number_format_i18n( $manque )
				),
		);

		// 3. Les listes utiles existent-elles ?
		$listes_attendues = $mailpoet_present ? wp_list_pluck( MailPoetMigrator::importable_segments(), 'name' ) : array();
		$listes_presentes = array_values( Lists::options() );
		$listes_manquantes = array_values( array_diff( $listes_attendues, $listes_presentes ) );

		$checks[] = array(
			'label'  => __( 'Listes MailPoet recréées', 'wam-newsletter' ),
			'ok'     => empty( $listes_manquantes ),
			'detail' => empty( $listes_manquantes )
				? ( $listes_presentes ? implode( ', ', $listes_presentes ) : __( 'aucune liste', 'wam-newsletter' ) )
				: sprintf(
					/* translators: %s noms de listes */
					__( 'manquante(s) : %s', 'wam-newsletter' ),
					implode( ', ', $listes_manquantes )
				),
		);

		// 4. Y a-t-il de quoi envoyer ?
		$actifs = Subscribers::counts_by_status()[ Subscribers::STATUS_SUBSCRIBED ] ?? 0;
		$checks[] = array(
			'label'  => __( 'Abonné·es actif·ves dans le plugin', 'wam-newsletter' ),
			'ok'     => $actifs > 0,
			'detail' => sprintf(
				/* translators: %s nombre */
				__( '%s personne(s)', 'wam-newsletter' ),
				number_format_i18n( $actifs )
			),
		);

		// 5. MailPoet est-il encore actif ? S'il l'est, il détourne wp_mail.
		$actif = self::mailpoet_active();
		$checks[] = array(
			'label'  => __( 'MailPoet désactivé', 'wam-newsletter' ),
			'ok'     => ! $actif,
			'detail' => $actif
				// Découverte du lot 1 : MailPoet remplace l'objet $phpmailer de
				// WordPress, et son send() surchargé ignore le mode d'envoi. Tant
				// qu'il est actif, un envoi peut être annoncé réussi sans partir.
				? __( 'encore actif - il remplace l’objet PHPMailer de WordPress et peut faire croire à un envoi réussi', 'wam-newsletter' )
				: __( 'désactivé', 'wam-newsletter' ),
		);

		// 6. Le plugin dépend-il encore de MailPoet pour fonctionner ?
		$checks[] = array(
			'label'  => __( 'Aucune dépendance de fonctionnement à MailPoet', 'wam-newsletter' ),
			'ok'     => true,
			'detail' => __( 'les tables MailPoet ne sont lues que par la migration et la sauvegarde, jamais à l’envoi', 'wam-newsletter' ),
		);

		$bloquants = array_filter(
			$checks,
			static function ( $check ) {
				return empty( $check['ok'] );
			}
		);

		return array(
			'ready'  => 0 === count( $bloquants ),
			'checks' => $checks,
			'steps'  => self::steps(),
		);
	}

	/** Marche à suivre, dans l'ordre. */
	public static function steps(): array {
		return array
		(
			__( '1. Télécharger la sauvegarde CSV des listes MailPoet (bouton dans Abonné·es → Migration, ou wp wam-nl backup-mailpoet --path=…).', 'wam-newsletter' ),
			__( '2. Faire une sauvegarde complète WPvivid de la production.', 'wam-newsletter' ),
			__( '3. Vérifier que ce rapport est entièrement au vert sur la PRODUCTION, pas seulement en local.', 'wam-newsletter' ),
			__( '4. Envoyer une vraie newsletter avec ce plugin et contrôler la réception avant de toucher à MailPoet.', 'wam-newsletter' ),
			__( '5. Désactiver MailPoet, puis attendre quelques jours sans le supprimer.', 'wam-newsletter' ),
			__( '6. Supprimer MailPoet. Ses tables partent avec lui : la sauvegarde de l’étape 1 devient la seule trace.', 'wam-newsletter' ),
		);
	}

	public static function mailpoet_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'mailpoet/mailpoet.php' );
	}

	/** Nombre de contacts portant la source « mailpoet ». */
	public static function migrated_count(): int {
		global $wpdb;
		$t = Subscribers::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE consent_source = %s", 'mailpoet' ) );
	}

	/**
	 * Adresses MailPoet « subscribed » absentes de nos tables.
	 *
	 * Comparaison en SQL plutôt qu'en PHP : 1 843 adresses d'un côté, 2 567 de
	 * l'autre, une double boucle serait inutilement lente sur un mutualisé.
	 */
	public static function missing_from_plugin(): int {
		if ( ! MailPoetMigrator::available() ) {
			return 0;
		}
		global $wpdb;
		$nous = Subscribers::table();
		$eux  = $wpdb->prefix . 'mailpoet_subscribers';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$eux` mp
				 LEFT JOIN `$nous` s ON s.email = LOWER(mp.email)
				 WHERE mp.status = %s AND mp.deleted_at IS NULL AND s.id IS NULL",
				'subscribed'
			)
		);
	}
}
