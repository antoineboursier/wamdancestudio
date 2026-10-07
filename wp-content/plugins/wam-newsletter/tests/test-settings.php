<?php
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Settings\Settings;

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
wam_nl_assert_equals( '', Settings::sanitize( array( 'from_email' => 'pas-une-adresse' ) )['from_email'], 'adresse invalide rejetée' );
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
$avant = get_option( Settings::OPTION, array() );
Settings::update( array( 'batch_size' => 35 ) );
wam_nl_assert_equals( 35, Settings::get( 'batch_size' ), 'valeur écrite puis relue' );
wam_nl_assert_equals( 60, Settings::get( 'batch_interval' ), 'les autres clés gardent leur défaut' );
wam_nl_assert_equals( null, Settings::get( 'cle_absente' ), 'clé inconnue renvoie null' );
update_option( Settings::OPTION, $avant );

echo "== Mot de passe SMTP ==\n";
wam_nl_assert( is_string( Settings::smtp_password() ), 'smtp_password() renvoie toujours une chaîne' );
if ( ! defined( 'WAM_NL_SMTP_PASSWORD' ) ) {
	wam_nl_assert_equals( '', Settings::smtp_password(), 'chaîne vide quand la constante est absente' );
}
$stocke = get_option( Settings::OPTION, array() );
wam_nl_assert( ! array_key_exists( 'smtp_password', (array) $stocke ), 'aucun mot de passe en base' );

wam_nl_test_report();
