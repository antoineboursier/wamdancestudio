<?php
/**
 * Publication automatique des stages et des événements sur la fiche Google Business Profile.
 *
 * Principe : un stage ou un événement publié devient un post Google de type "Événement"
 * (titre, dates, résumé, photo) avec un bouton "Réserver" qui mène à la fiche du site.
 * Le nom du post renvoyé par Google est gardé en méta (_wam_gbp_name) pour le mettre à jour
 * ou le supprimer ensuite, sans doublon. Brouillon, corbeille ou suppression : post retiré.
 * Un événement dont la fin est passée n'est pas envoyé (Google le refuse) et son post est retiré.
 *
 * - API : Business Profile v4 (accounts.locations.localPosts), accès sur autorisation de Google.
 *   Comptes et fiches : API Account Management et Business Information (activées dans le projet).
 * - Secrets : fichier wam-gbp-secrets.php à la racine du site ou dans son dossier parent (ignoré
 *   par git, modèle : inc/gbp-secrets.sample.php). Sans ce fichier, rien ne part.
 * - Garde-fou local : en environnement "local", rien n'est envoyé (une copie de prod publierait
 *   sur la vraie fiche), sauf si le fichier de secrets définit 'allow_local' => true.
 * - Dépend de wamv1_oa_timing() (inc/openagenda.php) pour lire dates et horaires.
 * - L'envoi part juste après la réponse au navigateur (hook shutdown), comme pour OpenAgenda.
 *   Dernier résultat dans la méta _wam_gbp_status (encadré "Google Business Profile" de la fiche).
 */

if (!defined('ABSPATH')) {
    exit;
}

const WAMV1_GBP_POST_TYPES = ['stages', 'evenements'];
const WAMV1_GBP_API = 'https://mybusiness.googleapis.com/v4/';

/**
 * Configuration : tableau ['client_id','client_secret','refresh_token', ...] ou null.
 */
function wamv1_gbp_config(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $cfg = null;
        $file = '';
        foreach ([ABSPATH, dirname(untrailingslashit(ABSPATH)) . '/'] as $dossier) {
            if (is_readable($dossier . 'wam-gbp-secrets.php')) {
                $file = $dossier . 'wam-gbp-secrets.php';
                break;
            }
        }
        if ($file !== '') {
            $data = include $file;
            if (is_array($data) && !empty($data['client_id']) && !empty($data['client_secret']) && !empty($data['refresh_token'])) {
                $cfg = $data;
            }
        }
    }
    if ($cfg && wp_get_environment_type() === 'local' && empty($cfg['allow_local'])) {
        return null;
    }
    return $cfg;
}

/**
 * Jeton d'accès (mis en cache), ou WP_Error.
 */
function wamv1_gbp_token(array $cfg)
{
    $cache = get_transient('wamv1_gbp_token');
    if (is_string($cache) && $cache !== '') {
        return $cache;
    }
    $rep = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 15,
        'body'    => [
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $cfg['refresh_token'],
            'grant_type'    => 'refresh_token',
        ],
    ]);
    if (is_wp_error($rep)) {
        return $rep;
    }
    $json = json_decode(wp_remote_retrieve_body($rep), true);
    if (empty($json['access_token'])) {
        $raison = is_array($json) ? (string) ($json['error'] ?? '') : '';
        return new WP_Error('wamv1_gbp_token', 'Jeton refusé (HTTP ' . wp_remote_retrieve_response_code($rep) . ($raison !== '' ? ', ' . $raison : '') . ').');
    }
    set_transient('wamv1_gbp_token', $json['access_token'], max(60, (int) ($json['expires_in'] ?? 3600) - 120));
    return $json['access_token'];
}

/**
 * Appel d'une API Google. Retourne [code HTTP, corps décodé] ou WP_Error.
 */
