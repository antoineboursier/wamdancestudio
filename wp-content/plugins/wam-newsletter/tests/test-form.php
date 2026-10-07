<?php
/**
 * Lot 3 — formulaire d'inscription.
 *
 * Comme pour le lot 2, tout ce qui est créé porte @wam-nl-test.invalid et le
 * finally nettoie, y compris les réglages et les transients de limitation.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Form\Form;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Repository as Subs;

$domaine   = '@wam-nl-test.invalid';
$prefixe_l = 'ZZTest ';

$reglages_initiaux = get_option( Settings::OPTION, false );

$nettoyer = static function () use ( $domaine, $prefixe_l ) {
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
};

$nettoyer();
Form::register_assets();

try {

	echo "== Enregistrement du code court et du bloc ==\n";
	wam_nl_assert( shortcode_exists( Form::SHORTCODE ), 'le code court [wam_newsletter_form] existe' );
	Form::register_block();
	$registre = \WP_Block_Type_Registry::get_instance();
	wam_nl_assert( null !== $registre->get_registered( Form::BLOCK ), 'le bloc wam-nl/form est enregistré' );
	$bloc = $registre->get_registered( Form::BLOCK );
	wam_nl_assert( is_callable( $bloc->render_callback ), 'le bloc est rendu côté serveur' );

	echo "== Rendu du formulaire ==\n";
	$html = Form::render();
	wam_nl_assert( false !== strpos( $html, 'name="wam_nl_nonce"' ), 'le nonce est présent' );
	wam_nl_assert( false !== strpos( $html, 'name="action" value="' . Form::ACTION . '"' ), 'action AJAX posée' );
	wam_nl_assert( false !== strpos( $html, 'name="wam_nl_hp"' ), 'champ honeypot présent' );
	wam_nl_assert( false !== strpos( $html, 'wam-hp-field' ), 'le honeypot utilise la classe masquée du thème' );
	wam_nl_assert( false !== strpos( $html, 'name="consentement"' ), 'case de consentement présente' );
	wam_nl_assert( false !== strpos( $html, 'required aria-required="true"' ), 'champs obligatoires annoncés aux lecteurs d’écran' );
	wam_nl_assert( false !== strpos( $html, 'aria-live="polite"' ), 'la zone de réponse est annoncée (aria-live)' );
	wam_nl_assert( false !== strpos( $html, 'wam-contact-form-wrapper' ), 'réutilise le gabarit de formulaire du thème' );
	wam_nl_assert( false !== strpos( $html, 'btn btn-primary' ), 'bouton du design system du thème' );
	wam_nl_assert( false === strpos( $html, 'style=' ), 'aucun style en ligne' );

	echo "== Deux formulaires sur une même page ont des identifiants distincts ==\n";
	$html2 = Form::render();
	preg_match( '/id="(wam-nl-form-\d+)"/', $html, $m1 );
	preg_match( '/id="(wam-nl-form-\d+)"/', $html2, $m2 );
	wam_nl_assert( ! empty( $m1[1] ) && ! empty( $m2[1] ), 'les deux formulaires portent un identifiant' );
	wam_nl_assert( $m1[1] !== $m2[1], 'les identifiants diffèrent (labels et aria-live restent valides)' );

	echo "== Le texte de consentement vient des réglages et est filtré ==\n";
	Settings::update( array( 'form_consent_text' => 'Je veux <strong>bien</strong> <script>alert(1)</script>' ) );
	$html = Form::render();
	wam_nl_assert( false !== strpos( $html, '<strong>bien</strong>' ), 'le gras saisi est conservé' );
	wam_nl_assert( false === strpos( $html, '<script>' ), 'le script est retiré' );

	echo "== Titre et texte facultatifs ==\n";
	$html = Form::render( array( 'titre' => 'Reste<script>', 'texte' => 'Chapô' ) );
	wam_nl_assert( false !== strpos( $html, 'Chapô' ), 'le texte d’introduction est rendu' );
	wam_nl_assert( false === strpos( $html, '<script>' ), 'le titre est échappé' );
	$html = Form::render();
	wam_nl_assert( false === strpos( $html, 'wam-nl-form__titre' ), 'sans titre, aucun h2 vide' );

	echo "== Inscription : création ==\n";
	$liste = Lists::ensure( $prefixe_l . 'Formulaire' );
	Settings::update( array( 'form_list_id' => $liste, 'welcome_email_enabled' => false ) );

	$r = Form::subscribe( '  NOUVEAU' . $domaine . ' ', 'Ada', 'Lovelace' );
	wam_nl_assert( ! is_wp_error( $r ), 'inscription acceptée' );
	wam_nl_assert( ! $r['deja'], 'signalée comme nouvelle' );

	$abonne = Subs::find_by_email( 'nouveau' . $domaine );
	wam_nl_assert( null !== $abonne, 'le contact est en base' );
	wam_nl_assert_equals( 'subscribed', $abonne['status'], 'statut abonné·e' );
	wam_nl_assert_equals( 'form', $abonne['consent_source'], 'source de consentement « form »' );
	wam_nl_assert( ! empty( $abonne['consent_at'] ), 'date de consentement enregistrée' );
	wam_nl_assert( Subs::is_token( $abonne['token'] ), 'token généré (lien de désinscription possible)' );
	wam_nl_assert( in_array( $liste, Subs::list_ids_of( (int) $abonne['id'] ), true ), 'rattaché·e à la liste des réglages' );

	echo "== Inscription : adresse invalide ==\n";
	wam_nl_assert( is_wp_error( Form::subscribe( 'pas-une-adresse' ) ), 'adresse invalide refusée' );
	wam_nl_assert( is_wp_error( Form::subscribe( '' ) ), 'adresse vide refusée' );

	echo "== Inscription : déjà inscrit·e, on ne duplique pas ==\n";
	$avant = Subs::count( array( 'search' => $domaine, 'include_trashed' => true ) );
	$r     = Form::subscribe( 'nouveau' . $domaine, 'Autre', 'Nom' );
	wam_nl_assert( $r['deja'], 'signalée comme déjà inscrite' );
	wam_nl_assert_equals( $avant, Subs::count( array( 'search' => $domaine, 'include_trashed' => true ) ), 'aucun doublon créé' );
	wam_nl_assert_equals( 'Ada', Subs::find_by_email( 'nouveau' . $domaine )['first_name'], 'le prénom d’origine n’est pas écrasé' );

	echo "== Inscription : un·e désabonné·e qui redemande est réabonné·e (§6) ==\n";
	// Seul chemin du plugin autorisé à réabonner : la personne le demande
	// elle-même, en cochant la case.
	$id = (int) Subs::find_by_email( 'nouveau' . $domaine )['id'];
	Subs::set_status( $id, Subs::STATUS_UNSUBSCRIBED );
	$r = Form::subscribe( 'nouveau' . $domaine, 'Ada' );
	wam_nl_assert( ! is_wp_error( $r ), 'réinscription acceptée' );
	wam_nl_assert( ! $r['deja'], 'traitée comme une nouvelle inscription' );
	$abonne = Subs::find( $id );
	wam_nl_assert_equals( 'subscribed', $abonne['status'], 'repassé·e en abonné·e' );
	wam_nl_assert_equals( null, $abonne['unsubscribed_at'], 'la date de désinscription est effacée' );

	echo "== Inscription : corbeille et rebond repassent en abonné·e ==\n";
	foreach ( array( Subs::STATUS_TRASHED, Subs::STATUS_BOUNCED ) as $statut ) {
		Subs::set_status( $id, $statut );
		Form::subscribe( 'nouveau' . $domaine, 'Ada' );
		wam_nl_assert_equals( 'subscribed', Subs::find( $id )['status'], "depuis le statut $statut, l’inscription rétablit l’abonnement" );
	}

	echo "== Sans liste configurée, l'inscription est enregistrée quand même ==\n";
	Settings::update( array( 'form_list_id' => 0 ) );
	$r = Form::subscribe( 'sansliste' . $domaine, 'Grace' );
	wam_nl_assert( ! is_wp_error( $r ), 'inscription acceptée sans liste' );
	$sans = Subs::find_by_email( 'sansliste' . $domaine );
	wam_nl_assert_equals( array(), Subs::list_ids_of( (int) $sans['id'] ), 'aucune liste rattachée' );
	wam_nl_assert( null === Form::target_list(), 'target_list() le signale' );
	Settings::update( array( 'form_list_id' => $liste ) );
	wam_nl_assert( is_array( Form::target_list() ), 'target_list() retrouve la liste configurée' );

	echo "== E-mail de bienvenue : désactivé par défaut ==\n";
	Settings::update( array( 'welcome_email_enabled' => false ) );
	wam_nl_assert( ! Form::send_welcome( 'quelquun' . $domaine, 'Ada' ), 'rien n’est envoyé quand l’option est désactivée' );

	echo "== E-mail de bienvenue : activé mais sans contenu, on n'envoie pas un e-mail vide ==\n";
	Settings::update(
		array(
			'welcome_email_enabled' => true,
			'welcome_email_subject' => 'Bienvenue',
			'welcome_email_body'    => '',
		)
	);
	wam_nl_assert( ! Form::send_welcome( 'quelquun' . $domaine, 'Ada' ), 'corps vide : pas d’envoi' );

	Settings::update( array( 'welcome_email_subject' => '', 'welcome_email_body' => 'Coucou {prenom}' ) );
	wam_nl_assert( ! Form::send_welcome( 'quelquun' . $domaine, 'Ada' ), 'objet vide : pas d’envoi' );

	echo "== E-mail de bienvenue : envoi réel (intercepté par Mailpit en local) ==\n";
	Settings::update(
		array(
			'welcome_email_subject' => 'Bienvenue chez WAM',
			'welcome_email_body'    => 'Bonjour {prenom}, ton adresse {email} est enregistrée.',
		)
	);
	$capture = null;
	$espion  = static function ( $args ) use ( &$capture ) {
		$capture = $args;
		return $args;
	};
	add_filter( 'wp_mail', $espion );
	$envoye = Form::send_welcome( 'bienvenue' . $domaine, 'Ada' );
	remove_filter( 'wp_mail', $espion );

	wam_nl_assert( $envoye, 'l’envoi est accepté' );
	wam_nl_assert( is_array( $capture ), 'wp_mail a bien été appelé' );
	if ( is_array( $capture ) ) {
		wam_nl_assert_equals( 'Bienvenue chez WAM', $capture['subject'], 'objet repris des réglages' );
		wam_nl_assert( false !== strpos( $capture['message'], 'Bonjour Ada' ), '{prenom} est remplacé' );
		wam_nl_assert( false !== strpos( $capture['message'], 'bienvenue' . $domaine ), '{email} est remplacé' );
		wam_nl_assert( false !== strpos( implode( ' ', (array) $capture['headers'] ), 'text/html' ), 'envoyé en HTML' );
	}
	Settings::update( array( 'welcome_email_enabled' => false ) );

	echo "== Limitation de fréquence par IP ==\n";
	$_SERVER['REMOTE_ADDR'] = '203.0.113.42';
	// Nettoyage préalable : un test précédent a pu laisser un compteur.
	$reflexion = new ReflectionMethod( Form::class, 'rate_key' );
	$reflexion->setAccessible( true );
	delete_transient( $reflexion->invoke( null ) );

	wam_nl_assert( ! Form::rate_limited(), 'au départ, aucune limitation' );
	$frapper = new ReflectionMethod( Form::class, 'rate_hit' );
	$frapper->setAccessible( true );
	for ( $i = 0; $i < Form::RATE_LIMIT_MAX; $i++ ) {
		$frapper->invoke( null );
	}
	wam_nl_assert( Form::rate_limited(), 'la limite est atteinte après ' . Form::RATE_LIMIT_MAX . ' essais' );
	delete_transient( $reflexion->invoke( null ) );
	wam_nl_assert( ! Form::rate_limited(), 'la limite retombe quand le compteur expire' );

	echo "== L'adresse IP n'est jamais stockée en clair ==\n";
	$cle = $reflexion->invoke( null );
	wam_nl_assert( false === strpos( $cle, '203.0.113.42' ), 'la clé du compteur ne contient pas l’IP' );
	wam_nl_assert( 1 === preg_match( '/^wam_nl_rl_[0-9a-f]{64}$/', $cle ), 'la clé est un condensat' );

} finally {
	unset( $_SERVER['REMOTE_ADDR'] );
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

wam_nl_test_report();
