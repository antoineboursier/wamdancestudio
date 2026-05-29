<?php
/**
 * Page de paramètres des cours collectifs
 *
 * Ajoute un sous-menu "Paramètres" sous le CPT "Les cours".
 * Permet de configurer l'encart tarifaire affiché sur toutes les pages
 * cours collectifs et sur la page planning.
 *
 * Option WordPress : cours_tarif_texte (HTML riche via wp_editor)
 *
 * @package wamv1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Format "Petit texte" dans TinyMCE (uniquement sur cette page) ─────────────
add_filter( 'tiny_mce_before_init', 'wamv1_cours_options_tiny_formats' );

function wamv1_cours_options_tiny_formats( $init ) {
	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'cours-parametres' ) === false ) {
		return $init;
	}

	$init['style_formats'] = wp_json_encode( [
		[ 'title' => 'Petit texte', 'inline' => 'small' ],
	] );

	// Ajoute styleselect en début de toolbar sans effacer le reste
	if ( isset( $init['toolbar1'] ) && strpos( $init['toolbar1'], 'styleselect' ) === false ) {
		$init['toolbar1'] = 'styleselect,' . $init['toolbar1'];
	}

	return $init;
}

// ── Enregistrement du sous-menu ───────────────────────────────────────────────
add_action( 'admin_menu', 'wamv1_register_cours_options_page' );

function wamv1_register_cours_options_page() {
	add_submenu_page(
		'edit.php?post_type=cours',
		'Paramètres des cours',
		'Paramètres',
		'manage_options',
		'cours-parametres',
		'wamv1_render_cours_options_page'
	);
}

// ── Sauvegarde ────────────────────────────────────────────────────────────────
add_action( 'admin_init', 'wamv1_save_cours_options' );

function wamv1_save_cours_options() {
	if (
		! isset( $_POST['wamv1_cours_options_nonce'] ) ||
		! wp_verify_nonce( $_POST['wamv1_cours_options_nonce'], 'wamv1_save_cours_options' ) ||
		! current_user_can( 'manage_options' )
	) {
		return;
	}

	if ( isset( $_POST['cours_tarif_texte'] ) ) {
		update_option(
			'cours_tarif_texte',
			wp_kses_post( wp_unslash( $_POST['cours_tarif_texte'] ) )
		);
		// Redirection pour éviter la re-soumission du formulaire
		wp_safe_redirect( add_query_arg( [ 'page' => 'cours-parametres', 'saved' => '1' ], admin_url( 'edit.php?post_type=cours' ) ) );
		exit;
	}
}

// ── Rendu de la page ──────────────────────────────────────────────────────────
function wamv1_render_cours_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved         = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
	$tarif_texte   = get_option( 'cours_tarif_texte', '' );
	?>
	<div class="wrap">
		<h1 style="margin-bottom:4px;">Cours collectifs — Paramètres</h1>
		<p style="color:#999;margin-top:0;margin-bottom:24px;">
			Ces informations s'affichent sur toutes les pages de cours collectifs et sur la page planning.
		</p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>✓ Paramètres enregistrés.</p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'wamv1_save_cours_options', 'wamv1_cours_options_nonce' ); ?>

			<div style="background:#fff;border:1px solid #dcdcdc;border-radius:8px;padding:24px 28px;max-width:900px;">

				<h2 style="font-size:15px;font-weight:700;margin:0 0 4px;color:#1d2327;">💶 Encart tarifs</h2>
				<p style="color:#999;font-size:12px;margin:0 0 16px;">
					Affiché en bas de chaque page cours collectif et au-dessus du planning.<br>
					Indiquez les prix, les conditions d'inscription, les règles de remboursement, etc.<br>
					<strong>Laisser vide</strong> pour masquer l'encart.
				</p>

				<?php
				wp_editor(
					$tarif_texte,
					'cours_tarif_texte',
					[
						'textarea_name' => 'cours_tarif_texte',
						'media_buttons' => false,
						'textarea_rows' => 12,
						'teeny'         => false,
						'quicktags'     => true,
					]
				);
				?>

			</div>

			<p style="margin-top:16px;">
				<input type="submit" class="button button-primary" value="Enregistrer">
			</p>
		</form>
	</div>
	<?php
}
