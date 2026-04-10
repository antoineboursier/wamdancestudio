<?php
/**
 * Template part: Contact Form (Shortcode)
 * 
 * Args:
 * - $args['default_subject']
 */

$default_subject = $args['default_subject'] ?? '';

$subjects = [
    'Cours collectifs',
    'Cours particuliers',
    'Mariage - EVJF',
    'Prestation de troupe',
    'Autres'
];
?>
<div class="wam-contact-form-wrapper">
    <p class="wam-contact-form-notice">
        Les champs marqués d'un <span class="required">*</span> sont obligatoires.
    </p>
    <form id="wam-contact-form" class="wam-contact-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" novalidate>
        <?php wp_nonce_field('wam_contact_action', 'wam_contact_nonce'); ?>
        <input type="hidden" name="action" value="wam_handle_contact_form">

        <div class="wam-form-row form-row-2">
            <div class="wam-form-group">
                <label for="contact_first_name">Prénom <span class="required">*</span></label>
                <input type="text" id="contact_first_name" name="first_name" required aria-required="true" placeholder="ex: Alexia">
            </div>
            <div class="wam-form-group">
                <label for="contact_last_name">Nom <span class="required">*</span></label>
                <input type="text" id="contact_last_name" name="last_name" required aria-required="true" placeholder="ex: Dubois">
            </div>
        </div>

        <div class="wam-form-row form-row-2">
            <div class="wam-form-group">
                <label for="contact_email">E-mail <span class="required">*</span></label>
                <input type="email" id="contact_email" name="email" required aria-required="true" placeholder="votre@email.fr">
            </div>
            <div class="wam-form-group">
                <label for="contact_phone">Téléphone</label>
                <input type="tel" id="contact_phone" name="phone" placeholder="06 .. .. .. ..">
            </div>
        </div>

        <div class="wam-form-group">
            <label for="contact_subject">Sujet de votre demande <span class="required">*</span></label>
            <div class="wam-select-wrapper">
                <select id="contact_subject" name="subject" required aria-required="true">
                    <option value="" disabled <?php selected($default_subject, ''); ?>>-- Sélectionnez un motif --</option>
                    <?php foreach ($subjects as $subject) : ?>
                        <option value="<?php echo esc_attr($subject); ?>" <?php selected($default_subject, $subject); ?>>
                            <?php echo esc_html($subject); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="wam-form-group">
            <label for="contact_message">Votre message <span class="required">*</span></label>
            <textarea id="contact_message" name="message" rows="5" required aria-required="true" placeholder="Comment pouvons-nous vous aider ?"></textarea>
        </div>

        <!-- Champ Honeypot (Anti-Spam invisible) -->
        <div class="wam-hp-field" aria-hidden="true">
            <label for="wam_contact_hp">Ne pas remplir ce champ si vous êtes humain</label>
            <input type="text" name="wam_contact_hp" id="wam_contact_hp" tabindex="-1" autocomplete="off">
        </div>

        <div class="wam-form-submit">
            <button type="submit" class="btn btn-primary" id="wam-contact-submit">
                <span class="btn__text">Envoyer mon message</span>
            </button>
        </div>

        <div class="wam-form-response" id="wam-contact-response" aria-live="polite"></div>
    </form>
</div>
