<?php
/**
 * Template Part : Section Vidéos — "We Are Move"
 * Grille collage Figma : 2 vidéos gauche / 2 droite, texte superposé centré
 *
 * @package wamv1
 */

$icon_dir = get_template_directory_uri() . '/assets/images/';

// Sources vidéos depuis ACF (champs video_home_1..4, type URL, sur la page d'accueil)
// TODO : créer les champs ACF video_home_1..4 dans l'admin (Groupe sur la page d'accueil).
// Fallback : vidéos de test Google tant que les champs ACF ne sont pas remplis.
$home_id = get_option('page_on_front');
$has_acf = function_exists('get_field');
$videos = [
    $has_acf ? (get_field('video_home_1', $home_id) ?: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4') : 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
    $has_acf ? (get_field('video_home_2', $home_id) ?: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4') : 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
    $has_acf ? (get_field('video_home_3', $home_id) ?: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4') : 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
    $has_acf ? (get_field('video_home_4', $home_id) ?: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4') : 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
];

?>
<section class="section-videos" aria-label="<?php esc_attr_e('Nos danses en vidéo', 'wamv1'); ?>">

    <div class="videos-wrapper">

        <?php /* Colonne gauche : 2 vidéos empilées + titre "We are move" */ ?>
        <div class="videos-col videos-col--left">
            <div class="video-card video-card--v1">
                <video src="<?php echo esc_url($videos[0]); ?>"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 1', 'wamv1'); ?>"></video>
            </div>
            <div class="videos-title" aria-hidden="true">
                <?php /* "We are" — .title-cool-lg */ ?>
                <span class="title-cool-lg videos-title__we-are">We are</span>
                <?php /* "move" — Cholo Rhita 100px yellow — style inline car taille hors token */ ?>
                <span class="videos-title__move" style="font-size: 6.25rem;">move</span>
            </div>
            <div class="video-card video-card--v3">
                <video src="<?php echo esc_url($videos[2]); ?>"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 3', 'wamv1'); ?>"></video>
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
                <video src="<?php echo esc_url($videos[1]); ?>"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 2', 'wamv1'); ?>"></video>
            </div>
            <div class="video-card video-card--v4">
                <video src="<?php echo esc_url($videos[3]); ?>"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 4', 'wamv1'); ?>"></video>
            </div>
        </div>

    </div>

</section>
