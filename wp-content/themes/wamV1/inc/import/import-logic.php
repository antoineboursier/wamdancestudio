<?php
/**
 * Logique partagée pour l'importation (CLI et Admin)
 *
 * @package wamv1
 */

/**
 * Fonction de log universelle
 */
function wamv1_import_log($message, $type = 'info') {
    if (defined('WP_CLI') && WP_CLI) {
        if ($type === 'error') {
            WP_CLI::error($message);
        } elseif ($type === 'warning') {
            WP_CLI::warning($message);
        } elseif ($type === 'success') {
            WP_CLI::success($message);
        } else {
            WP_CLI::log($message);
        }
    } else {
        // Pour l'admin, on pourrait stocker dans une session ou un transient
        // Mais pour l'instant on se contente de l'error_log si besoin
        if ($type === 'error') {
            error_log("WAM Import Error: " . $message);
        }
    }
}

/**
 * Récupère la date de dernière modification d'un fichier CSV
 */
function wamv1_get_csv_mtime($filename) {
    $path = get_template_directory() . '/data/' . $filename;
    if (file_exists($path)) {
        $timestamp = filemtime($path);
        // On utilise wp_date pour respecter le fuseau paramétré dans WP
        // Si WP est en UTC, on pourra suggérer à l'utilisateur de le régler
        return wp_date(get_option('date_format') . ' à ' . get_option('time_format'), $timestamp);
    }
    return 'Fichier introuvable';
}
