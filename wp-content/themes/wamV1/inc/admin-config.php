<?php
/**
 * Page d'administration : Configuration WAM
 *
 * Expose un groupe d'options `wam_config` via le Settings API WordPress.
 * Accessible sous Réglages > Configuration WAM (capacité manage_options).
 *
 * Options stockées :
 *   inscriptions_actives         (bool)   — interrupteur global : affiche le bouton d'inscription des cours
 *   btn_inscription_texte        (string) — libellé du bouton d'inscription
 *   btn_cours_desactive          (bool)   — si true : bouton affiché mais désactivé (cliquable = non)
 *   btn_cours_desactive_texte    (string) — texte du bouton quand il est désactivé
 *   message_inscriptions_fermees (string) — message affiché si inscriptions fermées
 *   inscription_ouverture        (int)    — timestamp UTC d'ouverture programmée (0 = désactivé)
 *   inscription_fermeture        (int)    — timestamp UTC de fermeture programmée (0 = désactivé)
 *
 * Helper functions (utilisables partout dans le thème) :
 *   wam_inscriptions_actives()           — source de vérité (tient compte de la programmation)
 *   wam_btn_inscription_texte()
 *   wam_btn_inscription_url()            — retourne toujours "#inscription" (URL fixe)
 *   wam_message_inscriptions_fermees()
 *   wam_adresse_visible()                — boolean, true par défaut
 *   wam_nom_lieu()                       — string, nom du lieu "WAM Dance Studio"
 *   wam_adresse_lieu()                   — string, adresse avec retours éventuels
 *
 * @package wamv1
 */

// -------------------------------------------------------
// Settings API — enregistrement
// -------------------------------------------------------
// -------------------------------------------------------
// Liste des réglages (clés et types)
// -------------------------------------------------------
function wam_get_settings_keys(): array
{
    return [
        'inscriptions_actives'         => ['type' => 'boolean', 'default' => true],
        'btn_inscription_texte'        => ['type' => 'string',  'default' => 'Ajouter ce cours au panier'],
        'btn_cours_desactive'          => ['type' => 'boolean', 'default' => false],
        'btn_cours_desactive_texte'    => ['type' => 'string',  'default' => 'Inscriptions bientôt disponibles'],
        'message_inscriptions_fermees' => ['type' => 'string',  'default' => 'Les inscriptions sont actuellement fermées.'],
        'inscription_ouverture'        => ['type' => 'integer', 'default' => 0],
        'inscription_fermeture'        => ['type' => 'integer', 'default' => 0],
        'adresse_visible'              => ['type' => 'boolean', 'default' => true],
        'nom_lieu'                     => ['type' => 'string',  'default' => 'WAM Dance Studio'],
        'adresse_lieu'                 => ['type' => 'string',  'default' => "202 rue Jean Jaurès\nVilleneuve d'Ascq"],
        'show_rentree'                 => ['type' => 'boolean', 'default' => false],
        'date_rentree'                 => ['type' => 'string',  'default' => ''],
        'url_instagram'                => ['type' => 'string',  'default' => ''],
        'url_facebook'                 => ['type' => 'string',  'default' => ''],
        'url_tiktok'                   => ['type' => 'string',  'default' => ''],
        'url_linkedin'                 => ['type' => 'string',  'default' => ''],
        'url_youtube'                  => ['type' => 'string',  'default' => ''],
        'smtp_host'                    => ['type' => 'string',  'default' => ''],
        'smtp_port'                    => ['type' => 'integer', 'default' => 465],
        'smtp_user'                    => ['type' => 'string',  'default' => ''],
        'smtp_pass'                    => ['type' => 'string',  'default' => ''],
        'smtp_secure'                  => ['type' => 'string',  'default' => 'ssl'],
        'smtp_from_email'              => ['type' => 'string',  'default' => get_option('admin_email')],
        'smtp_from_name'               => ['type' => 'string',  'default' => 'WAM Dance Studio'],
        'smtp_to_emails'               => ['type' => 'string',  'default' => ''],
    ];
}

// -------------------------------------------------------
// Migration automatique (wam_config -> options individuelles)
// -------------------------------------------------------
function wam_migrate_config_to_individual_options(): void
{
    // Si la migration a déjà eu lieu, on ne fait plus rien
    if (get_option('wam_setting_migrated') === '1') {
        return;
    }

    $old_config = get_option('wam_config');
    if (!$old_config || !is_array($old_config)) {
        // Même si pas d'ancienne config, on marque comme migré pour ne plus checker
        update_option('wam_setting_migrated', '1');
        return;
    }

    $keys = wam_get_settings_keys();
    foreach ($keys as $key => $meta) {
        $option_name = 'wam_setting_' . $key;
        
        // On ne migre que si l'option individuelle n'existe absolument pas en BDD
        if (get_option($option_name) === false) {
            $val = $old_config[$key] ?? $meta['default'];
            update_option($option_name, $val);
        }
    }

    // On marque la migration comme terminée
    update_option('wam_setting_migrated', '1');
}
add_action('admin_init', 'wam_migrate_config_to_individual_options');

