<?php
/**
 * Page d'options ACF — Paramètres des cours collectifs
 *
 * Enregistre un sous-menu "Paramètres" sous le CPT "Les cours".
 * Les champs sont définis dans acf-json/group_cours_options.json
 * et récupérés via get_field('nom_champ', 'option').
 *
 * @package wamv1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'wamv1_register_cours_options_page' );

function wamv1_register_cours_options_page() {
	if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
		return;
	}

	acf_add_options_sub_page( [
		'page_title'  => 'Paramètres des cours',
		'menu_title'  => 'Paramètres',
		'parent_slug' => 'edit.php?post_type=cours',
		'capability'  => 'manage_options',
		'menu_slug'   => 'cours-parametres',
		'position'    => false,
		'autoload'    => false,
	] );
}
