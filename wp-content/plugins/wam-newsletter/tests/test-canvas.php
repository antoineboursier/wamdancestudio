<?php
/**
 * Le canevas de l'éditeur reflète les règles de l'e-mail : mêmes valeurs,
 * lues dans Render\Spacing. Vérifié en vrai navigateur le 09/10/2026 (écarts
 * identiques au pixel près, ordinateur et mobile) ; ce test garde le lien.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Editor\EmailTheme;
use WamNewsletter\Render\Brand;
use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Render\Html;
use WamNewsletter\Render\Spacing;

$css   = EmailTheme::canvas_css();
$g     = Brand::GUTTER;
$email = EmailRenderer::document( '' );

echo "== Une seule source de règles ==\n";
wam_nl_assert( false !== strpos( Html::row( 'x' ), 'padding:' . Spacing::ROW_Y . 'px ' . $g . 'px' ), 'e-mail : rangée lue dans Spacing' );
wam_nl_assert( false !== strpos( $css, 'padding: ' . Spacing::ROW_Y . 'px ' . $g . 'px;' ), 'éditeur : même rangée' );
wam_nl_assert( false !== strpos( $css, 'padding: ' . Spacing::IMAGE_BEFORE . 'px ' . $g . 'px ' . Spacing::IMAGE_AFTER . 'px;' ), 'éditeur : air autour des images' );
wam_nl_assert( false !== strpos( $css, 'margin-top: ' . Spacing::BOX_ROW_Y . 'px !important;' ), 'éditeur : air autour des boîtes' );
wam_nl_assert( false !== strpos( $css, 'column-gap: ' . Spacing::COLUMN_GAP . 'px;' ) && false !== strpos( $css, 'row-gap: 0;' ), 'éditeur : écart entre colonnes, aucun une fois empilées' );
wam_nl_assert( false !== strpos( $css, 'box-sizing: border-box;' ), 'éditeur : la gouttière compte dans la largeur' );
wam_nl_assert( false !== strpos( $css, 'font-size: ' . Spacing::HEADING_SIZES[2] . 'px;' ), 'éditeur : taille des titres' );

echo "== Règles mobiles identiques ==\n";
$mobile = Spacing::mobile_css();
wam_nl_assert( false !== strpos( $email, $mobile ), 'e-mail : le bloc mobile vient de Spacing' );
wam_nl_assert( false !== strpos( $css, $mobile ), 'éditeur : le même bloc mobile, pour l’aperçu « Mobile »' );
wam_nl_assert( false !== strpos( $mobile, 'max-width: ' . Spacing::MOBILE_MAX . 'px' ), 'seuil mobile commun' );
wam_nl_assert( false === strpos( $mobile, "'" ), 'pas d’apostrophe droite qui casserait la chaîne' );

wam_nl_test_report();
