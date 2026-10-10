<?php
/**
 * Plafond horaire du domaine et renvoi aux non-remis.
 * Incident du 09/10/2026 : o2switch plafonne le domaine à 180 e-mails/heure ;
 * au-delà il met en attente puis JETTE les messages, après les avoir acceptés.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Log;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subs;

global $wpdb;
$domaine   = '@wam-nl-test.invalid';
$option    = get_option( 'wam_nl_settings', null );
$posts     = array();
$abonnes   = array();
$liste     = 0;
$fantome   = 999000111; // newsletter fictive qui occupe l'heure glissante
$q         = Queue::table();

try {
	echo "== Réglage ==\n";
	wam_nl_assert_equals( 150, (int) Settings::defaults()['hourly_cap'], 'plafond par défaut : 150 par heure' );

	$liste = (int) Lists::ensure( 'ZZTest plafond' );
	for ( $i = 0; $i < 6; $i++ ) {
		$abonnes[] = Subs::insert( array( 'email' => "plafond-$i" . $domaine, 'consent_source' => 'admin', 'list_ids' => array( $liste ) ) );
	}
	foreach ( $abonnes as $id ) {
		Subs::add_to_lists( (int) $id, array( $liste ) );
	}
	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'draft', 'post_title' => 'ZZTest plafond', 'post_content' => '<!-- wp:paragraph --><p>Bonjour {prenom}</p><!-- /wp:paragraph --><!-- wp:wam-nl/footer /-->' ) );
	$posts[] = $nl;
	update_post_meta( $nl, '_wam_nl_subject', 'ZZTest plafond' );
	update_post_meta( $nl, '_wam_nl_list_ids', array( $liste ) );
	Settings::update( array( 'hourly_cap' => 10, 'batch_size' => 20, 'fail_threshold_batch' => 100, 'fail_threshold_hour' => 100 ) );

	// 8 envois déjà partis dans l'heure (une autre newsletter).
	$maintenant = current_time( 'mysql' );
	for ( $i = 0; $i < 8; $i++ ) {
		$wpdb->insert( $q, array( 'newsletter_id' => $fantome, 'subscriber_id' => 990000 + $i, 'status' => Queue::STATUS_SENT, 'attempts' => 1, 'sent_at' => $maintenant ) );
	}

	echo "== Lot borné par la place restante ==\n";
	$res = Scheduler::start( $nl );
	wam_nl_assert( ! is_wp_error( $res ), 'envoi lancé' );
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 2, (int) $c['sent'], '8 déjà partis, plafond 10 : 2 envoyés seulement' );
	wam_nl_assert_equals( 4, (int) $c['pending'], '4 restent en attente' );

	echo "== Au plafond : rien n'est envoyé, reprise planifiée ==\n";
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 2, (int) $c['sent'], 'plafond atteint : aucun envoi de plus' );
	$journal = Log::query( $nl );
	wam_nl_assert( false !== strpos( (string) $journal[0]['message'], 'Plafond horaire atteint (10/10)' ), 'le journal explique l’attente' );
	if ( function_exists( 'as_next_scheduled_action' ) ) {
		$prochaine = (int) as_next_scheduled_action( Scheduler::HOOK, array( $nl ), Scheduler::GROUP );
		wam_nl_assert( $prochaine > time() + 3000, 'reprise planifiée quand une place se libère (≈ 1 h)' );
	}

	echo "== L'heure glissante se vide : l'envoi reprend ==\n";
	$ancien = current_datetime()->modify( '-2 hours' )->format( 'Y-m-d H:i:s' );
	$wpdb->query( $wpdb->prepare( "UPDATE `$q` SET sent_at = %s WHERE newsletter_id IN (%d, %d)", $ancien, $fantome, $nl ) );
	Scheduler::process( $nl );
	Scheduler::process( $nl );
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 6, (int) $c['sent'], 'tout est parti une fois la place libérée' );
	wam_nl_assert_equals( Scheduler::STATUS_SENT, Scheduler::status( $nl ), 'newsletter terminée' );

	echo "== Renvoi aux non-remis ==\n";
	$lignes = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `$q` WHERE newsletter_id = %d ORDER BY id", $nl ) );
	// 2 remises tracées, 1 sans trace mais ouverte (reçue), 3 sans trace ni ouverture.
	$wpdb->update( $q, array( 'delivery' => 'delivered' ), array( 'id' => $lignes[0] ) );
	$wpdb->update( $q, array( 'delivery' => 'delivered' ), array( 'id' => $lignes[1] ) );
	$ouvreur = (int) $wpdb->get_var( $wpdb->prepare( "SELECT subscriber_id FROM `$q` WHERE id = %d", $lignes[2] ) );
	Events::record( $nl, $ouvreur, Events::TYPE_OPEN );

	$refus = Scheduler::resend_undelivered( $nl );
	wam_nl_assert( is_wp_error( $refus ), 'sans relevé de remise postérieur à l’envoi : refusé' );

	Log::record( $nl, Log::TYPE_DELIVERY, 'relevé de test', Queue::counts( $nl ) );
	wam_nl_assert_equals( 3, count( Queue::undelivered_ids( $nl ) ), '3 sans trace ni ouverture' );
	$n = Scheduler::resend_undelivered( $nl );
	wam_nl_assert_equals( 3, $n, 'renvoi : 3 remis en file, ni les remis ni l’ouvreur' );
	wam_nl_assert_equals( Scheduler::STATUS_SENDING, Scheduler::status( $nl ), 'newsletter repassée en envoi' );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 3, (int) $c['pending'], '3 en attente' );
	wam_nl_assert_equals( 3, (int) $c['sent'], 'les 3 autres restent envoyés (pas de doublon)' );

	Scheduler::process( $nl );
	Scheduler::process( $nl );
	$c = Queue::counts( $nl );
	wam_nl_assert_equals( 6, (int) $c['sent'], 'renvoi effectué' );
	wam_nl_assert_equals( 0, (int) $c['pending'], 'plus rien en attente' );

} finally {
	$wpdb->delete( $q, array( 'newsletter_id' => $fantome ) );
	foreach ( $posts as $id ) {
		Scheduler::unschedule( (int) $id );
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
