<?php
/**
 * WAM — Intégration WooCommerce
 *
 * Hooks et filtres pour adapter WC au thème wamV1 :
 *   A. Désactiver les styles CSS natifs WC (on gère via shop.css)
 *   B. Retirer les endpoints inutiles du menu Mon compte
 *   C. Rediriger /boutique → /cours-collectifs
 *   D. Helper : récupérer l'ID du produit WC lié à un CPT
 *   E. Sync stock WC depuis le champ ACF complete_cours
 *   F. Injecter métadonnées WAM dans les items panier / commandes
 *
 * @package wamv1
 */

// ============================================================================
// A. Désactiver les styles CSS natifs WooCommerce
//    On charge notre shop.css à la place (voir functions.php)
// ============================================================================

add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

// ============================================================================
// B. Retirer les endpoints inutiles du menu Mon compte
//    Adresses et Téléchargements ne sont pas pertinents pour une école de danse
// ============================================================================

add_filter( 'woocommerce_account_menu_items', 'wamv1_wc_account_menu_items' );

function wamv1_wc_account_menu_items( array $items ): array {
    unset( $items['edit-address'] ); // Adresses — inutile (pas de livraison physique)
    unset( $items['downloads'] );    // Téléchargements — pas de produits numériques
    return $items;
}

// ============================================================================
// C. Rediriger /boutique → /cours-collectifs
//    La boutique WC native n'est pas exposée — les CPTs servent de listing
// ============================================================================

add_action( 'template_redirect', 'wamv1_redirect_boutique' );

function wamv1_redirect_boutique(): void {
    if ( ! function_exists( 'is_shop' ) || ! is_shop() ) return;

    $page  = get_page_by_path( 'cours-collectifs' );
    $cours_url = $page ? get_permalink( $page->ID ) : home_url( '/cours-collectifs/' );
    wp_safe_redirect( $cours_url, 301 );
    exit;
}

// ============================================================================
// D. Helper — récupérer l'ID du produit WC lié à un cours ou stage
//    Le champ ACF wc_product_id est de type "relationship" (retourne array de WP_Post)
// ============================================================================

function wamv1_get_wc_product_id( int $post_id ): int {
    if ( ! function_exists( 'get_field' ) ) return 0;

    $products = get_field( 'wc_product_id', $post_id );

    // relationship retourne un array de WP_Post (ou vide)
    if ( empty( $products ) || ! is_array( $products ) ) return 0;

    $first = $products[0];
    return is_object( $first ) ? (int) $first->ID : (int) $first;
}

// ============================================================================
// E. Sync stock — quand on coche complete_cours sur un cours/stage,
//    mettre à jour le statut de stock du produit WC lié
// ============================================================================

add_action( 'acf/save_post', 'wamv1_sync_wc_stock_from_acf', 20 );

function wamv1_sync_wc_stock_from_acf( $post_id ): void {
    $post_type = get_post_type( $post_id );
    if ( ! in_array( $post_type, [ 'cours', 'stages' ], true ) ) return;
    if ( ! function_exists( 'get_field' ) || ! function_exists( 'wc_get_product' ) ) return;

    $product_id = wamv1_get_wc_product_id( (int) $post_id );
    if ( ! $product_id ) return;

    $product = wc_get_product( $product_id );
    if ( ! $product ) return;

    $complet = (bool) get_field( 'complete_cours', $post_id );
    $product->set_stock_status( $complet ? 'outofstock' : 'instock' );
    $product->save();
}

// ============================================================================
// F. Métadonnées WAM dans le panier — injecter jour/heure (cours) ou date (stage)
//    Visible dans le panier, la commande et le back-office
// ============================================================================

add_filter( 'woocommerce_add_cart_item_data', 'wamv1_add_wc_item_meta', 10, 2 );

function wamv1_add_wc_item_meta( array $cart_item_data, int $product_id ): array {
    if ( ! function_exists( 'get_field' ) ) return $cart_item_data;

    foreach ( [ 'cours', 'stages' ] as $post_type ) {
        $linked = get_posts( [
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            // relationship stocke les IDs séparés par virgule ou sérialisés
            'meta_query'     => [ [ 'key' => 'wc_product_id', 'value' => $product_id, 'compare' => 'LIKE' ] ],
        ] );

        if ( ! $linked ) continue;

        $cpt_id = $linked[0]->ID;

        if ( $post_type === 'cours' ) {
            $jour        = get_field( 'jour_de_cours', $cpt_id );
            $heure_debut = get_field( 'heure_debut',   $cpt_id );
            $heure_fin   = get_field( 'heure_de_fin',  $cpt_id );
            if ( $jour )        $cart_item_data['wam_jour']  = wamv1_get_day_label( $jour );
            if ( $heure_debut ) $cart_item_data['wam_heure'] = "{$heure_debut} – {$heure_fin}";
        }

        if ( $post_type === 'stages' ) {
            $date = get_field( 'date_stage', $cpt_id );
            if ( $date ) $cart_item_data['wam_date'] = $date;
        }

        break;
    }

    return $cart_item_data;
}

// Afficher les métadonnées WAM dans le tableau panier et le récapitulatif commande

add_filter( 'woocommerce_get_item_data', 'wamv1_display_wc_item_meta', 10, 2 );

function wamv1_display_wc_item_meta( array $item_data, array $cart_item ): array {
    if ( ! empty( $cart_item['wam_jour'] ) ) {
        $item_data[] = [ 'name' => 'Jour',  'value' => $cart_item['wam_jour'] ];
    }
    if ( ! empty( $cart_item['wam_heure'] ) ) {
        $item_data[] = [ 'name' => 'Heure', 'value' => $cart_item['wam_heure'] ];
    }
    if ( ! empty( $cart_item['wam_date'] ) ) {
        $item_data[] = [ 'name' => 'Date',  'value' => $cart_item['wam_date'] ];
    }
    return $item_data;
}

// Persister les métadonnées dans la commande (visible dans le back-office)

add_action( 'woocommerce_checkout_create_order_line_item', 'wamv1_save_wc_item_meta_to_order', 10, 3 );

function wamv1_save_wc_item_meta_to_order( $item, $_cart_item_key, $values ): void {
    if ( ! empty( $values['wam_jour'] ) ) {
        $item->add_meta_data( 'Jour',  $values['wam_jour'],  true );
    }
    if ( ! empty( $values['wam_heure'] ) ) {
        $item->add_meta_data( 'Heure', $values['wam_heure'], true );
    }
    if ( ! empty( $values['wam_date'] ) ) {
        $item->add_meta_data( 'Date',  $values['wam_date'],  true );
    }
}

// ============================================================================
// Helper — convertir le slug ACF jour_de_cours en libellé lisible
//    (déjà déclarée dans functions.php — guard pour éviter le double-declare)
// ============================================================================

if ( ! function_exists( 'wamv1_get_day_label' ) ) {
    function wamv1_get_day_label( string $slug ): string {
        $labels = [
            '01day' => 'Lundi',
            '02day' => 'Mardi',
            '03day' => 'Mercredi',
            '04day' => 'Jeudi',
            '05day' => 'Vendredi',
            '06day' => 'Samedi',
            '07day' => 'Dimanche',
        ];
        return $labels[ $slug ] ?? $slug;
    }
}
