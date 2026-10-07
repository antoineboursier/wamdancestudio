<?php
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Admin\Menu;
use WamNewsletter\Editor\NewsletterPostType as CPT;

echo "== CPT wam_newsletter ==\n";

$objet = get_post_type_object( CPT::POST_TYPE );
wam_nl_assert( null !== $objet, 'le post type est enregistré' );

if ( $objet ) {
	wam_nl_assert_equals( false, $objet->public, 'non public (une newsletter n a pas de page front)' );
	wam_nl_assert_equals( true, $objet->show_ui, 'visible dans l administration' );
	wam_nl_assert_equals( true, $objet->show_in_rest, 'exposé en REST (requis par Gutenberg)' );
	wam_nl_assert_equals( Menu::SLUG, $objet->show_in_menu, 'rangé sous le menu Newsletter' );
	wam_nl_assert( post_type_supports( CPT::POST_TYPE, 'title' ), 'supporte title' );
	wam_nl_assert( post_type_supports( CPT::POST_TYPE, 'editor' ), 'supporte editor' );
	wam_nl_assert( post_type_supports( CPT::POST_TYPE, 'revisions' ), 'supporte revisions' );
}

wam_nl_assert_equals( false, get_post_type_archive_link( CPT::POST_TYPE ), 'aucune archive publique' );

echo "== Metas enregistrées ==\n";
$metas = get_registered_meta_keys( 'post', CPT::POST_TYPE );
foreach ( array_keys( CPT::META ) as $cle ) {
	wam_nl_assert( isset( $metas[ $cle ] ), "meta $cle enregistrée" );
	if ( isset( $metas[ $cle ] ) ) {
		wam_nl_assert( ! empty( $metas[ $cle ]['show_in_rest'] ), "  meta $cle exposée en REST" );
	}
}

echo "== Aller-retour sur une newsletter ==\n";
$id = wp_insert_post(
	array(
		'post_type'   => CPT::POST_TYPE,
		'post_title'  => 'Test meta',
		'post_status' => 'draft',
	)
);
wam_nl_assert( is_int( $id ) && $id > 0, 'newsletter de test créée' );
update_post_meta( $id, '_wam_nl_subject', 'Objet de test' );
update_post_meta( $id, '_wam_nl_list_ids', array( 1, 2 ) );
wam_nl_assert_equals( 'Objet de test', get_post_meta( $id, '_wam_nl_subject', true ), '_wam_nl_subject relu' );
wam_nl_assert_equals( array( 1, 2 ), get_post_meta( $id, '_wam_nl_list_ids', true ), '_wam_nl_list_ids relu comme tableau' );
wp_delete_post( $id, true );
wam_nl_assert( null === get_post( $id ), 'newsletter de test supprimée' );

wam_nl_test_report();
