<?php
/**
 * Harnais d'assertions pour les tests du plugin.
 *
 * Usage : ddev wp eval-file wp-content/plugins/wam-newsletter/tests/test-xxx.php
 *
 * Pas de PHPUnit : le site n'a aucune infrastructure de test et le cahier des
 * charges impose KISS/YAGNI. Ces scripts tournent dans le WordPress réel, donc
 * ils testent le plugin tel qu'il s'exécutera.
 */

defined( 'ABSPATH' ) || exit;

// Les adresses de test (@wam-nl-test.invalid) n'ont pas de domaine réel : la
// vérification DNS du contrôle qualité les rejetterait. test-quality.php la
// réactive explicitement pour tester ce cas précis.
add_filter( 'wam_nl_quality_check_dns', '__return_false' );

// Les tests d'envoi ne doivent pas dépendre de l'heure à laquelle on les joue :
// sans ceci, une suite lancée après 23 h verrait l'envoi se mettre en veille.
// Seules les heures calmes lisent cette horloge ; test-quiet-resend.php la déplace.
add_filter(
	'wam_nl_now',
	static function () {
		return current_datetime()->setTime( 12, 0, 0 )->getTimestamp();
	}
);

$GLOBALS['wam_nl_test_pass'] = 0;
$GLOBALS['wam_nl_test_fail'] = 0;

function wam_nl_assert( bool $cond, string $label ): void {
	if ( $cond ) {
		$GLOBALS['wam_nl_test_pass']++;
		echo "  OK   $label\n";
		return;
	}
	$GLOBALS['wam_nl_test_fail']++;
	echo "  FAIL $label\n";
}

function wam_nl_assert_equals( $attendu, $obtenu, string $label ): void {
	$ok = ( $attendu === $obtenu );
	wam_nl_assert(
		$ok,
		$label . ( $ok ? '' : sprintf( ' (attendu %s, obtenu %s)', var_export( $attendu, true ), var_export( $obtenu, true ) ) )
	);
}

function wam_nl_test_report(): void {
	printf( "\n%d réussite(s), %d échec(s)\n", $GLOBALS['wam_nl_test_pass'], $GLOBALS['wam_nl_test_fail'] );
	if ( $GLOBALS['wam_nl_test_fail'] > 0 ) {
		exit( 1 );
	}
}
