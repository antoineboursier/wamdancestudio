<?php
/**
 * Migration MailPoet rejouée pendant que MailPoet reste actif en prod :
 * contrôle qualité à l'entrée, et désinscriptions MailPoet reportées.
 * Joue une VRAIE migration sur la copie locale (idempotente), plus deux
 * lignes MailPoet de test retirées dans le finally.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Integrations\MailPoetMigrator;
use WamNewsletter\Subscribers\Repository as Subs;

global $wpdb;
$mp      = $wpdb->prefix . 'mailpoet_subscribers';
$mp_ids  = array();
$abonnes = array();

if ( ! MailPoetMigrator::available() ) {
	echo "Tables MailPoet absentes : suite sautée.\n";
	wam_nl_test_report();
	return;
}

try {
	$maintenant = current_time( 'mysql' );

	// Une adresse fautive restée dans MailPoet alors que la bonne existe chez nous.
	$bonne     = Subs::insert( array( 'email' => 'zzmp-faute@gmail.com', 'consent_source' => 'admin' ) );
	$abonnes[] = $bonne;
	$wpdb->insert( $mp, array( 'email' => 'zzmp-faute@gmai.com', 'status' => 'subscribed', 'created_at' => $maintenant, 'updated_at' => $maintenant ) );
	$mp_ids[] = (int) $wpdb->insert_id;

	// Une personne reprise de MailPoet, qui s'y est désinscrite depuis.
	$partie    = Subs::insert( array( 'email' => 'zzmp-partie@wam-nl-test.invalid', 'consent_source' => 'mailpoet' ) );
	$abonnes[] = $partie;
	$wpdb->insert( $mp, array( 'email' => 'zzmp-partie@wam-nl-test.invalid', 'status' => 'unsubscribed', 'created_at' => $maintenant, 'updated_at' => $maintenant ) );
	$mp_ids[] = (int) $wpdb->insert_id;

	// Une personne réinscrite par NOTRE formulaire : MailPoet ne la touche pas.
	$revenue   = Subs::insert( array( 'email' => 'zzmp-revenue@wam-nl-test.invalid', 'consent_source' => 'form' ) );
	$abonnes[] = $revenue;
	$wpdb->insert( $mp, array( 'email' => 'zzmp-revenue@wam-nl-test.invalid', 'status' => 'unsubscribed', 'created_at' => $maintenant, 'updated_at' => $maintenant ) );
	$mp_ids[] = (int) $wpdb->insert_id;

	echo "== Migration rejouée ==\n";
	MailPoetMigrator::run( array() );

	wam_nl_assert( null === Subs::find_by_email( 'zzmp-faute@gmai.com' ), 'l’adresse fautive de MailPoet n’est pas recréée' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $bonne )['status'] ?? '', 'la bonne adresse reste abonnée' );
	wam_nl_assert_equals( Subs::STATUS_UNSUBSCRIBED, Subs::find( $partie )['status'] ?? '', 'désinscription faite dans MailPoet reportée' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $revenue )['status'] ?? '', 'réinscription par notre formulaire respectée' );
	wam_nl_assert( null === Subs::find_by_email( 'zzmp-reste@gmail.com' ), 'forme fautive déjà connue : aucun doublon corrigé créé' );

} finally {
	foreach ( $mp_ids as $id ) {
		$wpdb->delete( $mp, array( 'id' => $id ) );
	}
	foreach ( $abonnes as $id ) {
		Subs::delete( (int) $id );
	}
	foreach ( array( 'zzmp-faute@gmai.com', 'zzmp-reste@gmail.com' ) as $orpheline ) {
		$ligne = Subs::find_by_email( $orpheline );
		if ( $ligne ) {
			Subs::delete( (int) $ligne['id'] );
		}
	}
}

wam_nl_test_report();
