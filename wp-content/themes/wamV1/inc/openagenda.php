<?php
/**
 * Publication automatique des stages et des événements sur OpenAgenda.
 *
 * Principe : chaque stage ou événement publié est envoyé à l'agenda WAM (PUT sur un
 * identifiant externe "wp-{ID}" : crée l'événement s'il n'existe pas, le met à jour
 * sinon). Passé en brouillon, à la corbeille ou supprimé, il est retiré de l'agenda.
 *
 * - Secrets : fichier wam-openagenda-secrets.php à la racine du site ou dans son dossier parent (ignoré par git,
 *   modèle : inc/openagenda-secrets.sample.php). Sans ce fichier, rien ne part.
 * - Garde-fou local : en environnement "local", rien n'est envoyé (une copie de prod
 *   publierait sur le vrai agenda), sauf si le fichier de secrets définit
 *   'allow_local' => true avec la clé d'un agenda de test.
 * - L'envoi passe par le cron WordPress (quelques secondes après l'enregistrement)
 *   pour ne pas ralentir l'admin. Le dernier résultat est dans la méta _wam_oa_status.
 * - Doc API : https://developers.openagenda.com/
 */

if (!defined('ABSPATH')) {
    exit;
}

const WAMV1_OA_POST_TYPES = ['stages', 'evenements'];
const WAMV1_OA_EXT_KEY = 'wam';

/**
 * Configuration : tableau ['agenda_uid','secret_key','location_uid','allow_local'] ou null.
 */
function wamv1_oa_config(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $cfg = null;
        // Racine du site, ou dossier parent (hors du web en prod : /home/<compte>/, plus sûr).
        $file = '';
        foreach ([ABSPATH, dirname(untrailingslashit(ABSPATH)) . '/'] as $dossier) {
            if (is_readable($dossier . 'wam-openagenda-secrets.php')) {
                $file = $dossier . 'wam-openagenda-secrets.php';
                break;
            }
        }
        if ($file !== '') {
            $data = include $file;
            if (is_array($data) && !empty($data['agenda_uid']) && !empty($data['secret_key'])) {
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
 * Debut et fin d'un stage ou d'un événement, en DateTimeImmutable (fuseau du site), ou null.
 */
function wamv1_oa_timing(int $post_id): ?array
{
    $type = get_post_type($post_id);
    $cles = $type === 'stages'
        ? ['date_stage', 'heure_debut', 'heure_de_fin']
        : ['date_event', 'horaire_debut_event', 'horaire_fin_event'];

    $date = (string) get_post_meta($post_id, $cles[0], true); // Ymd
    if (!preg_match('/^\d{8}$/', $date)) {
        return null;
    }
    $heure = static function (string $v): ?string {
        return preg_match('/^(\d{1,2})\s*[:h]\s*(\d{2})?/i', trim($v), $m)
            ? sprintf('%02d:%02d', (int) $m[1], isset($m[2]) ? (int) $m[2] : 0)
            : null;
    };
    $debut = $heure((string) get_post_meta($post_id, $cles[1], true)) ?? '00:00';
    $fin = $heure((string) get_post_meta($post_id, $cles[2], true));

    $tz = wp_timezone();
    $begin = DateTimeImmutable::createFromFormat('Ymd H:i', $date . ' ' . $debut, $tz);
    if (!$begin) {
        return null;
    }
    $end = $fin ? DateTimeImmutable::createFromFormat('Ymd H:i', $date . ' ' . $fin, $tz) : null;
    if (!$end || $end <= $begin) {
        $end = $begin->modify('+2 hours'); // durée par défaut si l'horaire de fin manque
    }
    return [$begin, $end];
}

/**
 * Corps de l'événement OpenAgenda (sans l'enveloppe "data"), ou null si non publiable.
 */
function wamv1_oa_build_payload(int $post_id, int $location_uid = 0): ?array
{
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, WAMV1_OA_POST_TYPES, true)) {
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
    $niveau = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'sous_titre', true)));

    // Description courte (200 caractères max).
    if ($est_stage && function_exists('wamv1_seo_stage_description')) {
        $court = wamv1_seo_stage_description($post_id);
    } else {
        $court = trim(wp_strip_all_tags(get_the_excerpt($post_id)));
        if ($court === '') {
            $court = $niveau !== '' ? $niveau : $titre . ' au WAM Dance Studio, à Villeneuve d\'Ascq.';
        }
    }
    $court = html_entity_decode($court, ENT_QUOTES, 'UTF-8');
    if (mb_strlen($court) > 200) {
        $court = rtrim(mb_substr($court, 0, 197)) . '...';
    }

    // Description longue (markdown, 10 000 caractères max).
    $lignes = [];
    if ($niveau !== '') {
        $lignes[] = $niveau . '.';
    }
    if ($est_stage) {
        if (function_exists('wamv1_seo_stage_intervenant') && ($prof = wamv1_seo_stage_intervenant($post_id)) !== '') {
            $lignes[] = 'Intervenant·e : ' . $prof . '.';
        }
        if (function_exists('wamv1_seo_stage_prix') && ($p = wamv1_seo_stage_prix($post_id)) && $p['min'] > 0) {
            $montant = rtrim(rtrim(number_format($p['min'], 2, ',', ''), '0'), ',');
            $lignes[] = 'Tarif : ' . ($p['nb'] > 1 ? 'dès ' : '') . $montant . ' €.';
        }
    }
    $contenu = wp_strip_all_tags($post->post_content);
    $contenu = html_entity_decode($contenu, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $contenu = str_replace(["\xC2\xA0", "\r"], [' ', ''], $contenu);
    $contenu = preg_replace(['/[ \t]+\n/', '/\n[ \t]+/', '/\n{3,}/'], ["\n", "\n", "\n\n"], $contenu);
    $contenu = trim($contenu);
    if ($contenu !== '') {
        $lignes[] = $contenu;
    }
    $lignes[] = 'Informations et réservation : ' . get_permalink($post_id);
    $long = mb_substr(implode("\n\n", $lignes), 0, 10000);

    $payload = [
        'title'           => mb_substr($titre, 0, 140),
        'description'     => $court,
        'longDescription' => $long,
        'keywords'        => $est_stage
            ? ['danse', 'stage', 'atelier', 'Villeneuve-d\'Ascq', 'Lille']
            : ['danse', 'événement', 'Villeneuve-d\'Ascq', 'Lille'],
        'timings'         => [[
            'begin' => $begin->format('Y-m-d\TH:i:sP'),
            'end'   => $end->format('Y-m-d\TH:i:sP'),
        ]],
        'registration'    => [get_permalink($post_id)],
        'canonicalUrl'    => get_permalink($post_id),
        'attendanceMode'  => 1,
        'state'           => 2, // publié
    ];
    if ($location_uid) {
        $payload['locationUid'] = $location_uid;
    }
    // Langue explicite : avec le seul en-tête "lang", les mots-clés sont rangés sous "en".
    foreach (['title', 'description', 'longDescription', 'keywords'] as $champ) {
        $payload[$champ] = ['fr' => $payload[$champ]];
    }
    return apply_filters('wamv1_openagenda_payload', $payload, $post_id);
}

