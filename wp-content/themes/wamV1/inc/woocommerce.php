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

add_filter('woocommerce_enqueue_styles', '__return_empty_array');

// ============================================================================
// B. Retirer les endpoints inutiles du menu Mon compte
//    Adresses et Téléchargements ne sont pas pertinents pour une école de danse
// ============================================================================

add_filter('woocommerce_account_menu_items', 'wamv1_wc_account_menu_items');

function wamv1_wc_account_menu_items(array $items): array
{
    unset($items['edit-address']); // Adresses — inutile (pas de livraison physique)
    unset($items['downloads']);    // Téléchargements — pas de produits numériques

    // Ajouter le lien Administration pour les rôles autorisés (Directrice et Professeurs)
    $user = wp_get_current_user();
    $allowed_roles = ['directrice', 'professeur', 'administrator'];

    if (array_intersect($allowed_roles, (array) $user->roles)) {
        // On insère avant "Déconnexion" ou à la fin si non trouvé
        $new_items = [];
        $inserted = false;

        foreach ($items as $key => $label) {
            if ($key === 'customer-logout') {
                $new_items['wp-admin-link'] = 'Administration';
                $inserted = true;
            }
            $new_items[$key] = $label;
        }

        if (!$inserted) {
            $new_items['wp-admin-link'] = 'Administration';
        }

        $items = $new_items;
    }

    return $items;
}

/**
 * Rediriger l'endpoint factice vers l'URL réelle de l'admin
 */
add_filter('woocommerce_get_endpoint_url', 'wamv1_wc_admin_endpoint_url', 10, 4);
function wamv1_wc_admin_endpoint_url($url, $endpoint, $value, $permalink)
{
    if ($endpoint === 'wp-admin-link') {
        return admin_url();
    }
    return $url;
}


// ============================================================================
// C. Rediriger /boutique → /cours-collectifs
//    La boutique WC native n'est pas exposée — les CPTs servent de listing
// ============================================================================

add_action('template_redirect', 'wamv1_redirect_boutique');

function wamv1_redirect_boutique(): void
{
    if (!function_exists('is_shop') || !is_shop())
        return;

    $page = get_page_by_path('cours-collectifs');
    $cours_url = $page ? get_permalink($page->ID) : home_url('/cours-collectifs/');
    wp_safe_redirect($cours_url, 301);
    exit;
}

// ============================================================================
// D. Helper — récupérer l'ID du produit WC lié à un cours ou stage
//    (Fonction déplacée dans functions.php pour compatibilité)
// ============================================================================

/**
 * Retourne le tableau des sous-champs du group field ACF "tarifs" sur un stage.
 * Les sous-champs (nom_tarif_1, prix_tarif_1, quota_tarif_1, quota_reserve_1,
 * activer_tarif_2, …) sont imbriqués dans le group field, donc inaccessibles via
 * get_field('nom_tarif_1'). Passer par get_field('tarifs') est obligatoire.
 */
function wamv1_stage_tarifs(?int $post_id = null): array
{
    if (!function_exists('get_field')) return [];
    $post_id = $post_id ?: (int) get_the_ID();
    $grp = get_field('tarifs', $post_id);
    return is_array($grp) ? $grp : [];
}

// ============================================================================
// E. Sync stock — quand on coche complete_cours sur un cours/stage,
//    mettre à jour le statut de stock du produit WC lié
// ============================================================================

add_action('acf/save_post', 'wamv1_sync_wc_stock_from_acf', 20);

function wamv1_sync_wc_stock_from_acf($post_id): void
{
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, ['cours', 'stages'], true))
        return;
    if (!function_exists('get_field') || !function_exists('wc_get_product'))
        return;

    $product_id = wamv1_get_wc_product_id((int) $post_id);
    if (!$product_id)
        return;

    $product = wc_get_product($product_id);
    if (!$product)
        return;

    $complet = (bool) get_field('complete_cours', $post_id);
    $product->set_stock_status($complet ? 'outofstock' : 'instock');
    $product->save();
}

// ============================================================================
// F. Métadonnées WAM dans le panier — injecter jour/heure (cours) ou date (stage)
//    Visible dans le panier, la commande et le back-office
// ============================================================================

add_filter('woocommerce_add_cart_item_data', 'wamv1_add_wc_item_meta', 10, 2);

