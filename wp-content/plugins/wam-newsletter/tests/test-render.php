<?php
/**
 * Lots 4 à 6 — identité visuelle, renderers de blocs, document e-mail.
 *
 * Ces tests portent sur ce qui casse réellement un e-mail en production :
 * l'AVIF invisible dans Outlook, les colonnes sans table fantôme, un lien sans
 * couleur en ligne, une largeur qui déborde. Le rendu visuel « joli » n'est pas
 * testable sans client de messagerie ; ce qui l'est, c'est le HTML produit.
 */
require_once __DIR__ . '/_harness.php';

use WamNewsletter\Blocks\Blocks;
use WamNewsletter\Editor\EditorSetup;
use WamNewsletter\Editor\EmailTheme;
use WamNewsletter\Editor\NewsletterPostType;
use WamNewsletter\Editor\RestApi;
use WamNewsletter\Integrations\ContentMap;
use WamNewsletter\Render\BlockRenderer;
use WamNewsletter\Render\Blocks\Core;
use WamNewsletter\Render\Blocks\Custom;
use WamNewsletter\Render\Blocks\Posts;
use WamNewsletter\Render\Brand;
use WamNewsletter\Render\EmailRenderer;
use WamNewsletter\Render\Html;
use WamNewsletter\Render\Images;
use WamNewsletter\Render\Placeholders;

$posts_temporaires = array();

