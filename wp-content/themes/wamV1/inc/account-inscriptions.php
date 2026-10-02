<?php
/**
 * Mon compte → « Mes commandes »
 *
 * Remplace l'onglet « Commandes » (retiré du menu, cf. inc/woocommerce.php) :
 * toutes les commandes de l'adhérent·e y sont listées, avec photo, statut,
 * total et facture le cas échéant. Permet en plus de corriger, pour les
 * inscriptions de la saison en cours, le prénom / nom de l'élève et ses
 * contacts d'urgence.
 *
 * Source de vérité : les metas des lignes de commande WooCommerce. Le BO de
 * l'équipe (WAM Staff BO) les lit directement, une correction y est donc
 * visible sans autre synchronisation. Bookly ne stocke ni l'élève ni le
 * contact d'urgence (sa fiche client est le titulaire du compte) : rien à y
 * reporter.
 *
 * Deux jeux de clés coexistent et sont réécrits tels quels, sans renommage :
 *   - tunnel d'inscription (wam-custom-plugin) : `Contact d'urgence - Nom|Tél`,
 *     `Date de naissance` (Y-m-d) ;
 *   - checkout du thème (préinscriptions Bookly, troupes, stages) :
 *     `[P{n} - ]Urgence 1|2 - Nom|Tél`, avec préfixe `P{n} - ` dès qu'une
 *     ligne porte plusieurs participant·es.
 *
 * La facture PDF ne reprend aucune de ces données (seulement Cours, Tarif,
 * Formule, Date) : les modifier ne touche pas à la facture émise.
 *
 * @package wamv1
 */

defined('ABSPATH') || exit;

const WAMV1_INSCRIPTIONS_ENDPOINT = 'mes-inscriptions';

/** Statuts de commande dont les inscriptions sont modifiables. */
const WAMV1_INSCRIPTIONS_STATUTS = ['completed', 'processing', 'on-hold'];

// =============================================================================
// Endpoint, menu, titre
// =============================================================================

/**
 * Déclaré comme query var WooCommerce : WC enregistre lui-même l'endpoint de
 * réécriture et le traite comme ses endpoints natifs (titre, menu actif).
 */
add_filter('woocommerce_get_query_vars', function (array $vars): array {
    $vars[WAMV1_INSCRIPTIONS_ENDPOINT] = WAMV1_INSCRIPTIONS_ENDPOINT;
    return $vars;
});

/**
 * Vidage unique des règles de réécriture, sans quoi l'endpoint renverrait une
 * 404 tant que personne n'a enregistré les permaliens (piège de la v1.7.8).
 */
add_action('init', function (): void {
    if (get_option('wamv1_inscriptions_endpoint') !== '1') {
        flush_rewrite_rules(false);
        update_option('wamv1_inscriptions_endpoint', '1');
    }
}, 99);

/**
 * Placé juste après « Commandes » si l'entrée existe encore, sinon juste après
 * « Tableau de bord » — « Commandes » est retirée du menu par
 * wamv1_wc_account_menu_items() (inc/woocommerce.php), son contenu étant
 * repris ici. Priorité 20 : s'exécute après ce retrait (priorité 10).
 */
add_filter('woocommerce_account_menu_items', function (array $items): array {
    $menu = [];
    foreach ($items as $key => $label) {
        $menu[$key] = $label;
        if ($key === 'orders' || $key === 'dashboard') {
            $menu[WAMV1_INSCRIPTIONS_ENDPOINT] = 'Mes commandes';
        }
    }
    if (!isset($menu[WAMV1_INSCRIPTIONS_ENDPOINT])) {
        $menu[WAMV1_INSCRIPTIONS_ENDPOINT] = 'Mes commandes';
    }
    return $menu;
}, 20);

add_filter('woocommerce_endpoint_' . WAMV1_INSCRIPTIONS_ENDPOINT . '_title', function (): string {
    return 'Mes commandes';
});