function wamv1_gbp_request(string $methode, string $url, array $cfg, ?array $corps = null, bool $relance = false)
{
    $token = wamv1_gbp_token($cfg);
    if (is_wp_error($token)) {
        return $token;
    }
    $args = [
        'method'  => $methode,
        'timeout' => 20,
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; charset=utf-8'],
    ];
    if ($corps !== null) {
        $args['body'] = wp_json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $rep = wp_remote_request($url, $args);
    if (is_wp_error($rep)) {
        return $rep;
    }
    $code = (int) wp_remote_retrieve_response_code($rep);
    // Jeton en cache expiré ou révoqué : on en redemande un, une seule fois.
    if ($code === 401 && !$relance) {
        delete_transient('wamv1_gbp_token');
        return wamv1_gbp_request($methode, $url, $cfg, $corps, true);
    }
    return [$code, json_decode(wp_remote_retrieve_body($rep), true)];
}

/**
 * Chemin de la fiche ("accounts/123/locations/456") : celui du fichier de secrets, sinon celui
 * mémorisé, sinon lu par l'API (seulement s'il n'y a qu'une fiche, puis mémorisé). '' si échec.
 */
function wamv1_gbp_location(array $cfg): string
{
    $id = static fn($v, $prefixe) => preg_replace('#^' . $prefixe . '/#', '', trim((string) $v));
    if (!empty($cfg['account_id']) && !empty($cfg['location_id'])) {
        return 'accounts/' . $id($cfg['account_id'], 'accounts') . '/locations/' . $id($cfg['location_id'], 'locations');
    }
    $memo = (string) get_option('wamv1_gbp_location', '');
    if ($memo !== '') {
        return $memo;
    }
    $comptes = wamv1_gbp_request('GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts', $cfg);
    $compte = (!is_wp_error($comptes) && $comptes[0] === 200) ? (string) ($comptes[1]['accounts'][0]['name'] ?? '') : '';
    if ($compte === '') {
        error_log('[wamv1 GBP] comptes : ' . wamv1_gbp_erreur($comptes));
        return '';
    }
    $fiches = wamv1_gbp_request('GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . $compte . '/locations?readMask=name,title', $cfg);
    $liste = (!is_wp_error($fiches) && $fiches[0] === 200) ? (array) ($fiches[1]['locations'] ?? []) : [];
    if (count($liste) !== 1) {
        error_log('[wamv1 GBP] ' . count($liste) . ' fiche(s) trouvée(s) : renseigner account_id et location_id dans wam-gbp-secrets.php.');
        return '';
    }
    $chemin = $compte . '/' . $liste[0]['name'];
    update_option('wamv1_gbp_location', $chemin, false);
    return $chemin;
}

/**
 * Image à la une en JPEG (les médias du site sont en AVIF, que Google refuse), rangée dans
 * uploads/wam-gbp/ pour avoir une URL publique. Retourne l'URL, ou '' sans image exploitable.
 */
function wamv1_gbp_image_url(int $post_id): string
{
    $thumb = (int) get_post_thumbnail_id($post_id);
    $source = $thumb ? get_attached_file($thumb) : '';
    if (!$source || !is_readable($source)) {
        return '';
    }
    $up = wp_upload_dir();
    $nom = 'wp-' . $post_id . '-' . $thumb . '-' . filemtime($source) . '.jpg';
    $dossier = trailingslashit($up['basedir']) . 'wam-gbp/';
    $url = trailingslashit($up['baseurl']) . 'wam-gbp/' . $nom;
    if (is_readable($dossier . $nom)) {
        return $url;
    }
    wp_mkdir_p($dossier);
    $editor = wp_get_image_editor($source);
    if (is_wp_error($editor)) {
        return '';
    }
    $editor->resize(1600, 1600, false);
    $editor->set_quality(85);
    // Le thème force toutes les images générées en AVIF : on neutralise ce filtre pour ce JPEG.
    $neutre = static fn() => [];
    add_filter('image_editor_output_format', $neutre, 999);
    $res = $editor->save($dossier . $nom, 'image/jpeg');
    remove_filter('image_editor_output_format', $neutre, 999);
    if (is_wp_error($res) || empty($res['path']) || !is_readable($res['path'])) {
        return '';
    }
    // Une seule image par contenu : on retire les anciennes versions.
    foreach (glob($dossier . 'wp-' . $post_id . '-*.jpg') ?: [] as $ancien) {
        if (basename($ancien) !== $nom) {
            @unlink($ancien);
        }
    }
    return $url;
}

/**
 * Corps du post Google (sans photo), ou null si non publiable (date manquante).
 */
function wamv1_gbp_build_payload(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, WAMV1_GBP_POST_TYPES, true) || !function_exists('wamv1_oa_timing')) {
        return null;
    }
    $timing = wamv1_oa_timing($post_id);
    if (!$timing) {
        return null;
    }
    [$begin, $end] = $timing;
    $est_stage = $post->post_type === 'stages';

    $titre = trim(html_entity_decode(wp_strip_all_tags(get_the_title($post_id)), ENT_QUOTES, 'UTF-8'));
    if ($est_stage && stripos($titre, 'stage') === false) {
        $titre = 'Stage ' . $titre;
    }
    if (mb_strlen($titre) > 58) { // limite des posts Événement
        $titre = rtrim(mb_substr($titre, 0, 55)) . '...';
    }

    // Résumé (1 500 caractères max) : phrase d'accroche, puis le texte de la fiche. Ni lien ni
    // numéro de téléphone dans le texte (Google refuse le post), le bouton porte le lien.
    $niveau = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'sous_titre', true)));
    if ($est_stage && function_exists('wamv1_seo_stage_description')) {
        $accroche = (string) wamv1_seo_stage_description($post_id);
    } else {
        $accroche = trim(wp_strip_all_tags(get_the_excerpt($post_id)));
        if ($accroche === '') {
            $accroche = $niveau !== '' ? $niveau . '.' : $titre . ' au WAM Dance Studio, à Villeneuve d\'Ascq.';
        }
    }
    $contenu = wp_strip_all_tags($post->post_content);
    $contenu = html_entity_decode($contenu, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $contenu = str_replace(["\xC2\xA0", "\r"], [' ', ''], $contenu);
    $contenu = preg_replace(['/[ \t]+\n/', '/\n[ \t]+/', '/\n{3,}/'], ["\n", "\n", "\n\n"], $contenu);
    $contenu = trim(preg_replace('#https?://\S+#', '', $contenu));
    $lignes = array_filter([html_entity_decode($accroche, ENT_QUOTES, 'UTF-8'), $contenu]);
    $resume = implode("\n\n", $lignes);
    if (mb_strlen($resume) > 1500) {
        $resume = rtrim(mb_substr($resume, 0, 1497)) . '...';
    }

    $date = static fn(DateTimeImmutable $d) => ['year' => (int) $d->format('Y'), 'month' => (int) $d->format('n'), 'day' => (int) $d->format('j')];
    $heure = static fn(DateTimeImmutable $d) => ['hours' => (int) $d->format('G'), 'minutes' => (int) $d->format('i'), 'seconds' => 0, 'nanos' => 0];

    $payload = [
        'languageCode' => 'fr',
        'topicType'    => 'EVENT',
        'summary'      => $resume,
        'event'        => [
            'title'    => $titre,
            'schedule' => [
                'startDate' => $date($begin),
                'startTime' => $heure($begin),
                'endDate'   => $date($end),
                'endTime'   => $heure($end),
            ],
        ],
        'callToAction' => ['actionType' => 'BOOK', 'url' => get_permalink($post_id)],
    ];
    return apply_filters('wamv1_gbp_payload', $payload, $post_id);
}

