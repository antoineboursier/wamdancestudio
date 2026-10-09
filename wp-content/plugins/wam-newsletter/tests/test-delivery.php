<?php
/**
 * Suivi de remise cPanel (EmailTrack) : appel mocké, statuts, rebonds.
 * Aucun appel réseau : `pre_http_request` répond à la place de cPanel.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Install;
use WamNewsletter\Sending\Delivery;
use WamNewsletter\Sending\Log;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Stats\Events;
use WamNewsletter\Subscribers\Repository as Subs;

$domaine  = '@wam-nl-test.invalid';
$posts    = array();
$abonnes  = array();
$requetes = array();
$reponse  = null;

$config = static function () {
	return array(
		'host'  => 'cpanel.exemple.invalid',
		'user'  => 'utilisateur',
		'token' => 'JETON-DE-TEST',
	);
};
$mock = static function ( $pre, $args, $url ) use ( &$requetes, &$reponse ) {
	if ( false === strpos( $url, '/json-api/cpanel' ) ) {
		return $pre;
	}
	$requetes[] = array( 'url' => $url, 'args' => $args );
	return $reponse;
};
$json = static function ( array $corps, int $code = 200 ) {
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( $corps ),
		'response' => array( 'code' => $code, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};

$option_avant = get_option( Delivery::OPTION, null );

try {
	Install::maybe_upgrade();

	echo "== Jeton enregistré depuis l'admin : chiffré, relu, jamais en clair ==
";
	delete_option( Delivery::OPTION );
	$jeton = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
	wam_nl_assert( true === Delivery::store( 'yuqo3097', $jeton ), 'identifiant et jeton enregistrés' );
	$brut = get_option( Delivery::OPTION );
	wam_nl_assert( false === strpos( wp_json_encode( $brut ), $jeton ), 'le jeton n’apparaît pas en clair en base' );
	wam_nl_assert( 0 === strpos( (string) $brut['token'], 'v1:' ), 'valeur chiffrée versionnée' );
	wam_nl_assert_equals( $jeton, Delivery::stored()['token'], 'relu et déchiffré' );
	wam_nl_assert( $brut['token'] !== Delivery::encrypt( $jeton ), 'deux chiffrements du même jeton diffèrent (IV aléatoire)' );
	if ( ! defined( 'WAM_NL_CPANEL_TOKEN' ) ) {
		wam_nl_assert_equals( 'admin', Delivery::source(), 'configuration venue de l’admin' );
		wam_nl_assert_equals( 'yuqo3097', Delivery::config()['user'], 'config() lit l’identifiant enregistré' );
		wam_nl_assert( Delivery::configured(), 'configuré sans wp-config.php' );
	}
	wam_nl_assert( true === Delivery::store( 'yuqo3097', '' ), 'jeton vide : enregistrement accepté' );
	wam_nl_assert_equals( $jeton, Delivery::stored()['token'], 'jeton vide : l’ancien est gardé' );
	wam_nl_assert( is_wp_error( Delivery::store( 'yuqo3097', 'pas un jeton !' ) ), 'jeton mal formé refusé' );
	wam_nl_assert_equals( $jeton, Delivery::stored()['token'], 'refus : l’ancien jeton reste' );
	wam_nl_assert( is_wp_error( Delivery::store( 'yuqo 3097', '' ) ), 'identifiant mal formé refusé' );
	$altere          = get_option( Delivery::OPTION );
	$altere['token'] = 'v1:' . base64_encode( str_repeat( 'x', 60 ) );
	update_option( Delivery::OPTION, $altere, false );
	wam_nl_assert( Delivery::stored()['unreadable'], 'valeur altérée ou clés changées : signalée illisible' );
	wam_nl_assert_equals( '', Delivery::stored()['token'], 'jamais de jeton faux renvoyé' );
	Delivery::store( 'yuqo3097', '', true );
	wam_nl_assert( ! Delivery::stored()['has_token'], 'effacer le jeton' );
	delete_option( Delivery::OPTION );

	echo "== Schéma : colonnes de remise sur la file ==\n";
	global $wpdb;
	$colonnes = $wpdb->get_col( 'SHOW COLUMNS FROM `' . Queue::table() . '`' );
	foreach ( array( 'delivery', 'delivery_message', 'delivery_at' ) as $c ) {
		wam_nl_assert( in_array( $c, $colonnes, true ), "colonne $c présente" );
	}

	echo "== Non configuré : erreur claire, aucun appel ==\n";
	add_filter( 'pre_http_request', $mock, 10, 3 );
	if ( ! defined( 'WAM_NL_CPANEL_TOKEN' ) ) {
		wam_nl_assert( ! Delivery::configured(), 'sans constantes : non configuré' );
		wam_nl_assert( is_wp_error( Delivery::sync( 1 ) ), 'sync refuse sans configuration' );
		wam_nl_assert_equals( 0, count( $requetes ), 'aucune requête envoyée' );
	}
	add_filter( 'wam_nl_cpanel_config', $config );
	wam_nl_assert( Delivery::configured(), 'configuration fournie par filtre' );

	echo "== Rebond franc : seulement l'adresse inexistante ==\n";
	$durs  = array( '550 5.1.1 The email account that you tried to reach does not exist', 'SMTP error from remote mail server: 550 User unknown', '5.1.10 RESOLVER.ADR.RecipientNotFound' );
	$mous  = array( '550 5.7.1 Message rejected as spam', '452 4.2.2 Mailbox full', '554 5.7.1 Service unavailable; client host blocked using Spamhaus', '550 Recipient address rejected: greylisted', '' );
	foreach ( $durs as $m ) {
		wam_nl_assert( Delivery::is_hard_bounce( $m ), "rebond franc : $m" );
	}
	foreach ( $mous as $m ) {
		wam_nl_assert( ! Delivery::is_hard_bounce( $m ), 'pas un rebond : ' . ( '' === $m ? '(vide)' : $m ) );
	}

	echo "== Une seule issue par adresse ==\n";
	$best = Delivery::best_by_recipient(
		array(
			array( 'recipient' => 'a@x.test', 'sender' => '', 'status' => Delivery::DEFERRED, 'message' => 'attente', 'time' => 10 ),
			array( 'recipient' => 'a@x.test', 'sender' => '', 'status' => Delivery::DELIVERED, 'message' => '', 'time' => 20 ),
			array( 'recipient' => 'b@x.test', 'sender' => '', 'status' => Delivery::DEFERRED, 'message' => 'premier', 'time' => 10 ),
			array( 'recipient' => 'b@x.test', 'sender' => '', 'status' => Delivery::DEFERRED, 'message' => 'dernier', 'time' => 30 ),
		)
	);
	wam_nl_assert_equals( Delivery::DELIVERED, $best['a@x.test']['status'], 'une remise après une attente : remis' );
	wam_nl_assert_equals( 'dernier', $best['b@x.test']['message'], 'deux attentes : la plus récente' );

	echo "== Appel cPanel : en-tête, filtres, réponse ==\n";
	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'publish', 'post_title' => 'ZZTest remise' ) );
	$posts[] = $nl;
	$ids     = array();
	foreach ( array( 'remis', 'refuse', 'attente', 'spam', 'muet' ) as $nom ) {
		$ids[ $nom ] = Subs::insert( array( 'email' => "remise-$nom" . $domaine, 'consent_source' => 'admin' ) );
	}
	$abonnes = array_values( $ids );
	$envoi = current_time( 'mysql' );
	foreach ( $abonnes as $sid ) {
		$wpdb->insert( Queue::table(), array( 'newsletter_id' => $nl, 'subscriber_id' => $sid, 'status' => Queue::STATUS_SENT, 'sent_at' => $envoi ) );
	}

	$reponse = $json(
		array(
			'cpanelresult' => array(
			'event'  => array( 'result' => 1 ),
			'data'   => array(
				array( 'type' => 'success', 'recipient' => 'remise-remis' . $domaine, 'sender' => '', 'message' => 'Accepted', 'actionunixtime' => time() ),
				array( 'type' => 'failure', 'recipient' => 'REMISE-REFUSE' . $domaine, 'sender' => '', 'message' => '550 5.1.1 user unknown', 'actionunixtime' => time() ),
				array( 'type' => 'defer', 'recipient' => 'remise-attente' . $domaine, 'sender' => '', 'message' => '451 4.7.1 try again later', 'actionunixtime' => time() ),
				array( 'type' => 'failure', 'recipient' => 'remise-spam' . $domaine, 'sender' => '', 'message' => '550 5.7.1 rejected as spam', 'actionunixtime' => time() ),
				array( 'type' => 'success', 'recipient' => 'quelquun@ailleurs.test', 'sender' => 'facture@autre-expediteur.test', 'message' => '', 'actionunixtime' => time() ),
			),
			),
		)
	);
	$rapport = Delivery::sync( $nl );
	wam_nl_assert( is_array( $rapport ), 'sync réussit' );
	wam_nl_assert_equals( 1, count( $requetes ), 'un seul appel à cPanel' );
	wam_nl_assert_equals( 'cpanel utilisateur:JETON-DE-TEST', $requetes[0]['args']['headers']['Authorization'] ?? '', 'en-tête d’authentification cPanel' );
	wam_nl_assert( 0 === strpos( $requetes[0]['url'], 'https://cpanel.exemple.invalid:2083/json-api/cpanel' ), 'bonne adresse API2' );
	wam_nl_assert( false !== strpos( $requetes[0]['url'], 'cpanel_jsonapi_module=EmailTrack' ) && false !== strpos( $requetes[0]['url'], 'cpanel_jsonapi_apiversion=2' ), 'module EmailTrack en API2' );
	wam_nl_assert_equals( 1, $rapport['delivered'], '1 remis' );
	wam_nl_assert_equals( 1, $rapport['deferred'], '1 en attente' );
	wam_nl_assert_equals( 2, $rapport['failed'], '2 refusés' );
	wam_nl_assert_equals( 1, $rapport['bounced'], '1 seul rebond (l’adresse inexistante)' );
	wam_nl_assert_equals( 1, $rapport['unknown'], '1 sans trace' );

	$remise = $wpdb->get_results( $wpdb->prepare( 'SELECT subscriber_id, delivery, delivery_message FROM `' . Queue::table() . '` WHERE newsletter_id = %d', $nl ), OBJECT_K );
	wam_nl_assert_equals( Delivery::DELIVERED, $remise[ $ids['remis'] ]->delivery, 'file : remis' );
	wam_nl_assert_equals( Delivery::FAILED, $remise[ $ids['refuse'] ]->delivery, 'file : refusé (casse de l’adresse ignorée)' );
	wam_nl_assert_equals( '550 5.1.1 user unknown', $remise[ $ids['refuse'] ]->delivery_message, 'message du serveur gardé' );
	wam_nl_assert( null === $remise[ $ids['muet'] ]->delivery, 'sans trace : rien écrit' );
	wam_nl_assert_equals( Subs::STATUS_BOUNCED, Subs::find( $ids['refuse'] )['status'] ?? '', 'adresse inexistante passée en rebond' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $ids['spam'] )['status'] ?? '', 'refus pour spam : reste abonné·e' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $ids['attente'] )['status'] ?? '', 'mise en attente : reste abonné·e' );
	$journal = Log::query( $nl );
	wam_nl_assert( (bool) array_filter( $journal, static function ( $l ) { return Log::TYPE_DELIVERY === $l['type']; } ), 'relevé inscrit au journal d’envoi' );

	echo "== Réponses d'erreur de cPanel ==\n";
	$reponse = $json( array( 'cpanelresult' => array( 'event' => array( 'result' => 0 ), 'error' => 'You do not have the feature “emailtrack”.' ) ) );
	$err     = Delivery::fetch( time() - 3600 );
	wam_nl_assert( is_wp_error( $err ) && false !== strpos( $err->get_error_message(), 'emailtrack' ), 'erreur UAPI relayée' );
	$reponse = $json( array(), 401 );
	$err     = Delivery::fetch( time() - 3600 );
	wam_nl_assert( is_wp_error( $err ) && false !== strpos( $err->get_error_message(), 'jeton' ), '401 : jeton mis en cause' );
	wam_nl_assert( false === strpos( $err->get_error_message(), 'JETON-DE-TEST' ), 'le jeton n’apparaît jamais dans un message' );
	$reponse = new WP_Error( 'http_request_failed', 'cURL error 28' );
	wam_nl_assert( is_wp_error( Delivery::fetch( time() ) ), 'cPanel injoignable : erreur, pas d’exception' );
	$reponse = $json( array( 'cpanelresult' => array( 'event' => array( 'result' => 1 ), 'data' => array() ) ) );
	wam_nl_assert_equals( array(), Delivery::fetch( time() ), 'réponse vide acceptée' );
	$reponse = $json( array( 'cpanelresult' => array( 'event' => array( 'result' => 1 ), 'data' => array(
		array( 'type' => 'success', 'recipient' => 'ancien@x.test', 'sender' => '', 'message' => 'Accepté', 'actionunixtime' => 1000 ),
		array( 'type' => 'success', 'recipient' => 'recent@x.test', 'sender' => '', 'message' => 'Accepté', 'actionunixtime' => time() ),
	) ) ) );
	wam_nl_assert_equals( 1, count( Delivery::fetch( time() - 3600 ) ), 'ce qui précède l’envoi est écarté côté plugin' );

	echo "== Relecture : rebonds, rapprochement par heure, plafond ==\n";
	wam_nl_assert( Delivery::is_hard_bounce( '550 5.1.10 RESOLVER.ADR.RecipientNotFound; Recipient not found by SMTP address lookup [10.4.1.20]' ), 'une IP « 10.4.1.20 » n’empêche pas le rebond franc' );
	wam_nl_assert( ! Delivery::is_hard_bounce( '450 4.2.1 user unknown, try later' ), 'un code temporaire 4xx n’est jamais un rebond' );
	wam_nl_assert( ! Delivery::is_hard_bounce( '550 5.7.1 user unknown at this policy' ), '5.7.x reste exclu' );

	// Une facture partie de contact@ 2 h après la newsletter, vers la même adresse.
	$wpdb->update( Queue::table(), array( 'delivery' => null, 'delivery_message' => null ), array( 'newsletter_id' => $nl ) );
	Subs::set_status( $ids['refuse'], Subs::STATUS_SUBSCRIBED );
	$heure_envoi = time();
	$reponse     = $json( array( 'cpanelresult' => array( 'event' => array( 'result' => 1 ), 'data' => array(
		array( 'type' => 'failure', 'recipient' => 'remise-remis' . $domaine, 'sender' => '', 'message' => '552 5.2.2 mailbox full', 'actionunixtime' => $heure_envoi, 'sendunixtime' => $heure_envoi ),
		array( 'type' => 'success', 'recipient' => 'remise-remis' . $domaine, 'sender' => '', 'message' => 'Accepté (facture)', 'actionunixtime' => $heure_envoi + 7200, 'sendunixtime' => $heure_envoi + 7200 ),
		array( 'type' => 'failure', 'recipient' => 'remise-refuse' . $domaine, 'sender' => '', 'message' => '550 5.1.1 user unknown', 'actionunixtime' => $heure_envoi + 86400, 'sendunixtime' => $heure_envoi + 86400 ),
	) ) ) );
	$rapport = Delivery::sync( $nl );
	$remise  = $wpdb->get_results( $wpdb->prepare( 'SELECT subscriber_id, delivery, delivery_message FROM `' . Queue::table() . '` WHERE newsletter_id = %d', $nl ), OBJECT_K );
	wam_nl_assert_equals( Delivery::FAILED, $remise[ $ids['remis'] ]->delivery, 'une facture remise 2 h plus tard ne masque pas l’échec de la newsletter' );
	wam_nl_assert( null === $remise[ $ids['refuse'] ]->delivery, 'un refus du lendemain (autre message) n’est pas attribué à la newsletter' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $ids['refuse'] )['status'] ?? '', 'pas de rebond tiré d’un autre message' );
	wam_nl_assert_equals( 0, $rapport['bounced'], 'aucun rebond compté' );

	// Aucune heure d'envoi lisible : rien n'est rapproché.
	$wpdb->query( $wpdb->prepare( 'UPDATE `' . Queue::table() . '` SET sent_at = %s WHERE newsletter_id = %d', '0000-00-00 00:00:00', $nl ) );
	$avant   = count( $requetes );
	$rapport = Delivery::sync( $nl );
	wam_nl_assert_equals( count( $abonnes ), $rapport['unknown'], 'sans heure d’envoi : tout reste « sans trace »' );
	$wpdb->query( $wpdb->prepare( 'UPDATE `' . Queue::table() . '` SET sent_at = %s WHERE newsletter_id = %d', current_time( 'mysql' ), $nl ) );

	// Effacer + nouveau jeton dans le même envoi : le nouveau jeton gagne.
	$avant_option = get_option( Delivery::OPTION, null );
	remove_filter( 'wam_nl_cpanel_config', $config );
	Delivery::store( 'yuqo3097', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ012345' );
	Delivery::store( 'yuqo3097', 'ZYXWVUTSRQPONMLKJIHGFEDCBA543210', true );
	wam_nl_assert_equals( 'ZYXWVUTSRQPONMLKJIHGFEDCBA543210', Delivery::stored()['token'], 'cocher « effacer » ET coller un jeton : le nouveau jeton est gardé' );
	null === $avant_option ? delete_option( Delivery::OPTION ) : update_option( Delivery::OPTION, $avant_option, false );
	add_filter( 'wam_nl_cpanel_config', $config );

	// Relevés planifiés : pas d'empilement si l'envoi se termine deux fois.
	if ( function_exists( 'as_get_scheduled_actions' ) ) {
		Delivery::schedule_followups( $nl );
		Delivery::schedule_followups( $nl );
		$planifies = as_get_scheduled_actions( array( 'hook' => Delivery::HOOK, 'args' => array( $nl ), 'status' => 'pending', 'per_page' => 50 ), 'ids' );
		wam_nl_assert_equals( 3, count( $planifies ), 'deux fins d’envoi : toujours 3 relevés, pas 6' );
		Delivery::unschedule( $nl );
		$planifies = as_get_scheduled_actions( array( 'hook' => Delivery::HOOK, 'args' => array( $nl ), 'status' => 'pending', 'per_page' => 50 ), 'ids' );
		wam_nl_assert_equals( 0, count( $planifies ), 'relevés retirés' );
	}

	// Plafond atteint : signalé au journal.
	$lignes_max = array();
	for ( $i = 0; $i < Delivery::MAX_RESULTS; $i++ ) {
		$lignes_max[] = array( 'type' => 'success', 'recipient' => "x$i@x.test", 'sender' => '', 'message' => '', 'actionunixtime' => time(), 'sendunixtime' => time() );
	}
	$reponse = $json( array( 'cpanelresult' => array( 'event' => array( 'result' => 1 ), 'data' => $lignes_max ) ) );
	Delivery::sync( $nl );
	$journal = Log::query( $nl );
	wam_nl_assert( false !== strpos( (string) $journal[0]['message'], 'plafonné' ), 'réponse plafonnée : avertissement au journal' );

	echo "== Écran de statistiques : tuile et liste des refus ==\n";
	$_GET['newsletter'] = $nl;
	$ancien             = get_current_user_id();
	wp_set_current_user( 1 );
	ob_start();
	\WamNewsletter\Stats\Screen::render();
	$ecran = (string) ob_get_clean();
	wp_set_current_user( $ancien );
	wam_nl_assert( false !== strpos( $ecran, 'Prise en charge par le serveur d’envoi' ), 'section remise rendue' );
	wam_nl_assert( false !== strpos( $ecran, 'remise-remis' . $domaine ) && false !== strpos( $ecran, '552 5.2.2 mailbox full' ), 'adresse refusée listée avec la réponse du serveur' );
	wam_nl_assert( false !== strpos( $ecran, 'Abonné·e' ) && false === strpos( $ecran, '>subscribed<' ), 'statut de l’abonné·e traduit' );
	wam_nl_assert( false !== strpos( $ecran, 'wam_nl_delivery_sync' ), 'bouton de vérification manuelle' );
	wam_nl_assert( false === strpos( $ecran, 'JETON-DE-TEST' ), 'le jeton n’est pas affiché' );

} finally {
	if ( null === $option_avant ) {
		delete_option( Delivery::OPTION );
	} else {
		update_option( Delivery::OPTION, $option_avant, false );
	}
	remove_filter( 'pre_http_request', $mock, 10 );
	remove_filter( 'wam_nl_cpanel_config', $config );
	foreach ( $posts as $id ) {
		Queue::clear( (int) $id );
		Events::purge_newsletter( (int) $id );
		Log::purge_newsletter( (int) $id );
		wp_delete_post( (int) $id, true );
	}
	foreach ( $abonnes as $id ) {
		Subs::delete( (int) $id );
	}
	unset( $_GET['newsletter'] );
}

wam_nl_test_report();
