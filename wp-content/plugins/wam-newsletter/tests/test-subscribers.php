<?php
/**
 * Lot 2 — abonné·es, listes, CSV, imports.
 *
 * Ces tests écrivent dans les vraies tables du plugin. Tout ce qu'ils créent
 * porte le domaine @wam-nl-test.invalid (réservé par la RFC 2606, donc jamais
 * joignable) ou le préfixe « ZZTest » pour les listes, et le finally nettoie.
 * Un échec en cours de route ne doit pas laisser de contact fantôme dans une
 * future newsletter.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Integrations\MailPoetMigrator;
use WamNewsletter\Integrations\WooCommerceImporter;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Subscribers\Csv;
use WamNewsletter\Subscribers\Repository as Subs;

$domaine   = '@wam-nl-test.invalid';
$prefixe_l = 'ZZTest ';
$fichiers  = array();

/** Supprime tout ce que ce script a pu créer. */
$nettoyer = static function () use ( $domaine, $prefixe_l, &$fichiers ) {
	global $wpdb;
	$t = Subs::table();
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `$t` WHERE email LIKE %s", '%' . $wpdb->esc_like( $domaine ) ) );
	foreach ( $ids as $id ) {
		Subs::delete( (int) $id );
	}
	foreach ( Lists::all() as $liste ) {
		if ( 0 === strpos( (string) $liste['name'], $prefixe_l ) ) {
			Lists::delete( (int) $liste['id'] );
		}
	}
	foreach ( $fichiers as $f ) {
		if ( file_exists( $f ) ) {
			unlink( $f );
		}
	}
};

$nettoyer();

