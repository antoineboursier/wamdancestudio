<?php
/**
 * Handler AJAX pour le formulaire de contact
 */

if (!defined('ABSPATH')) {
    exit;
}

function wamv1_handle_contact_form() {
    // 1. Vérification du nonce (Sécurité)
    if (!isset($_POST['wam_contact_nonce']) || !wp_verify_nonce($_POST['wam_contact_nonce'], 'wam_contact_action')) {
        wp_send_json_error(['message' => 'Session expirée ou sécurité invalide, veuillez rafraîchir la page.']);
    }

    // 1b. Vérification Honeypot (Anti-spam)
    if (!empty($_POST['wam_contact_hp'])) {
        // C'est un robot. On lui fait croire que tout va bien, mais on n'envoie rien.
        wp_send_json_success(['message' => '✨ Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais !']);
    }

    // 2. Récupération et nettoyage des données
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
    $email      = sanitize_email($_POST['email'] ?? '');
    $phone      = sanitize_text_field($_POST['phone'] ?? '');
    $subject    = sanitize_text_field($_POST['subject'] ?? '');
    $message    = sanitize_textarea_field($_POST['message'] ?? '');

    // 3. Validation de base
    if (empty($first_name) || empty($last_name) || empty($email) || empty($message)) {
        wp_send_json_error(['message' => 'Veuillez remplir tous les champs obligatoires (*).']);
    }

    if (!is_email($email)) {
        wp_send_json_error(['message' => 'L\'adresse e-mail n\'est pas valide.']);
    }

    // 4. Préparation de l'e-mail
    $opts = get_option('wam_config', []);
    $to_emails_raw = $opts['smtp_to_emails'] ?? '';
    
    // Déterminer les adresses d'envoi
    $to_emails = [];
    if (!empty($to_emails_raw)) {
        // Explode sur la virgule et nettoyer chaque adresse
        $emails_array = array_map('trim', explode(',', $to_emails_raw));
        $to_emails = array_filter($emails_array, 'is_email');
    }
    
    // Fallback à l'admin du site si aucune adresse valide trouvée
    if (empty($to_emails)) {
        $to_emails = get_option('admin_email');
    }

    $mail_subject = "{$subject} - {$first_name} {$last_name}";

    $mail_body = "<h2>Nouveau message depuis le site WAM Dance Studio</h2>";
    $mail_body .= "<p><strong>Envoyé par :</strong> {$first_name} {$last_name}</p>";
    $mail_body .= "<p><strong>Email :</strong> <a href='mailto:{$email}'>{$email}</a></p>";
    if (!empty($phone)) {
        $mail_body .= "<p><strong>Téléphone :</strong> {$phone}</p>";
    }
    $mail_body .= "<p><strong>Sujet :</strong> {$subject}</p>";
    $mail_body .= "<hr>";
    $mail_body .= "<h3>Message :</h3>";
    $mail_body .= "<p>" . nl2br($message) . "</p>";
    $mail_body .= "<hr>";
    $mail_body .= "<p><em>Ce message a été envoyé via le formulaire de contact du site.</em></p>";

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'Reply-To: ' . $first_name . ' ' . $last_name . ' <' . $email . '>'
    ];

    // 5. Envoi
    // Sécurité pour ne pas qu'un Warning PHP (ex: SMTP connection failed) n'abîme le JSON
    ob_start();
    $sent = wp_mail($to_emails, $mail_subject, $mail_body, $headers);
    $error_output = ob_get_clean();

    if ($sent) {
        wp_send_json_success(['message' => '✨ Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais !']);
    } else {
        // En cas d'erreur wp_mail remonte false. 
        // On renvoie un JSON propre, peu importe ce que PHPMailer a testé.
        wp_send_json_error(['message' => 'Une erreur technique est survenue lors de l\'envoi (votre serveur SMTP est peut-être mal configuré).']);
    }
}
add_action('wp_ajax_wam_handle_contact_form', 'wamv1_handle_contact_form');
add_action('wp_ajax_nopriv_wam_handle_contact_form', 'wamv1_handle_contact_form');
