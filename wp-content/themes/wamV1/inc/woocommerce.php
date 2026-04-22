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
//    Le champ ACF wc_product_id est de type "relationship" (retourne array de WP_Post)
// ============================================================================

function wamv1_get_wc_product_id(int $post_id): int
{
    if (!function_exists('get_field')) {
        return 0;
    }

    $products = get_field('wc_product_id', $post_id);

    if (empty($products)) {
        return 0;
    }

    // Cas 1 : Tableau (Relationship field)
    if (is_array($products)) {
        $first = $products[0];
        return is_object($first) ? (int) $first->ID : (int) $first;
    }

    // Cas 2 : Objet (Post Object field)
    if (is_object($products)) {
        return (int) $products->ID;
    }

    // Cas 3 : ID direct
    return (int) $products;
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

    foreach (['cours', 'stages'] as $post_type) {
        $linked = get_posts([
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'no_found_rows' => true,
            // relationship stocke les IDs séparés par virgule ou sérialisés
            'meta_query' => [['key' => 'wc_product_id', 'value' => $product_id, 'compare' => 'LIKE']],
        ]);

        if (!$linked)
            continue;

        $cpt_id = $linked[0]->ID;

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
        }

        break;
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
    return $item_data;
}

// Persister les métadonnées dans la commande (visible dans le back-office)

add_action('woocommerce_checkout_create_order_line_item', 'wamv1_save_wc_item_meta_to_order', 10, 3);

