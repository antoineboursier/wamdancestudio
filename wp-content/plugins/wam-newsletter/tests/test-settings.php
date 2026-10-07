<?php
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Settings\Settings;

// Ce script écrit dans la vraie option du site : l'état est capturé ici et
// restauré dans le finally, pour qu'une assertion qui tourne mal (ou un wp_die
// déclenché par un appel WordPress) ne laisse pas le site dans un état de test.
$reglages_initiaux = get_option( Settings::OPTION, false );

try {

echo "== Valeurs par défaut ==\n";
$d = Settings::defaults();
wam_nl_assert_equals( 'WAM Dance Studio', $d['from_name'], 'expéditeur précalibré' );
wam_nl_assert_equals( 'contact@wamdancestudio.fr', $d['from_email'], 'adresse d expédition' );
wam_nl_assert_equals( 20, $d['batch_size'], 'taille de lot par défaut' );
wam_nl_assert_equals( 60, $d['batch_interval'], 'intervalle par défaut' );
wam_nl_assert_equals( 5, $d['fail_threshold_batch'], 'seuil K' );
wam_nl_assert_equals( 20, $d['fail_threshold_hour'], 'seuil M' );
wam_nl_assert_equals( false, $d['welcome_email_enabled'], 'e-mail de bienvenue désactivé par défaut' );
wam_nl_assert_equals( false, $d['delete_data_on_uninstall'], 'désinstallation non destructrice par défaut' );

echo "== Assainissement : bornes numériques ==\n";
wam_nl_assert_equals( 1, Settings::sanitize( array( 'batch_size' => 0 ) )['batch_size'], 'batch_size 0 ramené à 1 (0 figerait tout envoi)' );
wam_nl_assert_equals( 200, Settings::sanitize( array( 'batch_size' => 10000 ) )['batch_size'], 'batch_size 10000 plafonné à 200' );
wam_nl_assert_equals( 10, Settings::sanitize( array( 'batch_interval' => -5 ) )['batch_interval'], 'intervalle négatif ramené à 10 s' );
wam_nl_assert_equals( 3600, Settings::sanitize( array( 'batch_interval' => 99999 ) )['batch_interval'], 'intervalle plafonné à 3600 s' );
wam_nl_assert_equals( 1, Settings::sanitize( array( 'fail_threshold_batch' => 0 ) )['fail_threshold_batch'], 'seuil K minimum 1' );
wam_nl_assert_equals( 465, Settings::sanitize( array( 'smtp_port' => 99999 ) )['smtp_port'], 'port hors plage ramené au défaut' );

echo "== Assainissement : e-mails ==\n";
wam_nl_assert_equals( 'contact@wamdancestudio.fr', Settings::sanitize( array( 'from_email' => '  CONTACT@WamDanceStudio.FR ' ) )['from_email'], 'adresse normalisée en minuscules' );
wam_nl_assert( ! array_key_exists( 'from_email', Settings::sanitize( array( 'from_email' => 'pas-une-adresse' ) ) ), 'adresse invalide : la clé n est pas retenue, donc rien n est écrasé' );
wam_nl_assert_equals( 'a@b.fr,c@d.fr', Settings::sanitize( array( 'test_recipients' => 'a@b.fr, pas-valide ,C@D.FR' ) )['test_recipients'], 'destinataires de test filtrés et normalisés' );
wam_nl_assert_equals( 'a@b.fr', Settings::sanitize( array( 'test_recipients' => 'a@b.fr, A@B.FR' ) )['test_recipients'], 'doublon de destinataire retiré' );

echo "== Assainissement : liste blanche et booléens ==\n";
wam_nl_assert_equals( 'ssl', Settings::sanitize( array( 'smtp_secure' => 'quantique' ) )['smtp_secure'], 'chiffrement inconnu ramené au défaut ssl' );
wam_nl_assert_equals( 'tls', Settings::sanitize( array( 'smtp_secure' => 'TLS' ) )['smtp_secure'], 'tls accepté, casse indifférente' );
wam_nl_assert_equals( '', Settings::sanitize( array( 'smtp_secure' => '' ) )['smtp_secure'], 'aucun chiffrement accepté' );
wam_nl_assert_equals( true, Settings::sanitize( array( 'track_opens' => '1' ) )['track_opens'], 'booléen depuis une case cochée' );
wam_nl_assert_equals( false, Settings::sanitize( array( 'track_opens' => '' ) )['track_opens'], 'booléen depuis une case décochée' );

echo "== Assainissement : texte riche et clés inconnues ==\n";
$s = Settings::sanitize( array( 'form_consent_text' => "J'accepte <strong>vraiment</strong><script>alert(1)</script>" ) );
wam_nl_assert( false === strpos( $s['form_consent_text'], '<script' ), 'le script est retiré du texte de consentement' );
wam_nl_assert( false !== strpos( $s['form_consent_text'], '<strong>' ), 'le gras est conservé' );
wam_nl_assert( ! array_key_exists( 'cle_inventee', Settings::sanitize( array( 'cle_inventee' => 'valeur' ) ) ), 'une clé hors defaults est ignorée' );

echo "== Lecture / écriture ==\n";
Settings::update( array( 'batch_size' => 35 ) );
wam_nl_assert_equals( 35, Settings::get( 'batch_size' ), 'valeur écrite puis relue' );
wam_nl_assert_equals( 60, Settings::get( 'batch_interval' ), 'les autres clés gardent leur défaut' );
wam_nl_assert_equals( null, Settings::get( 'cle_absente' ), 'clé inconnue renvoie null' );

echo "== Une saisie invalide ne doit pas effacer le réglage en place ==\n";
Settings::update( array( 'from_email' => 'contact@wamdancestudio.fr' ) );
$rejetes = Settings::update( array( 'from_email' => 'contact@wamdancestudio' ) );
wam_nl_assert_equals( 'contact@wamdancestudio.fr', Settings::get( 'from_email' ), 'adresse invalide : la valeur précédente est conservée' );
wam_nl_assert( in_array( 'from_email', $rejetes, true ), 'la clé rejetée est signalée à l appelant' );

$rejetes = Settings::update( array( 'from_email' => '' ) );
wam_nl_assert_equals( '', Settings::get( 'from_email' ), 'une chaîne vide efface volontairement le réglage' );
wam_nl_assert_equals( array(), $rejetes, 'vider volontairement n est pas un rejet' );

echo "== Une valeur non scalaire est rejetée, sans warning PHP ==\n";
// Un POST forgé peut envoyer wam_nl[from_email][]=x : un cast (string) sur un
// tableau émettrait un warning, qui casserait le critère « 0 entrée au debug.log ».
Settings::update( array( 'from_email' => 'contact@wamdancestudio.fr' ) );
$rejetes = Settings::update( array( 'from_email' => array( 'x' ) ) );
wam_nl_assert( in_array( 'from_email', $rejetes, true ), 'tableau rejeté pour une adresse' );
wam_nl_assert_equals( 'contact@wamdancestudio.fr', Settings::get( 'from_email' ), 'et la valeur en place est conservée' );

$rejetes = Settings::update( array( 'form_consent_text' => array( 'x' ) ) );
wam_nl_assert( in_array( 'form_consent_text', $rejetes, true ), 'tableau rejeté pour un texte riche' );

$rejetes = Settings::update( array( 'batch_size' => array( 'x' ) ) );
wam_nl_assert( in_array( 'batch_size', $rejetes, true ), 'tableau rejeté pour un entier borné' );
wam_nl_assert_equals( 35, Settings::get( 'batch_size' ), 'la taille de lot précédente est conservée' );

echo "== Préremplissage depuis une configuration SMTP existante ==\n";
// Objectif : si l'extension qui pilote aujourd'hui le SMTP est désactivée, le
// plugin doit pouvoir prendre le relais sans ressaisie.
Settings::update(
	array(
		'smtp_host' => '',
		'smtp_user' => '',
		'smtp_port' => 465,
	)
);
$copie = array(
	'smtp_host' => 'mail.exemple.test',
	'smtp_user' => 'robot@exemple.test',
	'smtp_port' => 587,
);
$appliques = Settings::seed( $copie );
wam_nl_assert_equals( array( 'smtp_host', 'smtp_user', 'smtp_port' ), $appliques, 'les trois réglages encore au défaut sont repris' );
wam_nl_assert_equals( 'mail.exemple.test', Settings::get( 'smtp_host' ), 'hôte repris' );
wam_nl_assert_equals( 'robot@exemple.test', Settings::get( 'smtp_user' ), 'utilisateur repris' );
wam_nl_assert_equals( 587, Settings::get( 'smtp_port' ), 'port repris' );

echo "== Un préremplissage n écrase jamais une valeur déjà saisie ==\n";
$appliques = Settings::seed( array( 'smtp_host' => 'autre.exemple.test' ) );
wam_nl_assert_equals( array(), $appliques, 'rien n est appliqué sur un réglage personnalisé' );
wam_nl_assert_equals( 'mail.exemple.test', Settings::get( 'smtp_host' ), 'la valeur saisie survit' );

$appliques = Settings::seed( array( 'smtp_host' => 'autre.exemple.test' ), true );
wam_nl_assert_equals( array( 'smtp_host' ), $appliques, 'l écrasement explicite, lui, s applique' );
wam_nl_assert_equals( 'autre.exemple.test', Settings::get( 'smtp_host' ), 'hôte remplacé sur demande' );

echo "== Le préremplissage ne peut pas introduire de mot de passe ni de clé inconnue ==\n";
$appliques = Settings::seed( array( 'smtp_password' => 'jamais', 'smtp_pass' => 'jamais', 'cle_inconnue' => 'x' ), true );
wam_nl_assert_equals( array(), $appliques, 'aucune de ces clés n est appliquée' );
$stocke = (array) get_option( Settings::OPTION, array() );
foreach ( array( 'smtp_password', 'smtp_pass', 'cle_inconnue' ) as $interdite ) {
	wam_nl_assert( ! array_key_exists( $interdite, $stocke ), "la clé $interdite n est pas en base" );
}

echo "== Mot de passe SMTP ==\n";
wam_nl_assert( is_string( Settings::smtp_password() ), 'smtp_password() renvoie toujours une chaîne' );
if ( ! defined( 'WAM_NL_SMTP_PASSWORD' ) ) {
	wam_nl_assert_equals( '', Settings::smtp_password(), 'chaîne vide quand la constante est absente' );
}
$stocke = get_option( Settings::OPTION, array() );
wam_nl_assert( ! array_key_exists( 'smtp_password', (array) $stocke ), 'aucun mot de passe en base' );
wam_nl_assert( ! array_key_exists( 'smtp_password', Settings::defaults() ), 'aucune clé de mot de passe dans les defaults' );
wam_nl_assert_equals( array( 'smtp_password' ), Settings::update( array( 'smtp_password' => 'tentative' ) ), 'une tentative d écriture du mot de passe est rejetée' );
wam_nl_assert( ! array_key_exists( 'smtp_password', (array) get_option( Settings::OPTION, array() ) ), 'et rien n est écrit en base' );

} finally {
	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}
}

echo "== État restauré ==\n";
wam_nl_assert_equals( $reglages_initiaux, get_option( Settings::OPTION, false ), 'réglages du plugin restaurés à l identique' );

wam_nl_test_report();