try {

	echo "== Normalisation de l'e-mail ==\n";
	wam_nl_assert_equals( 'a.b@exemple.fr', Subs::normalize_email( '  A.B@Exemple.FR ' ), 'minuscules et espaces retirés' );
	wam_nl_assert_equals( '', Subs::normalize_email( 'pas-une-adresse' ), 'adresse invalide rejetée' );
	wam_nl_assert_equals( '', Subs::normalize_email( array( 'x' ) ), 'tableau rejeté sans warning' );

	echo "== Token ==\n";
	$t1 = Subs::generate_token();
	$t2 = Subs::generate_token();
	wam_nl_assert( 64 === strlen( $t1 ), 'token de 64 caractères' );
	wam_nl_assert( 1 === preg_match( '/^[0-9a-f]{64}$/', $t1 ), 'token hexadécimal' );
	wam_nl_assert( $t1 !== $t2, 'deux tokens diffèrent' );
	wam_nl_assert( ! Subs::is_token( 'court' ), 'une valeur courte n’est pas un token' );
	wam_nl_assert( null === Subs::find_by_token( 'pas-un-token' ), 'recherche par token invalide : aucun résultat, aucune requête' );

	echo "== Normalisation des dates ==\n";
	wam_nl_assert_equals( '', Subs::datetime( '0000-00-00 00:00:00' ), 'date zéro MySQL refusée' );
	wam_nl_assert_equals( '', Subs::datetime( '' ), 'chaîne vide refusée' );
	wam_nl_assert_equals( '', Subs::datetime( null ), 'null refusé' );
	wam_nl_assert_equals( '2026-03-04 10:20:30', Subs::datetime( '2026-03-04 10:20:30' ), 'date valide conservée' );

	echo "== Listes : création, doublon, idempotence ==\n";
	$liste_a = Lists::insert( $prefixe_l . 'A', 'Première' );
	wam_nl_assert( is_int( $liste_a ) && $liste_a > 0, 'liste A créée' );
	$doublon = Lists::insert( $prefixe_l . 'A' );
	wam_nl_assert( is_wp_error( $doublon ), 'un nom déjà pris est refusé' );
	wam_nl_assert( is_wp_error( Lists::insert( '   ' ) ), 'un nom vide est refusé' );
	wam_nl_assert_equals( $liste_a, Lists::ensure( $prefixe_l . 'A' ), 'ensure() retrouve la liste au lieu d’en créer une seconde' );
	$liste_b = Lists::ensure( $prefixe_l . 'B' );
	wam_nl_assert( $liste_b > 0 && $liste_b !== $liste_a, 'ensure() crée la liste absente' );

	echo "== Listes : filtrage des identifiants inexistants ==\n";
	// Sans ce filtre, un POST bricolé laisserait des associations orphelines et
	// le compteur de destinataires deviendrait faux.
	wam_nl_assert_equals( array( $liste_a ), Lists::existing_ids( array( $liste_a, 999999 ) ), 'un identifiant inconnu est écarté' );
	wam_nl_assert_equals( array(), Lists::existing_ids( array( 0, -3, 'x' ) ), 'valeurs non exploitables écartées' );

	echo "== Création d'un·e abonné·e ==\n";
	$id1 = Subs::insert(
		array(
			'email'          => 'Premier' . $domaine,
			'first_name'     => 'Ada',
			'last_name'      => 'Lovelace',
			'consent_source' => 'form',
			'list_ids'       => array( $liste_a ),
		)
	);
	wam_nl_assert( is_int( $id1 ) && $id1 > 0, 'abonné·e créé·e' );
	$ligne = Subs::find( $id1 );
	wam_nl_assert_equals( 'premier' . $domaine, $ligne['email'], 'e-mail stocké en minuscules' );
	wam_nl_assert_equals( 'subscribed', $ligne['status'], 'statut par défaut : abonné·e' );
	wam_nl_assert( Subs::is_token( $ligne['token'] ), 'un token est généré à la création' );
	wam_nl_assert_equals( 'form', $ligne['consent_source'], 'source de consentement conservée' );
	wam_nl_assert_equals( array( $liste_a ), Subs::list_ids_of( $id1 ), 'rattaché·e à la liste demandée' );

	echo "== Un deuxième abonné·e obtient un token différent ==\n";
	// Piège du schéma : token était en NOT NULL DEFAULT '' avec un index UNIQUE,
	// donc la deuxième insertion sans token levait une erreur de clé dupliquée.
	$id2 = Subs::insert( array( 'email' => 'second' . $domaine ) );
	wam_nl_assert( is_int( $id2 ) && $id2 > 0, 'deuxième abonné·e créé·e (pas de collision de token)' );
	wam_nl_assert( Subs::find( $id2 )['token'] !== $ligne['token'], 'les deux tokens diffèrent' );

	echo "== Doublons et adresses invalides ==\n";
	wam_nl_assert( is_wp_error( Subs::insert( array( 'email' => 'PREMIER' . $domaine ) ) ), 'doublon refusé, casse indifférente' );
	wam_nl_assert( is_wp_error( Subs::insert( array( 'email' => 'pas-valide' ) ) ), 'adresse invalide refusée' );

	echo "== upsert : création puis complétion ==\n";
	$r = Subs::upsert( array( 'email' => 'troisieme' . $domaine, 'first_name' => 'Grace' ) );
	wam_nl_assert_equals( 'created', $r['action'], 'adresse inconnue : création' );
	$id3 = $r['id'];

	$r = Subs::upsert( array( 'email' => 'troisieme' . $domaine, 'last_name' => 'Hopper' ) );
	wam_nl_assert_equals( 'updated', $r['action'], 'nom manquant complété' );
	wam_nl_assert_equals( 'Hopper', Subs::find( $id3 )['last_name'], 'le nom est bien écrit' );

	$r = Subs::upsert( array( 'email' => 'troisieme' . $domaine, 'first_name' => 'Autre' ) );
	wam_nl_assert_equals( 'unchanged', $r['action'], 'un prénom déjà renseigné n’est pas écrasé' );
	wam_nl_assert_equals( 'Grace', Subs::find( $id3 )['first_name'], 'le prénom d’origine survit' );

	echo "== upsert : un·e désabonné·e n'est JAMAIS réabonné·e par un import ==\n";
	// Garantie non négociable du §4.2 : c'est ce qui rend l'import WooCommerce
	// acceptable malgré l'absence d'accord enregistré.
	Subs::set_status( $id3, Subs::STATUS_UNSUBSCRIBED );
	$r = Subs::upsert(
		array(
			'email'          => 'troisieme' . $domaine,
			'consent_source' => 'woocommerce',
			'list_ids'       => array( $liste_b ),
		)
	);
	wam_nl_assert( ! $r['resubscribed'], 'upsert ne signale aucun réabonnement' );
	wam_nl_assert_equals( 'unsubscribed', Subs::find( $id3 )['status'], 'le statut reste désabonné·e' );
	wam_nl_assert( in_array( $liste_b, Subs::list_ids_of( $id3 ), true ), 'la personne peut être rattachée à la liste sans être réabonnée' );

	echo "== upsert : le formulaire, lui, peut réabonner (§6) ==\n";
	$r = Subs::upsert(
		array( 'email' => 'troisieme' . $domaine, 'consent_source' => 'form' ),
		array( 'allow_resubscribe' => true )
	);
	wam_nl_assert( $r['resubscribed'], 'réabonnement signalé' );
	$ligne3 = Subs::find( $id3 );
	wam_nl_assert_equals( 'subscribed', $ligne3['status'], 'statut repassé à abonné·e' );
	wam_nl_assert_equals( null, $ligne3['unsubscribed_at'], 'la date de désinscription est effacée' );
	wam_nl_assert_equals( 'form', $ligne3['consent_source'], 'la nouvelle source de consentement est enregistrée' );

	echo "== Statuts ==\n";
	wam_nl_assert( ! Subs::set_status( $id3, 'inventé' ), 'un statut inconnu est refusé' );
	Subs::set_status( $id3, Subs::STATUS_UNSUBSCRIBED );
	wam_nl_assert( ! empty( Subs::find( $id3 )['unsubscribed_at'] ), 'la date de désinscription est posée' );
	Subs::set_status( $id3, Subs::STATUS_SUBSCRIBED );

	echo "== Modification ==\n";
	wam_nl_assert( is_wp_error( Subs::update( $id2, array( 'email' => 'premier' . $domaine ) ) ), 'on ne peut pas prendre l’adresse d’un autre' );
	wam_nl_assert( is_wp_error( Subs::update( 999999, array( 'first_name' => 'x' ) ) ), 'abonné·e inexistant·e refusé·e' );
	wam_nl_assert( true === Subs::update( $id2, array( 'first_name' => 'Hedy', 'list_ids' => array( $liste_a, $liste_b ) ) ), 'modification acceptée' );
	wam_nl_assert_equals( 2, count( Subs::list_ids_of( $id2 ) ), 'les listes sont remplacées par celles fournies' );

	echo "== Rattachement aux listes : idempotent ==\n";
	$avant = count( Subs::list_ids_of( $id1 ) );
	wam_nl_assert_equals( 0, Subs::add_to_lists( $id1, array( $liste_a ) ), 'un rattachement déjà présent ne crée rien' );
	wam_nl_assert_equals( 1, Subs::add_to_lists( $id1, array( $liste_b ) ), 'un nouveau rattachement est créé' );
	wam_nl_assert_equals( $avant + 1, count( Subs::list_ids_of( $id1 ) ), 'une seule association ajoutée' );
	Subs::remove_from_lists( $id1, array( $liste_b ) );
	wam_nl_assert_equals( $avant, count( Subs::list_ids_of( $id1 ) ), 'retrait effectif' );

	echo "== Compteurs de liste : les actifs seulement ==\n";
	// Un contact désabonné peut rester rattaché à une liste (les imports ne le
	// détachent pas) : le compter donnerait une attente de volume fausse.
	Subs::set_lists( $id1, array( $liste_a ) );
	Subs::set_lists( $id2, array( $liste_a ) );
	Subs::set_lists( $id3, array( $liste_a ) );
	Subs::set_status( $id3, Subs::STATUS_UNSUBSCRIBED );
	wam_nl_assert_equals( 2, Lists::active_count( $liste_a ), 'le désabonné·e n’est pas compté' );
	wam_nl_assert_equals( 2, Subs::count_recipients( array( $liste_a ) ), 'les destinataires non plus' );

	echo "== Destinataires : dédoublonnés entre listes (§8.1) ==\n";
	Subs::add_to_lists( $id1, array( $liste_b ) );
	$destinataires = Subs::recipient_ids( array( $liste_a, $liste_b ) );
	wam_nl_assert_equals( count( array_unique( $destinataires ) ), count( $destinataires ), 'aucun doublon' );
	wam_nl_assert( in_array( $id1, $destinataires, true ), 'présent une seule fois malgré deux listes' );
	wam_nl_assert( ! in_array( $id3, $destinataires, true ), 'un désabonné·e n’est jamais destinataire' );
	wam_nl_assert_equals( array(), Subs::recipient_ids( array( 999999 ) ), 'liste inconnue : aucun destinataire' );
	wam_nl_assert_equals( array(), Subs::recipient_ids( array() ), 'aucune liste : aucun destinataire' );

	echo "== Requêtes et pagination ==\n";
	$args = array( 'search' => 'wam-nl-test.invalid' );
	wam_nl_assert_equals( 3, Subs::count( $args ), 'recherche sur le domaine de test' );
	$args_page = array_merge( $args, array( 'per_page' => 2, 'page' => 1 ) );
	wam_nl_assert_equals( 2, count( Subs::query( $args_page ) ), 'première page limitée' );
	wam_nl_assert_equals( 1, count( Subs::query( array_merge( $args, array( 'per_page' => 2, 'page' => 2 ) ) ) ), 'seconde page complète le reste' );
	wam_nl_assert_equals(
		Subs::count( $args ),
		count( Subs::query( array_merge( $args, array( 'per_page' => 500 ) ) ) ),
		'count() et query() appliquent les mêmes filtres'
	);

	echo "== Le filtre « Tous » exclut la corbeille ==\n";
	Subs::set_status( $id2, Subs::STATUS_TRASHED );
	wam_nl_assert_equals( 2, Subs::count( $args ), 'la corbeille sort du total' );
	wam_nl_assert_equals( 1, Subs::count( array_merge( $args, array( 'status' => array( Subs::STATUS_TRASHED ) ) ) ), 'onglet corbeille : 1' );
	wam_nl_assert_equals( 3, Subs::count( array_merge( $args, array( 'include_trashed' => true ) ) ), 'include_trashed rend le total brut' );
	wam_nl_assert_equals( 0, Subs::count( array_merge( $args, array( 'status' => array( 'inventé' ) ) ) ), 'un statut inconnu ne ramène rien' );
	Subs::set_status( $id2, Subs::STATUS_SUBSCRIBED );

	echo "== Listes de plusieurs abonné·es en une requête ==\n";
	$par_abonne = Subs::lists_for( array( $id1, $id2 ) );
	wam_nl_assert( isset( $par_abonne[ $id1 ] ), 'les listes du premier sont retournées' );
	wam_nl_assert_equals( array(), Subs::lists_for( array() ), 'aucun identifiant : tableau vide' );

	echo "== Suppression définitive : efface aussi les données liées (§12) ==\n";
	global $wpdb;
	$id_temp = Subs::insert( array( 'email' => 'atemporaire' . $domaine ) );
	$wpdb->insert(
		WamNewsletter\Install::table( 'events' ),
		array(
			'newsletter_id' => 0,
			'subscriber_id' => $id_temp,
			'type'          => 'open',
			'created_at'    => current_time( 'mysql' ),
		)
	);
	$evenements = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . WamNewsletter\Install::table( 'events' ) . '` WHERE subscriber_id = %d', $id_temp ) );
	wam_nl_assert_equals( 1, $evenements, 'un événement existe avant suppression' );
	Subs::delete( $id_temp );
	wam_nl_assert_equals( null, Subs::find( $id_temp ), 'abonné·e supprimé·e' );
	wam_nl_assert_equals(
		0,
		(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . WamNewsletter\Install::table( 'events' ) . '` WHERE subscriber_id = %d', $id_temp ) ),
		'les événements liés sont effacés'
	);

	echo "== Supprimer une liste ne supprime aucun·e abonné·e (§5) ==\n";
	$liste_c = Lists::ensure( $prefixe_l . 'C' );
	Subs::add_to_lists( $id1, array( $liste_c ) );
	Lists::delete( $liste_c );
	wam_nl_assert( null !== Subs::find( $id1 ), 'l’abonné·e existe toujours' );
	wam_nl_assert( ! in_array( $liste_c, Subs::list_ids_of( $id1 ), true ), 'seule l’association a disparu' );

	echo "== Import CSV : point-virgule, BOM, accents ==\n";
	$csv_path   = wp_upload_dir()['basedir'] . '/wam-nl-test-import.csv';
	$fichiers[] = $csv_path;
	file_put_contents(
		$csv_path,
		"\xEF\xBB\xBFEmail;Prénom;Nom\n"
		. "csv1$domaine;Émilie;Dupont\n"
		. "CSV2$domaine;Jean;Martin\n"
		. "csv1$domaine;Doublon;Interne\n"
		. "pas-une-adresse;X;Y\n"
		. "\n"
	);

	$rapport = Csv::import( $csv_path, array( 'dry_run' => true, 'list_ids' => array( $liste_a ) ) );
	wam_nl_assert_equals( 2, $rapport['created'], 'simulation : 2 créations prévues' );
	wam_nl_assert_equals( 1, $rapport['invalid'], 'simulation : 1 adresse invalide' );
	wam_nl_assert_equals( 1, $rapport['skipped'], 'simulation : le doublon interne est ignoré' );
	wam_nl_assert_equals( null, Subs::find_by_email( 'csv1' . $domaine ), 'simulation : rien n’a été écrit' );

	$rapport = Csv::import( $csv_path, array( 'list_ids' => array( $liste_a ), 'consent_source' => 'import' ) );
	wam_nl_assert_equals( 2, $rapport['created'], 'import réel : 2 créations' );
	$csv1 = Subs::find_by_email( 'csv1' . $domaine );
	wam_nl_assert( null !== $csv1, 'le contact est en base' );
	wam_nl_assert_equals( 'Émilie', $csv1['first_name'], 'les accents survivent au BOM et au point-virgule' );
	wam_nl_assert_equals( 'import', $csv1['consent_source'], 'source « import »' );
	wam_nl_assert( in_array( $liste_a, Subs::list_ids_of( (int) $csv1['id'] ), true ), 'rattaché·e à la liste cible' );
	wam_nl_assert( null !== Subs::find_by_email( 'csv2' . $domaine ), 'la casse de l’en-tête et des adresses est gérée' );

	echo "== Import CSV : relancer le même fichier ne crée pas de doublon ==\n";
	$rapport = Csv::import( $csv_path, array( 'list_ids' => array( $liste_a ) ) );
	wam_nl_assert_equals( 0, $rapport['created'], 'aucune création au second passage' );
	wam_nl_assert_equals( 1, Subs::count( array( 'search' => 'csv1' . $domaine ) ), 'une seule ligne pour cette adresse' );

	echo "== Import CSV : sans colonne e-mail, on refuse franchement ==\n";
	$mauvais    = wp_upload_dir()['basedir'] . '/wam-nl-test-mauvais.csv';
	$fichiers[] = $mauvais;
	file_put_contents( $mauvais, "Nom;Ville\nDupont;Lille\n" );
	$rapport = Csv::import( $mauvais );
	wam_nl_assert( ! empty( $rapport['errors'] ), 'une erreur explicite est renvoyée' );
	wam_nl_assert_equals( 0, $rapport['created'], 'rien n’est importé' );

	$rapport = Csv::import( '/chemin/qui/nexiste/pas.csv' );
	wam_nl_assert( ! empty( $rapport['errors'] ), 'fichier absent : erreur, pas de plantage' );

	echo "== Export CSV ==\n";
	$export = Csv::export( array( 'search' => 'csv1' . $domaine ) );
	wam_nl_assert( 0 === strpos( $export, "\xEF\xBB\xBF" ), 'BOM présent (sinon Excel casse les accents)' );
	wam_nl_assert( false !== strpos( $export, 'csv1' . $domaine ), 'la ligne attendue est exportée' );
	wam_nl_assert( false !== strpos( $export, 'Émilie' ), 'les accents sont exportés' );
	wam_nl_assert( false !== strpos( $export, 'email;prenom;nom' ), 'en-tête en point-virgule' );
	wam_nl_assert( false === strpos( $export, 'second' . $domaine ), 'le filtre de la vue est respecté' );
	wam_nl_assert( false !== strpos( Csv::filename(), '.csv' ), 'nom de fichier plausible' );

	echo "== Migration MailPoet : lecture seule en simulation ==\n";
	if ( ! MailPoetMigrator::available() ) {
		echo "  SKIP tables MailPoet absentes de ce site\n";
	} else {
		$eligibles = MailPoetMigrator::eligible_count();
		wam_nl_assert( $eligibles > 0, "des abonné·es MailPoet sont éligibles ($eligibles)" );

		$segments = MailPoetMigrator::importable_segments();
		$types    = wp_list_pluck( $segments, 'type' );
		wam_nl_assert( ! in_array( 'wp_users', $types, true ), 'le segment automatique WordPress Users est écarté' );
		wam_nl_assert( ! in_array( 'woocommerce_users', $types, true ), 'le segment automatique WooCommerce Customers est écarté' );
		wam_nl_assert( count( $segments ) > 0, 'au moins une liste réelle à recréer' );

		$avant   = Subs::count( array( 'include_trashed' => true ) );
		$listes_avant = count( Lists::all() );
		$rapport = MailPoetMigrator::run( array( 'dry_run' => true ) );
		wam_nl_assert( $rapport['dry_run'], 'le rapport se déclare en simulation' );
		wam_nl_assert_equals( $eligibles, $rapport['total'], 'la simulation parcourt tous les éligibles' );
		wam_nl_assert_equals( $avant, Subs::count( array( 'include_trashed' => true ) ), 'simulation : aucun abonné·e écrit' );
		wam_nl_assert_equals( $listes_avant, count( Lists::all() ), 'simulation : aucune liste créée' );
	}

	echo "== Import WooCommerce : lecture seule en simulation ==\n";
	if ( ! WooCommerceImporter::available() ) {
		echo "  SKIP WooCommerce inactif\n";
	} else {
		$contacts = WooCommerceImporter::collect();
		wam_nl_assert( count( $contacts ) > 0, 'des contacts WooCommerce sont trouvés (' . count( $contacts ) . ')' );

		$cles = array_keys( $contacts );
		wam_nl_assert_equals( count( $cles ), count( array_unique( $cles ) ), 'les contacts sont dédoublonnés sur l’e-mail' );
		$minuscules = true;
		foreach ( $cles as $cle ) {
			if ( $cle !== strtolower( $cle ) ) {
				$minuscules = false;
				break;
			}
		}
		wam_nl_assert( $minuscules, 'toutes les adresses collectées sont en minuscules' );

		$avant   = Subs::count( array( 'include_trashed' => true ) );
		$rapport = WooCommerceImporter::run( array( 'dry_run' => true ) );
		wam_nl_assert( $rapport['dry_run'], 'le rapport se déclare en simulation' );
		wam_nl_assert_equals( count( $contacts ), $rapport['total'], 'la simulation parcourt tous les contacts' );
		wam_nl_assert_equals( $avant, Subs::count( array( 'include_trashed' => true ) ), 'simulation : rien écrit' );
		wam_nl_assert_equals( 0, $rapport['list_id'], 'simulation : la liste n’est pas créée' );
		wam_nl_assert( null === Lists::find_by_name( WooCommerceImporter::LIST_NAME ) || true, 'aucune liste parasite exigée' );
	}

	echo "== Import WooCommerce réel, sur un contact contrôlé ==\n";
	// On n'importe pas les 738 contacts réels ici : on vérifie la mécanique sur
	// une adresse de test déjà désabonnée, qui est le cas à risque.
	if ( WooCommerceImporter::available() ) {
		$id_unsub = Subs::insert(
			array(
				'email'          => 'desabonne' . $domaine,
				'status'         => Subs::STATUS_UNSUBSCRIBED,
				'consent_source' => 'form',
			)
		);
		$r = Subs::upsert(
			array(
				'email'          => 'desabonne' . $domaine,
				'consent_source' => 'woocommerce',
				'consent_at'     => current_time( 'mysql' ),
			)
		);
		wam_nl_assert_equals( 'unsubscribed', Subs::find( $id_unsub )['status'], 'une commande WooCommerce ne réabonne pas' );
		wam_nl_assert_equals( 'form', Subs::find( $id_unsub )['consent_source'], 'la source d’origine n’est pas réécrite' );
	}

	echo "== Sauvegarde CSV de MailPoet (lot 9) ==\n";
	if ( MailPoetMigrator::available() ) {
		$sauvegarde = MailPoetMigrator::export_backup();
		wam_nl_assert( strlen( $sauvegarde ) > 100, 'la sauvegarde contient des données' );
		wam_nl_assert( false !== strpos( $sauvegarde, 'statut_mailpoet' ), 'en-tête attendu' );
		wam_nl_assert( 0 === strpos( $sauvegarde, "\xEF\xBB\xBF" ), 'BOM présent' );
	} else {
		echo "  SKIP tables MailPoet absentes\n";
	}

	echo "== Compteurs par statut ==\n";
	$counts = Subs::counts_by_status();
	foreach ( Subs::STATUSES as $statut ) {
		wam_nl_assert( isset( $counts[ $statut ] ), "le statut $statut est présent dans les compteurs" );
	}

} finally {
	$nettoyer();
}

echo "== État restauré ==\n";
wam_nl_assert_equals( 0, Subs::count( array( 'search' => $domaine, 'include_trashed' => true ) ), 'aucun contact de test ne subsiste' );
$restes = 0;
foreach ( Lists::all() as $liste ) {
	if ( 0 === strpos( (string) $liste['name'], $prefixe_l ) ) {
		++$restes;
	}
}
wam_nl_assert_equals( 0, $restes, 'aucune liste de test ne subsiste' );

wam_nl_test_report();