function wamv1_add_wc_item_meta(array $cart_item_data, int $product_id): array
{
    if (!function_exists('get_field'))
        return $cart_item_data;

    // Capture de l'index du tarif (via AJAX POST)
    if (!empty($_POST['tarif_index'])) {
        $t_idx = absint($_POST['tarif_index']);
        if ($t_idx >= 1 && $t_idx <= 3) {
            $cart_item_data['wam_tarif_index'] = $t_idx;
        }
    }

    // 1. Priorité : Si on a l'ID direct (via notre tunnel AJAX), on l'utilise
    $cpt_id = !empty($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $post_type = $cpt_id ? get_post_type($cpt_id) : '';

    // 2. Fallback : Recherche par product_id (ex: ajout panier standard)
    if (!$cpt_id) {
        foreach (['cours', 'stages'] as $type) {
            $linked = get_posts([
                'post_type' => $type,
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'no_found_rows' => true,
                // Utilisation de guillemets pour matcher précisément l'ID dans la donnée sérialisée ACF
                'meta_query' => [['key' => 'wc_product_id', 'value' => '"' . $product_id . '"', 'compare' => 'LIKE']],
            ]);

            if ($linked) {
                $cpt_id = $linked[0]->ID;
                $post_type = $type;
                break;
            }
        }
    }

    if (!$cpt_id) return $cart_item_data;

    $cart_item_data['wam_course_id'] = $cpt_id;

    if ($post_type === 'cours') {
        $jour = get_field('jour_de_cours', $cpt_id);
        $heure_debut = get_field('heure_debut', $cpt_id);
        $heure_fin = get_field('heure_de_fin', $cpt_id);
        if ($jour)
            $cart_item_data['wam_jour'] = wamv1_get_day_label($jour);
        if ($heure_debut)
            $cart_item_data['wam_heure'] = "{$heure_debut} – {$heure_fin}";
    }

    if ($post_type === 'stages') {
        $date = get_field('date_stage', $cpt_id);
        if ($date)
            $cart_item_data['wam_date'] = $date;

        // Récupérer le libellé du tarif spécifique (via group field "tarifs")
        if (!empty($cart_item_data['wam_tarif_index'])) {
            $idx = (int) $cart_item_data['wam_tarif_index'];
            $grp = wamv1_stage_tarifs($cpt_id);
            $label = $grp['nom_tarif_' . $idx] ?? '';
            if ($label) {
                $cart_item_data['wam_tarif_label'] = $label;
            } else {
                // Sécurité : Si l'index ne correspond à aucun tarif réel, on le supprime
                unset($cart_item_data['wam_tarif_index']);
            }
        }
    }

    return $cart_item_data;
}

// Afficher les métadonnées WAM dans le tableau panier et le récapitulatif commande

add_filter('woocommerce_get_item_data', 'wamv1_display_wc_item_meta', 10, 2);

function wamv1_display_wc_item_meta(array $item_data, array $cart_item): array
{
    if (!empty($cart_item['wam_jour'])) {
        $item_data[] = ['name' => 'Jour', 'value' => $cart_item['wam_jour']];
    }
    if (!empty($cart_item['wam_heure'])) {
        $item_data[] = ['name' => 'Heure', 'value' => $cart_item['wam_heure']];
    }
    if (!empty($cart_item['wam_date'])) {
        $item_data[] = ['name' => 'Date', 'value' => $cart_item['wam_date']];
    }
    if (!empty($cart_item['wam_tarif_label'])) {
        $item_data[] = ['name' => 'Tarif', 'value' => $cart_item['wam_tarif_label']];
    }
    return $item_data;
}

// Persister les métadonnées dans la commande (visible dans le back-office)

add_action('woocommerce_checkout_create_order_line_item', 'wamv1_save_wc_item_meta_to_order', 10, 3);

function wamv1_save_wc_item_meta_to_order($item, $_cart_item_key, $values): void
{
    if (!empty($values['wam_course_id'])) {
        $item->add_meta_data('_wam_course_id', $values['wam_course_id'], true);
    }
    // Pour les stages
    if (!empty($values['wam_tarif_index'])) {
        $item->add_meta_data('_wam_tarif_index', $values['wam_tarif_index'], true);
    }
    if (!empty($values['wam_tarif_label'])) {
        $item->add_meta_data('Tarif', $values['wam_tarif_label'], true);
    }

    if (!empty($values['wam_jour'])) {
        $item->add_meta_data('Jour', $values['wam_jour'], true);
    }
    if (!empty($values['wam_heure'])) {
        $item->add_meta_data('Heure', $values['wam_heure'], true);
    }
    if (!empty($values['wam_date'])) {
        $item->add_meta_data('Date', $values['wam_date'], true);
    }
}

/**
 * OVERRIDE PRIX : Appliquer le prix ACF au produit WooCommerce dans le panier
 */
add_action('woocommerce_before_calculate_totals', 'wamv1_override_cart_item_prices', 10, 1);
function wamv1_override_cart_item_prices($cart)
{
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (did_action('woocommerce_before_calculate_totals') >= 2) return;

    foreach ($cart->get_cart() as $cart_item) {
        $course_id = $cart_item['wam_course_id'] ?? null;
        $tarif_idx = $cart_item['wam_tarif_index'] ?? null;
        $product_id = $cart_item['product_id'];

        if ($course_id && $tarif_idx) {
            // 1. Sécurité : Vérifier que le course_id est bien lié à ce produit WooCommerce
            $linked_product_id = wamv1_get_wc_product_id((int) $course_id);
            if ($linked_product_id !== (int) $product_id) {
                continue; // Manipulation suspecte ou produit non lié
            }

            // 2. Sécurité : Vérifier que l'index du tarif est valide (1, 2 ou 3)
            $tarif_idx = (int) $tarif_idx;
            if ($tarif_idx < 1 || $tarif_idx > 3) {
                continue;
            }

            // 3. Appliquer le prix depuis ACF
            $grp = wamv1_stage_tarifs((int) $course_id);
            $price = (float) ($grp['prix_tarif_' . $tarif_idx] ?? 0);

            if ($price > 0) {
                $cart_item['data']->set_price($price);
            }
        }
    }
}

// ============================================================================
// Quotas — Décompte automatique des places lors de la validation de commande
// ============================================================================

add_action('woocommerce_payment_complete', 'wamv1_decrement_course_quota_on_payment');
add_action('woocommerce_order_status_processing', 'wamv1_decrement_course_quota_on_payment');
add_action('woocommerce_order_status_completed', 'wamv1_decrement_course_quota_on_payment');

// Restauration des places si la commande est annulée, échouée ou remboursée
add_action('woocommerce_order_status_cancelled', 'wamv1_restore_course_quota_on_cancellation');
add_action('woocommerce_order_status_refunded', 'wamv1_restore_course_quota_on_cancellation');
add_action('woocommerce_order_status_failed', 'wamv1_restore_course_quota_on_cancellation');

function wamv1_decrement_course_quota_on_payment(int $order_id): void
{
    $order = wc_get_order($order_id);
    if (!$order)
        return;

    // Éviter double décompte
    if ($order->get_meta('_wam_quota_decremented'))
        return;

    foreach ($order->get_items() as $item) {
        $course_id = $item->get_meta('_wam_course_id');
        if (!$course_id)
            continue;

        $post_type = get_post_type($course_id);
        $tarif_idx = $item->get_meta('_wam_tarif_index');

        if ($post_type === 'cours') {
            // Logique classique pour les cours
            $places_res = (int) get_field('places_reservees', $course_id);
            $new_res = $places_res + 1;
            update_field('places_reservees', $new_res, $course_id);

            $places_totales = (int) get_field('places_totales', $course_id);
            if ($places_totales > 0 && $new_res >= $places_totales) {
                update_field('complete_cours', true, $course_id);
            $order->add_order_note(sprintf('Quota atteint pour le cours #%d (%s). Inscriptions fermées.', $course_id, get_the_title($course_id)));
            // Nettoyer le cache ACF pour éviter les décalages de lecture
            if (function_exists('acf_delete_cache')) acf_delete_cache("post_id={$course_id}");
            }
        } 
        elseif ($post_type === 'stages' && $tarif_idx) {
            // Stage multi-tarif : lecture + écriture via le group field "tarifs".
            // update_field sur un sous-champ imbriqué ne traverse pas le groupe ;
            // il faut réécrire le groupe entier avec la valeur incrémentée.
            $grp = wamv1_stage_tarifs((int) $course_id);
            $reserve_key = 'quota_reserve_' . $tarif_idx;
            $total_key   = 'quota_tarif_'   . $tarif_idx;

            $new_reserve = (int) ($grp[$reserve_key] ?? 0) + 1;
            $grp[$reserve_key] = $new_reserve;
            update_field('tarifs', $grp, $course_id);

            $max_places = (int) ($grp[$total_key] ?? 0);
            $tarif_name = $grp['nom_tarif_' . $tarif_idx] ?? '';

            if ($max_places > 0 && $new_reserve >= $max_places) {
                $order->add_order_note(sprintf('Tarif "%s" complet pour le stage #%d.', $tarif_name, $course_id));
            }
        }
    }

    $order->add_meta_data('_wam_quota_decremented', '1', true);
    $order->save();
}

/**
 * RESTAURER QUOTA : Rendre la place si la commande est annulée ou remboursée
 */
function wamv1_restore_course_quota_on_cancellation(int $order_id): void
{
    $order = wc_get_order($order_id);
    if (!$order || !$order->get_meta('_wam_quota_decremented'))
        return;

    foreach ($order->get_items() as $item) {
        $course_id = $item->get_meta('_wam_course_id');
        if (!$course_id)
            continue;

        $post_type = get_post_type($course_id);
        $tarif_idx = $item->get_meta('_wam_tarif_index');

        if ($post_type === 'cours') {
            $places_res = (int) get_field('places_reservees', $course_id);
            $new_res = max(0, $places_res - 1);
            update_field('places_reservees', $new_res, $course_id);
            
            // Si on libère une place, le cours n'est plus forcément complet
            update_field('complete_cours', false, $course_id);
        } 
        elseif ($post_type === 'stages' && $tarif_idx) {
            $grp = wamv1_stage_tarifs((int) $course_id);
            $reserve_key = 'quota_reserve_' . $tarif_idx;
            
            $new_reserve = max(0, (int) ($grp[$reserve_key] ?? 0) - 1);
            $grp[$reserve_key] = $new_reserve;
            update_field('tarifs', $grp, $course_id);
        }
        
        if (function_exists('acf_delete_cache')) acf_delete_cache("post_id={$course_id}");
    }

    // Supprimer le flag pour permettre un nouveau décompte si la commande est réactivée
    $order->delete_meta_data('_wam_quota_decremented');
    $order->save();
}

// ============================================================================
// Helper — convertir le slug ACF jour_de_cours en libellé lisible
//    (déjà déclarée dans functions.php — guard pour éviter le double-declare)
// ============================================================================

if (!function_exists('wamv1_get_day_label')) {
    function wamv1_get_day_label(string $slug): string
    {
        $labels = [
            '01day' => 'Lundi',
            '02day' => 'Mardi',
            '03day' => 'Mercredi',
            '04day' => 'Jeudi',
            '05day' => 'Vendredi',
            '06day' => 'Samedi',
            '07day' => 'Dimanche',
        ];
        return $labels[$slug] ?? $slug;
    }
}

// ============================================================================
// G. AJAX Add-to-Cart — endpoint custom pour les pages cours
//    Ajoute le produit WC lié, détecte les doublons, retourne les fragments
// ============================================================================

add_action('wp_ajax_wam_add_to_cart', 'wamv1_ajax_add_to_cart');
add_action('wp_ajax_nopriv_wam_add_to_cart', 'wamv1_ajax_add_to_cart');

function wamv1_ajax_add_to_cart(): void
{
    check_ajax_referer('wam_add_to_cart', 'nonce');

    // S'assurer que WC est chargé et que le panier est disponible
    if (!function_exists('WC') || !WC() || !WC()->session) {
        wp_send_json_error(['message' => 'Session WooCommerce introuvable.']);
    }

    if (null === WC()->cart) {
        wc_load_cart();
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    $course_id  = absint($_POST['course_id'] ?? 0);
    if (!$product_id) {
        wp_send_json_error(['message' => 'Produit introuvable.']);
    }

    // Vérifier que le produit existe et est achetable
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
        wp_send_json_error(['message' => 'Ce cours n\'est plus disponible.']);
    }

    // Ajouter au panier — WC fusionne les items au même wam_course_id + wam_tarif_index.
    $selections = $_POST['selections'] ?? null; // [{tarif_index, qty}, ...]
    $added      = 0;

    if ($selections && is_array($selections)) {
        foreach ($selections as $sel) {
            $t_idx = absint($sel['tarif_index'] ?? 0);
            $qty   = absint($sel['qty'] ?? 0);
            if ($qty <= 0 || !$t_idx) continue;
            $cart_item_data = ['wam_tarif_index' => $t_idx];
            if ($course_id) $cart_item_data['wam_course_id'] = $course_id;
            if (WC()->cart->add_to_cart($product_id, $qty, 0, [], $cart_item_data)) {
                $added += $qty;
            }
        }
    } else {
        $cart_item_data = [];
        if ($course_id) $cart_item_data['wam_course_id'] = $course_id;
        if (!empty($_POST['tarif_index'])) {
            $cart_item_data['wam_tarif_index'] = absint($_POST['tarif_index']);
        }
        if (WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data)) {
            $added = 1;
        }
    }

    if (!$added) {
        wp_send_json_error(['message' => 'Erreur lors de l\'ajout au panier.']);
    }

    wc_setcookie('woocommerce_items_in_cart', WC()->cart->get_cart_contents_count());
    wc_setcookie('woocommerce_cart_hash', WC()->cart->get_cart_hash());

    $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);

    wp_send_json_success([
        'message' => 'Cours ajouté au panier !',
        'fragments' => $fragments,
        'cart_count' => WC()->cart->get_cart_contents_count(),
    ]);
}