add_action('woocommerce_account_' . WAMV1_INSCRIPTIONS_ENDPOINT . '_endpoint', function (): void {
    get_template_part('template-parts/account-inscriptions');
});

/** Masque « Enregistrer » tant qu'aucun champ de la carte n'a changé. */
add_action('wp_enqueue_scripts', function (): void {
    if (!is_wc_endpoint_url(WAMV1_INSCRIPTIONS_ENDPOINT)) {
        return;
    }
    $rel = 'assets/js/account-inscriptions.js';
    wp_enqueue_script(
        'wamv1-account-inscriptions',
        get_template_directory_uri() . '/' . $rel,
        [],
        (string) filemtime(get_template_directory() . '/' . $rel),
        ['in_footer' => true, 'strategy' => 'defer']
    );
});

// =============================================================================
// Données
// =============================================================================

/**
 * Début de la saison en cours (1er juin à minuit, fuseau du site).
 *
 * Même bascule que le llms.txt (v1.7.8) : la saison change au 1er juin, quand
 * s'ouvrent les inscriptions de la suivante.
 *
 * @return array{debut: int, libelle: string}
 */
function wamv1_inscriptions_saison(): array
{
    $mois  = (int) current_time('n');
    $annee = (int) current_time('Y');
    $debut = ($mois >= 6) ? $annee : $annee - 1;

    $date = new DateTimeImmutable($debut . '-06-01 00:00:00', wp_timezone());

    return [
        'debut'   => $date->getTimestamp(),
        'libelle' => $debut . '-' . ($debut + 1),
    ];
}

/**
 * Toutes les commandes dont l'utilisateur est titulaire (remplace la liste
 * « Commandes », retirée du menu). Pas de filtre de statut ni de saison ici :
 * c'est wamv1_inscriptions_commande_modifiable() qui décide, ligne par ligne,
 * si une inscription reste éditable.
 *
 * @return WC_Order[]
 */
