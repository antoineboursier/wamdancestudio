<?php
/**
 * Page d'administration : Configuration WAM
 *
 * Expose un groupe d'options `wam_config` via le Settings API WordPress.
 * Accessible sous Réglages > Configuration WAM (capacité manage_options).
 *
 * Options stockées :
 *   inscriptions_actives         (bool)   — interrupteur global des inscriptions
 *   btn_inscription_texte        (string) — libellé du bouton d'inscription
 *   message_inscriptions_fermees (string) — message affiché si inscriptions fermées
 *
 * Helper functions (utilisables partout dans le thème) :
 *   wam_inscriptions_actives()
 *   wam_btn_inscription_texte()
 *   wam_btn_inscription_url()        — retourne toujours "#inscription" (URL fixe)
 *   wam_message_inscriptions_fermees()
 *   wam_adresse_visible()            — boolean, true par défaut
 *   wam_nom_lieu()                   — string, nom du lieu "WAM Dance Studio"
 *   wam_adresse_lieu()               — string, adresse avec retours éventuels
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
        'Inscriptions ouvertes',
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
        'message_inscriptions_fermees',
        'Message si fermées',
        'wam_field_message_ferme',
        'wam-config-general',
        'wam_section_inscriptions'
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

    add_settings_section(
        'wam_section_sync',
        'Synchronisation des données (CSV)',
        'wam_section_sync_desc',
        'wam-config-sync'
    );

    add_settings_field(
        'sync_profs',
        'Professeurs',
        'wam_field_sync_profs',
        'wam-config-sync',
        'wam_section_sync'
    );

    add_settings_field(
        'sync_cours',
        'Cours',
        'wam_field_sync_cours',
        'wam-config-sync',
        'wam_section_sync'
    );
}
add_action('admin_init', 'wam_config_register_settings');

// -------------------------------------------------------
// Sanitize callback
// -------------------------------------------------------
function wam_sanitize_config(array $input): array
{
    return [
        'inscriptions_actives'         => (bool) isset($input['inscriptions_actives']),
        'btn_inscription_texte'        => sanitize_text_field($input['btn_inscription_texte'] ?? ''),
        'message_inscriptions_fermees' => sanitize_textarea_field($input['message_inscriptions_fermees'] ?? ''),
        'adresse_visible'              => (bool) isset($input['adresse_visible']),
        'nom_lieu'                     => sanitize_text_field($input['nom_lieu'] ?? ''),
        'adresse_lieu'                 => sanitize_textarea_field($input['adresse_lieu'] ?? ''),
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
        Cocher pour activer les inscriptions sur tous les cours
    </label>
    <?php
}

function wam_field_btn_texte(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_attr($opts['btn_inscription_texte'] ?? 'Inscription 2024/25');
    // Grisé quand les inscriptions sont fermées (JS ci-dessous)
    echo '<span id="wam-row-btn-texte">';
    echo '<input type="text" name="wam_config[btn_inscription_texte]" value="' . $val . '" class="regular-text">';
    echo '</span>';
}

function wam_field_message_ferme(): void
{
    $opts = get_option('wam_config', []);
    $val  = esc_textarea($opts['message_inscriptions_fermees'] ?? 'Les inscriptions sont actuellement fermées.');
    // Grisé quand les inscriptions sont ouvertes (JS ci-dessous)
    echo '<span id="wam-row-message-ferme">';
    echo '<textarea name="wam_config[message_inscriptions_fermees]" rows="3" class="large-text">' . $val . '</textarea>';
    echo '<p class="description">Affiché à la place du bouton quand les inscriptions sont désactivées.</p>';
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

// --- Callbacks Synchronisation ---
function wam_section_sync_desc(): void
{
    echo '<p>Utilisez ces boutons pour mettre à jour les données du site à partir des fichiers CSV présents dans le thème (dossier <code>data/</code>).</p>';
}

function wam_field_sync_profs(): void
{
    $date = function_exists('wamv1_get_csv_mtime') ? wamv1_get_csv_mtime('profs_wam_import.csv') : 'Inconnue';
    echo '<p>Dernier changement du fichier : <strong>' . $date . '</strong></p>';
    echo '<button type="submit" name="wam_sync_action" value="sync_profs" class="button button-secondary">Synchroniser les professeurs</button>';
}

function wam_field_sync_cours(): void
{
    $date = function_exists('wamv1_get_csv_mtime') ? wamv1_get_csv_mtime('cours_wam_import.csv') : 'Inconnue';
    echo '<p>Dernier changement du fichier : <strong>' . $date . '</strong></p>';
    echo '<div style="display:flex; gap:10px;">';
    echo '<button type="submit" name="wam_sync_action" value="sync_cours" class="button button-secondary">Synchroniser les cours</button>';
    echo '<button type="submit" name="wam_sync_action" value="purge_cours" class="button button-link-delete js-confirm-purge" style="color:#d63638; text-decoration:none; align-self:center;">Vider tous les cours</button>';
    echo '</div>';
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

    // Gestion du déclenchement manuel de la synchronisation
    $sync_message = '';
    if (isset($_POST['wam_sync_action']) && check_admin_referer('wam_config_group-options')) {
        $action = $_POST['wam_sync_action'];
        if ($action === 'sync_profs' && function_exists('wamv1_import_profs_logic')) {
            $sync_message = wamv1_import_profs_logic();
        } elseif ($action === 'sync_cours' && function_exists('wamv1_import_cours_logic')) {
            $sync_message = wamv1_import_cours_logic();
        } elseif ($action === 'purge_cours' && function_exists('wamv1_purge_cours_logic')) {
            $sync_message = wamv1_purge_cours_logic();
        }
    }

    $opts    = get_option('wam_config', []);
    $checked = (bool) ($opts['inscriptions_actives'] ?? true);
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=wam-config&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Général</a>
            <a href="?page=wam-config&tab=smtp" class="nav-tab <?php echo $active_tab === 'smtp' ? 'nav-tab-active' : ''; ?>">Envoi Email (SMTP)</a>
            <a href="?page=wam-config&tab=socials" class="nav-tab <?php echo $active_tab === 'socials' ? 'nav-tab-active' : ''; ?>">Réseaux Sociaux</a>
            <a href="?page=wam-config&tab=sync" class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">Synchronisation</a>
        </h2>

        <?php if ($sync_message): ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Synchronisation réussie :</strong> <?php echo esc_html($sync_message); ?></p>
            </div>
        <?php endif; ?>

        <?php
        // Si on est sur l'onglet sync, le formulaire doit poster sur lui-même pour intercepter l'action.
        // Sinon, il poste vers options.php pour enregistrer les paramètres via l'API Settings WP.
        $form_action = ($active_tab === 'sync') ? '' : 'options.php';
        ?>
        <form method="post" action="<?php echo esc_attr($form_action); ?>">
            <?php
            settings_fields('wam_config_group');
            
            if ($active_tab === 'general') {
                do_settings_sections('wam-config-general');
            } elseif ($active_tab === 'smtp') {
                do_settings_sections('wam-config-smtp');
            } elseif ($active_tab === 'socials') {
                do_settings_sections('wam-config-socials');
            } elseif ($active_tab === 'sync') {
                do_settings_sections('wam-config-sync');
            }
            // En mode sync, le bouton enregistrer n'est pas nécessaire (il ne sauvegarde rien).
            if ($active_tab !== 'sync') {
                submit_button('Enregistrer');
            }
            ?>
        </form>
    </div>

    <script>
    (function () {
        var checkbox  = document.getElementById('wam-inscriptions-actives');
        var rowTexte  = document.getElementById('wam-row-btn-texte');
        var rowMsg    = document.getElementById('wam-row-message-ferme');

        function toggle(isChecked) {
            // Inscriptions ouvertes → texte du bouton actif, message grisé
            rowTexte.style.opacity  = isChecked ? '1'   : '0.4';
            rowTexte.querySelector('input').disabled = !isChecked;
            rowMsg.style.opacity    = isChecked ? '0.4' : '1';
            rowMsg.querySelector('textarea').disabled = isChecked;
        }

        toggle(checkbox.checked);
        checkbox.addEventListener('change', function () { toggle(this.checked); });
        
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

        // Confirmation de purge
        var purgeBtn = document.querySelector('.js-confirm-purge');
        if (purgeBtn) {
            purgeBtn.addEventListener('click', function (e) {
                if (!confirm('ATTENTION : Voulez-vous vraiment supprimer TOUS les cours de la base de données ?\n\nCette action est irréversible.')) {
                    e.preventDefault();
                }
            });
        }
    })();
    </script>
    <?php
}

// -------------------------------------------------------
// Helper functions — utilisables dans tout le thème
// -------------------------------------------------------

/**
 * Les inscriptions sont-elles globalement ouvertes ?
 * Défaut : true (pas de régression si l'option n'a jamais été sauvegardée).
 */
if (!function_exists('wam_inscriptions_actives')):
    function wam_inscriptions_actives(): bool
    {
        $opts = get_option('wam_config', []);
        return (bool) ($opts['inscriptions_actives'] ?? true);
    }
endif;

/**
 * Texte du bouton d'inscription.
 * Défaut : "Inscription 2024/25".
 */
if (!function_exists('wam_btn_inscription_texte')):
    function wam_btn_inscription_texte(): string
    {
        $opts = get_option('wam_config', []);
        return sanitize_text_field($opts['btn_inscription_texte'] ?? 'Inscription 2024/25');
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
