<?php
/**
 * Optimisations Performance & Éco-conception
 *
 * @package wamv1
 */

/**
 * 1. Façade YouTube légère (Click-to-load)
 * Remplace l'iframe oEmbed lourde par une image et un script de chargement au clic.
 */
add_filter('oembed_result', 'wamv1_youtube_facade', 10, 3);
function wamv1_youtube_facade($html, $url, $attr) {
    // Ne pas appliquer sur la homepage (demande utilisateur)
    if (is_front_page()) {
        return $html;
    }

    if (strpos($url, 'vimeo.com') !== false) {
        if (strpos($html, 'quality=') === false) {
            $html = preg_replace('/src=["\']([^"\']+)["\']/', 'src="$1&quality=1080p"', $html);
        }
        return $html;
    }

    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) {
        return $html;
    }

    // Extraire l'ID de la vidéo (y compris pour les Shorts)
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=|shorts/)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    $video_id = $match[1] ?? null;

    if (!$video_id) {
        return $html;
    }

    $is_shorts = (strpos($url, '/shorts/') !== false);
    $aspect_ratio = $is_shorts ? '9/16' : '16/9';

    $thumb_url = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
    $sd_url    = "https://img.youtube.com/vi/{$video_id}/sddefault.jpg";
    $hq_url    = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";

    // Le titre porté par l'iframe oEmbed d'origine sert de libellé accessible :
    // « Lire la vidéo » seul ne distingue pas trois vidéos sur une même page.
    $video_title = '';
    if (preg_match('/title=["\']([^"\']*)["\']/', $html, $m_title)) {
        $video_title = html_entity_decode($m_title[1], ENT_QUOTES, 'UTF-8');
    }
    $play_label = $video_title ? 'Lire la vidéo : ' . $video_title : 'Lire la vidéo';

    // Comportement (clic, clavier, survol, réinitialisation) : assets/js/video-facade.js,
    // enfilé globalement dans functions.php — surtout pas ici : ce filtre ne
    // rejoue pas sur les intégrations servies depuis le cache postmeta `_oembed_*`.
    ob_start();
    ?>
    <div class="wam-video-facade"
         role="button"
         tabindex="0"
         aria-label="<?php echo esc_attr($play_label); ?>"
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-title="<?php echo esc_attr($video_title); ?>"
         data-play-label="<?php echo esc_attr($play_label); ?>"
         style="position:relative; cursor:pointer; background: #000; aspect-ratio: <?php echo $aspect_ratio; ?>; overflow: hidden; border-radius: inherit; will-change: transform; isolation: isolate;">
        <img src="<?php echo esc_url($thumb_url); ?>"
             onerror="if(this.src.indexOf('maxresdefault')!==-1){this.src='<?php echo esc_url($sd_url); ?>';}else if(this.src.indexOf('sddefault')!==-1){this.src='<?php echo esc_url($hq_url); ?>';}else{this.style.visibility='hidden';}"
             alt=""
             style="width:100%; height:100%; object-fit:cover; opacity: 0.8; transition: opacity 0.3s ease;"
             loading="lazy"
             decoding="async">
        <div class="wam-video-play-btn" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width: 68px; height: 48px; background: rgba(0,0,0,0.7); color: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: background 0.3s ease, color 0.3s ease; pointer-events: none;">
             <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 1 bis. Marquage des embeds verticaux (YouTube Shorts) dans le contenu.
 *
 * Gutenberg ajoute `wp-embed-aspect-16-9` sur les vidéos paysage, mais
 * n'émet AUCUNE classe de ratio pour les Shorts : le markup d'un Short et
 * celui d'une vidéo paysage sont sinon strictement identiques. Le seul
 * signal disponible est le `/shorts/` de l'URL, qui n'existe qu'ici, côté
 * PHP — d'où ce filtre, qui pose la classe que le CSS ne peut pas déduire.
 *
 * Appliqué sur `render_block` et non sur `the_content` : le rendu de bloc
 * est aussi ce que l'aperçu de l'éditeur consomme, et le filtre reste sans
 * effet sur les contenus n'ayant pas d'embed.
 *
 * @param string $block_content HTML rendu du bloc.
 * @param array  $block         Bloc analysé (attributs compris).
 * @return string
 */
