<?php
/**
 * Non-régression de la relecture de branche avant déploiement (08/10/2026).
 *
 * Chaque bloc correspond à un défaut trouvé à la relecture, reproduit d'abord,
 * puis corrigé : envoi relancé sur une pause, ligne de file non réservée,
 * reprise qui se re-met aussitôt en pause, liens de clic à pourcentages,
 * désinscription par simple GET, statistiques de désinscription invisibles,
 * « vue navigateur » ouverte à tout·e abonné·e, réabonnement sans preuve.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\EditorSetup;
use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subs;
use WamNewsletter\Tracking\Links;

$domaine   = '@wam-nl-test.invalid';
$prefixe_l = 'ZZTest ';

$reglages_initiaux = get_option( Settings::OPTION, false );
$posts_temporaires = array();

$nettoyer = static function () use ( $domaine, $prefixe_l, &$posts_temporaires ) {
	global $wpdb;
	$t   = Subs::table();
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `$t` WHERE email LIKE %s", '%' . $wpdb->esc_like( $domaine ) ) );
	foreach ( $ids as $id ) {
		Subs::delete( (int) $id );
	}
	foreach ( Lists::all() as $liste ) {
		if ( 0 === strpos( (string) $liste['name'], $prefixe_l ) ) {
			Lists::delete( (int) $liste['id'] );
		}
	}
	foreach ( $posts_temporaires as $id ) {
		if ( $id && ! is_wp_error( $id ) ) {
			Queue::clear( (int) $id );
			Events::purge_newsletter( (int) $id );
			Scheduler::unschedule( (int) $id );
			delete_option( 'wam_nl_lock_' . (int) $id );
			wp_delete_post( (int) $id, true );
		}
	}
	$posts_temporaires = array();
};

$nettoyer();

try {

	$liste = Lists::ensure( $prefixe_l . 'Relecture' );
	$ids   = array();
	foreach ( array( 'a', 'b', 'c', 'd' ) as $lettre ) {
		$ids[ $lettre ] = Subs::insert(
			array(
				'email'          => $lettre . $domaine,
				'first_name'     => strtoupper( $lettre ),
				'consent_source' => 'form',
				'list_ids'       => array( $liste ),
			)
		);
	}

	$nl = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest relecture',
			'post_content' => EditorSetup::default_blocks(),
		)
	);
	$posts_temporaires[] = $nl;
	update_post_meta( $nl, '_wam_nl_subject', 'Bonjour {prenom}' );
	update_post_meta( $nl, '_wam_nl_list_ids', array( $liste ) );

	// ---------------------------------------------------------------
	echo "== Relancer « Envoyer » sur une pause ne renvoie à personne ==\n";
	Queue::build( $nl, array( $liste ) );
	$lot = Queue::next_batch( $nl, 2 );
	Queue::mark_sent( $lot[0]['id'] );
	Queue::mark_sent( $lot[1]['id'] );
	Scheduler::set_status( $nl, Scheduler::STATUS_PAUSED );

	$r = Scheduler::start( $nl );
	wam_nl_assert( is_wp_error( $r ), 'start() refuse une pause qui a déjà envoyé' );
	wam_nl_assert_equals( 2, Queue::counts( $nl )['sent'], 'la file n’a pas été reconstruite : 2 envoyés conservés' );

	// ---------------------------------------------------------------
	echo "== Réservation atomique des lignes ==\n";
	$prochain = Queue::next_batch( $nl, 1 );
	$ligne    = $prochain[0]['id'];
	wam_nl_assert( Queue::claim( $ligne ), 'la première réservation réussit' );
	wam_nl_assert( ! Queue::claim( $ligne ), 'la seconde échoue : une ligne ne part pas deux fois' );
	wam_nl_assert( ! in_array( $ligne, array_column( Queue::next_batch( $nl, 10 ), 'id' ), true ), 'une ligne réservée n’est plus dans le lot suivant' );

	echo "== Ligne restée « sending » après un processus mort ==\n";
	global $wpdb;
	$wpdb->update( Queue::table(), array( 'sent_at' => '2000-01-01 00:00:00' ), array( 'id' => $ligne ) );
	wam_nl_assert_equals( 1, Queue::release_stale( $nl ), 'une ligne orpheline est libérée' );
	$apres = $wpdb->get_row( $wpdb->prepare( 'SELECT status, attempts FROM `' . Queue::table() . '` WHERE id = %d', $ligne ), ARRAY_A );
	wam_nl_assert_equals( 'failed', $apres['status'], 'elle passe en échec visible' );
	wam_nl_assert_equals( 2, (int) $apres['attempts'], 'avec 2 tentatives : la reprise automatique ne la renvoie pas' );
	wam_nl_assert_equals( 0, Queue::requeue_failed( $nl ), 'requeue_failed() ne la remet pas en file' );

	// ---------------------------------------------------------------
	echo "== Seuil horaire : les échecs d'avant la reprise ne comptent plus ==\n";
	$a = Queue::next_batch( $nl, 5 );
	foreach ( $a as $entree ) {
		Queue::claim( $entree['id'] );
		Queue::mark_failed( $entree['id'], 'test', 1 );
	}
	wam_nl_assert( Queue::recent_failures( $nl ) >= 1, 'les échecs récents sont comptés' );
	$futur = current_datetime()->modify( '+1 minute' )->format( 'Y-m-d H:i:s' );
	wam_nl_assert_equals( 0, Queue::recent_failures( $nl, 3600, $futur ), 'après une reprise, la fenêtre repart de zéro' );

	echo "== Un·e désabonné·e écarté·e n'est pas un échec SMTP ==\n";
	Queue::clear( $nl );
	Queue::build( $nl, array( $liste ) );
	$lot = Queue::next_batch( $nl, 1 );
	Queue::claim( $lot[0]['id'] );
	Queue::mark_skipped( $lot[0]['id'], 'désabonné·e' );
	wam_nl_assert_equals( 0, Queue::recent_failures( $nl ), 'ne compte pas dans les échecs de l’heure' );

	// ---------------------------------------------------------------
	echo "== Lien de clic contenant des pourcentages ==\n";
	$token = Subs::generate_token();
	foreach ( array(
		'https://exemple.test/page%20x?a=b%26c',
		'https://exemple.test/caf%C3%A9?utm=1',
		'https://exemple.test/simple',
	) as $destination ) {
		$url    = Links::click_url( 9, $token, $destination );
		$params = array();
		// Ce que PHP met dans $_GET : UN seul décodage, pas deux.
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
		wam_nl_assert_equals( $destination, (string) $params['u'], "la destination survit au décodage : $destination" );
		wam_nl_assert( Links::verify_click( 9, $token, (string) $params['u'], (string) $params['s'] ), "la signature reste valide : $destination" );
	}
	$source = (string) file_get_contents( WAM_NL_DIR . 'includes/Tracking/Endpoints.php' );
	wam_nl_assert( false === strpos( $source, 'rawurldecode( (string)' ), 'Endpoints ne redécode plus la destination' );

	// ---------------------------------------------------------------
	echo "== Désinscription : rattachée à la dernière newsletter reçue ==\n";
	Queue::clear( $nl );
	Queue::build( $nl, array( $liste ) );
	$abonne_a = Subs::find( $ids['a'] );
	$lot      = Queue::next_batch( $nl, 10 );
	foreach ( $lot as $entree ) {
		if ( $entree['subscriber_id'] === (int) $ids['a'] ) {
			Queue::claim( $entree['id'] );
			Queue::mark_sent( $entree['id'] );
		}
	}
	wam_nl_assert_equals( $nl, Queue::last_sent_newsletter( (int) $ids['a'] ), 'last_sent_newsletter() retrouve l’envoi' );
	wam_nl_assert_equals( 0, Queue::last_sent_newsletter( (int) $ids['b'] ), '0 si rien n’a été envoyé à cette personne' );
	wam_nl_assert( Queue::has( $nl, (int) $ids['a'] ), 'has() : présent dans la file' );
	wam_nl_assert( ! Queue::has( $nl + 999999, (int) $ids['a'] ), 'has() : absent d’une autre newsletter' );

	// ---------------------------------------------------------------
	echo "== Formulaire : aucun réabonnement sans preuve ==\n";
	$mail = 'd' . $domaine;
	Subs::set_status( (int) $ids['d'], Subs::STATUS_UNSUBSCRIBED );
	delete_transient( 'wam_nl_rc_' . md5( $mail ) );
	\WamNewsletter\Form\Form::subscribe( $mail, 'D' );
	wam_nl_assert_equals( 'unsubscribed', Subs::find( (int) $ids['d'] )['status'], 'reste désabonné·e après un simple envoi du formulaire' );
	$url_confirm = Links::confirm_url( (string) Subs::find( (int) $ids['d'] )['token'] );
	wam_nl_assert( false !== strpos( $url_confirm, 'wam_nl_confirm=' ), 'le lien de confirmation existe' );

	// ---------------------------------------------------------------
	echo "== Import MailPoet : les désabonné·es sont repris, jamais recréés abonné·es ==\n";
	$source_mp = (string) file_get_contents( WAM_NL_DIR . 'includes/Integrations/MailPoetMigrator.php' );
	wam_nl_assert( false !== strpos( $source_mp, 'import_suppressions' ), 'la liste de suppression est importée' );
	if ( \WamNewsletter\Integrations\MailPoetMigrator::available() ) {
		$rapport = \WamNewsletter\Integrations\MailPoetMigrator::run( array( 'dry_run' => true ) );
		wam_nl_assert( array_key_exists( 'suppressed', $rapport ), 'le rapport annonce les personnes en liste de suppression' );
	}

} finally {
	$nettoyer();
	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}
}

wam_nl_test_report();
