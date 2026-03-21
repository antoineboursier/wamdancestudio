<?php
/**
 * Template Part : Hero Home
 * @package wamv1
 */
$logo_url = get_template_directory_uri() . '/assets/images/wam_logo_hero.svg';
?>
<section id="section-hero-home" class="section-hero"
    aria-label="<?php esc_attr_e('Bienvenue au WAM Dance Studio', 'wamv1'); ?>">

    <div class="section-hero__logo">
        <img src="<?php echo esc_url($logo_url); ?>" alt="WAM Dance Studio" width="400" height="181"
            class="section-hero__logo-img" loading="eager">
    </div>

    <address class="section-hero__address">
        <p class="text-sm">
            <?php esc_html_e('202 rue Jean Jaurès', 'wamv1'); ?>
        </p>
        <p class="text-xs">
            <?php esc_html_e("Villeneuve d'Ascq", 'wamv1'); ?>
        </p>
    </address>
</section>
