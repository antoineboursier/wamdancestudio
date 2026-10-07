<?php
/**
 * L'écran de réglages. On teste ce qui est testable sans navigateur :
 * construction des URLs, normalisation du POST (cases décochées) et rendu HTML.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Sending\Mailer;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Settings\SettingsPage;

require_once ABSPATH . 'wp-admin/includes/template.php';

echo "== URLs des onglets ==\n";
$url = SettingsPage::url( 'debit' );
wam_nl_assert( false !== strpos( $url, 'page=' . SettingsPage::PAGE_SLUG ), 'l URL porte le slug de la page' );
wam_nl_assert( false !== strpos( $url, 'tab=debit' ), 'l URL porte l onglet demandé' );
wam_nl_assert( false !== strpos( SettingsPage::url(), 'tab=expediteur' ), 'onglet par défaut = expediteur' );

echo "== Normalisation du POST : une case décochée doit redevenir false ==\n";
// Une case non cochée n'est pas envoyée par le navigateur. Sans remise à vide,
// elle resterait vraie indéfiniment et serait impossible à désactiver.
$n = SettingsPage::normalize_submission( array( 'batch_size' => '5' ), 'suivi' );
wam_nl_assert( array_key_exists( 'track_opens', $n ), 'track_opens réintroduit pour l onglet suivi' );
wam_nl_assert_equals( '', $n['track_opens'], 'track_opens remis à vide' );
wam_nl_assert_equals( '', $n['track_clicks'], 'track_clicks remis à vide' );
wam_nl_assert_equals( '', $n['delete_data_on_uninstall'], 'delete_data_on_uninstall remis à vide' );
wam_nl_assert_equals( '5', $n['batch_size'], 'les autres champs sont conservés tels quels' );

$n = SettingsPage::normalize_submission( array( 'track_opens' => '1' ), 'suivi' );
wam_nl_assert_equals( '1', $n['track_opens'], 'une case cochée est conservée' );
wam_nl_assert_equals( '', $n['track_clicks'], 'une case décochée du même onglet est remise à vide' );

$n = SettingsPage::normalize_submission( array(), 'formulaire' );
wam_nl_assert_equals( '', $n['welcome_email_enabled'], 'onglet formulaire : la case de bienvenue est remise à vide' );
wam_nl_assert( ! array_key_exists( 'track_opens', $n ), 'onglet formulaire : aucune case d un autre onglet n est touchée' );

$n = SettingsPage::normalize_submission( array(), 'expediteur' );
wam_nl_assert_equals( array(), $n, 'onglet expediteur : aucune case à cocher, rien n est ajouté' );

$n = SettingsPage::normalize_submission( array(), 'onglet-inconnu' );
wam_nl_assert_equals( array(), $n, 'onglet inconnu : rien n est ajouté' );

echo "== Chaîne complète POST -> enregistrement ==\n";
$sauvegarde = get_option( Settings::OPTION, array() );
Settings::update( array( 'track_opens' => true, 'track_clicks' => true ) );
wam_nl_assert_equals( true, Settings::get( 'track_opens' ), 'départ : track_opens activé' );
Settings::update( SettingsPage::normalize_submission( array( 'track_clicks' => '1' ), 'suivi' ) );
wam_nl_assert_equals( false, Settings::get( 'track_opens' ), 'case décochée -> réglage désactivé' );
wam_nl_assert_equals( true, Settings::get( 'track_clicks' ), 'case cochée -> réglage conservé' );
update_option( Settings::OPTION, $sauvegarde );

echo "== Rendu de la page ==\n";
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admin[0] );

$rendu = static function ( string $onglet ): string {
	$_GET['tab'] = $onglet;
	ob_start();
	SettingsPage::render();
	$html = (string) ob_get_clean();
	unset( $_GET['tab'] );
	return $html;
};

$html = $rendu( 'expediteur' );
foreach ( array_keys( SettingsPage::TABS ) as $slug ) {
	wam_nl_assert( false !== strpos( $html, 'tab=' . $slug ), "onglet $slug présent dans la navigation" );
}
wam_nl_assert( 1 === substr_count( $html, 'nav-tab-active' ), 'un seul onglet actif' );
wam_nl_assert( false !== strpos( $html, 'name="action" value="wam_nl_save_settings"' ), 'action du formulaire posée' );
wam_nl_assert( false !== strpos( $html, '_wpnonce' ), 'nonce présent' );
wam_nl_assert( false !== strpos( $html, 'name="wam_nl[from_email]"' ), 'champ adresse d expédition' );
wam_nl_assert( false !== strpos( $html, 'name="wam_nl_send_test"' ), 'bouton d envoi de test' );

echo "== Diagnostic SMTP : il doit nommer le propriétaire réel ==\n";
if ( Mailer::external_smtp_active() ) {
	wam_nl_assert( false !== strpos( $html, 'coulisses' ), 'le diagnostic nomme Les coulisses du site WAM' );
	wam_nl_assert( false === strpos( $html, 'name="wam_nl[smtp_host]"' ), 'les champs SMTP propres sont masqués quand les coulisses pilotent' );
} else {
	wam_nl_assert( false !== strpos( $html, 'name="wam_nl[smtp_host]"' ), 'les champs SMTP propres sont affichés' );
}
if ( Mailer::local_guard_active() ) {
	wam_nl_assert( false !== strpos( $html, 'Mailpit' ), 'le diagnostic signale l interception locale' );
}

echo "== Contenu propre à chaque onglet ==\n";
$h = $rendu( 'debit' );
wam_nl_assert( false !== strpos( $h, 'name="wam_nl[batch_size]"' ), 'onglet débit : taille de lot' );
wam_nl_assert( false !== strpos( $h, 'name="wam_nl[fail_threshold_hour]"' ), 'onglet débit : seuil horaire' );

$h = $rendu( 'formulaire' );
wam_nl_assert( false !== strpos( $h, 'name="wam_nl[form_consent_text]"' ), 'onglet formulaire : texte de consentement' );

$h = $rendu( 'suivi' );
wam_nl_assert( false !== strpos( $h, 'name="wam_nl[track_opens]"' ), 'onglet suivi : ouvertures' );
wam_nl_assert( false !== strpos( $h, 'name="wam_nl[delete_data_on_uninstall]"' ), 'onglet suivi : effacement à la désinstallation' );

$h = $rendu( 'onglet-bidon' );
wam_nl_assert( false !== strpos( $h, 'name="wam_nl[from_email]"' ), 'onglet inconnu : repli sur expediteur' );

echo "== Échappement ==\n";
$sauvegarde = get_option( Settings::OPTION, array() );
Settings::update( array( 'from_name' => 'WAM "guillemets" & <script>alert(1)</script>' ) );
$h = $rendu( 'expediteur' );
wam_nl_assert( false === strpos( $h, '<script>alert(1)</script>' ), 'aucun script non échappé dans la valeur affichée' );
update_option( Settings::OPTION, $sauvegarde );

wp_set_current_user( 0 );
wam_nl_test_report();
