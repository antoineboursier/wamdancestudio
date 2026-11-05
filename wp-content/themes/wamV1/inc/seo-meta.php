<?php
/**
 * Méta-descriptions de repli générées depuis les champs ACF.
 *
 * Principe : une description saisie à la main dans Yoast (par fiche) passe toujours
 * avant. Ce fichier ne comble que les fiches dont la description est vide.
 * Aujourd'hui : les stages (0 sur 18 avaient une description).
 *
 * Rien n'est écrit en base : la description est calculée à l'affichage.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "18:00" ou "18h30" => "18h" / "18h30".
 */
function wamv1_seo_heure(string $valeur): string
{
    if (!preg_match('/^(\d{1,2})\s*[:h]\s*(\d{2})?/i', trim($valeur), $m)) {
        return '';
    }
    $minutes = isset($m[2]) ? (int) $m[2] : 0;
    return (int) $m[1] . 'h' . ($minutes > 0 ? sprintf('%02d', $minutes) : '');
}

/**
 * Prix de départ d'un stage (le plus petit tarif renseigné), ou 0.
 */
function wamv1_seo_stage_prix(int $stage_id): array
{
    $prix = [];
    $tarifs = function_exists('wamv1_stage_tarifs') ? wamv1_stage_tarifs($stage_id) : [];
    foreach ([1, 2, 3] as $i) {
        $p = isset($tarifs['prix_tarif_' . $i]) ? (float) $tarifs['prix_tarif_' . $i] : 0.0;
        if ($p > 0) {
            $prix[] = $p;
        }
    }
    if (!$prix) {
        return ['min' => 0.0, 'nb' => 0];
    }
    return ['min' => min($prix), 'nb' => count($prix)];
}

/**
 * Nom de l'intervenant·e : compte WordPress ou saisie libre.
 */
function wamv1_seo_stage_intervenant(int $stage_id): string
{
    $groupe = get_field('intervenant·e', $stage_id);
    if (!is_array($groupe)) {
        return '';
    }
    $user = $groupe['stage_intervenant'] ?? null;
    if (is_array($user) && !empty($user['display_name'])) {
        return (string) $user['display_name'];
    }
    if (is_object($user) && !empty($user->display_name)) {
        return (string) $user->display_name;
    }
    return trim((string) ($groupe['stage_intervenant_out'] ?? ''));
}

/**
 * Description d'un stage, 160 caractères maximum. Chaîne vide si la date manque.
 */
function wamv1_seo_stage_description(int $stage_id): string
{
    $date = (string) get_post_meta($stage_id, 'date_stage', true); // Ymd
    if (!preg_match('/^\d{8}$/', $date)) {
        return '';
    }
    $titre = trim(html_entity_decode(wp_strip_all_tags(get_the_title($stage_id)), ENT_QUOTES, 'UTF-8'));
    $niveau = trim(wp_strip_all_tags((string) get_post_meta($stage_id, 'sous_titre', true)));
    $ts = strtotime($date . ' 12:00:00');
    $quand = wp_date('l j F Y', $ts);

    $debut = wamv1_seo_heure((string) get_post_meta($stage_id, 'heure_debut', true));
    $fin = wamv1_seo_heure((string) get_post_meta($stage_id, 'heure_de_fin', true));
    $horaire = '';
    if ($debut && $fin) {
        $horaire = ' de ' . $debut . ' à ' . $fin;
    } elseif ($debut) {
        $horaire = ' à ' . $debut;
    }

    // Base obligatoire, puis ajouts par ordre de priorité tant que 160 caractères tiennent.
    $texte = 'Stage ' . $titre . ' à Villeneuve d\'Ascq, ' . $quand . $horaire . '.';
    $ajouts = [];
    if (function_exists('wamv1_stage_est_passe') && wamv1_stage_est_passe($stage_id)) {
        $ajouts[] = 'Stage terminé : retrouvez les prochains au studio WAM.';
    } else {
        $p = wamv1_seo_stage_prix($stage_id);
        if ($p['min'] > 0) {
            $montant = rtrim(rtrim(number_format($p['min'], 2, ',', ''), '0'), ',');
            $ajouts[] = ($p['nb'] > 1 ? 'Dès ' : '') . $montant . ' €.';
        }
    }
    if ($niveau !== '') {
        $ajouts[] = $niveau . '.';
    }
    $prof = wamv1_seo_stage_intervenant($stage_id);
    if ($prof !== '') {
        $ajouts[] = 'Avec ' . $prof . '.';
    }
    $ajouts[] = 'Réservation en ligne.';

    foreach ($ajouts as $ajout) {
        if (mb_strlen($texte . ' ' . $ajout) <= 160) {
            $texte .= ' ' . $ajout;
        }
    }

    $texte = trim(preg_replace('/\s+/', ' ', $texte));
    if (mb_strlen($texte) > 160) {
        $texte = rtrim(mb_substr($texte, 0, 157)) . '...';
    }
    return $texte;
}

