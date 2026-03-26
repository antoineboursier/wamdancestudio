<?php
/**
 * WAM — Générateur dynamique llms.txt
 * 
 * À placer dans functions.php du thème wamv1
 * ou dans un fichier includes/llms-txt.php inclus depuis functions.php
 * 
 * Génère automatiquement /llms.txt à partir des CPTs cours, stages,
 * wam_membre, pages, et articles publiés.
 */

// -----------------------------------------------------------------------
// 1. Désactiver la génération Yoast si elle est active
// -----------------------------------------------------------------------
add_filter('wpseo_llms_txt_enabled', '__return_false');

// -----------------------------------------------------------------------
// 2. Intercepter la requête /llms.txt avant WordPress
// -----------------------------------------------------------------------
add_action('init', 'wam_register_llms_txt_rewrite');
function wam_register_llms_txt_rewrite()
{
    add_rewrite_rule('^llms\.txt$', 'index.php?wam_llms_txt=1', 'top');
}

add_filter('query_vars', 'wam_llms_txt_query_var');
function wam_llms_txt_query_var($vars)
{
    $vars[] = 'wam_llms_txt';
    return $vars;
}

add_action('template_redirect', 'wam_serve_llms_txt');
function wam_serve_llms_txt()
{

    if (!get_query_var('wam_llms_txt')) {
        return;
    }

    // Cache 12h — régénère si le contenu change
    $cached = get_transient('wam_llms_txt_content');
    if ($cached) {
        wam_output_llms_txt($cached);
        exit;
    }

    $content = wam_generate_llms_txt();
    set_transient('wam_llms_txt_content', $content, 12 * HOUR_IN_SECONDS);
    wam_output_llms_txt($content);
    exit;
}

// Vider le cache à chaque sauvegarde de post
add_action('save_post', 'wam_clear_llms_txt_cache');
add_action('delete_post', 'wam_clear_llms_txt_cache');
function wam_clear_llms_txt_cache()
{
    delete_transient('wam_llms_txt_content');
}

// -----------------------------------------------------------------------
// 3. Output avec les bons headers
// -----------------------------------------------------------------------
function wam_output_llms_txt(string $content)
{
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=43200'); // 12h
    header('X-Robots-Tag: noindex');
    echo $content;
}