// ============================================================================
// H. Cart Fragments — Mise à jour dynamique de l'icône panier
//    Remplace le markup du panier et ajoute la classe is-animating
// ============================================================================

add_filter('woocommerce_add_to_cart_fragments', 'wamv1_cart_fragment_refresh');

function wamv1_cart_fragment_refresh($fragments)
{
    ob_start();
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $empty_class = $cart_count === 0 ? ' is-empty' : ' is-animating'; // is-animating déclenche l'animation
    ?>
    <a href="<?php echo esc_url(wc_get_cart_url()); ?>"
        class="wam-header__cart-link wam-cart-fragment<?php echo $empty_class; ?>"
        aria-label="<?php esc_attr_e('Voir le panier', 'wamv1'); ?>">
        <span class="btn-icon"
            style="--icon-url: url('<?php echo esc_url(get_template_directory_uri() . '/assets/images/panier.svg'); ?>'); --icon-size: 34px;"></span>
        <?php if ($cart_count > 0): ?>
            <span class="wam-header__cart-count text-xs fw-bold"><?php echo esc_html($cart_count); ?></span>
        <?php endif; ?>
    </a>
    <?php
    $fragments['a.wam-cart-fragment'] = ob_get_clean();
    return $fragments;
}

// ============================================================================
// I. Nettoyage des fonctionnalités WooCommerce inutilisées
//    (Avis, Produits liés, Widgets)
// ============================================================================

// Supprimer le wrapper de fin natif (sécurité propreté HTML)
add_action('init', function () {
    remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
});

// Supprimer les produits liés / upsells / cross-sells (inutile pour des cours)
remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
add_filter('woocommerce_cross_sells_total', '__return_zero');

// Supprimer les avis (reviews) dans les tabs
add_filter('woocommerce_product_tabs', function ($tabs) {
    unset($tabs['reviews']);
    return $tabs;
});

// Désactiver l'enregistrement des widgets WC 
add_filter('woocommerce_widgets_init', '__return_false');

// ============================================================================
// FIX TEMPORAIRE : Remplacement brutal du bloc WooCommerce par le shortcode
// ============================================================================
add_action('init', function () {
    if (!is_admin() && !isset($_GET['wam_fix_pages'])) {
        return;
    }

    $cart_page_id = wc_get_page_id('cart');
    if ($cart_page_id) {
        $content = get_post_field('post_content', $cart_page_id);
        // Si le shortcode n'y est pas, on rase tout et on le met
        if (strpos($content, '[woocommerce_cart]') === false) {
            wp_update_post([
                'ID' => $cart_page_id,
                'post_content' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->'
            ]);
        }
    }

    $checkout_page_id = wc_get_page_id('checkout');
    if ($checkout_page_id) {
        $content = get_post_field('post_content', $checkout_page_id);
        if (strpos($content, '[woocommerce_checkout]') === false) {
            wp_update_post([
                'ID' => $checkout_page_id,
                'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
            ]);
        }
    }
});

