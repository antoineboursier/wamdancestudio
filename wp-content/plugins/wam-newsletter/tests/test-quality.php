<?php
/**
 * Contrôle qualité des adresses, outil de rattrapage et synchro WooCommerce.
 *
 * Les cas viennent de la base réelle (09/10/2026) : 9 robots WooCommerce, 12
 * adresses à domaine mal tapé, des adresses à suffixe technique Brevo.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Form\Form;
use WamNewsletter\Integrations\WooCommerceSync;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Quality;
use WamNewsletter\Subscribers\Repository as Subs;

$domaine           = '@wam-nl-test.invalid';
$prefixe_l         = 'ZZTest ';
$reglages_initiaux = get_option( Settings::OPTION, false );
$commandes         = array();

$nettoyer = static function () use ( $domaine, $prefixe_l, &$commandes ) {
	global $wpdb;
	$t = Subs::table();
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `$t` WHERE email LIKE %s OR email LIKE %s", '%' . $wpdb->esc_like( $domaine ), '%@gmai.test' ) ) as $id ) {
		Subs::delete( (int) $id );
	}
	foreach ( Lists::all() as $liste ) {
		if ( 0 === strpos( (string) $liste['name'], $prefixe_l ) ) {
			Lists::delete( (int) $liste['id'] );
		}
	}
	foreach ( $commandes as $id ) {
		if ( $id && function_exists( 'wc_get_order' ) ) {
			$o = wc_get_order( $id );
			if ( $o ) {
				$o->delete( true );
			}
		}
	}
	$commandes = array();
};

$nettoyer();

try {

	echo "== Verdicts sur les cas réels (DNS coupé : règles pures) ==\n";
	$cas = array(
		// adresse                                  prénom      nom          verdict attendu          adresse retenue
		array( 'marie.dupont@gmail.com', 'Marie', 'Dupont', Quality::OK, 'marie.dupont@gmail.com' ),
		array( 'clairevanpa@gmai.com', 'Claire', 'Vanpa', Quality::FIX, 'clairevanpa@gmail.com' ),
		array( 'rhalmi.kamel@gmail.cim', 'Kamel', 'Rhalmi', Quality::FIX, 'rhalmi.kamel@gmail.com' ),
		array( 'juliemouret59@gmail.co', 'Julie', 'Mouret', Quality::FIX, 'juliemouret59@gmail.com' ),
		array( 'iza-pl@hotmai.fr', 'Iza', 'Pl', Quality::FIX, 'iza-pl@hotmail.fr' ),
		array( 'bossard.anais@hotmail.ff', 'Anaïs', 'Bossard', Quality::FIX, 'bossard.anais@hotmail.fr' ),
		array( 'catherine.dupire@orange.ft', 'Catherine', 'Dupire', Quality::FIX, 'catherine.dupire@orange.fr' ),
		array( 'khenfer.sarah@yahoo.fe', 'Sarah', 'Khenfer', Quality::FIX, 'khenfer.sarah@yahoo.fr' ),
		array( 'bongoemilie@yahoo.fr.87279766.t-sender-sib.com', 'Emilie', 'Bongo', Quality::FIX, 'bongoemilie@yahoo.fr' ),
		array( 'aurore_p@yahoo.com.87279766.t-sender-sib.com', 'Aurore', 'P', Quality::FIX, 'aurore_p@yahoo.com' ),
		array( 'prime9@topcrush.org', 'Keesha', 'Owen', Quality::REJECT, 'prime9@topcrush.org' ),
		array( 'u80e4dc6f@blogranks.top', 'Jada', 'Bainton', Quality::REJECT, 'u80e4dc6f@blogranks.top' ),
		array( 'x@privbibl.ru', 'Илана', 'Броня', Quality::REJECT, 'x@privbibl.ru' ),
		array( 'info239@noreply0.com', 'Info', 'Machin', Quality::REJECT, 'info239@noreply0.com' ),
		array( 'noreply@exemple.fr', 'A', 'B', Quality::REJECT, 'noreply@exemple.fr' ),
		array( 'reply+e0760b6e04294d20@messaging.yelp.com', 'A', 'B', Quality::REJECT, 'reply+e0760b6e04294d20@messaging.yelp.com' ),
		array( 'pas-une-adresse', 'A', 'B', Quality::REJECT, 'pas-une-adresse' ),
		array( 'OrahTIJZ@exemple.fr', 'OrahTIJZULWsAyzer', 'ZLADmKuaaMaXxtNhFnhgk', Quality::REJECT, 'orahtijz@exemple.fr' ),
	);
	foreach ( $cas as $c ) {
		$r = Quality::assess( $c[0], $c[1], $c[2], false );
		wam_nl_assert_equals( $c[3], $r['verdict'], 'verdict de ' . $c[0] );
		wam_nl_assert_equals( $c[4], $r['email'], 'adresse retenue pour ' . $c[0] );
	}

	echo "== Vrais cas qui NE doivent PAS être touchés ==\n";
	foreach ( array(
		array( 'zora59000@hotmail.fr', 'zora59000', 'Dahmani' ),
		array( 'celined59273@gmail.com', 'Céline', 'Durand' ),
		array( 'mimounztn@icloud.com', 'Imane', 'FAIDHERBE' ),
		array( 'ddelannay@ville-roubaix.fr', 'D', 'Delannay' ),
		array( 'zhengmai.zhou@skema.edu', 'Zhengmai', 'Zhou' ),
		array( 'christellecarlier@ymail.com', 'Christelle', 'Carlier' ),
		array( 'emmanuelle@wanadoo.fr', 'Emmanuelle', 'Dupré' ),
		array( 'contact@lapetitefleurquidanse.fr', '', '' ),
	) as $c ) {
		$r = Quality::assess( $c[0], $c[1], $c[2], false );
		wam_nl_assert( in_array( $r['verdict'], array( Quality::OK, Quality::REVIEW ), true ) && $r['email'] === $c[0], 'conservé tel quel : ' . $c[0] );
		wam_nl_assert( Quality::REJECT !== $r['verdict'], 'jamais écarté : ' . $c[0] );
	}

	echo "== Doute signalé, jamais modifié ==\n";
	$r = Quality::assess( 'emmagawish@yahoo1.com', 'Emma', 'Gawish', false );
	wam_nl_assert_equals( Quality::REVIEW, $r['verdict'], 'yahoo1.com : à vérifier à la main' );
	wam_nl_assert_equals( 'emmagawish@yahoo1.com', $r['email'], 'yahoo1.com : adresse intacte' );

	echo "== Vérification DNS réelle (domaine qui n'existe pas) ==\n";
	remove_filter( 'wam_nl_quality_check_dns', '__return_false' );
	$existe = Quality::assess( 'jean@gmail.com', 'Jean', 'Dupont' );
	wam_nl_assert( Quality::OK === $existe['verdict'] || Quality::REVIEW === $existe['verdict'], 'gmail.com reçoit du courrier' );
	$inconnu = Quality::assess( 'jean@ce-domaine-nexiste-pas-wam-2026.fr', 'Jean', 'Dupont' );
	wam_nl_assert_equals( Quality::REJECT, $inconnu['verdict'], 'domaine inexistant écarté' );
	wam_nl_assert_equals( 'dns', $inconnu['code'], 'raison : domaine sans messagerie' );
	add_filter( 'wam_nl_quality_check_dns', '__return_false' );

	echo "== Formulaire public ==\n";
	$r = Form::subscribe( 'robot@topcrush.org', 'Keesha', 'Owen' );
	wam_nl_assert( ! is_wp_error( $r ), 'un robot reçoit une réponse de succès (on ne lui apprend rien)' );
	wam_nl_assert( null === Subs::find_by_email( 'robot@topcrush.org' ), '…mais n’est pas enregistré' );

	$r = Form::subscribe( 'claire@gmai.com', 'Claire', 'Vanpa' );
	wam_nl_assert( is_wp_error( $r ), 'une faute de domaine est refusée' );
	wam_nl_assert( false !== strpos( $r->get_error_message(), 'claire@gmail.com' ), 'avec la correction proposée dans le message' );
	wam_nl_assert( null === Subs::find_by_email( 'claire@gmai.com' ), 'rien d’enregistré avec la faute' );
	wam_nl_assert( null === Subs::find_by_email( 'claire@gmail.com' ), 'rien d’enregistré avec la correction non confirmée' );

	$r = Form::subscribe( 'noreply@exemple.fr', 'A', 'B' );
	wam_nl_assert( is_wp_error( $r ), 'une adresse technique est refusée' );

	echo "== Outil de rattrapage de l'existant ==\n";
	$liste = Lists::ensure( $prefixe_l . 'Qualité' );
	// faute + adresse correcte déjà présente (doublon à fusionner)
	$juste  = Subs::insert( array( 'email' => 'dup@gmail.com', 'first_name' => 'Dup', 'consent_source' => 'mailpoet' ) );
	$faute  = Subs::insert( array( 'email' => 'dup@gmai.com', 'first_name' => 'Dup', 'consent_source' => 'mailpoet', 'list_ids' => array( $liste ) ) );
	// faute seule (à corriger)
	$seule  = Subs::insert( array( 'email' => 'seule@orange.ft', 'first_name' => 'Seule', 'consent_source' => 'mailpoet' ) );
	// robot
	$robot  = Subs::insert( array( 'email' => 'bot@topcrush.org', 'first_name' => 'Bot', 'consent_source' => 'woocommerce' ) );
	// ajoutée à la main : jamais touchée, même fautive
	$manuel = Subs::insert( array( 'email' => 'manuel@gmai.com', 'first_name' => 'Manuel', 'consent_source' => 'admin' ) );
	// désabonnée : jamais touchée
	$desab  = Subs::insert( array( 'email' => 'desab@gmai.com', 'first_name' => 'Desab', 'consent_source' => 'mailpoet', 'status' => Subs::STATUS_UNSUBSCRIBED ) );

	$perimetre = array( $juste, $faute, $seule, $robot, $manuel, $desab );
	$simu      = Quality::scan( true, $perimetre );
	wam_nl_assert( $simu['dry_run'], 'la simulation se déclare comme telle' );
	wam_nl_assert_equals( 'dup@gmai.com', Subs::find( $faute )['email'], 'simulation : rien n’est modifié (faute)' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $robot )['status'], 'simulation : rien n’est modifié (robot)' );

	$reel = Quality::scan( false, $perimetre );
	wam_nl_assert( $reel['fixed'] >= 1, 'au moins une adresse corrigée' );
	wam_nl_assert( $reel['merged'] >= 1, 'au moins un doublon fusionné' );
	wam_nl_assert( $reel['rejected'] >= 1, 'au moins un robot écarté' );
	wam_nl_assert_equals( 'seule@orange.fr', Subs::find( $seule )['email'], 'la faute isolée est corrigée' );
	wam_nl_assert_equals( Subs::STATUS_TRASHED, Subs::find( $faute )['status'], 'le doublon fautif est mis à la corbeille' );
	wam_nl_assert( in_array( $liste, Subs::list_ids_of( $juste ), true ), 'ses listes sont reportées sur l’adresse correcte' );
	wam_nl_assert_equals( Subs::STATUS_SUBSCRIBED, Subs::find( $juste )['status'], 'l’adresse correcte reste active' );
	wam_nl_assert_equals( Subs::STATUS_TRASHED, Subs::find( $robot )['status'], 'le robot est mis à la corbeille (réversible)' );
	wam_nl_assert_equals( 'manuel@gmai.com', Subs::find( $manuel )['email'], 'une adresse saisie à la main n’est jamais modifiée' );
	wam_nl_assert_equals( Subs::STATUS_UNSUBSCRIBED, Subs::find( $desab )['status'], 'un·e désabonné·e n’est jamais touché·e' );

	echo "== Synchronisation automatique WooCommerce ==\n";
	if ( function_exists( 'wc_create_order' ) ) {
		$defauts = Settings::all();
		wam_nl_assert( ! empty( $defauts['woo_auto_sync'] ), 'activée par défaut' );

		$creer = static function ( string $email, string $prenom, string $nom ) use ( &$commandes ) {
			$c = wc_create_order();
			$c->set_billing_email( $email );
			$c->set_billing_first_name( $prenom );
			$c->set_billing_last_name( $nom );
			$c->save();
			$commandes[] = $c->get_id();
			return $c->get_id();
		};

		$o1 = $creer( 'cliente' . $domaine, 'Cliente', 'Test' );
		$r1 = WooCommerceSync::sync_order( $o1 );
		wam_nl_assert_equals( 'created', $r1['status'], 'une nouvelle cliente est ajoutée' );
		$cliente = Subs::find_by_email( 'cliente' . $domaine );
		wam_nl_assert( null !== $cliente, 'elle existe dans la base' );
		wam_nl_assert_equals( 'woocommerce', $cliente['consent_source'], 'source « woocommerce »' );
		$liste_adherentes = Lists::ensure( \WamNewsletter\Integrations\WooCommerceImporter::LIST_NAME );
		wam_nl_assert( in_array( $liste_adherentes, Subs::list_ids_of( (int) $cliente['id'] ), true ), 'rattachée à la liste Adhérent·es' );

		$r2 = WooCommerceSync::sync_order( $o1 );
		wam_nl_assert_equals( 'unchanged', $r2['status'], 'rejouée : aucun changement (idempotent)' );

		$o2 = $creer( 'bot@topcrush.org', 'Keesha', 'Owen' );
		wam_nl_assert_equals( 'rejected', WooCommerceSync::sync_order( $o2 )['status'], 'un robot est écarté' );

		$o3 = $creer( 'faute@gmai.com', 'Faute', 'Test' );
		$r3 = WooCommerceSync::sync_order( $o3 );
		wam_nl_assert( null !== Subs::find_by_email( 'faute@gmail.com' ), 'une faute de domaine est corrigée à l’entrée' );
		wam_nl_assert( null === Subs::find_by_email( 'faute@gmai.com' ), 'et la version fautive n’existe pas' );
		if ( Subs::find_by_email( 'faute@gmail.com' ) ) {
			Subs::delete( (int) Subs::find_by_email( 'faute@gmail.com' )['id'] );
		}

		$o4 = $creer( 'desabonnee' . $domaine, 'Desa', 'Bonnee' );
		Subs::insert( array( 'email' => 'desabonnee' . $domaine, 'status' => Subs::STATUS_UNSUBSCRIBED, 'consent_source' => 'mailpoet' ) );
		WooCommerceSync::sync_order( $o4 );
		wam_nl_assert_equals( Subs::STATUS_UNSUBSCRIBED, Subs::find_by_email( 'desabonnee' . $domaine )['status'], 'une désabonnée qui recommande n’est JAMAIS réabonnée' );

		Settings::update( array( 'woo_auto_sync' => false ) );
		$o5 = $creer( 'coupee' . $domaine, 'Coupee', 'Test' );
		WooCommerceSync::handle( $o5 );
		wam_nl_assert( null === Subs::find_by_email( 'coupee' . $domaine ), 'réglage désactivé : rien n’est ajouté' );
		Settings::update( array( 'woo_auto_sync' => true ) );

		wam_nl_assert_equals( 'absent', WooCommerceSync::sync_order( 99999999 )['status'], 'commande inconnue : sans erreur' );
	} else {
		echo "  (WooCommerce absent : section ignorée)\n";
	}

} finally {
	add_filter( 'wam_nl_quality_check_dns', '__return_false' );
	$nettoyer();
	global $wpdb;
	$t = Subs::table();
	foreach ( array( 'dup@gmail.com', 'dup@gmai.com', 'seule@orange.fr', 'seule@orange.ft', 'bot@topcrush.org', 'manuel@gmai.com', 'desab@gmai.com', 'bot@topcrush.org' ) as $e ) {
		$ligne = Subs::find_by_email( $e );
		if ( $ligne ) {
			Subs::delete( (int) $ligne['id'] );
		}
	}
	if ( false === $reglages_initiaux ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $reglages_initiaux );
	}
}

wam_nl_test_report();
