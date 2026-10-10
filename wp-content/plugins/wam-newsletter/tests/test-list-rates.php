<?php
/**
 * Colonnes « Ouvertures » et « Clics » de la liste des newsletters.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\NewsletterList;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subs;

global $wpdb;
$option  = get_option( 'wam_nl_settings', null );
$domaine = '@wam-nl-test.invalid';
$posts   = array();
$abonnes = array();
$q       = Queue::table();

$rendu = static function ( string $colonne, int $id ): string {
	ob_start();
	NewsletterList::column( $colonne, $id );
	return (string) ob_get_clean();
};

try {
	wam_nl_assert( isset( NewsletterList::columns( array( 'cb' => '' ) )['wam_opens'], NewsletterList::columns( array() )['wam_clicks'] ), 'les deux colonnes existent' );

	Settings::update( array( 'track_opens' => true, 'track_clicks' => true ) );

	// 4 envoyés : 2 ouvertures (dont une personne qui ouvre 3 fois), 1 clic.
	for ( $i = 0; $i < 4; $i++ ) {
		$abonnes[] = (int) Subs::insert( array( 'email' => "taux-$i" . $domaine, 'consent_source' => 'admin' ) );
	}
	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'publish', 'post_title' => 'ZZTest taux' ) );
	$posts[] = $nl;
	foreach ( $abonnes as $id ) {
		$wpdb->insert( $q, array( 'newsletter_id' => $nl, 'subscriber_id' => $id, 'status' => Queue::STATUS_SENT, 'attempts' => 1, 'sent_at' => current_time( 'mysql' ) ) );
	}
	update_post_meta( $nl, Scheduler::META_STATUS, Scheduler::STATUS_SENT );
	Events::record( $nl, $abonnes[0], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[0], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[0], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[1], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[1], Events::TYPE_CLICK, 'https://exemple.test/a' );

	$o = $rendu( 'wam_opens', $nl );
	wam_nl_assert( false !== strpos( $o, '50,0' ), 'ouvertures : 2 personnes sur 4 = 50 % (' . wp_strip_all_tags( $o ) . ')' );
	wam_nl_assert( false !== strpos( $o, '2 personnes' ), 'le détail compte les personnes, pas les ouvertures' );
	$c = $rendu( 'wam_clicks', $nl );
	wam_nl_assert( false !== strpos( $c, '25,0' ), 'clics : 1 personne sur 4 = 25 % (' . wp_strip_all_tags( $c ) . ')' );
	wam_nl_assert( false !== strpos( $c, '1 personne' ), 'singulier à une personne' );

	Settings::update( array( 'track_opens' => false ) );
	wam_nl_assert( false === strpos( $rendu( 'wam_opens', $nl ), '%' ), 'suivi des ouvertures coupé : pas de taux' );
	wam_nl_assert( false !== strpos( $rendu( 'wam_clicks', $nl ), '25,0' ), 'le taux de clics reste affiché' );
	Settings::update( array( 'track_opens' => true ) );

	update_post_meta( $nl, Scheduler::META_STATUS, Scheduler::STATUS_DRAFT );
	wam_nl_assert( false === strpos( $rendu( 'wam_opens', $nl ), '%' ), 'brouillon : pas de taux' );
	update_post_meta( $nl, Scheduler::META_STATUS, Scheduler::STATUS_SENDING );
	wam_nl_assert( false !== strpos( $rendu( 'wam_opens', $nl ), '50,0' ), 'envoi en cours : taux affiché' );

	$vide    = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'publish', 'post_title' => 'ZZTest taux vide' ) );
	$posts[] = $vide;
	update_post_meta( $vide, Scheduler::META_STATUS, Scheduler::STATUS_SENT );
	wam_nl_assert( false === strpos( $rendu( 'wam_opens', $vide ), '%' ), 'aucun envoi : pas de division par zéro' );
} finally {
	foreach ( $posts as $id ) {
		Queue::clear( (int) $id );
		Events::purge_newsletter( (int) $id );
		wp_delete_post( (int) $id, true );
	}
	foreach ( $abonnes as $id ) {
		Subs::delete( (int) $id );
	}
	if ( null === $option ) {
		delete_option( 'wam_nl_settings' );
	} else {
		update_option( 'wam_nl_settings', $option );
	}
}

wam_nl_test_report();