// ============================================================================
// J. Champs personnalisés Checkout (Info Adhérents par Cours)
// ============================================================================

// 1. Ajouter les champs dans le formulaire (après la facturation)
add_action('woocommerce_after_checkout_billing_form', 'wamv1_add_adherent_fields_to_checkout');

function wamv1_add_adherent_fields_to_checkout($checkout)
{
    if (WC()->cart->is_empty())
        return;

    $items = WC()->cart->get_cart();
    $cart_count = WC()->cart->get_cart_contents_count();
    $is_solo = ($cart_count === 1);

    // MODE SOLO RADICAL : Si 1 seul cours, pas de section adhérents (on utilise billing)
    if ($is_solo) {
        return;
    }

    echo '<div id="wam-adherents-fields" class="wam-adherents-section mt-xl">';
    echo '<h3 class="title-norm-sm color-green mb-md">Les adhérent·es</h3>';

    $index = 2;

    foreach ($items as $cart_item_key => $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        if (!$product)
            continue;

        $product_name = $product->get_name();
        $course_id = $cart_item['wam_course_id'] ?? null;
        $course_title = $course_id ? get_the_title($course_id) : $product_name;
        $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;
        $tarif_label = $cart_item['wam_tarif_label'] ?? '';

        // Détection auto du nombre de participants requis par le tarif
        $nb_participants_base = 1;
        $duo_keywords = ['duo', 'parent', 'enfant', 'accompagnant', 'couple', '2 personnes'];
        foreach ($duo_keywords as $key) {
            if (stripos($tarif_label, $key) !== false) {
                $nb_participants_base = 2;
                break;
            }
        }

        // Multiplication par la quantité pour permettre l'inscription de plusieurs personnes
        $qty = $cart_item['quantity'] ?? 1;
        $total_p = $nb_participants_base * $qty;

        for ($p = 1; $p <= $total_p; $p++) {
            $p_suffix = ($total_p > 1) ? " ($p)" : "";
            
            echo '<div class="wam-adherent-group wam-adherent-card">';

            echo '<div class="wam-adherent-card__header">';
            $card_title = 'Participant·e ' . $index;
            echo '<p class="text-md mb-0">' . $card_title . '</p>';
            echo '<div class="wam-adherent-card__course-info text-right">';
            echo '<h4 class="text-md color-yellow fw-bold m-0">' . esc_html($course_title) . '</h4>';
            if ($course_subtitle) {
                echo '<p class="text-xs color-subtext m-0">' . esc_html($course_subtitle) . '</p>';
            }
            if ($tarif_label) {
                echo '<p class="text-sm color-green fw-bold mb-0">' . esc_html($tarif_label) . '</p>';
            }
            echo '</div>';
            echo '</div>';

            // Checkbox d'auto-remplissage (uniquement pour le premier participant de chaque item)
            if ($p === 1) {
                echo '<p class="form-row form-row-wide wam-adherent-auto-fill mb-md">';
                echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
                $checked = ($is_solo && $p === 1) ? 'checked="checked"' : '';
                echo '<input type="checkbox" class="wam-is-buyer-checkbox woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wam_is_buyer_' . $cart_item_key . '" value="1" ' . $checked . ' /> ';
                echo '<span>Utiliser les informations de l\'adhérent·e principal·e</span>';
                echo '</label>';
                echo '</p>';
            }
            $index++;

            // Champs Prénom / Nom
            $field_key_suffix = ($p > 1) ? "_p$p" : "";
            $is_stage = wamv1_is_stage_item($cart_item);

            echo '<div class="wam-adherent-card__fields">';

            woocommerce_form_field('wam_prenom_eleve_' . $cart_item_key . $field_key_suffix, [
                'type' => 'text',
                'class' => ['form-row-first validated'],
                'label' => 'Prénom',
                'required' => true,
            ], $checkout->get_value('wam_prenom_eleve_' . $cart_item_key . $field_key_suffix));

            woocommerce_form_field('wam_nom_eleve_' . $cart_item_key . $field_key_suffix, [
                'type' => 'text',
                'class' => ['form-row-last validated'],
                'label' => 'Nom',
                'required' => true,
            ], $checkout->get_value('wam_nom_eleve_' . $cart_item_key . $field_key_suffix));

            if ($is_stage) {
                // Pour les stages, on demande juste le téléphone en plus
                woocommerce_form_field('wam_tel_participant_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'tel',
                    'class' => ['form-row-wide validated'],
                    'label' => 'Téléphone',
                    'required' => true,
                ], $checkout->get_value('wam_tel_participant_' . $cart_item_key . $field_key_suffix));
            }

            echo '</div>';
            
            if (!$is_stage) {
                // Urgence 1 (Uniquement pour les cours)
                echo '<div class="wam-emergency-block">';
                echo '<p class="wam-emergency-block__title">Contact d\'urgence</p>';
                echo '<div class="wam-adherent-card__fields">';
                
                woocommerce_form_field('wam_urgent_name_1_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'text',
                    'label' => 'Nom et prénom',
                    'required' => true,
                    'class' => ['form-row-first'],
                ], $checkout->get_value('wam_urgent_name_1_' . $cart_item_key . $field_key_suffix));

                woocommerce_form_field('wam_urgent_phone_1_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'tel',
                    'label' => 'Téléphone',
                    'required' => true,
                    'class' => ['form-row-last'],
                ], $checkout->get_value('wam_urgent_phone_1_' . $cart_item_key . $field_key_suffix));
                
                echo '</div>';
                echo '</div>';

                // Urgence 2 (Optionnel pour Mineurs - Cours uniquement)
                $is_minor = has_term(['enfants', 'ados'], 'product_cat', $cart_item['product_id']);
                if ($is_minor) {
                    echo '<div class="wam-emergency-block">';
                    echo '<p class="wam-emergency-block__title">Second contact d\'urgence (Optionnel)</p>';
                    echo '<div class="wam-adherent-card__fields">';
                    
                    woocommerce_form_field('wam_urgent_name_2_' . $cart_item_key . $field_key_suffix, [
                        'type' => 'text',
                        'label' => 'Nom et prénom',
                        'required' => false,
                        'class' => ['form-row-first'],
                    ], $checkout->get_value('wam_urgent_name_2_' . $cart_item_key . $field_key_suffix));

                    woocommerce_form_field('wam_urgent_phone_2_' . $cart_item_key . $field_key_suffix, [
                        'type' => 'tel',
                        'label' => 'Téléphone',
                        'required' => false,
                        'class' => ['form-row-last'],
                    ], $checkout->get_value('wam_urgent_phone_2_' . $cart_item_key . $field_key_suffix));
                    
                    echo '</div>';
                    echo '</div>';
                }
            }

            echo '<div class="clear"></div>';
            echo '</div>';
        }
    }

    echo '<div class="wam-rgpd-info mt-md">';
    echo '<p class="text-xs color-subtext mb-xs">Les informations nominatives et les contacts d\'urgence sont collectés exclusivement pour assurer le suivi pédagogique et la sécurité des participant·es pendant les cours. Ces données sont conservées pour une durée maximale de 2 ans.</p>';
    echo '<p class="text-xs color-subtext">L\'inscription aux cours ou stages WAM implique le consentement au droit à l\'image. Vous pouvez toutefois refuser l\'utilisation de votre image (ou celle de votre enfant) sur simple demande à <a href="mailto:contact@wamdancestudio.fr" class="color-yellow">contact@wamdancestudio.fr</a>.</p>';
    echo '</div>';

    echo '</div>'; // #wam-adherents-fields
}