function wamv1_inscriptions_commandes(int $user_id): array
{
    if (!$user_id) {
        return [];
    }

    return wc_get_orders([
        'customer_id' => $user_id,
        'limit'       => 50,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);
}

/**
 * Vrai si la commande est modifiable par cet utilisateur : titulaire, statut
 * actif, saison en cours. Rejoue exactement le filtre de la liste.
 */
function wamv1_inscriptions_commande_modifiable($order, int $user_id): bool
{
    if (!$order instanceof WC_Order || !$user_id) {
        return false;
    }
    if ((int) $order->get_customer_id() !== $user_id) {
        return false;
    }
    if (!in_array($order->get_status(), WAMV1_INSCRIPTIONS_STATUTS, true)) {
        return false;
    }
    $cree = $order->get_date_created();
    return $cree && $cree->getTimestamp() >= wamv1_inscriptions_saison()['debut'];
}

/**
 * Participant·es d'une ligne de commande et champs associés.
 *
 * Seules les clés DÉJÀ présentes sur la ligne sont proposées : on ne crée
 * jamais de meta, ce qui protège le format de chaque circuit d'inscription.
 *
 * @return array<int, array{titre: string, champs: array<string, array>}>
 */
function wamv1_inscriptions_participants(WC_Order_Item_Product $item): array
{
    $metas = [];
    foreach ($item->get_meta_data() as $meta) {
        $data = $meta->get_data();
        if (is_string($data['value'] ?? null)) {
            $metas[$data['key']] = $data['value'];
        }
    }

    // Préfixes « P{n} - » (plusieurs participant·es) ou aucun.
    $prefixes = [];
    foreach (array_keys($metas) as $key) {
        if (preg_match('/^(P(\d+) - )?Prénom$/u', $key, $m)) {
            $prefixes[(int) ($m[2] ?? 0)] = $m[1] ?? '';
        }
    }
    ksort($prefixes);

    $participants = [];
    foreach ($prefixes as $rang => $p) {
        $champs = [];
        // Un champ requis n'est imposé que s'il est déjà renseigné : on ne peut
        // pas l'effacer, mais une clé restée vide au checkout (contact
        // d'urgence des préinscriptions et troupes) ne bloque pas les autres
        // corrections.
        $ajouter = function (string $key, string $label, string $type, bool $requis, bool $modifiable = true) use (&$champs, $metas): void {
            if (array_key_exists($key, $metas)) {
                $champs[$key] = [
                    'label'      => $label,
                    'value'      => $metas[$key],
                    'type'       => $type,
                    'requis'     => $requis && $metas[$key] !== '',
                    'modifiable' => $modifiable,
                ];
            }
        };

        $ajouter($p . 'Prénom', 'Prénom', 'text', true);
        $ajouter($p . 'Nom', 'Nom', 'text', true);

        if (array_key_exists($p . 'Urgence 1 - Nom', $metas)) {
            $ajouter($p . 'Urgence 1 - Nom', "Contact d'urgence : nom", 'text', true);
            $ajouter($p . 'Urgence 1 - Tél', "Contact d'urgence : téléphone", 'tel', true);
        } elseif ($p === '') {
            $ajouter("Contact d'urgence - Nom", "Contact d'urgence : nom", 'text', true);
            $ajouter("Contact d'urgence - Tél", "Contact d'urgence : téléphone", 'tel', true);
        }
        $ajouter($p . 'Urgence 2 - Nom', "Second contact d'urgence : nom", 'text', false);
        $ajouter($p . 'Urgence 2 - Tél', "Second contact d'urgence : téléphone", 'tel', false);

        if ($p === '' && !empty($metas['Date de naissance'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $metas['Date de naissance']);
            $champs['Date de naissance'] = [
                'label'      => 'Date de naissance',
                'value'      => $dob ? $dob->format('d/m/Y') : $metas['Date de naissance'],
                'type'       => 'text',
                'requis'     => false,
                'modifiable' => false,
            ];
        }

        $participants[] = [
            'titre'  => count($prefixes) > 1 ? 'Participant·e ' . max(1, $rang) : 'Élève',
            'champs' => $champs,
        ];
    }

    return $participants;
}

/**
 * Cours ou stage rattaché à une ligne de commande, 0 si la ligne porte un
 * produit générique (formule de cours privé, EVJF, location, prestation sur
 * mesure). Résolu par la meta `_wam_course_id`, ou par le service Bookly pour
 * les préinscriptions à un cours d'essai.
 */
function wamv1_inscriptions_post_lie(WC_Order_Item_Product $item): int
{
    $post_id = (int) $item->get_meta('_wam_course_id');

    if (!$post_id) {
        $bookly = $item->get_meta('bookly');
        $slot   = is_array($bookly) ? ($bookly['items'][0] ?? []) : [];
        if (!empty($slot['service_id'])) {
            $post_id = wamv1_inscriptions_cours_par_service((int) $slot['service_id']);
        }
    }

    return ($post_id && get_post_status($post_id)) ? $post_id : 0;
}

/**
 * Vrai si la fiche participant d'une ligne apporte une information propre.
 *
 * Sur les prestations (formules de cours privé, EVJF, location, prestation
 * sur mesure…), le checkout recopie simplement le nom du titulaire et laisse
 * les contacts d'urgence vides : afficher une fiche « Élève » y est trompeur,
 * et la corriger n'a aucun sens — ces données se modifient dans « Informations
 * personnelles ».
 *
 * Mesuré sur la base complète (1 219 commandes) : les 48 lignes écartées par
 * ce filtre sont toutes des recopies du titulaire, sans contact d'urgence ;
 * aucune information n'est donc perdue. Le test ne porte pas sur une liste de
 * produits mais sur le contenu réel de la ligne : si un circuit se met à
 * collecter un vrai élève (troupes, par exemple), la fiche réapparaît seule.
 */
function wamv1_inscriptions_fiche_utile(WC_Order_Item_Product $item, array $participants): bool
{
    if (!$participants) {
        return false;
    }

    // Inscription à un cours ou un stage : toujours pertinente.
    if (wamv1_inscriptions_post_lie($item)) {
        return true;
    }

    // Plusieurs participant·es : ce n'est pas une recopie du titulaire.
    if (count($participants) > 1) {
        return true;
    }

    $order     = $item->get_order();
    $titulaire = $order
        ? mb_strtolower(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()))
        : '';

    foreach ($participants as $participant) {
        foreach ($participant['champs'] as $champ) {
            // Un contact d'urgence renseigné est une information propre.
            if (stripos($champ['label'], 'urgence') !== false && trim($champ['value']) !== '') {
                return true;
            }
        }
        $nom = mb_strtolower(wamv1_inscriptions_participant_nom($participant));
        if ($nom !== '' && $nom !== $titulaire) {
            return true;
        }
    }

    return false;
}

/**
 * Libellés d'affichage d'une ligne : nature (produit), titre du cours ou du
 * stage, précision (horaire, ou date du cours d'essai pour une préinscription
 * Bookly) et photo.
 *
 * `vignette_format` distingue les deux sources d'image, qui n'ont pas le même
 * cadrage : la photo d'un cours/stage est un portrait de danse, celle d'un
 * produit générique une illustration qu'un cadre portrait déformerait.
 *
 * @return array{nature: string, titre: string, precision: string, vignette_id: int, vignette_format: string}
 */
function wamv1_inscriptions_libelles(WC_Order_Item_Product $item): array
{
    $product     = $item->get_product();
    $nature      = $product ? $product->get_name() : $item->get_name();
    $titre       = $item->get_name();
    $precision   = '';
    $vignette_id = 0;
    $format      = 'carre';

    $post_id = wamv1_inscriptions_post_lie($item);

    if ($post_id) {
        $titre = get_the_title($post_id);
        $sous_titre = function_exists('get_field') ? (string) get_field('sous_titre', $post_id) : '';
        $horaire    = (string) $item->get_meta('Horaire');
        $date_stage = (string) $item->get_meta('Date');
        $precision  = trim(implode(' · ', array_filter([$sous_titre, $horaire, $date_stage])));
        $vignette_id = (int) get_post_thumbnail_id($post_id);
        if ($vignette_id) {
            $format = 'portrait';
        }
    }

    if (!$vignette_id && $product) {
        $vignette_id = (int) $product->get_image_id();
    }

    $bookly = $item->get_meta('bookly');
    $slot   = is_array($bookly) ? ($bookly['items'][0] ?? []) : [];
    if (!empty($slot['slots'][0][2])) {
        $debut = date_create_immutable($slot['slots'][0][2], wp_timezone());
        if ($debut) {
            $essai = 'Cours d\'essai le ' . wp_date('j F à G\hi', $debut->getTimestamp());
            $precision = $precision ? $precision . ' · ' . $essai : $essai;
        }
    }

    if (wamv1_inscriptions_est_essai($item)) {
        $nature .= ' · Essai';
    }

    return [
        'nature'          => $nature,
        'titre'           => $titre,
        'precision'       => $precision,
        'vignette_id'     => $vignette_id,
        'vignette_format' => $format,
    ];
}

/**
 * Famille visuelle d'un statut de commande, pour la pastille colorée :
 * `actif` (honorée), `attente` (en cours de traitement) ou `inactif`
 * (annulée/remboursée/échouée). N'invente aucune couleur : réutilise les
 * jetons déjà employés ailleurs dans le thème pour ces trois mêmes familles.
 */
function wamv1_inscriptions_statut_famille(string $status): string
{
    if ($status === 'completed') {
        return 'actif';
    }
    if (in_array($status, ['processing', 'on-hold', 'pending'], true)) {
        return 'attente';
    }
    return 'inactif';
}

/**
 * Prénom + nom d'un·e participant·e, affichables en lecture seule. Les clés
 * du tableau `champs` sont préfixées (`P1 - Prénom`…) dès qu'il y a plusieurs
 * participant·es (cf. wamv1_inscriptions_participants()) : on retrouve le
 * prénom/nom par leur `label` normalisé plutôt que par une clé en dur, sans
 * quoi le préfixe ferait échouer la recherche.
 */
function wamv1_inscriptions_participant_nom(array $participant): string
{
    $prenom = $nom = '';
    foreach ($participant['champs'] as $champ) {
        if ($champ['label'] === 'Prénom') {
            $prenom = $champ['value'];
        } elseif ($champ['label'] === 'Nom') {
            $nom = $champ['value'];
        }
    }
    return trim($prenom . ' ' . $nom);
}

/**
 * Lien de téléchargement de la facture (ou de l'avoir, prioritaire) d'une
 * commande — null si aucun PDF n'est réellement disponible.
 *
 * wam_factures_get_path() / wam_avoirs_get_path() (wam-custom-plugin, lecture
 * seule) vérifient file_exists() sur le disque : le lien n'est donc jamais
 * proposé pour un fichier absent.
 *
 * L'URL est forcée sur le schéma de la page courante : si la page est servie
 * en HTTPS et le lien construit en HTTP (option `home` mal renseignée derrière
 * un proxy, cas classique), Chrome bloque le téléchargement avec « fichier
 * non sécurisé » — le PDF n'y est pour rien.
 *
 * @return array{url: string, label: string}|null
 */
function wamv1_inscriptions_facture(WC_Order $order): ?array
{
    if (function_exists('wam_avoirs_get_path') && wam_avoirs_get_path($order)) {
        return [
            'url'   => set_url_scheme(wc_get_account_endpoint_url('avoir') . $order->get_id() . '/'),
            'label' => 'Avoir PDF',
        ];
    }

    if ($order->get_status() === 'completed'
        && function_exists('wam_factures_get_path')
        && wam_factures_get_path($order)
    ) {
        return [
            'url'   => set_url_scheme(function_exists('wam_factures_get_download_url')
                ? wam_factures_get_download_url($order)
                : wc_get_account_endpoint_url('facture') . $order->get_id() . '/'),
            'label' => 'Facture PDF',
        ];
    }

    return null;
}

/**
 * Préinscription à un cours d'essai (ligne Bookly) : affichée pour mémoire,
 * jamais modifiable — l'essai passé, ses données n'ont plus d'usage.
 */
function wamv1_inscriptions_est_essai(WC_Order_Item_Product $item): bool
{
    return is_array($item->get_meta('bookly'));
}

/** Cours rattaché à un service Bookly (champ ACF `service_bookly`). */
function wamv1_inscriptions_cours_par_service(int $service_id): int
{
    static $cache = [];
    if (!isset($cache[$service_id])) {
        $ids = get_posts([
            'post_type'      => 'cours',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_key'       => 'service_bookly',
            'meta_value'     => $service_id,
        ]);
        $cache[$service_id] = (int) ($ids[0] ?? 0);
    }
    return $cache[$service_id];
}

/** Nom de champ de formulaire : jamais la clé brute (espaces, apostrophes). */
function wamv1_inscriptions_nom_champ(string $key): string
{
    return 'wam_insc[' . md5($key) . ']';
}

// =============================================================================
// Enregistrement
// =============================================================================

/**
 * Valeurs saisies d'une ligne restée en erreur, pour les réafficher.
 *
 * @return array<string, string>|null
 */
function wamv1_inscriptions_saisie_en_erreur(int $item_id, ?array $valeurs = null): ?array
{
    static $saisies = [];
    if ($valeurs !== null) {
        $saisies[$item_id] = $valeurs;
    }
    return $saisies[$item_id] ?? null;
}

add_action('template_redirect', 'wamv1_inscriptions_enregistrer');

function wamv1_inscriptions_enregistrer(): void
{
    if (empty($_POST['wamv1_inscription_item']) || !is_user_logged_in()) {
        return;
    }

    $item_id = absint($_POST['wamv1_inscription_item']);
    $nonce   = sanitize_text_field(wp_unslash($_POST['wamv1_inscription_nonce'] ?? ''));
    if (!wp_verify_nonce($nonce, 'wamv1_inscription_' . $item_id)) {
        wc_add_notice('La session a expiré, merci de réessayer.', 'error');
        return;
    }

    $user_id  = get_current_user_id();
    $order_id = $item_id ? wc_get_order_id_by_order_item_id($item_id) : 0;
    $order    = $order_id ? wc_get_order($order_id) : null;
    $item     = $order ? $order->get_item($item_id) : null;

    if (!$item instanceof WC_Order_Item_Product || wamv1_inscriptions_est_essai($item) || !wamv1_inscriptions_commande_modifiable($order, $user_id)) {
        wc_add_notice('Cette inscription ne peut pas être modifiée depuis votre compte.', 'error');
        return;
    }

    // Même filtre qu'à l'affichage : une ligne sans fiche affichée ne peut pas
    // être enregistrée, même par un POST forgé.
    $participants = wamv1_inscriptions_participants($item);
    if (!wamv1_inscriptions_fiche_utile($item, $participants)) {
        wc_add_notice('Cette inscription ne peut pas être modifiée depuis votre compte.', 'error');
        return;
    }

    $saisie   = isset($_POST['wam_insc']) && is_array($_POST['wam_insc']) ? wp_unslash($_POST['wam_insc']) : [];
    $erreurs  = [];
    $nouveaux = [];
    $valeurs  = [];

    foreach ($participants as $participant) {
        foreach ($participant['champs'] as $key => $champ) {
            if (!$champ['modifiable']) {
                continue;
            }
            $hash   = md5($key);
            $valeur = isset($saisie[$hash]) ? trim(sanitize_text_field((string) $saisie[$hash])) : $champ['value'];
            $valeurs[$key] = $valeur;

            $libelle = $participant['titre'] . ', ' . $champ['label'];
            if ($champ['requis'] && $valeur === '') {
                $erreurs[] = sprintf('%s : ce champ est obligatoire.', $libelle);
                continue;
            }
            if ($champ['type'] === 'tel' && $valeur !== '' && !preg_match('/^[0-9\s\.\-\+\(\)]+$/', $valeur)) {
                $erreurs[] = sprintf('%s : numéro de téléphone invalide.', $libelle);
                continue;
            }
            if ($valeur !== $champ['value']) {
                $nouveaux[$key] = ['avant' => $champ['value'], 'apres' => $valeur];
            }
        }
    }

    if ($erreurs) {
        foreach ($erreurs as $erreur) {
            wc_add_notice(esc_html($erreur), 'error');
        }
        wamv1_inscriptions_saisie_en_erreur($item_id, $valeurs);
        return;
    }

    if ($nouveaux) {
        $lignes = [];
        foreach ($nouveaux as $key => $diff) {
            $item->update_meta_data($key, $diff['apres']);
            $lignes[] = sprintf('%s : « %s » → « %s »', $key, $diff['avant'], $diff['apres']);
        }
        $item->save();

        $order->add_order_note(
            sprintf("Informations modifiées par l'adhérent·e depuis Mon compte (%s) :\n%s", wamv1_inscriptions_libelles($item)['titre'], implode("\n", $lignes))
        );

        wc_add_notice('Vos informations ont bien été enregistrées.', 'success');
    } else {
        wc_add_notice('Aucune modification à enregistrer.', 'notice');
    }

    wp_safe_redirect(wc_get_account_endpoint_url(WAMV1_INSCRIPTIONS_ENDPOINT) . '#inscription-' . $item_id);
    exit;
}
