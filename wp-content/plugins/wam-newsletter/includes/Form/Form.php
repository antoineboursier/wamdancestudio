<?php
namespace WamNewsletter\Form;

use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Sending\Mailer;
use WamNewsletter\Settings\Settings;
use WamNewsletter\Subscribers\Repository as Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire d'inscription unique (§6), en code court et en bloc.
 *
 * Le markup réutilise le système de formulaire DÉJÀ présent dans le thème
 * (`.wam-contact-form-wrapper`, `.wam-form-group`, `.wam-form-response`,
 * `.wam-hp-field`, `.btn-primary`), chargé sur toutes les pages par
 * `assets/css/forms.css`. Conséquence voulue : le formulaire ressemble au
 * formulaire de contact du site sans qu'une seule règle CSS soit ajoutée côté
 * public, ce qu'impose le §6 (« hérite du thème, pas de CSS propre en front »).
 *
 * Simple opt-in, décision actée au §14.1 : la case cochée vaut accord, et le
 * consentement est enregistré avec sa date et sa source.
 */
class Form {

	const SHORTCODE = 'wam_newsletter_form';

	const BLOCK = 'wam-nl/form';

	const ACTION = 'wam_nl_subscribe';

	const NONCE = 'wam_nl_form';

	/** Fenêtre et plafond de la limitation par IP (§6). */
	const RATE_LIMIT_MAX    = 5;
	const RATE_LIMIT_WINDOW = 600;

	public static function register_hooks(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_action( 'init', array( self::class, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );

		// nopriv compris : le formulaire s'adresse d'abord aux visiteurs.
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( self::class, 'handle' ) );
	}

