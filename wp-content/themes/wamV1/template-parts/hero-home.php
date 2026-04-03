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

    <?php if (function_exists('wam_adresse_visible') && wam_adresse_visible()): ?>
        <address class="section-hero__address wam-adresse-globale">
            <p class="text-sm">
                <?php echo nl2br(esc_html(wam_adresse_lieu())); ?>
            </p>
        </address>
    <?php endif; ?>
</section>
