<?php
/**
 * API des chiffres clés (wam-nl/v1/kpis) : droits, calculs, absence de données
 * personnelles.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subs;

$domaine = '@wam-nl-test.invalid';
$posts   = array();
$abonnes = array();
$liste   = 0;
$ancien  = get_current_user_id();

$appel = static function ( string $route, array $params = array() ) {
	$requete = new WP_REST_Request( 'GET', $route );
	foreach ( $params as $k => $v ) {
		$requete->set_param( $k, $v );
	}
	return rest_do_request( $requete );
};

try {
	global $wpdb;

	$liste = (int) Lists::ensure( 'ZZTest KPIs' );
	foreach ( array( 'a', 'b', 'c', 'd' ) as $l ) {
		$id        = Subs::insert( array( 'email' => 'kpi-' . $l . $domaine, 'first_name' => 'Secret' . $l, 'consent_source' => 'admin' ) );
		$abonnes[] = $id;
	}

	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'publish', 'post_title' => 'ZZTest KPIs' ) );
	$posts[] = $nl;
	update_post_meta( $nl, '_wam_nl_subject', 'Objet KPIs' );
	update_post_meta( $nl, '_wam_nl_list_ids', array( $liste ) );
	update_post_meta( $nl, Scheduler::META_STATUS, Scheduler::STATUS_SENT );
	update_post_meta( $nl, Scheduler::META_SENT_AT, '2099-01-01 10:00:00' );

	// 4 envoyés ; a et b ouvrent ; b clique deux fois ; c se désinscrit.
	foreach ( $abonnes as $id ) {
		$wpdb->insert( Queue::table(), array( 'newsletter_id' => $nl, 'subscriber_id' => $id, 'status' => Queue::STATUS_SENT ) );
	}
	Events::record( $nl, $abonnes[0], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[1], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[1], Events::TYPE_CLICK, 'https://exemple.test/x' );
	Events::record( $nl, $abonnes[1], Events::TYPE_CLICK, 'https://exemple.test/x' );
	Events::record( $nl, $abonnes[2], Events::TYPE_UNSUBSCRIBE );

	echo "== Droits ==\n";
	wp_set_current_user( 0 );
	wam_nl_assert_equals( 401, $appel( '/wam-nl/v1/kpis' )->get_status(), 'anonyme : refusé' );

	wp_set_current_user( 1 );
	$r = $appel( '/wam-nl/v1/kpis', array( 'limit' => 100 ) );
	wam_nl_assert_equals( 200, $r->get_status(), 'administrateur : autorisé' );
	$data = $r->get_data();

	echo "== Vue d'ensemble ==\n";
	foreach ( array( 'generated_at', 'tracking', 'subscribers', 'lists', 'totals', 'newsletters' ) as $cle ) {
		wam_nl_assert( array_key_exists( $cle, $data ), "bloc « $cle » présent" );
	}
	$noms = wp_list_pluck( $data['lists'], 'subscribers', 'name' );
	wam_nl_assert( array_key_exists( 'ZZTest KPIs', $noms ), 'la liste de test figure dans « lists »' );
	wam_nl_assert( $data['totals']['sent'] >= 4, 'le cumul compte les envois de test' );

	$trouvee = null;
	foreach ( $data['newsletters'] as $n ) {
		if ( $nl === $n['id'] ) {
			$trouvee = $n;
		}
	}
	wam_nl_assert( null !== $trouvee, 'la newsletter envoyée est listée' );
	wam_nl_assert_equals( $nl, $data['newsletters'][0]['id'] ?? 0, 'la plus récemment envoyée en premier' );

	echo "== Chiffres d'une newsletter ==\n";
	$d = $appel( '/wam-nl/v1/kpis/newsletters/' . $nl )->get_data();
	wam_nl_assert_equals( 'Objet KPIs', $d['subject'], 'objet' );
	wam_nl_assert_equals( 4, $d['recipients']['sent'], '4 envoyés' );
	wam_nl_assert_equals( 0.5, $d['opens']['rate'] ?? 0.5, '2 ouvertures sur 4 = 0.5 (ou suivi coupé)' );
	if ( null !== $d['clicks']['unique'] ) {
		wam_nl_assert_equals( 1, $d['clicks']['unique'], '1 personne a cliqué' );
		wam_nl_assert_equals( 2, $d['clicks']['total'], '2 clics au total' );
		wam_nl_assert_equals( 0.25, $d['clicks']['rate'], 'taux de clic 0.25' );
	}
	wam_nl_assert_equals( 1, $d['unsubscribes']['count'], '1 désinscription' );
	wam_nl_assert_equals( 0.25, $d['unsubscribes']['rate'], 'taux de désinscription 0.25' );
	wam_nl_assert_equals( 'ZZTest KPIs', $d['lists'][0]['name'] ?? '', 'liste ciblée nommée' );
	wam_nl_assert( isset( $d['top_links'], $d['timeline'] ), 'détail : liens et chronologie' );
	wam_nl_assert( 0 === strpos( (string) $d['sent_at'], '2099-01-01T10:00:00' ), 'date d’envoi au format ISO 8601' );

	echo "== Aucune donnée personnelle ==\n";
	$json = wp_json_encode( array( $data, $d ) );
	wam_nl_assert( false === strpos( $json, $domaine ) && false === strpos( $json, 'Secret' ), 'ni e-mail ni prénom dans les réponses' );

	echo "== Newsletter inconnue ==\n";
	wam_nl_assert_equals( 404, $appel( '/wam-nl/v1/kpis/newsletters/999999999' )->get_status(), '404' );

} finally {
	wp_set_current_user( $ancien );
	foreach ( $posts as $id ) {
		Queue::clear( (int) $id );
		Events::purge_newsletter( (int) $id );
		wp_delete_post( (int) $id, true );
	}
	foreach ( $abonnes as $id ) {
		Subs::delete( (int) $id );
	}
	if ( $liste ) {
		Lists::delete( $liste );
	}
}

wam_nl_test_report();
