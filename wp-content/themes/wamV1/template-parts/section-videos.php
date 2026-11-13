<?php
/**
 * Template Part : Section Vidéos — "We Are Move"
 * Grille collage Figma : 2 vidéos gauche / 2 droite, texte superposé centré
 * 
 * Version YouTube directe : chaque vidéo tourne en boucle, en entier.
 *
 * @package wamv1
 */

$icon_dir = get_template_directory_uri() . '/assets/images/';
$site_url = home_url();

// Vidéos YouTube, lues en entier et en boucle.
$video_configs = [
    ['id' => 'ZPHTfwnDMGM'], // Vidéo 1 (Haut Gauche, .video-card--v1)
    ['id' => 'GpingOeeRjQ'], // Vidéo 2 (Haut Droite, .video-card--v2)
    ['id' => '0HY6vrDpQ98'], // Vidéo 3 (Bas Gauche, .video-card--v3)
    ['id' => 'TRG8vF-pOqc'], // Vidéo 4 (Bas Droite, .video-card--v4)
];

/**
 * Génère l'URL d'embed YouTube avec les paramètres requis :
 * - autoplay=1 & mute=1 (nécessaire pour l'auto-lecture)
 * - loop=1 & playlist={id} (requis pour boucler sur une seule vidéo)
 * - controls=0 & modestbranding=1 (aspect background épuré)
 * - enablejsapi=1 (pour le bouton pause via home.js)
 */
function wam_get_youtube_embed_url($config, $site_url)
{
    $id = $config['id'];
    $params = [
        'autoplay' => 1,
        'mute' => 1,
        'loop' => 1,
        'playlist' => $id,
        'controls' => 0,
        'modestbranding' => 1,
        'enablejsapi' => 1,
        'rel' => 0,
        'origin' => $site_url,
        'iv_load_policy' => 3,
        'showinfo' => 0
    ];
    return 'https://www.youtube.com/embed/' . $id . '?' . http_build_query($params);
}
?>

<section class="section-videos" aria-label="<?php esc_attr_e('Nos danses en vidéo', 'wamv1'); ?>">

    <div class="videos-wrapper">

        <?php /* Colonne gauche : 2 vidéos empilées + titre "We are move" */ ?>
        <div class="videos-col videos-col--left">
            <div class="video-card video-card--v1">
                <iframe id="yt-player-0" class="youtube-player"
                    src="<?php echo esc_url(wam_get_youtube_embed_url($video_configs[0], $site_url)); ?>"
                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen
                    title="<?php esc_attr_e('Vidéo de danse 1', 'wamv1'); ?>"></iframe>
            </div>
            <div class="videos-title" aria-hidden="true">
                <span class="is-style-title-cool-lg">We are</span>
                <span class="is-style-title-cool-lg color-yellow videos-move-xl">move</span>
            </div>
            <div class="video-card video-card--v3">
                <iframe id="yt-player-2" class="youtube-player"
                    src="<?php echo esc_url(wam_get_youtube_embed_url($video_configs[2], $site_url)); ?>"
                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen
                    title="<?php esc_attr_e('Vidéo de danse 3', 'wamv1'); ?>"></iframe>
            </div>
        </div>

        <?php /* Colonne droite : 2 vidéos empilées + bouton pause */ ?>
        <div class="videos-col videos-col--right">
            <?php /* Bouton pause — positionné en absolu dans la colonne */ ?>
            <div class="videos-pause-wrapper">
                <button id="pause-videos" class="btn-pause" aria-pressed="false" type="button">
                    <span class="btn-icon btn-icon--xs"
                        style="--icon-url: url('<?php echo esc_url($icon_dir . 'pause.svg'); ?>');" aria-hidden="true"
                        id="pause-videos-icon">
                    </span>
                    <span><?php esc_html_e("Mettre en pause les vidéos", 'wamv1'); ?></span>
                </button>
            </div>
            <div class="video-card video-card--v2">
                <iframe id="yt-player-1" class="youtube-player"
                    src="<?php echo esc_url(wam_get_youtube_embed_url($video_configs[1], $site_url)); ?>"
                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen
                    title="<?php esc_attr_e('Vidéo de danse 2', 'wamv1'); ?>"></iframe>
            </div>
            <div class="video-card video-card--v4">
                <iframe id="yt-player-3" class="youtube-player"
                    src="<?php echo esc_url(wam_get_youtube_embed_url($video_configs[3], $site_url)); ?>"
                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen
                    title="<?php esc_attr_e('Vidéo de danse 4', 'wamv1'); ?>"></iframe>
            </div>
        </div>

    </div>

</section>