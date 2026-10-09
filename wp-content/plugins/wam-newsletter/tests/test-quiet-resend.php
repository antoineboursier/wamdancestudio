<?php
/**
 * Heures calmes (pas d'envoi la nuit) et renvoi automatique des « sans trace ».
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Delivery;
use WamNewsletter\Sending\Log;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subs;

global $wpdb;
$domaine = '@wam-nl-test.invalid';
$option  = get_option( 'wam_nl_settings', null );
$posts   = array();
$abonnes = array();
$liste   = 0;
$q       = Queue::table();
$horloge = 0;
$filtre  = static function () use ( &$horloge ) {
	return $horloge;
};
add_filter( 'wam_nl_now', $filtre, 20 );

/** Horodatage d'aujourd'hui (heure du site) à H:M. */
$a = static function ( int $h, int $m = 0 ): int {
	return current_datetime()->setTime( $h, $m, 0 )->getTimestamp();
};

try {
	echo "== Plage horaire ==\n";
	$r = array( 'quiet_hours' => true, 'quiet_start' => 23, 'quiet_end' => 7 );
	wam_nl_assert( (bool) Settings::defaults()['quiet_hours'], 'heures calmes actives par défaut' );
	wam_nl_assert_equals( 23, (int) Settings::defaults()['quiet_start'], 'début par défaut : 23 h' );
	wam_nl_assert_equals( 7, (int) Settings::defaults()['quiet_end'], 'reprise par défaut : 7 h' );

	$horloge = $a( 12 );
	wam_nl_assert_equals( 0, Scheduler::quiet_until( $r ), '12 h : on envoie' );
	$horloge = $a( 22, 59 );
	wam_nl_assert_equals( 0, Scheduler::quiet_until( $r ), '22 h 59 : on envoie encore' );
	$horloge = $a( 23, 30 );
	wam_nl_assert_equals( $a( 7 ) + DAY_IN_SECONDS, Scheduler::quiet_until( $r ), '23 h 30 : veille jusqu’à 7 h le lendemain' );
	$horloge = $a( 3 );
	wam_nl_assert_equals( $a( 7 ), Scheduler::quiet_until( $r ), '3 h : veille jusqu’à 7 h le jour même' );
	$horloge = $a( 7 );
	wam_nl_assert_equals( 0, Scheduler::quiet_until( $r ), '7 h pile : l’envoi reprend' );
	$horloge = $a( 13 );
	wam_nl_assert_equals( $a( 14 ), Scheduler::quiet_until( array( 'quiet_hours' => true, 'quiet_start' => 12, 'quiet_end' => 14 ) ), 'plage dans la journée (12 h → 14 h)' );
	$horloge = $a( 3 );
	wam_nl_assert_equals( 0, Scheduler::quiet_until( array( 'quiet_hours' => false ) + $r ), 'désactivées : on envoie la nuit' );
	wam_nl_assert_equals( 0, Scheduler::quiet_until( array( 'quiet_hours' => true, 'quiet_start' => 5, 'quiet_end' => 5 ) ), 'début = fin : désactivées' );

	echo "== Réglages enregistrés ==\n";
	Settings::update( array( 'quiet_start' => 40, 'quiet_end' => -3 ) );
	wam_nl_assert_equals( 23, (int) Settings::get( 'quiet_start' ), 'heure bornée à 23' );
	wam_nl_assert_equals( 0, (int) Settings::get( 'quiet_end' ), 'heure bornée à 0' );

	echo "== Envoi lancé la nuit : veille, puis reprise ==\n";
	$liste = (int) Lists::ensure( 'ZZTest heures calmes' );
	for ( $i = 0; $i < 4; $i++ ) {
		$abonnes[] = (int) Subs::insert( array( 'email' => "calme-$i" . $domaine, 'consent_source' => 'admin', 'list_ids' => array( $liste ) ) );
	}
	foreach ( $abonnes as $id ) {
		Subs::add_to_lists( $id, array( $liste ) );
	}
	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'draft', 'post_title' => 'ZZTest heures calmes', 'post_content' => '<!-- wp:paragraph --><p>Bonjour {prenom}</p><!-- /wp:paragraph --><!-- wp:wam-nl/footer /-->' ) );
	$posts[] = $nl;
	update_post_meta( $nl, '_wam_nl_subject', 'ZZTest heures calmes' );
	update_post_meta( $nl, '_wam_nl_list_ids', array( $liste ) );
	Settings::update( array( 'quiet_hours' => true, 'quiet_start' => 23, 'quiet_end' => 7, 'hourly_cap' => 1000, 'batch_size' => 20, 'fail_threshold_batch' => 100, 'fail_threshold_hour' => 100, 'auto_resend' => true ) );

	$horloge = $a( 23, 30 );
	$res     = Scheduler::start( $nl );
	wam_nl_assert( ! is_wp_error( $res ), 'envoi lancé à 23 h 30' );
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 0, (int) $c['sent'], 'rien n’est parti la nuit' );
	wam_nl_assert_equals( 4, (int) $c['pending'], 'tout reste en attente' );
	$journal = Log::query( $nl );
	wam_nl_assert( false !== strpos( (string) $journal[0]['message'], 'Heures calmes' ), 'le journal explique la veille' );
	if ( function_exists( 'as_next_scheduled_action' ) ) {
		wam_nl_assert_equals( $a( 7 ) + DAY_IN_SECONDS, (int) as_next_scheduled_action( Scheduler::HOOK, array( $nl ), Scheduler::GROUP ), 'reprise planifiée à 7 h' );
	}

	$horloge = $a( 7, 1 );
	Scheduler::process( $nl );
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 4, (int) $c['sent'], 'à 7 h, tout part' );
	wam_nl_assert_equals( Scheduler::STATUS_SENT, Scheduler::status( $nl ), 'newsletter terminée' );

	echo "== Renvoi automatique ==\n";
	// Envoi terminé il y a 2 h ; les messages sont partis il y a 2 h.
	$il_y_a_2h = current_datetime()->modify( '-2 hours' )->format( 'Y-m-d H:i:s' );
	$wpdb->query( $wpdb->prepare( "UPDATE `$q` SET sent_at = %s WHERE newsletter_id = %d", $il_y_a_2h, $nl ) );
	update_post_meta( $nl, Scheduler::META_SENT_AT, $il_y_a_2h );
	$horloge = time();
	// L'horloge suit ici l'heure réelle : sans ceci, la suite jouée la nuit
	// verrait le renvoi se mettre en veille.
	Settings::update( array( 'quiet_hours' => false ) );

	$lignes = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `$q` WHERE newsletter_id = %d ORDER BY id", $nl ) ) );
	$wpdb->update( $q, array( 'delivery' => 'delivered' ), array( 'id' => $lignes[0] ) );
	$wpdb->update( $q, array( 'delivery' => 'delivered' ), array( 'id' => $lignes[1] ) );
	$ouvreur = (int) $wpdb->get_var( $wpdb->prepare( "SELECT subscriber_id FROM `$q` WHERE id = %d", $lignes[2] ) );
	Events::record( $nl, $ouvreur, Events::TYPE_OPEN );
	Log::record( $nl, Log::TYPE_DELIVERY, 'relevé de test', Queue::counts( $nl ) );

	Settings::update( array( 'auto_resend' => false ) );
	wam_nl_assert_equals( 'off', Delivery::maybe_auto_resend( $nl ), 'réglage coupé : rien' );
	Settings::update( array( 'auto_resend' => true ) );

	$horloge = time() - HOUR_IN_SECONDS - 10 * MINUTE_IN_SECONDS; // fin de l'envoi il y a 50 min
	wam_nl_assert_equals( 'too_early', Delivery::maybe_auto_resend( $nl ), 'relevé de +15 min : trop tôt' );
	$horloge = time();

	$n = Delivery::maybe_auto_resend( $nl );
	wam_nl_assert_equals( 1, $n, 'au relevé de +2 h : 1 seul·e renvoyé·e (ni les remis, ni l’ouvreur)' );
	wam_nl_assert_equals( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT resent FROM `$q` WHERE id = %d", $lignes[3] ) ), 'ligne marquée comme renvoyée' );
	Scheduler::process( $nl );
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 4, (int) $c['sent'], 'renvoi effectué' );
	wam_nl_assert_equals( Scheduler::STATUS_SENT, Scheduler::status( $nl ), 'newsletter de nouveau terminée' );

	// Toujours sans trace après le renvoi : jamais une troisième fois.
	$wpdb->query( $wpdb->prepare( "UPDATE `$q` SET sent_at = %s WHERE newsletter_id = %d", $il_y_a_2h, $nl ) );
	update_post_meta( $nl, Scheduler::META_SENT_AT, $il_y_a_2h );
	Log::record( $nl, Log::TYPE_DELIVERY, 'relevé de test 2', Queue::counts( $nl ) );
	wam_nl_assert_equals( 0, Delivery::maybe_auto_resend( $nl ), 'une seule fois par personne' );
	wam_nl_assert_equals( 0, count( Queue::undelivered_ids( $nl ) ), 'plus personne à renvoyer' );

	echo "== Garde-fou : trop de sans trace ==\n";
	$wpdb->query( $wpdb->prepare( "UPDATE `$q` SET delivery = NULL, resent = 0 WHERE newsletter_id = %d", $nl ) );
	Events::purge_newsletter( $nl );
	$res = Delivery::maybe_auto_resend( $nl );
	wam_nl_assert_equals( 'too_many', $res, '4 sans trace sur 4 envoyés : suivi suspect, rien n’est renvoyé' );
	wam_nl_assert_equals( 4, (int) Queue::counts( $nl )['sent'], 'aucune ligne remise en file' );
	$journal = Log::query( $nl );
	wam_nl_assert( false !== strpos( (string) $journal[0]['message'], 'Renvoi automatique annulé' ), 'le journal explique le refus' );

	echo "== Message trop récent ==\n";
	$wpdb->query( $wpdb->prepare( "UPDATE `$q` SET sent_at = %s WHERE newsletter_id = %d", current_time( 'mysql' ), $nl ) );
	wam_nl_assert_equals( 0, count( Queue::undelivered_ids( $nl, 30 * MINUTE_IN_SECONDS ) ), 'parti il y a moins de 30 min : pas encore « sans trace »' );

} finally {
	remove_filter( 'wam_nl_now', $filtre, 20 );
	foreach ( $posts as $id ) {
		Scheduler::unschedule( (int) $id );
		Delivery::unschedule( (int) $id );
		Queue::clear( (int) $id );
		Events::purge_newsletter( (int) $id );
		Log::purge_newsletter( (int) $id );
		wp_delete_post( (int) $id, true );
	}
	foreach ( $abonnes as $id ) {
		Subs::delete( (int) $id );
	}
	if ( $liste ) {
		Lists::delete( $liste );
	}
	if ( null === $option ) {
		delete_option( 'wam_nl_settings' );
	} else {
		update_option( 'wam_nl_settings', $option );
	}
}

wam_nl_test_report();