// 2. JS Auto-remplissage et CSS Checkout
add_action('wp_footer', 'wamv1_checkout_scripts');

function wamv1_checkout_scripts()
{
    if (!is_checkout() || is_wc_endpoint_url())
        return;
    ?>
    <script>
        jQuery(document).ready(function ($) {
            const prenomFact = document.getElementById('billing_first_name');
            const nomFact = document.getElementById('billing_last_name');
            const telFact = document.getElementById('billing_phone');
            const urgentNameFact = document.getElementById('billing_urgent_name');
            const urgentPhoneFact = document.getElementById('billing_urgent_phone');

            const checkboxes = document.querySelectorAll('.wam-is-buyer-checkbox');
            if (checkboxes.length === 0) return; // Pas de participants multiples

            const syncFields = (checkbox) => {
                const card = checkbox.closest('.wam-adherent-card');
                const prenomInput = card.querySelector('input[name^="wam_prenom_eleve_"]');
                const nomInput = card.querySelector('input[name^="wam_nom_eleve_"]');
                const telInput = card.querySelector('input[name^="wam_tel_participant_"]');
                const urgentNameInput = card.querySelector('input[name^="wam_urgent_name_1_"]');
                const urgentPhoneInput = card.querySelector('input[name^="wam_urgent_phone_1_"]');

                if (checkbox.checked) {
                    // Sauvegarde des anciennes valeurs si vides
                    if (prenomInput && prenomInput.value && !prenomInput.dataset.oldVal) prenomInput.dataset.oldVal = prenomInput.value;
                    if (nomInput && nomInput.value && !nomInput.dataset.oldVal) nomInput.dataset.oldVal = nomInput.value;
                    if (telInput && telInput.value && !telInput.dataset.oldVal) telInput.dataset.oldVal = telInput.value;
                    
                    // Copie des infos facturation
                    if (prenomInput && prenomFact) prenomInput.value = prenomFact.value;
                    if (nomInput && nomFact) nomInput.value = nomFact.value;
                    if (telInput && telFact) telInput.value = telFact.value;
                    if (urgentNameInput && urgentNameFact) urgentNameInput.value = urgentNameFact.value;
                    if (urgentPhoneInput && urgentPhoneFact) urgentPhoneInput.value = urgentPhoneFact.value;
                } else {
                    if (prenomInput && prenomInput.dataset.oldVal !== undefined) prenomInput.value = prenomInput.dataset.oldVal || '';
                    if (nomInput && nomInput.dataset.oldVal !== undefined) nomInput.value = nomInput.dataset.oldVal || '';
                    if (telInput && telInput.dataset.oldVal !== undefined) telInput.value = telInput.dataset.oldVal || '';
                    if (urgentNameInput && urgentNameInput.dataset.oldVal !== undefined) urgentNameInput.value = urgentNameInput.dataset.oldVal || '';
                    if (urgentPhoneInput && urgentPhoneInput.dataset.oldVal !== undefined) urgentPhoneInput.value = urgentPhoneInput.dataset.oldVal || '';
                }
            };

            checkboxes.forEach(checkbox => {
                // État initial (pour le mode solo auto-coché)
                if (checkbox.checked) {
                    setTimeout(() => syncFields(checkbox), 100); // Petit délai pour laisser WC peupler billing
                }

                checkbox.addEventListener('change', function () {
                    syncFields(this);
                });

                // Si l'utilisateur modifie manuellement, on décoche l'unification
                const card = checkbox.closest('.wam-adherent-card');
                const inputsToWatch = card.querySelectorAll('input[name^="wam_prenom_eleve_"], input[name^="wam_nom_eleve_"], input[name^="wam_tel_participant_"], input[name^="wam_urgent_name_1_"], input[name^="wam_urgent_phone_1_"]');

                inputsToWatch.forEach(input => {
                    input.addEventListener('input', function () {
                        if (checkbox.checked) {
                            checkbox.checked = false;
                        }
                    });
                });
            });

            // Mise à jour en temps réel si les infos de facturation changent
            [prenomFact, nomFact, telFact, urgentNameFact, urgentPhoneFact].forEach(field => {
                if (field) {
                    field.addEventListener('input', function () {
                        checkboxes.forEach(checkbox => {
                            if (checkbox.checked) {
                                const card = checkbox.closest('.wam-adherent-card');
                                const prenomInput = card.querySelector('input[name^="wam_prenom_eleve_"]');
                                const nomInput = card.querySelector('input[name^="wam_nom_eleve_"]');
                                const telInput = card.querySelector('input[name^="wam_tel_participant_"]');
                                const urgentNameInput = card.querySelector('input[name^="wam_urgent_name_1_"]');
                                const urgentPhoneInput = card.querySelector('input[name^="wam_urgent_phone_1_"]');

                                if (prenomInput && prenomFact) prenomInput.value = prenomFact.value;
                                if (nomInput && nomFact) nomInput.value = nomFact.value;
                                if (telInput && telFact) {
                                    telInput.value = telFact.value;
                                }
                                if (urgentNameInput && urgentNameFact) urgentNameInput.value = urgentNameFact.value;
                                if (urgentPhoneInput && urgentPhoneFact) urgentPhoneInput.value = urgentPhoneFact.value;
                            }
                        });
                    });
                }
            });
        });
    </script>
    <?php
}

// 3. Validation des champs d'adhérents WAM
add_action('woocommerce_checkout_process', 'wamv1_validate_adherent_fields');

