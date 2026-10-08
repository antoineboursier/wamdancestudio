<?php
/**
 * Lots 7 et 8 — file d'envoi, pause automatique, suivi, statistiques.
 *
 * C'est la zone la plus sensible du plugin : une erreur ici part à 1 850
 * personnes, ou grille la limite horaire d'échecs d'o2switch. Les garanties
 * vérifiées en priorité sont donc celles qui protègent :
 *  - un échec SMTP ne marque JAMAIS une adresse en rebond ;
 *  - la pause automatique se déclenche aux seuils configurés ;
 *  - un lien de suivi non signé ne redirige nulle part (pas d'open redirect) ;
 *  - un envoi de test n'enregistre aucune statistique.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\EditorSetup;
use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Editor\RestApi;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Render\Placeholders;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Sending\Sender;
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
			wp_delete_post( (int) $id, true );
		}
	}
	$posts_temporaires = array();
};

$nettoyer();

try {

	echo "== Action Scheduler est disponible (fourni par WooCommerce) ==\n";
	wam_nl_assert( Scheduler::available(), 'as_schedule_single_action() existe' );

	// ---------------------------------------------------------------
	// Liens de suivi
	// ---------------------------------------------------------------

	echo "== Signature HMAC des liens de clic (§10) ==\n";
	$signature = Links::sign( 'charge-utile' );
	wam_nl_assert( 16 === strlen( $signature ), 'signature de 16 caractères' );
	wam_nl_assert( Links::verify( 'charge-utile', $signature ), 'une signature valide est acceptée' );
	wam_nl_assert( ! Links::verify( 'charge-utile', 'aaaaaaaaaaaaaaaa' ), 'une signature fausse est refusée' );
	wam_nl_assert( ! Links::verify( 'autre-charge', $signature ), 'la signature est liée à sa charge' );

	echo "== Un lien de clic forgé ne passe pas ==\n";
	// Sans cette vérification, ?wam_nl_click=1&u=https://site-malveillant ferait
	// de wamdancestudio.fr un relais de hameçonnage (open redirect).
	$token = Subs::generate_token();
	$url   = Links::click_url( 7, $token, 'https://exemple.test/vrai' );
	wam_nl_assert( false !== strpos( $url, 'wam_nl_click=7' ), 'identifiant de newsletter dans l’URL' );
	wam_nl_assert( false !== strpos( $url, 's=' ), 'signature dans l’URL' );
	$params = array();
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
	wam_nl_assert( Links::verify_click( 7, $token, 'https://exemple.test/vrai', (string) $params['s'] ), 'la signature émise est vérifiable' );
	wam_nl_assert( ! Links::verify_click( 7, $token, 'https://site-malveillant.test', (string) $params['s'] ), 'changer la destination invalide la signature' );
	wam_nl_assert( ! Links::verify_click( 8, $token, 'https://exemple.test/vrai', (string) $params['s'] ), 'changer de newsletter invalide la signature' );
	wam_nl_assert( ! Links::verify_click( 7, Subs::generate_token(), 'https://exemple.test/vrai', (string) $params['s'] ), 'changer de destinataire invalide la signature' );

	echo "== Réécriture des liens pour le suivi ==\n";
	$html_liens = '<a href="https://exemple.test/a">A</a>'
		. '<a href="mailto:contact@exemple.test">Mail</a>'
		. '<a href="#ancre">Ancre</a>'
		. '<a href="' . Placeholders::UNSUB_URL . '">Désabo</a>'
		. '<a href="/relatif">Relatif</a>';
	$reecrit = Links::rewrite( $html_liens, 7, $token );

	wam_nl_assert( false !== strpos( $reecrit, 'wam_nl_click=7' ), 'le lien externe est réécrit' );
	wam_nl_assert( false !== strpos( $reecrit, 'mailto:contact@exemple.test' ), 'un mailto est laissé intact' );
	wam_nl_assert( false !== strpos( $reecrit, 'href="#ancre"' ), 'une ancre est laissée intacte' );
	wam_nl_assert( false !== strpos( $reecrit, 'href="' . Placeholders::UNSUB_URL . '"' ), 'le marqueur de désinscription n’est pas suivi' );
	wam_nl_assert( false !== strpos( $reecrit, 'href="/relatif"' ), 'une URL relative est laissée intacte' );

	echo "== Pixel d'ouverture et désinscription ==\n";
	$pixel = Links::open_pixel( 7, $token );
	wam_nl_assert( false !== strpos( $pixel, 'width="1" height="1"' ), 'pixel de 1×1' );
	wam_nl_assert( false !== strpos( $pixel, 'alt=""' ), 'alt vide (image décorative)' );
	wam_nl_assert( false !== strpos( Links::unsubscribe_url( $token ), 'wam_nl_unsub=' . $token ), 'URL de désinscription tokenisée' );
	wam_nl_assert( false !== strpos( Links::unsubscribe_mailto(), 'mailto:' ), 'repli mailto pour List-Unsubscribe' );
	wam_nl_assert( false !== strpos( Links::unsubscribe_mailto(), 'subject=unsubscribe' ), 'objet du mailto conforme' );

	// ---------------------------------------------------------------
	// En-têtes
	// ---------------------------------------------------------------

	echo "== En-têtes de désinscription (§8.3) ==\n";
	$entetes = Sender::headers( $token );
	$joints  = implode( "\n", $entetes );
	wam_nl_assert( false !== strpos( $joints, 'List-Unsubscribe: <' ), 'List-Unsubscribe présent' );
	wam_nl_assert( false !== strpos( $joints, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' ), 'désinscription en un clic annoncée (exigée par Gmail et Yahoo)' );
	wam_nl_assert( false !== strpos( $joints, 'Content-Type: text/html' ), 'contenu en HTML' );
	wam_nl_assert( false !== strpos( $joints, 'mailto:' ), 'le repli mailto est dans l’en-tête' );
	wam_nl_assert( 1 === preg_match( '/List-Unsubscribe: <[^>]+>, <mailto:[^>]+>/', $joints ), 'les deux URL sont présentes et bien formées' );

	$sans_token = implode( "\n", Sender::headers( '' ) );
	wam_nl_assert( false === strpos( $sans_token, 'List-Unsubscribe' ), 'sans token, aucun en-tête de désinscription bidon' );

	// ---------------------------------------------------------------
	// Préparation d'un envoi réel
	// ---------------------------------------------------------------

	echo "== Mise en place : liste, abonné·es, newsletter ==\n";
	$liste = Lists::ensure( $prefixe_l . 'Envoi' );
	wam_nl_assert( $liste > 0, 'liste de test créée' );

	$abonnes = array();
	foreach ( array( 'a', 'b', 'c' ) as $lettre ) {
		$id = Subs::insert(
			array(
				'email'          => $lettre . $domaine,
				'first_name'     => strtoupper( $lettre ),
				'consent_source' => 'form',
				'list_ids'       => array( $liste ),
			)
		);
		wam_nl_assert( is_int( $id ), "abonné·e $lettre créé·e" );
		$abonnes[] = $id;
	}

	// Un·e désabonné·e dans la même liste : ne doit jamais entrer dans la file.
	$desabonne = Subs::insert(
		array(
			'email'    => 'desabonne' . $domaine,
			'status'   => Subs::STATUS_UNSUBSCRIBED,
			'list_ids' => array( $liste ),
		)
	);

	$nl = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest envoi',
			'post_content' => EditorSetup::default_blocks(),
		)
	);
	$posts_temporaires[] = $nl;
	update_post_meta( $nl, '_wam_nl_subject', 'Bonjour {prenom}, les nouvelles' );
	update_post_meta( $nl, '_wam_nl_list_ids', array( $liste ) );

	echo "== Construction de la file : les actifs seulement, dédoublonnés (§8.1) ==\n";
	$total = Queue::build( $nl, array( $liste ) );
	wam_nl_assert_equals( 3, $total, 'trois destinataires, le désabonné·e est écarté' );

	$counts = Queue::counts( $nl );
	wam_nl_assert_equals( 3, $counts['pending'], 'trois en attente' );
	wam_nl_assert_equals( 0, $counts['sent'], 'aucun envoyé' );

	echo "== Reconstruire la file ne crée pas de doublon (garanti par le schéma) ==\n";
	// UNIQUE (newsletter_id, subscriber_id) : personne ne peut recevoir deux fois
	// le même e-mail, même si la file était reconstruite par erreur.
	Queue::build( $nl, array( $liste ) );
	wam_nl_assert_equals( 3, Queue::counts( $nl )['total'], 'toujours trois entrées' );

	echo "== Lot suivant ==\n";
	$lot = Queue::next_batch( $nl, 2 );
	wam_nl_assert_equals( 2, count( $lot ), 'le lot respecte la taille demandée' );
	wam_nl_assert( isset( $lot[0]['subscriber_id'], $lot[0]['id'], $lot[0]['attempts'] ), 'le lot porte les champs attendus' );

	echo "== Marquage envoyé / échec ==\n";
	Queue::mark_sent( $lot[0]['id'] );
	wam_nl_assert_equals( 1, Queue::counts( $nl )['sent'], 'un envoi compté' );

	Queue::mark_failed( $lot[1]['id'], 'SMTP refusé', 1 );
	wam_nl_assert_equals( 1, Queue::counts( $nl )['failed'], 'un échec compté' );

	echo "== Un échec SMTP ne marque JAMAIS l'abonné·e en rebond (§8.2) ==\n";
	// Une limite serveur côté WAM ne dit rien de la validité de l'adresse.
	$statut_apres = Subs::find( $lot[1]['subscriber_id'] )['status'];
	wam_nl_assert_equals( 'subscribed', $statut_apres, 'l’abonné·e reste abonné·e malgré l’échec' );

	echo "== Les échecs sont rejoués une seule fois ==\n";
	$remis = Queue::requeue_failed( $nl );
	wam_nl_assert_equals( 1, $remis, 'l’échec est remis en attente' );
	Queue::mark_failed( $lot[1]['id'], 'SMTP refusé', 2 );
	wam_nl_assert_equals( 0, Queue::requeue_failed( $nl ), 'après deux tentatives, plus de reprise automatique' );

	echo "== Échecs sur l'heure glissante (seuil M) ==\n";
	Queue::touch_attempt( $lot[1]['id'] );
	wam_nl_assert( Queue::recent_failures( $nl, 3600 ) >= 1, 'l’échec récent est compté' );

	// Un échec vieux de deux heures sort de la fenêtre d'une heure. C'est ce
	// point précis qui était faux avant correction : la fenêtre était calculée
	// en UTC alors que les dates sont écrites en heure du site, ce qui
	// l'élargissait de deux heures en été.
	global $wpdb;
	$wpdb->update(
		Queue::table(),
		array( 'sent_at' => current_datetime()->modify( '-2 hours' )->format( 'Y-m-d H:i:s' ) ),
		array( 'id' => $lot[1]['id'] )
	);
	wam_nl_assert_equals( 0, Queue::recent_failures( $nl, 3600 ), 'un échec de plus d’une heure n’est plus compté' );
	wam_nl_assert( Queue::recent_failures( $nl, 10800 ) >= 1, 'une fenêtre de trois heures le retrouve' );
	Queue::touch_attempt( $lot[1]['id'] );

	echo "== Liste des adresses en échec, pour l'écran de statistiques ==\n";
	$echecs = Queue::failures( $nl );
	wam_nl_assert( count( $echecs ) >= 1, 'au moins un échec listé' );
	wam_nl_assert( false !== strpos( $echecs[0]['email'], $domaine ), 'l’adresse est jointe' );
	wam_nl_assert( false !== strpos( $echecs[0]['error'], 'SMTP' ), 'le message d’erreur est conservé' );

	Queue::clear( $nl );
	wam_nl_assert_equals( 0, Queue::counts( $nl )['total'], 'la file se vide' );

	// ---------------------------------------------------------------
	// Personnalisation d'un message
	// ---------------------------------------------------------------

	echo "== Personnalisation par destinataire ==\n";
	Settings::update( array( 'track_clicks' => true, 'track_opens' => true ) );
	$abonne = Subs::find( $abonnes[0] );
	$html   = EmailRenderer::render( $nl );
	$perso  = Sender::personalize( $abonne, $nl, $html );

	wam_nl_assert( false === strpos( $perso, '{{wam_nl_' ), 'aucun marqueur technique résiduel' );
	wam_nl_assert( false !== strpos( $perso, 'wam_nl_unsub=' . $abonne['token'] ), 'lien de désinscription propre à la personne' );
	wam_nl_assert( false !== strpos( $perso, 'wam_nl_view=' . $nl ), 'lien « voir dans le navigateur » présent' );
	wam_nl_assert( false !== strpos( $perso, 'wam_nl_open=' . $nl ), 'pixel d’ouverture inséré' );
	wam_nl_assert( false !== strpos( $perso, 'wam_nl_click=' . $nl ), 'les liens sont réécrits pour le suivi' );

	echo "== Suivi désactivé : ni pixel, ni réécriture ==\n";
	Settings::update( array( 'track_clicks' => false, 'track_opens' => false ) );
	$sans_suivi = Sender::personalize( $abonne, $nl, $html );
	wam_nl_assert( false === strpos( $sans_suivi, 'wam_nl_open=' ), 'aucun pixel quand les ouvertures sont désactivées' );
	wam_nl_assert( false === strpos( $sans_suivi, 'wam_nl_click=' ), 'aucune réécriture quand les clics sont désactivés' );
	wam_nl_assert( false !== strpos( $sans_suivi, 'wam_nl_unsub=' ), 'la désinscription reste, elle est obligatoire' );
	Settings::update( array( 'track_clicks' => true, 'track_opens' => true ) );

	echo "== Un token manquant est fabriqué à l'envoi ==\n";
	global $wpdb;
	$wpdb->update( Subs::table(), array( 'token' => null ), array( 'id' => $abonnes[1] ) );
	$sans_token_abonne = Subs::find( $abonnes[1] );
	wam_nl_assert( ! Subs::is_token( $sans_token_abonne['token'] ), 'départ : aucun token' );
	Sender::personalize( $sans_token_abonne, $nl, $html );
	wam_nl_assert( Subs::is_token( Subs::find( $abonnes[1] )['token'] ), 'un token est généré, le lien de désinscription reste possible' );

	// ---------------------------------------------------------------
	// Envoi de test
	// ---------------------------------------------------------------

	echo "== Envoi de test : contrôles préalables ==\n";
	$vide = wp_insert_post(
		array(
			'post_type'   => NewsletterPostType::POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => 'ZZTest sans objet',
		)
	);
	$posts_temporaires[] = $vide;

	$r = Sender::send_test( $vide, array( 'recette' . $domaine ) );
	wam_nl_assert( ! $r['ok'], 'sans objet, l’envoi de test est refusé' );
	wam_nl_assert( false !== strpos( $r['message'], 'objet' ), 'le message dit quoi corriger' );

	$r = Sender::send_test( 0, array( 'recette' . $domaine ) );
	wam_nl_assert( ! $r['ok'], 'newsletter inexistante refusée' );

	Settings::update( array( 'test_recipients' => '' ) );
	$r = Sender::send_test( $nl, array() );
	wam_nl_assert( ! $r['ok'], 'aucune adresse de test : refus explicite' );

	echo "== Envoi de test : accepté, et sans statistiques (§7.5) ==\n";
	$evenements_avant = Events::count( $nl, Events::TYPE_OPEN ) + Events::count( $nl, Events::TYPE_CLICK );

	$capture = array();
	$espion  = static function ( $args ) use ( &$capture ) {
		$capture[] = $args;
		return $args;
	};
	add_filter( 'wp_mail', $espion );
	$r = Sender::send_test( $nl, array( 'recette' . $domaine ) );
	remove_filter( 'wp_mail', $espion );

	wam_nl_assert( $r['ok'], 'envoi de test accepté (intercepté par Mailpit en local)' );
	wam_nl_assert_equals( 1, $r['sent'], 'un message envoyé' );
	wam_nl_assert_equals( 1, count( $capture ), 'wp_mail appelé une fois' );

	if ( $capture ) {
		$msg = $capture[0];
		wam_nl_assert( 0 === strpos( $msg['subject'], '[TEST] ' ), 'objet préfixé [TEST]' );
		wam_nl_assert( false === strpos( $msg['message'], '{{wam_nl_' ), 'aucun marqueur résiduel' );
		wam_nl_assert( false === strpos( $msg['message'], 'wam_nl_open=' ), 'aucun pixel sur un test' );
		wam_nl_assert( false === strpos( $msg['message'], 'wam_nl_unsub=' ), 'aucun lien de désinscription actif sur un test' );
		wam_nl_assert( false !== strpos( $msg['message'], 'href="#"' ), 'les liens techniques sont inertes' );
		wam_nl_assert( false !== strpos( $msg['subject'], 'Prénom' ), '{prenom} remplacé par une valeur d’exemple' );
	}

	wam_nl_assert_equals(
		$evenements_avant,
		Events::count( $nl, Events::TYPE_OPEN ) + Events::count( $nl, Events::TYPE_CLICK ),
		'un test n’enregistre aucune statistique'
	);

	echo "== Envoi de test : plusieurs adresses ==\n";
	$capture = array();
	add_filter( 'wp_mail', $espion );
	$r = Sender::send_test( $nl, array( 'un' . $domaine, 'deux' . $domaine, 'pas-valide' ) );
	remove_filter( 'wp_mail', $espion );
	wam_nl_assert( $r['ok'], 'envoi accepté' );
	wam_nl_assert_equals( 2, $r['sent'], 'les deux adresses valides sont servies, l’invalide est écartée' );

	// ---------------------------------------------------------------
	// Lancement d'un envoi
	// ---------------------------------------------------------------

	echo "== Refus de lancer un envoi incomplet ==\n";
	$incomplet = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest incomplet',
			'post_content' => EditorSetup::default_blocks(),
		)
	);
	$posts_temporaires[] = $incomplet;

	$res = Scheduler::start( $incomplet );
	wam_nl_assert( is_wp_error( $res ), 'sans objet : refus' );
	wam_nl_assert_equals( 'wam_nl_objet', $res->get_error_code(), 'le code d’erreur nomme la cause' );

	update_post_meta( $incomplet, '_wam_nl_subject', 'Un objet' );
	$res = Scheduler::start( $incomplet );
	wam_nl_assert( is_wp_error( $res ), 'sans liste : refus' );
	wam_nl_assert_equals( 'wam_nl_listes', $res->get_error_code(), 'le code d’erreur nomme la cause' );

	$liste_vide = Lists::ensure( $prefixe_l . 'Vide' );
	update_post_meta( $incomplet, '_wam_nl_list_ids', array( $liste_vide ) );
	$res = Scheduler::start( $incomplet );
	wam_nl_assert( is_wp_error( $res ), 'liste sans abonné·e actif : refus' );
	wam_nl_assert_equals( 'wam_nl_vide', $res->get_error_code(), 'le code d’erreur nomme la cause' );

	wam_nl_assert( is_wp_error( Scheduler::start( 0 ) ), 'newsletter inexistante : refus' );

	echo "== Lancement : le HTML est figé (§7.3) ==\n";
	$res = Scheduler::start( $nl );
	wam_nl_assert( ! is_wp_error( $res ), 'lancement accepté' );
	if ( ! is_wp_error( $res ) ) {
		wam_nl_assert( $res['ok'], 'le rapport est positif' );
		wam_nl_assert_equals( 3, $res['total'], 'trois destinataires' );
	}
	wam_nl_assert_equals( Scheduler::STATUS_SENDING, Scheduler::status( $nl ), 'statut passé en « envoi en cours »' );

	$fige = (string) get_post_meta( $nl, Scheduler::META_HTML, true );
	wam_nl_assert( '' !== $fige, 'le HTML est enregistré' );
	wam_nl_assert( false !== strpos( $fige, '<!DOCTYPE html>' ), 'et c’est un document complet' );

	echo "== Le contenu figé ne bouge plus, même si un contenu est publié ==\n";
	// C'est tout l'intérêt du gel : tout le monde reçoit la même chose.
	$nouveau_stage = wp_insert_post(
		array(
			'post_type'   => 'stages',
			'post_status' => 'publish',
			'post_title'  => 'ZZTest stage publie pendant envoi',
		)
	);
	$posts_temporaires[] = $nouveau_stage;
	update_post_meta( $nouveau_stage, 'date_stage', gmdate( 'Ymd', time() + 86400 ) );

	$fige_apres = (string) get_post_meta( $nl, Scheduler::META_HTML, true );
	wam_nl_assert_equals( $fige, $fige_apres, 'le HTML figé est inchangé' );
	wam_nl_assert( false === strpos( $fige, 'publie pendant envoi' ), 'le nouveau contenu n’y apparaît pas' );

	echo "== Relancer un envoi déjà en cours est refusé ==\n";
	$res = Scheduler::start( $nl );
	wam_nl_assert( is_wp_error( $res ), 'refus' );
	wam_nl_assert_equals( 'wam_nl_deja', $res->get_error_code(), 'le code d’erreur nomme la cause' );

	echo "== Traitement d'un lot ==\n";
	Settings::update( array( 'batch_size' => 2, 'fail_threshold_batch' => 5, 'fail_threshold_hour' => 50 ) );
	$capture = array();
	add_filter( 'wp_mail', $espion );
	Scheduler::process( $nl );
	remove_filter( 'wp_mail', $espion );

	$counts = Queue::counts( $nl );
	wam_nl_assert_equals( 2, $counts['sent'], 'deux messages envoyés dans le lot' );
	wam_nl_assert_equals( 1, $counts['pending'], 'un reste en attente' );
	wam_nl_assert_equals( 2, count( $capture ), 'deux appels à wp_mail' );

	if ( $capture ) {
		$msg = $capture[0];
		wam_nl_assert( false === strpos( $msg['message'], '{{wam_nl_' ), 'aucun marqueur résiduel dans un envoi réel' );
		wam_nl_assert( false !== strpos( $msg['message'], 'wam_nl_unsub=' ), 'lien de désinscription réel' );
		$entetes_envoi = implode( "\n", (array) $msg['headers'] );
		wam_nl_assert( false !== strpos( $entetes_envoi, 'List-Unsubscribe-Post' ), 'en-tête un clic présent sur un envoi réel' );
		wam_nl_assert( false === strpos( $msg['subject'], '{prenom}' ), 'l’objet est personnalisé' );
	}

	echo "== Fin de file : statut envoyée ==\n";
	add_filter( 'wp_mail', $espion );
	Scheduler::process( $nl ); // dernier message
	Scheduler::process( $nl ); // file vide -> reprise des échecs puis fin
	Scheduler::process( $nl );
	remove_filter( 'wp_mail', $espion );
	wam_nl_assert_equals( Scheduler::STATUS_SENT, Scheduler::status( $nl ), 'statut passé en « envoyée »' );
	wam_nl_assert( '' !== (string) get_post_meta( $nl, Scheduler::META_SENT_AT, true ), 'date de fin enregistrée' );

	echo "== Un·e désabonné·e en cours d'envoi ne reçoit rien ==\n";
	$nl2 = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest envoi 2',
			'post_content' => '<!-- wp:paragraph --><p>Coucou</p><!-- /wp:paragraph -->',
		)
	);
	$posts_temporaires[] = $nl2;
	update_post_meta( $nl2, '_wam_nl_subject', 'Deuxième' );
	update_post_meta( $nl2, '_wam_nl_list_ids', array( $liste ) );

	Scheduler::start( $nl2 );
	// La personne se désabonne après la construction de la file.
	Subs::set_status( $abonnes[0], Subs::STATUS_UNSUBSCRIBED );

	$capture = array();
	Settings::update( array( 'batch_size' => 10, 'fail_threshold_batch' => 100 ) );
	add_filter( 'wp_mail', $espion );
	Scheduler::process( $nl2 );
	remove_filter( 'wp_mail', $espion );

	$destinataires_servis = array();
	foreach ( $capture as $msg ) {
		$destinataires_servis[] = is_array( $msg['to'] ) ? $msg['to'][0] : $msg['to'];
	}
	wam_nl_assert( ! in_array( 'a' . $domaine, $destinataires_servis, true ), 'la personne désabonnée entre-temps n’est pas servie' );
	Subs::set_status( $abonnes[0], Subs::STATUS_SUBSCRIBED );

	// ---------------------------------------------------------------
	// Pause automatique
	// ---------------------------------------------------------------

	echo "== Pause automatique au seuil d'échecs dans un lot (§8.2) ==\n";
	$nl3 = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest pause',
			'post_content' => '<!-- wp:paragraph --><p>Test pause</p><!-- /wp:paragraph -->',
		)
	);
	$posts_temporaires[] = $nl3;
	update_post_meta( $nl3, '_wam_nl_subject', 'Pause' );
	update_post_meta( $nl3, '_wam_nl_list_ids', array( $liste ) );

	Settings::update( array( 'batch_size' => 10, 'fail_threshold_batch' => 2, 'fail_threshold_hour' => 100 ) );
	Scheduler::start( $nl3 );

	// On fait échouer tous les envois : c'est le scénario o2switch qu'il faut
	// absolument détecter, sinon la limite horaire d'échecs est consommée.
	$faire_echouer = static function () {
		return false;
	};
	add_filter( 'pre_wp_mail', $faire_echouer );
	Scheduler::process( $nl3 );
	remove_filter( 'pre_wp_mail', $faire_echouer );

	wam_nl_assert_equals( Scheduler::STATUS_PAUSED, Scheduler::status( $nl3 ), 'l’envoi se met en pause tout seul' );
	$raison = (string) get_post_meta( $nl3, Scheduler::META_PAUSE, true );
	wam_nl_assert( '' !== $raison, "la raison est consignée ($raison)" );

	echo "== Aucune adresse n'est marquée en rebond par la pause ==\n";
	foreach ( $abonnes as $id ) {
		$statut = Subs::find( $id )['status'];
		wam_nl_assert( Subs::STATUS_BOUNCED !== $statut, 'aucun rebond posé automatiquement' );
	}

	echo "== Un lot n'est pas traité quand l'envoi est en pause ==\n";
	$avant = Queue::counts( $nl3 );
	Scheduler::process( $nl3 );
	wam_nl_assert_equals( $avant['sent'], Queue::counts( $nl3 )['sent'], 'rien n’est envoyé en pause' );

	echo "== Reprise ==\n";
	$res = Scheduler::resume( $nl3 );
	wam_nl_assert( ! is_wp_error( $res ), 'reprise acceptée' );
	wam_nl_assert_equals( Scheduler::STATUS_SENDING, Scheduler::status( $nl3 ), 'retour en « envoi en cours »' );
	wam_nl_assert_equals( '', (string) get_post_meta( $nl3, Scheduler::META_PAUSE, true ), 'la raison de pause est effacée' );

	echo "== Reprendre un envoi qui n'est pas en pause est refusé ==\n";
	wam_nl_assert( is_wp_error( Scheduler::resume( $nl3 ) ), 'refus (il est déjà en cours)' );

	echo "== Arrêt manuel ==\n";
	Scheduler::cancel( $nl3 );
	wam_nl_assert_equals( Scheduler::STATUS_PAUSED, Scheduler::status( $nl3 ), 'arrêt = pause' );
	wam_nl_assert( false !== strpos( (string) get_post_meta( $nl3, Scheduler::META_PAUSE, true ), 'manuellement' ), 'la raison le dit' );

	echo "== Planification ==\n";
	$nl4 = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest planifie',
			'post_content' => '<!-- wp:paragraph --><p>Plus tard</p><!-- /wp:paragraph -->',
		)
	);
	$posts_temporaires[] = $nl4;
	update_post_meta( $nl4, '_wam_nl_subject', 'Plus tard' );
	update_post_meta( $nl4, '_wam_nl_list_ids', array( $liste ) );

	$res = Scheduler::start( $nl4, wp_date( 'Y-m-d H:i:s', time() + 7200 ) );
	wam_nl_assert( ! is_wp_error( $res ), 'planification acceptée' );
	wam_nl_assert_equals( Scheduler::STATUS_SCHEDULED, Scheduler::status( $nl4 ), 'statut « programmée »' );
	$progression = Scheduler::progress( $nl4 );
	wam_nl_assert( $progression['nextRunAt'] > time(), 'une action est planifiée dans le futur' );

	echo "== Progression ==\n";
	$p = Scheduler::progress( $nl );
	wam_nl_assert_equals( Scheduler::STATUS_SENT, $p['status'], 'statut repris' );
	wam_nl_assert_equals( 3, $p['total'], 'total repris' );
	wam_nl_assert( isset( $p['sent'], $p['failed'], $p['pending'] ), 'tous les compteurs sont là' );

	// ---------------------------------------------------------------
	// Événements et statistiques
	// ---------------------------------------------------------------

	echo "== Enregistrement des événements ==\n";
	wam_nl_assert( Events::record( $nl, $abonnes[0], Events::TYPE_CLICK, 'https://exemple.test/x' ), 'un clic est enregistré' );
	wam_nl_assert( ! Events::record( $nl, $abonnes[0], 'inventé' ), 'un type inconnu est refusé' );
	wam_nl_assert_equals( 1, Events::count( $nl, Events::TYPE_CLICK ), 'un clic compté' );

	Events::record( $nl, $abonnes[0], Events::TYPE_CLICK, 'https://exemple.test/x' );
	wam_nl_assert_equals( 2, Events::count( $nl, Events::TYPE_CLICK ), 'deux clics au total' );
	wam_nl_assert_equals( 1, Events::count_unique( $nl, Events::TYPE_CLICK ), 'une seule personne' );

	echo "== Les ouvertures ne sont comptées qu'une fois par personne ==\n";
	// Apple Mail précharge les images : sans ce garde-fou, une personne
	// générerait des dizaines d'ouvertures et le taux deviendrait absurde.
	wam_nl_assert( Events::record_open_once( $nl, $abonnes[0] ), 'première ouverture enregistrée' );
	wam_nl_assert( ! Events::record_open_once( $nl, $abonnes[0] ), 'la deuxième est ignorée' );
	wam_nl_assert_equals( 1, Events::count( $nl, Events::TYPE_OPEN ), 'une seule ouverture en base' );

	echo "== Top des liens cliqués ==\n";
	Events::record( $nl, $abonnes[1], Events::TYPE_CLICK, 'https://exemple.test/y' );
	$top = Events::top_links( $nl );
	wam_nl_assert( count( $top ) >= 2, 'deux liens distincts' );
	wam_nl_assert_equals( 'https://exemple.test/x', $top[0]['url'], 'le plus cliqué en tête' );
	wam_nl_assert_equals( 2, $top[0]['total'], 'deux clics au total' );
	wam_nl_assert_equals( 1, $top[0]['uniques'], 'une personne' );

	echo "== Purge des événements d'une newsletter ==\n";
	Events::purge_newsletter( $nl );
	wam_nl_assert_equals( 0, Events::count( $nl, Events::TYPE_CLICK ), 'les clics sont effacés' );

	// ---------------------------------------------------------------
	// Checklist « prêt à envoyer »
	// ---------------------------------------------------------------

	echo "== Checklist : ce qui manque est dit en français ==\n";
	$check = RestApi::checklist( $incomplet );
	wam_nl_assert( ! $check['ready'], 'une newsletter incomplète n’est pas prête' );
	wam_nl_assert( count( $check['items'] ) >= 4, 'au moins quatre points vérifiés' );

	$par_cle = array();
	foreach ( $check['items'] as $item ) {
		$par_cle[ $item['key'] ] = $item;
	}
	wam_nl_assert( isset( $par_cle['subject'], $par_cle['lists'], $par_cle['content'], $par_cle['footer'] ), 'les points attendus sont présents' );
	wam_nl_assert( $par_cle['subject']['ok'], 'l’objet est renseigné sur cette newsletter' );
	wam_nl_assert( ! $par_cle['lists']['ok'], 'la liste vide est signalée' );
	wam_nl_assert( '' !== $par_cle['lists']['hint'], 'et un conseil est donné' );
	wam_nl_assert( ! empty( $par_cle['preheader']['optional'] ), 'le texte d’aperçu est un conseil, pas un blocage' );

	echo "== Checklist : prête quand tout est en place ==\n";
	$check = RestApi::checklist( $nl2 );
	wam_nl_assert( $check['recipients'] > 0, 'des destinataires sont comptés' );

	$complet = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest complet',
			'post_content' => EditorSetup::default_blocks(),
		)
	);
	$posts_temporaires[] = $complet;
	update_post_meta( $complet, '_wam_nl_subject', 'Tout est prêt' );
	update_post_meta( $complet, '_wam_nl_list_ids', array( $liste ) );

	$check = RestApi::checklist( $complet );
	wam_nl_assert( $check['ready'], 'la newsletter est prête à partir' );

	echo "== Checklist : le pied de page manquant est bloquant (obligation légale) ==\n";
	$sans_pied = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest sans pied',
			'post_content' => '<!-- wp:paragraph --><p>Du texte</p><!-- /wp:paragraph -->',
		)
	);
	$posts_temporaires[] = $sans_pied;
	update_post_meta( $sans_pied, '_wam_nl_subject', 'Objet' );
	update_post_meta( $sans_pied, '_wam_nl_list_ids', array( $liste ) );

	$check = RestApi::checklist( $sans_pied );
	wam_nl_assert( ! $check['ready'], 'sans pied de page, pas prête' );
	foreach ( $check['items'] as $item ) {
		if ( 'footer' === $item['key'] ) {
			wam_nl_assert( ! $item['ok'], 'le point « lien de désinscription » est en défaut' );
		}
	}

	echo "== Checklist : elle suit le brouillon non enregistré ==\n";
	// C'est ce qui la rend utile pendant la rédaction : pas besoin d'enregistrer
	// pour savoir où on en est.
	$check = RestApi::checklist(
		$sans_pied,
		array(
			'subject' => 'Objet du brouillon',
			'listIds' => array( $liste ),
			'content' => EditorSetup::default_blocks(),
		)
	);
	wam_nl_assert( $check['ready'], 'le contenu non enregistré est pris en compte' );

} finally {
	$nettoyer();
	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}
}

echo "== État restauré ==\n";
wam_nl_assert_equals( 0, Subs::count( array( 'search' => $domaine, 'include_trashed' => true ) ), 'aucun contact de test ne subsiste' );
wam_nl_assert_equals( $reglages_initiaux, get_option( Settings::OPTION, false ), 'réglages restaurés à l’identique' );

$restes = get_posts(
	array(
		'post_type'        => array( 'stages', NewsletterPostType::POST_TYPE ),
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		's'                => 'ZZTest',
		'suppress_filters' => true,
	)
);
wam_nl_assert_equals( array(), $restes, 'aucun contenu de test ne subsiste' );

wam_nl_test_report();
