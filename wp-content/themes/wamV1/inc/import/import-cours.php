<?php
/**
 * Logique d'importation des cours pour WP-CLI et l'Admin.
 *
 * @package wamv1
 */

/**
 * Commande WP-CLI
 */
function wamv1_cli_import_cours($args, $assoc_args) {
    if (!defined('WP_CLI')) return;

    $dry_run = isset($assoc_args['dry-run']);
    $result = wamv1_import_cours_logic($dry_run);

    if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
    } else {
        WP_CLI::success($result);
    }
}

/**
 * Logique métier universelle
 */
function wamv1_import_cours_logic($dry_run = false) {
    $csv_path = get_template_directory() . '/data/cours_wam_import.csv';

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

        // Upsert
        $existing = get_page_by_path($data['slug'], OBJECT, 'cours');
        $post_id = $existing ? $existing->ID : 0;
        
        $post_args = array(
            'post_type'   => 'cours',
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

        // Taxonomie cat_cours
        if (!empty($data['cat_cours'])) {
            $slugs = array_map('trim', explode('|', $data['cat_cours']));
            $term_ids = array();
            foreach ($slugs as $slug) {
                // Normalisation : On force la version plurielle "enfants" comme demandé
                if ($slug === 'enfant' || $slug === 'danse-enfant') {
                    $slug = 'enfants';
                }

                $term = get_term_by('slug', $slug, 'cat_cours');
                if (! $term) {
                    // Création de la catégorie à la volée si elle n'existe pas
                    $inserted = wp_insert_term(ucfirst($slug), 'cat_cours', array('slug' => $slug));
                    if (! is_wp_error($inserted)) {
                        $term_ids[] = (int) $inserted['term_id'];
                    }
                } else {
                    $term_ids[] = (int)$term->term_id;
                }
            }
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, 'cat_cours');
            }
        }

        // Champs ACF
        if (function_exists('update_field')) {
            $acf_fields = array(
                'sous_titre', 'jour_de_cours', 'heure_debut', 'heure_de_fin',
                'tarif_cours', 'description_cours', 'pedagogie', 'info_complementaire',
                'echauffement_time', 'echauffement_description',
                'exercice_time', 'exercice_description',
                'choregraphie_time', 'choregraphie_description',
                'styles_musiques', 'tenue'
            );

            foreach ($acf_fields as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    update_field($field, sanitize_textarea_field($data[$field]), $post_id);
                }
            }

            if (isset($data['complete_cours'])) {
                update_field('complete_cours', (bool)intval($data['complete_cours']), $post_id);
            }

            // Relation Professeur(s)
            if (!empty($data['prof_login'])) {
                $logins = array_map('trim', explode('|', $data['prof_login']));
                $user_ids = array();
                foreach ($logins as $login) {
                    $user = get_user_by('login', $login);
                    if ($user) {
                        $user_ids[] = (int)$user->ID;
                    }
                }
                if (!empty($user_ids)) {
                    update_field('prof_cours', $user_ids, $post_id);
                }
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
            }
        }
    }

    fclose($handle);
    return sprintf("Importation des cours terminée — Créés : %d | MàJ : %d | Erreurs : %d", $created, $updated, $errors);
}

/**
 * Supprime tous les posts de type 'cours' (purge avant ré-import).
 */
function wamv1_purge_cours_logic() {
    $posts = get_posts([
        'post_type'      => 'cours',
        'post_status'    => 'any',
        'posts_per_page' => -1,
    ]);

    $count = 0;
    foreach ($posts as $post) {
        if (wp_delete_post($post->ID, true)) { // true = suppression définitive
            $count++;
        }
    }

    return sprintf("%d cours ont été supprimés définitivement.", $count);
}