function wamv1_validate_adherent_fields()
{
    $items = WC()->cart->get_cart();
    $cart_count = WC()->cart->get_cart_contents_count();
    $is_solo = ($cart_count === 1);
    $has_only_stages = wamv1_cart_has_only_stages();

    // 1. Validation du contact d'urgence Billing (Uniquement si pas que des stages)
    if (!$has_only_stages) {
        if (empty($_POST['billing_urgent_name'])) {
            wc_add_notice('Le contact d\'urgence pour l\'adhérent·e principal·e est obligatoire.', 'error');
        }
        if (empty($_POST['billing_urgent_phone'])) {
            wc_add_notice('Le téléphone du contact d\'urgence pour l\'adhérent·e principal·e est obligatoire.', 'error');
        } elseif (!preg_match('/^[0-9\s\.\-\+\(\)]+$/', $_POST['billing_urgent_phone'])) {
            wc_add_notice('Le numéro de téléphone d\'urgence (adhérent·e principal·e) n\'est pas valide.', 'error');
        }
    }

    // 2. Validation des participants (si multi)
    if ($is_solo) return;

    $index = 2;
    foreach ($items as $cart_item_key => $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        if (!$product)
            continue;

        $is_stage = wamv1_is_stage_item($cart_item);
        $course_id = $cart_item['wam_course_id'] ?? null;
        $course_title = $course_id ? get_the_title($course_id) : $product->get_name();
        $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;
        $tarif_label = $cart_item['wam_tarif_label'] ?? '';

        $nb_participants_base = 1;
        $duo_keywords = ['duo', 'parent', 'enfant', 'accompagnant', 'couple', '2 personnes'];
        foreach ($duo_keywords as $key) {
            if (stripos($tarif_label, $key) !== false) {
                $nb_participants_base = 2;
                break;
            }
        }

        $qty = $cart_item['quantity'] ?? 1;
        $total_p = $nb_participants_base * $qty;

        for ($p = 1; $p <= $total_p; $p++) {
            $field_key_suffix = ($p > 1) ? "_p$p" : "";
            $item_desc = '<strong>Participant·e ' . $index . '</strong> (' . $course_title . ($course_subtitle ? ' — ' . $course_subtitle : '') . ')';

            if (empty($_POST['wam_prenom_eleve_' . $cart_item_key . $field_key_suffix])) {
                wc_add_notice('Le prénom pour ' . $item_desc . ' est obligatoire.', 'error');
            }
            if (empty($_POST['wam_nom_eleve_' . $cart_item_key . $field_key_suffix])) {
                wc_add_notice('Le nom pour ' . $item_desc . ' est obligatoire.', 'error');
            }

            if ($is_stage) {
                if (empty($_POST['wam_tel_participant_' . $cart_item_key . $field_key_suffix])) {
                    wc_add_notice('Le téléphone pour ' . $item_desc . ' est obligatoire.', 'error');
                }
            } else {
                $u1_name_key = 'wam_urgent_name_1_' . $cart_item_key . $field_key_suffix;
                $u1_phone_key = 'wam_urgent_phone_1_' . $cart_item_key . $field_key_suffix;

                if (empty($_POST[$u1_name_key])) {
                    wc_add_notice('Le nom du contact d\'urgence 1 pour ' . $item_desc . ' est obligatoire.', 'error');
                }
                if (empty($_POST[$u1_phone_key])) {
                    wc_add_notice('Le téléphone du contact d\'urgence 1 pour ' . $item_desc . ' est obligatoire.', 'error');
                } elseif (!preg_match('/^[0-9\s\.\-\+\(\)]+$/', $_POST[$u1_phone_key])) {
                    wc_add_notice('Le numéro de téléphone pour ' . $item_desc . ' n\'est pas valide.', 'error');
                }
            }
            $index++;
        }
    }
}

// 4. Mettre les Méta-données sur CHAQUE Ligne de Commande (Item)
add_action('woocommerce_checkout_create_order_line_item', 'wamv1_save_adherent_to_order_items', 20, 4);

function wamv1_save_adherent_to_order_items($item, $cart_item_key, $values, $order)
{
    $cart_count = WC()->cart->get_cart_contents_count();
    $is_solo = ($cart_count === 1);
    $is_stage = wamv1_is_stage_item($values);

    if ($is_solo) {
        // En mode solo, on tire les infos directement de la facturation (billing)
        $item->add_meta_data('Prénom', sanitize_text_field($_POST['billing_first_name'] ?? ''), true);
        $item->add_meta_data('Nom', sanitize_text_field($_POST['billing_last_name'] ?? ''), true);
        
        if ($is_stage) {
            $item->add_meta_data('Téléphone', sanitize_text_field($_POST['billing_phone'] ?? ''), true);
        } else {
            $item->add_meta_data('Urgence 1 - Nom', sanitize_text_field($_POST['billing_urgent_name'] ?? ''), true);
            $item->add_meta_data('Urgence 1 - Tél', sanitize_text_field($_POST['billing_urgent_phone'] ?? ''), true);
        }
    } else {
        // Mode Multi-participants
        // Note: WC appelle ce hook pour CHAQUE item de commande.
        // On récupère le nombre de participants pour CET item.
        $tarif_label = $values['wam_tarif_label'] ?? '';
        $nb_participants_base = 1;
        $duo_keywords = ['duo', 'parent', 'enfant', 'accompagnant', 'couple', '2 personnes'];
        foreach ($duo_keywords as $key) {
            if (stripos($tarif_label, $key) !== false) {
                $nb_participants_base = 2;
                break;
            }
        }

        $qty = $values['quantity'] ?? 1;
        $total_p = $nb_participants_base * $qty;

        for ($p = 1; $p <= $total_p; $p++) {
            $suffix = ($p > 1) ? "_p$p" : "";
            $meta_prefix = ($total_p > 1) ? "P$p - " : "";

            $prenom_key = 'wam_prenom_eleve_' . $cart_item_key . $suffix;
            $nom_key = 'wam_nom_eleve_' . $cart_item_key . $suffix;
            
            if (isset($_POST[$prenom_key])) {
                $item->add_meta_data($meta_prefix . 'Prénom', sanitize_text_field($_POST[$prenom_key]), true);
            }
            if (isset($_POST[$nom_key])) {
                $item->add_meta_data($meta_prefix . 'Nom', sanitize_text_field($_POST[$nom_key]), true);
            }

            if ($is_stage) {
                $tel_key = 'wam_tel_participant_' . $cart_item_key . $suffix;
                if (isset($_POST[$tel_key])) {
                    $item->add_meta_data($meta_prefix . 'Téléphone', sanitize_text_field($_POST[$tel_key]), true);
                }
            } else {
                $u1_n = 'wam_urgent_name_1_' . $cart_item_key . $suffix;
                $u1_t = 'wam_urgent_phone_1_' . $cart_item_key . $suffix;
                $u2_n = 'wam_urgent_name_2_' . $cart_item_key . $suffix;
                $u2_t = 'wam_urgent_phone_2_' . $cart_item_key . $suffix;

                if (isset($_POST[$u1_n])) $item->add_meta_data($meta_prefix . 'Urgence 1 - Nom', sanitize_text_field($_POST[$u1_n]), true);
                if (isset($_POST[$u1_t])) $item->add_meta_data($meta_prefix . 'Urgence 1 - Tél', sanitize_text_field($_POST[$u1_t]), true);
                if (isset($_POST[$u2_n])) $item->add_meta_data($meta_prefix . 'Urgence 2 - Nom', sanitize_text_field($_POST[$u2_n]), true);
                if (isset($_POST[$u2_t])) $item->add_meta_data($meta_prefix . 'Urgence 2 - Tél', sanitize_text_field($_POST[$u2_t]), true);
            }
        }
    }

    // SAUVEGARDE DES IDS CRITIQUES
    if (!empty($values['wam_course_id'])) {
        $item->add_meta_data('_wam_course_id', $values['wam_course_id'], true);
    }
    if (!empty($values['wam_tarif_index'])) {
        $item->add_meta_data('_wam_tarif_index', $values['wam_tarif_index'], true);
    }

    // AMÉLIORATION DU NOM DE L'ARTICLE (AVEC CACHE TRANSIENT)
    $c_id = $values['wam_course_id'] ?? null;
    if ($c_id) {
        $transient_key = 'wam_course_meta_' . $c_id;
        $cached_data = get_transient($transient_key);

        if (false === $cached_data) {
            $cached_data = [
                'subtitle' => get_field('sous_titre', $c_id),
                'day'      => get_field('jour_de_cours', $c_id),
                'h_start'  => get_field('heure_debut', $c_id),
                'h_end'    => get_field('heure_de_fin', $c_id),
            ];
            // Cache pour 12 heures
            set_transient($transient_key, $cached_data, 12 * HOUR_IN_SECONDS);
        }

        $name_suffix = [];
        if (!empty($cached_data['subtitle'])) $name_suffix[] = $cached_data['subtitle'];
        if (!empty($cached_data['day']))      $name_suffix[] = wamv1_get_day_label($cached_data['day']);
        if (!empty($cached_data['h_start']))  $name_suffix[] = "{$cached_data['h_start']}–{$cached_data['h_end']}";

        if (!empty($name_suffix)) {
            $item->set_name($item->get_name() . ' (' . implode(' - ', $name_suffix) . ')');
        }
    }
}

