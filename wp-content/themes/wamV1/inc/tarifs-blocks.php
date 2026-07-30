<?php
/**
 * Blocs Gutenberg de la page Tarifs.
 *
 * Blocs **dynamiques** : le contenu de la page ne stocke qu'un commentaire de
 * bloc, le HTML est produit à l'affichage par les template-parts
 * `template-parts/tarifs-*.php`. Les montants restent donc justes sans réédition,
 * y compris après un `ddev pull` — c'est ce qui permet d'avoir à la fois une page
 * modifiable dans Gutenberg et des tarifs qui ne se périment pas.
 *
 * Les données viennent de inc/tarifs.php (WooCommerce, options du plugin, ACF).
 *
 * @package wamv1
 */

add_action( 'init', 'wamv1_register_tarifs_blocks' );

if ( ! function_exists( 'wamv1_register_tarifs_blocks' ) ) {
    function wamv1_register_tarifs_blocks(): void {
        $dir = get_template_directory();
        $uri = get_template_directory_uri();

        wp_register_script(
            'wam-tarifs-editor',
            $uri . '/blocks/tarifs/index.js',
            [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ],
            filemtime( $dir . '/blocks/tarifs/index.js' ),
            true
        );

        /* L'aperçu dans l'éditeur réutilise le CSS du front : ce que voit
           l'éditrice correspond à ce que verra le public. */
        wp_register_style(
            'wam-tarifs-editor-style',
            $uri . '/assets/css/tarifs.css',
            [],
            filemtime( $dir . '/assets/css/tarifs.css' )
        );

        $blocs = [
            'grille' => [
                'attributes' => [
                    'max' => [ 'type' => 'number', 'default' => 5 ],
                ],
            ],
            'prestations' => [
                'attributes' => [
                    'stages'      => [ 'type' => 'boolean', 'default' => true ],
                    'particulier' => [ 'type' => 'boolean', 'default' => true ],
                    'evjf'        => [ 'type' => 'boolean', 'default' => true ],
                    'location'    => [ 'type' => 'boolean', 'default' => true ],
                    'team'        => [ 'type' => 'boolean', 'default' => true ],
                ],
            ],
            'formules' => [ 'attributes' => [] ],
            'faq'      => [ 'attributes' => [] ],
        ];

        foreach ( $blocs as $nom => $config ) {
            register_block_type( 'wam/tarifs-' . $nom, [
                'editor_script'   => 'wam-tarifs-editor',
                'editor_style'    => 'wam-tarifs-editor-style',
                'attributes'      => $config['attributes'],
                'render_callback' => 'wamv1_render_tarifs_block_' . $nom,
            ] );
        }
    }
}

/**
 * Fabrique de rendu : délègue au template-part correspondant.
 *
 * `wamv1_tarifs_prix_cours()` sert de garde — si inc/tarifs.php n'était pas
 * chargé, on préfère ne rien afficher plutôt que de provoquer une erreur fatale.
 */
if ( ! function_exists( 'wamv1_render_tarifs_block' ) ) {
    function wamv1_render_tarifs_block( string $part, array $attributes = [] ): string {
        if ( ! function_exists( 'wamv1_tarifs_prix_cours' ) ) {
            return '';
        }

        ob_start();
        get_template_part( 'template-parts/tarifs', $part, $attributes );
        return (string) ob_get_clean();
    }
}

if ( ! function_exists( 'wamv1_render_tarifs_block_grille' ) ) {
    function wamv1_render_tarifs_block_grille( $attributes ): string {
        return wamv1_render_tarifs_block( 'grille', (array) $attributes );
    }
}

if ( ! function_exists( 'wamv1_render_tarifs_block_prestations' ) ) {
    function wamv1_render_tarifs_block_prestations( $attributes ): string {
        return wamv1_render_tarifs_block( 'prestations', (array) $attributes );
    }
}

if ( ! function_exists( 'wamv1_render_tarifs_block_formules' ) ) {
    function wamv1_render_tarifs_block_formules( $attributes ): string {
        return wamv1_render_tarifs_block( 'formules', (array) $attributes );
    }
}

if ( ! function_exists( 'wamv1_render_tarifs_block_faq' ) ) {
    function wamv1_render_tarifs_block_faq( $attributes ): string {
        return wamv1_render_tarifs_block( 'faq', (array) $attributes );
    }
}

/**
 * Charge le CSS des tarifs dès qu'un bloc tarifs est présent sur la page.
 *
 * Remplace la condition `is_page('tarifs')` : les blocs peuvent désormais être
 * insérés n'importe où (page tarifs, page d'accueil, article…) et le style suit.
 */
add_action( 'wp_enqueue_scripts', 'wamv1_enqueue_tarifs_css', 20 );

if ( ! function_exists( 'wamv1_enqueue_tarifs_css' ) ) {
    function wamv1_enqueue_tarifs_css(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        if ( ! wamv1_has_tarifs_block() ) {
            return;
        }

        wp_enqueue_style(
            'wamv1-tarifs',
            get_template_directory_uri() . '/assets/css/tarifs.css',
            [ 'wamv1-layout' ],
            filemtime( get_template_directory() . '/assets/css/tarifs.css' )
        );
    }
}

/**
 * Le contenu courant contient-il au moins un bloc tarifs ?
 *
 * @param string $bloc Suffixe de bloc à chercher, ou '' pour n'importe lequel.
 */
if ( ! function_exists( 'wamv1_has_tarifs_block' ) ) {
    function wamv1_has_tarifs_block( string $bloc = '' ): bool {
        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return false;
        }

        $content = (string) get_post_field( 'post_content', $post_id );
        if ( '' === $content ) {
            return false;
        }

        return has_block( 'wam/tarifs-' . ( $bloc ?: 'grille' ), $content )
            || ( '' === $bloc && (
                has_block( 'wam/tarifs-prestations', $content )
                || has_block( 'wam/tarifs-formules', $content )
                || has_block( 'wam/tarifs-faq', $content )
            ) );
    }
}
