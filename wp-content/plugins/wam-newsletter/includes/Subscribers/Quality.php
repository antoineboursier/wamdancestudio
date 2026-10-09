<?php
namespace WamNewsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

/**
 * Contrôle qualité des adresses : robots, fautes de frappe, adresses techniques.
 *
 * Une seule définition, utilisée partout où une adresse entre dans la base :
 * import WooCommerce, synchronisation automatique, formulaire public, et outil
 * « Contrôle qualité » qui rattrape l'existant.
 *
 * Les règles sont nées de la base réelle (09/10/2026) :
 *  - 9 commandes de robots WooCommerce (noms plausibles, domaines `.top`/`.ru`/
 *    `topcrush.org`) ;
 *  - 12 adresses de personnes réelles avec un domaine mal tapé (`gmai.com`,
 *    `hotmail.ff`…), qui rebondiraient à coup sûr ;
 *  - 5 adresses MailPoet portant un suffixe technique `….t-sender-sib.com`.
 *
 * Principe : mieux vaut laisser passer un robot qu'écarter un·e vrai·e adhérent·e.
 * Un doute n'écarte jamais, il se signale (verdict « review »).
 */
class Quality {

	const OK     = 'ok';
	const FIX    = 'fix';
	const REJECT = 'reject';
	const REVIEW = 'review';

	/** Fautes de domaine courantes → domaine correct. Aucune ambiguïté possible. */
	const TYPOS = array(
		'gmai.com'     => 'gmail.com',
		'gmial.com'    => 'gmail.com',
		'gmal.com'     => 'gmail.com',
		'gmil.com'     => 'gmail.com',
		'gamil.com'    => 'gmail.com',
		'gnail.com'    => 'gmail.com',
		'gmail.con'    => 'gmail.com',
		'gmail.cim'    => 'gmail.com',
		'gmail.co'     => 'gmail.com',
		'gmail.cm'     => 'gmail.com',
		'gmail.om'     => 'gmail.com',
		'hotmai.fr'    => 'hotmail.fr',
		'hotmal.fr'    => 'hotmail.fr',
		'hotmial.fr'   => 'hotmail.fr',
		'hotmil.fr'    => 'hotmail.fr',
		'hotmail.ff'   => 'hotmail.fr',
		'hotmail.fe'   => 'hotmail.fr',
		'hotmail.f'    => 'hotmail.fr',
		'hotmai.com'   => 'hotmail.com',
		'hotmal.com'   => 'hotmail.com',
		'hotmial.com'  => 'hotmail.com',
		'hotmail.con'  => 'hotmail.com',
		'hotmail.cm'   => 'hotmail.com',
		'yahoo.fe'     => 'yahoo.fr',
		'yahoo.ff'     => 'yahoo.fr',
		'yahoo.f'      => 'yahoo.fr',
		'yaho.fr'      => 'yahoo.fr',
		'yahooo.fr'    => 'yahoo.fr',
		'yahoo.con'    => 'yahoo.com',
		'yahoo.cm'     => 'yahoo.com',
		'orange.ft'    => 'orange.fr',
		'orange.f'     => 'orange.fr',
		'orange.fe'    => 'orange.fr',
		'orange.ff'    => 'orange.fr',
		'oragne.fr'    => 'orange.fr',
		'wanadoo.f'    => 'wanadoo.fr',
		'wanadoo.ft'   => 'wanadoo.fr',
		'free.f'       => 'free.fr',
		'free.ff'      => 'free.fr',
		'sfr.f'        => 'sfr.fr',
		'sfr.ft'       => 'sfr.fr',
		'outlook.fe'   => 'outlook.fr',
		'outlook.ff'   => 'outlook.fr',
		'outlook.con'  => 'outlook.com',
		'icloud.con'   => 'icloud.com',
		'iclod.com'    => 'icloud.com',
		'laposte.nt'   => 'laposte.net',
		'live.ff'      => 'live.fr',
		'live.fe'      => 'live.fr',
	);

	/** Domaines de spam constatés dans les commandes, ou de messagerie jetable. */
	const BLOCKED_DOMAINS = array(
		'topcrush.org',
		'privbibl.ru',
		'noreply0.com',
		'mailinator.com',
		'yopmail.com',
		'yopmail.fr',
		'guerrillamail.com',
		'guerrillamail.net',
		'sharklasers.com',
		'tempmail.com',
		'temp-mail.org',
		'10minutemail.com',
		'trashmail.com',
		'getnada.com',
		'dispostable.com',
		'maildrop.cc',
		'fakeinbox.com',
		'throwawaymail.com',
	);