// -------------------------------------------------------
// Settings API — enregistrement
// -------------------------------------------------------
function wam_config_register_settings(): void
{
    $keys = wam_get_settings_keys();
    foreach ($keys as $key => $meta) {
        $args = [
            'type'              => $meta['type'],
            'default'           => $meta['default'],
            'sanitize_callback' => null,
        ];

        // Sanitizers spécifiques
        if ($meta['type'] === 'boolean') {
            $args['sanitize_callback'] = 'wam_sanitize_checkbox';
        } elseif (strpos($key, 'inscription_') === 0) {
            $args['sanitize_callback'] = 'wam_sanitize_datetime_to_timestamp';
        }

        register_setting('wam_config_group', 'wam_setting_' . $key, $args);
    }



    add_settings_section(
        'wam_section_inscriptions',
        'Inscriptions',
        '', // pas de callback pour la description
        'wam-config-general'
    );

    add_settings_field(
        'inscriptions_actives',
        'Activer le bouton pour les cours',
        'wam_field_inscriptions_actives',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'btn_inscription_texte',
        'Texte du bouton',
        'wam_field_btn_texte',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'btn_cours_desactive',
        'Désactiver l\'utilisation du bouton',
        'wam_field_btn_cours_desactive',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'btn_cours_desactive_texte',
        'Texte du bouton désactivé',
        'wam_field_btn_cours_desactive_texte',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'message_inscriptions_fermees',
        'Message si fermées',
        'wam_field_message_ferme',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'inscription_ouverture',
        'Ouverture programmée',
        'wam_field_inscription_ouverture',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'inscription_fermeture',
        'Fermeture programmée',
        'wam_field_inscription_fermeture',
        'wam-config-general',
        'wam_section_inscriptions'
    );

    add_settings_section(
        'wam_section_rentree',
        'Informations de la rentrée',
        '', // pas de callback
        'wam-config-general'
    );

    add_settings_field(
        'show_rentree',
        'Afficher la date de rentrée',
        'wam_field_show_rentree',
        'wam-config-general',
        'wam_section_rentree'
    );

    add_settings_field(
        'date_rentree',
        'Date de rentrée',
        'wam_field_date_rentree',
        'wam-config-general',
        'wam_section_rentree'
    );

    add_settings_section(
        'wam_section_lieux',
        'Localisation & Lieux',
        '', // pas de callback
        'wam-config-general'
    );

    add_settings_field(
        'adresse_visible',
        'Afficher l\'adresse sur le site',
        'wam_field_adresse_visible',
        'wam-config-general',
        'wam_section_lieux'
    );

    add_settings_field(
        'nom_lieu',
        'Nom du lieu',
        'wam_field_nom_lieu',
        'wam-config-general',
        'wam_section_lieux'
    );

    add_settings_field(
        'adresse_lieu',
        'Adresse complète',
        'wam_field_adresse_lieu',
        'wam-config-general',
        'wam_section_lieux'
    );

    add_settings_section(
        'wam_section_socials',
        'Réseaux Sociaux',
        '', // pas de callback
        'wam-config-socials'
    );

    add_settings_field('url_instagram', 'Lien Instagram', 'wam_field_url_instagram', 'wam-config-socials', 'wam_section_socials');
    add_settings_field('url_facebook', 'Lien Facebook', 'wam_field_url_facebook', 'wam-config-socials', 'wam_section_socials');
    add_settings_field('url_tiktok', 'Lien TikTok', 'wam_field_url_tiktok', 'wam-config-socials', 'wam_section_socials');
    add_settings_field('url_linkedin', 'Lien LinkedIn', 'wam_field_url_linkedin', 'wam-config-socials', 'wam_section_socials');
    add_settings_field('url_youtube', 'Lien YouTube', 'wam_field_url_youtube', 'wam-config-socials', 'wam_section_socials');

    add_settings_section(
        'wam_section_smtp',
        'Configuration SMTP (Envoi Email)',
        'wam_section_smtp_desc',
        'wam-config-smtp'
    );
    add_settings_field('smtp_host', 'Serveur SMTP (Hôte)', 'wam_field_smtp_host', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_port', 'Port', 'wam_field_smtp_port', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_user', 'Identifiant / Email', 'wam_field_smtp_user', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_pass', 'Mot de passe', 'wam_field_smtp_pass', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_secure', 'Sécurité (SSL/TLS)', 'wam_field_smtp_secure', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_from_email', 'Email expéditeur par défaut', 'wam_field_smtp_from_email', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_from_name', 'Nom expéditeur par défaut', 'wam_field_smtp_from_name', 'wam-config-smtp', 'wam_section_smtp');
    add_settings_field('smtp_to_emails', 'Destinataire(s) des formulaires', 'wam_field_smtp_to_emails', 'wam-config-smtp', 'wam_section_smtp');

}
add_action('admin_init', 'wam_config_register_settings');

