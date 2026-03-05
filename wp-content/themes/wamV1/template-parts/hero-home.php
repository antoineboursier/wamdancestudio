<?php
/**
 * Template Part : Hero Home
 *
 * @package wamv1
 */
?>
<section class="section-hero" aria-label="<?php esc_attr_e('Bienvenue au WAM Dance Studio', 'wamv1'); ?>">
    <div class="section-hero__logo">
        <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/wam-logo-full.svg'); ?>"
            alt="WAM Dance Studio" width="400" height="181">
    </div>
    <address class="section-hero__address">
        <p class="street">202 rue Jean Jaurès</p>
        <p class="city">Villeneuve d'Ascq</p>
    </address>
</section>