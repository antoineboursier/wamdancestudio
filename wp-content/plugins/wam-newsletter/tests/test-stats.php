<?php
/**
 * Écran de statistiques : graduations, chronologie, carte des clics, liens.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Stats\Events;
use WamNewsletter\Stats\Screen;
use WamNewsletter\Subscribers\Repository as Subs;

$domaine = '@wam-nl-test.invalid';
$posts   = array();
$abonnes = array();

try {

	echo "== Graduations rondes de l'axe ==\n";
	foreach ( array( 0 => 4, 3 => 4, 9 => 12, 10 => 12, 17 => 20, 38 => 40, 120 => 120, 950 => 1200 ) as $max => $attendu ) {
		$plafond = Screen::nice_max( $max );
		wam_nl_assert_equals( $attendu, $plafond, "plafond pour un maximum de $max" );
		wam_nl_assert( 0 === $plafond % 4 && $plafond >= $max, "$plafond : quatre graduations entières, au-dessus du maximum" );
	}

	echo "== Chronologie : créneaux vides présents, heure puis jour ==\n";
	$nl      = wp_insert_post( array( 'post_type' => 'wam_newsletter', 'post_status' => 'draft', 'post_title' => 'ZZTest stats' ) );
	$posts[] = $nl;
	$a       = Subs::insert( array( 'email' => 'stats-a' . $domaine, 'consent_source' => 'admin' ) );
	$b       = Subs::insert( array( 'email' => 'stats-b' . $domaine, 'consent_source' => 'admin' ) );
	$abonnes = array( $a, $b );
	global $wpdb;
	$wpdb->insert( Events::table(), array( 'newsletter_id' => $nl, 'subscriber_id' => $a, 'type' => 'open', 'created_at' => '2026-10-08 21:10:00' ) );
	$wpdb->insert( Events::table(), array( 'newsletter_id' => $nl, 'subscriber_id' => $b, 'type' => 'open', 'created_at' => '2026-10-08 23:40:00' ) );
	$wpdb->insert( Events::table(), array( 'newsletter_id' => $nl, 'subscriber_id' => $b, 'type' => 'click', 'url' => 'https://exemple.test/a?x=1&y=2', 'created_at' => '2026-10-08 23:45:00' ) );
	$chrono = Events::timeline( $nl );
	wam_nl_assert_equals( 'hour', $chrono['step'], 'activité sur trois heures : pas horaire' );
	wam_nl_assert_equals( 3, count( $chrono['points'] ), '21h, 22h (vide) et 23h : le créneau vide est présent' );
	wam_nl_assert_equals( 0, $chrono['points'][1]['open'] + $chrono['points'][1]['click'], 'le créneau de 22h est à zéro' );
	wam_nl_assert_equals( 1, $chrono['points'][2]['click'], 'le clic tombe à 23h' );

	$wpdb->insert( Events::table(), array( 'newsletter_id' => $nl, 'subscriber_id' => $a, 'type' => 'click', 'url' => 'https://exemple.test/b', 'created_at' => '2026-10-13 10:00:00' ) );
	$chrono = Events::timeline( $nl );
	wam_nl_assert_equals( 'day', $chrono['step'], 'au-delà de 72 heures : pas journalier' );
	wam_nl_assert_equals( 6, count( $chrono['points'] ), 'du 8 au 13 octobre : six jours' );

	echo "== Carte des clics : pastilles sur les liens de l'e-mail ==\n";
	$html = '<p><a href="https://exemple.test/a?x=1&#038;y=2" style="color:#fff">Lien A</a> <a href="https://exemple.test/c">Lien C</a> <a href="mailto:x@y.z">Mail</a></p>';
	$carte = Screen::heatmap_html( $html, Events::top_links( $nl, 50 ) );
	wam_nl_assert( false !== strpos( $carte, 'Lien A</a><span' ) && false !== strpos( $carte, '1 clic · 1 pers.' ), 'le lien cliqué porte son compte, même avec « &#038; » dans le HTML' );
	wam_nl_assert( 1 === preg_match( '#Lien C</a><span[^>]*>0 clic</span>#', $carte ), 'le lien jamais cliqué porte « 0 clic »' );
	wam_nl_assert( false !== strpos( $carte, 'Mail</a></p>' ), 'un lien mailto n’a pas de pastille' );

	echo "== Liens : titre et vignette du contenu visé ==\n";
	$stage = (int) get_posts( array( 'post_type' => 'stages', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'publish' ) )[0];
	if ( $stage ) {
		$info = Screen::describe_link( (string) get_permalink( $stage ), '' );
		wam_nl_assert_equals( (string) get_the_title( $stage ), $info['titre'], 'un lien vers un stage prend le titre du stage' );
	}
	$info = Screen::describe_link( 'https://exemple.test/c', '<a href="https://exemple.test/c"><img src="https://exemple.test/photo.jpg" alt=""></a>' );
	wam_nl_assert_equals( 'https://exemple.test/photo.jpg', $info['vignette'], 'un lien autour d’une image prend cette image en vignette' );
	wam_nl_assert_equals( 'exemple.test/c', $info['titre'], 'sans texte ni contenu du site : l’adresse lisible' );

	echo "== Écran complet, sans alerte PHP ==\n";
	update_post_meta( $nl, Scheduler::META_HTML, $html );
	$_GET['newsletter'] = $nl;
	$ancien = get_current_user_id();
	wp_set_current_user( 1 );
	ob_start();
	Screen::render();
	$ecran = (string) ob_get_clean();
	wp_set_current_user( $ancien );
	wam_nl_assert( false !== strpos( $ecran, 'wam-nl-kpis' ), 'chiffres clés rendus' );
	wam_nl_assert( false !== strpos( $ecran, 'wam-nl-chart__svg' ), 'courbe rendue' );
	wam_nl_assert( false !== strpos( $ecran, 'wam-nl-heatmap' ) && false !== strpos( $ecran, 'sandbox=""' ), 'carte des clics rendue dans une iframe sans script' );
	wam_nl_assert( false !== strpos( $ecran, 'Voir les chiffres' ), 'vue tableau de la courbe présente' );

} finally {
	foreach ( $posts as $id ) {
		Queue::clear( (int) $id );
		Events::purge_newsletter( (int) $id );
		wp_delete_post( (int) $id, true );
	}
	foreach ( $abonnes as $id ) {
		Subs::delete( (int) $id );
	}
	unset( $_GET['newsletter'] );
}

wam_nl_test_report();