// -------------------------------------------------------
// Sanitize callback (obsolète mais gardée pour référence ou hooks spécifiques)
// -------------------------------------------------------
// Sanitizers individuels
// -------------------------------------------------------

/**
 * Sanitize pour les checkbox : force la valeur à boolean.
 */
function wam_sanitize_checkbox($value): bool
{
    return (bool) $value;
}

/**
 * Convertit une chaîne datetime-local (Y-m-dTH:i) en timestamp UTC.
 */
function wam_sanitize_datetime_to_timestamp($value): int
{
    if (empty($value)) {
        return 0;
    }

    // Si c'est déjà un timestamp (ex: migration ou save répété), on le garde
    if (is_numeric($value)) {
        return (int) $value;
    }

    try {
        $tz_paris = new DateTimeZone('Europe/Paris');
        $tz_utc   = new DateTimeZone('UTC');
        $dt       = new DateTime($value, $tz_paris);
        $dt->setTimezone($tz_utc);
        return $dt->getTimestamp();
    } catch (Exception $e) {
        return 0;
    }
}

function wam_update_cron_on_setting_change($old_value, $new_value): void
{
    // On récupère toutes les valeurs pour reprogrammer proprement
    $opts = [
        'inscription_ouverture' => get_option('wam_setting_inscription_ouverture', 0),
        'inscription_fermeture' => get_option('wam_setting_inscription_fermeture', 0),
    ];
    wam_schedule_inscription_cron($opts);
}
add_action('update_option_wam_setting_inscription_ouverture', 'wam_update_cron_on_setting_change', 10, 2);
add_action('update_option_wam_setting_inscription_fermeture', 'wam_update_cron_on_setting_change', 10, 2);



// -------------------------------------------------------
// Callbacks des champs
// -------------------------------------------------------
function wam_field_url_instagram(): void {
    $val = esc_attr(get_option('wam_setting_url_instagram', ''));
    echo '<input type="url" name="wam_setting_url_instagram" value="' . $val . '" class="regular-text">';
}
function wam_field_url_facebook(): void {
    $val = esc_attr(get_option('wam_setting_url_facebook', ''));
    echo '<input type="url" name="wam_setting_url_facebook" value="' . $val . '" class="regular-text">';
}
function wam_field_url_tiktok(): void {
    $val = esc_attr(get_option('wam_setting_url_tiktok', ''));
    echo '<input type="url" name="wam_setting_url_tiktok" value="' . $val . '" class="regular-text">';
}
function wam_field_url_linkedin(): void {
    $val = esc_attr(get_option('wam_setting_url_linkedin', ''));
    echo '<input type="url" name="wam_setting_url_linkedin" value="' . $val . '" class="regular-text">';
}
function wam_field_url_youtube(): void {
    $val = esc_attr(get_option('wam_setting_url_youtube', ''));
    echo '<input type="url" name="wam_setting_url_youtube" value="' . $val . '" class="regular-text">';
}

// --- Callbacks SMTP ---
function wam_section_smtp_desc(): void {
    echo '<p>Configurez ici les accès à votre serveur d\'envoi email (SMTP) pour garantir la bonne réception de vos messages de contact et notifications. Laissez vide si vous souhaitez utiliser le système par défaut de votre serveur d\'hébergement (déconseillé).</p>';
}
function wam_field_smtp_host(): void {
    $val = esc_attr(get_option('wam_setting_smtp_host', ''));
    echo '<input type="text" name="wam_setting_smtp_host" value="' . $val . '" class="regular-text" placeholder="ex: smtp.gmail.com">';
}
function wam_field_smtp_port(): void {
    $val = esc_attr(get_option('wam_setting_smtp_port', '465'));
    echo '<input type="number" name="wam_setting_smtp_port" value="' . $val . '" class="small-text">';
}
function wam_field_smtp_user(): void {
    $val = esc_attr(get_option('wam_setting_smtp_user', ''));
    echo '<input type="text" name="wam_setting_smtp_user" value="' . $val . '" class="regular-text">';
}
function wam_field_smtp_pass(): void {
    $val = esc_attr(get_option('wam_setting_smtp_pass', ''));
    echo '<input type="password" name="wam_setting_smtp_pass" value="' . $val . '" class="regular-text" placeholder="Votre mot de passe">';
    echo '<p class="description">Le mot de passe sera enregistré en toute sécurité dans la base de données.</p>';
}
function wam_field_smtp_secure(): void {
    $val = esc_attr(get_option('wam_setting_smtp_secure', 'ssl'));
    echo '<select name="wam_setting_smtp_secure">
        <option value="ssl" ' . selected($val, 'ssl', false) . '>SSL (recommandé port 465)</option>
        <option value="tls" ' . selected($val, 'tls', false) . '>TLS (recommandé port 587)</option>
        <option value="none" ' . selected($val, 'none', false) . '>Aucune (non recommandé)</option>
    </select>';
}
function wam_field_smtp_from_email(): void {
    $val = esc_attr(get_option('wam_setting_smtp_from_email', get_option('admin_email')));
    echo '<input type="email" name="wam_setting_smtp_from_email" value="' . $val . '" class="regular-text">';
}
function wam_field_smtp_from_name(): void {
    $val = esc_attr(get_option('wam_setting_smtp_from_name', 'WAM Dance Studio'));
    echo '<input type="text" name="wam_setting_smtp_from_name" value="' . $val . '" class="regular-text">';
}
function wam_field_smtp_to_emails(): void {
    $val = esc_attr(get_option('wam_setting_smtp_to_emails', ''));
    echo '<input type="text" name="wam_setting_smtp_to_emails" value="' . $val . '" class="large-text" placeholder="ex: contact@wamdancestudio.fr, direction@wamdancestudio.fr">';
    echo '<p class="description">Séparez les adresses e-mail par une virgule. Si ce champ est vide, l\'e-mail de l\'administrateur du site ('. get_option('admin_email') .') sera utilisé.</p>';
}

