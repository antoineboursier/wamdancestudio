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
add_filter('embed_oembed_html', 'wamv1_youtube_facade', 10, 3);
function wamv1_youtube_facade($html, $url, $attr) {
    // Ne pas appliquer sur la homepage (demande utilisateur)
    if (is_front_page()) {
        return $html;
    }

    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) {
        return $html;
    }

    // Extraire l'ID de la vidéo
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    $video_id = $match[1] ?? null;

    if (!$video_id) {
        return $html;
    }

    $thumb_url = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
    
    ob_start();
    ?>
    <div class="wam-video-facade" data-video-id="<?php echo esc_attr($video_id); ?>" style="position:relative; cursor:pointer; background: #000; aspect-ratio: 16/9; overflow: hidden; border-radius: var(--wam-radius-md);">
        <img src="<?php echo esc_url($thumb_url); ?>" alt="YouTube Video" style="width:100%; height:100%; object-fit:cover; opacity: 0.8; transition: opacity 0.3s ease;" loading="lazy">
        <div class="wam-video-play-btn" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width: 68px; height: 48px; background: rgba(0,0,0,0.7); border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: background 0.3s ease;">
             <svg width="30" height="30" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7z"/></svg>
        </div>
        <script>
            (function() {
                const facade = document.currentScript.parentElement;
                facade.addEventListener('click', function() {
                    const id = this.dataset.videoId;
                    this.innerHTML = '<iframe width="100%" height="100%" src="https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="position:absolute; top:0; left:0; width:100%; height:100%;"></iframe>';
                }, { once: true });
                
                facade.addEventListener('mouseenter', function() {
                    this.querySelector('img').style.opacity = '1';
                    this.querySelector('.wam-video-play-btn').style.background = 'var(--wp--preset--color--accent-yellow, #FBD150)';
                    this.querySelector('svg').style.fill = '#000';
                });
                facade.addEventListener('mouseleave', function() {
                    this.querySelector('img').style.opacity = '0.8';
                    this.querySelector('.wam-video-play-btn').style.background = 'rgba(0,0,0,0.7)';
                    this.querySelector('svg').style.fill = 'white';
                });
            })();
        </script>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 2. Désactivation des scripts WP Embed (si non utilisés ailleurs)
 * Le script wp-embed.js est souvent inutile si on gère nos propres façades.
 */
add_action('wp_footer', function() {
    wp_deregister_script('wp-embed');
});