/**
 * 5. Sauvegarder les contacts d'urgence de facturation dans les méta de commande "propres"
 */
add_action('woocommerce_checkout_update_order_meta', 'wamv1_save_billing_emergency_to_order_meta');
function wamv1_save_billing_emergency_to_order_meta($order_id) {
    if (!empty($_POST['billing_urgent_name'])) {
        update_post_meta($order_id, 'Contact Urgence - Nom', sanitize_text_field($_POST['billing_urgent_name']));
    }
    if (!empty($_POST['billing_urgent_phone'])) {
        update_post_meta($order_id, 'Contact Urgence - Tél', sanitize_text_field($_POST['billing_urgent_phone']));
    }
}

/**
 * Vérifie si le panier contient uniquement des stages
 */
function wamv1_cart_has_only_stages() {
    if (!WC()->cart) return false;
    $items = WC()->cart->get_cart();
    if (empty($items)) return false;

    foreach ($items as $item) {
        $course_id = $item['wam_course_id'] ?? null;
        if (!$course_id || get_post_type($course_id) !== 'stages') {
            return false;
        }
    }
    return true;
}

/**
 * Vérifie si un item spécifique est un stage
 */
function wamv1_is_stage_item($cart_item) {
    $course_id = $cart_item['wam_course_id'] ?? null;
    return $course_id && get_post_type($course_id) === 'stages';
}

// ============================================================================
// K. Allègement du formulaire et blocage MailPoet par défaut
// ============================================================================

add_filter('woocommerce_checkout_fields', 'wamv1_simplify_checkout_fields');

function wamv1_simplify_checkout_fields($fields)
{
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_state']);

    // Si on n'a que des stages, on retire aussi l'adresse physique (Brief WAM : pas besoin du reste)
    if (wamv1_cart_has_only_stages()) {
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_country']);
    }
    
    // On garde le téléphone billing pour l'adhérent principal
    $fields['billing']['billing_phone']['priority'] = 100;
    $fields['billing']['billing_email']['priority'] = 110;
    
    return $fields;
}

/**
 * Ajout manuel du bloc d'urgence pour l'adhérent principal
 * On le place en fin de formulaire de facturation (priority 5)
 * pour qu'il soit avant le chargement des participants (priority 10)
 */
add_action('woocommerce_after_checkout_billing_form', 'wamv1_add_billing_emergency_block', 5);
function wamv1_add_billing_emergency_block($checkout) {
    if (wamv1_cart_has_only_stages()) {
        return;
    }
    echo '<div class="wam-emergency-block">';
    echo '<p class="wam-emergency-block__title">Contact d\'urgence</p>';
    echo '<div class="wam-adherent-card__fields">';
    
    woocommerce_form_field('billing_urgent_name', [
        'type'        => 'text',
        'label'       => 'Nom et prénom',
        'placeholder' => 'Ex: Jean Dupont',
        'required'    => true,
        'class'       => ['form-row-first'],
        'default'     => $checkout->get_value('billing_urgent_name')
    ], $checkout->get_value('billing_urgent_name'));

    woocommerce_form_field('billing_urgent_phone', [
        'type'        => 'tel',
        'label'       => 'Téléphone',
        'placeholder' => 'Ex: 06 12 34 56 78',
        'required'    => true,
        'class'       => ['form-row-last'],
        'custom_attributes' => [
            'pattern' => '[0-9\s\.\-\+\(\)]*',
            'inputmode' => 'tel'
        ],
        'default'     => $checkout->get_value('billing_urgent_phone')
    ], $checkout->get_value('billing_urgent_phone'));

    echo '</div>'; // .wam-adherent-card__fields
    echo '</div>'; // .wam-emergency-block
}

// S'assurer que le champ Pays par defaut est la France
add_filter('default_checkout_billing_country', function () {
    return 'FR';
});

// Retrait du formulaire de code promo sur la page Checkout
remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);

// ============================================================================
// L. Nettoyages Visuels et Traductions (Feedback Utilisateur)
// ============================================================================

// 1. Réactiver l'optin MailPoet pour permettre le stylisage demandé par le client
// (Le filtre __return_false a été supprimé)

// 2. Renommer le titre de la page "Checkout" directement au moment du rendu
add_filter('the_title', 'wamv1_rename_checkout_title', 10, 2);

function wamv1_rename_checkout_title($title, $id = null)
{
    if ((is_checkout() && !is_wc_endpoint_url()) && in_the_loop() && (strtolower($title) === 'checkout' || strtolower($title) === 'validation de la commande')) {
        return 'Informations de commandes';
    }
    return $title;
}

// 3. Déplacer le module de paiement hors du récapitulatif (on l'appelle manuellement dans form-checkout.php)
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);

// Renommage du titre de facturation (Détails de facturation -> Adhérent·e principal·e)
add_filter('gettext', function ($translated_text, $text, $domain) {
    if ($domain === 'woocommerce' && ($text === 'Billing details')) {
        return 'Adhérent·e principal·e';
    }
    return $translated_text;
}, 20, 3);

// Ajout du sous-titre "Informations de facturation" sous le titre Adhérent·e principal·e
add_action('woocommerce_before_checkout_billing_form', function () {
    echo '<p class="subtext text-sm mb-md color-subtext">Informations de facturation</p>';
}, 5);

