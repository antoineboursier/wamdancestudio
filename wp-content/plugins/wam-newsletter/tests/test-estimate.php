<?php
/**
 * Estimation de la durée d'envoi (étape « Envoyer »), plafond par défaut,
 * courbe des statistiques heure par heure sur 5 jours.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\RestApi;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;

$option  = get_option( 'wam_nl_settings', null );
$horloge = 0;
$filtre  = static function () use ( &$horloge ) {
	return $horloge;
};
add_filter( 'wam_nl_now', $filtre, 20 );
$posts = array();

$a = static function ( int $h, int $m = 0, int $jours = 0 ): int {
	return current_datetime()->modify( "+$jours days" )->setTime( $h, $m, 0 )->getTimestamp();
};

try {
	echo "== Réglages ==\n";
	wam_nl_assert_equals( 150, (int) Settings::defaults()['hourly_cap'], 'plafond par défaut : 150 par heure (30 laissés au site)' );
	wam_nl_assert_equals( 120, Events::HOURLY_SPAN, 'courbe heure par heure sur 5 jours' );

	Settings::update( array( 'hourly_cap' => 150, 'batch_size' => 10, 'batch_interval' => 60, 'quiet_hours' => true, 'quiet_start' => 23, 'quiet_end' => 7 ) );

	echo "== Petits envois ==\n";
	$horloge = $a( 12 );
	wam_nl_assert_equals( '', Scheduler::estimate( 0 )['text'], 'personne : pas de phrase' );
	$e = Scheduler::estimate( 30 );
	wam_nl_assert( ! $e['quiet'], '30 personnes à midi : pas de nuit traversée' );
	wam_nl_assert( false !== strpos( $e['text'], 'environ 5 min' ), '30 personnes : environ 5 min (' . $e['text'] . ')' );
	wam_nl_assert( false !== strpos( $e['text'], 'aujourd’hui vers 12 h' ), 'fin aujourd’hui vers 12 h' );
	wam_nl_assert_equals( 150, $e['per_hour'], 'débit annoncé : le plafond' );

	echo "== Gros envoi lancé le matin ==\n";
	$horloge = $a( 8 );
	$e       = Scheduler::estimate( 1798 );
	wam_nl_assert( ! $e['quiet'], '1 798 à 8 h : tout part dans la journée' );
	wam_nl_assert( $e['end'] >= $a( 19 ) && $e['end'] < $a( 21 ), 'fin entre 19 h et 21 h (' . wp_date( 'H:i', $e['end'] ) . ')' );
	wam_nl_assert( (bool) preg_match( '/environ 1[12] h/', $e['text'] ), 'durée annoncée ≈ 11 à 12 h (' . $e['text'] . ')' );

	echo "== Gros envoi lancé l'après-midi : nuit traversée ==\n";
	$horloge = $a( 14 );
	$e       = Scheduler::estimate( 1798 );
	wam_nl_assert( $e['quiet'], 'la nuit est traversée' );
	wam_nl_assert( $e['end'] > $a( 7, 0, 1 ) && $e['end'] < $a( 12, 0, 1 ), 'fin le lendemain matin (' . wp_date( 'd H:i', $e['end'] ) . ')' );
	wam_nl_assert( false !== strpos( $e['text'], 'Pas d’envoi entre 23 h et 7 h' ), 'la pause de nuit est annoncée' );
	wam_nl_assert( false !== strpos( $e['text'], 'demain vers' ), 'fin annoncée « demain vers… »' );
	wam_nl_assert( $e['sending'] < 13 * HOUR_IN_SECONDS, 'la nuit ne compte pas dans la durée d’envoi' );

	echo "== Lancé pendant les heures calmes ==\n";
	$horloge = $a( 23, 30 );
	$e       = Scheduler::estimate( 30 );
	wam_nl_assert_equals( $a( 7, 0, 1 ), $e['start'], 'premier e-mail à 7 h le lendemain' );
	wam_nl_assert( ! $e['quiet'], 'pas de « pause de nuit » pour un envoi qui commence au réveil' );
	wam_nl_assert( false !== strpos( $e['text'], 'Heures calmes en cours' ), 'le départ différé est annoncé' );

	echo "== Programmé un autre jour ==\n";
	$horloge = $a( 12 );
	$e       = Scheduler::estimate( 30, $a( 10, 0, 3 ) );
	wam_nl_assert( false !== strpos( $e['text'], wp_date( 'l d/m', $a( 10, 0, 3 ) ) ), 'fin datée en toutes lettres (' . $e['text'] . ')' );
	wam_nl_assert_equals( $a( 14, 30 ), Scheduler::parse_local( wp_date( 'Y-m-d', $a( 14 ) ) . ' 14:30:00' ), 'date programmée lue en heure du site' );
	wam_nl_assert( null === Scheduler::parse_local( '' ), 'date vide : maintenant' );

	echo "== Heures calmes coupées ==\n";
	Settings::update( array( 'quiet_hours' => false ) );
	$horloge = $a( 14 );
	$e       = Scheduler::estimate( 1798 );
	wam_nl_assert( ! $e['quiet'], 'sans heures calmes : pas de pause annoncée' );
	wam_nl_assert( $e['end'] < $a( 4, 0, 1 ), 'fin dans la nuit (' . wp_date( 'd H:i', $e['end'] ) . ')' );

	echo "== Route REST de la checklist ==\n";
	Settings::update( array( 'quiet_hours' => true ) );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) $admins[0] );
	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'draft', 'post_title' => 'ZZTest estimation' ) );
	$posts[] = $nl;
	$req     = new WP_REST_Request( 'POST', '/wam-nl/v1/checklist' );
	$req->set_param( 'postId', $nl );
	$req->set_param( 'listIds', array() );
	$req->set_param( 'scheduleAt', wp_date( 'Y-m-d', $a( 10, 0, 2 ) ) . ' 10:00:00' );
	$rep = RestApi::checklist_route( $req );
	$d   = $rep->get_data();
	wam_nl_assert( isset( $d['estimate'] ), 'la checklist porte l’estimation' );
	wam_nl_assert_equals( 0, (int) $d['estimate']['count'], 'sans liste : 0 destinataire' );
	wam_nl_assert_equals( $a( 10, 0, 2 ), (int) $d['estimate']['start'], 'départ = date programmée' );

} finally {
	remove_filter( 'wam_nl_now', $filtre, 20 );
	foreach ( $posts as $id ) {
		wp_delete_post( (int) $id, true );
	}
	wp_set_current_user( 0 );
	if ( null === $option ) {
		delete_option( 'wam_nl_settings' );
	} else {
		update_option( 'wam_nl_settings', $option );
	}
}

wam_nl_test_report();
