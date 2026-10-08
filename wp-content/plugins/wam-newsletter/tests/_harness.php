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