// ============================================================================
// M. Tunnel de commande : Connexion & Inscription
//    Forcer la connexion avant le checkout et enrichir l'inscription
// ============================================================================

/**
 * 1. Forcer la connexion pour accéder au checkout
 */
add_action('template_redirect', 'wamv1_wc_force_login_checkout');
function wamv1_wc_force_login_checkout() {
    if (is_checkout() && !is_user_logged_in() && !is_wc_endpoint_url('order-pay') && !is_wc_endpoint_url('order-received')) {
        $login_url = wc_get_page_permalink('myaccount');
        $redirect_url = add_query_arg('redirect', wc_get_checkout_url(), $login_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
}

/**
 * 2. Ajouter les champs Prénom et Nom au formulaire d'inscription
 */
add_action('woocommerce_register_form_start', 'wamv1_wc_add_registration_fields');
function wamv1_wc_add_registration_fields() {
    ?>
    <div class="wam-registration-fields">
        <p class="form-row form-row-wide">
            <label for="reg_billing_first_name"><?php _e('Prénom', 'wamv1'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name" value="<?php if (!empty($_POST['billing_first_name'])) echo esc_attr($_POST['billing_first_name']); ?>" required />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_last_name"><?php _e('Nom', 'wamv1'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="billing_last_name" id="reg_billing_last_name" value="<?php if (!empty($_POST['billing_last_name'])) echo esc_attr($_POST['billing_last_name']); ?>" required />
        </p>
        <div class="clear"></div>
    </div>
    <?php
}

/**
 * 3. Valider les champs d'inscription
 */
add_filter('woocommerce_registration_errors', 'wamv1_wc_validate_registration_fields', 10, 3);
function wamv1_wc_validate_registration_fields($errors, $username, $email) {
    if (empty($_POST['billing_first_name'])) {
        $errors->add('billing_first_name_error', __('Le prénom est obligatoire.', 'wamv1'));
    }
    if (empty($_POST['billing_last_name'])) {
        $errors->add('billing_last_name_error', __('Le nom est obligatoire.', 'wamv1'));
    }
    return $errors;
}

/**
 * 4. Enregistrer les champs d'inscription dans le profil billing
 */
add_action('woocommerce_created_customer', 'wamv1_wc_save_registration_fields');
function wamv1_wc_save_registration_fields($customer_id) {
    if (isset($_POST['billing_first_name'])) {
        update_user_meta($customer_id, 'billing_first_name', sanitize_text_field($_POST['billing_first_name']));
        update_user_meta($customer_id, 'first_name', sanitize_text_field($_POST['billing_first_name']));
    }
    if (isset($_POST['billing_last_name'])) {
        update_user_meta($customer_id, 'billing_last_name', sanitize_text_field($_POST['billing_last_name']));
        update_user_meta($customer_id, 'last_name', sanitize_text_field($_POST['billing_last_name']));
    }
}

/**
 * 5. Redirection intelligente après login/register si un redirect est présent
 */
add_filter('woocommerce_login_redirect', 'wamv1_wc_custom_login_redirect', 10, 2);
add_filter('woocommerce_registration_redirect', 'wamv1_wc_custom_login_redirect', 10, 2);
function wamv1_wc_custom_login_redirect($redirect, $user) {
    if (!empty($_REQUEST['redirect'])) {
        return wp_validate_redirect($_REQUEST['redirect'], $redirect);
    }
    return $redirect;
}
// ============================================================================
// N. Correctif Navigation : Éviter le "Resubmit Form" sur le Panier
// ============================================================================

/**
 * Nettoie l'historique du navigateur sur la page panier pour éviter le message
 * "Confirmer le nouvel envoi du formulaire" lors d'un retour arrière depuis le checkout.
 */
add_action('wp_footer', function() {
    if (is_cart()) {
        ?>
        <script>
        if ( window.history.replaceState ) {
            // Remplace l'entrée POST dans l'historique par une entrée GET propre
            window.history.replaceState( null, null, window.location.href );
        }
        </script>
        <?php
    }
}, 99);

/**
 * Ajouter la mention sur le droit à l'image dans le tableau de bord "Mon Compte"
 */
add_action('woocommerce_account_dashboard', function() {
    echo '<div class="wam-account-image-notice mt-xl p-lg" style="background: var(--wam-color-card-bg); border: 1px solid var(--wam-color-input-bg); border-radius: var(--wam-radius-xl);">';
    echo '<h4 class="title-norm-sm mb-xs">Droit à l\'image</h4>';
    echo '<p class="text-sm color-subtext">L\'inscription aux cours ou stages WAM implique votre consentement au droit à l\'image pour les besoins de communication du studio. Si vous souhaitez vous opposer à l\'utilisation de votre image (ou celle de votre enfant), vous pouvez nous contacter à tout moment à <a href="mailto:contact@wamdancestudio.fr" class="color-yellow">contact@wamdancestudio.fr</a>.</p>';
    echo '</div>';
});

// ============================================================================
// L. Accessibilité des Formulaires — aria-describedby pour les erreurs
// ============================================================================

/**
 * Ajoute aria-describedby à tous les champs WC pour pointer vers un conteneur d'erreur.
 * Cela permet aux lecteurs d'écran d'associer sémantiquement le champ à son erreur.
 */
add_filter('woocommerce_form_field', 'wamv1_add_aria_describedby_to_fields', 10, 4);
function wamv1_add_aria_describedby_to_fields($field, $key, $args, $value) {
    // On génère un ID unique pour le message d'erreur
    $error_id = $key . '_error';
    
    // On injecte aria-describedby dans le tag de saisie (input, select ou textarea)
    if (strpos($field, '<input') !== false) {
        $field = str_replace('<input', '<input aria-describedby="' . esc_attr($error_id) . '"', $field);
    } elseif (strpos($field, '<select') !== false) {
        $field = str_replace('<select', '<select aria-describedby="' . esc_attr($error_id) . '"', $field);
    } elseif (strpos($field, '<textarea') !== false) {
        $field = str_replace('<textarea', '<textarea aria-describedby="' . esc_attr($error_id) . '"', $field);
    }
    
    // On ajoute le conteneur d'erreur à la fin du markup du champ.
    // Ce conteneur sera rempli dynamiquement (ou via PHP au rechargement) si une erreur existe.
    $error_html = '<span id="' . esc_attr($error_id) . '" class="wam-field-error sr-only" role="alert"></span>';
    
    // On l'insère juste avant la fermeture du paragraphe form-row si possible
    if (strpos($field, '</p>') !== false) {
        $field = str_replace('</p>', $error_html . '</p>', $field);
    } else {
        $field .= $error_html;
    }
    
    return $field;
}

/**
 * 6. Vider le cache (transient) d'un cours/stage lors de sa mise à jour
 */
add_action('save_post', 'wamv1_clear_course_cache', 10, 3);
function wamv1_clear_course_cache($post_id, $post, $update) {
    if (!$update || !in_array($post->post_type, ['cours', 'stages'])) {
        return;
    }
    delete_transient('wam_course_meta_' . $post_id);
}
