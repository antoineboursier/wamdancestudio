<?php
/**
 * Bloc Contenus WAM : le type « événements » (CPT `evenements`).
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Integrations\ContentMap;
use WamNewsletter\Render\Blocks\Posts;

$ids = array();

try {
	wam_nl_assert( post_type_exists( 'evenements' ), 'le CPT « evenements » existe' );
	wam_nl_assert( in_array( 'evenements', ContentMap::post_types(), true ), 'proposé dans le bloc Contenus' );

	$cree = static function ( string $titre, string $date, string $debut, string $fin ) use ( &$ids ): int {
		$id = wp_insert_post( array( 'post_type' => 'evenements', 'post_status' => 'publish', 'post_title' => $titre ) );
		update_post_meta( $id, 'date_event', $date );
		update_post_meta( $id, 'horaire_debut_event', $debut );
		update_post_meta( $id, 'horaire_fin_event', $fin );
		update_post_meta( $id, 'sous_titre', 'Sous-titre ' . $titre );
		$ids[] = $id;
		return $id;
	};

	$futur = $cree( 'ZZTest événement futur', gmdate( 'Ymd', time() + 30 * DAY_IN_SECONDS ), '18:00:00', '21:30:00' );
	$passe = $cree( 'ZZTest événement passé', gmdate( 'Ymd', time() - 30 * DAY_IN_SECONDS ), '10:00:00', '12:00:00' );

	wam_nl_assert_equals( 'date_event', ContentMap::field( 'evenements', 'date' ), 'champ date détecté' );
	wam_nl_assert_equals( 'sous_titre', ContentMap::field( 'evenements', 'subtitle' ), 'sous-titre détecté' );
	wam_nl_assert( ContentMap::supports_upcoming( 'evenements' ), 'tri « à venir » possible' );

	$venir = Posts::query( array( 'postType' => 'evenements', 'mode' => 'auto', 'order' => 'upcoming', 'count' => 12 ) );
	wam_nl_assert( in_array( $futur, $venir, true ), 'à venir : l’événement futur est là' );
	wam_nl_assert( ! in_array( $passe, $venir, true ), 'à venir : l’événement passé est exclu' );

	$when = Posts::formatted_when( 'evenements', $futur );
	wam_nl_assert( false !== strpos( $when, '· 18h-21h30' ), 'horaire repris, secondes ignorées : ' . $when );

	wam_nl_assert_equals( 'Voir l’événement', Posts::default_button_text( 'evenements' ), 'libellé du bouton' );

	$rendu = Posts::render( array( 'postType' => 'evenements', 'mode' => 'manual', 'postIds' => array( $futur ), 'showDate' => true ) );
	wam_nl_assert( false !== strpos( $rendu, 'ZZTest événement futur' ), 'titre rendu' );
	wam_nl_assert( false !== strpos( $rendu, 'Sous-titre ZZTest' ), 'sous-titre rendu' );
	wam_nl_assert( false !== strpos( $rendu, 'Voir l’événement' ), 'bouton rendu' );

	// Stages inchangés : l'horaire garde ses métas d'origine.
	wam_nl_assert_equals( 'Voir le stage', Posts::default_button_text( 'stages' ), 'stages inchangés' );
} finally {
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

wam_nl_test_report();
