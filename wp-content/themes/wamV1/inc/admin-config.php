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
function wam_config_register_settings(): void
{
    register_setting('wam_config_group', 'wam_config', [
        'sanitize_callback' => 'wam_sanitize_config',
    ]);

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
// Sanitize callback
// -------------------------------------------------------
function wam_sanitize_config(array $input): array
{
    // Conversion des dates "datetime-local" (format HTML : Y-m-dTH:i) en timestamps UTC
    // Les dates saisies par l'utilisateur sont en heure locale Paris (Europe/Paris)
    $tz_paris = new DateTimeZone('Europe/Paris');
    $tz_utc   = new DateTimeZone('UTC');

    $ts_ouverture = 0;
    if (!empty($input['inscription_ouverture'])) {
        try {
            $dt = new DateTime($input['inscription_ouverture'], $tz_paris);
            $dt->setTimezone($tz_utc);
            $ts_ouverture = $dt->getTimestamp();
        } catch (Exception $e) {
            $ts_ouverture = 0;
        }
    }

    $ts_fermeture = 0;
    if (!empty($input['inscription_fermeture'])) {
        try {
            $dt = new DateTime($input['inscription_fermeture'], $tz_paris);
            $dt->setTimezone($tz_utc);
            $ts_fermeture = $dt->getTimestamp();
        } catch (Exception $e) {
            $ts_fermeture = 0;
        }
    }

    $sanitized = [
        'inscriptions_actives'         => (bool) isset($input['inscriptions_actives']),
        'btn_inscription_texte'        => sanitize_text_field($input['btn_inscription_texte'] ?? ''),
        'btn_cours_desactive'          => (bool) isset($input['btn_cours_desactive']),
        'btn_cours_desactive_texte'    => sanitize_text_field($input['btn_cours_desactive_texte'] ?? ''),
        'message_inscriptions_fermees' => sanitize_textarea_field($input['message_inscriptions_fermees'] ?? ''),
        'inscription_ouverture'        => $ts_ouverture,
        'inscription_fermeture'        => $ts_fermeture,
        'adresse_visible'              => (bool) isset($input['adresse_visible']),
        'nom_lieu'                     => sanitize_text_field($input['nom_lieu'] ?? ''),
        'adresse_lieu'                 => sanitize_textarea_field($input['adresse_lieu'] ?? ''),
        'show_rentree'                 => (bool) isset($input['show_rentree']),
        'date_rentree'                 => sanitize_text_field($input['date_rentree'] ?? ''),
        'url_instagram'                => esc_url_raw($input['url_instagram'] ?? ''),
        'url_facebook'                 => esc_url_raw($input['url_facebook'] ?? ''),
        'url_tiktok'                   => esc_url_raw($input['url_tiktok'] ?? ''),
        'url_linkedin'                 => esc_url_raw($input['url_linkedin'] ?? ''),
        'url_youtube'                  => esc_url_raw($input['url_youtube'] ?? ''),
        'smtp_host'                    => sanitize_text_field($input['smtp_host'] ?? ''),
        'smtp_port'                    => absint($input['smtp_port'] ?? 465),
        'smtp_user'                    => sanitize_text_field($input['smtp_user'] ?? ''),
        'smtp_pass'                    => sanitize_text_field($input['smtp_pass'] ?? ''),
        'smtp_secure'                  => sanitize_text_field($input['smtp_secure'] ?? 'ssl'),
        'smtp_from_email'              => sanitize_email($input['smtp_from_email'] ?? ''),
        'smtp_from_name'               => sanitize_text_field($input['smtp_from_name'] ?? 'WAM Dance Studio'),
        'smtp_to_emails'               => sanitize_text_field($input['smtp_to_emails'] ?? ''),
    ];

    // Planification des événements WP-Cron si des dates sont définies
    wam_schedule_inscription_cron($sanitized);

    return $sanitized;
}

// -------------------------------------------------------
// Callbacks des champs
// -------------------------------------------------------
function wam_field_url_instagram(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['url_instagram'] ?? 'https://www.instagram.com/wam_dance_studio/');
    echo '<input type="url" name="wam_config[url_instagram]" value="' . $val . '" class="regular-text">';
}
function wam_field_url_facebook(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['url_facebook'] ?? 'https://www.facebook.com/WAMDanceStudio/');
    echo '<input type="url" name="wam_config[url_facebook]" value="' . $val . '" class="regular-text">';
}
function wam_field_url_tiktok(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['url_tiktok'] ?? 'https://www.tiktok.com/@wamdancestudio');
    echo '<input type="url" name="wam_config[url_tiktok]" value="' . $val . '" class="regular-text">';
}
function wam_field_url_linkedin(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['url_linkedin'] ?? 'https://www.linkedin.com/company/wam-dance-studio');
    echo '<input type="url" name="wam_config[url_linkedin]" value="' . $val . '" class="regular-text">';
}
function wam_field_url_youtube(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['url_youtube'] ?? '');
    echo '<input type="url" name="wam_config[url_youtube]" value="' . $val . '" class="regular-text">';
}

