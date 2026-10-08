<?php
namespace WamNewsletter\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Construction et vérification des URL publiques (§8.3, §8.4, §10).
 *
 * Toutes les URL de suivi sont signées en HMAC avec les sels du site. Pour les
 * clics, c'est une exigence explicite du §10 : un endpoint qui redirigerait vers
 * n'importe quelle URL passée en paramètre serait un « open redirect », donc un
 * tremplin pour du hameçonnage depuis notre domaine.
 *
 * Les paramètres sont volontairement courts : certaines passerelles tronquent
 * les URL longues, et chaque lien est réécrit dans l'e-mail.
 */
class Links {

	const PARAM_UNSUB = 'wam_nl_unsub';
	const PARAM_VIEW  = 'wam_nl_view';
	const PARAM_CLICK = 'wam_nl_click';
	const PARAM_OPEN  = 'wam_nl_open';

	/** Longueur de la signature conservée. 16 hexa = 64 bits, suffisant ici. */
	const SIG_LENGTH = 16;

	public static function sign( string $charge ): string {
		return substr( hash_hmac( 'sha256', $charge, wp_salt( 'wam_nl_tracking' ) ), 0, self::SIG_LENGTH );
	}

	public static function verify( string $charge, string $signature ): bool {
		return hash_equals( self::sign( $charge ), $signature );
	}

	/**
	 * Désinscription. Un seul paramètre : le token de la personne.
	 *
	 * Pas de signature : le token est déjà 32 octets aléatoires, imprévisible.
	 */
	public static function unsubscribe_url( string $token ): string {
		return add_query_arg( self::PARAM_UNSUB, $token, home_url( '/' ) );
	}

	/** Adresse mailto de repli des en-têtes List-Unsubscribe (§8.3). */
	public static function unsubscribe_mailto(): string {
		$adresse = (string) \WamNewsletter\Settings\Settings::get( 'from_email' );
		if ( ! is_email( $adresse ) ) {
			$adresse = (string) get_option( 'admin_email' );
		}
		return 'mailto:' . $adresse . '?subject=unsubscribe';
	}

	/** « Voir dans le navigateur » : page publique tokenisée (§7.4). */
	public static function view_url( int $newsletter_id, string $token ): string {
		return add_query_arg(
			array(
				self::PARAM_VIEW => $newsletter_id,
				't'              => $token,
			),
			home_url( '/' )
		);
	}

	/** Pixel d'ouverture (§10). */
	public static function open_url( int $newsletter_id, string $token ): string {
		return add_query_arg(
			array(
				self::PARAM_OPEN => $newsletter_id,
				't'              => $token,
			),
			home_url( '/' )
		);
	}

	public static function open_pixel( int $newsletter_id, string $token ): string {
		return sprintf(
			'<img src="%s" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;" />',
			esc_url( self::open_url( $newsletter_id, $token ) )
		);
	}

	/**
	 * Lien de suivi des clics, signé (§10).
	 *
	 * La destination est signée avec l'identifiant de newsletter et le token :
	 * un lien forgé vers un autre site ne passe pas la vérification, et un lien
	 * valide ne peut pas être réutilisé pour une autre newsletter.
	 */
	public static function click_url( int $newsletter_id, string $token, string $destination ): string {
		$charge = $newsletter_id . '|' . $token . '|' . $destination;

		return add_query_arg(
			array(
				self::PARAM_CLICK => $newsletter_id,
				't'               => $token,
				'u'               => rawurlencode( $destination ),
				's'               => self::sign( $charge ),
			),
			home_url( '/' )
		);
	}

	public static function verify_click( int $newsletter_id, string $token, string $destination, string $signature ): bool {
		return self::verify( $newsletter_id . '|' . $token . '|' . $destination, $signature );
	}

	/**
	 * Réécrit les liens d'un HTML d'e-mail vers l'endpoint de suivi.
	 *
	 * Volontairement épargnés :
	 *  - le lien de désinscription et celui de « voir dans le navigateur », qui
	 *    sont encore des marqueurs à ce stade et ne doivent pas être suivis ;
	 *  - les `mailto:` et `tel:`, qu'un endpoint HTTP ne saurait pas relayer ;
	 *  - les ancres internes.
	 */
	public static function rewrite( string $html, int $newsletter_id, string $token ): string {
		return (string) preg_replace_callback(
			'#(<a\b[^>]*\bhref=")([^"]+)(")#i',
			static function ( $m ) use ( $newsletter_id, $token ) {
				$url = $m[2];

				if ( '' === $url || '#' === $url[0] ) {
					return $m[0];
				}
				if ( preg_match( '#^(mailto:|tel:)#i', $url ) ) {
					return $m[0];
				}
				// Marqueurs non encore résolus : ils portent leurs propres liens.
				if ( false !== strpos( $url, '{{wam_nl_' ) ) {
					return $m[0];
				}
				if ( ! preg_match( '#^https?://#i', $url ) ) {
					return $m[0];
				}

				return $m[1] . esc_url( self::click_url( $newsletter_id, $token, $url ) ) . $m[3];
			},
			$html
		);
	}
}
