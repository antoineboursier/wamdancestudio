<?php
/**
 * Désinstallation.
 *
 * Par défaut on ne supprime RIEN : perdre 1 850 abonné·es sur une désactivation
 * malheureuse n'est pas rattrapable. L'effacement complet est un choix explicite,
 * coché dans les réglages (wam_nl_settings['delete_data_on_uninstall']).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wam_nl_reglages = get_option( 'wam_nl_settings', array() );
if ( empty( $wam_nl_reglages['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Ordre inverse des dépendances logiques, par principe.
foreach ( array( 'log', 'events', 'queue', 'list_subscriber', 'lists', 'subscribers' ) as $wam_nl_table ) {
	$wam_nl_nom = $wpdb->prefix . 'wam_nl_' . $wam_nl_table;
	$wpdb->query( "DROP TABLE IF EXISTS `$wam_nl_nom`" );
}

delete_option( 'wam_nl_db_version' );
delete_option( 'wam_nl_settings' );

foreach ( array( 'administrator', 'directrice' ) as $wam_nl_role_nom ) {
	$wam_nl_role = get_role( $wam_nl_role_nom );
	if ( $wam_nl_role ) {
		$wam_nl_role->remove_cap( 'wam_nl_manage' );
	}
}