/**
 * Comble la description vide d'un stage (balise description, Open Graph, X).
 */
function wamv1_seo_stage_metadesc_fallback($desc)
{
    if (is_string($desc) && trim($desc) !== '') {
        return $desc;
    }
    if (!is_singular('stages')) {
        return $desc;
    }
    $genere = wamv1_seo_stage_description((int) get_queried_object_id());
    return $genere !== '' ? $genere : $desc;
}
add_filter('wpseo_metadesc', 'wamv1_seo_stage_metadesc_fallback', 20);
add_filter('wpseo_opengraph_desc', 'wamv1_seo_stage_metadesc_fallback', 20);
add_filter('wpseo_twitter_description', 'wamv1_seo_stage_metadesc_fallback', 20);

/**
 * Variables Yoast pour les titres des stages et événements : %%wam_type%% et %%wam_date%%.
 * (Yoast sait lire un champ brut avec %%cf_...%% mais pas son libellé, ni une date mise en forme.)
 * Réglage du gabarit : Yoast > Types de contenu > Stages > Modèle de titre SEO.
 */
function wamv1_seo_variable_post_id($args = null): int
{
    // Yoast transmet le contenu concerné (aperçu de l'éditeur compris) ; repli sur la page affichée.
    if (is_object($args) && !empty($args->ID)) {
        return (int) $args->ID;
    }
    $id = (int) get_queried_object_id();
    return $id ?: (int) get_the_ID();
}

/** Libellé du type ("Stage", "Atelier", "Workshop"), vide si le titre le contient déjà. */
function wamv1_seo_var_type($var = '', $args = null): string
{
    $id = wamv1_seo_variable_post_id($args);
    if (!$id || get_post_type($id) !== 'stages') {
        return '';
    }
    $libelles = ['type_stage' => 'Stage', 'type_atel' => 'Atelier', 'type_wshop' => 'Workshop'];
    $brut = (string) get_post_meta($id, 'type_format', true);
    $libelle = $libelles[$brut] ?? 'Stage';
    $titre = html_entity_decode(wp_strip_all_tags(get_the_title($id)), ENT_QUOTES, 'UTF-8');
    return stripos($titre, $libelle) !== false ? '' : $libelle;
}

/** Date du stage ou de l'événement, en toutes lettres ("25 octobre 2026"). */
function wamv1_seo_var_date($var = '', $args = null): string
{
    $id = wamv1_seo_variable_post_id($args);
    $cle = get_post_type($id) === 'stages' ? 'date_stage' : (get_post_type($id) === 'evenements' ? 'date_event' : '');
    $date = $cle ? (string) get_post_meta($id, $cle, true) : '';
    if (!preg_match('/^\d{8}$/', $date)) {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Ymd', $date, wp_timezone());
    return $dt ? wp_date('j F Y', $dt->getTimestamp()) : '';
}

add_action('wpseo_register_extra_replacements', static function () {
    if (!function_exists('wpseo_register_var_replacement')) {
        return;
    }
    wpseo_register_var_replacement('wam_type', 'wamv1_seo_var_type', 'advanced', 'WAM : type du stage (Stage, Atelier, Workshop), vide si déjà dans le titre');
    wpseo_register_var_replacement('wam_date', 'wamv1_seo_var_date', 'advanced', 'WAM : date du stage ou de l\'événement (25 octobre 2026)');
});
