<?php
/**
 * Template Part : Module Accessibilité
 * HTML du panneau de personnalisation (affichage statique/inline)
 *
 * @package wamv1
 */

$icon_dir  = get_template_directory_uri() . '/assets/images/';
?>

<section class="wam-a11y-panel wam-a11y-panel--inline"
    id="wam-a11y-panel"
    role="region"
    aria-label="<?php esc_attr_e("Options d'accessibilité", 'wamv1'); ?>">

    <?php /* En-tête du panel */ ?>
    <div class="wam-a11y-panel__header">
        <p class="wam-a11y-panel__title is-style-title-norm-md">
            <?php esc_html_e('Personnalisez votre expérience sur notre site', 'wamv1'); ?>
        </p>
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
</section>