	/** Extensions qu'aucun·e adhérent·e du studio n'utilise, et que les robots adorent. */
	const BLOCKED_TLD_PATTERN = '/\.(top|ru|su|xyz|icu|click|pw|cfd|sbs|monster|buzz|bz)$/';

	/** Fournisseurs servant de référence pour repérer une faute de frappe. */
	const MAJOR_PROVIDERS = array( 'gmail.com', 'hotmail.fr', 'hotmail.com', 'yahoo.fr', 'yahoo.com', 'orange.fr', 'outlook.fr', 'outlook.com', 'free.fr', 'icloud.com', 'wanadoo.fr', 'sfr.fr', 'laposte.net', 'live.fr' );

	/** Domaines réels proches d'un fournisseur majeur : ne jamais les signaler. */
	const LEGIT_NEAR = array( 'ymail.com', 'live.com', 'live.be', 'msn.com', 'me.com', 'gmx.fr', 'gmx.com', 'aol.com', 'aol.fr', 'yahoo.be', 'yahoo.de', 'hotmail.be', 'hotmail.de', 'outlook.be', 'neuf.fr', 'sfr.net', 'sfr.com', 'bbox.fr', 'online.fr', 'laposte.fr', 'orange.com', 'gmail.fr', 'proton.me', 'protonmail.com', 'tutamail.com', 'zaclys.net', 'netcourrier.com', 'dbmail.com', 'ntymail.com', 'lilo.org' );

	/**
	 * Évalue une adresse.
	 *
	 * @param bool $dns Vérifie que le domaine existe et reçoit du courrier.
	 * @return array{verdict:string,email:string,reason:string,code:string}
	 *         `email` : l'adresse à retenir (corrigée si verdict « fix »).
	 *         `code`  : syntax | technical | bot | dns | typo | fixed | ''.
	 */
	public static function assess( string $email, string $first_name = '', string $last_name = '', bool $dns = true ): array {
		$email = strtolower( trim( $email ) );

		if ( ! is_email( $email ) ) {
			return self::result( self::REJECT, $email, __( 'adresse invalide', 'wam-newsletter' ), 'syntax' );
		}

		// 1. Corrections sûres : suffixe technique, faute de domaine connue.
		$corrige = self::fixed_email( $email );
		$verdict = $corrige !== $email ? self::FIX : self::OK;
		$email   = $corrige;

		$domaine = self::domain( $email );
		$local   = (string) strstr( $email, '@', true );

		// 2. Adresses techniques : on ne peut pas écrire à quelqu'un.
		if ( preg_match( '/^(no-?reply|do-?not-?reply|mailer-daemon|postmaster|bounces?)([+._-]|$)/', $local ) || preg_match( '/^reply\+/', $local ) || false !== strpos( $domaine, 't-sender-sib.com' ) ) {
			return self::result( self::REJECT, $email, __( 'adresse technique', 'wam-newsletter' ), 'technical' );
		}

		// 3. Robots et domaines de spam.
		if ( self::is_probable_bot( $email, $first_name, $last_name ) ) {
			return self::result( self::REJECT, $email, __( 'robot probable', 'wam-newsletter' ), 'bot' );
		}

		// 4. Le domaine existe-t-il, et reçoit-il du courrier ?
		if ( $dns && ! self::domain_receives_mail( $domaine ) ) {
			return self::result( self::REJECT, $email, __( 'domaine sans messagerie', 'wam-newsletter' ), 'dns' );
		}

		// 5. Doute : très proche d'un grand fournisseur sans être dans la liste.
		if ( self::OK === $verdict && self::looks_like_typo( $domaine ) ) {
			return self::result( self::REVIEW, $email, sprintf( /* translators: %s domaine */ __( 'domaine proche d’un fournisseur connu (%s) : faute de frappe ?', 'wam-newsletter' ), $domaine ), 'typo' );
		}

		return self::result( $verdict, $email, self::FIX === $verdict ? __( 'adresse corrigée', 'wam-newsletter' ) : '', self::FIX === $verdict ? 'fixed' : '' );
	}

	private static function result( string $verdict, string $email, string $reason, string $code = '' ): array {
		return array(
			'verdict' => $verdict,
			'email'   => $email,
			'reason'  => $reason,
			'code'    => $code,
		);
	}

	public static function domain( string $email ): string {
		$d = strrchr( $email, '@' );
		return false === $d ? '' : strtolower( substr( $d, 1 ) );
	}

