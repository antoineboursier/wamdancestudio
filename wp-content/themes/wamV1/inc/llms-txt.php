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

// Les données tarifaires (prix, paliers, saison, FAQ, association) sont lues
// depuis inc/tarifs.php, déjà source de vérité de la page /tarifs/. Le require
// est un filet : functions.php le charge normalement avant ce fichier.
require_once get_template_directory() . '/inc/tarifs.php';

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
    add_rewrite_rule('^llms\.txt/?$', 'index.php?wam_llms_txt=1', 'top');
}

/**
 * Empêche la redirection canonique d'ajouter un slash final à /llms.txt.
 *
 * WordPress considère /llms.txt comme une URL sans extension connue et la
 * redirige en 301 vers /llms.txt/. La spec llmstxt.org attend un 200 direct,
 * et certains fetchers stricts ne suivent pas les redirections.
 */
add_filter('redirect_canonical', 'wam_llms_txt_skip_canonical', 10, 2);
function wam_llms_txt_skip_canonical($redirect_url, $requested_url)
{
    if (get_query_var('wam_llms_txt')) {
        return false;
    }
    return $redirect_url;
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
/**
 * Libellé de la saison commercialisée, au format "2026-2027".
 *
 * On annonce la saison vers laquelle on vend, pas celle qui s'achève : dès la
 * fin des cours (juin), ce sont les inscriptions de la rentrée suivante qui
 * sont ouvertes. La bascule est donc calée sur le 1er juin, et non sur la
 * rentrée elle-même — sans quoi l'été annoncerait une saison déjà terminée.
 *
 * Aucune saisie annuelle n'est nécessaire : le fichier suit le calendrier.
 */
function wam_llms_txt_saison(): string
{
    $mois = (int) current_time('n');
    $annee = (int) current_time('Y');
    $debut = ($mois >= 6) ? $annee : $annee - 1;

    return $debut . '-' . ($debut + 1);
}

/**
 * Bornes de la saison en clair ("du 14 septembre 2026 au 20 juin 2027").
 *
 * Lues dans Configuration WAM via `wamv1_tarifs_saison()`, qui est déjà la
 * source de vérité de la page /tarifs/. Le libellé calendaire ne sert que de
 * repli quand les bornes ne sont pas déterminables (option vide hors saison).
 *
 * @return string Chaîne vide si aucune borne n'est exploitable.
 */
function wam_llms_txt_saison_bornes(): string
{
    if (!function_exists('wamv1_tarifs_saison')) {
        return '';
    }

    $saison = wamv1_tarifs_saison();
    $debut = trim((string) ($saison['debut'] ?? ''));
    $fin = trim((string) ($saison['fin'] ?? ''));

    if ($debut && $fin) {
        return "du {$debut} au {$fin}";
    }
    if ($debut) {
        return "à partir du {$debut}";
    }

    return '';
}

/**
 * Montant en texte brut ("285 €", "22,50 €").
 *
 * Pendant de `wamv1_tarifs_montant()`, qui produit du HTML avec une espace
 * insécable (`285&nbsp;€`) : inutilisable tel quel dans un fichier .txt.
 */
function wam_llms_txt_prix(float $montant): string
{
    $decimales = (fmod($montant, 1.0) === 0.0) ? 0 : 2;

    return number_format($montant, $decimales, ',', ' ') . ' €';
}

/**
 * Jour de la semaine en français depuis la valeur ACF `jour_de_cours`.
 *
 * Le champ stocke "01day"…"07day" (Lundi…Dimanche) et peut revenir en tableau
 * si le select est configuré en multiple.
 */
function wam_llms_txt_jour($valeur): string
{
    if (is_array($valeur)) {
        $valeur = reset($valeur);
    }
    $valeur = is_array($valeur) ? ($valeur['value'] ?? '') : (string) $valeur;

    $jours = [
        '01day' => 'Lundi',
        '02day' => 'Mardi',
        '03day' => 'Mercredi',
        '04day' => 'Jeudi',
        '05day' => 'Vendredi',
        '06day' => 'Samedi',
        '07day' => 'Dimanche',
    ];

    return $jours[$valeur] ?? '';
}

/**
 * Tranche d'âge lisible depuis les champs ACF `age_min` / `age_max`.
 *
 * Rappel de la règle métier (v1.4.0) : âge min strict, âge max non strict.
 */
function wam_llms_txt_ages($min, $max): string
{
    $min = (int) $min;
    $max = (int) $max;

    if ($min && $max) {
        return "{$min}-{$max} ans";
    }
    if ($min) {
        return "dès {$min} ans";
    }
    if ($max) {
        return "jusqu'à {$max} ans";
    }

    return '';
}

/**
 * Noms des professeur·es depuis le champ ACF `prof_cours` (user, multiple).
 */
function wam_llms_txt_profs($valeur): string
{
    if (!$valeur) {
        return '';
    }

    $noms = [];
    foreach ((array) $valeur as $user) {
        if (is_array($user)) {
            $nom = $user['display_name'] ?? '';
        } elseif (is_object($user)) {
            $nom = $user->display_name ?? '';
        } else {
            $u = get_userdata((int) $user);
            $nom = $u ? $u->display_name : '';
        }
        $nom = wam_llms_txt_texte($nom);
        if ($nom) {
            $noms[] = $nom;
        }
    }

    return $noms ? implode(' et ', $noms) : '';
}

/**
 * Détails factuels d'un cours, assemblés pour le llms.txt.
 *
 * Chaque segment est optionnel : un cours sans horaire ou sans prof ne doit
 * produire ni séparateur orphelin ni segment vide.
 *
 * @param string $sous_titre Sous-titre déjà rendu, pour ne pas répéter l'âge.
 * @return string Ex. "Mercredi 18h-19h — dès 16 ans — avec Valentina"
 */
function wam_llms_txt_details_cours(int $post_id, string $sous_titre = ''): string
{
    if (!function_exists('get_field')) {
        return '';
    }

    $parts = [];

    // Créneau : le jour seul reste utile si l'horaire n'est pas saisi.
    $jour = wam_llms_txt_jour(get_field('jour_de_cours', $post_id));
    $debut = wam_llms_txt_texte(get_field('heure_debut', $post_id));
    $fin = wam_llms_txt_texte(get_field('heure_de_fin', $post_id));
    $horaire = trim($jour . ' ' . trim($debut . ($debut && $fin ? '-' : '') . $fin));
    if ($horaire) {
        $parts[] = $horaire;
    }

    // ⚠️ L'âge n'est ajouté QUE si le sous-titre n'en porte pas déjà un.
    // `age_min`/`age_max` sont la règle d'éligibilité à l'inscription (âge min
    // strict, âge max non strict) et non l'âge communiqué : sur les cours
    // enfants ils débordent volontairement d'un an (« Éveil 1 » est annoncé
    // 3-4 ans pour un age_min de 2). Publier les deux ferait se contredire la
    // même ligne. Le sous-titre, qui est ce que le site affiche, fait foi.
    $ages = wam_llms_txt_ages(get_field('age_min', $post_id), get_field('age_max', $post_id));
    if ($ages && !preg_match('/\bans\b/iu', $sous_titre)) {
        $parts[] = $ages;
    }

    $profs = wam_llms_txt_profs(get_field('prof_cours', $post_id));
    if ($profs) {
        $parts[] = 'avec ' . $profs;
    }

    $styles = wam_truncate(wam_llms_txt_texte(get_field('styles_musiques', $post_id)), 60);
    if ($styles) {
        $parts[] = $styles;
    }

    if (get_field('complete_cours', $post_id)) {
        $parts[] = 'COMPLET';
    }

    return implode(' — ', $parts);
}

function wam_generate_llms_txt(): string
{

    $site_url = home_url();
    $site_name = get_bloginfo('name');
    $saison = wam_llms_txt_saison();
    $lines = [];

    // --- En-tête obligatoire (spec llmstxt.org) ---
    $lines[] = "# {$site_name}";
    $lines[] = '';
    $lines[] = '> WAM est une école de danse associative (loi 1901) basée à Villeneuve d\'Ascq (59650),';
    $lines[] = '> au cœur de la métropole lilloise et à proximité immédiate de Croix, Wasquehal et Roubaix.';
    $lines[] = '> Fondée par Charlotte Boursier Maczenko, elle propose des cours collectifs, stages,';
    $lines[] = '> workshops et prestations événementielles (mariage, chorégraphie, EVJF, team building)';
    $lines[] = '> pour enfants et adultes depuis 2006.';
    $lines[] = ">";
    $bornes = wam_llms_txt_saison_bornes();
    $lines[] = "> Saison en cours : {$saison}" . ($bornes ? " ({$bornes})" : '') . '.';
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
        'cours-particulier-prive' => [
            'label' => 'Cours particuliers & cours privés',
            'desc' => 'Cours individuels ou en petit groupe, tous styles et tous niveaux, sur rendez-vous.',
        ],
        'evjf-evg-anniversaire' => [
            'label' => 'EVJF, EVG & anniversaires',
            'desc' => 'Ateliers de danse pour enterrement de vie de jeune fille ou de garçon et anniversaires, encadrés par un·e professeur·e.',
        ],
        'location-du-studio' => [
            'label' => 'Location du studio',
            'desc' => 'Location de salle de danse à Villeneuve d\'Ascq à l\'heure, avec miroirs, parquet et sonorisation.',
        ],
        'team-building' => [
            'label' => 'Team building danse en entreprise',
            'desc' => 'Animations et team building danse pour entreprises et collectivités de la métropole lilloise.',
        ],
        'tarifs' => [
            'label' => 'Tarifs',
            'desc' => 'Tarifs des cours annuels, remise multi-cours, stages, cours privés et prestations événementielles.',
        ],
        // Pages géo (v1.6.0) : elles ciblent précisément les requêtes locales,
        // c'est-à-dire le terrain sur lequel une IA est le plus souvent
        // interrogée (« cours de danse à Roubaix »).
        'cours-de-danse-roubaix' => [
            'label' => 'Cours de danse à Roubaix',
            'desc' => 'Cours de danse pour les habitant·es de Roubaix, Croix, Hem et Lys-lez-Lannoy, à 15 minutes du studio.',
        ],
        'cours-de-danse-wasquehal' => [
            'label' => 'Cours de danse à Wasquehal',
            'desc' => 'Cours de danse pour Wasquehal, Croix et Marcq-en-Barœul, à 10 minutes du studio.',
        ],
        'cours-de-danse-belgique' => [
            'label' => 'Cours de danse depuis la Belgique',
            'desc' => 'Cours de danse accessibles depuis Tournai, Mouscron et Courtrai, avec des horaires compatibles avec un trajet frontalier.',
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
            $cat_label = ($cats && !is_wp_error($cats)) ? wam_llms_txt_texte($cats[0]->name) : 'Autre';

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
            $subtitle = wam_llms_txt_texte($subtitle);
            $subtitle = wam_truncate($subtitle, 120);

            $grouped[$cat_label][] = [
                'title' => wam_llms_txt_texte(get_the_title()),
                'url' => get_permalink(),
                // Le sous-titre porte le niveau ("Débutants") : aucun champ ACF
                // ne le stocke, il est donc conservé en tête des détails.
                'subtitle' => $subtitle,
                'details' => wam_llms_txt_details_cours($post_id, $subtitle),
            ];
        }
        wp_reset_postdata();

        foreach ($grouped as $cat => $cours_list) {
            $lines[] = '';
            $lines[] = "### {$cat}";
            foreach ($cours_list as $cours) {
                $line = "- [{$cours['title']}]({$cours['url']})";

                $infos = array_filter([$cours['subtitle'], $cours['details']]);
                if ($infos) {
                    $line .= ' : ' . implode(' — ', $infos);
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
            $subtitle = wam_truncate(wam_llms_txt_texte($subtitle), 100);

            // Construire la description
            $desc_parts = [];
            if ($date_str)
                $desc_parts[] = $date_str;
            if ($subtitle)
                $desc_parts[] = $subtitle;
            $desc = implode(' — ', $desc_parts);

            $line = "- [" . wam_llms_txt_texte(get_the_title()) . "](" . get_permalink() . ")";
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
            $desc = wam_truncate(wam_llms_txt_texte($micro_desc), 120);

            $line = "- [" . wam_llms_txt_texte(get_the_title()) . "](" . get_permalink() . ")";
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
            $title = wam_llms_txt_texte(get_the_title());
            // Filtrer les titres placeholder
            if (in_array($title, $excluded_titles, true)) {
                continue;
            }
            $excerpt = wam_truncate(wam_llms_txt_texte(get_the_excerpt()), 120);
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
    // SECTION : Tarifs
    //
    // Aucun montant n'est écrit ici : tout est lu dans inc/tarifs.php, déjà
    // source de vérité de la page /tarifs/ et du JSON-LD. Les prix ne peuvent
    // donc ni se périmer ni contredire le panier.
    //
    // ⚠️ Ne pas utiliser wamv1_tarifs_montant() : elle retourne du HTML avec
    // des &nbsp;, inadapté à un fichier texte.
    // ---------------------------------------------------------------
    $tarifs = [];

    $prix_cours = function_exists('wamv1_tarifs_prix_cours') ? wamv1_tarifs_prix_cours() : 0;
    if ($prix_cours > 0) {
        $tarifs[] = '- Adhésion annuelle, 1 cours par semaine : ' . wam_llms_txt_prix($prix_cours);

        $remise = wamv1_tarifs_remise_multi_cours();
        if ($remise > 0) {
            $tarifs[] = '- Remise de ' . wam_llms_txt_prix($remise) . ' par cours hebdomadaire supplémentaire';

            $paliers = wamv1_tarifs_paliers_cours(4);
            foreach ($paliers as $palier) {
                if ((int) $palier['nb'] < 2) {
                    continue;
                }
                $tarifs[] = '  - ' . $palier['nb'] . ' cours par semaine : '
                    . wam_llms_txt_prix($palier['total']) . ' par an ('
                    . wam_llms_txt_prix($palier['moyen']) . ' par cours)';
            }
        }
    }

    $stage_min = function_exists('wamv1_tarifs_stage_prix_min') ? wamv1_tarifs_stage_prix_min() : 0;
    if ($stage_min > 0) {
        $tarifs[] = '- Stages et workshops ponctuels : à partir de ' . wam_llms_txt_prix($stage_min);
    }

    $prix_prive = function_exists('wamv1_tarifs_prix_produit') ? wamv1_tarifs_prix_produit(675) : 0;
    if ($prix_prive > 0) {
        $tarifs[] = '- Cours particulier (1 h) : ' . wam_llms_txt_prix($prix_prive);
    }

    // Formules de chorégraphie de mariage — mêmes définitions que
    // template-parts/tarifs-formules.php, dont les prix viennent de WooCommerce.
    $formules = function_exists('wamv1_tarifs_formules') ? wamv1_tarifs_formules([
        ['id' => 785, 'label' => 'Formule découverte', 'detail' => '1 séance d’1 heure'],
        ['id' => 681, 'label' => 'Formule 5 h', 'detail' => '5 séances d’1 heure'],
        ['id' => 964, 'label' => 'Formule 8 h', 'detail' => '8 séances d’1 heure'],
        ['id' => 682, 'label' => 'Formule 10 h', 'detail' => '10 séances d’1 heure'],
    ]) : [];

    if ($formules) {
        $tarifs[] = '- Chorégraphie de mariage / ouverture de bal :';
        foreach ($formules as $formule) {
            $detail = $formule['detail'] ? ' (' . wam_llms_txt_texte($formule['detail']) . ')' : '';
            $tarifs[] = '  - ' . wam_llms_txt_texte($formule['label']) . $detail . ' : '
                . wam_llms_txt_prix($formule['prix']);
        }
    }

    $forfaits = function_exists('wamv1_tarifs_forfaits_manuels') ? wamv1_tarifs_forfaits_manuels() : [];

    if (!empty($forfaits['evjf']['prix'])) {
        $evjf = $forfaits['evjf'];
        $ligne = '- EVJF / EVG / anniversaire : ' . wam_llms_txt_prix($evjf['prix']);
        if (!empty($evjf['duree'])) {
            $ligne .= ' pour ' . $evjf['duree'];
        }
        if (!empty($evjf['participants'])) {
            $ligne .= ', jusqu’à ' . (int) $evjf['participants'] . ' personnes';
        }
        $tarifs[] = $ligne;
    }

    if (!empty($forfaits['location']['prix_bas'])) {
        $loc = $forfaits['location'];
        $ligne = '- Location du studio : de ' . wam_llms_txt_prix($loc['prix_bas'])
            . ' à ' . wam_llms_txt_prix($loc['prix_haut']) . ' de l’heure';
        if (!empty($loc['surface'])) {
            $ligne .= ' (salle de ' . (int) $loc['surface'] . ' m²';
            $ligne .= !empty($loc['capacite']) ? ', ' . (int) $loc['capacite'] . ' personnes maximum)' : ')';
        }
        $tarifs[] = $ligne;
    }

    $trois_x = function_exists('wamv1_tarifs_paiement_3x') ? wamv1_tarifs_paiement_3x() : [];
    if (!empty($trois_x['actif'])) {
        $tarifs[] = '- Paiement en 3 fois sans frais possible (HelloAsso)';
    }

    if ($tarifs) {
        $lines[] = '## Tarifs';
        $lines = array_merge($lines, $tarifs);
        $lines[] = '- Détail complet et conditions : ' . $site_url . '/tarifs/';
        $lines[] = '';
    }

    // ---------------------------------------------------------------
    // SECTION : Questions fréquentes
    //
    // Reprend les Q/R générées par wamv1_tarifs_faq(), déjà affichées sur
    // /tarifs/ et balisées en FAQPage. Le format question/réponse est le plus
    // directement exploitable par un modèle.
    // ---------------------------------------------------------------
    $faq = function_exists('wamv1_tarifs_faq') ? wamv1_tarifs_faq() : [];
    if ($faq) {
        $lines[] = '## Questions fréquentes';
        foreach ($faq as $item) {
            $question = wam_llms_txt_texte($item['q'] ?? '');
            $reponse = wam_llms_txt_texte($item['r'] ?? '');
            if ($question && $reponse) {
                $lines[] = "- **{$question}** {$reponse}";
            }
        }
        $lines[] = '';
    }

    // ---------------------------------------------------------------
    // SECTION : Infos pratiques
    // ---------------------------------------------------------------
    // L'adresse est lue là où elle est administrée (Configuration WAM), jamais
    // écrite en dur : elle ne peut donc plus diverger de la page Contact ni du
    // JSON-LD LocalBusiness.
    $adresse = function_exists('wam_adresse_lieu') ? wam_adresse_lieu() : '';
    $adresse = wam_llms_txt_texte($adresse) ?: '202 rue Jean Jaurès, Villeneuve d\'Ascq';

    // Zones desservies : dérivées de la même table que le JSON-LD, pour que le
    // déclaré aux IA corresponde au déclaré aux moteurs.
    $zones = [];
    if (function_exists('wamv1_schema_area_served')) {
        foreach (wamv1_schema_area_served('mel') as $ville) {
            $nom = wam_llms_txt_texte($ville['name'] ?? '');
            if ($nom) {
                $zones[] = $nom;
            }
        }
    }
    $zones = $zones ?: ['Villeneuve d\'Ascq', 'Croix', 'Wasquehal', 'Roubaix', 'Hem', 'Lille'];

    $lines[] = '## Informations pratiques';
    $lines[] = "- Établissement : {$site_name}";
    $lines[] = "- Siège & Studio : {$adresse} (59650), Nord, France";
    $lines[] = '- Coordonnées GPS : 50.665313, 3.141649';
    $lines[] = '- Zones d\'intervention & Proximité : ' . implode(', ', $zones);
    $lines[] = '- Horaires d\'ouverture : Lun-Sam (09:00 - 22:00), Dim (13:00 - 22:00)';

    // Identité juridique : utile pour qu'une IA rattache le studio à la bonne
    // entité (l'association porte encore son nom historique, Entre2Danses).
    $asso = function_exists('wamv1_tarifs_association') ? wamv1_tarifs_association() : [];
    $statut = '- Statut : Association loi 1901';
    if (!empty($asso['nom'])) {
        $statut .= ' — ' . wam_llms_txt_texte($asso['nom']);
    }
    $lines[] = $statut;
    if (!empty($asso['rna'])) {
        $lines[] = '- N° RNA : ' . wam_llms_txt_texte($asso['rna']);
    }
    if (!empty($asso['siret'])) {
        $lines[] = '- SIRET : ' . wam_llms_txt_texte($asso['siret']);
    }

    $lines[] = '- Disciplines : danse moderne, danse de salon, heels, West coast swing, swing, street jazz, danse orientale, latino, éveil danse enfants';
    $lines[] = '- Prestations événementielles : chorégraphie mariage, EVJF/EVG, danse en entreprise, team building danse, TAP/NAP périscolaire';

    // Réseaux sociaux : uniquement ceux réellement renseignés.
    if (function_exists('wam_get_setting')) {
        $reseaux = [
            'url_instagram' => 'Instagram',
            'url_facebook' => 'Facebook',
            'url_youtube' => 'YouTube',
            'url_tiktok' => 'TikTok',
            'url_linkedin' => 'LinkedIn',
        ];
        foreach ($reseaux as $cle => $label) {
            $url = trim((string) wam_get_setting($cle));
            if ($url) {
                $lines[] = "- {$label} : {$url}";
            }
        }
    }

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
/**
 * Nettoie un texte destiné au llms.txt : balises retirées, entités décodées,
 * espaces normalisés.
 *
 * `wp_strip_all_tags()` seul laisse passer les entités (&#038;, &rsquo;…), qui
 * ressortaient telles quelles dans le fichier servi aux IA. Même correctif que
 * `wamv1_schema_text()` en v1.6.1.
 */
function wam_llms_txt_texte($text): string
{
    $text = wp_strip_all_tags((string) $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim((string) $text);
}

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

// On ne branche plus le llms.txt dans le robots.txt pour éviter les erreurs de syntaxe non standard.
// Le fichier reste accessible directement via /llms.txt.
