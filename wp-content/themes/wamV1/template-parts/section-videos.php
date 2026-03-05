<?php
/**
 * Template Part : Section Vidéos
 * 4 vidéos placeholder avec titre "We are Move" en superposition
 * Bouton de pause réutilisable
 *
 * @package wamv1
 */

// Sources vidéos placeholder (lorem ipsum)
$placeholder_videos = array(
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
    'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
);
?>
<section class="section-videos" aria-label="<?php esc_attr_e('Nos danses en vidéo', 'wamv1'); ?>">
    <div class="videos-grid" style="position:relative;">
        <?php foreach ($placeholder_videos as $index => $src): ?>
            <div class="video-card">
                <video src="<?php echo esc_url($src); ?>" autoplay muted loop playsinline
                    aria-label="<?php printf(esc_attr__('Vidéo de danse %d', 'wamv1'), $index + 1); ?>"></video>
            </div>
        <?php endforeach; ?>

        <div class="videos-title" aria-hidden="true">
            <span class="videos-title__we-are">We are</span>
            <span class="videos-title__move">Move</span>
        </div>
    </div>

    <div style="display:flex; justify-content:flex-end; max-width:1000px; margin:8px auto 0; padding:8px;">
        <?php get_template_part('template-parts/btn', 'pause', array(
            'id' => 'pause-videos',
            'label' => __('Mettre en pause les vidéos', 'wamv1'),
        )); ?>
    </div>
</section>