// --- Callbacks SMTP ---
function wam_section_smtp_desc(): void {
    echo '<p>Configurez ici les accès à votre serveur d\'envoi email (SMTP) pour garantir la bonne réception de vos messages de contact et notifications. Laissez vide si vous souhaitez utiliser le système par défaut de votre serveur d\'hébergement (déconseillé).</p>';
}
function wam_field_smtp_host(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_host'] ?? '');
    echo '<input type="text" name="wam_config[smtp_host]" value="' . $val . '" class="regular-text" placeholder="ex: smtp.gmail.com">';
}
function wam_field_smtp_port(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_port'] ?? '465');
    echo '<input type="number" name="wam_config[smtp_port]" value="' . $val . '" class="small-text">';
}
function wam_field_smtp_user(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_user'] ?? '');
    echo '<input type="text" name="wam_config[smtp_user]" value="' . $val . '" class="regular-text">';
}
function wam_field_smtp_pass(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_pass'] ?? '');
    echo '<input type="password" name="wam_config[smtp_pass]" value="' . $val . '" class="regular-text" placeholder="Votre mot de passe">';
    echo '<p class="description">Le mot de passe sera enregistré en toute sécurité dans la base de données.</p>';
}
function wam_field_smtp_secure(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_secure'] ?? 'ssl');
    echo '<select name="wam_config[smtp_secure]">
        <option value="ssl" ' . selected($val, 'ssl', false) . '>SSL (recommandé port 465)</option>
        <option value="tls" ' . selected($val, 'tls', false) . '>TLS (recommandé port 587)</option>
        <option value="none" ' . selected($val, 'none', false) . '>Aucune (non recommandé)</option>
    </select>';
}
function wam_field_smtp_from_email(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_from_email'] ?? get_option('admin_email'));
    echo '<input type="email" name="wam_config[smtp_from_email]" value="' . $val . '" class="regular-text">';
}
function wam_field_smtp_from_name(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_from_name'] ?? 'WAM Dance Studio');
    echo '<input type="text" name="wam_config[smtp_from_name]" value="' . $val . '" class="regular-text">';
}
function wam_field_smtp_to_emails(): void {
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['smtp_to_emails'] ?? '');
    echo '<input type="text" name="wam_config[smtp_to_emails]" value="' . $val . '" class="large-text" placeholder="ex: contact@wamdancestudio.fr, direction@wamdancestudio.fr">';
    echo '<p class="description">Séparez les adresses e-mail par une virgule. Si ce champ est vide, l\'e-mail de l\'administrateur du site ('. get_option('admin_email') .') sera utilisé.</p>';
}

function wam_field_inscriptions_actives(): void
{
    $opts    = get_option('wam_config', []);
    $checked = (bool) ($opts['inscriptions_actives'] ?? true);
    ?>
    <label>
        <input type="checkbox"
               id="wam-inscriptions-actives"
               name="wam_config[inscriptions_actives]"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher le bouton d'inscription sur les fiches de cours
    </label>
    <p class="description">Quand décoché, le bouton est masqué et un message de remplacement est affiché ci-dessous.</p>
    <?php
}

function wam_field_btn_texte(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['btn_inscription_texte'] ?? 'Ajouter ce cours au panier');
    echo '<span id="wam-row-btn-texte">';
    echo '<input type="text" name="wam_config[btn_inscription_texte]" value="' . $val . '" class="regular-text">';
    echo '<p class="description">Texte affiché sur le bouton quand les inscriptions sont actives.</p>';
    echo '</span>';
}

/**
 * Champ : "Désactiver l'utilisation du bouton"
 * Rend le bouton visible mais non cliquable (aria-disabled), avec un texte spécifique.
 */
function wam_field_btn_cours_desactive(): void
{
    $opts    = get_option('wam_config', []);
    $checked = (bool) ($opts['btn_cours_desactive'] ?? false);
    ?>
    <label>
        <input type="checkbox"
               id="wam-btn-cours-desactive"
               name="wam_config[btn_cours_desactive]"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher le bouton en mode <strong>désactivé</strong> (visible mais non cliquable)
    </label>
    <p class="description">Utile pour signaler que les inscriptions arrivent bientôt, sans cacheter le bouton. Nécessite que le bouton soit activé ci-dessus.</p>
    <?php
}

