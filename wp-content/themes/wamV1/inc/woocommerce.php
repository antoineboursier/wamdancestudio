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

/**
 * Personnaliser le titre du Hero sur la page de remerciement (Thank You)
 */
add_filter('the_title', 'wamv1_wc_thankyou_title', 10, 2);
function wamv1_wc_thankyou_title($title, $id) {
    if (!is_admin() && is_checkout() && is_wc_endpoint_url('order-received') && $id === get_the_ID() && in_the_loop() && is_main_query()) {
        return 'Commande reçue';
    }
    return $title;
}

add_filter('woocommerce_cart_totals_coupon_html', 'wamv1_custom_coupon_html', 10, 2);
function wamv1_custom_coupon_html($coupon_html, $coupon) {
    return str_replace('[Enlever]', 'Supprimer', $coupon_html);
}

// ============================================================================
// B. Retirer les endpoints inutiles du menu Mon compte
//    Adresses et Téléchargements ne sont pas pertinents pour une école de danse
// ============================================================================

add_filter('woocommerce_account_menu_items', 'wamv1_wc_account_menu_items');

function wamv1_wc_account_menu_items(array $items): array
{
    // On masque edit-address du menu (accessible via edit-account)
    unset($items['edit-address']);
    unset($items['downloads']);    // Téléchargements — pas de produits numériques

    // Renommage
    if (isset($items['edit-account'])) {
        $items['edit-account'] = 'Informations personnelles';
    }

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
    
    if (!is_array($grp)) return [];

    // Tarif 1 est considéré comme toujours actif si son nom est rempli
    // Tarif 2 et 3 ont des interrupteurs (true_false) dans l'admin
    foreach ([2, 3] as $i) {
        $is_active = !empty($grp['tarif_' . $i]);
        if (!$is_active) {
            $grp['nom_tarif_' . $i] = '';
            $grp['prix_tarif_' . $i] = '';
            $grp['quota_tarif_' . $i] = 0;
            $grp['quota_reserve_' . $i] = 0;
        }
    }

    return $grp;
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

/**
 * Valider l'intégrité du panier par rapport aux données ACF (tarifs et statut complet).
 * Si un tarif est retiré ou qu'un stage devient complet, on nettoie le panier.
 */
add_action('woocommerce_check_cart_items', 'wamv1_validate_cart_integrity');
function wamv1_validate_cart_integrity()
{
    if (is_admin() && !defined('DOING_AJAX')) return;

    $cart = WC()->cart;
    if (!$cart) return;

    $items_removed = false;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $cpt_id = $cart_item['wam_course_id'] ?? null;
        if (!$cpt_id) continue;

        // 0. Vérifier si le cours/stage est toujours publié
        if (get_post_status($cpt_id) !== 'publish') {
            $cart->remove_cart_item($cart_item_key);
            $items_removed = true;
            continue;
        }

        // 1. Vérifier si le cours/stage est devenu complet (switch global ACF)
        if (function_exists('get_field') && get_field('complete_cours', $cpt_id)) {
            $cart->remove_cart_item($cart_item_key);
            $items_removed = true;
            continue;
        }

        // 2. Pour les stages : vérifier la validité du tarif sélectionné
        if (get_post_type($cpt_id) === 'stages') {
            $tarif_idx = $cart_item['wam_tarif_index'] ?? null;
            if ($tarif_idx) {
                $grp = function_exists('wamv1_stage_tarifs') ? wamv1_stage_tarifs((int) $cpt_id) : [];
                $label = $grp['nom_tarif_' . $tarif_idx] ?? '';

                // Si le libellé est vide, c'est que le tarif a été supprimé ou désactivé
                if (!$label) {
                    $cart->remove_cart_item($cart_item_key);
                    $items_removed = true;
                }
            }
        }
    }

    if ($items_removed) {
        wc_add_notice('Certains articles de votre panier ne sont plus disponibles et ont été retirés.', 'error');
    }
}