/**
 * Jeton d'accès (mis en cache), ou WP_Error.
 */
function wamv1_oa_token(array $cfg)
{
    $cache = get_transient('wamv1_oa_token');
    if (is_string($cache) && $cache !== '') {
        return $cache;
    }
    $rep = wp_remote_post('https://api.openagenda.com/v2/requestAccessToken', [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['code' => $cfg['secret_key']]),
    ]);
    if (is_wp_error($rep)) {
        return $rep;
    }
    $json = json_decode(wp_remote_retrieve_body($rep), true);
    if (empty($json['access_token'])) {
        return new WP_Error('wamv1_oa_token', 'Jeton refusé (HTTP ' . wp_remote_retrieve_response_code($rep) . ').');
    }
    $duree = max(60, (int) ($json['expires_in'] ?? 3600) - 60);
    set_transient('wamv1_oa_token', $json['access_token'], $duree);
    return $json['access_token'];
}

/**
 * Appel de l'API événements. Retourne [code HTTP, corps décodé] ou WP_Error.
 */
function wamv1_oa_request(string $methode, string $chemin, array $cfg, ?array $corps = null, string $ressource = 'events', string $image = '')
{
    $token = wamv1_oa_token($cfg);
    if (is_wp_error($token)) {
        return $token;
    }
    $args = [
        'method'  => $methode,
        'timeout' => 20,
        'headers' => ['access-token' => $token, 'Content-Type' => 'application/json', 'lang' => 'fr'],
    ];
    if ($corps !== null && $image !== '' && is_readable($image)) {
        // Envoi avec image : multipart, partie "data" (JSON de l'événement) + partie "image".
        // Une image donnée par simple lien est acceptée par l'API puis ignorée sans erreur.
        $limite = wp_generate_password(24, false);
        $json = wp_json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $args['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $limite;
        $crlf = "\r\n"; // le format multipart exige des CRLF
        $args['body'] = '--' . $limite . $crlf
            . 'Content-Disposition: form-data; name="data"' . $crlf . $crlf
            . $json . $crlf
            . '--' . $limite . $crlf
            . 'Content-Disposition: form-data; name="image"; filename="image.jpg"' . $crlf
            . 'Content-Type: image/jpeg' . $crlf . $crlf
            . file_get_contents($image) . $crlf
            . '--' . $limite . '--' . $crlf;
    } elseif ($corps !== null) {
        $args['body'] = wp_json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $url = 'https://api.openagenda.com/v2/agendas/' . rawurlencode((string) $cfg['agenda_uid']) . '/' . $ressource . $chemin;
    $rep = wp_remote_request($url, $args);
    if (is_wp_error($rep)) {
        return $rep;
    }
    return [(int) wp_remote_retrieve_response_code($rep), json_decode(wp_remote_retrieve_body($rep), true)];
}

/**
 * Image à la une convertie en JPEG (les médias du site sont en AVIF, que l'API peut refuser).
 * Retourne le chemin d'un fichier temporaire, ou '' s'il n'y a pas d'image exploitable.
 */
function wamv1_oa_image_jpeg(int $post_id): string
{
    $thumb = (int) get_post_thumbnail_id($post_id);
    $source = $thumb ? get_attached_file($thumb) : '';
    if (!$source || !is_readable($source)) {
        return '';
    }
    $editor = wp_get_image_editor($source);
    if (is_wp_error($editor)) {
        return '';
    }
    $editor->resize(1600, 1600, false);
    $editor->set_quality(85);
    $dest = wp_tempnam('wam-oa-') . '.jpg';
    // Le thème force toutes les images générées en AVIF : on neutralise ce filtre pour ce JPEG.
    $neutre = static fn() => [];
    add_filter('image_editor_output_format', $neutre, 999);
    $res = $editor->save($dest, 'image/jpeg');
    remove_filter('image_editor_output_format', $neutre, 999);
    @unlink(substr($dest, 0, -4)); // wp_tempnam crée aussi un fichier sans extension
    return (!is_wp_error($res) && !empty($res['path']) && is_readable($res['path'])) ? $res['path'] : '';
}

/**
 * Identifiant du lieu du studio dans l'agenda : celui du fichier de secrets, sinon celui
 * mémorisé, sinon on le crée une fois (puis on le mémorise).
 */
function wamv1_oa_location_uid(array $cfg): int
{
    if (!empty($cfg['location_uid'])) {
        return (int) $cfg['location_uid'];
    }
    $memo = (int) get_option('wamv1_oa_location_uid', 0);
    if ($memo) {
        return $memo;
    }
    $place = function_exists('wamv1_schema_place_wam') ? wamv1_schema_place_wam() : [];
    $adr = $place['address'] ?? [];
    $rue = preg_replace('/\s+à\s+Villeneuve.*$/iu', '', (string) ($adr['streetAddress'] ?? ''));
    $corps = [
        'name'        => $place['name'] ?? 'WAM Dance Studio',
        'address'     => trim($rue . ', ' . ($adr['postalCode'] ?? '') . ' ' . ($adr['addressLocality'] ?? ''), ' ,'),
        'countryCode' => 'FR',
    ];
    if (!empty($place['geo']['latitude'])) {
        $corps['latitude'] = $place['geo']['latitude'];
        $corps['longitude'] = $place['geo']['longitude'];
    }
    $rep = wamv1_oa_request('POST', '', $cfg, $corps, 'locations');
    $uid = (!is_wp_error($rep) && $rep[0] >= 200 && $rep[0] < 300) ? (int) ($rep[1]['location']['uid'] ?? 0) : 0;
    if ($uid) {
        update_option('wamv1_oa_location_uid', $uid, false);
    } else {
        error_log('[wamv1 OpenAgenda] création du lieu : ' . wamv1_oa_erreur($rep));
    }
    return $uid;
}

/**
 * Synchronise un stage ou un événement : publication, mise à jour ou retrait.
 */
function wamv1_oa_sync(int $post_id): void
{
    $cfg = wamv1_oa_config();
    if (!$cfg) {
        return;
    }
    $post = get_post($post_id);
    $chemin = '/ext/' . WAMV1_OA_EXT_KEY . '/wp-' . $post_id;
    $publie = $post && $post->post_status === 'publish' && in_array($post->post_type, WAMV1_OA_POST_TYPES, true);

    if ($publie) {
        $lieu = wamv1_oa_location_uid($cfg);
        if (!$lieu) {
            update_post_meta($post_id, '_wam_oa_status', 'erreur : lieu OpenAgenda introuvable (voir debug.log)');
            return;
        }
        $payload = wamv1_oa_build_payload($post_id, $lieu);
        if (!$payload) {
            update_post_meta($post_id, '_wam_oa_status', 'ignoré : date manquante');
            return;
        }
        // Un événement dont la date est passée n'est pas (re)publié.
        if (strtotime($payload['timings'][0]['end']) < time()) {
            update_post_meta($post_id, '_wam_oa_status', 'ignoré : date passée');
            return;
        }
        $image = wamv1_oa_image_jpeg($post_id);
        $rep = $image !== '' ? wamv1_oa_request('PUT', $chemin, $cfg, $payload, 'events', $image) : null;
        if ($image !== '') {
            @unlink($image);
        }
        // Sans image, ou si l'envoi avec image est refusé : l'événement est publié sans visuel.
        if ($rep === null || is_wp_error($rep) || $rep[0] >= 400) {
            $rep = wamv1_oa_request('PUT', $chemin, $cfg, $payload);
            if (!is_wp_error($rep) && $rep[0] === 400) { // repli : corps sous une clé "data"
                $rep = wamv1_oa_request('PUT', $chemin, $cfg, ['data' => $payload]);
            }
        }
        $ok = !is_wp_error($rep) && $rep[0] >= 200 && $rep[0] < 300;
        $msg = $ok ? 'publié le ' . current_time('d/m/Y H:i') : wamv1_oa_erreur($rep);
        update_post_meta($post_id, '_wam_oa_status', $msg);
        if ($ok) {
            update_post_meta($post_id, '_wam_oa_synced', 1);
        } else {
            error_log('[wamv1 OpenAgenda] ' . get_the_title($post_id) . ' (#' . $post_id . ') : ' . $msg);
        }
        return;
    }

    // Brouillon, corbeille ou suppression : retrait, seulement si déjà publié sur l'agenda.
    if ($post && !get_post_meta($post_id, '_wam_oa_synced', true)) {
        update_post_meta($post_id, '_wam_oa_status', 'non publié : rien à envoyer');
        return;
    }
    $rep = wamv1_oa_request('DELETE', $chemin, $cfg);
    $ok = !is_wp_error($rep) && ($rep[0] === 404 || ($rep[0] >= 200 && $rep[0] < 300));
    if ($post) {
        update_post_meta($post_id, '_wam_oa_status', $ok ? 'retiré le ' . current_time('d/m/Y H:i') : wamv1_oa_erreur($rep));
        if ($ok) {
            delete_post_meta($post_id, '_wam_oa_synced');
        }
    }
    if (!$ok) {
        error_log('[wamv1 OpenAgenda] retrait #' . $post_id . ' : ' . wamv1_oa_erreur($rep));
    }
}
add_action('wamv1_oa_sync', 'wamv1_oa_sync');

function wamv1_oa_erreur($rep): string
{
    if (is_wp_error($rep)) {
        return 'erreur : ' . $rep->get_error_message();
    }
    $detail = is_array($rep[1] ?? null) ? wp_json_encode($rep[1], JSON_UNESCAPED_UNICODE) : '';
    return 'erreur HTTP ' . $rep[0] . ' ' . mb_substr($detail, 0, 300);
}

/**
 * Planifie la synchronisation (cron, dans 10 secondes) quand un contenu concerné change.
 */
function wamv1_oa_planifier(int $post_id): void
{
    if (!wamv1_oa_config() || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (!in_array(get_post_type($post_id), WAMV1_OA_POST_TYPES, true)) {
        return;
    }
    // Un seul envoi par contenu et par requête (save_post peut se déclencher plusieurs fois).
    static $deja = [];
    if (isset($deja[$post_id])) {
        return;
    }
    $deja[$post_id] = true;
    update_post_meta($post_id, '_wam_oa_status', 'envoi en cours...');

    // Envoi juste après la réponse au navigateur : pas d'attente du cron WordPress (qui ne
    // se déclenche que sur une visite) et pas de ralentissement pour la personne qui enregistre.
    add_action('shutdown', static function () use ($post_id) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(90);
        wamv1_oa_sync($post_id);
    });
}
add_action('save_post', 'wamv1_oa_planifier', 30);
add_action('wp_trash_post', 'wamv1_oa_planifier');

// Suppression définitive : le contenu n'existera plus quand le cron passera, on retire tout de suite.
add_action('before_delete_post', static function ($post_id) {
    if (wamv1_oa_config() && in_array(get_post_type($post_id), WAMV1_OA_POST_TYPES, true)
        && get_post_meta($post_id, '_wam_oa_synced', true)) {
        $cfg = wamv1_oa_config();
        wamv1_oa_request('DELETE', '/ext/' . WAMV1_OA_EXT_KEY . '/wp-' . (int) $post_id, $cfg);
    }
});

/**
 * Colonne d'état dans la liste des stages et des événements.
 */
foreach (WAMV1_OA_POST_TYPES as $type_oa) {
    add_filter("manage_{$type_oa}_posts_columns", static function ($cols) {
        $cols['wam_oa'] = 'OpenAgenda';
        return $cols;
    });
    add_action("manage_{$type_oa}_posts_custom_column", static function ($col, $post_id) {
        if ($col === 'wam_oa') {
            echo esc_html((string) get_post_meta($post_id, '_wam_oa_status', true) ?: '-');
        }
    }, 10, 2);
}

/**
 * Encadré "OpenAgenda" dans la fiche d'un stage ou d'un événement : état du dernier envoi.
 * (Le plugin Admin Columns ignore les colonnes ajoutées par le code : l'encadré, lui, s'affiche toujours.)
 */
add_action('add_meta_boxes', static function () {
    if (!wamv1_oa_config()) {
        return;
    }
    foreach (WAMV1_OA_POST_TYPES as $type) {
        add_meta_box('wamv1_oa', 'OpenAgenda', static function ($post) {
            $statut = (string) get_post_meta($post->ID, '_wam_oa_status', true);
            echo '<p>' . esc_html($statut !== '' ? $statut : 'Pas encore envoyé.') . '</p>';
            if ($post->post_status === 'publish') {
                $url = wp_nonce_url(admin_url('admin-post.php?action=wamv1_oa_now&post=' . (int) $post->ID), 'wamv1_oa_now_' . (int) $post->ID);
                echo '<p><a class="button" href="' . esc_url($url) . '">Envoyer maintenant</a></p>';
            }
            echo '<p class="description">Envoi automatique à l\'enregistrement (publié = ajouté à l\'agenda, brouillon ou corbeille = retiré).</p>';
        }, $type, 'side', 'default');
    }
});

/**
 * Action groupée "Envoyer à OpenAgenda" dans la liste des stages et des événements
 * (utile pour les contenus déjà publiés avant la mise en place). Envois étalés par le cron.
 */
foreach (WAMV1_OA_POST_TYPES as $type_oa) {
    add_filter("bulk_actions-edit-{$type_oa}", static function ($actions) {
        if (wamv1_oa_config()) {
            $actions['wamv1_oa_send'] = 'Envoyer à OpenAgenda';
        }
        return $actions;
    });
    add_filter("handle_bulk_actions-edit-{$type_oa}", static function ($redirect, $action, $ids) {
        if ($action !== 'wamv1_oa_send' || !wamv1_oa_config()) {
            return $redirect;
        }
        $n = 0;
        foreach ((array) $ids as $i => $id) {
            $id = (int) $id;
            if (current_user_can('edit_post', $id) && !wp_next_scheduled('wamv1_oa_sync', [$id])) {
                wp_schedule_single_event(time() + 5 + $n * 6, 'wamv1_oa_sync', [$id]);
                $n++;
            }
        }
        return add_query_arg('wamv1_oa_planifies', $n, $redirect);
    }, 10, 3);
}
add_action('admin_notices', static function () {
    if (isset($_GET['wamv1_oa_planifies'])) {
        $n = (int) $_GET['wamv1_oa_planifies'];
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(
            '%d contenu(s) planifié(s) pour envoi à OpenAgenda (quelques secondes chacun). Le résultat s\'affiche dans l\'encadré OpenAgenda de chaque fiche.',
            $n
        )) . '</p></div>';
    }
});

/**
 * Bouton "Envoyer maintenant" de l'encadré : envoi immédiat, résultat visible tout de suite.
 */
add_action('admin_post_wamv1_oa_now', static function () {
    $id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if (!$id || !current_user_can('edit_post', $id) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wamv1_oa_now_' . $id)) {
        wp_die('Action non autorisée.', '', ['response' => 403]);
    }
    @set_time_limit(90);
    wamv1_oa_sync($id);
    wp_safe_redirect(add_query_arg('wamv1_oa_fait', 1, get_edit_post_link($id, 'raw')));
    exit;
});
add_action('admin_notices', static function () {
    if (isset($_GET['wamv1_oa_fait'])) {
        echo '<div class="notice notice-info is-dismissible"><p>Envoi à OpenAgenda effectué : voir l\'encadré OpenAgenda, dans la colonne de droite.</p></div>';
    }
});
