<?php
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Install;

global $wpdb;

echo "== Install : tables et colonnes ==\n";

$attendu = array(
	'subscribers'     => array( 'id', 'email', 'first_name', 'last_name', 'status', 'token', 'consent_at', 'consent_source', 'created_at', 'updated_at', 'unsubscribed_at' ),
	'lists'           => array( 'id', 'name', 'description', 'created_at' ),
	'list_subscriber' => array( 'list_id', 'subscriber_id' ),
	'queue'           => array( 'id', 'newsletter_id', 'subscriber_id', 'status', 'attempts', 'last_error', 'sent_at' ),
	'events'          => array( 'id', 'newsletter_id', 'subscriber_id', 'type', 'url', 'created_at' ),
);

foreach ( $attendu as $court => $colonnes ) {
	$table  = Install::table( $court );
	$existe = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	wam_nl_assert( $existe, "table $table présente" );
	if ( ! $existe ) {
		continue;
	}
	$reelles = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 );
	foreach ( $colonnes as $col ) {
		wam_nl_assert( in_array( $col, $reelles, true ), "  colonne $court.$col" );
	}
}

echo "== Install : index uniques ==\n";

$unique_sur = static function ( string $court, array $colonnes ) use ( $wpdb ): bool {
	$table   = Install::table( $court );
	$index   = $wpdb->get_results( "SHOW INDEX FROM `$table`", ARRAY_A );
	$par_nom = array();
	foreach ( (array) $index as $i ) {
		if ( '0' === (string) $i['Non_unique'] ) {
			$par_nom[ $i['Key_name'] ][ (int) $i['Seq_in_index'] ] = $i['Column_name'];
		}
	}
	foreach ( $par_nom as $cols ) {
		ksort( $cols );
		if ( array_values( $cols ) === $colonnes ) {
			return true;
		}
	}
	return false;
};

wam_nl_assert( $unique_sur( 'subscribers', array( 'email' ) ), 'UNIQUE sur subscribers.email' );
wam_nl_assert( $unique_sur( 'subscribers', array( 'token' ) ), 'UNIQUE sur subscribers.token' );
wam_nl_assert( $unique_sur( 'queue', array( 'newsletter_id', 'subscriber_id' ) ), 'UNIQUE sur queue(newsletter_id, subscriber_id) — dédoublonnage garanti par le schéma' );

echo "== Install : version de schéma ==\n";
wam_nl_assert_equals( Install::DB_VERSION, get_option( Install::DB_VERSION_OPTION ), 'option wam_nl_db_version à jour' );

echo "== Install : capacité ==\n";
foreach ( array( 'administrator', 'directrice' ) as $nom ) {
	$role = get_role( $nom );
	if ( ! $role ) {
		echo "  SKIP rôle $nom absent de ce site\n";
		continue;
	}
	wam_nl_assert( $role->has_cap( Install::CAPABILITY ), "le rôle $nom a " . Install::CAPABILITY );
}
$prof = get_role( 'professeur' );
if ( $prof ) {
	wam_nl_assert( ! $prof->has_cap( Install::CAPABILITY ), "le rôle professeur n'a PAS " . Install::CAPABILITY );
}

wam_nl_test_report();