function wam_field_btn_cours_desactive_texte(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['btn_cours_desactive_texte'] ?? 'Inscriptions bientôt disponibles');
    echo '<span id="wam-row-btn-desactive-texte">';
    echo '<input type="text" name="wam_config[btn_cours_desactive_texte]" value="' . $val . '" class="regular-text">';
    echo '<p class="description">Texte affiché sur le bouton désactivé (grisé, non cliquable).</p>';
    echo '</span>';
}

function wam_field_message_ferme(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_textarea($opts['message_inscriptions_fermees'] ?? 'Les inscriptions sont actuellement fermées.');
    echo '<span id="wam-row-message-ferme">';
    echo '<textarea name="wam_config[message_inscriptions_fermees]" rows="3" class="large-text">' . $val . '</textarea>';
    echo '<p class="description">Affiché à la place du bouton quand les inscriptions sont désactivées (bouton masqué).</p>';
    echo '</span>';
}

/**
 * Champ : date/heure d'ouverture programmée.
 * Affiché en heure Paris, stocké en UTC.
 */
function wam_field_inscription_ouverture(): void
{
    $opts = get_option('wam_config', []);
    $ts   = (int) ($opts['inscription_ouverture'] ?? 0);

    // Reconversion UTC → Paris pour l'affichage dans l'input
    $val = '';
    if ($ts > 0) {
        $dt  = new DateTime('@' . $ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Paris'));
        $val = $dt->format('Y-m-d\TH:i'); // format attendu par datetime-local
    }

    echo '<span id="wam-row-inscription-ouverture">';
    echo '<input type="datetime-local" id="wam-inscription-ouverture" name="wam_config[inscription_ouverture]" value="' . esc_attr($val) . '" class="regular-text">';
    echo '<button type="button" class="button button-small wam-clear-datetime" data-target="wam-inscription-ouverture" style="margin-left:8px;">Effacer</button>';
    echo '<p class="description">Heure de Paris (Europe/Paris). Si définie, les inscriptions s\'ouvriront automatiquement à cette date/heure, <strong>quelle que soit la case à cocher ci-dessus</strong>.</p>';
    echo '</span>';
}

/**
 * Champ : date/heure de fermeture programmée.
 * Affiché en heure Paris, stocké en UTC.
 */
function wam_field_inscription_fermeture(): void
{
    $opts = get_option('wam_config', []);
    $ts   = (int) ($opts['inscription_fermeture'] ?? 0);

    $val = '';
    if ($ts > 0) {
        $dt  = new DateTime('@' . $ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Paris'));
        $val = $dt->format('Y-m-d\TH:i');
    }

    echo '<span id="wam-row-inscription-fermeture">';
    echo '<input type="datetime-local" id="wam-inscription-fermeture" name="wam_config[inscription_fermeture]" value="' . esc_attr($val) . '" class="regular-text">';
    echo '<button type="button" class="button button-small wam-clear-datetime" data-target="wam-inscription-fermeture" style="margin-left:8px;">Effacer</button>';
    echo '<p class="description">Heure de Paris (Europe/Paris). Si définie, les inscriptions se fermeront automatiquement à cette date/heure.</p>';
    echo '</span>';
}

function wam_field_show_rentree(): void
{
    $opts    = get_option('wam_config', []);
    $checked = (bool) ($opts['show_rentree'] ?? false);
    ?>
    <label>
        <input type="checkbox"
               id="wam-show-rentree"
               name="wam_config[show_rentree]"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher la date de rentrée sur toutes les fiches de cours.
    </label>
    <?php
}

function wam_field_date_rentree(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['date_rentree'] ?? '19/04/2025');
    echo '<span id="wam-row-date-rentree">';
    echo '<input type="text" name="wam_config[date_rentree]" value="' . $val . '" class="regular-text" placeholder="ex: 19/04/2025">';
    echo '<p class="description">Le texte qui sera affiché après "Date de rentrée : ".</p>';
    echo '</span>';
}

function wam_field_adresse_visible(): void
{
    $opts    = get_option('wam_config', []);
    $checked = (bool) ($opts['adresse_visible'] ?? true);
    ?>
    <label>
        <input type="checkbox"
               id="wam-adresse-visible"
               name="wam_config[adresse_visible]"
               value="1"
               <?php checked($checked); ?>>
        Cocher pour afficher le bloc adresse publiquement sur le site (pages de cours, événements...).
    </label>
    <?php
}

function wam_field_nom_lieu(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['nom_lieu'] ?? 'WAM Dance Studio');
    echo '<span id="wam-row-nom-lieu">';
    echo '<input type="text" name="wam_config[nom_lieu]" value="' . $val . '" class="regular-text">';
    echo '</span>';
}