function wam_field_inscriptions_actives(): void
{
    $checked = (bool) get_option('wam_setting_inscriptions_actives', true);
    ?>
    <label>
        <input type="hidden" name="wam_setting_inscriptions_actives" value="0">
        <input type="checkbox"
               id="wam-inscriptions-actives"
               name="wam_setting_inscriptions_actives"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher le bouton d'inscription sur les fiches de cours
    </label>
    <p class="description">Quand décoché, le bouton est masqué et un message de remplacement est affiché ci-dessous.</p>
    <?php
}

function wam_field_btn_texte(): void
{
    $val = esc_attr(get_option('wam_setting_btn_inscription_texte', 'Ajouter ce cours au panier'));
    echo '<span id="wam-row-btn-texte">';
    echo '<input type="text" name="wam_setting_btn_inscription_texte" value="' . $val . '" class="regular-text">';
    echo '<p class="description">Texte affiché sur le bouton quand les inscriptions sont actives.</p>';
    echo '</span>';
}

function wam_field_btn_cours_desactive(): void
{
    $checked = (bool) get_option('wam_setting_btn_cours_desactive', false);
    ?>
    <label>
        <input type="hidden" name="wam_setting_btn_cours_desactive" value="0">
        <input type="checkbox"
               id="wam-btn-cours-desactive"
               name="wam_setting_btn_cours_desactive"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher le bouton en mode <strong>désactivé</strong> (visible mais non cliquable)
    </label>
    <p class="description">Utile pour signaler que les inscriptions arrivent bientôt, sans cacheter le bouton. Nécessite que le bouton soit activé ci-dessus.</p>
    <?php
}

function wam_field_btn_cours_desactive_texte(): void
{
    $val = esc_attr(get_option('wam_setting_btn_cours_desactive_texte', 'Inscriptions bientôt disponibles'));
    echo '<span id="wam-row-btn-desactive-texte">';
    echo '<input type="text" name="wam_setting_btn_cours_desactive_texte" value="' . $val . '" class="regular-text">';
    echo '<p class="description">Texte affiché sur le bouton désactivé (grisé, non cliquable).</p>';
    echo '</span>';
}

function wam_field_message_ferme(): void
{
    $val = esc_textarea(get_option('wam_setting_message_inscriptions_fermees', 'Les inscriptions sont actuellement fermées.'));
    echo '<span id="wam-row-message-ferme">';
    echo '<textarea name="wam_setting_message_inscriptions_fermees" rows="3" class="large-text">' . $val . '</textarea>';
    echo '<p class="description">Affiché à la place du bouton quand les inscriptions sont désactivées (bouton masqué).</p>';
    echo '</span>';
}

