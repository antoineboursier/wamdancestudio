<?php
/**
 * Extrait DB_NAME / DB_USER / DB_PASSWORD d'un wp-config.php de production
 * et les émet sous forme d'affectations shell (le fichier n'est jamais exécuté).
 *
 * Usage : php o2switch-parse-wpconfig.php /chemin/wp-config.php
 */

$source = $argv[1] ?? '';

if ( '' === $source || ! is_readable( $source ) ) {
	fwrite( STDERR, "wp-config.php illisible : {$source}\n" );
	exit( 1 );
}

$contents = file_get_contents( $source );
$map      = array(
	'DB_NAME'     => 'PROD_DB_NAME',
	'DB_USER'     => 'PROD_DB_USER',
	'DB_PASSWORD' => 'PROD_DB_PASS',
);

$output = '';

foreach ( $map as $constant => $variable ) {
	// Gère les guillemets simples ou doubles ainsi que les caractères échappés.
	$pattern = '/define\(\s*([\'"])' . $constant . '\1\s*,\s*([\'"])((?:\\\\.|(?!\2).)*)\2\s*\)/s';

	if ( ! preg_match( $pattern, $contents, $matches ) ) {
		fwrite( STDERR, "Constante {$constant} introuvable dans le wp-config.php distant.\n" );
		exit( 1 );
	}

	$value   = stripslashes( $matches[3] );
	$output .= sprintf( "%s=%s\n", $variable, escapeshellarg( $value ) );
}

echo $output;