function wam_field_adresse_lieu(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_textarea($opts['adresse_lieu'] ?? "202 rue Jean Jaurès\nVilleneuve d'Ascq");
    echo '<span id="wam-row-adresse-lieu">';
    echo '<textarea name="wam_config[adresse_lieu]" rows="3" class="large-text">' . $val . '</textarea>';
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
        $opts         = get_option('wam_config', []);
        $ts_now       = time(); // UTC
        $ts_ouverture = (int) ($opts['inscription_ouverture'] ?? 0);
        $ts_fermeture = (int) ($opts['inscription_fermeture'] ?? 0);

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
        return (bool) ($opts['inscriptions_actives'] ?? true);
    }
endif;

/**
 * Texte du bouton d'inscription (bouton actif).
 * Défaut : "Inscription 2024/25".
 */
if (!function_exists('wam_btn_inscription_texte')):
    function wam_btn_inscription_texte(): string
    {
        $opts = get_option('wam_config', []);
        return sanitize_text_field($opts['btn_inscription_texte'] ?? 'Ajouter ce cours au panier');
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
 * Si true : bouton visible mais non cliquable (aria-disabled, style grisé).
 */
if (!function_exists('wam_btn_cours_est_desactive')):
    function wam_btn_cours_est_desactive(): bool
    {
        $opts = get_option('wam_config', []);
        return (bool) ($opts['btn_cours_desactive'] ?? false);
    }
endif;

/**
 * Texte du bouton quand il est en mode désactivé.
 * Défaut : "Inscriptions bientôt disponibles".
 */
if (!function_exists('wam_btn_cours_desactive_texte')):
    function wam_btn_cours_desactive_texte(): string
    {
        $opts = get_option('wam_config', []);
        return sanitize_text_field($opts['btn_cours_desactive_texte'] ?? 'Inscriptions bientôt disponibles');
    }
endif;

/**
 * Message affiché quand les inscriptions sont désactivées globalement.
 * Défaut : "Les inscriptions sont actuellement fermées."
 */
if (!function_exists('wam_message_inscriptions_fermees')):
    function wam_message_inscriptions_fermees(): string
    {
        $opts = get_option('wam_config', []);
        return esc_html($opts['message_inscriptions_fermees'] ?? 'Les inscriptions sont actuellement fermées.');
    }
endif;

/**
 * L'adresse globale est-elle visible ?
 */
if (!function_exists('wam_adresse_visible')):
    function wam_adresse_visible(): bool
    {
        $opts = get_option('wam_config', []);
        return (bool) ($opts['adresse_visible'] ?? true);
    }
endif;

/**
 * Nom global du lieu (WAM Dance Studio)
 */
if (!function_exists('wam_nom_lieu')):
    function wam_nom_lieu(): string
    {
        $opts = get_option('wam_config', []);
        return sanitize_text_field($opts['nom_lieu'] ?? 'WAM Dance Studio');
    }
endif;

/**
 * Adresse globale
 */
if (!function_exists('wam_adresse_lieu')):
    function wam_adresse_lieu(): string
    {
        $opts = get_option('wam_config', []);
        return esc_html($opts['adresse_lieu'] ?? "202 rue Jean Jaurès\nVilleneuve d'Ascq");
    }
endif;

/**
 * La date de rentrée doit-elle être affichée ?
 */
if (!function_exists('wam_show_rentree')):
    function wam_show_rentree(): bool
    {
        $opts = get_option('wam_config', []);
        return (bool) ($opts['show_rentree'] ?? false);
    }
endif;

/**
 * Valeur de la date de rentrée.
 */
if (!function_exists('wam_date_rentree')):
    function wam_date_rentree(): string
    {
        $opts = get_option('wam_config', []);
        return sanitize_text_field($opts['date_rentree'] ?? '19/04/2025');
    }
endif;

// --- Réseaux Sociaux Helpers ---

if (!function_exists('wam_url_instagram')):
    function wam_url_instagram(): string
    {
        $opts = get_option('wam_config', []);
        return esc_url($opts['url_instagram'] ?? '');
    }
endif;

if (!function_exists('wam_url_facebook')):
    function wam_url_facebook(): string
    {
        $opts = get_option('wam_config', []);
        return esc_url($opts['url_facebook'] ?? '');
    }
endif;

if (!function_exists('wam_url_tiktok')):
    function wam_url_tiktok(): string
    {
        $opts = get_option('wam_config', []);
        return esc_url($opts['url_tiktok'] ?? '');
    }
endif;

if (!function_exists('wam_url_linkedin')):
    function wam_url_linkedin(): string
    {
        $opts = get_option('wam_config', []);
        return esc_url($opts['url_linkedin'] ?? '');
    }
endif;

if (!function_exists('wam_url_youtube')):
    function wam_url_youtube(): string
    {
        $opts = get_option('wam_config', []);
        return esc_url($opts['url_youtube'] ?? '');
    }
endif;
