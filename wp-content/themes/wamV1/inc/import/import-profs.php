<?php
/**
 * Logique d'importation des professeurs pour WP-CLI et l'Admin.
 *
 * @package wamv1
 */

/**
 * Commande WP-CLI
 */
function wamv1_cli_import_profs($args, $assoc_args) {
    if (!defined('WP_CLI')) return;

    $dry_run = isset($assoc_args['dry-run']);
    $result = wamv1_import_profs_logic($dry_run);

    if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
    } else {
        WP_CLI::success($result);
    }
}

/**
 * Logique métier universelle
 */
function wamv1_import_profs_logic($dry_run = false) {
    $csv_path = get_template_directory() . '/data/profs_wam_import.csv';

    if (!file_exists($csv_path)) {
        return new WP_Error('csv_missing', "Fichier CSV introuvable : {$csv_path}");
    }

    $handle = fopen($csv_path, 'r');
    $headers = array_map('trim', fgetcsv($handle, 0, ','));
    $created = $updated = $errors = 0;

    $log_prefix = $dry_run ? "[DRY RUN] " : "";

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if (count($row) !== count($headers)) continue;
        $data = array_combine($headers, array_map('trim', $row));
        
        if (empty($data['post_title']) || empty($data['slug'])) continue;

        if (defined('WP_CLI') && WP_CLI) WP_CLI::log("→ {$data['post_title']}");
        
        if ($dry_run) continue;

        // 1. Gestion de l'utilisateur WordPress
        $user_id = 0;
        if (!empty($data['wp_user_login'])) {
            $user = get_user_by('login', $data['wp_user_login']);
            if ($user) {
                $user_id = $user->ID;
            } else {
                $user_id = wp_insert_user(array(
                    'user_login' => $data['wp_user_login'],
                    'user_pass'  => wp_generate_password(),
                    'user_nicename' => $data['slug'],
                    'display_name'  => $data['post_title'],
                    'role'          => 'professeur'
                ));
            }
        }

        // 2. Gestion du Post WAM Membre (CPT)
        $existing = get_page_by_path($data['slug'], OBJECT, 'wam_membre');
        $post_id = $existing ? $existing->ID : 0;
        
        $post_args = array(
            'post_type'   => 'wam_membre',
            'post_status' => 'publish',
            'post_title'  => $data['post_title'],
            'post_name'   => $data['slug']
        );

        if ($post_id) {
            $post_args['ID'] = $post_id;
            wp_update_post($post_args);
            $updated++;
        } else {
            $result = wp_insert_post($post_args, true);
            if (is_wp_error($result)) {
                $errors++;
                continue;
            }
            $post_id = $result;
            $created++;
        }

        // Mise à jour des champs ACF
        if (function_exists('update_field')) {
            if (!empty($data['micro_description_prof'])) {
                update_field('micro_description_prof', $data['micro_description_prof'], $post_id);
            }
            if (!empty($data['description_prof'])) {
                update_field('description_prof', wp_kses_post($data['description_prof']), $post_id);
            }

            $socials = array();
            $social_keys = array('instagram_link_prof', 'facebook_link_prof', 'tiktok_link_prof', 'linkedin_link_prof');
            foreach ($social_keys as $key) {
                if (!empty($data[$key])) {
                    $socials[$key] = esc_url_raw($data[$key]);
                }
            }
            if (!empty($socials)) {
                update_field('reseaux_sociaux_prof', $socials, $post_id);
            }

            if ($user_id && !is_wp_error($user_id)) {
                update_field('user_prof', $user_id, $post_id);
            }
        }

        // Yoast
        $yoast_map = array(
            'yoast_focus_kw'  => '_yoast_wpseo_focuskw',
            'yoast_title'     => '_yoast_wpseo_title',
            'yoast_meta_desc' => '_yoast_wpseo_metadesc',
            'yoast_og_title'  => '_yoast_wpseo_opengraph-title',
            'yoast_og_desc'   => '_yoast_wpseo_opengraph-description'
        );

        foreach ($yoast_map as $col => $meta_key) {
            if (isset($data[$col]) && $data[$col] !== '') {
                update_post_meta($post_id, $meta_key, sanitize_text_field($data[$col]));
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }
    }

    fclose($handle);
    return sprintf("Importation des professeurs terminée — Créés : %d | MàJ : %d | Erreurs : %d", $created, $updated, $errors);
}
