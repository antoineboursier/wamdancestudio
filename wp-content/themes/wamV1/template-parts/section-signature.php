<?php
/**
 * Template part: Section Signature "A bientôt sur le parquet"
 * 
 * @package wamv1
 */

$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<section id="section-signature" class="section-signature">
    <div class="section-signature__container">
        
        <!-- Image avec effet 4 couches -->
        <div class="section-signature__photo-wrapper wam-effect-4-layers photo-wrapper">
            <img src="<?php echo esc_url($icon_dir . 'photo_team.png'); ?>" 
                 alt="<?php esc_attr_e('L’équipe WAM', 'wamv1'); ?>" 
                 class="section-signature__photo">
            <?php wamv1_the_photo_overlay(); ?>
        </div>

        <!-- Texte signature -->
        <div class="section-signature__content">
            <p class="section-signature__text-mallia title-sign-lg">
                <?php _e('A bientôt<br>sur le parquet !', 'wamv1'); ?>
            </p>
            <p class="section-signature__text-graphical title-cool-lg color-yellow">
                <?php _e('L’équipe wam', 'wamv1'); ?>
            </p>
        </div>

    </div>
</section>