function wam_field_inscription_ouverture(): void
{
    $val_raw = get_option('wam_setting_inscription_ouverture', '');
    
    // Si c'est déjà un timestamp (stocké par l'ancien système ou via sanitize), on le garde tel quel
    // Sinon, c'est peut-être la chaîne datetime-local directe (si on ne sanitize pas via register_setting)
    $ts = is_numeric($val_raw) ? (int)$val_raw : 0;

    $val = '';
    if ($ts > 0) {
        $dt  = new DateTime('@' . $ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Paris'));
        $val = $dt->format('Y-m-d\TH:i');
    } elseif (!empty($val_raw) && !is_numeric($val_raw)) {
        $val = $val_raw;
    }

    echo '<span id="wam-row-inscription-ouverture">';
    echo '<input type="datetime-local" id="wam-inscription-ouverture" name="wam_setting_inscription_ouverture" value="' . esc_attr($val) . '" class="regular-text">';
    echo '<button type="button" class="button button-small wam-clear-datetime" data-target="wam-inscription-ouverture" style="margin-left:8px;">Effacer</button>';
    echo '<p class="description">Heure de Paris (Europe/Paris). Si définie, les inscriptions s\'ouvriront automatiquement à cette date/heure, <strong>quelle que soit la case à cocher ci-dessus</strong>.</p>';
    echo '</span>';
}

function wam_field_inscription_fermeture(): void
{
    $val_raw = get_option('wam_setting_inscription_fermeture', '');
    $ts = is_numeric($val_raw) ? (int)$val_raw : 0;

    $val = '';
    if ($ts > 0) {
        $dt  = new DateTime('@' . $ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Paris'));
        $val = $dt->format('Y-m-d\TH:i');
    } elseif (!empty($val_raw) && !is_numeric($val_raw)) {
        $val = $val_raw;
    }

    echo '<span id="wam-row-inscription-fermeture">';
    echo '<input type="datetime-local" id="wam-inscription-fermeture" name="wam_setting_inscription_fermeture" value="' . esc_attr($val) . '" class="regular-text">';
    echo '<button type="button" class="button button-small wam-clear-datetime" data-target="wam-inscription-fermeture" style="margin-left:8px;">Effacer</button>';
    echo '<p class="description">Heure de Paris (Europe/Paris). Si définie, les inscriptions se fermeront automatiquement à cette date/heure.</p>';
    echo '</span>';
}

function wam_field_show_rentree(): void
{
    $checked = (bool) get_option('wam_setting_show_rentree', false);
    ?>
    <label>
        <input type="hidden" name="wam_setting_show_rentree" value="0">
        <input type="checkbox"
               id="wam-show-rentree"
               name="wam_setting_show_rentree"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher la date de rentrée sur toutes les fiches de cours.
    </label>
    <?php
}

function wam_field_date_rentree(): void
{
    $val = esc_attr(get_option('wam_setting_date_rentree', ''));
    echo '<span id="wam-row-date-rentree">';
    echo '<input type="text" name="wam_setting_date_rentree" value="' . $val . '" class="regular-text" placeholder="ex: 19/04/2025">';
    echo '<p class="description">Le texte qui sera affiché après "Date de rentrée : ".</p>';
    echo '</span>';
}

function wam_field_adresse_visible(): void
{
    $checked = (bool) get_option('wam_setting_adresse_visible', true);
    ?>
    <label>
        <input type="hidden" name="wam_setting_adresse_visible" value="0">
        <input type="checkbox"
               id="wam-adresse-visible"
               name="wam_setting_adresse_visible"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher le bloc adresse publiquement sur le site (pages de cours, événements...).
    </label>
    <?php
}

function wam_field_nom_lieu(): void
{
    $val = esc_attr(get_option('wam_setting_nom_lieu', 'WAM Dance Studio'));
    echo '<span id="wam-row-nom-lieu">';
    echo '<input type="text" name="wam_setting_nom_lieu" value="' . $val . '" class="regular-text">';
    echo '</span>';
}

function wam_field_adresse_lieu(): void
{
    $val = esc_textarea(get_option('wam_setting_adresse_lieu', "202 rue Jean Jaurès\nVilleneuve d'Ascq"));
    echo '<span id="wam-row-adresse-lieu">';
    echo '<textarea name="wam_setting_adresse_lieu" rows="3" class="large-text">' . $val . '</textarea>';
    echo '<p class="description">Utilisez la touche Entrée pour séparer les lignes de votre adresse. Le système les affichera correctement en HTML.</p>';
    echo '</span>';
}

// -------------------------------------------------------
// Page admin
// -------------------------------------------------------
function wam_config_add_menu_page(): void
{
    add_options_page(
        'Configuration WAM',
        'Configuration WAM',
        'manage_options',
        'wam-config',
        'wam_config_page_html'
    );
}
add_action('admin_menu', 'wam_config_add_menu_page');

function wam_config_page_html(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $opts       = get_option('wam_config', []);
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

    // --- Calcul du statut d'inscription pour le bandeau ---
    $ts_now        = time();
    $ts_ouverture  = (int) ($opts['inscription_ouverture'] ?? 0);
    $ts_fermeture  = (int) ($opts['inscription_fermeture'] ?? 0);
    $inscr_actives = wam_inscriptions_actives();

    $tz_paris = new DateTimeZone('Europe/Paris');
    $bandeau_class = '';
    $bandeau_msg   = '';

    if ($ts_ouverture > 0 || $ts_fermeture > 0) {
        if ($inscr_actives) {
            $bandeau_class = 'notice-success';
            if ($ts_fermeture > $ts_now) {
                $dt = new DateTime('@' . $ts_fermeture, new DateTimeZone('UTC'));
                $dt->setTimezone($tz_paris);
                $bandeau_msg = '✅ Inscriptions <strong>ouvertes</strong>. Fermeture programmée le <strong>' . $dt->format('d/m/Y à H\hi') . '</strong> (heure de Paris).';
            } else {
                $bandeau_msg = '✅ Inscriptions <strong>ouvertes</strong> (programmation active).';
            }
        } else {
            $bandeau_class = 'notice-warning';
            if ($ts_ouverture > $ts_now) {
                $dt = new DateTime('@' . $ts_ouverture, new DateTimeZone('UTC'));
                $dt->setTimezone($tz_paris);
                $bandeau_msg = '⏳ Inscriptions <strong>fermées</strong>. Ouverture programmée le <strong>' . $dt->format('d/m/Y à H\hi') . '</strong> (heure de Paris).';
            } else {
                $bandeau_msg = '🔴 Inscriptions <strong>fermées</strong>.';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php if ($bandeau_msg): ?>
        <div class="notice <?php echo esc_attr($bandeau_class); ?> is-dismissible" style="margin-top:12px;">
            <p><?php echo $bandeau_msg; ?></p>
        </div>
        <?php endif; ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=wam-config&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Général</a>
            <a href="?page=wam-config&tab=smtp" class="nav-tab <?php echo $active_tab === 'smtp' ? 'nav-tab-active' : ''; ?>">Envoi Email (SMTP)</a>
            <a href="?page=wam-config&tab=socials" class="nav-tab <?php echo $active_tab === 'socials' ? 'nav-tab-active' : ''; ?>">Réseaux Sociaux</a>
        </h2>

        <form method="post" action="options.php">
            <?php
            settings_fields('wam_config_group');

            if ($active_tab === 'general') {
                do_settings_sections('wam-config-general');
            } elseif ($active_tab === 'smtp') {
                do_settings_sections('wam-config-smtp');
            } elseif ($active_tab === 'socials') {
                do_settings_sections('wam-config-socials');
            }
            submit_button('Enregistrer');
            ?>
        </form>
    </div>

    <style>
        /* Separateurs visuels entre les sections Settings API */
        .form-table + h2 {
            border-top: 1px solid #ccc;
            padding-top: 20px;
            margin-top: 30px !important;
        }
        /* Amélioration de l'aspect des sections */
        .wrap h2 { margin-bottom: 5px; }
        .form-table { margin-bottom: 20px; }
    </style>

    <script>
    (function () {
        // --- Toggle global : bouton actif / désactivé ---
        var checkboxActif  = document.getElementById('wam-inscriptions-actives');
        var rowTexte       = document.getElementById('wam-row-btn-texte');
        var rowMsg         = document.getElementById('wam-row-message-ferme');
        var checkboxDesact = document.getElementById('wam-btn-cours-desactive');
        var rowDesactTexte = document.getElementById('wam-row-btn-desactive-texte');

        // Récupère le <tr> parent d'un élément
        function getTR(el) { return el ? el.closest('tr') : null; }

        var trDesact      = getTR(checkboxDesact);
        var trDesactTexte = getTR(rowDesactTexte);

        function updateAll() {
            var actif  = checkboxActif.checked;
            var desact = checkboxDesact && checkboxDesact.checked;

            // --- "Texte du bouton" : toujours visible, jamais effacé ---
            // Grisé seulement si "Désactiver" est coché (=le texte normal est remplacé par l'autre)
            rowTexte.style.opacity = desact ? '0.4' : '1';
            rowTexte.querySelector('input').disabled = desact;

            // --- Ligne "Désactiver l'utilisation du bouton" ---
            // Visible uniquement si le bouton global est actif
            if (trDesact) {
                trDesact.style.display = actif ? '' : 'none';
                // Si on décoche "actif", on réinitialise aussi "désactiver"
                if (!actif && checkboxDesact) {
                    checkboxDesact.checked = false;
                }
            }

            // Recalcul après éventuelle réinitialisation
            var desactNow = checkboxDesact && checkboxDesact.checked;

            // --- Ligne "Texte du bouton désactivé" ---
            // Cachée par défaut, visible + required seulement si "Désactiver" est coché ET bouton actif
            var showDesactTexte = actif && desactNow;
            if (trDesactTexte) {
                trDesactTexte.style.display = showDesactTexte ? '' : 'none';
            }
            if (rowDesactTexte) {
                var inp = rowDesactTexte.querySelector('input');
                if (inp) {
                    inp.disabled = !showDesactTexte;
                    inp.required = showDesactTexte; // obligatoire pour la sauvegarde quand visible
                }
            }

            // --- Message fermé : visible uniquement quand bouton inactif ---
            rowMsg.style.opacity = actif ? '0.4' : '1';
            rowMsg.querySelector('textarea').disabled = actif;
        }

        // Initialisation (masque le TR "Texte désactivé" si besoin dès le chargement)
        updateAll();

        checkboxActif.addEventListener('change', updateAll);
        if (checkboxDesact) {
            checkboxDesact.addEventListener('change', updateAll);
        }


        // --- Boutons "Effacer" pour les champs datetime ---
        document.querySelectorAll('.wam-clear-datetime').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = this.getAttribute('data-target');
                var field    = document.getElementById(targetId);
                if (field) { field.value = ''; }
            });
        });

        // --- Logique pour l'adresse ---
        var checkboxAddr = document.getElementById('wam-adresse-visible');
        var rowNomLieu   = document.getElementById('wam-row-nom-lieu');
        var rowAddrLieu  = document.getElementById('wam-row-adresse-lieu');

        if (checkboxAddr && rowNomLieu && rowAddrLieu) {
            function toggleAddr(isChecked) {
                rowNomLieu.style.opacity = isChecked ? '1' : '0.4';
                rowNomLieu.querySelector('input').disabled = !isChecked;
                rowAddrLieu.style.opacity = isChecked ? '1' : '0.4';
                rowAddrLieu.querySelector('textarea').disabled = !isChecked;
            }
            toggleAddr(checkboxAddr.checked);
            checkboxAddr.addEventListener('change', function () { toggleAddr(this.checked); });
        }

        // --- Logique pour la date de rentrée ---
        var checkboxRentree = document.getElementById('wam-show-rentree');
        var rowDateRentree  = document.getElementById('wam-row-date-rentree');
        if (checkboxRentree && rowDateRentree) {
            function toggleRentree(isChecked) {
                rowDateRentree.style.opacity = isChecked ? '1' : '0.4';
                rowDateRentree.querySelector('input').disabled = !isChecked;
            }
            toggleRentree(checkboxRentree.checked);
            checkboxRentree.addEventListener('change', function () { toggleRentree(this.checked); });
        }
    })();
    </script>
    <?php
}

// -------------------------------------------------------
// WP-Cron : planification du vidage de cache
// -------------------------------------------------------

/**
 * Planifie (ou re-planifie) les événements Cron quand les dates changent.
 * Appelé depuis wam_sanitize_config() à chaque sauvegarde.
 */
function wam_schedule_inscription_cron(array $opts): void
{
    // Nettoyage des éventuels anciens événements
    $hook_open  = 'wam_cron_ouvrir_inscriptions';
    $hook_close = 'wam_cron_fermer_inscriptions';

    $ts_next_open  = wp_next_scheduled($hook_open);
    $ts_next_close = wp_next_scheduled($hook_close);
    if ($ts_next_open)  { wp_unschedule_event($ts_next_open,  $hook_open); }
    if ($ts_next_close) { wp_unschedule_event($ts_next_close, $hook_close); }

    $ts_ouverture = (int) ($opts['inscription_ouverture'] ?? 0);
    $ts_fermeture = (int) ($opts['inscription_fermeture'] ?? 0);
    $now          = time();

    // Planifier seulement si la date est dans le futur
    if ($ts_ouverture > $now) {
        wp_schedule_single_event($ts_ouverture, $hook_open);
    }
    if ($ts_fermeture > $now) {
        wp_schedule_single_event($ts_fermeture, $hook_close);
    }
}

/**
 * Callback Cron : vide le cache à l'heure d'ouverture / fermeture.
 * Compatible avec les principaux plugins de cache.
 */
function wam_vider_cache_inscription(): void
{
    // LiteSpeed Cache
    if (class_exists('LiteSpeed_Cache_API')) {
        do_action('litespeed_purge_all');
    }
    // WP Rocket
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    // WP Fastest Cache
    if (function_exists('wpfc_clear_all_cache')) {
        wpfc_clear_all_cache();
    }
    // W3 Total Cache
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    // WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    // Fallback générique : supprime les transients liés aux inscriptions
    delete_transient('wam_inscriptions_cache');
}
add_action('wam_cron_ouvrir_inscriptions',  'wam_vider_cache_inscription');
add_action('wam_cron_fermer_inscriptions',  'wam_vider_cache_inscription');

// -------------------------------------------------------
// Helper functions — utilisables dans tout le thème
// -------------------------------------------------------

/**
 * Les inscriptions sont-elles globalement ouvertes ?
 *
 * Logique de priorité :
 *   1. Si une fenêtre temporelle est programmée ET on est dedans → TRUE
 *   2. Si une fenêtre temporelle est programmée ET on est dehors → FALSE
 *   3. Sinon → valeur du toggle manuel (comportement historique)
 *
 * Fuseau horaire : Europe/Paris (géré via timestamp UTC en base).
 * Cache résistant : pas de transient ici, lecture directe de l'option
 * pour garantir la fraîcheur même si WP-Cron tarde légèrement.
 */
if (!function_exists('wam_inscriptions_actives')):
    function wam_inscriptions_actives(): bool
    {
        $ts_now       = time(); // UTC
        $ts_ouverture = (int) get_option('wam_setting_inscription_ouverture', 0);
        $ts_fermeture = (int) get_option('wam_setting_inscription_fermeture', 0);

        $has_ouverture = $ts_ouverture > 0;
        $has_fermeture = $ts_fermeture > 0;

        // Fenêtre complète : ouverture ET fermeture définies
        if ($has_ouverture && $has_fermeture) {
            return $ts_now >= $ts_ouverture && $ts_now < $ts_fermeture;
        }

        // Ouverture seule : actif dès l'heure d'ouverture, sans fin
        if ($has_ouverture && !$has_fermeture) {
            return $ts_now >= $ts_ouverture;
        }

        // Fermeture seule : actif jusqu'à l'heure de fermeture
        if (!$has_ouverture && $has_fermeture) {
            return $ts_now < $ts_fermeture;
        }

        // Aucune programmation → toggle manuel
        return (bool) get_option('wam_setting_inscriptions_actives', true);
    }
endif;

/**
 * Texte du bouton d'inscription (bouton actif).
 */
if (!function_exists('wam_btn_inscription_texte')):
    function wam_btn_inscription_texte(): string
    {
        return sanitize_text_field(get_option('wam_setting_btn_inscription_texte', 'Ajouter ce cours au panier'));
    }
endif;

/**
 * URL cible du bouton d'inscription — fixe (#inscription).
 */
if (!function_exists('wam_btn_inscription_url')):
    function wam_btn_inscription_url(): string
    {
        return '#inscription';
    }
endif;

/**
 * Le bouton d'inscription est-il en mode "désactivé" ?
 */
if (!function_exists('wam_btn_cours_est_desactive')):
    function wam_btn_cours_est_desactive(): bool
    {
        return (bool) get_option('wam_setting_btn_cours_desactive', false);
    }
endif;

/**
 * Texte du bouton quand il est en mode désactivé.
 */
if (!function_exists('wam_btn_cours_desactive_texte')):
    function wam_btn_cours_desactive_texte(): string
    {
        return sanitize_text_field(get_option('wam_setting_btn_cours_desactive_texte', 'Inscriptions bientôt disponibles'));
    }
endif;

/**
 * Message affiché quand les inscriptions sont désactivées globalement.
 */
if (!function_exists('wam_message_inscriptions_fermees')):
    function wam_message_inscriptions_fermees(): string
    {
        return esc_html(get_option('wam_setting_message_inscriptions_fermees', 'Les inscriptions sont actuellement fermées.'));
    }
endif;

/**
 * L'adresse globale est-elle visible ?
 */
if (!function_exists('wam_adresse_visible')):
    function wam_adresse_visible(): bool
    {
        return (bool) get_option('wam_setting_adresse_visible', true);
    }
endif;

/**
 * Nom global du lieu (WAM Dance Studio)
 */
if (!function_exists('wam_nom_lieu')):
    function wam_nom_lieu(): string
    {
        return sanitize_text_field(get_option('wam_setting_nom_lieu', 'WAM Dance Studio'));
    }
endif;

/**
 * Adresse globale
 */
if (!function_exists('wam_adresse_lieu')):
    function wam_adresse_lieu(): string
    {
        return esc_html(get_option('wam_setting_adresse_lieu', "202 rue Jean Jaurès\nVilleneuve d'Ascq"));
    }
endif;

/**
 * La date de rentrée doit-elle être affichée ?
 */
if (!function_exists('wam_show_rentree')):
    function wam_show_rentree(): bool
    {
        return (bool) get_option('wam_setting_show_rentree', false);
    }
endif;

/**
 * Valeur de la date de rentrée.
 */
if (!function_exists('wam_date_rentree')):
    function wam_date_rentree(): string
    {
        return sanitize_text_field(get_option('wam_setting_date_rentree', ''));
    }
endif;

// --- Réseaux Sociaux Helpers ---

if (!function_exists('wam_url_instagram')):
    function wam_url_instagram(): string
    {
        return esc_url(get_option('wam_setting_url_instagram', ''));
    }
endif;

if (!function_exists('wam_url_facebook')):
    function wam_url_facebook(): string
    {
        return esc_url(get_option('wam_setting_url_facebook', ''));
    }
endif;

if (!function_exists('wam_url_tiktok')):
    function wam_url_tiktok(): string
    {
        return esc_url(get_option('wam_setting_url_tiktok', ''));
    }
endif;

if (!function_exists('wam_url_linkedin')):
    function wam_url_linkedin(): string
    {
        return esc_url(get_option('wam_setting_url_linkedin', ''));
    }
endif;

if (!function_exists('wam_url_youtube')):
    function wam_url_youtube(): string
    {
        return esc_url(get_option('wam_setting_url_youtube', ''));
    }
endif;