	/** Adresse corrigée si une correction sûre existe, sinon l'adresse telle quelle. */
	public static function fixed_email( string $email ): string {
		// Artefact d'un ancien export d'un service d'envoi : « x@yahoo.fr.87279766.t-sender-sib.com ».
		$email = (string) preg_replace( '/\.\d+\.t-sender-sib\.com$/i', '', $email );

		$domaine = self::domain( $email );
		if ( isset( self::TYPOS[ $domaine ] ) ) {
			$email = (string) strstr( $email, '@', true ) . '@' . self::TYPOS[ $domaine ];
		}
		return $email;
	}

	private static function looks_like_typo( string $domaine ): bool {
		if ( '' === $domaine || in_array( $domaine, self::MAJOR_PROVIDERS, true ) || in_array( $domaine, self::LEGIT_NEAR, true ) ) {
			return false;
		}
		foreach ( self::MAJOR_PROVIDERS as $ref ) {
			// Deux fautes permises sur un long domaine, une seule sur un court
			// (« sfr.fr » est à deux lettres de beaucoup de domaines légitimes).
			$tolerance = strlen( $ref ) >= 9 ? 2 : 1;
			if ( levenshtein( $domaine, $ref ) <= $tolerance ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Le domaine reçoit-il du courrier ? Résultat mémorisé : une centaine de
	 * domaines distincts pour plusieurs milliers d'adresses. Un échec de résolution
	 * DNS n'écarte personne : on ne rejette que sur une réponse négative nette, et
	 * seulement si la résolution fonctionne (test sur un domaine qui existe).
	 */
	public static function domain_receives_mail( string $domaine ): bool {
		if ( '' === $domaine || ! function_exists( 'checkdnsrr' ) ) {
			return true;
		}
		/**
		 * Permet de couper la vérification DNS (tests, hébergement sans résolveur).
		 *
		 * @param bool $actif
		 */
		if ( ! apply_filters( 'wam_nl_quality_check_dns', true ) ) {
			return true;
		}

		$cle   = 'wam_nl_dns_' . md5( $domaine );
		$cache = get_transient( $cle );
		if ( false !== $cache ) {
			return '1' === $cache;
		}

		// Garde-fou : si même gmail.com ne se résout pas, le résolveur est en panne.
		if ( ! checkdnsrr( 'gmail.com', 'MX' ) ) {
			return true;
		}

		$recoit = checkdnsrr( $domaine, 'MX' ) || checkdnsrr( $domaine, 'A' );
		if ( ! $recoit ) {
			// Une seconde tentative avant de conclure : une panne DNS passagère ne
			// doit écarter personne.
			usleep( 300000 );
			$recoit = checkdnsrr( $domaine, 'MX' ) || checkdnsrr( $domaine, 'A' );
		}
		set_transient( $cle, $recoit ? '1' : '0', $recoit ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		return $recoit;
	}

	/**
	 * Commande probablement passée par un robot (test de cartes bancaires, spam).
	 *
	 * Volontairement prudent : mieux vaut laisser passer un robot qu'écarter un·e
	 * vrai·e adhérent·e.
	 */
	public static function is_probable_bot( string $email, string $first_name, string $last_name ): bool {
		foreach ( array( $first_name, $last_name ) as $nom ) {
			$nom = trim( $nom );
			if ( strlen( $nom ) < 8 || preg_match( "/[\\s\\-'.]/u", $nom ) ) {
				continue;
			}
			// Passages minuscule → majuscule au milieu d'un mot. « McDonald » en a
			// un, « DeLaCruz » deux ; un nom aléatoire en a beaucoup plus.
			$transitions = (int) preg_match_all( '/\p{Ll}\p{Lu}/u', $nom );
			if ( $transitions >= 3 || ( $transitions >= 2 && strlen( $nom ) >= 12 ) ) {
				return true;
			}
		}

		// Alphabet cyrillique : les robots de spam russophones y mettent noms et
		// prénoms. Une vraie personne qui l'utiliserait reste possible mais rare ;
		// il suffit de l'ajouter à la main.
		if ( preg_match( '/\p{Cyrillic}/u', $first_name . $last_name ) ) {
			return true;
		}

		$domaine = self::domain( strtolower( $email ) );
		if ( '' !== $domaine ) {
			if ( in_array( $domaine, self::BLOCKED_DOMAINS, true ) || preg_match( self::BLOCKED_TLD_PATTERN, $domaine ) ) {
				return true;
			}
		}

		// Technique du « point Gmail » : a.b.c.d.e@gmail.com est la même boîte que
		// abcde@gmail.com. Quelqu'un qui écrit son adresse avec 4 points ou plus
		// est presque toujours un robot.
		$partie = strstr( $email, '@', true );
		if ( false !== $partie && preg_match( '/@(gmail|googlemail)\.com$/i', $email ) && substr_count( $partie, '.' ) >= 4 ) {
			return true;
		}

		return false;
	}

	// ------------------------------------------------------------------
	// Rattrapage de l'existant
	// ------------------------------------------------------------------

	/**
	 * Passe en revue les abonné·es actifs, et corrige ou écarte ce qui doit l'être.
	 *
	 * - « fix » : l'adresse est corrigée ; si l'adresse correcte existe déjà, la
	 *   ligne fautive est mise à la corbeille et ses listes reportées sur l'autre.
	 * - « reject » : mise à la corbeille (réversible).
	 * - « review » : signalé, jamais modifié.
	 *
	 * Les adresses ajoutées à la main (source « admin ») ne sont jamais touchées :
	 * quelqu'un les a saisies exprès.
	 *
	 * @param int[] $seulement Limite l'analyse à ces identifiants (vide = tout).
	 * @return array{scanned:int,fixed:int,merged:int,rejected:int,review:int,samples:array<string,array<int,string>>,dry_run:bool}
	 */
	public static function scan( bool $a_blanc = true, array $seulement = array() ): array {
		global $wpdb;
		$t = Repository::table();

		// Périmètre optionnel (tests, ou futur « contrôler la sélection »).
		$restriction = '';
		if ( $seulement ) {
			$restriction = ' AND id IN (' . implode( ',', array_map( 'intval', $seulement ) ) . ')';
		}

		$rapport = array(
			'scanned'  => 0,
			'fixed'    => 0,
			'merged'   => 0,
			'rejected' => 0,
			'review'   => 0,
			'samples'  => array(
				'fix'    => array(),
				'merge'  => array(),
				'reject' => array(),
				'review' => array(),
			),
			'dry_run'  => $a_blanc,
		);

		$dernier = 0;
		do {
			$lignes = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, email, first_name, last_name FROM `$t` WHERE status = %s AND consent_source <> %s AND id > %d{$restriction} ORDER BY id ASC LIMIT 500",
					Repository::STATUS_SUBSCRIBED,
					'admin',
					$dernier
				),
				ARRAY_A
			);

			foreach ( $lignes as $r ) {
				$dernier = (int) $r['id'];
				++$rapport['scanned'];

				$verdict = self::assess( (string) $r['email'], (string) $r['first_name'], (string) $r['last_name'] );
				$id      = (int) $r['id'];
				$ligne   = (string) $r['email'];

				if ( self::OK === $verdict['verdict'] ) {
					continue;
				}

				if ( self::REVIEW === $verdict['verdict'] ) {
					++$rapport['review'];
					self::sample( $rapport, 'review', $ligne . ' - ' . $verdict['reason'] );
					continue;
				}

				if ( self::REJECT === $verdict['verdict'] ) {
					++$rapport['rejected'];
					self::sample( $rapport, 'reject', $ligne . ' - ' . $verdict['reason'] );
					if ( ! $a_blanc ) {
						Repository::set_status( $id, Repository::STATUS_TRASHED );
					}
					continue;
				}

				// FIX
				$cible = Repository::find_by_email( $verdict['email'] );
				if ( $cible && (int) $cible['id'] !== $id ) {
					++$rapport['merged'];
					self::sample( $rapport, 'merge', $ligne . ' → déjà présent·e : ' . $verdict['email'] );
					if ( ! $a_blanc ) {
						Repository::add_to_lists( (int) $cible['id'], Repository::list_ids_of( $id ) );
						Repository::set_status( $id, Repository::STATUS_TRASHED );
					}
					continue;
				}

				++$rapport['fixed'];
				self::sample( $rapport, 'fix', $ligne . ' → ' . $verdict['email'] );
				if ( ! $a_blanc ) {
					Repository::update( $id, array( 'email' => $verdict['email'] ) );
				}
			}
		} while ( count( $lignes ) === 500 );

		return $rapport;
	}

	private static function sample( array &$rapport, string $categorie, string $texte ): void {
		if ( count( $rapport['samples'][ $categorie ] ) < 25 ) {
			$rapport['samples'][ $categorie ][] = $texte;
		}
	}
}