// -----------------------------------------------------------------------
// 4. Génération du contenu llms.txt
// -----------------------------------------------------------------------
function wam_generate_llms_txt(): string
{

    $site_url = home_url();
    $site_name = get_bloginfo('name');
    $lines = [];

    // --- En-tête obligatoire (spec llmstxt.org) ---
    $lines[] = "# {$site_name}";
    $lines[] = '';
    $lines[] = '> WAM est une école de danse associative (loi 1901) basée à Villeneuve d\'Ascq,';
    $lines[] = '> dans la métropole lilloise (Nord, 59, France). Fondée par Charlotte Boursier Maczenko,';
    $lines[] = '> elle propose des cours collectifs, stages, workshops, évènements dansants,';
    $lines[] = '> ateliers et prestations événementielles (mariage, chorégraphie d\'ouverte de bal, EVJF, team building)';
    $lines[] = '> pour enfants et adultes depuis 2006. Zones d\'intervention : Villeneuve d\'Ascq,';
    $lines[] = '> Roubaix, Wasquehal, Croix, Lille et environs.';
    $lines[] = '';

    // ---------------------------------------------------------------
    // SECTION : Pages de services (statiques, priorité haute)
    // ---------------------------------------------------------------
    $service_pages = [
        'choregraphie-de-mariage-ouvertures-de-bal' => [
            'label' => 'Chorégraphie de mariage & ouvertures de bal',
            'desc' => 'Formules et tarifs pour préparer une ouverture de bal sur mesure à Lille et dans le Nord.',
        ],
        'cours-collectifs' => [
            'label' => 'Cours collectifs',
            'desc' => 'Programme complet des cours hebdomadaires adultes et enfants : danse moderne, salon, street jazz, orientale, latino.',
        ],
        'stages-workshop-ateliers' => [
            'label' => 'Stages, Workshops & Ateliers',
            'desc' => 'Stages ponctuels et ateliers thématiques tous niveaux, enfants et adultes.',
        ],
        'prof-wam' => [
            'label' => 'L\'équipe — Les professeur·es WAM',
            'desc' => 'Présentation des professeurs qui enseignent à WAM.',
        ],
        'planning' => [
            'label' => 'Planning des cours',
            'desc' => 'Horaires et planning hebdomadaire de tous les cours collectifs.',
        ],
    ];

    $lines[] = '## Pages de services';
    foreach ($service_pages as $slug => $data) {
        $page = get_page_by_path($slug);
        $url = $page ? get_permalink($page->ID) : $site_url . '/' . $slug . '/';
        $lines[] = "- [{$data['label']}]({$url}) : {$data['desc']}";
    }
    $lines[] = '';

    // ---------------------------------------------------------------
    // SECTION : Cours (CPT `cours`) — boucle dynamique
    // ---------------------------------------------------------------
    $cours_query = new WP_Query([
        'post_type' => 'cours',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ]);

    if ($cours_query->have_posts()) {
        $lines[] = '## Les cours collectifs';

        // Regrouper par catégorie de cours (taxonomie cat_cours)
        $grouped = [];
        while ($cours_query->have_posts()) {
            $cours_query->the_post();
            $post_id = get_the_ID();

            // Récupérer la catégorie (taxonomie cat_cours)
            $cats = get_the_terms($post_id, 'cat_cours');
            $cat_label = ($cats && !is_wp_error($cats)) ? $cats[0]->name : 'Autre';

            // Récupérer le sous-titre via ACF si disponible
            $subtitle = '';
            if (function_exists('get_field')) {
                $subtitle = get_field('sous_titre', $post_id) ?: '';
            }
            // Fallback : extrait WordPress
            if (!$subtitle) {
                $subtitle = get_the_excerpt();
            }
            // Nettoyer et tronquer
            $subtitle = wp_strip_all_tags($subtitle);
            $subtitle = wam_truncate($subtitle, 120);

            $grouped[$cat_label][] = [
                'title' => get_the_title(),
                'url' => get_permalink(),
                'subtitle' => $subtitle,
            ];
        }
        wp_reset_postdata();

        foreach ($grouped as $cat => $cours_list) {
            $lines[] = '';
            $lines[] = "### {$cat}";
            foreach ($cours_list as $cours) {
                $line = "- [{$cours['title']}]({$cours['url']})";
                if ($cours['subtitle']) {
                    $line .= " : {$cours['subtitle']}";
                }
                $lines[] = $line;
            }
        }
        $lines[] = '';
    }

    // ---------------------------------------------------------------
    // SECTION : Stages (CPT `stages`) — boucle dynamique
    // Uniquement les stages à venir ou récents (< 6 mois passés)
    // ---------------------------------------------------------------
    $cutoff_date = date('Ymd', strtotime('-6 months'));

    $stages_query = new WP_Query([
        'post_type' => 'stages',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'meta_value',
        'meta_key' => 'date_stage',        // Champ ACF date du stage
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'OR',
            // Stages avec date définie et non trop ancienne
            [
                'key' => 'date_stage',
                'value' => $cutoff_date,
                'compare' => '>=',
                'type' => 'DATE',
            ],
            // Stages sans date renseignée (pas d'exclusion)
            [
                'key' => 'date_stage',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);

    if ($stages_query->have_posts()) {
        $lines[] = '## Stages et ateliers';

        while ($stages_query->have_posts()) {
            $stages_query->the_post();
            $post_id = get_the_ID();

            $subtitle = '';
            $date_str = '';

            if (function_exists('get_field')) {
                $subtitle = get_field('sous_titre', $post_id)
                    ?: get_field('description', $post_id)
                    ?: '';
                $date_raw = get_field('date_stage', $post_id);
                if ($date_raw) {
                    // ACF retourne la date en d/m/Y (format de retour configuré dans ACF)
                    // Pas de conversion nécessaire — on l'utilise directement
                    $date_str = $date_raw;
                }
            }

            if (!$subtitle) {
                $subtitle = get_the_excerpt();
            }
            $subtitle = wam_truncate(wp_strip_all_tags($subtitle), 100);

            // Construire la description
            $desc_parts = [];
            if ($date_str)
                $desc_parts[] = $date_str;
            if ($subtitle)
                $desc_parts[] = $subtitle;
            $desc = implode(' — ', $desc_parts);

            $line = "- [" . get_the_title() . "](" . get_permalink() . ")";
            if ($desc)
                $line .= " : {$desc}";
            $lines[] = $line;
        }
        wp_reset_postdata();
        $lines[] = '';
    }

    // ---------------------------------------------------------------
    // SECTION : Équipe (CPT `wam_membre`)
    // ---------------------------------------------------------------
    $membres_query = new WP_Query([
        'post_type' => 'wam_membre',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    if ($membres_query->have_posts()) {
        $lines[] = '## L\'équipe pédagogique';

        while ($membres_query->have_posts()) {
            $membres_query->the_post();
            $post_id = get_the_ID();

            $micro_desc = '';
            if (function_exists('get_field')) {
                $micro_desc = get_field('micro_description_prof', $post_id) ?: '';
            }
            // Fallback : extrait WordPress
            if (!$micro_desc) {
                $micro_desc = get_the_excerpt();
            }
            $desc = wam_truncate(wp_strip_all_tags($micro_desc), 120);

            $line = "- [" . get_the_title() . "](" . get_permalink() . ")";
            if ($desc)
                $line .= " : {$desc}";
            $lines[] = $line;
        }
        wp_reset_postdata();
        $lines[] = '';
    }

    // ---------------------------------------------------------------
    // SECTION : Articles de blog (seulement les "vrais" articles)
    // Exclure les articles de test et les placeholders
    // ---------------------------------------------------------------
    $excluded_titles = ['Hello world!', 'Titre article', 'Test', 'Draft'];

    $posts_query = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $real_posts = [];
    if ($posts_query->have_posts()) {
        while ($posts_query->have_posts()) {
            $posts_query->the_post();
            $title = get_the_title();
            // Filtrer les titres placeholder
            if (in_array($title, $excluded_titles, true)) {
                continue;
            }
            $excerpt = wam_truncate(wp_strip_all_tags(get_the_excerpt()), 120);
            $real_posts[] = [
                'title' => $title,
                'url' => get_permalink(),
                'excerpt' => $excerpt,
            ];
        }
        wp_reset_postdata();
    }

    if (!empty($real_posts)) {
        $lines[] = '## Articles';
        foreach ($real_posts as $p) {
            $line = "- [{$p['title']}]({$p['url']})";
            if ($p['excerpt'])
                $line .= " : {$p['excerpt']}";
            $lines[] = $line;
        }
        $lines[] = '';
    }

    // ---------------------------------------------------------------
    // SECTION : Infos pratiques (optionnel)
    // ---------------------------------------------------------------
    $lines[] = '## Informations pratiques';
    $lines[] = '- Localisation : Villeneuve d\'Ascq (59491), métropole lilloise, Nord, France';
    $lines[] = '- Zone d\'intervention : Villeneuve d\'Ascq, Roubaix, Wasquehal, Croix, Hem, Tourcoing, Lille';
    $lines[] = '- Statut : Association loi 1901';
    $lines[] = '- Disciplines : danse moderne, danse de salon, heels, West coast swing, swing, street jazz, danse orientale, latino, éveil danse enfants';
    $lines[] = '- Prestations événementielles : chorégraphie mariage, EVJF/EVG, danse en entreprise, team building danse, TAP/NAP périscolaire';
    $lines[] = '';

    // ---------------------------------------------------------------
    // SECTION : Optional (sitemap)
    // ---------------------------------------------------------------
    $lines[] = '## Optional';
    $lines[] = "- [Sitemap XML]({$site_url}/sitemap_index.xml)";
    $lines[] = '';

    // Timestamp de génération (utile pour debug)
    $lines[] = '<!-- Generated: ' . current_time('c') . ' -->';

    return implode("\n", $lines);
}

// -----------------------------------------------------------------------
// 5. Helper : tronquer proprement sans couper un mot
// -----------------------------------------------------------------------
function wam_truncate(string $text, int $max): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $truncated = mb_substr($text, 0, $max);
    $last_space = mb_strrpos($truncated, ' ');
    if ($last_space !== false) {
        $truncated = mb_substr($truncated, 0, $last_space);
    }
    return $truncated . '…';
}

// -----------------------------------------------------------------------
// 6. Flush des rewrite rules à l'activation du thème
//    (à appeler une fois manuellement ou via un hook d'activation)
// -----------------------------------------------------------------------
add_action('after_switch_theme', function () {
    wam_register_llms_txt_rewrite();
    flush_rewrite_rules();
});