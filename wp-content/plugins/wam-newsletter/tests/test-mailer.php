<?php
/**
 * Les branches de Mailer::configure() sont exercées en forçant l'option
 * coulisses_smtp_active (restaurée en finally) : sur ce site elle vaut 1, donc
 * sans ce forçage le chemin « SMTP propre au plugin » ne serait jamais testé.
 *
 * ⚠️ Ce fichier ne doit JAMAIS afficher le mot de passe SMTP, ni directement, ni
 * par le message d'une assertion qui échoue (wam_nl_assert_equals imprime les
 * deux valeurs comparées). Sur une installation où WAM_NL_SMTP_PASSWORD existe
 * déjà, c'est le vrai secret qui partirait sur la sortie standard.
 */
require_once __DIR__ . '/_harness.php';

use PHPMailer\PHPMailer\PHPMailer;
use WamNewsletter\Sending\Mailer;
use WamNewsletter\Settings\Settings;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

$option_externe    = 'coulisses_smtp_active';
$externe_initial   = get_option( $option_externe, 0 );
$reglages_initiaux = get_option( Settings::OPTION, false );

/** PHPMailer neuf, en mode mail() : on observe si configure() le bascule en SMTP. */
$neuf = static function (): PHPMailer {
	$m = new PHPMailer( true );
	$m->isMail();
	return $m;
};

// Si l'installation définit déjà la constante, on ne la redéfinit pas et on
// n'observe pas la branche « sans mot de passe ».
$constante_preexistante = defined( 'WAM_NL_SMTP_PASSWORD' );

