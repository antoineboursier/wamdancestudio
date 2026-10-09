<?php
/**
 * Fidélité éditeur → e-mail : alignements (WordPress 7), ponctuation, blocs WAM.
 * Cas relevés le 09/10/2026 en comparant l'éditeur au message reçu dans Gmail.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\EmailTheme;
use WamNewsletter\Render\Blocks\Core;
use WamNewsletter\Render\Placeholders;

$para = static function ( array $attrs, string $html ) {
	return Core::paragraph( array( 'blockName' => 'core/paragraph', 'attrs' => $attrs, 'innerHTML' => $html, 'innerBlocks' => array() ) );
};
$titre = static function ( array $attrs, string $html ) {
	return Core::heading( array( 'blockName' => 'core/heading', 'attrs' => $attrs, 'innerHTML' => $html, 'innerBlocks' => array() ) );
};

echo "== Alignement du texte (style.typography.textAlign, WordPress 7) ==\n";
wam_nl_assert( false !== strpos( $para( array( 'style' => array( 'typography' => array( 'textAlign' => 'center' ) ) ), '<p class="has-text-align-center">Salut</p>' ), 'text-align:center' ), 'paragraphe centré par style.typography.textAlign' );
wam_nl_assert( false !== strpos( $para( array(), '<p class="has-text-align-right">Salut</p>' ), 'text-align:right' ), 'classe has-text-align-right seule : lue' );
wam_nl_assert( false !== strpos( $para( array( 'align' => 'center' ), '<p>Salut</p>' ), 'text-align:center' ), 'ancien attribut align toujours lu' );
wam_nl_assert( false !== strpos( $para( array(), '<p>Salut</p>' ), 'text-align:left' ), 'paragraphe sans alignement : à gauche' );
wam_nl_assert( false !== strpos( $titre( array( 'level' => 3, 'style' => array( 'typography' => array( 'textAlign' => 'left' ) ) ), '<h3 class="has-text-align-left">Avec Asmy</h3>' ), 'text-align:left' ), 'titre aligné à gauche par style.typography.textAlign' );
wam_nl_assert( false !== strpos( $titre( array( 'level' => 2 ), '<h2>Titre</h2>' ), 'text-align:center' ), 'titre sans alignement : centré' );
wam_nl_assert( false !== strpos( $titre( array( 'textAlign' => 'right' ), '<h2>Titre</h2>' ), 'text-align:right' ), 'attribut textAlign toujours lu' );

echo "== Ponctuation : le deux-points après une entité survit ==\n";
wam_nl_assert_equals( 'heures&nbsp;: voici', Placeholders::tidy( 'heures&nbsp;: voici' ), '« &nbsp;: » intact' );
wam_nl_assert_equals( 'R&amp;D ; suite', Placeholders::tidy( 'R&amp;D ; suite' ), '« &amp; » intact' );
wam_nl_assert_equals( 'd&#039;Ascq, Lille', Placeholders::tidy( 'd&#039;Ascq, Lille' ), 'apostrophe codée intacte' );
wam_nl_assert_equals( 'Bonjour, ça va', Placeholders::tidy( 'Bonjour ,, ça va' ), 'virgules doublées toujours nettoyées' );
wam_nl_assert_equals( 'Bonjour, ça va', Placeholders::tidy( 'Bonjour , ça va' ), 'espace avant la virgule toujours retirée' );

echo "== Blocs WAM dessinés par l'éditeur : rangée de l'e-mail ==\n";
$css = EmailTheme::canvas_css();
wam_nl_assert( false !== strpos( $css, '.wp-block-wam-nl-button' ) && false !== strpos( $css, 'padding: 30px 20px;' ), 'bouton : 30 px en haut et en bas' );
wam_nl_assert( false !== strpos( $css, '.wp-block-wam-nl-separator' ), 'séparateur : même padding que l’e-mail' );
wam_nl_assert( 1 === preg_match( '/\+ :is\([^)]+\)\s*\{\s*margin-top: 40px !important;/', $css ), 'deux boîtes à la suite : 40 px, pas 20' );
$js = (string) file_get_contents( WAM_NL_DIR . 'blocks/newsletter/index.js' );
wam_nl_assert( false === strpos( $js, "margin: '13px 0'" ), 'séparateur : plus de marge de 13 px codée dans le JS' );

wam_nl_test_report();
