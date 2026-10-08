<?php
namespace WamNewsletter\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Marqueurs remplacés tardivement dans le HTML de l'e-mail.
 *
 * Le HTML est figé au lancement de l'envoi (§7.3) pour que tout le monde
 * reçoive la même chose. Mais trois éléments dépendent du destinataire : le lien
 * de désinscription, le lien « voir dans le navigateur » et les variables de
 * personnalisation. Ils sont donc écrits comme marqueurs au rendu, et résolus au
 * moment de l'expédition de chaque message.
 *
 * Marqueurs volontairement non ambigus (doubles accolades) : les variables
 * visibles par la rédactrice, elles, restent en simple accolade ({prenom}), ce
 * qui évite de les confondre.
 */
class Placeholders {

	const UNSUB_URL = '{{wam_nl_unsub_url}}';
	const VIEW_URL  = '{{wam_nl_view_url}}';
	const OPEN_PIXEL = '{{wam_nl_open_pixel}}';

	/** Variables de personnalisation offertes à la rédactrice (§7.4). */
	const PERSON_TAGS = array( '{prenom}', '{nom}', '{email}' );

	/**
	 * Remplace les marqueurs techniques.
	 *
	 * @param string               $html
	 * @param array<string,string> $valeurs Marqueur => remplacement.
	 */
	public static function replace( string $html, array $valeurs ): string {
		return strtr( $html, $valeurs );
	}

	/**
	 * Valeurs d'aperçu : aucun lien actif, aucun pixel.
	 *
	 * Un aperçu qui enverrait sur le vrai endpoint de désinscription permettrait
	 * de se désabonner en cliquant dans l'éditeur.
	 *
	 * @return array<string,string>
	 */
	public static function preview_values(): array {
		return array(
			self::UNSUB_URL  => '#',
			self::VIEW_URL   => '#',
			self::OPEN_PIXEL => '',
		);
	}

	/**
	 * Personnalisation pour un·e destinataire.
	 *
	 * @param array|null $abonne Ligne de la table des abonné·es.
	 * @return array<string,string>
	 */
	public static function person_values( ?array $abonne ): array {
		$prenom = trim( (string) ( $abonne['first_name'] ?? '' ) );
		$nom    = trim( (string) ( $abonne['last_name'] ?? '' ) );
		$email  = (string) ( $abonne['email'] ?? '' );

		return array(
			'{prenom}' => $prenom,
			'{nom}'    => $nom,
			'{email}'  => $email,
		);
	}

	/**
	 * Nettoie les formules de politesse restées bancales après remplacement.
	 *
	 * « Bonjour {prenom}, » avec un prénom vide donnerait « Bonjour , » : le
	 * repli du §7.4 demande « Bonjour, » sans prénom. On corrige la ponctuation
	 * orpheline plutôt que d'imposer un prénom obligatoire à l'inscription.
	 */
	public static function tidy( string $texte ): string {
		// Seules la virgule et le point ne prennent pas d'espace avant eux en
		// français. Les ponctuations hautes (? ! ; :) en prennent une : les
		// inclure ici produirait « ça va? », une faute de typographie sur chaque
		// e-mail envoyé.
		$texte = preg_replace( '/\h+([,.])/u', '$1', $texte );

		// Ponctuations qui se suivent après un remplacement vide (« Bonjour ,, »).
		$texte = preg_replace( '/([,;:])\h*([,;:])/u', '$1', $texte );

		return (string) preg_replace( '/\h{2,}/u', ' ', $texte );
	}
}
