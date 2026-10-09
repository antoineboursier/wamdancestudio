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

	// ---------------------------------------------------------------
	echo "== Import WooCommerce : les commandes de robots sont écartées ==
";
	$bot = array( 'WamNewsletter\Integrations\WooCommerceImporter', 'is_probable_bot' );
	wam_nl_assert( $bot( 'x@exemple.test', 'OrahTIJZULWsAyzer', 'ZLADmKuaaMaXxtNhFnhgk' ), 'noms aléatoires détectés' );
	wam_nl_assert( $bot( 'a.b.c.d.e@gmail.com', 'Jean', 'Dupont' ), 'adresse Gmail à 4 points détectée' );
	foreach ( array( array( 'Marie', 'Dupont' ), array( 'Jean-Baptiste', 'McDonald' ), array( 'Anne Sophie', 'DeLaCruz' ), array( 'Élodie', 'Lefèvre-Martin' ) ) as $nom ) {
		wam_nl_assert( ! $bot( 'vrai@exemple.test', $nom[0], $nom[1] ), 'vrai nom conservé : ' . $nom[0] . ' ' . $nom[1] );
	}
	wam_nl_assert( ! $bot( 'marie.dupont@gmail.com', 'Marie', 'Dupont' ), 'Gmail avec un seul point conservé' );
	wam_nl_assert( $bot( 'prime9@topcrush.org', 'Keesha', 'Owen' ), 'domaine de spam connu détecté' );
	wam_nl_assert( $bot( 'u80e4dc6f@blogranks.top', 'Jada', 'Bainton' ), 'extension .top détectée' );
	wam_nl_assert( $bot( 'x@privbibl.ru', 'Илана', 'Броня' ), 'noms cyrilliques détectés' );
	wam_nl_assert( ! $bot( 'amelie@hotmail.fr', 'Amélie', 'Gottrand' ), 'vraie adhérente conservée' );
	wam_nl_assert( ! $bot( 'a@laposte.net', 'Clémence', 'Joets' ), 'laposte.net conservé' );

	// ---------------------------------------------------------------
	echo "== L'objet et le texte d'aperçu survivent à l'enregistrement par l'API REST ==
";
	// Sans le support « custom-fields », WordPress retire le champ `meta` de l'API
	// REST du type de contenu : l'éditeur croyait enregistrer l'objet, et rien
	// n'arrivait en base. Le test passe par la même route que Gutenberg.
	wam_nl_assert( post_type_supports( NewsletterPostType::POST_TYPE, 'custom-fields' ), 'le type de contenu déclare le support custom-fields' );
	$ancien_user = get_current_user_id();
	wp_set_current_user( 1 );
	$brouillon           = wp_insert_post( array( 'post_type' => NewsletterPostType::POST_TYPE, 'post_status' => 'draft', 'post_title' => 'ZZTest rest' ) );
	$posts_temporaires[] = $brouillon;
	$requete             = new WP_REST_Request( 'POST', '/wp/v2/' . NewsletterPostType::POST_TYPE . '/' . $brouillon );
	$requete->set_header( 'content-type', 'application/json' );
	$requete->set_body( wp_json_encode( array( 'meta' => array( '_wam_nl_subject' => 'Mon objet', '_wam_nl_preheader' => 'Mon aperçu' ) ) ) );
	$reponse = rest_do_request( $requete );
	wam_nl_assert_equals( 200, $reponse->get_status(), 'la route REST répond 200' );
	wam_nl_assert_equals( 'Mon objet', (string) get_post_meta( $brouillon, '_wam_nl_subject', true ), 'objet enregistré en base' );
	wam_nl_assert_equals( 'Mon aperçu', (string) get_post_meta( $brouillon, '_wam_nl_preheader', true ), 'texte d’aperçu enregistré en base' );
	$lecture = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . NewsletterPostType::POST_TYPE . '/' . $brouillon ) )->get_data();
	wam_nl_assert( isset( $lecture['meta']['_wam_nl_subject'] ) && 'Mon objet' === $lecture['meta']['_wam_nl_subject'], 'l’éditeur relit l’objet par l’API' );
	wp_set_current_user( $ancien_user );

	// ---------------------------------------------------------------
	echo "== Brouillon jusqu'à l'envoi définitif, publiée ensuite ==
