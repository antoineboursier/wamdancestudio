<?php
/**
 * Template Part : Section Vidéos — "We Are Move"
 * Grille collage Figma : 2 vidéos gauche / 2 droite, texte superposé centré
 *
 * @package wamv1
 */

$icon_dir = get_template_directory_uri() . '/assets/images/';

// Sources vidéos placeholder (à remplacer par ACF ou les médias WP)
$videos = array(
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
);
?>
<section class="section-videos relative w-full pb-6" aria-label="<?php esc_attr_e('Nos danses en vidéo', 'wamv1'); ?>">

    <div class="videos-wrapper relative grid gap-3 max-w-[920px] mx-auto items-stretch"
        style="grid-template-columns: 1fr auto 1fr; grid-template-rows: 1fr 1fr;">

        <?php /* Colonne gauche : 2 vidéos empilées + text */ ?>
        <div class="videos-col videos-col--left flex flex-col gap-3" style="grid-column:1; grid-row:1/3;">
            <div class="video-card rounded-wam-2xl overflow-hidden relative bg-wam-bg600 flex-1">
                <video src="<?php echo esc_url($videos[0]); ?>" class="w-full h-full object-cover block min-h-[160px]"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 1', 'wamv1'); ?>"></video>
            </div>
            <div class="videos-title flex flex-col items-center justify-center text-center pointer-events-none px-6 min-w-[200px]"
                style="grid-column:2; grid-row:1/3;" aria-hidden="true">
                <?php /* "We are" — .title-cool-lg */ ?>
                <span class="title-cool-lg text-wam-text block">We are</span>
                <?php /* "move" — Cholo Rhita 100px yellow — style inline car taille hors token */ ?>
                <span class="font-cholo text-wam-yellow block leading-none"
                    style="font-size: 6.25rem; font-style: normal;">move</span>
            </div>
            <div class="video-card rounded-wam-2xl overflow-hidden relative bg-wam-bg600 flex-1 max-w-[200px]">
                <video src="<?php echo esc_url($videos[2]); ?>" class="w-full h-full object-cover block min-h-[160px]"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 3', 'wamv1'); ?>"></video>
            </div>
        </div>

        <?php /* Colonne droite : 2 vidéos empilées */ ?>
        <div class="videos-col videos-col--right flex flex-col gap-3" style="grid-column:3; grid-row:1/3;">
            <?php /* Bouton pause vidéos */ ?>
            <div class="videos-pause-wrapper absolute -top-2 -right-2 z-10">
                <button id="pause-videos" class="btn-pause" aria-pressed="false" type="button">
                    <span class="btn-icon w-2.5 h-2.5"
                        style="--icon-url: url('<?php echo esc_url($icon_dir . 'pause.svg'); ?>');" aria-hidden="true"
                        id="pause-videos-icon">
                    </span>
                    <span><?php esc_html_e("Mettre en pause les vidéos", 'wamv1'); ?></span>
                </button>
            </div>
            <div class="video-card rounded-wam-2xl overflow-hidden relative bg-wam-bg600 flex-1">
                <video src="<?php echo esc_url($videos[1]); ?>" class="w-full h-full object-cover block min-h-[160px]"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 2', 'wamv1'); ?>"></video>
            </div>
            <div class="video-card rounded-wam-2xl overflow-hidden relative bg-wam-bg600 flex-1 mt-3">
                <video src="<?php echo esc_url($videos[3]); ?>" class="w-full h-full object-cover block min-h-[160px]"
                    autoplay muted loop playsinline
                    aria-label="<?php esc_attr_e('Vidéo de danse 4', 'wamv1'); ?>"></video>
            </div>
        </div>

    </div>

</section>