function wamv1_save_wc_item_meta_to_order($item, $_cart_item_key, $values): void
{
    if (!empty($values['wam_course_id'])) {
        $item->add_meta_data('_wam_course_id', $values['wam_course_id'], true);
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

// ============================================================================
// Quotas — Décompte automatique des places lors de la validation de commande
// ============================================================================

add_action('woocommerce_payment_complete', 'wamv1_decrement_course_quota_on_payment');
add_action('woocommerce_order_status_processing', 'wamv1_decrement_course_quota_on_payment');

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

        $places_res = (int) get_field('places_reservees', $course_id);
        $new_res = $places_res + 1;
        update_field('places_reservees', $new_res, $course_id);

        // NOUVEAU : Auto-complétion si le quota est atteint
        $places_totales = (int) get_field('places_totales', $course_id);
        if ($places_totales > 0 && $new_res >= $places_totales) {
            update_field('complete_cours', true, $course_id);
            // On peut ajouter une note à la commande pour info admin
            $order->add_order_note(sprintf('Quota atteint pour le cours #%d (%s). Inscriptions fermées.', $course_id, get_the_title($course_id)));
        }
    }

    $order->add_meta_data('_wam_quota_decremented', '1', true);
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
    if (!$product_id) {
        wp_send_json_error(['message' => 'Produit introuvable.']);
    }

    // Vérifier que le produit existe et est achetable
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
        wp_send_json_error(['message' => 'Ce cours n\'est plus disponible.']);
    }

    // L'incrémentation est gérée nativement par WooCommerce (add_to_cart) 
    // qui fusionne les items si les meta (wam_course_id) sont identiques, 
    // ou crée une nouvelle ligne si le cours est différent.

    // Ajouter au panier (quantité 1)
    $cart_item_data = [];
    if (!empty($_POST['course_id'])) {
        $cart_item_data['wam_course_id'] = absint($_POST['course_id']);
    }

    $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);

    if (!$cart_item_key) {
        wp_send_json_error(['message' => 'Erreur lors de l\'ajout au panier.']);
    }

    // Récupérer les fragments mis à jour (icône panier, etc.)
    // Note : WC() est déjà peuplé par add_to_cart
    wc_setcookie('woocommerce_items_in_cart', WC()->cart->get_cart_contents_count());
    wc_setcookie('woocommerce_cart_hash', WC()->cart->get_cart_hash());
    ob_end_clean();

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
    $cart_page_id = wc_get_page_id('cart');
    if ($cart_page_id) {
        $content = get_post_field('post_content', $cart_page_id);
        // Si le shortcode n'y est pas, on rase tout et on le met
        if (strpos($content, '[woocommerce_cart]') === false) {
            wp_update_post([
                'ID' => $cart_page_id,
                'post_content' => '[woocommerce_cart]'
            ]);
        }
    }

    $checkout_page_id = wc_get_page_id('checkout');
    if ($checkout_page_id) {
        $content = get_post_field('post_content', $checkout_page_id);
        if (strpos($content, '[woocommerce_checkout]') === false) {
            wp_update_post([
                'ID' => $checkout_page_id,
                'post_content' => '[woocommerce_checkout]'
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

    $index = 1;

    foreach ($items as $cart_item_key => $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        if (!$product)
            continue;

        $product_name = $product->get_name();
        $course_id = $cart_item['wam_course_id'] ?? null;
        $course_title = $course_id ? get_the_title($course_id) : $product_name;
        $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;

        echo '<div class="wam-adherent-group wam-adherent-card">';

        echo '<div class="wam-adherent-card__header">';
        $card_title = $is_solo ? 'Informations participant·e' : 'Participant·e ' . $index;
        echo '<p class="text-md mb-0">' . $card_title . '</p>';
        echo '<div class="wam-adherent-card__course-info text-right">';
        echo '<h4 class="text-md color-yellow fw-bold m-0">' . esc_html($course_title) . '</h4>';
        if ($course_subtitle) {
            echo '<p class="text-sm color-text mb-0">' . esc_html($course_subtitle) . '</p>';
        }
        echo '</div>';
        echo '</div>';

        // Checkbox d'auto-remplissage PAR participant
        echo '<p class="form-row form-row-wide wam-adherent-auto-fill mb-md">';
        echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
        $checked = $is_solo ? 'checked="checked"' : '';
        echo '<input type="checkbox" class="wam-is-buyer-checkbox woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wam_is_buyer_' . $cart_item_key . '" value="1" ' . $checked . ' /> ';
        echo '<span>Utiliser les informations de l\'adhérent·e principal·e</span>';
        echo '</label>';
        echo '</p>';

        // Champs Prénom / Nom groupés pour le layout
        echo '<div class="wam-adherent-card__fields">';

        // Prénom
        woocommerce_form_field('wam_prenom_eleve_' . $cart_item_key, [
            'type' => 'text',
            'class' => ['form-row-first validated'],
            'label' => 'Prénom du participant·e ' . $index,
            'required' => true,
            'default' => $checkout->get_value('wam_prenom_eleve_' . $cart_item_key)
        ], $checkout->get_value('wam_prenom_eleve_' . $cart_item_key));

        // Nom
        woocommerce_form_field('wam_nom_eleve_' . $cart_item_key, [
            'type' => 'text',
            'class' => ['form-row-last validated'],
            'label' => 'Nom du participant·e ' . $index,
            'required' => true,
            'default' => $checkout->get_value('wam_nom_eleve_' . $cart_item_key)
        ], $checkout->get_value('wam_nom_eleve_' . $cart_item_key));

        echo '</div>'; // .wam-adherent-card__fields

        // ---------------------------------------------------
        // BLOCS CONTACTS D'URGENCE (Orange Glass)
        // ---------------------------------------------------
        $is_minor = has_term(['enfants', 'ados'], 'product_cat', $cart_item['product_id']);

        // Contact Urgence 1
        echo '<div class="wam-emergency-block">';
        echo '<p class="wam-emergency-block__title">Contact d\'urgence</p>';
        echo '<div class="wam-adherent-card__fields">';
        
        woocommerce_form_field('wam_urgent_name_1_' . $cart_item_key, [
            'type' => 'text',
            'label' => 'Nom et prénom',
            'placeholder' => 'Ex: Marie Dupont',
            'required' => true,
            'class' => ['form-row-first'],
            'default' => $checkout->get_value('wam_urgent_name_1_' . $cart_item_key)
        ], $checkout->get_value('wam_urgent_name_1_' . $cart_item_key));

        woocommerce_form_field('wam_urgent_phone_1_' . $cart_item_key, [
            'type' => 'tel',
            'label' => 'Téléphone',
            'placeholder' => 'Ex: 06 12 34 56 78',
            'required' => true,
            'class' => ['form-row-last'],
            'custom_attributes' => [
                'pattern' => '[0-9\s\.\-\+\(\)]*',
                'inputmode' => 'tel'
            ],
            'default' => $checkout->get_value('wam_urgent_phone_1_' . $cart_item_key)
        ], $checkout->get_value('wam_urgent_phone_1_' . $cart_item_key));
        
        echo '</div>'; // .wam-adherent-card__fields
        echo '</div>'; // .wam-emergency-block

        // Contact Urgence 2 (Optionnel pour Enfants/Ados)
        if ($is_minor) {
            echo '<div class="wam-emergency-block">';
            echo '<p class="wam-emergency-block__title">Second contact d\'urgence (Optionnel)</p>';
            echo '<div class="wam-adherent-card__fields">';
            
            woocommerce_form_field('wam_urgent_name_2_' . $cart_item_key, [
                'type' => 'text',
                'label' => 'Nom et prénom',
                'placeholder' => 'Ex: Pierre Dupont',
                'required' => false,
                'class' => ['form-row-first'],
                'default' => $checkout->get_value('wam_urgent_name_2_' . $cart_item_key)
            ], $checkout->get_value('wam_urgent_name_2_' . $cart_item_key));

            woocommerce_form_field('wam_urgent_phone_2_' . $cart_item_key, [
                'type' => 'tel',
                'label' => 'Téléphone',
                'placeholder' => 'Ex: 06 12 34 56 78',
                'required' => false,
                'class' => ['form-row-last'],
                'custom_attributes' => [
                    'pattern' => '[0-9\s\.\-\+\(\)]*',
                    'inputmode' => 'tel'
                ],
                'default' => $checkout->get_value('wam_urgent_phone_2_' . $cart_item_key)
            ], $checkout->get_value('wam_urgent_phone_2_' . $cart_item_key));
            
            echo '</div>'; // .wam-adherent-card__fields
            echo '</div>'; // .wam-emergency-block
        }

        echo '<div class="clear"></div>';
        echo '</div>';
        $index++;
    }

    echo '</div>';
}

// 2. JS Auto-remplissage et CSS Checkout
add_action('wp_footer', 'wamv1_checkout_scripts');

function wamv1_checkout_scripts()
{
    if (!is_checkout() || is_wc_endpoint_url())
        return;
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const prenomFact = document.getElementById('billing_first_name');
            const nomFact = document.getElementById('billing_last_name');
            const urgentNameFact = document.getElementById('billing_urgent_name');
            const urgentPhoneFact = document.getElementById('billing_urgent_phone');

            const checkboxes = document.querySelectorAll('.wam-is-buyer-checkbox');
            if (checkboxes.length === 0) return; // Pas de participants multiples

            const syncFields = (checkbox) => {
                const card = checkbox.closest('.wam-adherent-card');
                const prenomInput = card.querySelector('input[name^="wam_prenom_eleve_"]');
                const nomInput = card.querySelector('input[name^="wam_nom_eleve_"]');
                const urgentNameInput = card.querySelector('input[name^="wam_urgent_name_1_"]');
                const urgentPhoneInput = card.querySelector('input[name^="wam_urgent_phone_1_"]');

                if (checkbox.checked) {
                    // Sauvegarde des anciennes valeurs si vides
                    if (prenomInput.value && !prenomInput.dataset.oldVal) prenomInput.dataset.oldVal = prenomInput.value;
                    if (nomInput.value && !nomInput.dataset.oldVal) nomInput.dataset.oldVal = nomInput.value;
                    
                    // Copie des infos facturation
                    prenomInput.value = prenomFact.value;
                    nomInput.value = nomFact.value;
                    urgentNameInput.value = urgentNameFact.value;
                    urgentPhoneInput.value = urgentPhoneFact.value;
                } else if (prenomInput.dataset.oldVal !== undefined) {
                    prenomInput.value = prenomInput.dataset.oldVal || '';
                    nomInput.value = nomInput.dataset.oldVal || '';
                    urgentNameInput.value = urgentNameInput.dataset.oldVal || '';
                    urgentPhoneInput.value = urgentPhoneInput.dataset.oldVal || '';
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
                const inputsToWatch = card.querySelectorAll('input[name^="wam_prenom_eleve_"], input[name^="wam_nom_eleve_"], input[name^="wam_urgent_name_1_"], input[name^="wam_urgent_phone_1_"]');

                inputsToWatch.forEach(input => {
                    input.addEventListener('input', function () {
                        if (checkbox.checked) {
                            checkbox.checked = false;
                        }
                    });
                });
            });

            // Mise à jour en temps réel si les infos de facturation changent
            [prenomFact, nomFact, urgentNameFact, urgentPhoneFact].forEach(field => {
                if (field) {
                    field.addEventListener('input', function () {
                        checkboxes.forEach(checkbox => {
                            if (checkbox.checked) {
                                const card = checkbox.closest('.wam-adherent-card');
                                const prenomInput = card.querySelector('input[name^="wam_prenom_eleve_"]');
                                const nomInput = card.querySelector('input[name^="wam_nom_eleve_"]');
                                const urgentNameInput = card.querySelector('input[name^="wam_urgent_name_1_"]');
                                const urgentPhoneInput = card.querySelector('input[name^="wam_urgent_phone_1_"]');

                                if (prenomInput && prenomFact) prenomInput.value = prenomFact.value;
                                if (nomInput && nomFact) nomInput.value = nomFact.value;
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

    // 1. Validation du contact d'urgence Billing (commun à tous les modes)
    if (empty($_POST['billing_urgent_name'])) {
        wc_add_notice('Le contact d\'urgence pour l\'adhérent·e principal·e est obligatoire.', 'error');
    }
    if (empty($_POST['billing_urgent_phone'])) {
        wc_add_notice('Le téléphone du contact d\'urgence pour l\'adhérent·e principal·e est obligatoire.', 'error');
    } elseif (!preg_match('/^[0-9\s\.\-\+\(\)]+$/', $_POST['billing_urgent_phone'])) {
        wc_add_notice('Le numéro de téléphone d\'urgence (adhérent·e principal·e) n\'est pas valide.', 'error');
    }

    // 2. Validation des participants (si multi)
    if ($is_solo) return;

    $index = 1;
    foreach ($items as $cart_item_key => $cart_item) {
        $product = wc_get_product($cart_item['product_id']);
        if (!$product)
            continue;

        $course_id = $cart_item['wam_course_id'] ?? null;
        $course_title = $course_id ? get_the_title($course_id) : $product->get_name();
        $course_subtitle = $course_id ? get_field('sous_titre', $course_id) : null;

        $item_desc = '<strong>Participant·e ' . $index . '</strong> (' . $course_title . ($course_subtitle ? ' — ' . $course_subtitle : '') . ')';

        $prenom_key = 'wam_prenom_eleve_' . $cart_item_key;
        $nom_key = 'wam_nom_eleve_' . $cart_item_key;

        if (empty($_POST[$prenom_key])) {
            wc_add_notice('Le prénom pour ' . $item_desc . ' est obligatoire.', 'error');
        }
        if (empty($_POST[$nom_key])) {
            wc_add_notice('Le nom pour ' . $item_desc . ' est obligatoire.', 'error');
        }

        // Validation Contacts Urgence
        $u1_name_key = 'wam_urgent_name_1_' . $cart_item_key;
        $u1_phone_key = 'wam_urgent_phone_1_' . $cart_item_key;

        if (empty($_POST[$u1_name_key])) {
            wc_add_notice('Le nom du contact d\'urgence 1 pour ' . $item_desc . ' est obligatoire.', 'error');
        }
        if (empty($_POST[$u1_phone_key])) {
            wc_add_notice('Le téléphone du contact d\'urgence 1 pour ' . $item_desc . ' est obligatoire.', 'error');
        } elseif (!preg_match('/^[0-9\s\.\-\+\(\)]+$/', $_POST[$u1_phone_key])) {
            wc_add_notice('Le numéro de téléphone pour ' . $item_desc . ' n\'est pas valide (chiffres uniquement).', 'error');
        }

        $index++;
    }
}

// 4. Mettre les Méta-données sur CHAQUE Ligne de Commande (Item)
add_action('woocommerce_checkout_create_order_line_item', 'wamv1_save_adherent_to_order_items', 20, 4);

function wamv1_save_adherent_to_order_items($item, $cart_item_key, $values, $order)
{
    $cart_count = WC()->cart->get_cart_contents_count();
    $is_solo = ($cart_count === 1);

    if ($is_solo) {
        // En mode solo, on tire les infos directement de la facturation (billing)
        $item->add_meta_data('Prénom', sanitize_text_field($_POST['billing_first_name'] ?? ''), true);
        $item->add_meta_data('Nom', sanitize_text_field($_POST['billing_last_name'] ?? ''), true);
        $item->add_meta_data('Urgence 1 - Nom', sanitize_text_field($_POST['billing_urgent_name'] ?? ''), true);
        $item->add_meta_data('Urgence 1 - Tél', sanitize_text_field($_POST['billing_urgent_phone'] ?? ''), true);
    } else {
        // Mode Multi-participants
        if (isset($_POST['wam_prenom_eleve_' . $cart_item_key])) {
            $item->add_meta_data('Prénom', sanitize_text_field($_POST['wam_prenom_eleve_' . $cart_item_key]), true);
        }
        if (isset($_POST['wam_nom_eleve_' . $cart_item_key])) {
            $item->add_meta_data('Nom', sanitize_text_field($_POST['wam_nom_eleve_' . $cart_item_key]), true);
        }

        // Contacts Urgence Participant
        if (isset($_POST['wam_urgent_name_1_' . $cart_item_key])) {
            $item->add_meta_data('Urgence 1 - Nom', sanitize_text_field($_POST['wam_urgent_name_1_' . $cart_item_key]), true);
        }
        if (isset($_POST['wam_urgent_phone_1_' . $cart_item_key])) {
            $item->add_meta_data('Urgence 1 - Tél', sanitize_text_field($_POST['wam_urgent_phone_1_' . $cart_item_key]), true);
        }
        if (isset($_POST['wam_urgent_name_2_' . $cart_item_key]) && !empty($_POST['wam_urgent_name_2_' . $cart_item_key])) {
            $item->add_meta_data('Urgence 2 - Nom', sanitize_text_field($_POST['wam_urgent_name_2_' . $cart_item_key]), true);
        }
        if (isset($_POST['wam_urgent_phone_2_' . $cart_item_key]) && !empty($_POST['wam_urgent_phone_2_' . $cart_item_key])) {
            $item->add_meta_data('Urgence 2 - Tél', sanitize_text_field($_POST['wam_urgent_phone_2_' . $cart_item_key]), true);
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

// ============================================================================
// K. Allègement du formulaire et blocage MailPoet par défaut
// ============================================================================

add_filter('woocommerce_checkout_fields', 'wamv1_simplify_checkout_fields');

function wamv1_simplify_checkout_fields($fields)
{
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_state']);
    
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

// Renommage du titre de facturation (Détails de facturation -> Adhérent·e principal)
add_filter('gettext', function ($translated_text, $text, $domain) {
    if ($domain === 'woocommerce' && ($text === 'Billing details' || $text === 'Billing details')) {
        return 'Adhérent·e principal';
    }
    return $translated_text;
}, 20, 3);

// Ajout du sous-titre "Informations de facturation" sous le titre Adhérent·e principal
add_action('woocommerce_before_checkout_billing_form', function () {
    echo '<p class="subtext text-sm mb-md color-subtext">Informations de facturation</p>';
}, 5);