try {
	echo "== Détection de l environnement ==\n";
	wam_nl_assert( is_bool( Mailer::local_guard_active() ), 'local_guard_active() renvoie un booléen' );
	wam_nl_assert( Mailer::local_guard_active(), 'garde-fou local détecté (environnement local)' );

	echo "== Branche A : les coulisses pilotent le SMTP ==\n";
	update_option( $option_externe, 1 );
	Settings::update( array( 'smtp_host' => 'smtp.example.test' ) );
	wam_nl_assert( Mailer::external_smtp_active(), 'external_smtp_active() voit l interrupteur à 1' );
	wam_nl_assert( ! Mailer::owns_transport(), 'le plugin ne se déclare pas porteur du transport' );

	$a = $neuf();
	Mailer::configure( $a );
	wam_nl_assert_equals( 'mail', $a->Mailer, 'PHPMailer laissé intact : on ne double pas le hook des coulisses' );

	echo "== Branche B : SMTP propre, mais aucun hôte ==\n";
	update_option( $option_externe, 0 );
	Settings::update( array( 'smtp_host' => '' ) );
	wam_nl_assert( ! Mailer::external_smtp_active(), 'external_smtp_active() voit l interrupteur à 0' );
	wam_nl_assert( ! Mailer::owns_transport(), 'sans hôte, le plugin ne porte pas le transport' );

	$b = $neuf();
	Mailer::configure( $b );
	wam_nl_assert_equals( 'mail', $b->Mailer, 'hôte vide : on laisse mail()' );

	echo "== Branche C : hôte renseigné mais mot de passe absent (Review Focus 4) ==\n";
	Settings::update(
		array(
			'smtp_host'   => 'smtp.example.test',
			'smtp_port'   => 587,
			'smtp_secure' => 'tls',
			'smtp_user'   => 'robot@wamdancestudio.fr',
		)
	);

	if ( $constante_preexistante ) {
		echo "  SKIP WAM_NL_SMTP_PASSWORD est déjà définie sur cette installation :\n";
		echo "       la branche « sans mot de passe » n'y est pas observable, et on ne\n";
		echo "       redéfinit pas la constante (le vrai secret serait exposé).\n";
	} else {
		wam_nl_assert( '' === Settings::smtp_password(), 'aucun mot de passe disponible à ce stade' );
		wam_nl_assert( ! Mailer::owns_transport(), 'sans mot de passe, le plugin ne porte pas le transport' );
		$c = $neuf();
		Mailer::configure( $c );
		wam_nl_assert_equals( 'mail', $c->Mailer, 'sans mot de passe, on reste sur mail() au lieu d une authentification vide' );

		define( 'WAM_NL_SMTP_PASSWORD', 'secret-de-test' );
	}

	echo "== Branche D : SMTP propre complet ==\n";
	// Aucune assertion ne compare le mot de passe à une valeur littérale : en cas
	// d'échec, le harnais imprimerait les deux côtés de la comparaison.
	wam_nl_assert( '' !== Settings::smtp_password(), 'un mot de passe est disponible (valeur jamais affichée)' );
	wam_nl_assert( Mailer::owns_transport(), 'le plugin se déclare porteur du transport' );

	$d = $neuf();
	Mailer::configure( $d );
	wam_nl_assert_equals( 'smtp', $d->Mailer, 'PHPMailer basculé en SMTP' );
	wam_nl_assert_equals( 'smtp.example.test', $d->Host, 'hôte repris des réglages' );
	wam_nl_assert_equals( 587, $d->Port, 'port repris des réglages' );
	wam_nl_assert_equals( 'tls', $d->SMTPSecure, 'chiffrement repris des réglages' );
	wam_nl_assert_equals( true, $d->SMTPAuth, 'authentification activée' );
	wam_nl_assert_equals( 'robot@wamdancestudio.fr', $d->Username, 'utilisateur repris des réglages' );
	wam_nl_assert( Settings::smtp_password() === $d->Password, 'mot de passe injecté depuis la constante (valeur jamais affichée)' );
	wam_nl_assert_equals( 'robot@wamdancestudio.fr', $d->Sender, 'enveloppe = compte authentifié (sinon l hébergeur rejette)' );
	wam_nl_assert_equals( true, $d->SMTPAutoTLS, 'auto-TLS laissé actif hors SSL implicite' );

	Settings::update( array( 'smtp_secure' => 'ssl' ) );
	$e = $neuf();
	Mailer::configure( $e );
	wam_nl_assert_equals( false, $e->SMTPAutoTLS, 'auto-TLS désactivé en SSL implicite' );

	echo "== Expéditeur : propre aux envois du plugin, pas au courrier du site ==\n";
	Settings::update(
		array(
			'from_name'  => 'Newsletter WAM',
			'from_email' => 'news@wamdancestudio.fr',
		)
	);
	// On mesure la présence de NOS filtres, pas la valeur finale : le site a ses
	// propres filtres d'expéditeur (wam-custom-plugin en priorité 10), qui sont
	// légitimes et ne nous concernent pas.
	$notre_filtre_email = static function (): bool {
		return false !== has_filter( 'wp_mail_from', array( Mailer::class, 'from_email' ) );
	};
	$notre_filtre_nom   = static function (): bool {
		return false !== has_filter( 'wp_mail_from_name', array( Mailer::class, 'from_name' ) );
	};

	wam_nl_assert( ! $notre_filtre_email(), 'hors envoi du plugin, aucun filtre d expéditeur posé par le plugin' );
	wam_nl_assert( ! $notre_filtre_nom(), 'hors envoi du plugin, aucun filtre de nom posé par le plugin' );

	$dedans = Mailer::with_sender(
		static function () use ( $notre_filtre_email, $notre_filtre_nom ): array {
			return array(
				'pose_email' => $notre_filtre_email(),
				'pose_nom'   => $notre_filtre_nom(),
				'email'      => apply_filters( 'wp_mail_from', 'defaut@exemple.test' ),
				'nom'        => apply_filters( 'wp_mail_from_name', 'Défaut' ),
			);
		}
	);
	wam_nl_assert( $dedans['pose_email'], 'pendant un envoi du plugin, le filtre d expéditeur est posé' );
	wam_nl_assert( $dedans['pose_nom'], 'pendant un envoi du plugin, le filtre de nom est posé' );
	wam_nl_assert_equals( 'news@wamdancestudio.fr', $dedans['email'], 'et l expéditeur vient des réglages du plugin' );
	wam_nl_assert_equals( 'Newsletter WAM', $dedans['nom'], 'et le nom vient des réglages du plugin' );

	wam_nl_assert( ! $notre_filtre_email(), 'les filtres sont retirés après l envoi' );
	wam_nl_assert( ! $notre_filtre_nom(), 'y compris celui du nom' );

	// Même en cas d'exception pendant l'envoi, les filtres ne doivent pas rester.
	try {
		Mailer::with_sender(
			static function () {
				throw new RuntimeException( 'échec simulé pendant l envoi' );
			}
		);
	} catch ( RuntimeException $e ) {
		wam_nl_assert_equals( 'échec simulé pendant l envoi', $e->getMessage(), 'l exception remonte à l appelant' );
	}
	wam_nl_assert( ! $notre_filtre_email(), 'après une exception, le filtre d expéditeur est retiré' );
	wam_nl_assert( ! $notre_filtre_nom(), 'après une exception, le filtre de nom est retiré' );

	echo "== Reprise des réglages SMTP d une autre extension ==\n";
	$copie = Mailer::external_smtp_snapshot();
	wam_nl_assert( is_array( $copie ), 'external_smtp_snapshot() renvoie un tableau' );
	wam_nl_assert( ! array_key_exists( 'smtp_password', $copie ), 'la copie ne contient jamais de mot de passe' );
	foreach ( array_keys( $copie ) as $cle ) {
		wam_nl_assert( array_key_exists( $cle, Settings::defaults() ), "clé copiée connue des réglages : $cle" );
	}

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
	// Si l'option n'existait pas avant le test, on ne la laisse pas derrière nous.
	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}
}

echo "== État restauré ==\n";
wam_nl_assert_equals( $externe_initial, get_option( $option_externe ), 'coulisses_smtp_active remis à sa valeur d origine' );
wam_nl_assert_equals( $reglages_initiaux, get_option( Settings::OPTION, false ), 'réglages du plugin restaurés à l identique' );

wam_nl_test_report();