// ============================================================================
// Quotas — Décompte automatique des places lors de la validation de commande
// ============================================================================

// Décompte dès la création de la commande (avant paiement) pour bloquer les places
// immédiatement et éviter la surréservation (ex : virement bancaire).
add_action('woocommerce_checkout_order_created', 'wamv1_decrement_course_quota_on_payment');

// Restauration des places si la commande est annulée, échouée ou remboursée
add_action('woocommerce_order_status_cancelled', 'wamv1_restore_course_quota_on_cancellation');
add_action('woocommerce_order_status_refunded', 'wamv1_restore_course_quota_on_cancellation');
add_action('woocommerce_order_status_failed', 'wamv1_restore_course_quota_on_cancellation');

function wamv1_decrement_course_quota_on_payment( $order_or_id ): void
{
    // Le hook woocommerce_checkout_order_created passe un objet WC_Order,
    // les anciens hooks passaient un int. On supporte les deux.
    $order = is_int( $order_or_id ) ? wc_get_order( $order_or_id ) : $order_or_id;
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

            // OG : changé +1 par get_quantity() pour prendre en compte le nombre réel de places réservées dans une seule commande
            $new_reserve = (int) ($grp[$reserve_key] ?? 0) + $item->get_quantity();
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
            
            $new_reserve = max(0, (int) ($grp[$reserve_key] ?? 0) - $item->get_quantity());
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
    $selections_raw = isset($_POST['selections']) ? wp_unslash($_POST['selections']) : null;
    $selections = is_array($selections_raw) ? wc_clean($selections_raw) : (is_string($selections_raw) ? json_decode($selections_raw, true) : null);
    $added      = 0;

    if ($selections && is_array($selections)) {
        // Récupérer les quotas du stage pour validation serveur
        $grp_quota = $course_id ? (function_exists('wamv1_stage_tarifs') ? wamv1_stage_tarifs($course_id) : (get_field('tarifs', $course_id) ?: [])) : [];

        foreach ($selections as $sel) {
            $t_idx = absint($sel['tarif_index'] ?? 0);
            $qty   = absint($sel['qty'] ?? 0);
            if ($qty <= 0 || !$t_idx) continue;

            // Validation quota côté serveur (protection contre contournement JS)
            if ($course_id && !empty($grp_quota)) {
                $total_key   = 'quota_tarif_'   . $t_idx;
                $reserve_key = 'quota_reserve_' . $t_idx;
                $total   = (int) ($grp_quota[$total_key]   ?? 0);
                $reserve = (int) ($grp_quota[$reserve_key] ?? 0);

                // Compter ce qui est déjà dans le panier pour ce stage + ce tarif
                $en_panier = 0;
                foreach (WC()->cart->get_cart() as $cart_item) {
                    if (($cart_item['wam_course_id'] ?? 0) == $course_id
                        && ($cart_item['wam_tarif_index'] ?? 0) == $t_idx) {
                        $en_panier += $cart_item['quantity'];
                    }
                }

                $dispo = $total > 0 ? max(0, $total - $reserve - $en_panier) : PHP_INT_MAX;

                if ($qty > $dispo) {
                    wp_send_json_error(['message' => sprintf(
                        'Il ne reste que %d place(s) disponible(s) pour ce tarif.',
                        $dispo
                    )]);
                }
            }

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

// OG — refonte complète de l'affichage des champs checkout :
// - stages  → toujours afficher les participants (1 fiche/place, index à partir de 1)
// - cours   → logique Antoine inchangée (contact urgence, index à partir de 2)
// - autres  → produits simples WC (carte cadeau, EVJF via Bookly...) : rien à afficher
function wamv1_add_adherent_fields_to_checkout($checkout)
{
    if (WC()->cart->is_empty()) return;

    $items = WC()->cart->get_cart();

    // Séparer les items par type de CPT
    $stage_items = [];
    $cours_items = [];

    foreach ($items as $key => $item) {
        $cpt_id = $item['wam_course_id'] ?? null;
        if (!$cpt_id) continue; // Produit WC simple (carte cadeau, formule mariage...) → rien
        $type = get_post_type($cpt_id);
        if ($type === 'stages')    $stage_items[$key] = $item;
        elseif ($type === 'cours') $cours_items[$key] = $item;
    }

    // Section cours désactivée temporairement — logique à redéfinir.
    $is_solo = true;

    // -------------------------------------------------------------------------
    // STAGES — toujours afficher les champs participants, 1 fiche par place
    // -------------------------------------------------------------------------
    if (!empty($stage_items)) {
        echo '<div id="wam-participants-stages" class="wam-adherents-section mt-xl">';
        echo '<h3 class="title-norm-sm color-green mb-md">Les participants</h3>';

        $current_stage_id = null;
        $index = 1;
        foreach ($stage_items as $cart_item_key => $cart_item) {
            $qty             = $cart_item['quantity'] ?? 1;
            $cpt_id          = $cart_item['wam_course_id'];
            $course_title    = get_the_title($cpt_id);
            $course_subtitle = get_field('sous_titre', $cpt_id);
            $tarif_label     = $cart_item['wam_tarif_label'] ?? '';

            // Repartir à 1 à chaque nouveau stage
            if ($cpt_id !== $current_stage_id) {
                $index = 1;
                $current_stage_id = $cpt_id;
            }

            for ($p = 1; $p <= $qty; $p++) {
                $field_key_suffix = ($p > 1) ? "_p$p" : "";

                echo '<div class="wam-adherent-group wam-adherent-card" data-stage-id="' . esc_attr($cpt_id) . '">';
                echo '<div class="wam-adherent-card__header">';
                echo '<p class="text-md mb-0">Participant·e ' . $index . '</p>';
                echo '<div class="wam-adherent-card__course-info text-right">';
                echo '<h4 class="text-md color-yellow fw-bold m-0">' . esc_html($course_title) . '</h4>';
                if ($course_subtitle) echo '<p class="text-xs color-subtext m-0">' . esc_html($course_subtitle) . '</p>';
                if ($tarif_label)     echo '<p class="text-sm color-green fw-bold mb-0">' . esc_html($tarif_label) . '</p>';
                echo '</div></div>';

                // Checkbox "Utiliser mes informations" sur chaque fiche — non pré-cochée
                // car on ne sait pas quel participant est l'acheteur
                // (ex: billet enfant en premier, ou 2 billets du même tarif)
                echo '<p class="form-row form-row-wide wam-adherent-auto-fill mb-md">';
                echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
                echo '<input type="checkbox" class="wam-is-buyer-checkbox woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wam_is_buyer_' . $cart_item_key . $field_key_suffix . '" value="1" /> ';
                echo '<span>Cette place est la mienne</span>';
                echo '</label></p>';

                echo '<div class="wam-adherent-card__fields">';

                woocommerce_form_field('wam_prenom_eleve_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'text', 'class' => ['form-row-first validated'], 'label' => 'Prénom', 'required' => true,
                ], $checkout->get_value('wam_prenom_eleve_' . $cart_item_key . $field_key_suffix));

                woocommerce_form_field('wam_nom_eleve_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'text', 'class' => ['form-row-last validated'], 'label' => 'Nom', 'required' => true,
                ], $checkout->get_value('wam_nom_eleve_' . $cart_item_key . $field_key_suffix));

                echo '</div><div class="clear"></div></div>';
                $index++;
            }
        }

        echo '<div class="wam-rgpd-info mt-md">';
        echo '<p class="text-xs color-subtext mb-xs">Les informations nominatives sont collectées exclusivement pour assurer le suivi et la sécurité des participant·es. Ces données sont conservées pour une durée maximale de 2 ans.</p>';
        echo '<p class="text-xs color-subtext">L\'inscription aux stages WAM implique le consentement au droit à l\'image. Vous pouvez refuser l\'utilisation de votre image sur simple demande à <a href="mailto:contact@wamdancestudio.fr" class="color-yellow">contact@wamdancestudio.fr</a>.</p>';
        echo '</div>';
        echo '</div>'; // #wam-participants-stages
    }

    // -------------------------------------------------------------------------
    // COURS — logique Antoine inchangée (contact urgence, index à partir de 2)
    // -------------------------------------------------------------------------
    if (!empty($cours_items) && !$is_solo) {
        echo '<div id="wam-adherents-fields" class="wam-adherents-section mt-xl">';
        echo '<h3 class="title-norm-sm color-green mb-md">Les adhérent·es</h3>';

        $index = 2;

        foreach ($cours_items as $cart_item_key => $cart_item) {
            $product = wc_get_product($cart_item['product_id']);
            if (!$product) continue;

            $course_id       = $cart_item['wam_course_id'] ?? null;
            $course_title    = $course_id ? get_the_title($course_id) : $product->get_name();
            $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;
            $tarif_label     = $cart_item['wam_tarif_label'] ?? '';
            $qty             = $cart_item['quantity'] ?? 1;

            for ($p = 1; $p <= $qty; $p++) {
                $field_key_suffix = ($p > 1) ? "_p$p" : "";

                echo '<div class="wam-adherent-group wam-adherent-card">';
                echo '<div class="wam-adherent-card__header">';
                echo '<p class="text-md mb-0">Participant·e ' . $index . '</p>';
                echo '<div class="wam-adherent-card__course-info text-right">';
                echo '<h4 class="text-md color-yellow fw-bold m-0">' . esc_html($course_title) . '</h4>';
                if ($course_subtitle) echo '<p class="text-xs color-subtext m-0">' . esc_html($course_subtitle) . '</p>';
                if ($tarif_label)     echo '<p class="text-sm color-green fw-bold mb-0">' . esc_html($tarif_label) . '</p>';
                echo '</div></div>';

                if ($p === 1) {
                    echo '<p class="form-row form-row-wide wam-adherent-auto-fill mb-md">';
                    echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
                    echo '<input type="checkbox" class="wam-is-buyer-checkbox woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wam_is_buyer_' . $cart_item_key . '" value="1" /> ';
                    echo '<span>Utiliser les informations de l\'adhérent·e principal·e</span>';
                    echo '</label></p>';
                }
                $index++;

                echo '<div class="wam-adherent-card__fields">';

                woocommerce_form_field('wam_prenom_eleve_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'text', 'class' => ['form-row-first validated'], 'label' => 'Prénom', 'required' => true,
                ], $checkout->get_value('wam_prenom_eleve_' . $cart_item_key . $field_key_suffix));

                woocommerce_form_field('wam_nom_eleve_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'text', 'class' => ['form-row-last validated'], 'label' => 'Nom', 'required' => true,
                ], $checkout->get_value('wam_nom_eleve_' . $cart_item_key . $field_key_suffix));

                echo '</div>';

                // Contact d'urgence (cours uniquement)
                echo '<div class="wam-emergency-block"><p class="wam-emergency-block__title">Contact d\'urgence</p>';
                echo '<div class="wam-adherent-card__fields">';

                woocommerce_form_field('wam_urgent_name_1_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'text', 'label' => 'Nom et prénom', 'required' => true, 'class' => ['form-row-first'],
                ], $checkout->get_value('wam_urgent_name_1_' . $cart_item_key . $field_key_suffix));

                woocommerce_form_field('wam_urgent_phone_1_' . $cart_item_key . $field_key_suffix, [
                    'type' => 'tel', 'label' => 'Téléphone', 'required' => true, 'class' => ['form-row-last'],
                ], $checkout->get_value('wam_urgent_phone_1_' . $cart_item_key . $field_key_suffix));

                echo '</div></div>';

                $is_minor = has_term(['enfants', 'ados'], 'product_cat', $cart_item['product_id']);
                if ($is_minor) {
                    echo '<div class="wam-emergency-block"><p class="wam-emergency-block__title">Second contact d\'urgence (Optionnel)</p>';
                    echo '<div class="wam-adherent-card__fields">';

                    woocommerce_form_field('wam_urgent_name_2_' . $cart_item_key . $field_key_suffix, [
                        'type' => 'text', 'label' => 'Nom et prénom', 'required' => false, 'class' => ['form-row-first'],
                    ], $checkout->get_value('wam_urgent_name_2_' . $cart_item_key . $field_key_suffix));

                    woocommerce_form_field('wam_urgent_phone_2_' . $cart_item_key . $field_key_suffix, [
                        'type' => 'tel', 'label' => 'Téléphone', 'required' => false, 'class' => ['form-row-last'],
                    ], $checkout->get_value('wam_urgent_phone_2_' . $cart_item_key . $field_key_suffix));

                    echo '</div></div>';
                }

                echo '<div class="clear"></div></div>';
            }
        }

        echo '<div class="wam-rgpd-info mt-md">';
        echo '<p class="text-xs color-subtext mb-xs">Les informations nominatives et les contacts d\'urgence sont collectés exclusivement pour assurer le suivi pédagogique et la sécurité des participant·es pendant les cours. Ces données sont conservées pour une durée maximale de 2 ans.</p>';
        echo '<p class="text-xs color-subtext">L\'inscription aux cours ou stages WAM implique le consentement au droit à l\'image. Vous pouvez toutefois refuser l\'utilisation de votre image (ou celle de votre enfant) sur simple demande à <a href="mailto:contact@wamdancestudio.fr" class="color-yellow">contact@wamdancestudio.fr</a>.</p>';
        echo '</div>';
        echo '</div>'; // #wam-adherents-fields
    }
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
                // État initial
                if (checkbox.checked) {
                    setTimeout(() => syncFields(checkbox), 100);
                }

                checkbox.addEventListener('change', function () {
                    if (this.checked) {
                        // Décocher les autres cases du MÊME stage uniquement
                        // (on peut participer à plusieurs stages différents)
                        const thisStageId = this.closest('.wam-adherent-card')?.dataset.stageId;
                        checkboxes.forEach(other => {
                            if (other !== this && other.checked) {
                                const otherStageId = other.closest('.wam-adherent-card')?.dataset.stageId;
                                if (otherStageId === thisStageId) {
                                    other.checked = false;
                                    syncFields(other);
                                }
                            }
                        });
                    }
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
// OG — refonte complète, symétrique avec l'affichage :
// - stages  → toujours valider (même 1 seule place), index à partir de 1
// - cours   → valider uniquement si multi (logique Antoine inchangée), index à partir de 2
// - autres  → rien à valider ici
add_action('woocommerce_checkout_process', 'wamv1_validate_adherent_fields');

function wamv1_validate_adherent_fields()
{
    if (WC()->cart->is_empty()) return;

    $items      = WC()->cart->get_cart();
    $cart_count = WC()->cart->get_cart_contents_count();
    $is_solo    = ($cart_count === 1);

    $stage_items = [];
    $cours_items = [];

    foreach ($items as $key => $item) {
        $cpt_id = $item['wam_course_id'] ?? null;
        if (!$cpt_id) continue;
        $type = get_post_type($cpt_id);
        if ($type === 'stages')    $stage_items[$key] = $item;
        elseif ($type === 'cours') $cours_items[$key] = $item;
    }

    // Contact d'urgence billing — requis uniquement si le panier contient des cours
    if (!empty($cours_items)) {
        if (empty($_POST['billing_urgent_name'])) {
            wc_add_notice('Le contact d\'urgence est obligatoire.', 'error');
        }
        $billing_urgent_phone = isset($_POST['billing_urgent_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_urgent_phone'])) : '';
        if (empty($billing_urgent_phone)) {
            wc_add_notice('Le téléphone du contact d\'urgence est obligatoire.', 'error');
        } elseif (!preg_match('/^[0-9\s\.\-\+\(\)]+$/', $billing_urgent_phone)) {
            wc_add_notice('Le numéro de téléphone du contact d\'urgence n\'est pas valide.', 'error');
        }
    }

    // STAGES — toujours valider, même en solo, index à partir de 1
    $index = 1;
    foreach ($stage_items as $cart_item_key => $cart_item) {
        $qty          = $cart_item['quantity'] ?? 1;
        $course_id    = $cart_item['wam_course_id'] ?? null;
        $course_title = $course_id ? get_the_title($course_id) : '';

        for ($p = 1; $p <= $qty; $p++) {
            $suffix    = ($p > 1) ? "_p$p" : "";
            $item_desc = '<strong>Participant·e ' . $index . '</strong> (' . esc_html($course_title) . ')';

            if (empty($_POST['wam_prenom_eleve_' . $cart_item_key . $suffix])) wc_add_notice('Le prénom pour ' . $item_desc . ' est obligatoire.', 'error');
            if (empty($_POST['wam_nom_eleve_'   . $cart_item_key . $suffix])) wc_add_notice('Le nom pour '    . $item_desc . ' est obligatoire.', 'error');
            $index++;
        }
    }

    // COURS — valider uniquement si multi, index à partir de 2
    if ($is_solo || empty($cours_items)) return;

    $index = 2;
    foreach ($cours_items as $cart_item_key => $cart_item) {
        $qty             = $cart_item['quantity'] ?? 1;
        $course_id       = $cart_item['wam_course_id'] ?? null;
        $course_title    = $course_id ? get_the_title($course_id) : '';
        $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;

        for ($p = 1; $p <= $qty; $p++) {
            $suffix    = ($p > 1) ? "_p$p" : "";
            $item_desc = '<strong>Participant·e ' . $index . '</strong> (' . esc_html($course_title) . ($course_subtitle ? ' — ' . esc_html($course_subtitle) : '') . ')';

            if (empty($_POST['wam_prenom_eleve_' . $cart_item_key . $suffix])) wc_add_notice('Le prénom pour ' . $item_desc . ' est obligatoire.', 'error');
            if (empty($_POST['wam_nom_eleve_'   . $cart_item_key . $suffix])) wc_add_notice('Le nom pour '    . $item_desc . ' est obligatoire.', 'error');

            $u1_name_key  = 'wam_urgent_name_1_'  . $cart_item_key . $suffix;
            $u1_phone_key = 'wam_urgent_phone_1_' . $cart_item_key . $suffix;

            if (empty($_POST[$u1_name_key]))  wc_add_notice('Le nom du contact d\'urgence pour '       . $item_desc . ' est obligatoire.', 'error');
            $u1_phone_val = isset($_POST[$u1_phone_key]) ? sanitize_text_field(wp_unslash($_POST[$u1_phone_key])) : '';
            if (empty($u1_phone_val)) wc_add_notice('Le téléphone du contact d\'urgence pour ' . $item_desc . ' est obligatoire.', 'error');
            elseif (!preg_match('/^[0-9\s\.\-\+\(\)]+$/', $u1_phone_val)) wc_add_notice('Le numéro de téléphone pour ' . $item_desc . ' n\'est pas valide.', 'error');
            $index++;
        }
    }
}

// 4. Mettre les Méta-données sur CHAQUE Ligne de Commande (Item)
// OG — stages lisent TOUJOURS depuis les champs formulaire (jamais billing), même en solo.
//      Cours : billing en solo, formulaire en multi (logique Antoine inchangée).
add_action('woocommerce_checkout_create_order_line_item', 'wamv1_save_adherent_to_order_items', 20, 4);

function wamv1_save_adherent_to_order_items($item, $cart_item_key, $values, $order)
{
    $cart_count = WC()->cart->get_cart_contents_count();
    $is_solo    = ($cart_count === 1);
    $is_stage   = wamv1_is_stage_item($values);

    if ($is_stage) {
        // STAGE — toujours lire depuis les champs participants du formulaire
        $qty = $values['quantity'] ?? 1;
        for ($p = 1; $p <= $qty; $p++) {
            $suffix      = ($p > 1) ? "_p$p" : "";
            $meta_prefix = ($qty > 1) ? "P$p - " : "";

            $prenom_key = 'wam_prenom_eleve_' . $cart_item_key . $suffix;
            $nom_key    = 'wam_nom_eleve_'    . $cart_item_key . $suffix;

            if (isset($_POST[$prenom_key])) $item->add_meta_data($meta_prefix . 'Prénom', sanitize_text_field($_POST[$prenom_key]), true);
            if (isset($_POST[$nom_key]))    $item->add_meta_data($meta_prefix . 'Nom',    sanitize_text_field($_POST[$nom_key]),    true);
        }

    } elseif ($is_solo) {
        // COURS / autre, solo — infos depuis la facturation
        $item->add_meta_data('Prénom',          sanitize_text_field($_POST['billing_first_name']   ?? ''), true);
        $item->add_meta_data('Nom',             sanitize_text_field($_POST['billing_last_name']    ?? ''), true);
        $item->add_meta_data('Urgence 1 - Nom', sanitize_text_field($_POST['billing_urgent_name']  ?? ''), true);
        $item->add_meta_data('Urgence 1 - Tél', sanitize_text_field($_POST['billing_urgent_phone'] ?? ''), true);

    } else {
        // COURS / autre, multi — lire depuis les champs formulaire
        $qty     = $values['quantity'] ?? 1;
        $total_p = $qty;

        for ($p = 1; $p <= $total_p; $p++) {
            $suffix      = ($p > 1) ? "_p$p" : "";
            $meta_prefix = ($total_p > 1) ? "P$p - " : "";

            $prenom_key = 'wam_prenom_eleve_' . $cart_item_key . $suffix;
            $nom_key    = 'wam_nom_eleve_'    . $cart_item_key . $suffix;

            if (isset($_POST[$prenom_key])) $item->add_meta_data($meta_prefix . 'Prénom', sanitize_text_field($_POST[$prenom_key]), true);
            if (isset($_POST[$nom_key]))    $item->add_meta_data($meta_prefix . 'Nom',    sanitize_text_field($_POST[$nom_key]),    true);

            $u1_n = 'wam_urgent_name_1_'  . $cart_item_key . $suffix;
            $u1_t = 'wam_urgent_phone_1_' . $cart_item_key . $suffix;
            $u2_n = 'wam_urgent_name_2_'  . $cart_item_key . $suffix;
            $u2_t = 'wam_urgent_phone_2_' . $cart_item_key . $suffix;

            if (isset($_POST[$u1_n])) $item->add_meta_data($meta_prefix . 'Urgence 1 - Nom', sanitize_text_field($_POST[$u1_n]), true);
            if (isset($_POST[$u1_t])) $item->add_meta_data($meta_prefix . 'Urgence 1 - Tél', sanitize_text_field($_POST[$u1_t]), true);
            if (isset($_POST[$u2_n])) $item->add_meta_data($meta_prefix . 'Urgence 2 - Nom', sanitize_text_field($_POST[$u2_n]), true);
            if (isset($_POST[$u2_t])) $item->add_meta_data($meta_prefix . 'Urgence 2 - Tél', sanitize_text_field($_POST[$u2_t]), true);
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
 * Vérifie si le panier contient au moins un cours (CPT 'cours')
 */
function wamv1_cart_has_cours() {
    if (!WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $item) {
        $course_id = $item['wam_course_id'] ?? null;
        if ($course_id && get_post_type($course_id) === 'cours') {
            return true;
        }
    }
    return false;
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
    // Champs supprimés globalement : pas utiles pour WAM
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_state']);

    // billing_country : masqué visuellement et forcé à "FR" (HelloAsso exige un code ISO valide).
    // Les champs address_1 / city / postcode sont conservés et affichés pour tous les produits :
    // ils sont nécessaires pour HelloAsso et modifiables dans Mon compte → Mes adresses.
    $fields['billing']['billing_country']['class']    = ['form-row-wide', 'wam-hidden-field'];
    $fields['billing']['billing_country']['required'] = false;
    $fields['billing']['billing_country']['label']    = '';

    // Adresse — libellé FR, pleine largeur, requis
    $fields['billing']['billing_address_1']['label']       = 'Adresse';
    $fields['billing']['billing_address_1']['placeholder'] = 'Ex : 12 rue des Lilas';
    $fields['billing']['billing_address_1']['class']       = ['form-row-wide'];
    $fields['billing']['billing_address_1']['required']    = true;
    $fields['billing']['billing_address_1']['priority']    = 70;

    // Ville — moitié gauche, requis
    $fields['billing']['billing_city']['label']       = 'Ville';
    $fields['billing']['billing_city']['placeholder'] = 'Ex : Bordeaux';
    $fields['billing']['billing_city']['class']       = ['form-row-first'];
    $fields['billing']['billing_city']['required']    = true;
    $fields['billing']['billing_city']['priority']    = 80;

    // Code postal — moitié droite, requis
    $fields['billing']['billing_postcode']['label']       = 'Code postal';
    $fields['billing']['billing_postcode']['placeholder'] = 'Ex : 33000';
    $fields['billing']['billing_postcode']['class']       = ['form-row-last'];
    $fields['billing']['billing_postcode']['required']    = true;
    $fields['billing']['billing_postcode']['priority']    = 90;

    // Téléphone et email en bas
    $fields['billing']['billing_phone']['priority'] = 100;
    $fields['billing']['billing_email']['priority'] = 110;

    return $fields;
}

// Simplifier aussi les champs dans "Mon compte > Adresses"
add_filter('woocommerce_billing_fields', 'wamv1_simplify_billing_fields');
function wamv1_simplify_billing_fields($fields)
{
    unset($fields['billing_company']);
    unset($fields['billing_address_2']);
    unset($fields['billing_state']);
    return $fields;
}

/**
 * Ajout manuel du bloc d'urgence pour l'adhérent principal
 * On le place en fin de formulaire de facturation (priority 5)
 * pour qu'il soit avant le chargement des participants (priority 10)
 */
add_action('woocommerce_after_checkout_billing_form', 'wamv1_add_billing_emergency_block', 5);
function wamv1_add_billing_emergency_block($checkout) {
    if (!wamv1_cart_has_cours()) {
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

// Garantie : forcer billing_country à "FR" lors de la soumission du checkout
// Evite que HelloAsso reçoive une chaîne vide → erreur 400 "Country must be length ≥ 3"
add_filter('woocommerce_checkout_posted_data', function ($data) {
    if (empty($data['billing_country'])) {
        $data['billing_country'] = 'FR';
    }
    return $data;
});

// Garantie côté base de données : si billing_country est vide à la création de commande
add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    $country = get_post_meta($order_id, '_billing_country', true);
    if (empty($country)) {
        update_post_meta($order_id, '_billing_country', 'FR');
    }
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

// OG — vu avec Charlotte : le payeur n'est pas forcément adhérent.
// "Adhérent·e principal·e" ne convient pas pour tous les types de réservation. Terme générique.
add_filter('gettext', function ($translated_text, $text, $domain) {
    if ($domain === 'woocommerce' && ($text === 'Billing details')) {
        return 'Vos coordonnées';
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

// ============================================================================
// G. Sauvegarder l'adresse de facturation depuis "Informations personnelles"
// ============================================================================

add_action('woocommerce_save_account_details', 'wamv1_save_billing_address_on_account_details');
function wamv1_save_billing_address_on_account_details($user_id)
{
    $billing_fields = [
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_postcode',
        'billing_phone'
    ];

    foreach ($billing_fields as $field) {
        if (isset($_POST[$field])) {
            update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