function wamv1_gbp_erreur($rep): string
{
    if (is_wp_error($rep)) {
        return 'erreur : ' . $rep->get_error_message();
    }
    $e = is_array($rep[1] ?? null) ? ($rep[1]['error'] ?? null) : null;
    $detail = is_array($e) ? trim(($e['status'] ?? '') . ' ' . ($e['message'] ?? '')) : '';
    return 'erreur HTTP ' . $rep[0] . ($detail !== '' ? ' ' . mb_substr($detail, 0, 300) : '');
}

function wamv1_gbp_ok($rep): bool
{
    return !is_wp_error($rep) && $rep[0] >= 200 && $rep[0] < 300;
}

/**
 * Synchronise un stage ou un événement : création, mise à jour ou retrait du post Google.
 */
function wamv1_gbp_sync(int $post_id): void
{
    $cfg = wamv1_gbp_config();
    if (!$cfg) {
        return;
    }
    $post = get_post($post_id);
    $nom = (string) get_post_meta($post_id, '_wam_gbp_name', true);
    $publie = $post && $post->post_status === 'publish' && in_array($post->post_type, WAMV1_GBP_POST_TYPES, true);
    $fiche = wamv1_gbp_location($cfg);
    if ($fiche === '') {
        if ($post) {
            update_post_meta($post_id, '_wam_gbp_status', 'erreur : fiche Google introuvable (voir debug.log)');
        }
        return;
    }

    if ($publie) {
        $payload = wamv1_gbp_build_payload($post_id);
        if (!$payload) {
            update_post_meta($post_id, '_wam_gbp_status', 'ignoré : date manquante');
            return;
        }
        // Google refuse un événement dont la fin est passée : on n'envoie rien, et on retire un
        // éventuel post existant (il serait de toute façon expiré).
        $fin = wamv1_oa_timing($post_id)[1] ?? null;
        if ($fin && $fin < new DateTimeImmutable('now', wp_timezone())) {
            if ($nom !== '') {
                $rep = wamv1_gbp_request('DELETE', WAMV1_GBP_API . $nom, $cfg);
                if (!is_wp_error($rep) && ($rep[0] === 404 || wamv1_gbp_ok($rep))) {
                    delete_post_meta($post_id, '_wam_gbp_name');
                }
            }
            update_post_meta($post_id, '_wam_gbp_status', 'ignoré : événement passé (Google refuse les dates passées)');
            return;
        }
        $image = wamv1_gbp_image_url($post_id);
        $essais = $image !== '' ? [true, false] : [false]; // avec photo, puis sans si Google la refuse
        $rep = null;
        foreach ($essais as $avec_photo) {
            $corps = $payload;
            $masque = 'languageCode,summary,event,callToAction';
            if ($avec_photo) {
                $corps['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => $image]];
                $masque .= ',media';
            }
            if ($nom !== '') {
                unset($corps['topicType']); // non modifiable
                $rep = wamv1_gbp_request('PATCH', WAMV1_GBP_API . $nom . '?updateMask=' . rawurlencode($masque), $cfg, $corps);
                if (!is_wp_error($rep) && $rep[0] === 404) { // post supprimé côté Google : on le recrée
                    $nom = '';
                    delete_post_meta($post_id, '_wam_gbp_name');
                }
            }
            if ($nom === '') {
                $rep = wamv1_gbp_request('POST', WAMV1_GBP_API . $fiche . '/localPosts', $cfg, $corps);
            }
            if (wamv1_gbp_ok($rep)) {
                break;
            }
        }
        if (wamv1_gbp_ok($rep)) {
            $nom_recu = (string) ($rep[1]['name'] ?? $nom);
            if ($nom_recu !== '') {
                update_post_meta($post_id, '_wam_gbp_name', $nom_recu);
            }
            $etat = (string) ($rep[1]['state'] ?? '');
            update_post_meta($post_id, '_wam_gbp_status', ($nom !== '' ? 'mis à jour le ' : 'publié le ') . current_time('d/m/Y H:i') . ($etat !== '' ? ' (état Google : ' . $etat . ')' : ''));
        } else {
            $msg = wamv1_gbp_erreur($rep);
            update_post_meta($post_id, '_wam_gbp_status', $msg);
            error_log('[wamv1 GBP] ' . get_the_title($post_id) . ' (#' . $post_id . ') : ' . $msg);
        }
        return;
    }

    // Brouillon, corbeille ou suppression : retrait, seulement si un post existe côté Google.
    if ($nom === '') {
        if ($post) {
            update_post_meta($post_id, '_wam_gbp_status', 'non publié : rien à envoyer');
        }
        return;
    }
    $rep = wamv1_gbp_request('DELETE', WAMV1_GBP_API . $nom, $cfg);
    $ok = !is_wp_error($rep) && ($rep[0] === 404 || wamv1_gbp_ok($rep));
    if ($post) {
        update_post_meta($post_id, '_wam_gbp_status', $ok ? 'retiré le ' . current_time('d/m/Y H:i') : wamv1_gbp_erreur($rep));
        if ($ok) {
            delete_post_meta($post_id, '_wam_gbp_name');
        }
    }
    if (!$ok) {
        error_log('[wamv1 GBP] retrait #' . $post_id . ' : ' . wamv1_gbp_erreur($rep));
    }
}

/**
 * Lance la synchronisation (en fin de requête) quand un contenu concerné change.
 */
function wamv1_gbp_planifier(int $post_id): void
{
    if (!wamv1_gbp_config() || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (!in_array(get_post_type($post_id), WAMV1_GBP_POST_TYPES, true)) {
        return;
    }
    static $deja = [];
    if (isset($deja[$post_id])) {
        return;
    }
    $deja[$post_id] = true;
    update_post_meta($post_id, '_wam_gbp_status', 'envoi en cours...');

    add_action('shutdown', static function () use ($post_id) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(90);
        wamv1_gbp_sync($post_id);
    });
}
add_action('save_post', 'wamv1_gbp_planifier', 30);
add_action('wp_trash_post', 'wamv1_gbp_planifier');

// Suppression définitive : le contenu n'existera plus en fin de requête, on retire tout de suite.
add_action('before_delete_post', static function ($post_id) {
    $cfg = wamv1_gbp_config();
    $nom = (string) get_post_meta($post_id, '_wam_gbp_name', true);
    if ($cfg && $nom !== '' && in_array(get_post_type($post_id), WAMV1_GBP_POST_TYPES, true)) {
        wamv1_gbp_request('DELETE', WAMV1_GBP_API . $nom, $cfg);
    }
});

/**
 * Encadré "Google Business Profile" dans la fiche : état du dernier envoi + envoi immédiat.
 */
add_action('add_meta_boxes', static function () {
    if (!wamv1_gbp_config()) {
        return;
    }
    foreach (WAMV1_GBP_POST_TYPES as $type) {
        add_meta_box('wamv1_gbp', 'Google Business Profile', static function ($post) {
            $statut = (string) get_post_meta($post->ID, '_wam_gbp_status', true);
            echo '<p>' . esc_html($statut !== '' ? $statut : 'Pas encore envoyé.') . '</p>';
            if ($post->post_status === 'publish') {
                $url = wp_nonce_url(admin_url('admin-post.php?action=wamv1_gbp_now&post=' . (int) $post->ID), 'wamv1_gbp_now_' . (int) $post->ID);
                echo '<p><a class="button" href="' . esc_url($url) . '">Envoyer maintenant</a></p>';
            }
            echo '<p class="description">Envoi automatique à l\'enregistrement (publié = post Événement sur la fiche Google, brouillon ou corbeille = retiré).</p>';
        }, $type, 'side', 'default');
    }
});

add_action('admin_post_wamv1_gbp_now', static function () {
    $id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if (!$id || !current_user_can('edit_post', $id) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wamv1_gbp_now_' . $id)) {
        wp_die('Action non autorisée.', '', ['response' => 403]);
    }
    @set_time_limit(90);
    wamv1_gbp_sync($id);
    wp_safe_redirect(add_query_arg('wamv1_gbp_fait', 1, get_edit_post_link($id, 'raw')));
    exit;
});
add_action('admin_notices', static function () {
    if (isset($_GET['wamv1_gbp_fait'])) {
        echo '<div class="notice notice-info is-dismissible"><p>Envoi à Google effectué : voir l\'encadré Google Business Profile, dans la colonne de droite.</p></div>';
    }
});
