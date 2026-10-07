<?php
namespace WamNewsletter\Settings;

use WamNewsletter\Admin\Menu;
use WamNewsletter\Install;
use WamNewsletter\Sending\Mailer;

defined( 'ABSPATH' ) || exit;

/**
 * Écran de réglages à onglets.
 *
 * L'onglet « Contenus » (mapping ACF) relève du lot 5 et n'est pas créé ici.
 */
class SettingsPage {

	const PAGE_SLUG = 'wam-nl-settings';

	const TABS = array(
		'expediteur' => 'Expéditeur et SMTP',
		'debit'      => 'Débit d’envoi',
		'formulaire' => 'Formulaire',
		'suivi'      => 'Suivi',
	);

	/** Cases à cocher de chaque onglet, pour la remise à false. */
	const CHECKBOXES = array(
		'formulaire' => array( 'welcome_email_enabled' ),
		'suivi'      => array( 'track_opens', 'track_clicks', 'delete_data_on_uninstall' ),
	);

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 11 );
		add_action( 'admin_post_wam_nl_save_settings', array( self::class, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			Menu::SLUG,
			__( 'Réglages', 'wam-newsletter' ),
			__( 'Réglages', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$rel = 'assets/admin/wam-nl-admin.css';
		wp_enqueue_style(
			'wam-nl-admin',
			WAM_NL_URL . $rel,
			array(),
			(string) filemtime( WAM_NL_DIR . $rel )
		);
	}

	public static function url( string $onglet = 'expediteur' ): string {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $onglet,
			),
			admin_url( 'admin.php' )
		);
	}

	private static function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'expediteur';
		return isset( self::TABS[ $tab ] ) ? $tab : 'expediteur';
	}

	/**
	 * Réintroduit les cases à cocher absentes du POST.
	 *
	 * Le navigateur n'envoie pas une case décochée : sans cette remise à vide, un
	 * réglage activé une fois ne pourrait plus jamais être désactivé depuis l'écran.
	 * Méthode publique parce qu'elle porte la seule règle non triviale de
	 * l'enregistrement, et que handle_save() se termine par une redirection,
	 * donc n'est pas testable directement.
	 *
	 * @param array  $brut   Données POST déjà déséchappées.
	 * @param string $onglet Onglet soumis.
	 * @return array
	 */
	public static function normalize_submission( array $brut, string $onglet ): array {
		foreach ( self::CHECKBOXES[ $onglet ] ?? array() as $cle ) {
			$brut[ $cle ] = $brut[ $cle ] ?? '';
		}
		return $brut;
	}

	public static function handle_save(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}
		check_admin_referer( 'wam_nl_save_settings' );

		$onglet = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'expediteur';
		$onglet = isset( self::TABS[ $onglet ] ) ? $onglet : 'expediteur';

		$brut = ( isset( $_POST['wam_nl'] ) && is_array( $_POST['wam_nl'] ) )
			? wp_unslash( $_POST['wam_nl'] )
			: array();

		Settings::update( self::normalize_submission( $brut, $onglet ) );

		$avis = 'enregistre';

		if ( isset( $_POST['wam_nl_send_test'] ) ) {
			$destinataire = sanitize_email( wp_unslash( (string) ( $_POST['wam_nl_test_to'] ?? '' ) ) );
			$resultat     = Mailer::send_test( $destinataire );
			$avis         = $resultat['ok'] ? 'test_ok' : 'test_ko';
			set_transient( 'wam_nl_test_message', $resultat['message'], 60 );
		}

		wp_safe_redirect( add_query_arg( 'wam_nl_notice', $avis, self::url( $onglet ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}

		$onglet   = self::current_tab();
		$reglages = Settings::all();

		echo '<div class="wrap wam-nl-settings">';
		echo '<h1>' . esc_html__( 'Réglages de la newsletter', 'wam-newsletter' ) . '</h1>';

		self::render_notice();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( self::TABS as $slug => $libelle ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( $slug ) ),
				$slug === $onglet ? ' nav-tab-active' : '',
				esc_html( $libelle )
			);
		}
		echo '</nav>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wam_nl_save_settings' );
		echo '<input type="hidden" name="action" value="wam_nl_save_settings">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $onglet ) . '">';

		switch ( $onglet ) {
			case 'debit':
				self::tab_debit( $reglages );
				break;
			case 'formulaire':
				self::tab_formulaire( $reglages );
				break;
			case 'suivi':
				self::tab_suivi( $reglages );
				break;
			default:
				self::tab_expediteur( $reglages );
		}

		submit_button( __( 'Enregistrer', 'wam-newsletter' ) );
		echo '</form></div>';
	}

	private static function render_notice(): void {
		$avis = isset( $_GET['wam_nl_notice'] ) ? sanitize_key( wp_unslash( $_GET['wam_nl_notice'] ) ) : '';
		if ( '' === $avis ) {
			return;
		}

		$textes = array(
			'enregistre' => array( 'success', __( 'Réglages enregistrés.', 'wam-newsletter' ) ),
			'test_ok'    => array( 'success', __( 'E-mail de test envoyé.', 'wam-newsletter' ) ),
			'test_ko'    => array( 'error', __( 'L’e-mail de test n’a pas pu être envoyé.', 'wam-newsletter' ) ),
		);
		if ( ! isset( $textes[ $avis ] ) ) {
			return;
		}

		$detail = (string) get_transient( 'wam_nl_test_message' );
		delete_transient( 'wam_nl_test_message' );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s%s</p></div>',
			esc_attr( $textes[ $avis ][0] ),
			esc_html( $textes[ $avis ][1] ),
			'' !== $detail ? ' <code>' . esc_html( $detail ) . '</code>' : ''
		);
	}

	private static function field_text( string $cle, string $libelle, $valeur, string $type = 'text', string $aide = '' ): void {
		printf(
			'<tr><th scope="row"><label for="wam_nl_%1$s">%2$s</label></th><td>'
			. '<input type="%3$s" id="wam_nl_%1$s" name="wam_nl[%1$s]" value="%4$s" class="regular-text">%5$s</td></tr>',
			esc_attr( $cle ),
			esc_html( $libelle ),
			esc_attr( $type ),
			esc_attr( (string) $valeur ),
			'' !== $aide ? '<p class="description">' . esc_html( $aide ) . '</p>' : ''
		);
	}

	private static function field_textarea( string $cle, string $libelle, string $valeur, int $lignes = 3, string $aide = '' ): void {
		printf(
			'<tr><th scope="row"><label for="wam_nl_%1$s">%2$s</label></th><td>'
			. '<textarea id="wam_nl_%1$s" name="wam_nl[%1$s]" rows="%3$d" class="large-text">%4$s</textarea>%5$s</td></tr>',
			esc_attr( $cle ),
			esc_html( $libelle ),
			(int) $lignes,
			esc_textarea( $valeur ),
			'' !== $aide ? '<p class="description">' . esc_html( $aide ) . '</p>' : ''
		);
	}

	private static function field_checkbox( string $cle, string $libelle, bool $valeur, string $aide = '' ): void {
		printf(
			'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" id="wam_nl_%1$s" name="wam_nl[%1$s]" value="1"%3$s> %4$s</label></td></tr>',
			esc_attr( $cle ),
			esc_html( $libelle ),
			checked( $valeur, true, false ),
			esc_html( $aide )
		);
	}

	private static function tab_expediteur( array $r ): void {
		echo '<h2>' . esc_html__( 'Expéditeur', 'wam-newsletter' ) . '</h2><table class="form-table">';
		self::field_text( 'from_name', __( 'Nom affiché', 'wam-newsletter' ), $r['from_name'] );
		self::field_text( 'from_email', __( 'Adresse d’expédition', 'wam-newsletter' ), $r['from_email'], 'email' );
		self::field_text( 'reply_to', __( 'Répondre à', 'wam-newsletter' ), $r['reply_to'], 'email' );
		echo '</table>';

		echo '<h2>' . esc_html__( 'SMTP', 'wam-newsletter' ) . '</h2>';
		self::render_smtp_diagnostic();

		if ( ! Mailer::external_smtp_active() ) {
			echo '<table class="form-table">';
			self::field_text( 'smtp_host', __( 'Hôte', 'wam-newsletter' ), $r['smtp_host'] );
			self::field_text( 'smtp_port', __( 'Port', 'wam-newsletter' ), $r['smtp_port'], 'number' );
			printf(
				'<tr><th scope="row"><label for="wam_nl_smtp_secure">%s</label></th><td><select id="wam_nl_smtp_secure" name="wam_nl[smtp_secure]">',
				esc_html__( 'Chiffrement', 'wam-newsletter' )
			);
			$choix = array(
				'ssl' => 'SSL',
				'tls' => 'TLS',
				''    => __( 'Aucun', 'wam-newsletter' ),
			);
			foreach ( $choix as $v => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $r['smtp_secure'], $v, false ), esc_html( $l ) );
			}
			echo '</select></td></tr>';
			self::field_text( 'smtp_user', __( 'Utilisateur', 'wam-newsletter' ), $r['smtp_user'], 'email' );
			echo '</table>';
		}

		echo '<h2>' . esc_html__( 'Envoi de test', 'wam-newsletter' ) . '</h2><table class="form-table">';
		self::field_text(
			'test_recipients',
			__( 'Adresses de test', 'wam-newsletter' ),
			$r['test_recipients'],
			'text',
			__( 'Séparées par des virgules. Les adresses invalides sont ignorées à l’enregistrement.', 'wam-newsletter' )
		);
		printf(
			'<tr><th scope="row"><label for="wam_nl_test_to">%1$s</label></th><td>'
			. '<input type="email" id="wam_nl_test_to" name="wam_nl_test_to" class="regular-text" placeholder="%2$s"> '
			. '<button type="submit" name="wam_nl_send_test" value="1" class="button">%3$s</button>'
			. '<p class="description">%4$s</p></td></tr>',
			esc_html__( 'Envoyer un test à', 'wam-newsletter' ),
			esc_attr__( 'vide = adresses de test ci-dessus', 'wam-newsletter' ),
			esc_html__( 'Envoyer un e-mail de test', 'wam-newsletter' ),
			esc_html__( 'Enregistre les réglages puis envoie le test.', 'wam-newsletter' )
		);
		echo '</table>';
	}

	/**
	 * Dit clairement qui pilote PHPMailer et ce qui manque.
	 * Sans ça, un envoi qui échoue en production se diagnostique à l'aveugle.
	 */
	private static function render_smtp_diagnostic(): void {
		$lignes = array();

		if ( Mailer::external_smtp_active() ) {
			$lignes[] = array(
				'info',
				__( 'SMTP géré par l’extension « Les coulisses du site WAM ». Le plugin newsletter emprunte cette configuration et n’y touche pas.', 'wam-newsletter' ),
			);
		} elseif ( '' === (string) Settings::get( 'smtp_host' ) ) {
			$lignes[] = array(
				'warning',
				__( 'Aucun hôte SMTP renseigné : les e-mails partiront par la fonction mail() du serveur.', 'wam-newsletter' ),
			);
		} elseif ( '' === Settings::smtp_password() ) {
			$lignes[] = array(
				'error',
				__( 'La constante WAM_NL_SMTP_PASSWORD n’est pas définie dans wp-config.php : l’authentification SMTP est impossible, les e-mails partiront par mail().', 'wam-newsletter' ),
			);
		} else {
			$lignes[] = array( 'success', __( 'SMTP propre au plugin configuré.', 'wam-newsletter' ) );
		}

		if ( Mailer::local_guard_active() ) {
			$lignes[] = array(
				'warning',
				__( 'Environnement local : les e-mails sont interceptés par Mailpit et ne partent pas réellement. La connexion SMTP ne peut être vérifiée qu’en production.', 'wam-newsletter' ),
			);
		}

		// is_plugin_active() vit dans wp-admin : on la charge si besoin, cet écran
		// pouvant être rendu hors du contexte admin complet (tests, WP-CLI).
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
			$lignes[] = array(
				'error',
				__( 'MailPoet est actif : il remplace l’objet PHPMailer de WordPress et détourne tous les e-mails du site, y compris ceux de ce plugin. Les envois peuvent être annoncés comme réussis sans partir. À désactiver avant de se fier à un test.', 'wam-newsletter' ),
			);
		}

		foreach ( $lignes as $ligne ) {
			printf( '<div class="notice notice-%s inline"><p>%s</p></div>', esc_attr( $ligne[0] ), esc_html( $ligne[1] ) );
		}
	}

	private static function tab_debit( array $r ): void {
		echo '<p class="description">' . esc_html__(
			'Valeurs volontairement prudentes : o2switch limite le nombre d’échecs par heure. À ajuster après un test de montée en charge réel.',
			'wam-newsletter'
		) . '</p><table class="form-table">';
		self::field_text( 'batch_size', __( 'E-mails par lot', 'wam-newsletter' ), $r['batch_size'], 'number', __( 'Entre 1 et 200.', 'wam-newsletter' ) );
		self::field_text( 'batch_interval', __( 'Intervalle entre deux lots (secondes)', 'wam-newsletter' ), $r['batch_interval'], 'number', __( 'Entre 10 et 3600.', 'wam-newsletter' ) );
		self::field_text( 'fail_threshold_batch', __( 'Pause après N échecs dans un lot', 'wam-newsletter' ), $r['fail_threshold_batch'], 'number' );
		self::field_text( 'fail_threshold_hour', __( 'Pause après N échecs sur une heure', 'wam-newsletter' ), $r['fail_threshold_hour'], 'number' );
		echo '</table>';
	}

	private static function tab_formulaire( array $r ): void {
		echo '<table class="form-table">';
		self::field_text(
			'form_list_id',
			__( 'Liste par défaut (identifiant)', 'wam-newsletter' ),
			$r['form_list_id'],
			'number',
			__( 'Sélection par nom dès que les listes existent (lot 2).', 'wam-newsletter' )
		);
		self::field_textarea(
			'form_consent_text',
			__( 'Texte de consentement', 'wam-newsletter' ),
			(string) $r['form_consent_text'],
			3,
			__( 'HTML simple autorisé. Écriture inclusive.', 'wam-newsletter' )
		);
		self::field_checkbox(
			'welcome_email_enabled',
			__( 'E-mail de bienvenue', 'wam-newsletter' ),
			(bool) $r['welcome_email_enabled'],
			__( 'Envoyer un e-mail après inscription', 'wam-newsletter' )
		);
		self::field_text( 'welcome_email_subject', __( 'Objet de l’e-mail de bienvenue', 'wam-newsletter' ), $r['welcome_email_subject'] );
		self::field_textarea( 'welcome_email_body', __( 'Contenu de l’e-mail de bienvenue', 'wam-newsletter' ), (string) $r['welcome_email_body'], 6 );
		echo '</table>';
	}

	private static function tab_suivi( array $r ): void {
		echo '<table class="form-table">';
		self::field_checkbox( 'track_opens', __( 'Ouvertures', 'wam-newsletter' ), (bool) $r['track_opens'], __( 'Pixel de suivi — chiffre indicatif seulement', 'wam-newsletter' ) );
		self::field_checkbox( 'track_clicks', __( 'Clics', 'wam-newsletter' ), (bool) $r['track_clicks'], __( 'Réécriture signée des liens', 'wam-newsletter' ) );
		echo '</table><h2>' . esc_html__( 'Désinstallation', 'wam-newsletter' ) . '</h2><table class="form-table">';
		self::field_checkbox(
			'delete_data_on_uninstall',
			__( 'Tout effacer à la désinstallation', 'wam-newsletter' ),
			(bool) $r['delete_data_on_uninstall'],
			__( 'Supprime abonné·es, listes, file et événements. Irréversible.', 'wam-newsletter' )
		);
		echo '</table>';
	}
}
