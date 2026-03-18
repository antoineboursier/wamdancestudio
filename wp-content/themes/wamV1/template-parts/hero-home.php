<?php
/**
 * Template Part : Hero Home
 * @package wamv1
 */
$logo_url = get_template_directory_uri() . '/assets/images/wam_logo_hero.svg';
$pattern_url = get_template_directory_uri() . '/assets/images/bg_pattern_color_black.svg';
?>
<section id="section-hero-home" class="section-hero relative flex flex-col items-center justify-center gap-12 py-14 overflow-hidden w-full"
    style="background-image: url('<?php echo esc_url($pattern_url); ?>'); background-size: 600px auto; background-repeat: repeat; background-position: center;"
    aria-label="<?php esc_attr_e('Bienvenue au WAM Dance Studio', 'wamv1'); ?>">

    <div class="relative z-10">
        <img src="<?php echo esc_url($logo_url); ?>" alt="WAM Dance Studio" width="400" height="181"
            class="w-[400px] max-w-[90vw] h-auto" loading="eager">
    </div>

    <address class="relative z-10 flex flex-col items-center gap-1 text-center not-italic">
        <p class="text-wam-sm text-wam-subtext font-outfit m-0">
            <?php esc_html_e('202 rue Jean Jaurès', 'wamv1'); ?>
        </p>
        <p class="text-wam-xs text-wam-subtext font-outfit m-0">
            <?php esc_html_e("Villeneuve d'Ascq", 'wamv1'); ?>
        </p>
    </address>
</section>