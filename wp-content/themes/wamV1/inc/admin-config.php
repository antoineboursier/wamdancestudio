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
        'wam-config'
    );

    add_settings_field(
        'inscriptions_actives',
        'Inscriptions ouvertes',
        'wam_field_inscriptions_actives',
        'wam-config',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'btn_inscription_texte',
        'Texte du bouton',
        'wam_field_btn_texte',
        'wam-config',
        'wam_section_inscriptions'
    );

    add_settings_field(
        'message_inscriptions_fermees',
        'Message si fermées',
        'wam_field_message_ferme',
        'wam-config',
        'wam_section_inscriptions'
    );

    add_settings_section(
        'wam_section_lieux',
        'Localisation & Lieux',
        '', // pas de callback
        'wam-config'
    );

    add_settings_field(
        'adresse_visible',
        'Afficher l\'adresse sur le site',
        'wam_field_adresse_visible',
        'wam-config',
        'wam_section_lieux'
    );

    add_settings_field(
        'nom_lieu',
        'Nom du lieu',
        'wam_field_nom_lieu',
        'wam-config',
        'wam_section_lieux'
    );

    add_settings_field(
        'adresse_lieu',
        'Adresse complète',
        'wam_field_adresse_lieu',
        'wam-config',
        'wam_section_lieux'
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
    ];
}

// -------------------------------------------------------
// Callbacks des champs
// -------------------------------------------------------
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
    $opts    = get_option('wam_config', []);
    $checked = (bool) ($opts['inscriptions_actives'] ?? true);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wam_config_group');
            do_settings_sections('wam-config');
            submit_button('Enregistrer');
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
