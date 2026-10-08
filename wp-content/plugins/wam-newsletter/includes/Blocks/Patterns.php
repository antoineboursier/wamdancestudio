<?php
namespace WamNewsletter\Blocks;

use WamNewsletter\Editor\NewsletterPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Compositions prêtes à insérer dans une newsletter.
 *
 * Pensées pour des personnes qui débutent sous WordPress : plutôt que
 * d'assembler un titre, un bloc Contenus et un bouton une pièce à la fois, on
 * insère une section entière déjà réglée, puis on remplace le texte. C'est le
 * même geste que les « modèles » de MailPoet, mais à l'échelle d'une section
 * plutôt que de l'e-mail entier — donc combinable.
 *
 * Les motifs du cœur de WordPress et du thème sont écartés de cet éditeur
 * (EditorSetup) : ils parlent de pages, pas d'e-mails, et proposeraient des
 * blocs qui ne savent pas se rendre dans une boîte de réception.
 */
class Patterns {

	const CATEGORY = 'wam-newsletter';

	public static function register_hooks(): void {
		// Priorité 11 : après l'enregistrement des blocs, dont les motifs
		// dépendent.
		add_action( 'init', array( self::class, 'register' ), 11 );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_pattern' ) || ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Newsletter WAM', 'wam-newsletter' ),
				'description' => __( 'Sections prêtes à insérer dans une newsletter.', 'wam-newsletter' ),
			)
		);

		foreach ( self::definitions() as $slug => $def ) {
			register_block_pattern(
				'wam-newsletter/' . $slug,
				array(
					'title'      => $def['title'],
					'description' => $def['description'] ?? '',
					'content'    => $def['content'],
					'categories' => array( self::CATEGORY ),
					'postTypes'  => array( NewsletterPostType::POST_TYPE ),
					'keywords'   => $def['keywords'] ?? array(),
				)
			);
		}
	}

	/** Noms complets des motifs du plugin. */
	public static function names(): array {
		return array_map(
			static function ( $slug ) {
				return 'wam-newsletter/' . $slug;
			},
			array_keys( self::definitions() )
		);
	}

	/** @return array<string,array<string,mixed>> */
	public static function definitions(): array {
		$cours = esc_url( home_url( '/cours-collectifs/' ) );

		return array(
			'stages-a-venir' => array(
				'title'       => __( 'Les prochains stages', 'wam-newsletter' ),
				'description' => __( 'Un titre et les trois prochains stages, mis à jour automatiquement à chaque envoi.', 'wam-newsletter' ),
				'keywords'    => array( 'stage', 'contenus' ),
				'content'     => '<!-- wp:heading {"level":2} -->' . "\n"
					. '<h2>' . esc_html__( 'Les prochains stages', 'wam-newsletter' ) . '</h2>' . "\n"
					. '<!-- /wp:heading -->' . "\n\n"
					. '<!-- wp:wam-nl/posts {"postType":"stages","mode":"auto","count":3,"order":"upcoming","buttonText":"'
					. esc_attr__( 'Je réserve ma place', 'wam-newsletter' ) . '"} /-->',
			),

			'un-stage-mis-en-avant' => array(
				'title'       => __( 'Un seul stage mis en avant', 'wam-newsletter' ),
				'description' => __( 'Le prochain stage, en grand, avec son bouton de réservation.', 'wam-newsletter' ),
				'keywords'    => array( 'stage', 'une' ),
				'content'     => '<!-- wp:wam-nl/posts {"postType":"stages","mode":"auto","count":1,"order":"upcoming","alternate":false,"buttonText":"'
					. esc_attr__( 'Je réserve ma place', 'wam-newsletter' ) . '"} /-->',
			),

			'derniers-articles' => array(
				'title'       => __( 'Les derniers articles du blog', 'wam-newsletter' ),
				'description' => __( 'Les trois articles les plus récents.', 'wam-newsletter' ),
				'keywords'    => array( 'blog', 'article', 'actualité' ),
				'content'     => '<!-- wp:heading {"level":2} -->' . "\n"
					. '<h2>' . esc_html__( 'À lire sur le blog', 'wam-newsletter' ) . '</h2>' . "\n"
					. '<!-- /wp:heading -->' . "\n\n"
					. '<!-- wp:wam-nl/posts {"postType":"post","mode":"auto","count":3,"order":"recent"} /-->',
			),

			'appel-a-action' => array(
				'title'       => __( 'Appel à l’action', 'wam-newsletter' ),
				'description' => __( 'Un titre, une phrase et un bouton centré.', 'wam-newsletter' ),
				'keywords'    => array( 'bouton', 'inscription' ),
				'content'     => '<!-- wp:wam-nl/separator /-->' . "\n\n"
					. '<!-- wp:heading {"level":2} -->' . "\n"
					. '<h2>' . esc_html__( 'Envie de nous rejoindre ?', 'wam-newsletter' ) . '</h2>' . "\n"
					. '<!-- /wp:heading -->' . "\n\n"
					. '<!-- wp:paragraph -->' . "\n"
					. '<p>' . esc_html__( 'Il reste des places dans plusieurs cours. Viens essayer, le premier cours est offert.', 'wam-newsletter' ) . '</p>' . "\n"
					. '<!-- /wp:paragraph -->' . "\n\n"
					. '<!-- wp:wam-nl/button {"text":"' . esc_attr__( 'Voir tous les cours', 'wam-newsletter' )
					. '","url":"' . $cours . '","align":"center"} /-->',
			),

			'texte-et-image' => array(
				'title'       => __( 'Texte et image côte à côte', 'wam-newsletter' ),
				'description' => __( 'Deux colonnes qui s’empilent sur téléphone.', 'wam-newsletter' ),
				'keywords'    => array( 'colonnes', 'image' ),
				'content'     => '<!-- wp:columns -->' . "\n"
					. '<div class="wp-block-columns">' . "\n"
					. '<!-- wp:column -->' . "\n"
					. '<div class="wp-block-column">' . "\n"
					. '<!-- wp:paragraph -->' . "\n"
					. '<p>' . esc_html__( 'Racontez ici une nouveauté du studio, en quelques phrases.', 'wam-newsletter' ) . '</p>' . "\n"
					. '<!-- /wp:paragraph -->' . "\n"
					. '</div>' . "\n"
					. '<!-- /wp:column -->' . "\n\n"
					. '<!-- wp:column -->' . "\n"
					. '<div class="wp-block-column">' . "\n"
					. '<!-- wp:image -->' . "\n"
					. '<figure class="wp-block-image"></figure>' . "\n"
					. '<!-- /wp:image -->' . "\n"
					. '</div>' . "\n"
					. '<!-- /wp:column -->' . "\n"
					. '</div>' . "\n"
					. '<!-- /wp:columns -->',
			),

			'mot-de-la-directrice' => array(
				'title'       => __( 'Le mot d’intro', 'wam-newsletter' ),
				'description' => __( 'Un paragraphe d’ouverture avec le prénom de la personne.', 'wam-newsletter' ),
				'keywords'    => array( 'intro', 'bonjour', 'prénom' ),
				'content'     => '<!-- wp:paragraph -->' . "\n"
					. '<p>' . esc_html__( 'Bonjour {prenom}, voici les nouvelles du studio pour ce trimestre.', 'wam-newsletter' ) . '</p>' . "\n"
					. '<!-- /wp:paragraph -->',
			),
		);
	}
}
