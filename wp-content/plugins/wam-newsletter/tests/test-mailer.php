<?php
/**
 * Les deux branches de Mailer::configure() sont exercées en forçant l'option
 * coulisses_smtp_active (restaurée en fin de test) : sur ce site elle vaut 1, donc
 * sans ce forçage le chemin « SMTP propre au plugin » ne serait jamais testé.
 */
require_once __DIR__ . '/_harness.php';

use PHPMailer\PHPMailer\PHPMailer;
use WamNewsletter\Sending\Mailer;
use WamNewsletter\Settings\Settings;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

$option_externe   = 'coulisses_smtp_active';
$externe_initial  = get_option( $option_externe, 0 );
$reglages_initiaux = get_option( Settings::OPTION, array() );

/** PHPMailer neuf, en mode mail() : on observe si configure() le bascule en SMTP. */
$neuf = static function (): PHPMailer {
	$m = new PHPMailer( true );
	$m->isMail();
	return $m;
};

try {
	echo "== Détection de l environnement ==\n";
	wam_nl_assert( is_bool( Mailer::local_guard_active() ), 'local_guard_active() renvoie un booléen' );
	wam_nl_assert( Mailer::local_guard_active(), 'garde-fou local détecté (environnement local)' );

	echo "== Branche A : les coulisses pilotent le SMTP ==\n";
	update_option( $option_externe, 1 );
	Settings::update( array( 'smtp_host' => 'smtp.example.test' ) );
	wam_nl_assert( Mailer::external_smtp_active(), 'external_smtp_active() voit l interrupteur à 1' );

	$a = $neuf();
	Mailer::configure( $a );
	wam_nl_assert_equals( 'mail', $a->Mailer, 'PHPMailer laissé intact : on ne double pas le hook des coulisses' );
	wam_nl_assert_equals( 'defaut@exemple.test', Mailer::from_email( 'defaut@exemple.test' ), 'expéditeur laissé aux coulisses' );
	wam_nl_assert_equals( 'Défaut', Mailer::from_name( 'Défaut' ), 'nom d expéditeur laissé aux coulisses' );

	echo "== Branche B : SMTP propre, mais aucun hôte ==\n";
	update_option( $option_externe, 0 );
	Settings::update( array( 'smtp_host' => '' ) );
	wam_nl_assert( ! Mailer::external_smtp_active(), 'external_smtp_active() voit l interrupteur à 0' );

	$b = $neuf();
	Mailer::configure( $b );
	wam_nl_assert_equals( 'mail', $b->Mailer, 'hôte vide : on laisse mail()' );
	wam_nl_assert_equals( (string) Settings::get( 'from_email' ), Mailer::from_email( 'defaut@exemple.test' ), 'expéditeur issu des réglages' );
	wam_nl_assert_equals( (string) Settings::get( 'from_name' ), Mailer::from_name( 'Défaut' ), 'nom issu des réglages' );

	echo "== Branche C : hôte renseigné mais mot de passe absent (Review Focus 4) ==\n";
	Settings::update(
		array(
			'smtp_host'   => 'smtp.example.test',
			'smtp_port'   => 587,
			'smtp_secure' => 'tls',
			'smtp_user'   => 'robot@wamdancestudio.fr',
		)
	);
	wam_nl_assert_equals( '', Settings::smtp_password(), 'la constante WAM_NL_SMTP_PASSWORD est bien absente à ce stade' );

	$c = $neuf();
	Mailer::configure( $c );
	wam_nl_assert_equals( 'mail', $c->Mailer, 'sans mot de passe, on reste sur mail() au lieu d une authentification vide' );

	echo "== Branche D : SMTP propre complet ==\n";
	// La constante ne peut plus être retirée ensuite : cette branche vient en dernier.
	define( 'WAM_NL_SMTP_PASSWORD', 'secret-de-test' );
	wam_nl_assert_equals( 'secret-de-test', Settings::smtp_password(), 'le mot de passe vient de la constante' );

	$d = $neuf();
	Mailer::configure( $d );
	wam_nl_assert_equals( 'smtp', $d->Mailer, 'PHPMailer basculé en SMTP' );
	wam_nl_assert_equals( 'smtp.example.test', $d->Host, 'hôte repris des réglages' );
	wam_nl_assert_equals( 587, $d->Port, 'port repris des réglages' );
	wam_nl_assert_equals( 'tls', $d->SMTPSecure, 'chiffrement repris des réglages' );
	wam_nl_assert_equals( true, $d->SMTPAuth, 'authentification activée' );
	wam_nl_assert_equals( 'robot@wamdancestudio.fr', $d->Username, 'utilisateur repris des réglages' );
	wam_nl_assert_equals( 'secret-de-test', $d->Password, 'mot de passe injecté depuis la constante' );
	wam_nl_assert_equals( 'robot@wamdancestudio.fr', $d->Sender, 'enveloppe = compte authentifié (sinon l hébergeur rejette)' );
	wam_nl_assert_equals( true, $d->SMTPAutoTLS, 'auto-TLS laissé actif hors SSL implicite' );

	Settings::update( array( 'smtp_secure' => 'ssl' ) );
	$e = $neuf();
	Mailer::configure( $e );
	wam_nl_assert_equals( false, $e->SMTPAutoTLS, 'auto-TLS désactivé en SSL implicite' );

	echo "== Envoi de test : refus ==\n";
	$r = Mailer::send_test( 'pas-une-adresse' );
	wam_nl_assert_equals( false, $r['ok'], 'adresse invalide refusée' );
	wam_nl_assert( '' !== $r['message'], 'un message explique le refus' );

	Settings::update( array( 'test_recipients' => '' ) );
	$r = Mailer::send_test( '' );
	wam_nl_assert_equals( false, $r['ok'], 'aucun destinataire : refus explicite' );
	wam_nl_assert( '' !== $r['message'], 'un message explique l absence de destinataire' );

	echo "== Envoi de test : repli sur les adresses des réglages ==\n";
	Settings::update( array( 'test_recipients' => 'recette@wamdancestudio.test' ) );
	wam_nl_assert_equals( array( 'recette@wamdancestudio.test' ), Settings::test_recipients(), 'les adresses de test sont lues depuis les réglages' );

	echo "== Envoi de test : acceptation ==\n";
	// On remet les coulisses aux commandes : c'est l'état réel du site, et le
	// garde-fou local force mail() de toute façon.
	update_option( $option_externe, $externe_initial );
	$r = Mailer::send_test( 'recette@wamdancestudio.test' );
	wam_nl_assert_equals( true, $r['ok'], 'envoi accepté vers une adresse valide (intercepté par Mailpit en local)' );
	wam_nl_assert( false !== strpos( $r['message'], 'recette@wamdancestudio.test' ), 'le message nomme le destinataire' );
} finally {
	update_option( $option_externe, $externe_initial );
	update_option( Settings::OPTION, $reglages_initiaux );
}

echo "== État restauré ==\n";
wam_nl_assert_equals( $externe_initial, get_option( $option_externe ), 'coulisses_smtp_active remis à sa valeur d origine' );
wam_nl_assert_equals( $reglages_initiaux, get_option( Settings::OPTION, array() ), 'réglages du plugin restaurés' );

wam_nl_test_report();
