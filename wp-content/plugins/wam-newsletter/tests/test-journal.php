<?php
/**
 * Journal d'envoi — déroulé de la campagne et suivi par destinataire.
 *
 * Besoin exprimé le 08/10/2026 : « si j'envoie sur une base à 50 personnes, on
 * va devoir compter sur des stats sans retour de leur part ». Les données
 * existaient déjà en base (file + événements) mais rien ne les montrait, et
 * rien ne traçait le déroulé de l'envoi lui-même.
 *
 * Ce que ces tests protègent en priorité :
 *  - le journal se remplit tout seul aux moments qui comptent (démarrage, lot,
 *    pause, reprise, relance, fin), sinon un envoi bloqué reste inexplicable ;
 *  - la vue par destinataire dit la vérité : qui a reçu, qui a échoué et
 *    pourquoi, qui a ouvert, qui a cliqué, qui s'est désabonné ;
 *  - les filtres et l'export ne mélangent jamais deux newsletters.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\NewsletterList;
use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Install;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Log;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Stats\Events;
use WamNewsletter\Stats\JournalScreen;
use WamNewsletter\Stats\Recipients;
use WamNewsletter\Subscribers\Repository as Subs;

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
			Log::purge_newsletter( (int) $id );
			Scheduler::unschedule( (int) $id );
			wp_delete_post( (int) $id, true );
		}
	}
	$posts_temporaires = array();
};

$nettoyer();

try {
	echo "== La table de journal existe ==\n";
	global $wpdb;
	$table = Log::table();
	wam_nl_assert_equals( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ), 'la table de journal est créée' );
	wam_nl_assert( version_compare( Install::DB_VERSION, '1.3.0', '>=' ), 'la version de schéma a été incrémentée' );

	// ------------------------------------------------------------------
	// Jeu d'essai : une newsletter, une liste, quatre abonné·es.
	// ------------------------------------------------------------------
	$liste = Lists::ensure( $prefixe_l . 'journal' );

	$emails = array(
		'journal-recu' . $domaine,
		'journal-echec' . $domaine,
		'journal-attente' . $domaine,
		'journal-ouvert' . $domaine,
	);

	$abonnes = array();
	foreach ( $emails as $i => $email ) {
		// upsert() rend un tableau {id, action, resubscribed}, pas un identifiant.
		$res               = Subs::upsert(
			array(
				'email'      => $email,
				'first_name' => 'Zz' . $i,
				'last_name'  => 'Test',
			)
		);
		$abonnes[ $email ] = is_wp_error( $res ) ? 0 : (int) $res['id'];
		wam_nl_assert( $abonnes[ $email ] > 0, 'abonné·e de test créé·e : ' . $email );
		Subs::add_to_lists( $abonnes[ $email ], array( $liste ) );
	}

	$nl = wp_insert_post(
		array(
			'post_type'   => NewsletterPostType::POST_TYPE,
			'post_title'  => 'ZZTest journal',
			'post_status' => 'draft',
			'post_content' => '<!-- wp:paragraph --><p>Bonjour {prenom}</p><!-- /wp:paragraph -->',
		)
	);
	$posts_temporaires[] = $nl;
	update_post_meta( $nl, '_wam_nl_subject', 'ZZTest objet' );
	update_post_meta( $nl, '_wam_nl_list_ids', array( $liste ) );

	$autre = wp_insert_post(
		array(
			'post_type'   => NewsletterPostType::POST_TYPE,
			'post_title'  => 'ZZTest journal voisin',
			'post_status' => 'draft',
		)
	);
	$posts_temporaires[] = $autre;

	echo "\n== Journal : écriture et relecture ==\n";
	wam_nl_assert( Log::record( $nl, Log::TYPE_START, 'Envoi lancé', array( 'pending' => 4 ) ), 'une entrée est enregistrée' );
	$lignes = Log::query( $nl );
	wam_nl_assert_equals( 1, count( $lignes ), 'une seule entrée pour cette newsletter' );
	wam_nl_assert_equals( Log::TYPE_START, $lignes[0]['type'], 'le type est conservé' );
	wam_nl_assert_equals( 'Envoi lancé', $lignes[0]['message'], 'le message est conservé' );
	wam_nl_assert_equals( 4, $lignes[0]['pending'], 'le compteur « en attente » est conservé' );
	wam_nl_assert( '' !== (string) $lignes[0]['created_at'], 'l’entrée est horodatée' );

	// Heure du site et non UTC : tout le plugin écrit ses datetime en heure
	// locale (leçon de Queue::recent_failures, où la comparaison en UTC élargissait
	// la fenêtre de pause de deux heures en été).
	$ecart = abs( strtotime( (string) $lignes[0]['created_at'] ) - strtotime( current_time( 'mysql' ) ) );
	wam_nl_assert( $ecart < 120, 'l’horodatage est en heure du site' );

	echo "\n== Journal : un type inconnu est refusé ==\n";
	wam_nl_assert( false === Log::record( $nl, 'bidon', 'ne doit pas passer' ), 'un type hors liste est rejeté' );
	wam_nl_assert_equals( 1, count( Log::query( $nl ) ), 'rien n’a été écrit' );

	echo "\n== Journal : ordre et cloisonnement par newsletter ==\n";
	Log::record( $nl, Log::TYPE_BATCH, 'Lot 1', array( 'sent' => 2 ) );
	Log::record( $autre, Log::TYPE_START, 'Voisine' );
	$lignes = Log::query( $nl );
	wam_nl_assert_equals( 2, count( $lignes ), 'la newsletter voisine n’apparaît pas' );
	wam_nl_assert_equals( Log::TYPE_BATCH, $lignes[0]['type'], 'la plus récente est en premier' );
	wam_nl_assert_equals( 2, Log::count( $nl ), 'le comptage suit' );
	wam_nl_assert_equals( 1, Log::count( $autre ), 'chaque newsletter a son propre journal' );

	Log::purge_newsletter( $autre );
	wam_nl_assert_equals( 0, Log::count( $autre ), 'la purge vide le journal visé' );
	wam_nl_assert_equals( 2, Log::count( $nl ), 'la purge ne touche pas les autres' );

	// ------------------------------------------------------------------
	// File d'envoi : un envoyé, un échec, un en attente, un ouvert+cliqué.
	// ------------------------------------------------------------------
	echo "\n== Vue par destinataire ==\n";
	Queue::build( $nl, array( $liste ) );
	wam_nl_assert_equals( 4, Queue::counts( $nl )['total'], 'les 4 destinataires sont en file' );

	$file = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT q.id, s.email FROM `' . Queue::table() . '` q JOIN `' . Subs::table() . '` s ON s.id = q.subscriber_id WHERE q.newsletter_id = %d', $nl ), ARRAY_A ) as $r ) {
		$file[ (string) $r['email'] ] = (int) $r['id'];
	}

	Queue::mark_sent( $file[ 'journal-recu' . $domaine ] );
	Queue::mark_failed( $file[ 'journal-echec' . $domaine ], 'SMTP : boîte pleine', 1 );
	Queue::mark_sent( $file[ 'journal-ouvert' . $domaine ] );

	Events::record( $nl, $abonnes[ 'journal-ouvert' . $domaine ], Events::TYPE_OPEN );
	Events::record( $nl, $abonnes[ 'journal-ouvert' . $domaine ], Events::TYPE_CLICK, home_url( '/stages-workshop-ateliers/' ) );
	Events::record( $nl, $abonnes[ 'journal-echec' . $domaine ], Events::TYPE_UNSUBSCRIBE );

	$tous = Recipients::query( $nl, array( 'per_page' => 50 ) );
	wam_nl_assert_equals( 4, count( $tous ), 'une ligne par destinataire' );
	wam_nl_assert_equals( 4, Recipients::count( $nl ), 'le comptage total suit' );

	$par_email = array();
	foreach ( $tous as $ligne ) {
		$par_email[ (string) $ligne['email'] ] = $ligne;
	}

	$recu = $par_email[ 'journal-recu' . $domaine ];
	wam_nl_assert_equals( Queue::STATUS_SENT, $recu['status'], 'le statut d’envoi est remonté' );
	wam_nl_assert( '' !== (string) $recu['sent_at'], 'l’heure d’envoi est remontée' );
	wam_nl_assert_equals( 'Zz0 Test', trim( $recu['name'] ), 'le nom est remonté' );
	wam_nl_assert_equals( false, $recu['opened'], 'personne n’a ouvert pour cette ligne' );

	$echec = $par_email[ 'journal-echec' . $domaine ];
	wam_nl_assert_equals( Queue::STATUS_FAILED, $echec['status'], 'l’échec est remonté' );
	wam_nl_assert_equals( 'SMTP : boîte pleine', $echec['error'], 'le message d’erreur exact est remonté' );
	wam_nl_assert_equals( 1, $echec['attempts'], 'le nombre de tentatives est remonté' );
	wam_nl_assert_equals( true, $echec['unsubscribed'], 'la désinscription est remontée' );

	$ouvert = $par_email[ 'journal-ouvert' . $domaine ];
	wam_nl_assert_equals( true, $ouvert['opened'], 'l’ouverture est remontée' );
	wam_nl_assert_equals( true, $ouvert['clicked'], 'le clic est remonté' );
	wam_nl_assert_equals( 1, $ouvert['clicks'], 'le nombre de clics est remonté' );

	$attente = $par_email[ 'journal-attente' . $domaine ];
	wam_nl_assert_equals( Queue::STATUS_PENDING, $attente['status'], 'l’attente est remontée' );
	wam_nl_assert_equals( '', (string) $attente['sent_at'], 'aucune heure d’envoi sur une ligne en attente' );

	echo "\n== Filtres ==\n";
	wam_nl_assert_equals( 2, count( Recipients::query( $nl, array( 'filter' => 'sent' ) ) ), 'filtre « envoyés »' );
	wam_nl_assert_equals( 1, count( Recipients::query( $nl, array( 'filter' => 'failed' ) ) ), 'filtre « échecs »' );
	wam_nl_assert_equals( 1, count( Recipients::query( $nl, array( 'filter' => 'pending' ) ) ), 'filtre « en attente »' );
	wam_nl_assert_equals( 1, count( Recipients::query( $nl, array( 'filter' => 'opened' ) ) ), 'filtre « ouverts »' );
	wam_nl_assert_equals( 1, count( Recipients::query( $nl, array( 'filter' => 'clicked' ) ) ), 'filtre « cliqués »' );
	wam_nl_assert_equals( 1, count( Recipients::query( $nl, array( 'filter' => 'unsubscribed' ) ) ), 'filtre « désinscrits »' );
	wam_nl_assert_equals( 1, Recipients::count( $nl, array( 'filter' => 'failed' ) ), 'le comptage suit le filtre' );
	wam_nl_assert_equals( 4, count( Recipients::query( $nl, array( 'filter' => 'bidon' ) ) ), 'un filtre inconnu ne filtre rien' );

	echo "\n== Recherche et pagination ==\n";
	wam_nl_assert_equals( 1, count( Recipients::query( $nl, array( 'search' => 'journal-echec' ) ) ), 'recherche par adresse' );
	wam_nl_assert_equals( 0, count( Recipients::query( $nl, array( 'search' => 'personne-ici' ) ) ), 'recherche sans résultat' );
	wam_nl_assert_equals( 2, count( Recipients::query( $nl, array( 'per_page' => 2, 'page' => 1 ) ) ), 'première page de 2' );
	wam_nl_assert_equals( 2, count( Recipients::query( $nl, array( 'per_page' => 2, 'page' => 2 ) ) ), 'deuxième page de 2' );
	wam_nl_assert_equals( 0, count( Recipients::query( $nl, array( 'per_page' => 2, 'page' => 9 ) ) ), 'page au-delà de la fin' );

	$page1 = wp_list_pluck( Recipients::query( $nl, array( 'per_page' => 2, 'page' => 1 ) ), 'email' );
	$page2 = wp_list_pluck( Recipients::query( $nl, array( 'per_page' => 2, 'page' => 2 ) ), 'email' );
	wam_nl_assert_equals( array(), array_intersect( $page1, $page2 ), 'aucune ligne n’apparaît sur deux pages' );

	echo "\n== Les compteurs ne mélangent pas deux newsletters ==\n";
	wam_nl_assert_equals( 0, Recipients::count( $autre ), 'la newsletter voisine n’a aucun destinataire' );

	echo "\n== Export CSV ==\n";
	$csv = Recipients::csv( $nl );
	wam_nl_assert( 0 === strpos( $csv, "\xEF\xBB\xBF" ), 'le CSV commence par un BOM UTF-8 (Excel)' );
	$nb_lignes = count( array_filter( explode( "\n", trim( $csv ) ) ) );
	wam_nl_assert_equals( 5, $nb_lignes, 'une ligne d’en-tête et 4 destinataires' );
	wam_nl_assert( false !== strpos( $csv, 'journal-echec' . $domaine ), 'l’adresse en échec est exportée' );
	wam_nl_assert( false !== strpos( $csv, 'boîte pleine' ), 'le message d’erreur est exporté' );
	wam_nl_assert( false !== strpos( $csv, ';' ), 'séparateur point-virgule, comme l’export des abonné·es' );

	$csv_filtre = Recipients::csv( $nl, array( 'filter' => 'failed' ) );
	wam_nl_assert_equals( 2, count( array_filter( explode( "\n", trim( $csv_filtre ) ) ) ), 'l’export suit le filtre affiché' );

	// ------------------------------------------------------------------
	// Le journal se remplit tout seul : c'est le cœur de la demande.
	// ------------------------------------------------------------------
	echo "\n== Le déroulé est tracé sans intervention ==\n";
	Log::purge_newsletter( $nl );

	Scheduler::pause( $nl, 'Trop d’échecs sur le lot' );
	$apres_pause = Log::query( $nl );
	wam_nl_assert_equals( Log::TYPE_PAUSE, $apres_pause[0]['type'], 'la mise en pause est tracée' );
	wam_nl_assert( false !== strpos( (string) $apres_pause[0]['message'], 'Trop d’échecs' ), 'la raison de la pause est tracée' );
	wam_nl_assert_equals( 2, $apres_pause[0]['sent'], 'la pause enregistre l’état de la file' );

	Scheduler::resume( $nl );
	wam_nl_assert_equals( Log::TYPE_RESUME, Log::query( $nl )[0]['type'], 'la reprise est tracée' );

	Scheduler::cancel( $nl );
	wam_nl_assert_equals( Log::TYPE_CANCEL, Log::query( $nl )[0]['type'], 'l’arrêt manuel est tracé' );

	echo "\n== Un lot traité laisse une ligne chiffrée ==\n";
	Log::purge_newsletter( $nl );
	update_post_meta( $nl, Scheduler::META_HTML, '<p>Bonjour {prenom}</p>' );
	Scheduler::set_status( $nl, Scheduler::STATUS_SENDING );
	Settings::update( array( 'batch_size' => 1, 'batch_interval' => 60 ) );

	Scheduler::process( $nl );
	$lot = Log::query( $nl );
	wam_nl_assert( ! empty( $lot ), 'le traitement d’un lot écrit au journal' );
	$ligne_lot = null;
	foreach ( $lot as $entree ) {
		if ( Log::TYPE_BATCH === $entree['type'] ) {
			$ligne_lot = $entree;
			break;
		}
	}
	wam_nl_assert( null !== $ligne_lot, 'une entrée de type « lot » est présente' );
	if ( $ligne_lot ) {
		wam_nl_assert( $ligne_lot['sent'] + $ligne_lot['failed'] >= 1, 'le lot annonce ce qu’il a traité' );
		wam_nl_assert( false !== stripos( (string) $ligne_lot['message'], 'lot' ), 'le message nomme le lot' );
	}

	echo "\n== Écran : aucune fuite entre newsletters, et une sortie lisible ==\n";
	wam_nl_assert( false !== strpos( JournalScreen::url( $nl ), (string) $nl ), 'l’URL de l’écran porte l’identifiant' );
	wam_nl_assert( false !== strpos( JournalScreen::url( $nl, 'deroule' ), 'vue=deroule' ), 'l’URL peut viser un onglet' );

	$utilisateur = wp_get_current_user();
	$admin       = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( $admin ) {
		wp_set_current_user( (int) $admin[0] );
	}

	$_GET['newsletter'] = $nl;
	$_GET['page']       = JournalScreen::PAGE_SLUG;
	ob_start();
	JournalScreen::render();
	$html = (string) ob_get_clean();
	wam_nl_assert( false !== strpos( $html, 'journal-echec' . $domaine ), 'l’écran affiche les destinataires' );
	wam_nl_assert( false !== strpos( $html, 'Par destinataire' ), 'l’onglet par destinataire est présent' );
	wam_nl_assert( false !== strpos( $html, 'Déroulé de l’envoi' ), 'l’onglet du déroulé est présent' );
	wam_nl_assert( false === strpos( $html, '<script' ), 'aucun script injecté dans la page' );

	$_GET['vue'] = 'deroule';
	ob_start();
	JournalScreen::render();
	$html_deroule = (string) ob_get_clean();
	wam_nl_assert( false !== stripos( $html_deroule, 'Lot trait' ), 'le déroulé affiche les entrées du journal' );

	unset( $_GET['newsletter'], $_GET['page'], $_GET['vue'] );
	wp_set_current_user( $utilisateur ? (int) $utilisateur->ID : 0 );

	echo "\n== Les adresses affichées sont échappées ==\n";
	$piege = Subs::upsert(
		array(
			'email'      => 'journal-xss' . $domaine,
			'first_name' => '<script>alert(1)</script>',
			'last_name'  => 'Test',
		)
	);
	if ( ! is_wp_error( $piege ) ) {
		Subs::add_to_lists( (int) $piege['id'], array( $liste ) );
		Queue::clear( $nl );
		Queue::build( $nl, array( $liste ) );
		if ( $admin ) {
			wp_set_current_user( (int) $admin[0] );
		}
		$_GET['newsletter'] = $nl;
		ob_start();
		JournalScreen::render();
		$html_xss = (string) ob_get_clean();
		unset( $_GET['newsletter'] );
		wp_set_current_user( $utilisateur ? (int) $utilisateur->ID : 0 );
		wam_nl_assert( false === strpos( $html_xss, '<script>alert(1)</script>' ), 'un nom piégé ne ressort pas en balise' );
	}

	echo "\n== L'action « Journal d'envoi » apparaît dès qu'il y a quelque chose à lire ==\n";
	$post_autre = get_post( $autre );
	$sans       = NewsletterList::row_actions( array(), $post_autre );
	wam_nl_assert( ! isset( $sans['wam_nl_journal'] ), 'pas de lien sur une newsletter sans file ni journal' );

	Log::record( $autre, Log::TYPE_TEST, '1 test(s) vers quelquun@example.invalid.' );
	$avec = NewsletterList::row_actions( array(), $post_autre );
	wam_nl_assert( isset( $avec['wam_nl_journal'] ), 'le lien apparaît après un simple e-mail de test' );
	wam_nl_assert( ! isset( $avec['wam_nl_stats'] ), 'les statistiques restent masquées sans destinataire' );
	Log::purge_newsletter( $autre );

	echo "\n== Aucune alerte PHP sur une newsletter sans envoi ==\n";
	$avant = count( Log::query( $autre ) );
	wam_nl_assert_equals( 0, $avant, 'journal vide' );
	wam_nl_assert_equals( array(), Recipients::query( $autre ), 'aucune ligne, et aucune alerte' );
	wam_nl_assert( 0 === strpos( Recipients::csv( $autre ), "\xEF\xBB\xBF" ), 'un export vide reste un CSV valide' );

	echo "\n== Suppression d'une newsletter : le journal part avec elle ==\n";
	Log::record( $autre, Log::TYPE_START, 'à supprimer' );
	wp_delete_post( (int) $autre, true );
	wam_nl_assert_equals( 0, Log::count( $autre ), 'le journal est purgé avec la newsletter' );

} finally {
	$nettoyer();

	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}

	echo "\n== État restauré ==\n";
	global $wpdb;
	$t = Subs::table();
	wam_nl_assert_equals(
		'0',
		(string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE email LIKE %s", '%' . $wpdb->esc_like( $domaine ) ) ),
		'aucun abonné·e de test ne subsiste'
	);
}

wam_nl_test_report();