try {

	echo "== Couleurs : lues dans le theme.json du thème ==\n";
	Brand::flush();
	$palette = Brand::theme_palette();
	wam_nl_assert( ! empty( $palette ), 'la palette du thème est lisible' );

	$c = Brand::colors();
	wam_nl_assert_equals( '#131620', $c['background'], 'fond = background-800 du thème' );
	wam_nl_assert_equals( '#F9F4EB', $c['text'], 'texte = text-normal' );
	wam_nl_assert_equals( '#FBD150', $c['accent'], 'accent = accent-yellow' );
	wam_nl_assert_equals( '#01E3BD', $c['separator'], 'séparateur = accent-green du thème (et non la valeur de secours)' );
	wam_nl_assert_equals( '#7F7F7F', $c['muted'], 'gris = text-disabled' );

	echo "== Normalisation des couleurs en hexadécimal ==\n";
	// Un e-mail ne sait lire ni var(), ni rgb(), ni la notation à 3 chiffres.
	wam_nl_assert_equals( '#AABBCC', Brand::to_hex( '#abc' ), 'notation courte développée' );
	wam_nl_assert_equals( '#FF0000', Brand::to_hex( 'rgb(255, 0, 0)' ), 'rgb() converti' );
	wam_nl_assert_equals( '#FF0000', Brand::to_hex( 'rgba(255,0,0,0.5)' ), 'rgba() converti, alpha ignoré' );
	wam_nl_assert_equals( '', Brand::to_hex( 'var(--wam-color-green)' ), 'une variable CSS est refusée' );
	wam_nl_assert_equals( '', Brand::to_hex( 'papayawhip' ), 'un nom de couleur est refusé' );
	wam_nl_assert_equals( '', Brand::to_hex( '' ), 'chaîne vide refusée' );

	echo "== Contraste calculé sur la luminance, pas sur une moyenne ==\n";
	// Le jaune d'accent est clair pour l'œil : du texte blanc dessus serait
	// illisible. Une moyenne RVB le ferait passer pour une couleur moyenne.
	wam_nl_assert_equals( $c['background'], Brand::contrast_color( '#FBD150' ), 'sur le jaune, texte sombre' );
	wam_nl_assert_equals( $c['text'], Brand::contrast_color( '#131620' ), 'sur le fond sombre, texte clair' );
	wam_nl_assert_equals( $c['background'], Brand::contrast_color( '#FFFFFF' ), 'sur blanc, texte sombre' );
	wam_nl_assert_equals( $c['text'], Brand::contrast_color( '#000000' ), 'sur noir, texte clair' );

	echo "== Largeurs du gabarit (§7.4) ==\n";
	wam_nl_assert_equals( 660, Brand::WIDTH_OUTER, 'conteneur à 660 px' );
	wam_nl_assert_equals( 620, Brand::WIDTH_CONTENT, 'zone de contenu à 620 px' );
	wam_nl_assert_equals( 20, Brand::GUTTER, 'gouttières de 20 px' );
	wam_nl_assert( false !== strpos( Brand::FONT_STACK, 'Arial' ), 'typographie standard web' );
	wam_nl_assert( false === strpos( Brand::FONT_STACK, 'Outfit' ), 'aucune police du thème' );

	echo "== Tronquage d'extrait au mot ==\n";
	$long = str_repeat( 'Le studio accueille tous les niveaux. ', 20 );
	$court = Html::excerpt( $long, 100 );
	wam_nl_assert( mb_strlen( $court ) <= 101, 'longueur respectée' );
	wam_nl_assert( '…' === mb_substr( $court, -1 ), 'terminé par des points de suspension' );
	wam_nl_assert( false === strpos( $court, 'stud…' ), 'coupé au mot, pas au milieu' );
	wam_nl_assert_equals( 'Court.', Html::excerpt( 'Court.', 100 ), 'un texte court n’est pas touché' );
	wam_nl_assert_equals( '', Html::excerpt( '   ', 100 ), 'un texte vide reste vide' );
	wam_nl_assert_equals( 'A & B', Html::excerpt( 'A &amp; B', 100 ), 'les entités HTML sont décodées' );
	wam_nl_assert_equals( 'Gras', Html::excerpt( '<strong>Gras</strong>', 100 ), 'les balises sont retirées' );

	echo "== Bouton « bulletproof » (§7.1.1) ==\n";
	$plein = Html::button( array( 'texte' => 'Réserver', 'url' => 'https://exemple.test/a' ) );
	wam_nl_assert( false !== strpos( $plein, '<table' ), 'rendu en table (Outlook ignore le padding d’un lien)' );
	wam_nl_assert( false !== strpos( $plein, 'role="presentation"' ), 'table de présentation, pas de données' );
	wam_nl_assert( false !== strpos( $plein, 'border-collapse:collapse' ), 'border-collapse posé' );
	wam_nl_assert( false !== strpos( $plein, 'border-radius:4px' ), 'coins arrondis' );
	wam_nl_assert( false !== strpos( $plein, 'background-color:' . $c['accent'] ), 'fond jaune par défaut' );
	wam_nl_assert( false !== strpos( $plein, 'color:' . $c['background'] ), 'texte sombre, calculé par contraste' );
	wam_nl_assert( false !== strpos( $plein, 'https://exemple.test/a' ), 'lien présent' );
	wam_nl_assert( false !== strpos( $plein, 'mso-line-height-rule:exactly' ), 'hauteur de ligne fiable sous Outlook' );
	wam_nl_assert( false !== strpos( $plein, 'Réserver' ), 'libellé présent' );

	$contour = Html::button( array( 'texte' => 'Voir', 'url' => 'https://exemple.test/b', 'style' => 'contour' ) );
	wam_nl_assert( false !== strpos( $contour, 'background-color:transparent' ), 'contour : fond transparent' );
	wam_nl_assert( false !== strpos( $contour, '2px solid ' . $c['accent'] ), 'contour : bordure de 2 px' );
	wam_nl_assert( false !== strpos( $contour, 'color:' . $c['accent'] ), 'contour : texte de la couleur choisie' );

	echo "== Bouton sans lien : pas de <a href=\"\"> ==\n";
	// Un <a href=""> renverrait sur la page courante au clic.
	$sans_lien = Html::button( array( 'texte' => 'Brouillon' ) );
	wam_nl_assert( false === strpos( $sans_lien, '<a ' ), 'aucun lien rendu tant qu’aucune URL n’est saisie' );
	wam_nl_assert( false !== strpos( $sans_lien, '<span' ), 'le libellé est rendu dans un span' );

	echo "== Entête : bannière, alt non vide, JPG ==\n";
	Images::register_sizes();
	$entete = Custom::header();
	wam_nl_assert( false !== strpos( $entete, '<img' ), 'une image est rendue' );
	wam_nl_assert( false === strpos( $entete, 'alt=""' ), 'alt jamais vide (la bannière porte le logo)' );
	wam_nl_assert( false !== strpos( $entete, 'WAM' ), 'alt renseigné avec le nom du studio' );
	wam_nl_assert( false === strpos( $entete, '.avif' ), 'aucune image AVIF (invisible dans Outlook desktop)' );
	wam_nl_assert( false === strpos( $entete, '.webp' ), 'aucune image WebP' );
	wam_nl_assert( false !== strpos( $entete, 'display:block' ), 'image en display:block (évite l’espace sous l’image)' );
	wam_nl_assert( false !== strpos( $entete, 'max-width:620px' ), 'bornée à la zone de contenu' );

	echo "== Entête : alt et image personnalisés ==\n";
	$entete2 = Custom::header( array( 'alt' => 'Bannière de la rentrée', 'url' => 'https://exemple.test/c' ) );
	wam_nl_assert( false !== strpos( $entete2, 'Bannière de la rentrée' ), 'alt personnalisé repris' );
	wam_nl_assert( false !== strpos( $entete2, 'https://exemple.test/c' ), 'lien personnalisé repris' );

	echo "== Pied de page (§7.6.2) ==\n";
	$pied = Custom::footer();
	wam_nl_assert( false !== strpos( $pied, Placeholders::UNSUB_URL ), 'le lien de désinscription est un marqueur, résolu à l’envoi' );
	wam_nl_assert( false !== strpos( $pied, 'Se désabonner' ), 'libellé de désinscription' );
	wam_nl_assert( false === stripos( $pied, 'Gestion de l' ), 'le lien « Gestion de l’abonnement » est supprimé, comme demandé' );
	wam_nl_assert( false !== strpos( $pied, 'font-size:12px' ), 'pied en 12 px' );
	wam_nl_assert( false !== strpos( $pied, $c['muted'] ), 'pied en gris discret' );
	wam_nl_assert( false !== strpos( $pied, 'Jean Jaurès' ), 'adresse présente' );

	echo "== Pied de page : icônes des réseaux sociaux (09/10/2026) ==\n";
	// Reflète les réglages réels du thème (Admin > Configuration > Réseaux) :
	// aucune donnée de test à fabriquer, la liste fait foi des deux côtés.
	$reseaux = Brand::social_links();
	foreach ( $reseaux as $reseau ) {
		wam_nl_assert(
			false !== strpos( $pied, Brand::social_icon_url( $reseau['slug'] ) ),
			'icône ' . $reseau['slug'] . ' présente, avec son URL PNG'
		);
		wam_nl_assert(
			false !== strpos( $pied, 'href="' . esc_url( $reseau['url'] ) . '"' ),
			'icône ' . $reseau['slug'] . ' pointe vers son URL enregistrée'
		);
	}
	wam_nl_assert( false === strpos( $pied, '.svg' ), 'jamais de SVG dans l’e-mail (non lu par Outlook/Gmail)' );
	if ( $reseaux ) {
		wam_nl_assert( substr_count( $pied, '<img' ) === count( $reseaux ), 'une icône par réseau renseigné, aucune de plus' );
		wam_nl_assert( false !== strpos( $pied, 'width="' ), 'largeur figée en attribut HTML (filet si le CSS est ignoré)' );
	}
	// Un réseau non renseigné dans le thème (TikTok, LinkedIn en l'absence de
	// réglage) ne doit jamais apparaître — même logique que le footer du site.
	foreach ( array_diff( array_keys( Brand::SOCIAL_NETWORKS ), array_column( $reseaux, 'slug' ) ) as $absent ) {
		wam_nl_assert( false === strpos( $pied, 'icon-' . $absent . '.png' ), $absent . ' absent tant qu’aucune URL n’est enregistrée' );
	}

	echo "== Pied de page : l'adresse est du texte, pas un lien ==\n";
	// Laissée en lien, les clients la transforment en lien Google Maps.
	$avant_adresse = substr( $pied, 0, strpos( $pied, 'Jean Jaurès' ) );
	$dernier_a     = strrpos( $avant_adresse, '<a ' );
	$dernier_fin   = strrpos( $avant_adresse, '</a>' );
	wam_nl_assert(
		false === $dernier_a || ( false !== $dernier_fin && $dernier_fin > $dernier_a ),
		'aucune balise de lien ouverte autour de l’adresse'
	);

	echo "== Séparateur : pointillé turquoise ==\n";
	$sep = Custom::separator();
	wam_nl_assert( false !== strpos( $sep, '2px dotted' ), 'filet pointillé de 2 px' );
	wam_nl_assert( false !== strpos( $sep, $c['separator'] ), 'couleur turquoise du thème' );
	wam_nl_assert( false !== strpos( $sep, 'font-size:0' ), 'cellule sans hauteur de texte' );
	$sep2 = Custom::separator( array( 'color' => '#FBD150' ) );
	wam_nl_assert( false !== strpos( $sep2, '#FBD150' ), 'couleur personnalisée respectée' );

	echo "== Espacement : hauteur fiable sous Outlook ==\n";
	$esp = Custom::spacer( array( 'height' => 40 ) );
	wam_nl_assert( false !== strpos( $esp, 'height:40px' ), 'hauteur demandée' );
	wam_nl_assert( false !== strpos( $esp, 'font-size:0' ), 'font-size à 0 (sinon Outlook impose une hauteur de ligne)' );
	wam_nl_assert( false !== strpos( $esp, 'line-height:0' ), 'line-height à 0' );
	wam_nl_assert( false !== strpos( Custom::spacer( array( 'height' => 9999 ) ), 'height:120px' ), 'hauteur plafonnée' );
	wam_nl_assert( false !== strpos( Custom::spacer( array( 'height' => 0 ) ), 'height:4px' ), 'hauteur plancher' );

	echo "== Paragraphe : styles en ligne, liens colorés ==\n";
	$para = Core::paragraph(
		array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
			'innerHTML' => '<p>Un <strong>texte</strong> avec un <a href="https://exemple.test/d">lien</a>.</p>',
		)
	);
	wam_nl_assert( false !== strpos( $para, '<strong>texte</strong>' ), 'le gras est conservé' );
	wam_nl_assert( false !== strpos( $para, 'font-size:16px' ), 'corps en 16 px et non 14 (défaut corrigé du §7.6.4)' );
	wam_nl_assert( false !== strpos( $para, 'text-decoration:underline' ), 'lien souligné' );
	wam_nl_assert( false !== strpos( $para, 'color:' . $c['text'] ), 'lien coloré en ligne (sinon bleu par défaut)' );
	wam_nl_assert( false !== strpos( $para, 'target="_blank"' ), 'lien ouvert dans un nouvel onglet' );
	wam_nl_assert( false !== strpos( $para, Brand::FONT_STACK ), 'police en ligne' );

	echo "== Paragraphe : le HTML indésirable est retiré ==\n";
	$sale = Core::paragraph(
		array(
			'blockName' => 'core/paragraph',
			'innerHTML' => '<p>Texte<script>alert(1)</script><span class="wp-x">et</span><img src="x.gif"></p>',
		)
	);
	wam_nl_assert( false === strpos( $sale, '<script' ), 'script retiré' );
	wam_nl_assert( false === strpos( $sale, '<span' ), 'span retiré' );
	wam_nl_assert( false === strpos( $sale, '<img' ), 'image en ligne retirée' );
	wam_nl_assert( false !== strpos( $sale, 'Texte' ), 'le texte survit' );
	wam_nl_assert_equals( '', Core::paragraph( array( 'blockName' => 'core/paragraph', 'innerHTML' => '<p></p>' ) ), 'un paragraphe vide ne rend rien' );

	echo "== Titre : un seul h1 au plus, titres de section en h2 ==\n";
	$h1 = Core::heading( array( 'blockName' => 'core/heading', 'attrs' => array( 'level' => 1 ), 'innerHTML' => '<h1>Titre</h1>' ) );
	wam_nl_assert( false !== strpos( $h1, '<h2' ), 'un h1 est ramené à h2 (plusieurs h1 = défaut du §7.6.4)' );
	wam_nl_assert( false === strpos( $h1, '<h1' ), 'aucun h1 produit' );
	$h3 = Core::heading( array( 'blockName' => 'core/heading', 'attrs' => array( 'level' => 3 ), 'innerHTML' => '<h3>Sous-titre</h3>' ) );
	wam_nl_assert( false !== strpos( $h3, '<h3' ), 'un h3 reste un h3' );
	$h6 = Core::heading( array( 'blockName' => 'core/heading', 'attrs' => array( 'level' => 6 ), 'innerHTML' => '<h6>Petit</h6>' ) );
	wam_nl_assert( false !== strpos( $h6, '<h4' ), 'les niveaux au-delà de h4 sont ramenés à h4' );
	wam_nl_assert( false !== strpos( $h1, 'color:' . $c['accent'] ), 'titre en jaune' );
	wam_nl_assert( false !== strpos( $h1, 'text-align:center' ), 'titre centré' );
	wam_nl_assert( false !== strpos( $h1, 'font-size:30px' ), 'titre de section en 30 px' );

	echo "== Taille et couleur choisies dans l'éditeur ==\n";
	$dim = Core::paragraph(
		array(
			'blockName' => 'core/paragraph',
			'attrs'     => array( 'style' => array( 'typography' => array( 'fontSize' => '22px' ), 'color' => array( 'text' => '#FBD150' ) ) ),
			'innerHTML' => '<p>Gros</p>',
		)
	);
	wam_nl_assert( false !== strpos( $dim, 'font-size:22px' ), 'taille libre reprise' );
	wam_nl_assert( false !== strpos( $dim, '#FBD150' ), 'couleur libre reprise' );

	$slug = Core::paragraph(
		array(
			'blockName' => 'core/paragraph',
			'attrs'     => array( 'fontSize' => 'small', 'textColor' => 'separator' ),
			'innerHTML' => '<p>Petit</p>',
		)
	);
	wam_nl_assert( false !== strpos( $slug, 'font-size:13px' ), 'taille par slug de la palette e-mail' );
	wam_nl_assert( false !== strpos( $slug, $c['separator'] ), 'couleur par slug de la palette e-mail' );

	echo "== Liste ==\n";
	$liste = Core::list_block(
		array(
			'blockName'   => 'core/list',
			'attrs'       => array(),
			'innerHTML'   => '<ul><li>Un</li><li>Deux</li></ul>',
			'innerBlocks' => array(),
		)
	);
	wam_nl_assert( false !== strpos( $liste, '<ul' ), 'liste à puces' );
	wam_nl_assert( 2 === substr_count( $liste, '<li' ), 'les deux éléments sont rendus' );
	wam_nl_assert( false !== strpos( $liste, 'padding:0 0 0 24px' ), 'retrait explicite (les clients ont des défauts différents)' );
	$ol = Core::list_block( array( 'blockName' => 'core/list', 'attrs' => array( 'ordered' => true ), 'innerHTML' => '<ol><li>Un</li></ol>' ) );
	wam_nl_assert( false !== strpos( $ol, '<ol' ), 'liste numérotée' );
	wam_nl_assert_equals( '', Core::list_block( array( 'blockName' => 'core/list', 'innerHTML' => '' ) ), 'une liste vide ne rend rien' );

	echo "== Colonnes : tables fantômes MSO pour Outlook (§7.6.4) ==\n";
	$colonnes = Core::columns(
		array(
			'blockName'   => 'core/columns',
			'attrs'       => array(),
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/column',
					'attrs'       => array(),
					'innerBlocks' => array( array( 'blockName' => 'core/paragraph', 'innerHTML' => '<p>Gauche</p>', 'attrs' => array() ) ),
				),
				array(
					'blockName'   => 'core/column',
					'attrs'       => array(),
					'innerBlocks' => array( array( 'blockName' => 'core/paragraph', 'innerHTML' => '<p>Droite</p>', 'attrs' => array() ) ),
				),
			),
		),
		array( BlockRenderer::class, 'render_list' )
	);
	wam_nl_assert( false !== strpos( $colonnes, '<!--[if mso]>' ), 'commentaire conditionnel MSO ouvrant' );
	wam_nl_assert( false !== strpos( $colonnes, '<![endif]-->' ), 'commentaire conditionnel fermé' );
	wam_nl_assert( false !== strpos( $colonnes, 'wam-nl-col' ), 'classe d’empilement mobile posée' );
	wam_nl_assert( false !== strpos( $colonnes, 'Gauche' ) && false !== strpos( $colonnes, 'Droite' ), 'les deux colonnes sont rendues' );
	wam_nl_assert( false !== strpos( $colonnes, 'display:inline-block' ), 'colonnes en inline-block hors Outlook' );

	echo "== Une colonne seule : pas de table de colonnes inutile ==\n";
	$une = Core::columns(
		array(
			'blockName'   => 'core/columns',
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/column',
					'innerBlocks' => array( array( 'blockName' => 'core/paragraph', 'innerHTML' => '<p>Seule</p>', 'attrs' => array() ) ),
				),
			),
		),
		array( BlockRenderer::class, 'render_list' )
	);
	wam_nl_assert( false === strpos( $une, 'if mso' ), 'aucune table fantôme pour une colonne unique' );
	wam_nl_assert( false !== strpos( $une, 'Seule' ), 'le contenu est rendu' );

	echo "== Aiguillage des blocs ==\n";
	wam_nl_assert_equals( '', BlockRenderer::render( array( 'blockName' => 'core/video' ) ), 'un bloc hors liste blanche ne rend rien, sans erreur' );
	wam_nl_assert_equals( '', BlockRenderer::render( array( 'blockName' => '' ) ), 'un bloc classique est ignoré' );
	wam_nl_assert( false !== strpos( BlockRenderer::render( array( 'blockName' => 'wam-nl/separator', 'attrs' => array() ) ), 'dotted' ), 'wam-nl/separator aiguillé' );

	echo "== Tailles d'image dédiées à l'e-mail ==\n";
	$tailles = wp_get_registered_image_subsizes();
	wam_nl_assert( isset( $tailles[ Images::SIZE_THUMB ] ), 'wam_nl_thumb enregistrée' );
	if ( isset( $tailles[ Images::SIZE_THUMB ] ) ) {
		wam_nl_assert_equals( 580, (int) $tailles[ Images::SIZE_THUMB ]['width'], 'vignette 580 de large' );
		wam_nl_assert_equals( 435, (int) $tailles[ Images::SIZE_THUMB ]['height'], 'vignette 435 de haut (4:3)' );
		wam_nl_assert( ! empty( $tailles[ Images::SIZE_THUMB ]['crop'] ), 'vignette recadrée (format uniforme, §7.6.4)' );
	}
	wam_nl_assert( isset( $tailles[ Images::SIZE_BANNER ] ), 'wam_nl_banner enregistrée' );

	echo "== Bannière retrouvée par son slug ==\n";
	$banniere = Brand::banner_id();
	wam_nl_assert( $banniere > 0, "la bannière est retrouvée (pièce jointe $banniere)" );
	if ( $banniere > 0 ) {
		$image = Images::email_image( $banniere, Images::SIZE_BANNER );
		wam_nl_assert( is_array( $image ), 'une image exploitable est renvoyée' );
		if ( $image ) {
			wam_nl_assert( false === strpos( $image['url'], '.avif' ), 'URL non AVIF' );
			wam_nl_assert( false === strpos( $image['url'], '.webp' ), 'URL non WebP' );
			wam_nl_assert( '' !== $image['alt'], 'un alt est disponible' );
		}
	}
	wam_nl_assert( null === Images::email_image( 0 ), 'identifiant 0 : rien' );
	wam_nl_assert( null === Images::email_image( 99999999 ), 'pièce jointe inexistante : rien, sans erreur' );

	echo "== Bloc Contenus : requête des stages à venir ==\n";
	// Le CPT s'appelle « stages » et non « stage », et date_stage est stocké en
	// Ymd : les deux pièges sont vérifiés ici.
	wam_nl_assert( post_type_exists( 'stages' ), 'le CPT « stages » existe bien sous ce nom' );
	wam_nl_assert_equals( 'Ymd', ContentMap::date_format( 'stages' ), 'format de stockage de date_stage détecté' );
	wam_nl_assert( ContentMap::supports_upcoming( 'stages' ), 'le tri « à venir » est possible pour les stages' );
	wam_nl_assert( ! ContentMap::supports_upcoming( 'cours' ), 'impossible pour les cours : aucun champ date' );

	$futur = wp_insert_post(
		array(
			'post_type'    => 'stages',
			'post_status'  => 'publish',
			'post_title'   => 'ZZTest stage futur',
			'post_content' => 'Un stage de test, dans longtemps.',
		)
	);
	$posts_temporaires[] = $futur;
	update_post_meta( $futur, 'date_stage', gmdate( 'Ymd', time() + 86400 * 30 ) );
	// Image à la une nécessaire : sans image, le renderer passe volontairement en
	// une seule colonne, et l'alternance zigzag ne s'applique pas.
	if ( $banniere > 0 ) {
		set_post_thumbnail( $futur, $banniere );
	}

	$passe = wp_insert_post(
		array(
			'post_type'    => 'stages',
			'post_status'  => 'publish',
			'post_title'   => 'ZZTest stage passé',
			'post_content' => 'Un stage de test, déjà passé.',
		)
	);
	$posts_temporaires[] = $passe;
	update_post_meta( $passe, 'date_stage', gmdate( 'Ymd', time() - 86400 * 30 ) );
	if ( $banniere > 0 ) {
		set_post_thumbnail( $passe, $banniere );
	}

	$a_venir = Posts::query( array( 'postType' => 'stages', 'mode' => 'auto', 'count' => 12, 'order' => 'upcoming' ) );
	wam_nl_assert( in_array( $futur, $a_venir, true ), 'le stage futur est retenu' );
	wam_nl_assert( ! in_array( $passe, $a_venir, true ), 'le stage passé est exclu' );

	$recents = Posts::query( array( 'postType' => 'stages', 'mode' => 'auto', 'count' => 12, 'order' => 'recent' ) );
	wam_nl_assert( in_array( $passe, $recents, true ), 'en tri « récents », le stage passé revient' );

	echo "== Bloc Contenus : mode manuel, ordre respecté ==\n";
	$manuel = Posts::query( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $passe, $futur ) ) );
	wam_nl_assert_equals( array( $passe, $futur ), $manuel, 'l’ordre choisi est conservé' );
	wam_nl_assert_equals( array(), Posts::query( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array() ) ), 'aucun choix : rien' );
	wam_nl_assert_equals( array(), Posts::query( array( 'postType' => 'inexistant' ) ), 'type de contenu inconnu : rien' );
	wam_nl_assert_equals( array(), Posts::query( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( 99999999 ) ) ), 'contenu inexistant écarté' );

	echo "== Bloc Contenus : plafond du nombre ==\n";
	wam_nl_assert( count( Posts::query( array( 'postType' => 'stages', 'count' => 1 ) ) ) <= 1, 'count = 1 respecté' );
	wam_nl_assert( count( Posts::query( array( 'postType' => 'stages', 'count' => 999 ) ) ) <= 12, 'count plafonné à 12' );

	echo "== Bloc Contenus : date formatée depuis la méta brute ==\n";
	$date_affichee = Posts::formatted_when( 'stages', $futur );
	wam_nl_assert( '' !== $date_affichee, "une date lisible est produite ($date_affichee)" );
	// Stage : JJ/MM/AA puis horaire (demande d'Antoine du 09/10). La date en
	// toutes lettres reste celle des autres types et de la liste de l'éditeur.
	wam_nl_assert( 1 === preg_match( '#^\d{2}/\d{2}/\d{2}( · \d{1,2}h(\d{2})?(-\d{1,2}h(\d{2})?)?)?$#u', $date_affichee ), 'date de stage en JJ/MM/AA, horaire facultatif' );
	wam_nl_assert( false === strpos( Posts::formatted_date( 'stages', $futur ), '/' ), 'formatted_date() garde la date en français littéral' );

	echo "== Bloc Contenus : rendu d'un item ==\n";
	$rendu = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur ), 'alternate' => true ) );
	wam_nl_assert( false !== strpos( $rendu, 'ZZTest stage futur' ), 'le titre du contenu est rendu' );
	wam_nl_assert( false !== strpos( $rendu, '2px dotted' ), 'séparateur pointillé avant l’item' );
	wam_nl_assert( false !== strpos( $rendu, '<h2' ), 'titre de l’item en h2' );
	wam_nl_assert( false !== strpos( $rendu, get_permalink( $futur ) ), 'lien vers le contenu' );
	wam_nl_assert( false === strpos( $rendu, '.avif' ), 'aucune image AVIF' );
	// 24px entre deux zones quelle qu'en soit la nature : chaque rangée porte
	// 12px en haut et 12px en bas (demande d'Antoine après relecture). Le
	// sous-titre/date ne fait plus exception, il suit le défaut comme le reste.
	wam_nl_assert( false !== strpos( $rendu, 'padding:12px 20px' ), 'espacement par défaut de 12px en haut et en bas' );
	wam_nl_assert( false === strpos( $rendu, 'padding:6px 20px 6px' ), 'plus aucun espacement sur mesure autour du sous-titre' );
	wam_nl_assert( false !== strpos( $rendu, 'padding:30px 20px' ), '30px autour du séparateur (un cran de moins que 40px, retour du 09/10)' );

	echo "== Bloc Contenus : données structurées pour « Convertir en blocs modifiables » ==\n";
	$resolu = Posts::resolve_items( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur, $passe ) ) );
	wam_nl_assert( isset( $resolu['items'], $resolu['defaultButtonText'] ), 'la réponse porte les deux clés attendues' );
	wam_nl_assert_equals( 2, count( $resolu['items'] ), 'les deux contenus demandés sont résolus' );
	wam_nl_assert_equals( array( $futur, $passe ), wp_list_pluck( $resolu['items'], 'id' ), 'l’ordre du mode manuel est respecté' );

	$item = $resolu['items'][0];
	wam_nl_assert_equals( 'ZZTest stage futur', $item['title'], 'titre présent' );
	wam_nl_assert_equals( get_permalink( $futur ), $item['link'], 'lien présent' );
	wam_nl_assert( '' !== $item['date'], 'date formatée présente' );
	wam_nl_assert( is_array( $item ) && array_key_exists( 'excerpt', $item ), 'clé extrait présente (même vide)' );
	wam_nl_assert( array_key_exists( 'image', $item ), 'clé image présente' );

	if ( null !== $item['image'] ) {
		wam_nl_assert( $item['image']['id'] > 0, 'image : identifiant de pièce jointe' );
		wam_nl_assert( false !== strpos( $item['image']['url'], 'http' ), 'image : URL exploitable par l’éditeur' );
		wam_nl_assert( '' !== $item['image']['alt'], 'image : un alt est toujours fourni (repli sur le titre)' );
		// L'image brute n'est volontairement pas le dérivé JPG de l'e-mail : ce
		// dérivé n'existe qu'au moment du rendu final (Core::image), produit
		// depuis le seul identifiant — pas depuis cette URL d'aperçu.
		wam_nl_assert( false === strpos( $item['image']['url'], 'wam_nl_banner' ), 'URL d’aperçu distincte du dérivé e-mail' );
	}

	wam_nl_assert_equals(
		Posts::default_button_text( 'stages' ),
		$resolu['defaultButtonText'],
		'le libellé de bouton par défaut est fourni, pour le repli côté JavaScript'
	);

	echo "== resolve_items() : aucun contenu, aucune erreur ==\n";
	$vide_resolu = Posts::resolve_items( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array() ) );
	wam_nl_assert_equals( array(), $vide_resolu['items'], 'liste vide plutôt qu’une erreur' );

	echo "== Route REST posts-resolve ==\n";
	$requete = new WP_REST_Request( 'POST', '/wam-nl/v1/posts-resolve' );
	$requete->set_body_params(
		array(
			'postType' => 'stages',
			'mode'     => 'manual',
			'postIds'  => array( $futur ),
		)
	);
	$reponse = RestApi::posts_resolve( $requete );
	wam_nl_assert( $reponse instanceof WP_REST_Response, 'une réponse REST est renvoyée' );
	$donnees = $reponse->get_data();
	wam_nl_assert_equals( 1, count( $donnees['items'] ), 'un seul contenu résolu via la route' );
	wam_nl_assert_equals( 'ZZTest stage futur', $donnees['items'][0]['title'], 'même donnée que l’appel direct' );

	echo "== Bloc Contenus : alternance zigzag et empilement mobile ==\n";
	$deux = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur, $passe ), 'alternate' => true ) );
	// L'alternance passe par dir="rtl" sur la rangée, pas par un échange des
	// colonnes dans le source : c'est ce qui garde l'image AU-DESSUS du texte
	// quand les colonnes s'empilent sur mobile.
	wam_nl_assert( false !== strpos( $deux, 'dir="rtl"' ), 'le second item inverse l’ordre visuel' );
	wam_nl_assert( false !== strpos( $deux, 'dir="ltr"' ), 'les colonnes rétablissent le sens de lecture' );

	$sans_zigzag = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur, $passe ), 'alternate' => false ) );
	wam_nl_assert( false === strpos( $sans_zigzag, 'dir="rtl"' ), 'alternance désactivable' );

	echo "== Bloc Contenus : rien à afficher, on le dit ==\n";
	$vide = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array() ) );
	wam_nl_assert( false !== strpos( $vide, 'Aucun contenu' ), 'message explicite plutôt qu’un trou silencieux' );

	echo "== Bloc Contenus : les bascules d'affichage agissent ==\n";
	$sans_bouton = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur ), 'showButton' => false ) );
	wam_nl_assert( false === strpos( $sans_bouton, 'border-radius:4px;border:' ), 'aucun bouton quand la bascule est fermée' );
	$avec_bouton = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur ), 'showButton' => true, 'buttonText' => 'Je réserve' ) );
	wam_nl_assert( false !== strpos( $avec_bouton, 'Je réserve' ), 'libellé de bouton personnalisé' );
	$avec_date = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur ), 'showDate' => true ) );
	$sans_date = Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur ), 'showDate' => false ) );
	wam_nl_assert( '' !== $date_affichee && false !== strpos( $avec_date, $date_affichee ), 'date affichée quand la bascule est ouverte' );
	wam_nl_assert( '' !== $date_affichee && false === strpos( $sans_date, $date_affichee ), 'date masquée sur demande' );

	echo "== Libellé de bouton par défaut adapté au type ==\n";
	wam_nl_assert( false !== strpos( Posts::default_button_text( 'stages' ), 'stage' ), 'stages : « Voir le stage »' );
	wam_nl_assert( false !== strpos( Posts::default_button_text( 'cours' ), 'cours' ), 'cours : « Voir le cours »' );
	wam_nl_assert( false !== strpos( Posts::default_button_text( 'post' ), 'article' ), 'articles : « Lire l’article complet »' );

	echo "== Thème d'e-mail injecté dans l'éditeur ==\n";
	$reglages = EmailTheme::settings();
	wam_nl_assert_equals( '660px', $reglages['layout']['contentSize'], 'canevas à 660 px, comme l’e-mail' );
	wam_nl_assert_equals( array( 'px' ), $reglages['spacing']['units'], 'espacements en pixels seulement' );
	wam_nl_assert( false === $reglages['color']['custom'], 'pas de couleur libre (une couleur hors charte casserait le rendu)' );
	wam_nl_assert( false === $reglages['color']['defaultGradients'], 'aucun dégradé (non rendu par les clients lourds)' );
	wam_nl_assert( false === $reglages['typography']['dropCap'], 'pas de lettrine' );
	wam_nl_assert_equals( 1, count( $reglages['typography']['fontFamilies']['theme'] ), 'une seule police proposée' );
	wam_nl_assert( false !== strpos( $reglages['typography']['fontFamilies']['theme'][0]['fontFamily'], 'Arial' ), 'et c’est Arial' );
	wam_nl_assert( ! empty( $reglages['color']['palette']['theme'] ), 'la palette WAM est proposée' );

	echo "== Peinture du canevas ==\n";
	$canevas = EmailTheme::canvas_css();
	wam_nl_assert( false !== strpos( $canevas, $c['background'] ), 'fond sombre dans le canevas' );
	wam_nl_assert( false !== strpos( $canevas, $c['accent'] ), 'titres en jaune dans le canevas' );
	wam_nl_assert( false !== strpos( $canevas, 'Arial' ), 'Arial dans le canevas' );

	echo "== Blocs autorisés : liste blanche sur le seul CPT newsletter ==\n";
	wam_nl_assert( in_array( 'wam-nl/posts', EditorSetup::ALLOWED_BLOCKS, true ), 'le bloc Contenus est autorisé' );
	wam_nl_assert( in_array( 'core/paragraph', EditorSetup::ALLOWED_BLOCKS, true ), 'le paragraphe est autorisé' );
	wam_nl_assert( ! in_array( 'core/video', EditorSetup::ALLOWED_BLOCKS, true ), 'la vidéo ne l’est pas (aucun client mail ne la lit)' );
	wam_nl_assert( ! in_array( 'core/gallery', EditorSetup::ALLOWED_BLOCKS, true ), 'la galerie ne l’est pas' );
	wam_nl_assert( ! in_array( 'core/html', EditorSetup::ALLOWED_BLOCKS, true ), 'le HTML libre ne l’est pas' );

	$faux_contexte = (object) array( 'post' => (object) array( 'post_type' => 'post' ) );
	wam_nl_assert_equals( true, EditorSetup::allowed_blocks( true, $faux_contexte ), 'un article garde tous ses blocs' );

	$contexte_nl = new stdClass();
	$contexte_nl->post = get_post( $futur );
	wam_nl_assert_equals( true, EditorSetup::allowed_blocks( true, $contexte_nl ), 'un stage garde tous ses blocs' );

	echo "== Gabarit par défaut d'une newsletter neuve ==\n";
	$gabarit = EditorSetup::default_blocks();
	wam_nl_assert( false !== strpos( $gabarit, 'wp:wam-nl/header' ), 'entête présent' );
	wam_nl_assert( false !== strpos( $gabarit, 'wp:wam-nl/footer' ), 'pied de page présent' );
	wam_nl_assert( false !== strpos( $gabarit, 'wp:wam-nl/posts' ), 'bloc Contenus présent' );
	wam_nl_assert( false !== strpos( $gabarit, 'wp:paragraph' ), 'paragraphe d’intro présent' );
	wam_nl_assert( 2 === substr_count( $gabarit, '"lock":{"move":true,"remove":true}' ), 'entête et pied verrouillés en position' );

	$blocs_gabarit = parse_blocks( $gabarit );
	wam_nl_assert( count( $blocs_gabarit ) >= 4, 'le gabarit s’analyse en 4 blocs au moins' );
	wam_nl_assert_equals( 'wam-nl/header', $blocs_gabarit[0]['blockName'], 'l’entête est en premier' );
	$dernier = end( $blocs_gabarit );
	wam_nl_assert_equals( 'wam-nl/footer', $dernier['blockName'], 'le pied est en dernier' );

	echo "== Les blocs du plugin sont enregistrés ==\n";
	Blocks::register();
	$registre = WP_Block_Type_Registry::get_instance();
	foreach ( Blocks::names() as $nom ) {
		$type = $registre->get_registered( $nom );
		wam_nl_assert( null !== $type, "$nom enregistré" );
		if ( $type ) {
			wam_nl_assert( is_callable( $type->render_callback ), "  $nom a un rendu serveur" );
		}
	}

	echo "== Compositions prêtes à insérer ==\n";
	// Elles servent à insérer une section déjà réglée au lieu d'assembler les
	// blocs un par un : si l'une d'elles contenait un bloc hors liste blanche,
	// l'insérer donnerait un bloc invalide dans l'éditeur.
	\WamNewsletter\Blocks\Patterns::register();
	$registre_motifs = WP_Block_Patterns_Registry::get_instance();

	foreach ( \WamNewsletter\Blocks\Patterns::names() as $nom_motif ) {
		$motif = $registre_motifs->get_registered( $nom_motif );
		$court = str_replace( 'wam-newsletter/', '', $nom_motif );
		wam_nl_assert( null !== $motif, "motif $court enregistré" );
		if ( ! $motif ) {
			continue;
		}

		wam_nl_assert_equals(
			array( NewsletterPostType::POST_TYPE ),
			$motif['postTypes'] ?? array(),
			"  $court réservé aux newsletters"
		);

		$noms_blocs = array();
		$aplatir    = static function ( array $liste ) use ( &$aplatir, &$noms_blocs ) {
			foreach ( $liste as $bloc ) {
				if ( ! empty( $bloc['blockName'] ) ) {
					$noms_blocs[] = (string) $bloc['blockName'];
				}
				if ( ! empty( $bloc['innerBlocks'] ) ) {
					$aplatir( (array) $bloc['innerBlocks'] );
				}
			}
		};
		$aplatir( parse_blocks( (string) $motif['content'] ) );

		$hors_liste = array_values( array_diff( array_unique( $noms_blocs ), EditorSetup::ALLOWED_BLOCKS ) );
		wam_nl_assert_equals( array(), $hors_liste, "  $court n’utilise que des blocs autorisés" );
		wam_nl_assert( '' !== trim( BlockRenderer::preview( (string) $motif['content'] ) ), "  $court se rend sans erreur" );
	}

	echo "== Seuls nos motifs sont proposés dans l'éditeur de newsletter ==\n";
	// Les motifs du cœur parlent de pages (couvertures, grilles de requête) et
	// proposeraient des blocs qu'aucun client mail ne sait afficher.
	$reglages_faux = array(
		'__experimentalBlockPatterns'          => array(
			array( 'name' => 'core/two-columns-of-text' ),
			array( 'name' => 'wam-newsletter/appel-a-action' ),
			array( 'name' => 'wamv1/un-motif-du-theme' ),
		),
		'__experimentalBlockPatternCategories' => array(
			array( 'name' => 'featured' ),
			array( 'name' => \WamNewsletter\Blocks\Patterns::CATEGORY ),
		),
	);

	$nl_contexte = wp_insert_post(
		array(
			'post_type'   => NewsletterPostType::POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => 'ZZTest contexte motifs',
		)
	);
	$posts_temporaires[] = $nl_contexte;

	$contexte_newsletter       = new stdClass();
	$contexte_newsletter->post = get_post( $nl_contexte );
	wam_nl_assert(
		EditorSetup::is_newsletter_editor( $contexte_newsletter ),
		'le contexte est bien reconnu comme un éditeur de newsletter'
	);
	$filtre = EditorSetup::editor_settings( $reglages_faux, $contexte_newsletter );

	wam_nl_assert_equals(
		array( 'wam-newsletter/appel-a-action' ),
		wp_list_pluck( $filtre['__experimentalBlockPatterns'], 'name' ),
		'seuls les motifs du plugin subsistent'
	);
	wam_nl_assert_equals(
		array( \WamNewsletter\Blocks\Patterns::CATEGORY ),
		wp_list_pluck( $filtre['__experimentalBlockPatternCategories'], 'name' ),
		'seule la catégorie du plugin subsiste'
	);

	$contexte_stage       = new stdClass();
	$contexte_stage->post = get_post( $futur );
	$intacts              = EditorSetup::editor_settings( $reglages_faux, $contexte_stage );
	wam_nl_assert_equals( 3, count( $intacts['__experimentalBlockPatterns'] ), 'les autres types de contenu gardent tous leurs motifs' );
	wam_nl_assert( ! isset( $intacts['styles'] ), 'et ne reçoivent pas la peinture du canevas d’e-mail' );

	echo "== L'aperçu d'un bloc ne pose aucun lien actif ==\n";
	$apercu_pied = Blocks::definitions()['wam-nl/footer']['render']( array() );
	wam_nl_assert( false === strpos( $apercu_pied, Placeholders::UNSUB_URL ), 'le marqueur est résolu dans l’aperçu' );
	wam_nl_assert( false !== strpos( $apercu_pied, 'href="#"' ), 'et remplacé par un lien inerte' );

	echo "== Document e-mail complet ==\n";
	$newsletter = wp_insert_post(
		array(
			'post_type'    => NewsletterPostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => 'ZZTest newsletter',
			'post_content' => EditorSetup::default_blocks(),
		)
	);
	$posts_temporaires[] = $newsletter;
	update_post_meta( $newsletter, '_wam_nl_subject', 'Les nouvelles du studio' );
	update_post_meta( $newsletter, '_wam_nl_preheader', 'Stages, cours et nouveautés' );

	$html = EmailRenderer::render( $newsletter );
	wam_nl_assert( '' !== $html, 'un document est produit' );
	wam_nl_assert( 0 === strpos( $html, '<!DOCTYPE html>' ), 'document complet avec doctype' );
	wam_nl_assert( false !== strpos( $html, '<meta name="color-scheme" content="dark">' ), 'mode sombre déclaré (§7.6.6)' );
	wam_nl_assert( false !== strpos( $html, 'supported-color-schemes' ), 'schémas supportés déclarés' );
	wam_nl_assert( false !== strpos( $html, 'max-width:660px' ), 'conteneur borné à 660 px' );
	wam_nl_assert( false !== strpos( $html, 'Les nouvelles du studio' ), 'objet dans le titre du document' );
	wam_nl_assert( false !== strpos( $html, 'Stages, cours et nouveautés' ), 'texte d’aperçu présent' );
	wam_nl_assert( false !== strpos( $html, 'Voir dans le navigateur' ), 'lien « voir dans le navigateur » (§7.6.4)' );
	wam_nl_assert( false !== strpos( $html, 'x-apple-disable-message-reformatting' ), 'reformatage Apple désactivé' );

	echo "== Preheader : masqué, en tout premier ==\n";
	$position_preheader = strpos( $html, 'Stages, cours et nouveautés' );
	$position_contenu   = strpos( $html, 'Voir dans le navigateur' );
	wam_nl_assert( $position_preheader < $position_contenu, 'le preheader précède le contenu' );
	wam_nl_assert( false !== strpos( $html, 'display:none;max-height:0' ), 'preheader masqué' );

	echo "== Le seul <style> ne contient que des media queries (§7.4) ==\n";
	if ( preg_match( '#<style[^>]*>(.*?)</style>#s', $html, $m ) ) {
		$css = $m[1];
		wam_nl_assert( false !== strpos( $css, '@media' ), 'une media query est présente' );
		// Toute règle hors media query serait ignorée par Gmail, qui retire les
		// styles non pris en charge : on vérifie qu'il n'y en a pas.
		$hors_media = preg_replace( '#@media[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}#s', '', $css );
		// Les commentaires ne sont pas des règles : un client qui les ignore
		// n'en est pas affecté.
		$hors_media = preg_replace( '#/\*.*?\*/#s', '', (string) $hors_media );
		// Seule exception admise : neutraliser les liens que Gmail et Apple Mail
		// fabriquent eux-mêmes (adresse, date) et qui n'ont pas de style en ligne.
		$hors_media = preg_replace( '#(a\[x-apple-data-detectors\]|u \+ \#body a)\s*\{[^}]*\}#s', '', (string) $hors_media );
		wam_nl_assert_equals( '', trim( (string) $hors_media ), 'aucune règle en dehors des media queries, hors neutralisation des liens automatiques' );
		wam_nl_assert( false !== strpos( $css, 'a[x-apple-data-detectors]' ), 'les liens automatiques d’Apple Mail reprennent la couleur du texte' );
		wam_nl_assert( false !== strpos( $css, 'u + #body a' ), 'les liens automatiques de Gmail aussi' );
		wam_nl_assert( false !== strpos( $html, '<body id="body"' ), 'le corps porte l’identifiant visé par la règle Gmail' );
		wam_nl_assert( false !== strpos( $css, 'wam-nl-col' ), 'l’empilement mobile des colonnes est prévu' );
	} else {
		wam_nl_assert( false, 'un bloc <style> est attendu' );
	}

	echo "== Aucune police externe, aucun AVIF (§7.6.4, §7.6.5) ==\n";
	wam_nl_assert( false === strpos( $html, '<link' ), 'aucune feuille de style externe' );
	wam_nl_assert( false === stripos( $html, 'fonts.googleapis' ), 'aucune police Google' );
	wam_nl_assert( false === strpos( $html, '.avif' ), 'aucune image AVIF dans le HTML final' );
	wam_nl_assert( false === strpos( $html, '.webp' ), 'aucune image WebP dans le HTML final' );
	wam_nl_assert( false === strpos( $html, 'Outfit' ), 'aucune police du thème' );

	echo "== Tables partout, border-collapse partout ==\n";
	$nb_tables = substr_count( $html, '<table' );
	wam_nl_assert( $nb_tables >= 4, "mise en page en tables ($nb_tables tables)" );
	wam_nl_assert_equals(
		$nb_tables,
		substr_count( $html, 'border-collapse:collapse' ),
		'chaque table porte border-collapse (sinon Outlook ajoute des espaces)'
	);
	wam_nl_assert_equals( $nb_tables, substr_count( $html, 'role="presentation"' ), 'chaque table est annoncée comme présentation' );

	echo "== Marqueurs résolus ou non selon le mode ==\n";
	wam_nl_assert( false !== strpos( $html, Placeholders::UNSUB_URL ), 'le rendu brut garde les marqueurs (résolus par destinataire)' );
	$apercu = EmailRenderer::render( $newsletter, array( 'preview' => true ) );
	wam_nl_assert( false === strpos( $apercu, Placeholders::UNSUB_URL ), 'l’aperçu les résout' );
	wam_nl_assert( false === strpos( $apercu, '{{wam_nl_' ), 'aucun marqueur résiduel dans l’aperçu' );

	echo "== Version texte brut (AltBody) ==\n";
	$texte = EmailRenderer::plain_text( $html );
	wam_nl_assert( '' !== $texte, 'une version texte est produite' );
	wam_nl_assert( false === strpos( $texte, '<' ), 'aucune balise' );
	wam_nl_assert( false === strpos( $texte, 'Stages, cours et nouveautés' ), 'le preheader n’est pas répété en texte' );
	wam_nl_assert( false !== strpos( $texte, 'ZZTest stage futur' ), 'le contenu est présent' );
	wam_nl_assert( false === strpos( $texte, '  ' ), 'pas de double espace' );
	wam_nl_assert( false === strpos( $texte, "\n\n\n" ), 'pas de triple saut de ligne' );

	echo "== Texte brut : les liens gardent leur destination ==\n";
	$avec_lien = EmailRenderer::plain_text( '<p>Voir <a href="https://exemple.test/e">le stage</a></p>' );
	wam_nl_assert( false !== strpos( $avec_lien, 'le stage (https://exemple.test/e)' ), 'libellé suivi de l’URL' );
	$lien_nu = EmailRenderer::plain_text( '<a href="https://exemple.test/f">https://exemple.test/f</a>' );
	wam_nl_assert_equals( 'https://exemple.test/f', $lien_nu, 'une URL en libellé n’est pas répétée' );

	echo "== Personnalisation et nettoyage de la ponctuation (§7.4) ==\n";
	$avec_prenom = Placeholders::replace( 'Bonjour {prenom}, ça va ?', Placeholders::person_values( array( 'first_name' => 'Ada', 'email' => 'a@b.fr' ) ) );
	wam_nl_assert_equals( 'Bonjour Ada, ça va ?', $avec_prenom, 'le prénom est inséré' );

	$sans_prenom = Placeholders::tidy(
		Placeholders::replace( 'Bonjour {prenom}, ça va ?', Placeholders::person_values( array( 'first_name' => '', 'email' => 'a@b.fr' ) ) )
	);
	wam_nl_assert_equals( 'Bonjour, ça va ?', $sans_prenom, 'sans prénom : « Bonjour, » et non « Bonjour , »' );

	echo "== Aucune alerte PHP sur des attributs absents ==\n";
	// Régression du 08/10/2026 : `in_array( $opts['align'] ?? 'left', … ) ? $opts['align'] : 'left'`
	// émettait « Undefined array key: align » quand la clé manquait — « left »
	// étant dans la liste autorisée, c'est la branche vraie qui relisait la clé.
	// Le défaut ne cassait rien à l'écran mais remplissait le debug.log à chaque
	// rendu, ce qui noie les vraies erreurs.
	$alertes = array();
	set_error_handler(
		static function ( $niveau, $message ) use ( &$alertes ) {
			$alertes[] = $message;
			return true;
		},
		E_ALL
	);

	Html::button( array( 'texte' => 'Sans align' ) );
	Html::button( array() );
	Html::row( 'contenu' );
	Html::text_style();
	Custom::button( array() );
	Custom::header( array() );
	Custom::footer( array() );
	Custom::separator( array() );
	Custom::spacer( array() );
	Core::paragraph( array( 'blockName' => 'core/paragraph', 'innerHTML' => '<p>Minimal</p>' ) );
	Core::heading( array( 'blockName' => 'core/heading', 'innerHTML' => '<h2>Minimal</h2>' ) );
	Core::image( array( 'blockName' => 'core/image' ) );
	Core::list_block( array( 'blockName' => 'core/list', 'innerHTML' => '<ul><li>x</li></ul>' ) );
	Posts::render( array( 'postType' => 'stages', 'mode' => 'manual', 'postIds' => array( $futur ) ) );
	BlockRenderer::render_list( parse_blocks( EditorSetup::default_blocks() ) );

	restore_error_handler();
	wam_nl_assert_equals( array(), $alertes, 'aucune alerte PHP sur des attributs de bloc absents' );

	echo "== Les blocs ne sont jamais enregistrés deux fois ==\n";
	// Un second appel à register() émettait un avis « déjà enregistré » par bloc.
	$alertes = array();
	set_error_handler(
		static function ( $niveau, $message ) use ( &$alertes ) {
			$alertes[] = $message;
			return true;
		},
		E_ALL
	);
	Blocks::register();
	\WamNewsletter\Form\Form::register_block();
	restore_error_handler();
	wam_nl_assert_equals( array(), $alertes, 'réenregistrer les blocs est silencieux' );

	echo "== Messages d'administration : un transient absent n'est pas un tableau ==\n";
	// `(array) false` donne `array( false )` et non un tableau vide : le premier
	// message d'une série arrivait avec un `false` en tête, qui provoquait un
	// « Trying to access array offset on value of type bool » à l'affichage.
	// Les messages sont rangés dans un transient propre à l'utilisateur·rice :
	// sans utilisateur courant, rien n'est empilé. En WP-CLI il n'y en a aucun.
	$utilisateur_avant = get_current_user_id();
	$admins            = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( $admins ) {
		wp_set_current_user( (int) $admins[0] );
	}

	$alertes = array();
	set_error_handler(
		static function ( $niveau, $message ) use ( &$alertes ) {
			$alertes[] = $message;
			return true;
		},
		E_ALL
	);
	\WamNewsletter\Admin\Notices::success( 'Message de test du harnais' );
	ob_start();
	\WamNewsletter\Admin\Notices::render();
	$rendu_notice = (string) ob_get_clean();
	restore_error_handler();

	wam_nl_assert_equals( array(), $alertes, 'aucune alerte lors de l’empilement puis du rendu d’un message' );
	wam_nl_assert( false !== strpos( $rendu_notice, 'Message de test du harnais' ), 'le message est bien affiché' );
	ob_start();
	\WamNewsletter\Admin\Notices::render();
	wam_nl_assert_equals( '', trim( (string) ob_get_clean() ), 'un message n’est affiché qu’une fois' );
	wp_set_current_user( $utilisateur_avant );

	echo "== Un document sans gabarit reste valide ==\n";
	$minimal = EmailRenderer::document( '<p>Test</p>', array( 'title' => 'T' ) );
	wam_nl_assert( false !== strpos( $minimal, '<!DOCTYPE html>' ), 'document bien formé' );
	wam_nl_assert_equals( '', EmailRenderer::render( 0 ), 'identifiant 0 : aucun rendu' );
	wam_nl_assert_equals( '', EmailRenderer::render( $futur ), 'un contenu qui n’est pas une newsletter : aucun rendu' );

} finally {
	foreach ( $posts_temporaires as $id ) {
		if ( $id && ! is_wp_error( $id ) ) {
			wp_delete_post( (int) $id, true );
		}
	}
}

echo "== État restauré ==\n";
$restes = get_posts(
	array(
		'post_type'        => array( 'stages', NewsletterPostType::POST_TYPE ),
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		's'                => 'ZZTest',
		'suppress_filters' => true,
	)
);
wam_nl_assert_equals( array(), $restes, 'aucun contenu de test ne subsiste' );

wam_nl_test_report();