";
	$envoi = wp_insert_post( array( 'post_type' => NewsletterPostType::POST_TYPE, 'post_status' => 'draft', 'post_title' => 'ZZTest publication', 'post_content' => EditorSetup::default_blocks() ) );
	$posts_temporaires[] = $envoi;
	update_post_meta( $envoi, '_wam_nl_subject', 'Test publication' );
	update_post_meta( $envoi, '_wam_nl_list_ids', array( $liste ) );
	wam_nl_assert_equals( 'draft', get_post_status( $envoi ), 'brouillon tant que rien n’est envoyé' );
	$r = Scheduler::start( $envoi, wp_date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
	wam_nl_assert( ! is_wp_error( $r ), 'l’envoi programmé est accepté' );
	wam_nl_assert_equals( 'publish', get_post_status( $envoi ), 'passée en « publiée » à l’envoi définitif' );
	Scheduler::unschedule( $envoi );

	// ---------------------------------------------------------------
	echo "== Images : 24px d'air du côté où il y a du contenu ==
";
	$para  = "<!-- wp:paragraph --><p>Texte</p><!-- /wp:paragraph -->";
	$image = '<!-- wp:image --><figure class="wp-block-image"><img src="https://exemple.test/a.jpg" alt="a"/></figure><!-- /wp:image -->';
	$rendre = static function ( string $contenu ): string {
		return \WamNewsletter\Render\BlockRenderer::render_list( parse_blocks( $contenu ) );
	};
	$au_milieu = $rendre( $para . "

" . $image . "

" . $para );
	wam_nl_assert( false !== strpos( $au_milieu, 'padding:36px 20px 48px;' ), 'image entre deux blocs : 36px en haut, 48px en bas' );
	$en_tete = $rendre( $image . "

" . $para );
	wam_nl_assert( false !== strpos( $en_tete, 'padding:12px 20px 48px;' ), 'image en tête : rien en haut, 48px en bas' );
	$en_fin = $rendre( $para . "

" . $image );
	wam_nl_assert( false !== strpos( $en_fin, 'padding:36px 20px 12px;' ), 'image en fin : 36px en haut, rien en bas' );
	$seule = $rendre( $image );
	wam_nl_assert( false !== strpos( $seule, 'padding:12px 20px 12px;' ), 'image seule : espacement de base' );

	// ---------------------------------------------------------------
	echo "== Bannières : deux versions dédiées, ou une seule zoomée en repli ==\n";
	global $wpdb;
	$id_desktop = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name LIKE 'template-head-desktop%' ORDER BY ID DESC LIMIT 1" );
	$id_mobile  = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name LIKE 'template-head-mobile%' ORDER BY ID DESC LIMIT 1" );
	$gabarit    = (string) file_get_contents( WAM_NL_DIR . 'templates/email/base.php' );

	if ( $id_desktop && $id_mobile ) {
		wam_nl_assert_equals( $id_desktop, \WamNewsletter\Render\Brand::banner_id(), 'la bannière ordinateur est celle nommée template-head-desktop' );
		wam_nl_assert_equals( $id_mobile, \WamNewsletter\Render\Brand::banner_mobile_id(), 'la bannière mobile est celle nommée template-head-mobile' );

		$entete = \WamNewsletter\Render\Blocks\Custom::header( array() );
		wam_nl_assert( false !== strpos( $entete, 'class="wam-nl-banner-desktop"' ) && false !== strpos( $entete, 'class="wam-nl-banner-mobile"' ), 'les deux images sont dans l’en-tête' );
		wam_nl_assert( false !== strpos( $entete, 'wam-nl-banner--duo' ), 'l’en-tête se déclare en mode « deux bannières »' );
		wam_nl_assert( false === strpos( $entete, 'wam-nl-banner__img' ), 'pas de zoom quand une vraie bannière mobile existe' );
		wam_nl_assert( 1 === preg_match( '/wam-nl-banner-mobile"[^>]*style="display:none;[^"]*mso-hide:all/', $entete ), 'la version mobile est cachée par défaut (et pour Outlook)' );
		wam_nl_assert( 1 === preg_match( '/wam-nl-banner-desktop"[^>]*style="display:block;width:100%;max-width:620px/', $entete ), 'la version ordinateur est visible par défaut' );
		wam_nl_assert( 1 === preg_match( '#src="[^"]*template-head-desktop[^"]*\.jpg"#', $entete ) && 1 === preg_match( '#src="[^"]*template-head-mobile[^"]*\.jpg"#', $entete ), 'chacune est servie en JPG (pas d’AVIF), avec son propre fichier' );
		wam_nl_assert( 1 === preg_match( '/@media[^{]*max-width: 620px\)\s*\{.*\.wam-nl-banner-desktop\s*\{[^}]*display: none !important/s', $gabarit ), 'la media query mobile masque la version ordinateur' );
		wam_nl_assert( 1 === preg_match( '/@media[^{]*max-width: 620px\)\s*\{.*\.wam-nl-banner-mobile\s*\{[^}]*display: block !important[^}]*width: 100% !important/s', $gabarit ), 'et affiche la version mobile pleine largeur' );

		// Une image imposée dans le bloc ne se marie pas avec la bannière mobile par défaut.
		$impose = \WamNewsletter\Render\Blocks\Custom::header( array( 'attachmentId' => $id_desktop ) );
		wam_nl_assert( false === strpos( $impose, 'wam-nl-banner-mobile' ) && false !== strpos( $impose, 'wam-nl-banner__img' ), 'image imposée dans le bloc : une seule bannière, zoomée sur mobile' );
	} else {
		echo "  (bannières template-head-* absentes de cette base : cas ignoré)\n";
	}

	$ancienne = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = 'email-banner' LIMIT 1" );
	if ( $ancienne ) {
		$seule = \WamNewsletter\Render\Blocks\Custom::header( array( 'attachmentId' => $ancienne ) );
		wam_nl_assert( false !== strpos( $seule, 'class="wam-nl-banner"' ) && false !== strpos( $seule, 'class="wam-nl-banner__img"' ), 'repli : une seule bannière, avec ses classes de zoom' );
		wam_nl_assert( false !== strpos( $seule, 'width:100%;max-width:620px' ), 'repli : largeur normale sur ordinateur et dans Outlook' );
	}
	wam_nl_assert( 1 === preg_match( '/@media[^{]*max-width: 620px\)\s*\{.*\.wam-nl-banner__img\s*\{[^}]*width: 140% !important/s', $gabarit ), 'le zoom à 140 % n’existe que dans la media query mobile' );


	// ---------------------------------------------------------------
	echo "== Recherche de contenus : le sous-titre distingue deux titres identiques ==
";
	$recherche = new WP_REST_Request( 'GET', '/x' );
	$recherche->set_param( 'postType', 'cours' );
	$resultats = \WamNewsletter\Editor\RestApi::content_search( $recherche )->get_data();
	wam_nl_assert( ! $resultats || array_key_exists( 'subtitle', $resultats[0] ), 'chaque résultat porte une clé « subtitle »' );

	// ---------------------------------------------------------------
	echo "== Retours du 09/10 : couleurs de la palette ==\n";
	foreach ( array( 'accent-pink', 'accent-orange', 'accent-green' ) as $slug ) {
		wam_nl_assert( 1 === preg_match( '/^#[0-9A-F]{6}$/', \WamNewsletter\Render\Brand::color_from_slug( $slug ) ), "le slug $slug de la palette du thème est résolu en hexadécimal" );
	}
	wam_nl_assert_equals( \WamNewsletter\Render\Brand::color( 'separator' ), \WamNewsletter\Render\Brand::color_from_slug( 'separator' ), 'le slug « separator » est le turquoise WAM' );
	wam_nl_assert_equals( \WamNewsletter\Render\Brand::color_from_slug( 'accent-orange' ), \WamNewsletter\Render\Brand::color_from_slug( 'var:preset|color|accent-orange' ), 'écriture var:preset|color|… résolue comme le slug' );
	wam_nl_assert_equals( '', \WamNewsletter\Render\Brand::color_from_slug( 'inconnue-xyz' ), 'slug inconnu : chaîne vide' );

	echo "== Titres et paragraphes à fond, bordure, arrondi ==\n";
	$rendre_blocs = static function ( string $contenu ): string {
		return \WamNewsletter\Render\BlockRenderer::render_list( parse_blocks( $contenu ) );
	};
	$titre_pilule = '<!-- wp:heading {"level":4,"style":{"typography":{"textAlign":"center"},"border":{"radius":{"topLeft":"39px","topRight":"39px","bottomLeft":"39px","bottomRight":"39px"},"width":"4px"}},"backgroundColor":"separator","textColor":"accent-orange"} --><h4 class="wp-block-heading">Retest</h4><!-- /wp:heading -->';
	$r = $rendre_blocs( $titre_pilule );
	wam_nl_assert( false !== strpos( $r, 'border-radius:39px 39px 39px 39px' ), 'arrondi des quatre coins rendu' );
	wam_nl_assert( 1 === preg_match( '/border:4px solid #[0-9A-F]{6}/', $r ), 'bordure de 4px rendue' );
	wam_nl_assert( false !== strpos( $r, 'background-color:' . \WamNewsletter\Render\Brand::color( 'separator' ) ), 'fond turquoise rendu' );
	wam_nl_assert( false !== strpos( $r, 'border-collapse:separate' ), 'table en border-collapse:separate (condition de l’arrondi)' );
	wam_nl_assert( false !== strpos( $r, 'class="wam-nl-row-box"' ), 'classe de la rangée, cible de l’espacement mobile' );
	wam_nl_assert( false !== strpos( $r, 'color:' . \WamNewsletter\Render\Brand::color_from_slug( 'accent-orange' ) ), 'couleur de texte choisie rendue' );

	$titre_simple = '<!-- wp:heading --><h2 class="wp-block-heading">Simple</h2><!-- /wp:heading -->';
	wam_nl_assert( false === strpos( $rendre_blocs( $titre_simple ), 'border-collapse:separate' ), 'un titre sans fond ni bordure reste une rangée ordinaire' );

	$para_fond = '<!-- wp:paragraph {"backgroundColor":"accent"} --><p>Sur fond jaune</p><!-- /wp:paragraph -->';
	$r = $rendre_blocs( $para_fond );
	wam_nl_assert( false !== strpos( $r, 'background-color:' . \WamNewsletter\Render\Brand::color( 'accent' ) ), 'paragraphe à fond coloré' );
	wam_nl_assert( false !== strpos( $r, 'color:' . \WamNewsletter\Render\Brand::contrast_color( \WamNewsletter\Render\Brand::color( 'accent' ) ) ), 'texte lisible sur le fond (contraste calculé)' );

	echo "== Images, boutons et séparateurs : espacements ==\n";
	$image_col = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:image --><figure class="wp-block-image"><img src="https://exemple.test/a.jpg" alt="a"/></figure><!-- /wp:image --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Texte</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->';
	wam_nl_assert( false !== strpos( $rendre_blocs( $image_col ), 'padding:12px 20px 48px' ), 'une image de colonne garde 48px en dessous (les colonnes s’empilent sur mobile)' );
	wam_nl_assert( false !== strpos( $rendre_blocs( $image ), 'class="wam-nl-row-image"' ), 'l’image porte sa classe d’espacement mobile' );
	$bouton = \WamNewsletter\Render\Blocks\Custom::button( array( 'text' => 'Go', 'url' => 'https://exemple.test' ) );
	wam_nl_assert( false !== strpos( $bouton, 'padding:30px 20px' ) && false !== strpos( $bouton, 'class="wam-nl-row-bouton"' ), 'bouton : 30px en haut et en bas, classe mobile' );
	$gabarit_mobile = (string) file_get_contents( WAM_NL_DIR . 'templates/email/base.php' );
	foreach ( array( 'wam-nl-row-image', 'wam-nl-row-box', 'wam-nl-row-bouton' ) as $classe ) {
		wam_nl_assert( 1 === preg_match( '/@media[^{]*max-width: 620px\)\s*\{.*\.' . $classe . '\s*\{[^}]*!important/s', $gabarit_mobile ), "la media query mobile règle .$classe" );
	}

	$image_etroite = '<!-- wp:image {"width":"186px","align":"center"} --><figure class="wp-block-image aligncenter"><img src="https://exemple.test/a.jpg" alt="a"/></figure><!-- /wp:image -->';
	$r = $rendre_blocs( $image_etroite );
	wam_nl_assert( false !== strpos( $r, 'max-width:186px' ), 'la largeur choisie dans l’éditeur (186px) est respectée' );
	wam_nl_assert( false !== strpos( $r, 'margin:0 auto;' ), 'une image centrée plus étroite que la cellule se centre par ses marges' );

	echo "== Liens : couleur choisie, sinon blanc ; adresse du pied jamais en lien bleu ==\n";
	$lien_colore = '<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"var:preset|color|accent-green"}}}}} --><p>Voir <a href="https://exemple.test/x">le lien</a></p><!-- /wp:paragraph -->';
	$r = $rendre_blocs( $lien_colore );
	wam_nl_assert( 1 === preg_match( '/<a [^>]*style="color:' . preg_quote( \WamNewsletter\Render\Brand::color_from_slug( 'accent-green' ), '/' ) . ';text-decoration:underline;"/', $r ), 'la couleur de lien choisie dans l’éditeur est appliquée' );
	$lien_simple = '<!-- wp:paragraph --><p>Voir <a href="https://exemple.test/x">le lien</a></p><!-- /wp:paragraph -->';
	$r = $rendre_blocs( $lien_simple );
	wam_nl_assert( 1 === preg_match( '/<a [^>]*style="color:' . preg_quote( \WamNewsletter\Render\Brand::color( 'text' ), '/' ) . ';/', $r ), 'sans choix, le lien est blanc (crème), jamais bleu' );
	$titre_lien = '<!-- wp:heading {"style":{"elements":{"link":{"color":{"text":"var:preset|color|accent-pink"}}}}} --><h2 class="wp-block-heading"><a href="https://exemple.test/y">Titre lié</a></h2><!-- /wp:heading -->';
	wam_nl_assert( false !== strpos( $rendre_blocs( $titre_lien ), 'color:' . \WamNewsletter\Render\Brand::color_from_slug( 'accent-pink' ) . ';text-decoration:underline;' ), 'la couleur de lien vaut aussi pour un titre' );
	$pied = \WamNewsletter\Render\Blocks\Custom::footer( array() );
	wam_nl_assert( false !== strpos( $pied, '&zwnj;' ), 'l’adresse porte des caractères invisibles qui cassent la détection automatique' );
	wam_nl_assert( 1 === preg_match( '/<span style="color:' . preg_quote( \WamNewsletter\Render\Brand::color( 'muted' ), '/' ) . ';text-decoration:none;">2&zwnj;0&zwnj;2&zwnj;/', $pied ), 'l’adresse a sa couleur posée en ligne' );

	echo "== Contenus WAM : sous-titre vert, date et horaire du stage ==\n";
	$stage = wp_insert_post( array( 'post_type' => 'stages', 'post_status' => 'publish', 'post_title' => 'ZZTest stage retours' ) );
	$posts_temporaires[] = $stage;
	update_post_meta( $stage, 'sous_titre', 'Intermédiaires dès 16 ans' );
	update_post_meta( $stage, 'date_stage', '20270927' );
	update_post_meta( $stage, 'heure_debut', '15:30' );
	update_post_meta( $stage, 'heure_de_fin', '17:30' );
	wam_nl_assert_equals( '27/09/27 · 15h30-17h30', \WamNewsletter\Render\Blocks\Posts::formatted_when( 'stages', $stage ), 'stage : JJ/MM/AA puis horaire' );
	update_post_meta( $stage, 'heure_debut', '18:00' );
	update_post_meta( $stage, 'heure_de_fin', '' );
	wam_nl_assert_equals( '27/09/27 · 18h', \WamNewsletter\Render\Blocks\Posts::formatted_when( 'stages', $stage ), 'horaire sans minutes ni fin : « 18h »' );
	update_post_meta( $stage, 'heure_debut', '' );
	wam_nl_assert_equals( '27/09/27', \WamNewsletter\Render\Blocks\Posts::formatted_when( 'stages', $stage ), 'sans horaire : la date seule' );
	update_post_meta( $stage, 'heure_debut', '15:30' );
	update_post_meta( $stage, 'heure_de_fin', '17:30' );
	$rendu_stage = \WamNewsletter\Render\Blocks\Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $stage ), 'showDate' => true ) );
	wam_nl_assert( 1 === preg_match( '/font-size:22px[^"]*color:' . preg_quote( \WamNewsletter\Render\Brand::color( 'separator' ), '/' ) . '[^"]*"[^>]*>Intermédiaires dès 16 ans/iu', $rendu_stage ), 'sous-titre en 22px, vert WAM' );
	wam_nl_assert( false !== strpos( $rendu_stage, '27/09/27 · 15h30-17h30' ), 'date et horaire rendus' );
	wam_nl_assert( 1 === preg_match( '#Intermédiaires dès 16 ans</p></td></tr></table><table[^>]*><tr><td[^>]*><p[^>]*>27/09/27#u', $rendu_stage ), 'la date est sur sa propre ligne, sous le sous-titre' );

} finally {
	$nettoyer();
	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}
}

wam_nl_test_report();