add_filter('render_block', 'wamv1_mark_portrait_embeds', 10, 2);
function wamv1_mark_portrait_embeds($block_content, $block) {
    if (empty($block['blockName']) || $block['blockName'] !== 'core/embed') {
        return $block_content;
    }

    $url = $block['attrs']['url'] ?? '';
    if (strpos($url, '/shorts/') === false) {
        return $block_content;
    }

    // Idempotent : ne pas repositionner la classe si elle est déjà là.
    if (strpos($block_content, 'wam-embed--portrait') !== false) {
        return $block_content;
    }

    return preg_replace(
        '/class="([^"]*\bwp-block-embed\b[^"]*)"/',
        'class="$1 wam-embed--portrait"',
        $block_content,
        1
    );
}

/**
 * 2. Désactivation des scripts WP Embed (si non utilisés ailleurs)
 * Le script wp-embed.js est souvent inutile si on gère nos propres façades.
 */
add_action('wp_footer', function() {
    wp_deregister_script('wp-embed');
});

/**
 * 3. Optimisation du Back-Office (Heartbeat & Widgets)
 * Allège la charge CPU en limitant les requêtes automatiques du navigateur en admin.
 */

// Limiter la fréquence de Heartbeat API (60s au lieu de 15s)
add_filter('heartbeat_settings', function($settings) {
    $settings['interval'] = 60;
    return $settings;
});

// Désactiver XML-RPC (sécurité et perf si non utilisé)
add_filter('xmlrpc_enabled', '__return_false');

// Nettoyer le tableau de bord (Dashboard) pour un chargement plus rapide
add_action('wp_dashboard_setup', function() {
    remove_meta_box('dashboard_primary', 'dashboard', 'side');   // News WP
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side'); // Brouillon rapide
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal'); // D'un coup d'oeil
    remove_meta_box('dashboard_activity', 'dashboard', 'normal');  // Activité
});

/**
 * 5. Invalidation des transients de contenu
 * Les blocs mis en cache (footer, grille des profs, planning) sont
 * invalidés dès qu'un contenu concerné est modifié, publié ou supprimé,
 * plutôt que d'attendre l'expiration de 24h.
 */
add_action('save_post', 'wamv1_flush_content_transients');
add_action('deleted_post', 'wamv1_flush_content_transients');
function wamv1_flush_content_transients($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    switch (get_post_type($post_id)) {
        case 'cours':
            delete_transient('wamv1_planning_data_v1');
            delete_transient('wamv1_footer_cours_grouped_v2');
            // Le produit WooCommerce porteur de l'adhésion est déduit des cours
            delete_transient('wamv1_tarifs_produit_cours_v1');
            break;
        case 'stages':
            delete_transient('wamv1_footer_stages_v3');
            // Prix d'entrée affiché sur /tarifs/
            delete_transient('wamv1_tarifs_stage_min_v1');
            break;
        case 'wam_membre':
            delete_transient('wamv1_teachers_grid_v1');
            break;
    }
}

// Les termes cat_cours impactent la légende du planning et le groupement du footer
foreach (array('created_cat_cours', 'edited_cat_cours', 'delete_cat_cours') as $wamv1_cat_hook) {
    add_action($wamv1_cat_hook, function () {
        delete_transient('wamv1_planning_data_v1');
        delete_transient('wamv1_footer_cours_grouped_v2');
    });
}

/**
 * 6. Optimisation LCP (Largest Contentful Paint)
 * Précharge l'image Hero dans le head et lui donne une priorité haute.
 */

// Injection du <link rel="preload"> dans le head
add_action('wp_head', 'wamv1_preload_lcp', 1);
function wamv1_preload_lcp() {
    if (!is_singular() && !is_front_page()) {
        return;
    }

    $image_url = '';
    $image_size = 'wam-page-thumbnail';

    if (is_front_page()) {
        // Sur la home, le LCP est le logo hero (SVG)
        $image_url = get_template_directory_uri() . '/assets/images/wam_logo_hero.svg';
        echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="high">';
        return;
    }

    if (is_singular() && has_post_thumbnail()) {
        // Déterminer la taille d'image selon le template utilisé
        if (is_singular('cours')) {
            $image_size = 'wam-card';
        } elseif (is_singular('stages')) {
            $image_size = 'wam-stage-portrait';
        } elseif (is_singular('wam_membre')) {
            $image_size = 'wam-portrait';
        } elseif (is_singular('post')) {
            $image_size = 'wamv1-page-hero';
        }

        $image_src = wp_get_attachment_image_src(get_post_thumbnail_id(), $image_size);
        if ($image_src) {
            $image_url = $image_src[0];
            echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="high">';
        }
    }
}
