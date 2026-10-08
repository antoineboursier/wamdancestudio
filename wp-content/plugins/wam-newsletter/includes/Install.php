<?php
namespace WamNewsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Création et mise à jour du schéma, capacité d'accès.
 *
 * Seul fichier du plugin autorisé à émettre du DDL.
 */
class Install {

	/** Incrémenter à chaque changement de schéma : maybe_upgrade() rejoue alors dbDelta. */
	const DB_VERSION = '1.3.0';

	const DB_VERSION_OPTION = 'wam_nl_db_version';

	const CAPABILITY = 'wam_nl_manage';

	/** Rôles qui reçoivent la capacité. « directrice » est un rôle existant du site. */
	const ROLES = array( 'administrator', 'directrice' );

	public static function table( string $nom ): string {
		global $wpdb;
		return $wpdb->prefix . 'wam_nl_' . $nom;
	}

	public static function activate(): void {
		self::create_tables();
		self::migrate_schema();
		self::add_capability();
		self::seed_smtp_settings();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Recopie une fois la configuration SMTP déjà présente sur le site.
	 *
	 * Le transport est aujourd'hui piloté par wam-custom-plugin ; si cette
	 * extension est un jour désactivée, le plugin doit pouvoir reprendre la main
	 * sans ressaisie. On ne touche qu'aux réglages encore à leur valeur par
	 * défaut, et JAMAIS au mot de passe : il vit dans WAM_NL_SMTP_PASSWORD.
	 */
	private static function seed_smtp_settings(): void {
		$copie = Sending\Mailer::external_smtp_snapshot();
		if ( $copie ) {
			Settings\Settings::seed( $copie );
		}
	}

	/**
	 * Rattrape une mise à jour de code déployée sans réactivation du plugin.
	 * Sans ça, une table ajoutée dans une version ultérieure n'existerait jamais
	 * en production : le déploiement se fait par cp -r, il ne réactive rien.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
		self::migrate_schema();
		self::add_capability();
		self::seed_smtp_settings();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Changements de schéma que dbDelta ne sait pas faire.
	 *
	 * dbDelta compare les définitions de colonnes au texte près, mais il ne
	 * produit JAMAIS d'ALTER pour un passage de NOT NULL à nullable : il faut
	 * l'écrire soi-même. Chaque correctif vérifie l'état courant avant d'agir,
	 * pour rester rejouable sans effet de bord.
	 */
	private static function migrate_schema(): void {
		global $wpdb;

		// token : NOT NULL DEFAULT '' -> nullable.
		// La colonne porte un index UNIQUE. MySQL autorise plusieurs NULL dans un
		// index unique, mais pas deux chaînes vides : avec l'ancienne définition,
		// le deuxième abonné·e inséré sans token levait une erreur de clé
		// dupliquée. Piège qui n'aurait sauté qu'au premier import de masse.
		$table   = self::table( 'subscribers' );
		$colonne = $wpdb->get_row( "SHOW COLUMNS FROM `$table` LIKE 'token'" );
		if ( $colonne && 'NO' === $colonne->Null ) {
			// Les tokens vides existants deviennent NULL, sinon l'ALTER échoue
			// sur la contrainte d'unicité dès qu'il y en a plus d'un.
			$wpdb->query( "UPDATE `$table` SET token = NULL WHERE token = ''" );
			$wpdb->query( "ALTER TABLE `$table` MODIFY token char(64) DEFAULT NULL" );
		}
	}

	/**
	 * Les colonnes de statut sont des varchar et non des enum : dbDelta compare les
	 * définitions au texte près et rejouerait un ALTER TABLE à chaque passage sur un
	 * enum. La liste des valeurs admises est tenue en PHP (repositories, lot 2).
	 */
	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();

		$subscribers     = self::table( 'subscribers' );
		$lists           = self::table( 'lists' );
		$list_subscriber = self::table( 'list_subscriber' );
		$queue           = self::table( 'queue' );
		$events          = self::table( 'events' );
		$log             = self::table( 'log' );

		// email en 190 et non 255 : au-delà, un index UNIQUE dépasse la limite
		// de longueur de clé d'InnoDB en utf8mb4.
		//
		// token nullable et non NOT NULL DEFAULT '' : la colonne porte un index
		// UNIQUE, or MySQL autorise plusieurs NULL dans un index unique mais pas
		// plusieurs chaînes vides. Avec un défaut à '', le deuxième abonné·e
		// inséré sans token aurait déclenché une erreur de clé dupliquée — piège
		// qui n'aurait sauté qu'au premier import de masse.
		dbDelta(
			"CREATE TABLE $subscribers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'subscribed',
			token char(64) DEFAULT NULL,
			consent_at datetime DEFAULT NULL,
			consent_source varchar(50) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			unsubscribed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			UNIQUE KEY token (token),
			KEY status (status)
			) $collate;"
		);

		dbDelta(
			"CREATE TABLE $lists (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			description text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY name (name)
			) $collate;"
		);

		dbDelta(
			"CREATE TABLE $list_subscriber (
			list_id bigint(20) unsigned NOT NULL,
			subscriber_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (list_id,subscriber_id),
			KEY subscriber_id (subscriber_id)
			) $collate;"
		);

		// UNIQUE (newsletter_id, subscriber_id) : garantit au niveau du schéma le
		// dédoublonnage des destinataires exigé par le §8.1 du cahier des charges.
		dbDelta(
			"CREATE TABLE $queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			newsletter_id bigint(20) unsigned NOT NULL,
			subscriber_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY newsletter_subscriber (newsletter_id,subscriber_id),
			KEY newsletter_status (newsletter_id,status)
			) $collate;"
		);

		// Journal du déroulé de l'envoi (démarrage, lot, pause, reprise, fin).
		// Les compteurs sont des colonnes et non du JSON : une ligne de journal
		// doit se lire d'un coup d'œil pendant un incident.
		dbDelta(
			"CREATE TABLE $log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			newsletter_id bigint(20) unsigned NOT NULL,
			type varchar(30) NOT NULL,
			message text NULL,
			sent int(10) unsigned NOT NULL DEFAULT 0,
			failed int(10) unsigned NOT NULL DEFAULT 0,
			pending int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY newsletter_created (newsletter_id,created_at)
			) $collate;"
		);

		dbDelta(
			"CREATE TABLE $events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			newsletter_id bigint(20) unsigned NOT NULL,
			subscriber_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL,
			url text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY newsletter_type (newsletter_id,type),
			KEY subscriber_id (subscriber_id)
			) $collate;"
		);
	}

	/**
	 * get_role() renvoie null si le rôle n'existe pas (site neuf, rôle renommé) :
	 * on teste avant d'appeler add_cap(), sinon l'activation lèverait une erreur
	 * fatale et laisserait le plugin à moitié installé.
	 */
	private static function add_capability(): void {
		foreach ( self::ROLES as $nom ) {
			$role = get_role( $nom );
			if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}
}
