<?php
/**
 * Template Part : Module Accessibilité
 * HTML du panel et du bouton flottant déclencheur
 *
 * Paramètres via $args :
 *   'inline' (bool) — true sur la page /accessibilite/ :
 *       - pas de bouton flottant ni de backdrop
 *       - panel visible sans [hidden], role="region"
 *
 * @package wamv1
 */

$icon_dir  = get_template_directory_uri() . '/assets/images/';
$is_inline = $args['inline'] ?? false;
?>

<?php if ( ! $is_inline ) : ?>
<?php /* =====================================================
BOUTON FLOTTANT — toujours visible sur le site (mode normal)
===================================================== */ ?>
<div class="wam-a11y-trigger-wrap" id="wam-a11y-trigger-wrap">
    <?php
    // Bouton de modification rapide si l'utilisateur peut éditer la page
    if (is_user_logged_in() && $edit_link = get_edit_post_link()): ?>
        <a href="<?php echo esc_url($edit_link); ?>" class="wam-edit-trigger"
            aria-label="<?php esc_attr_e('Modifier cette page', 'wamv1'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="wam-a11y-trigger__label"><?php esc_html_e('Modifier', 'wamv1'); ?></span>
        </a>
    <?php endif; ?>

    <button class="wam-a11y-trigger" id="wam-a11y-trigger" aria-controls="wam-a11y-panel" aria-expanded="false"
        aria-label="<?php esc_attr_e("Ouvrir les options d'accessibilité", 'wamv1'); ?>" type="button">
        <?php /* Icône SVG accessibilité — outline personne */ ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
            <circle cx="12" cy="5" r="2.5" stroke="currentColor" stroke-width="1.8" />
            <path d="M5 10.5h14M12 10.5v9m-4-5 1.5 5M16 14.5l-1.5 5" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="wam-a11y-trigger__label">
            <?php esc_html_e('Accessibilité', 'wamv1'); ?>
        </span>
    </button>
</div>
<?php endif; // ! $is_inline — fin du bouton flottant ?>