	public static function register_assets(): void {
		$style = 'assets/front/wam-nl-form.css';
		if ( file_exists( WAM_NL_DIR . $style ) ) {
			wp_register_style(
				'wam-nl-form',
				WAM_NL_URL . $style,
				array(),
				(string) filemtime( WAM_NL_DIR . $style )
			);
		}

		$rel = 'assets/front/wam-nl-form.js';
		if ( ! file_exists( WAM_NL_DIR . $rel ) ) {
			return;
		}
		wp_register_script(
			'wam-nl-form',
			WAM_NL_URL . $rel,
			array(),
			(string) filemtime( WAM_NL_DIR . $rel ),
			true
		);
		wp_localize_script(
			'wam-nl-form',
			'wamNlForm',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::ACTION,
			)
		);
	}

	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		// Même garde que pour les blocs de l'éditeur : réenregistrer un bloc
		// remplit le debug.log d'avis « déjà enregistré ».
		if ( \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			return;
		}

		$rel = 'blocks/form/index.js';
		if ( file_exists( WAM_NL_DIR . $rel ) ) {
			wp_register_script(
				'wam-nl-block-form',
				WAM_NL_URL . $rel,
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
				(string) filemtime( WAM_NL_DIR . $rel ),
				true
			);
		}

		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 2,
				'title'           => __( 'Inscription newsletter', 'wam-newsletter' ),
				'category'        => 'widgets',
				'icon'            => 'email-alt',
				'description'     => __( 'Formulaire d’inscription à la newsletter WAM.', 'wam-newsletter' ),
				'editor_script'   => 'wam-nl-block-form',
				'render_callback' => array( self::class, 'render_block' ),
				'attributes'      => array(
					'titre' => array(
						'type'    => 'string',
						'default' => '',
					),
					'texte' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array(
					'html'      => false,
					'multiple'  => false,
					'reusable'  => false,
					'className' => true,
				),
			)
		);
	}

	/** @param array|string $atts */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'titre' => '',
				'texte' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);
		return self::render( $atts );
	}

	/** @param array $attributes */
	public static function render_block( $attributes ): string {
		return self::render(
			array(
				'titre' => (string) ( $attributes['titre'] ?? '' ),
				'texte' => (string) ( $attributes['texte'] ?? '' ),
			)
		);
	}

	/**
	 * Le formulaire lui-même.
	 *
	 * Chaque instance porte un identifiant unique : la page des stages affiche
	 * déjà un encart newsletter, rien n'interdit deux formulaires sur une même
	 * page, et des `id` dupliqués casseraient les `label for` comme l'`aria-live`.
	 */
	public static function render( array $atts = array() ): string {
		static $instance = 0;
		++$instance;

		$uid       = 'wam-nl-form-' . $instance;
		$consent   = (string) Settings::get( 'form_consent_text' );
		$titre     = trim( (string) ( $atts['titre'] ?? '' ) );
		$texte     = trim( (string) ( $atts['texte'] ?? '' ) );

		wp_enqueue_style( 'wam-nl-form' );
		wp_enqueue_script( 'wam-nl-form' );

		ob_start();
		?>
		<div class="wam-contact-form-wrapper wam-nl-form-wrapper">
			<?php if ( '' !== $titre ) : ?>
				<h2 class="wam-nl-form__titre"><?php echo esc_html( $titre ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== $texte ) : ?>
				<p class="wam-nl-form__texte"><?php echo esc_html( $texte ); ?></p>
			<?php endif; ?>

			<p class="wam-contact-form-notice">
				<?php
				printf(
					/* translators: %s astérisque signalant un champ obligatoire */
					esc_html__( 'Les champs marqués d’un %s sont obligatoires.', 'wam-newsletter' ),
					'<span class="required">*</span>'
				);
				?>
			</p>

			<form class="wam-contact-form wam-nl-form" id="<?php echo esc_attr( $uid ); ?>" method="post"
				action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" novalidate>

				<?php wp_nonce_field( self::NONCE, 'wam_nl_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">

				<div class="wam-form-row form-row-2">
					<div class="wam-form-group">
						<label for="<?php echo esc_attr( $uid ); ?>-prenom">
							<?php esc_html_e( 'Prénom', 'wam-newsletter' ); ?> <span class="required">*</span>
						</label>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-prenom" name="prenom"
							required aria-required="true" autocomplete="given-name">
					</div>
					<div class="wam-form-group">
						<label for="<?php echo esc_attr( $uid ); ?>-nom">
							<?php esc_html_e( 'Nom', 'wam-newsletter' ); ?>
						</label>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-nom" name="nom" autocomplete="family-name">
					</div>
				</div>

				<div class="wam-form-group">
					<label for="<?php echo esc_attr( $uid ); ?>-email">
						<?php esc_html_e( 'E-mail', 'wam-newsletter' ); ?> <span class="required">*</span>
					</label>
					<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="email"
						required aria-required="true" autocomplete="email" placeholder="votre@email.fr">
				</div>

				<div class="wam-form-group wam-nl-form__consentement">
					<label for="<?php echo esc_attr( $uid ); ?>-consentement">
						<input type="checkbox" id="<?php echo esc_attr( $uid ); ?>-consentement"
							name="consentement" value="1" required aria-required="true">
						<span><?php echo wp_kses_post( $consent ); ?> <span class="required">*</span></span>
					</label>
				</div>

				<div class="wam-hp-field" aria-hidden="true">
					<label for="<?php echo esc_attr( $uid ); ?>-hp">
						<?php esc_html_e( 'Ne pas remplir ce champ si vous êtes humain', 'wam-newsletter' ); ?>
					</label>
					<input type="text" name="wam_nl_hp" id="<?php echo esc_attr( $uid ); ?>-hp" tabindex="-1" autocomplete="off">
				</div>

				<div class="wam-form-submit">
					<button type="submit" class="btn btn-primary">
						<span class="btn__text"><?php esc_html_e( 'Je m’inscris', 'wam-newsletter' ); ?></span>
					</button>
				</div>

				<div class="wam-form-response" id="<?php echo esc_attr( $uid ); ?>-reponse" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Clé de limitation par IP.
	 *
	 * L'adresse est hachée avec les sels du site : on obtient un compteur par
	 * visiteur sans jamais écrire d'adresse IP en base, ce qui évite de créer une
	 * donnée personnelle de plus pour une simple protection anti-spam.
	 */
	private static function rate_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'inconnue';
		return 'wam_nl_rl_' . hash( 'sha256', wp_salt( 'auth' ) . $ip );
	}

	/** @return bool true si la limite est atteinte. */
	public static function rate_limited(): bool {
		$cle   = self::rate_key();
		$essais = (int) get_transient( $cle );
		return $essais >= self::RATE_LIMIT_MAX;
	}

	private static function rate_hit(): void {
		$cle    = self::rate_key();
		$essais = (int) get_transient( $cle );
		set_transient( $cle, $essais + 1, self::RATE_LIMIT_WINDOW );
	}

	private static function fail( string $message, int $code = 400 ): void {
		wp_send_json_error( array( 'message' => $message ), $code );
	}

	/**
	 * Traitement de l'inscription.
	 *
	 * Ordre volontaire des contrôles : honeypot d'abord (coût nul, écarte
	 * l'essentiel des robots), puis limitation de fréquence, puis nonce, puis
	 * validation métier. Inutile de consulter la base pour une soumission déjà
	 * disqualifiée.
	 */
	public static function handle(): void {
		// Honeypot : un champ masqué que seul un robot remplit. On renvoie un
		// succès plausible plutôt qu'une erreur, pour ne pas lui indiquer le piège.
		$piege = isset( $_POST['wam_nl_hp'] ) ? trim( (string) wp_unslash( $_POST['wam_nl_hp'] ) ) : '';
		if ( '' !== $piege ) {
			wp_send_json_success( array( 'message' => __( 'Merci, votre inscription est enregistrée.', 'wam-newsletter' ) ) );
		}

		if ( self::rate_limited() ) {
			self::fail( __( 'Trop de tentatives. Merci de réessayer dans quelques minutes.', 'wam-newsletter' ), 429 );
		}
		self::rate_hit();

		$nonce = isset( $_POST['wam_nl_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wam_nl_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::fail( __( 'Votre session a expiré. Rechargez la page et réessayez.', 'wam-newsletter' ), 403 );
		}

		if ( empty( $_POST['consentement'] ) ) {
			self::fail( __( 'Merci de cocher la case de consentement pour vous inscrire.', 'wam-newsletter' ) );
		}

		$email = Subscribers::normalize_email( isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '' );
		if ( '' === $email ) {
			self::fail( __( 'Cette adresse e-mail ne semble pas valide.', 'wam-newsletter' ) );
		}

		$prenom = isset( $_POST['prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['prenom'] ) ) : '';
		if ( '' === trim( $prenom ) ) {
			self::fail( __( 'Merci d’indiquer votre prénom.', 'wam-newsletter' ) );
		}
		$nom = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';

		$resultat = self::subscribe( $email, $prenom, $nom );

		if ( is_wp_error( $resultat ) ) {
			self::fail( $resultat->get_error_message() );
		}

		wp_send_json_success( array( 'message' => $resultat['message'] ) );
	}

	/**
	 * Inscription proprement dite. Séparée de handle() pour être testable sans
	 * simuler une requête HTTP.
	 *
	 * @return array{message:string,id:int,deja:bool}|\WP_Error
	 */
	public static function subscribe( string $email, string $prenom = '', string $nom = '' ) {
		$email = Subscribers::normalize_email( $email );
		if ( '' === $email ) {
			return new \WP_Error( 'wam_nl_email', __( 'Cette adresse e-mail ne semble pas valide.', 'wam-newsletter' ) );
		}

		$liste    = (int) Settings::get( 'form_list_id' );
		$existant = Subscribers::find_by_email( $email );
		$etait_actif = $existant && Subscribers::STATUS_SUBSCRIBED === $existant['status'];

		$resultat = Subscribers::upsert(
			array(
				'email'          => $email,
				'first_name'     => $prenom,
				'last_name'      => $nom,
				'consent_source' => 'form',
				'consent_at'     => current_time( 'mysql' ),
				'list_ids'       => $liste ? array( $liste ) : array(),
			),
			// Seul chemin du plugin autorisé à réabonner : c'est la personne
			// elle-même qui le demande, en cochant la case de consentement (§6).
			array( 'allow_resubscribe' => true )
		);

		if ( is_wp_error( $resultat ) ) {
			return $resultat;
		}

		// Une personne mise à la corbeille ou marquée en rebond qui s'inscrit à
		// nouveau redevient abonnée : sa demande est explicite et datée.
		$ligne = Subscribers::find( $resultat['id'] );
		if ( $ligne && in_array( $ligne['status'], array( Subscribers::STATUS_TRASHED, Subscribers::STATUS_BOUNCED ), true ) ) {
			Subscribers::set_status( $resultat['id'], Subscribers::STATUS_SUBSCRIBED );
		}

		// E-mail de bienvenue : seulement pour une vraie nouvelle inscription,
		// pour ne pas renvoyer un message de bienvenue à quelqu'un déjà inscrit
		// qui resoumet le formulaire.
		if ( ! $etait_actif ) {
			self::send_welcome( $email, $prenom );
		}

		return array(
			'message' => $etait_actif
				? __( 'Vous êtes déjà inscrit·e : rien ne change, à bientôt !', 'wam-newsletter' )
				: __( 'Merci, votre inscription est bien enregistrée.', 'wam-newsletter' ),
			'id'      => (int) $resultat['id'],
			'deja'    => $etait_actif,
		);
	}

	/** E-mail de bienvenue, désactivé par défaut (§6). */
	public static function send_welcome( string $email, string $prenom = '' ): bool {
		if ( ! Settings::get( 'welcome_email_enabled' ) ) {
			return false;
		}

		$sujet = (string) Settings::get( 'welcome_email_subject' );
		$corps = (string) Settings::get( 'welcome_email_body' );
		if ( '' === trim( $sujet ) || '' === trim( $corps ) ) {
			return false;
		}

		$corps = strtr(
			$corps,
			array(
				'{prenom}' => $prenom,
				'{email}'  => $email,
			)
		);

		$entetes = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply   = (string) Settings::get( 'reply_to' );
		if ( is_email( $reply ) ) {
			$entetes[] = 'Reply-To: ' . $reply;
		}

		return (bool) Mailer::with_sender(
			static function () use ( $email, $sujet, $corps, $entetes ) {
				return wp_mail( $email, $sujet, wpautop( $corps ), $entetes );
			}
		);
	}

	/** Liste cible du formulaire, pour l'affichage des réglages et les tests. */
	public static function target_list(): ?array {
		$id = (int) Settings::get( 'form_list_id' );
		return $id ? Lists::find( $id ) : null;
	}
}
