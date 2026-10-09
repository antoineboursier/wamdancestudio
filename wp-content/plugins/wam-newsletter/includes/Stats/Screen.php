<?php
namespace WamNewsletter\Stats;

use WamNewsletter\Editor\NewsletterList;
use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Install;
use WamNewsletter\Lists\Repository as Lists;
use WamNewsletter\Render\Placeholders;
use WamNewsletter\Admin\Notices;
use WamNewsletter\Sending\Delivery;
use WamNewsletter\Sending\Queue;
use WamNewsletter\Sending\Scheduler;
use WamNewsletter\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Statistiques d'une newsletter (§10), en tableau de bord.
 *
 * De haut en bas : les chiffres clés, le parcours envoyés → ouverts → cliqués,
 * les réactions au fil du temps, ce qui a été cliqué (avec la vignette du
 * contenu visé), puis l'e-mail lui-même avec le nombre de clics posé sur chaque
 * lien. Tout est rendu côté serveur, en SVG et en HTML : aucune bibliothèque,
 * aucun appel externe. Un seul petit script ajoute les infobulles de la courbe.
 *
 * Les ouvertures restent annoncées comme INDICATIVES (exigence du cahier des
 * charges) : Apple Mail précharge les images, d'autres clients les bloquent.
 * Les clics et les désinscriptions, eux, sont fiables.
 *
 * Couleurs : la paire de séries (bleu #2a78d6, orange #eb6834) est la palette
 * catégorielle de référence, validée au script (daltonisme, contraste) sur fond
 * clair. Les textes restent en couleurs de texte, jamais dans la couleur des
 * séries.
 */
class Screen {

	const PAGE_SLUG = 'wam-nl-stats';

	const COLOR_OPEN  = '#2a78d6';
	const COLOR_CLICK = '#eb6834';

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 12 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Page sans entrée de menu visible : on y arrive depuis la liste des
	 * newsletters. Une entrée « Statistiques » sans newsletter choisie
	 * n'afficherait rien d'utile.
	 */
	public static function add_page(): void {
		add_submenu_page(
			'',
			__( 'Statistiques de la newsletter', 'wam-newsletter' ),
			__( 'Statistiques', 'wam-newsletter' ),
			Install::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		foreach ( array( 'css' => 'assets/admin/wam-nl-stats.css', 'js' => 'assets/admin/wam-nl-stats.js' ) as $type => $rel ) {
			if ( ! file_exists( WAM_NL_DIR . $rel ) ) {
				continue;
			}
			if ( 'css' === $type ) {
				wp_enqueue_style( 'wam-nl-stats', WAM_NL_URL . $rel, array(), (string) filemtime( WAM_NL_DIR . $rel ) );
			} else {
				wp_enqueue_script( 'wam-nl-stats', WAM_NL_URL . $rel, array(), (string) filemtime( WAM_NL_DIR . $rel ), true );
			}
		}
	}

	public static function url( int $newsletter_id ): string {
		return add_query_arg(
			array(
				'page'       => self::PAGE_SLUG,
				'newsletter' => $newsletter_id,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wam-newsletter' ) );
		}

		$id   = isset( $_GET['newsletter'] ) ? (int) $_GET['newsletter'] : 0;
		$post = $id ? get_post( $id ) : null;

		echo '<div class="wrap wam-nl wam-nl-stats">';

		if ( ! $post || NewsletterPostType::POST_TYPE !== $post->post_type ) {
			echo '<h1>' . esc_html__( 'Statistiques', 'wam-newsletter' ) . '</h1>';
			echo '<p>' . esc_html__( 'Newsletter introuvable.', 'wam-newsletter' ) . '</p>';
			printf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) ),
				esc_html__( 'Retour aux newsletters', 'wam-newsletter' )
			);
			echo '</div>';
			return;
		}

		$counts        = Queue::counts( $id );
		$envoyes       = (int) $counts['sent'];
		$clics_total   = Events::count( $id, Events::TYPE_CLICK );
		$clics_uniques = Events::count_unique( $id, Events::TYPE_CLICK );
		$ouvertures    = Events::count_unique( $id, Events::TYPE_OPEN );
		$desinscrits   = Events::count( $id, Events::TYPE_UNSUBSCRIBE );
		$suivi_ouv     = (bool) Settings::get( 'track_opens' );
		$suivi_clics   = (bool) Settings::get( 'track_clicks' );

		Notices::render();
		self::render_header( $id, $post );

		// --- Chiffres clés ---
		echo '<div class="wam-nl-kpis">';
		self::tile( __( 'Envoyés', 'wam-newsletter' ), number_format_i18n( $envoyes ), sprintf( /* translators: %s total */ __( 'sur %s destinataires', 'wam-newsletter' ), number_format_i18n( (int) $counts['total'] ) ) );
		self::tile(
			__( 'Ouvertures', 'wam-newsletter' ),
			$suivi_ouv ? self::pct( $ouvertures, $envoyes ) : '-',
			$suivi_ouv ? sprintf( /* translators: %s nombre */ __( '%s personnes · indicatif', 'wam-newsletter' ), number_format_i18n( $ouvertures ) ) : __( 'suivi désactivé', 'wam-newsletter' )
		);
		self::tile(
			__( 'Clics', 'wam-newsletter' ),
			$suivi_clics ? self::pct( $clics_uniques, $envoyes ) : '-',
			$suivi_clics ? sprintf( /* translators: 1: personnes, 2: clics */ __( '%1$s personnes · %2$s clics', 'wam-newsletter' ), number_format_i18n( $clics_uniques ), number_format_i18n( $clics_total ) ) : __( 'suivi désactivé', 'wam-newsletter' )
		);
		$remise = self::delivery_counts( $id );
		if ( $remise['checked'] > 0 ) {
			self::tile(
				__( 'Remis', 'wam-newsletter' ),
				self::pct( $remise['delivered'], $envoyes ),
				sprintf( /* translators: 1: refusés, 2: en attente */ __( '%1$s refusés · %2$s en attente', 'wam-newsletter' ), number_format_i18n( $remise['failed'] ), number_format_i18n( $remise['deferred'] ) ),
				$remise['failed'] > 0
			);
		}
		self::tile( __( 'Désinscriptions', 'wam-newsletter' ), number_format_i18n( $desinscrits ), self::pct( $desinscrits, $envoyes ) . ' ' . __( 'des envoyés', 'wam-newsletter' ) );
		self::tile( __( 'Échecs', 'wam-newsletter' ), number_format_i18n( (int) $counts['failed'] ), (int) $counts['pending'] > 0 ? sprintf( /* translators: %s nombre */ __( '%s encore en attente', 'wam-newsletter' ), number_format_i18n( (int) $counts['pending'] ) ) : __( 'adresses à regarder', 'wam-newsletter' ), (int) $counts['failed'] > 0 );
		echo '</div>';

		// --- Parcours ---
		if ( $envoyes > 0 ) {
			echo '<section class="wam-nl-card"><h2>' . esc_html__( 'Le parcours', 'wam-newsletter' ) . '</h2>';
			echo '<p class="wam-nl-card__aide">' . esc_html__( 'Sur 100 e-mails envoyés, combien ont été ouverts, puis combien ont mené à un clic.', 'wam-newsletter' ) . '</p>';
			echo '<div class="wam-nl-funnel">';
			self::funnel_row( __( 'Envoyés', 'wam-newsletter' ), $envoyes, $envoyes );
			if ( $suivi_ouv ) {
				self::funnel_row( __( 'Ouverts (indicatif)', 'wam-newsletter' ), $ouvertures, $envoyes );
			}
			if ( $suivi_clics ) {
				self::funnel_row( __( 'Ont cliqué', 'wam-newsletter' ), $clics_uniques, $envoyes );
			}
			echo '</div></section>';
		}

		// --- Au fil du temps ---
		$chrono = Events::timeline( $id );
		if ( $chrono['points'] ) {
			echo '<section class="wam-nl-card"><h2>' . esc_html__( 'Au fil du temps', 'wam-newsletter' ) . '</h2>';
			echo '<p class="wam-nl-card__aide">' . esc_html( 'hour' === $chrono['step'] ? __( 'Ouvertures et clics, heure par heure.', 'wam-newsletter' ) : __( 'Ouvertures et clics, jour par jour.', 'wam-newsletter' ) ) . '</p>';
			self::render_timeline( $chrono );
			echo '</section>';
		}

		// --- Ce qui a été cliqué ---
		$html_fige = (string) get_post_meta( $id, Scheduler::META_HTML, true );
		$liens     = Events::top_links( $id, 50 );
		if ( $liens ) {
			echo '<section class="wam-nl-card"><h2>' . esc_html__( 'Ce qui a été cliqué', 'wam-newsletter' ) . '</h2>';
			self::render_links( $liens, $html_fige );
			echo '</section>';
		}

		// --- Carte des clics ---
		if ( '' !== $html_fige && $suivi_clics ) {
			echo '<section class="wam-nl-card"><h2>' . esc_html__( 'Carte des clics', 'wam-newsletter' ) . '</h2>';
			echo '<p class="wam-nl-card__aide">' . esc_html__( 'L’e-mail tel qu’il est parti, avec le nombre de clics posé sur chaque lien. En gris : les liens que personne n’a cliqués.', 'wam-newsletter' ) . '</p>';
			printf(
				'<iframe class="wam-nl-heatmap" sandbox="" title="%s" srcdoc="%s"></iframe>',
				esc_attr__( 'E-mail envoyé, avec le nombre de clics de chaque lien', 'wam-newsletter' ),
				esc_attr( self::heatmap_html( $html_fige, $liens ) )
			);
			echo '</section>';
		}

		// --- Remise réelle (suivi cPanel) ---
		self::render_delivery( $id, $remise );

		// --- Échecs ---
		$echecs = Queue::failures( $id );
		if ( $echecs ) {
			echo '<section class="wam-nl-card"><h2>' . esc_html__( 'Adresses en échec', 'wam-newsletter' ) . '</h2>';
			echo '<p class="wam-nl-card__aide">' . esc_html__( 'Un échec d’envoi ne veut pas dire que l’adresse est morte : c’est souvent une limite du serveur d’envoi. Aucune adresse n’est marquée en rebond automatiquement.', 'wam-newsletter' ) . '</p>';
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th style="width:24em">' . esc_html__( 'Adresse', 'wam-newsletter' ) . '</th>';
			echo '<th>' . esc_html__( 'Message', 'wam-newsletter' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $echecs as $echec ) {
				printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $echec['email'] ), esc_html( $echec['error'] ) );
			}
			echo '</tbody></table></section>';
		}

		echo '<p class="description wam-nl-stats__note">';
		esc_html_e(
			'Le nombre d’ouvertures est indicatif, jamais une mesure : Apple Mail précharge les images (ce qui le gonfle) et d’autres clients les bloquent (ce qui le sous-estime). Les clics et les désinscriptions, eux, sont fiables.',
			'wam-newsletter'
		);
		echo '</p>';

		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Remise réelle
	// ------------------------------------------------------------------

	/** @return array{delivered:int,deferred:int,failed:int,checked:int} */
	private static function delivery_counts( int $id ): array {
		global $wpdb;
		$q      = Queue::table();
		$sortie = array(
			'delivered' => 0,
			'deferred'  => 0,
			'failed'    => 0,
			'checked'   => 0,
		);
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT delivery, COUNT(*) c FROM `$q` WHERE newsletter_id = %d AND delivery IS NOT NULL GROUP BY delivery", $id ), ARRAY_A ) as $r ) {
			if ( isset( $sortie[ $r['delivery'] ] ) ) {
				$sortie[ $r['delivery'] ] = (int) $r['c'];
				$sortie['checked']       += (int) $r['c'];
			}
		}
		return $sortie;
	}

	private static function render_delivery( int $id, array $remise ): void {
		echo '<section class="wam-nl-card"><h2>' . esc_html__( 'Remise chez les destinataires', 'wam-newsletter' ) . '</h2>';

		if ( ! Delivery::configured() ) {
			echo '<p class="wam-nl-card__aide">' . esc_html__( 'Non branché. Le suivi de livraison d’o2switch dit, pour chaque adresse, si le message a été remis, refusé ou mis en attente par le serveur du destinataire. Il suffit de renseigner l’identifiant et le jeton cPanel dans Réglages → Suivi.', 'wam-newsletter' ) . '</p></section>';
			return;
		}

		echo '<p class="wam-nl-card__aide">' . esc_html__( 'Relevé automatiquement 15 minutes, 2 heures et 24 heures après la fin de l’envoi, d’après le suivi de livraison d’o2switch. Une adresse refusée parce qu’elle n’existe pas passe en rebond et ne recevra plus rien.', 'wam-newsletter' ) . '</p>';

		printf(
			'<form method="post" action="%s"><input type="hidden" name="action" value="wam_nl_delivery_sync"><input type="hidden" name="newsletter" value="%d">%s<button type="submit" class="button">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			(int) $id,
			wp_nonce_field( 'wam_nl_delivery_sync_' . $id, '_wpnonce', true, false ),
			esc_html__( 'Vérifier la remise maintenant', 'wam-newsletter' )
		);

		if ( $remise['failed'] > 0 ) {
			global $wpdb;
			$q      = Queue::table();
			$s      = \WamNewsletter\Subscribers\Repository::table();
			$refus  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT s.email, s.status, q.delivery_message FROM `$q` q JOIN `$s` s ON s.id = q.subscriber_id WHERE q.newsletter_id = %d AND q.delivery = %s ORDER BY q.id ASC LIMIT 200", $id, Delivery::FAILED ), ARRAY_A );
			echo '<table class="widefat striped wam-nl-refus"><thead><tr><th class="wam-nl-refus__adresse">' . esc_html__( 'Adresse refusée', 'wam-newsletter' ) . '</th><th class="wam-nl-refus__statut">' . esc_html__( 'Statut', 'wam-newsletter' ) . '</th><th>' . esc_html__( 'Réponse du serveur du destinataire', 'wam-newsletter' ) . '</th></tr></thead><tbody>';
			foreach ( $refus as $r ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( (string) $r['email'] ),
					esc_html( \WamNewsletter\Subscribers\Repository::status_label( (string) $r['status'] ) ),
					esc_html( (string) $r['delivery_message'] )
				);
			}
			echo '</tbody></table>';
		}
		echo '</section>';
	}

	// ------------------------------------------------------------------
	// En-tête
	// ------------------------------------------------------------------

	private static function render_header( int $id, \WP_Post $post ): void {
		$statut  = Scheduler::status( $id );
		$sujet   = (string) get_post_meta( $id, '_wam_nl_subject', true );
		$envoyee = (string) get_post_meta( $id, Scheduler::META_SENT_AT, true );

		$listes = array();
		foreach ( Scheduler::list_ids( $id ) as $list_id ) {
			$liste = Lists::find( $list_id );
			if ( $liste ) {
				$listes[] = (string) $liste['name'];
			}
		}

		echo '<div class="wam-nl-stats__entete">';
		echo '<div>';
		echo '<h1>' . esc_html( '' !== $sujet ? $sujet : $post->post_title ) . '</h1>';
		echo '<p class="wam-nl-stats__meta">';
		printf( '<span class="wam-nl-pastille wam-nl-pastille--%s">%s</span>', esc_attr( $statut ), esc_html( NewsletterList::status_label( $statut ) ) );
		if ( '' !== $envoyee ) {
			$ts = strtotime( $envoyee );
			if ( $ts ) {
				echo ' <span>' . esc_html( sprintf( /* translators: %s date */ __( 'envoi terminé le %s', 'wam-newsletter' ), wp_date( 'd/m/Y à H:i', $ts ) ) ) . '</span>';
			}
		}
		if ( $listes ) {
			echo ' <span>· ' . esc_html( implode( ', ', $listes ) ) . '</span>';
		}
		echo '</p></div>';

		echo '<p class="wam-nl-stats__actions">';
		printf( '<a class="button" href="%s">%s</a> ', esc_url( JournalScreen::url( $id ) ), esc_html__( 'Journal d’envoi', 'wam-newsletter' ) );
		printf( '<a class="button" href="%s">%s</a> ', esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ), esc_html__( 'Ouvrir la newsletter', 'wam-newsletter' ) );
		printf( '<a class="button-link" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . NewsletterPostType::POST_TYPE ) ), esc_html__( 'Retour aux newsletters', 'wam-newsletter' ) );
		echo '</p></div>';
	}

	// ------------------------------------------------------------------
	// Chiffres clés et parcours
	// ------------------------------------------------------------------

	private static function tile( string $libelle, string $valeur, string $detail, bool $alerte = false ): void {
		printf(
			'<div class="wam-nl-kpi%4$s"><span class="wam-nl-kpi__libelle">%1$s</span><span class="wam-nl-kpi__valeur">%2$s</span><span class="wam-nl-kpi__detail">%3$s</span></div>',
			esc_html( $libelle ),
			esc_html( $valeur ),
			esc_html( $detail ),
			$alerte ? ' is-alerte' : ''
		);
	}

	private static function pct( int $valeur, int $base ): string {
		if ( $base <= 0 ) {
			return '-';
		}
		return number_format_i18n( round( $valeur / $base * 100, 1 ), 1 ) . ' %';
	}

	private static function funnel_row( string $libelle, int $valeur, int $base ): void {
		$largeur = $base > 0 ? max( 0.5, min( 100, $valeur / $base * 100 ) ) : 0;
		printf(
			'<div class="wam-nl-funnel__ligne"><span class="wam-nl-funnel__libelle">%1$s</span>'
			. '<span class="wam-nl-funnel__piste"><span class="wam-nl-funnel__barre" style="width:%2$s%%"></span></span>'
			. '<span class="wam-nl-funnel__valeur">%3$s <span>%4$s</span></span></div>',
			esc_html( $libelle ),
			esc_attr( number_format( $largeur, 2, '.', '' ) ),
			esc_html( number_format_i18n( $valeur ) ),
			esc_html( self::pct( $valeur, $base ) )
		);
	}

	// ------------------------------------------------------------------
	// Courbe dans le temps (SVG)
	// ------------------------------------------------------------------

	/**
	 * Plafond de l'axe : quatre graduations entières et rondes (pas de 1, 2, 3,
	 * 5 × 10^n), pour lire 0 / 5 / 10 / 15 / 20 et jamais 0 / 3 / 5 / 8 / 10.
	 */
	public static function nice_max( int $max ): int {
		$brut      = max( 1, $max ) / 4;
		$puissance = pow( 10, floor( log10( $brut ) ) );
		$pas       = 10 * $puissance;
		foreach ( array( 1, 2, 3, 5, 10 ) as $m ) {
			if ( $m * $puissance >= $brut ) {
				$pas = $m * $puissance;
				break;
			}
		}
		return (int) max( 4, round( max( 1, $pas ) ) * 4 );
	}

	private static function time_label( string $t, string $pas, bool $court = false ): string {
		$ts = strtotime( $t );
		if ( ! $ts ) {
			return $t;
		}
		if ( 'day' === $pas ) {
			return wp_date( $court ? 'd/m' : 'l d/m', $ts, new \DateTimeZone( 'UTC' ) );
		}
		return wp_date( $court ? 'D H\h' : 'l d/m à H\h', $ts, new \DateTimeZone( 'UTC' ) );
	}

	private static function render_timeline( array $chrono ): void {
		$points = $chrono['points'];
		$pas    = $chrono['step'];
		$n      = count( $points );

		$larg = 760;
		$haut = 260;
		$g    = 44;
		$d    = 96;
		$h    = 16;
		$b    = 34;

		$max = 0;
		foreach ( $points as $p ) {
			$max = max( $max, $p['open'], $p['click'] );
		}
		$plafond = self::nice_max( $max );

		$x = static function ( int $i ) use ( $n, $larg, $g, $d ): float {
			return $n > 1 ? $g + $i * ( $larg - $g - $d ) / ( $n - 1 ) : $g + ( $larg - $g - $d ) / 2;
		};
		$y = static function ( int $v ) use ( $plafond, $haut, $h, $b ): float {
			return $h + ( $haut - $h - $b ) * ( 1 - $v / $plafond );
		};

		$svg = sprintf( '<svg class="wam-nl-chart__svg" viewBox="0 0 %d %d" role="img" aria-label="%s">', $larg, $haut, esc_attr__( 'Ouvertures et clics dans le temps', 'wam-newsletter' ) );

		// Grille et axe Y : quatre graduations rondes.
		for ( $k = 0; $k <= 4; $k++ ) {
			$valeur = (int) round( $plafond * $k / 4 );
			$yy     = $y( $valeur );
			$svg   .= sprintf( '<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" class="wam-nl-chart__grille" />', $g, $larg - $d, $yy, $yy );
			$svg   .= sprintf( '<text x="%d" y="%.1f" class="wam-nl-chart__axe" text-anchor="end">%s</text>', $g - 8, $yy + 4, esc_html( number_format_i18n( $valeur ) ) );
		}

		// Axe X : au plus six étiquettes, réparties.
		$etiquettes = min( 6, $n );
		$deja       = array();
		for ( $k = 0; $k < $etiquettes; $k++ ) {
			$i = $etiquettes > 1 ? (int) round( $k * ( $n - 1 ) / ( $etiquettes - 1 ) ) : 0;
			if ( isset( $deja[ $i ] ) ) {
				continue;
			}
			$deja[ $i ] = true;
			$svg       .= sprintf( '<text x="%.1f" y="%d" class="wam-nl-chart__axe" text-anchor="middle">%s</text>', $x( $i ), $haut - 10, esc_html( self::time_label( $points[ $i ]['t'], $pas, true ) ) );
		}

		// Deux séries : traits de 2px, points d'extrémité cerclés de blanc.
		$series = array(
			'open'  => array( self::COLOR_OPEN, __( 'Ouvertures', 'wam-newsletter' ) ),
			'click' => array( self::COLOR_CLICK, __( 'Clics', 'wam-newsletter' ) ),
		);
		$fins = array();
		foreach ( $series as $cle => $def ) {
			$chemin = '';
			foreach ( $points as $i => $p ) {
				$chemin .= ( 0 === $i ? 'M' : 'L' ) . sprintf( '%.1f %.1f ', $x( $i ), $y( $p[ $cle ] ) );
			}
			if ( 'open' === $cle && $n > 1 ) {
				// Voile très léger sous la série principale (10 %).
				$svg .= sprintf( '<path d="%sL%.1f %.1f L%.1f %.1f Z" fill="%s" fill-opacity="0.1" />', $chemin, $x( $n - 1 ), $y( 0 ), $x( 0 ), $y( 0 ), esc_attr( $def[0] ) );
			}
			$svg .= sprintf( '<path d="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />', trim( $chemin ), esc_attr( $def[0] ) );
			$dernier = $points[ $n - 1 ][ $cle ];
			$svg    .= sprintf( '<circle cx="%.1f" cy="%.1f" r="4" fill="%s" stroke="#ffffff" stroke-width="2" />', $x( $n - 1 ), $y( $dernier ), esc_attr( $def[0] ) );
			$fins[ $cle ] = $y( $dernier );
		}

		// Étiquettes directes en bout de courbe, écartées si elles se chevauchent.
		if ( abs( $fins['open'] - $fins['click'] ) < 16 ) {
			if ( $fins['open'] <= $fins['click'] ) {
				$fins['open']  -= 8;
				$fins['click'] += 8;
			} else {
				$fins['open']  += 8;
				$fins['click'] -= 8;
			}
		}
		foreach ( $series as $cle => $def ) {
			$svg .= sprintf( '<text x="%.1f" y="%.1f" class="wam-nl-chart__fin">%s</text>', $x( $n - 1 ) + 10, $fins[ $cle ] + 4, esc_html( $def[1] ) );
		}

		// Zones de survol, une par créneau (plus larges que les marques).
		$pas_x = $n > 1 ? ( $larg - $g - $d ) / ( $n - 1 ) : ( $larg - $g - $d );
		foreach ( $points as $i => $p ) {
			$svg .= sprintf(
				'<rect class="wam-nl-chart__zone" x="%.1f" y="%d" width="%.1f" height="%d" data-x="%.1f" data-label="%s" data-open="%d" data-click="%d" />',
				$x( $i ) - $pas_x / 2,
				$h,
				$pas_x,
				$haut - $h - $b,
				$x( $i ),
				esc_attr( self::time_label( $p['t'], $pas ) ),
				$p['open'],
				$p['click']
			);
		}
		$svg .= sprintf( '<line class="wam-nl-chart__repere" x1="0" x2="0" y1="%d" y2="%d" />', $h, $haut - $b );
		$svg .= '</svg>';

		echo '<div class="wam-nl-chart">';
		echo '<ul class="wam-nl-legende">';
		foreach ( $series as $def ) {
			printf( '<li><span class="wam-nl-legende__trait" style="background:%s"></span>%s</li>', esc_attr( $def[0] ), esc_html( $def[1] ) );
		}
		echo '</ul>';
		echo '<div class="wam-nl-chart__cadre">' . $svg . '<div class="wam-nl-chart__bulle" hidden></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG construit ci-dessus, valeurs échappées.

		// Vue tableau : les mêmes chiffres, lisibles sans la couleur.
		echo '<details class="wam-nl-chart__table"><summary>' . esc_html__( 'Voir les chiffres', 'wam-newsletter' ) . '</summary>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Créneau', 'wam-newsletter' ) . '</th><th>' . esc_html__( 'Ouvertures', 'wam-newsletter' ) . '</th><th>' . esc_html__( 'Clics', 'wam-newsletter' ) . '</th></tr></thead><tbody>';
		foreach ( $points as $p ) {
			if ( 0 === $p['open'] && 0 === $p['click'] ) {
				continue;
			}
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( self::time_label( $p['t'], $pas ) ), esc_html( number_format_i18n( $p['open'] ) ), esc_html( number_format_i18n( $p['click'] ) ) );
		}
		echo '</tbody></table></details>';
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Liens cliqués
	// ------------------------------------------------------------------

	/**
	 * Ce que vise un lien : titre et vignette.
	 *
	 * Un lien vers une page du site (cours, stage, article) prend le titre et
	 * l'image à la une du contenu. Sinon, l'image que le lien entoure dans
	 * l'e-mail (bannière, photo d'un bloc Contenus), puis à défaut son texte.
	 *
	 * @return array{titre:string,vignette:string}
	 */
	public static function describe_link( string $url, string $html_fige ): array {
		$titre    = '';
		$vignette = '';

		// Les deux côtés décodés : l'URL d'un clic peut garder « &#038; » là où le
		// HTML de l'e-mail porte « & », et inversement.
		$url     = html_entity_decode( $url, ENT_QUOTES );
		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			$titre    = (string) get_the_title( $post_id );
			$vignette = (string) get_the_post_thumbnail_url( $post_id, 'thumbnail' );
		}

		if ( '' !== $html_fige && ( '' === $titre || '' === $vignette ) ) {
			foreach ( self::anchors( $html_fige ) as $ancre ) {
				if ( $ancre['href'] !== $url ) {
					continue;
				}
				if ( '' === $vignette && preg_match( '#<img[^>]+src="([^"]+)"#i', $ancre['inner'], $m ) ) {
					$vignette = html_entity_decode( $m[1], ENT_QUOTES );
				}
				if ( '' === $titre ) {
					$texte = trim( html_entity_decode( wp_strip_all_tags( $ancre['inner'] ), ENT_QUOTES ) );
					if ( '' !== $texte ) {
						$titre = $texte;
					}
				}
			}
		}

		if ( '' === $titre ) {
			$hote   = (string) wp_parse_url( $url, PHP_URL_HOST );
			$chemin = (string) wp_parse_url( $url, PHP_URL_PATH );
			$titre  = trim( $hote . ( '/' !== $chemin ? $chemin : '' ), '/' );
		}

		return array(
			'titre'    => $titre,
			'vignette' => $vignette,
		);
	}

	/** @return array<int,array{href:string,inner:string,full:string}> */
	private static function anchors( string $html ): array {
		if ( ! preg_match_all( '#<a\b([^>]*)>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
			return array();
		}
		$sortie = array();
		foreach ( $m as $a ) {
			if ( ! preg_match( '#\bhref="([^"]*)"#i', $a[1], $h ) ) {
				continue;
			}
			$sortie[] = array(
				'href'  => html_entity_decode( $h[1], ENT_QUOTES ),
				'inner' => $a[2],
				'full'  => $a[0],
			);
		}
		return $sortie;
	}

	private static function render_links( array $liens, string $html_fige ): void {
		$max = 1;
		foreach ( $liens as $l ) {
			$max = max( $max, (int) $l['total'] );
		}

		echo '<ul class="wam-nl-liens">';
		foreach ( $liens as $l ) {
			$info = self::describe_link( (string) $l['url'], $html_fige );
			echo '<li class="wam-nl-lien">';
			if ( '' !== $info['vignette'] ) {
				printf( '<img class="wam-nl-lien__vignette" src="%s" alt="" loading="lazy" />', esc_url( $info['vignette'] ) );
			} else {
				echo '<span class="wam-nl-lien__vignette wam-nl-lien__vignette--vide dashicons dashicons-admin-links" aria-hidden="true"></span>';
			}
			echo '<span class="wam-nl-lien__corps">';
			printf( '<a class="wam-nl-lien__titre" href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( (string) $l['url'] ), esc_html( $info['titre'] ) );
			printf( '<span class="wam-nl-lien__url">%s</span>', esc_html( (string) $l['url'] ) );
			printf(
				'<span class="wam-nl-lien__piste"><span class="wam-nl-lien__barre" style="width:%s%%"></span></span>',
				esc_attr( number_format( max( 1, (int) $l['total'] / $max * 100 ), 2, '.', '' ) )
			);
			echo '</span>';
			printf(
				'<span class="wam-nl-lien__chiffres"><strong>%1$s</strong> %2$s<br><span>%3$s</span></span>',
				esc_html( number_format_i18n( (int) $l['total'] ) ),
				esc_html( _n( 'clic', 'clics', (int) $l['total'], 'wam-newsletter' ) ),
				esc_html( sprintf( /* translators: %s personnes */ _n( '%s personne', '%s personnes', (int) $l['uniques'], 'wam-newsletter' ), number_format_i18n( (int) $l['uniques'] ) ) )
			);
			echo '</li>';
		}
		echo '</ul>';
	}

	// ------------------------------------------------------------------
	// Carte des clics
	// ------------------------------------------------------------------

	/**
	 * L'e-mail figé, avec une pastille de clics après chaque lien.
	 *
	 * Les marqueurs restés dans le HTML figé (désinscription, vue navigateur,
	 * pixel, prénom) sont neutralisés comme pour l'aperçu de l'éditeur : la
	 * carte est un document inerte, affiché dans une iframe sans script.
	 */
	public static function heatmap_html( string $html_fige, array $liens ): string {
		$compte = array();
		foreach ( $liens as $l ) {
			$cle = html_entity_decode( (string) $l['url'], ENT_QUOTES );
			$deja = $compte[ $cle ] ?? array( 0, 0 );
			$compte[ $cle ] = array( $deja[0] + (int) $l['total'], $deja[1] + (int) $l['uniques'] );
		}

		$html = Placeholders::replace( $html_fige, Placeholders::preview_values() );
		$html = Placeholders::replace(
			$html,
			array(
				'{prenom}' => __( 'Prénom', 'wam-newsletter' ),
				'{nom}'    => __( 'Nom', 'wam-newsletter' ),
				'{email}'  => 'adresse@exemple.fr',
			)
		);

		return (string) preg_replace_callback(
			'#<a\b([^>]*)>(.*?)</a>#is',
			static function ( $m ) use ( $compte ) {
				if ( ! preg_match( '#\bhref="([^"]*)"#i', $m[1], $h ) ) {
					return $m[0];
				}
				$href = html_entity_decode( $h[1], ENT_QUOTES );
				if ( ! preg_match( '#^https?://#i', $href ) ) {
					return $m[0];
				}
				$total   = $compte[ $href ][0] ?? 0;
				$uniques = $compte[ $href ][1] ?? 0;
				$fond    = $total > 0 ? self::COLOR_OPEN : '#8c8f94';
				$texte   = $total > 0
					? sprintf( /* translators: 1: clics, 2: personnes */ _n( '%1$s clic · %2$s pers.', '%1$s clics · %2$s pers.', $total, 'wam-newsletter' ), number_format_i18n( $total ), number_format_i18n( $uniques ) )
					: __( '0 clic', 'wam-newsletter' );
				$pastille = sprintf(
					'<span style="display:inline-block;vertical-align:middle;margin:4px 0 4px 6px;padding:2px 8px;border-radius:10px;background:%s;color:#ffffff;font:bold 12px/1.5 Arial,Helvetica,sans-serif;white-space:nowrap;">%s</span>',
					$fond,
					esc_html( $texte )
				);
				return $m[0] . $pastille;
			},
			$html
		);
	}
}