<?php /* =====================================================
PANEL — dialog flottant (normal) ou widget inline (/accessibilite/)
===================================================== */ ?>
<section class="wam-a11y-panel<?php echo $is_inline ? ' wam-a11y-panel--inline' : ''; ?>"
    id="wam-a11y-panel"
    <?php echo $is_inline ? 'role="region"' : 'role="dialog" aria-modal="false"'; ?>
    aria-label="<?php esc_attr_e("Options d'accessibilité", 'wamv1'); ?>"
    <?php echo $is_inline ? '' : 'hidden'; ?>>

    <?php /* En-tête du panel */ ?>
    <div class="wam-a11y-panel__header">
        <p class="wam-a11y-panel__title<?php echo $is_inline ? ' is-style-title-norm-md' : ''; ?>">
            <?php echo $is_inline
                ? esc_html__('Personnalisez votre expérience sur notre site', 'wamv1')
                : esc_html__('Personnalisation', 'wamv1'); ?>
        </p>
        <?php if ( ! $is_inline ) : ?>
        <button class="wam-a11y-panel__close" id="wam-a11y-close"
            aria-label="<?php esc_attr_e('Fermer le panel accessibilité', 'wamv1'); ?>" type="button">
            <img src="<?php echo esc_url($icon_dir . 'close.svg'); ?>" alt="" width="16" height="16">
        </button>
        <?php endif; ?>
    </div>

    <div class="wam-a11y-panel__body">

        <?php /* ----- 1. THÈME ------------------------------------------ */ ?>
        <fieldset class="a11y-group">
            <legend class="a11y-group__legend">
                <?php esc_html_e('Thème', 'wamv1'); ?>
            </legend>
            <div class="a11y-toggles">
                <label class="a11y-toggle-btn" id="lbl-theme-dark">
                    <input type="radio" name="wam-theme" value="dark" checked aria-describedby="desc-theme">
                    <span class="a11y-toggle-btn__swatch a11y-toggle-btn__swatch--dark"></span>
                    <?php esc_html_e('Sombre', 'wamv1'); ?>
                </label>
                <label class="a11y-toggle-btn" id="lbl-theme-light">
                    <input type="radio" name="wam-theme" value="light" aria-describedby="desc-theme">
                    <span class="a11y-toggle-btn__swatch a11y-toggle-btn__swatch--light"></span>
                    <?php esc_html_e('Clair', 'wamv1'); ?>
                </label>
            </div>
            <p class="a11y-group__desc" id="desc-theme">
                <?php esc_html_e('Choisissez le contraste qui vous convient.', 'wamv1'); ?>
            </p>
        </fieldset>

        <?php /* ----- 2. POLICES GRAPHIQUES ------------------------------ */ ?>
        <fieldset class="a11y-group">
            <legend class="a11y-group__legend">
                <?php esc_html_e('Polices graphiques', 'wamv1'); ?>
            </legend>
            <label class="a11y-switch-row" for="a11y-no-graphical-fonts">
                <span class="a11y-switch">
                    <input type="checkbox" id="a11y-no-graphical-fonts" name="wam-no-graphical-fonts" value="1">
                    <span class="a11y-switch__track"><span class="a11y-switch__thumb"></span></span>
                </span>
                <span class="a11y-switch-row__label">
                    <?php esc_html_e('Ne pas utiliser de polices graphiques', 'wamv1'); ?>
                </span>
            </label>
            <p class="a11y-group__desc">
                <?php esc_html_e('Remplace Mallia et Cholo Rhita par Outfit (lecture facilitée).', 'wamv1'); ?>
            </p>
        </fieldset>

        <?php /* ----- 3. POLICE DE SUBSTITUTION -------------------------- */ ?>
        <fieldset class="a11y-group">
            <legend class="a11y-group__legend">
                <?php esc_html_e('Police de lecture', 'wamv1'); ?>
            </legend>
            <div class="a11y-select-row">
                <label for="a11y-font-choice" class="sr-only">
                    <?php esc_html_e('Choisir une police', 'wamv1'); ?>
                </label>
                <select id="a11y-font-choice" name="wam-font-choice" class="a11y-select">
                    <option value="default">
                        <?php esc_html_e('Par défaut', 'wamv1'); ?>
                    </option>
                    <option value="comic-sans">
                        <?php esc_html_e('Comic Sans MS', 'wamv1'); ?>
                    </option>
                    <option value="arial">
                        <?php esc_html_e('Arial', 'wamv1'); ?>
                    </option>
                    <option value="times">
                        <?php esc_html_e('Times New Roman', 'wamv1'); ?>
                    </option>
                </select>
            </div>
            <p class="a11y-group__desc">
                <?php esc_html_e('Certaines polices améliorent la lisibilité (ex : dyslexie).', 'wamv1'); ?>
            </p>
        </fieldset>

        <?php /* ----- 4. TAILLE DES TEXTES ------------------------------ */ ?>
        <fieldset class="a11y-group">
            <legend class="a11y-group__legend">
                <?php esc_html_e('Taille des textes', 'wamv1'); ?>
            </legend>
            <div class="a11y-toggles">
                <?php
                $sizes = array(
                    '100' => '100%',
                    '120' => '120%',
                    '150' => '150%',
                );
                foreach ($sizes as $val => $label): ?>
                    <label class="a11y-toggle-btn">
                        <input type="radio" name="wam-font-size" value="<?php echo esc_attr($val); ?>" <?php checked($val, '100'); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <?php /* ----- 5. INTERLIGNAGE ------------------------------------ */ ?>
        <fieldset class="a11y-group">
            <legend class="a11y-group__legend">
                <?php esc_html_e('Interlignage', 'wamv1'); ?>
            </legend>
            <div class="a11y-toggles">
                <label class="a11y-toggle-btn">
                    <input type="radio" name="wam-line-height" value="default" checked>
                    <?php esc_html_e('Par défaut', 'wamv1'); ?>
                </label>
                <label class="a11y-toggle-btn">
                    <input type="radio" name="wam-line-height" value="increased">
                    <?php esc_html_e('Augmenté (+25%)', 'wamv1'); ?>
                </label>
            </div>
        </fieldset>

        <?php /* ----- 6. ANIMATIONS ------------------------------------- */ ?>
        <fieldset class="a11y-group">
            <legend class="a11y-group__legend">
                <?php esc_html_e('Animations', 'wamv1'); ?>
            </legend>
            <label class="a11y-switch-row" for="a11y-reduce-motion">
                <span class="a11y-switch">
                    <input type="checkbox" id="a11y-reduce-motion" name="wam-reduce-motion" value="1">
                    <span class="a11y-switch__track"><span class="a11y-switch__thumb"></span></span>
                </span>
                <span class="a11y-switch-row__label">
                    <?php esc_html_e('Désactiver toutes les animations', 'wamv1'); ?>
                </span>
            </label>
            <p class="a11y-group__desc">
                <?php esc_html_e('Pour les personnes sensibles aux mouvements à l\'écran.', 'wamv1'); ?>
            </p>
        </fieldset>

    </div><!-- /.wam-a11y-panel__body -->

    <?php /* Pied du panel */ ?>
    <div class="wam-a11y-panel__footer">
        <button type="button" class="btn-smart" id="wam-a11y-reset">
            <?php esc_html_e('Réinitialiser', 'wamv1'); ?>
        </button>
        <p class="a11y-save-hint">
            <?php esc_html_e('Vos choix sont enregistrés automatiquement.', 'wamv1'); ?>
        </p>
    </div>
</aside>

<?php if ( ! $is_inline ) : ?>
<?php /* Fond opaque cliquable pour fermer le panel sur mobile (mode normal uniquement) */ ?>
<div class="wam-a11y-backdrop" id="wam-a11y-backdrop" aria-hidden="true"></div>
<?php endif; ?>
