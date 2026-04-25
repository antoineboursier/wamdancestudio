<?php
/**
 * Configuration SMTP de WAM V1
 * Accroche sur phpmailer_init pour router les emails sortants.
 */

if (!defined('ABSPATH')) {
    exit;
}

function wamv1_phpmailer_init(PHPMailer\PHPMailer\PHPMailer $phpmailer) {
    $smtp_host = get_option('wam_setting_smtp_host', '');
    
    // Si l'hôte n'est pas configuré, on ne force pas le SMTP WAM (fallback hébergeur par défaut).
    if (empty($smtp_host)) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = $smtp_host;
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = absint(get_option('wam_setting_smtp_port', 465));
    $phpmailer->Username   = sanitize_text_field(get_option('wam_setting_smtp_user', ''));
    $phpmailer->Password   = sanitize_text_field(get_option('wam_setting_smtp_pass', ''));
    
    $secure = get_option('wam_setting_smtp_secure', 'ssl');
    if ($secure === 'ssl' || $secure === 'tls') {
        $phpmailer->SMTPSecure = $secure;
    } else {
        $phpmailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
    }

    $from_email = sanitize_email(get_option('wam_setting_smtp_from_email', get_option('admin_email')));
    $from_name  = sanitize_text_field(get_option('wam_setting_smtp_from_name', 'WAM Dance Studio'));
    
    $phpmailer->setFrom($from_email, $from_name);
}
add_action('phpmailer_init', 'wamv1_phpmailer_init');
