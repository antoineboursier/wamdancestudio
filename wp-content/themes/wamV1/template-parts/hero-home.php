<?php
/**
 * Template Part : Hero Home
 * @package wamv1
 */
$logo_url = get_template_directory_uri() . '/assets/images/wam_logo_hero.svg';
?>
<section id="section-hero-home" class="section-hero"
    aria-label="<?php esc_attr_e('Bienvenue au WAM Dance Studio', 'wamv1'); ?>">

    <h1 class="section-hero__logo">
        <span class="sr-only"><?php esc_html_e("École de danse à Villeneuve d'Ascq - WAM Dance Studio", 'wamv1'); ?></span>
        <img src="<?php echo esc_url($logo_url); ?>" alt="WAM Dance Studio" width="400" height="181"
            class="section-hero__logo-img" loading="eager" fetchpriority="high" aria-hidden="true">
    </h1>

    <?php if (function_exists('wam_adresse_visible') && wam_adresse_visible()): ?>
        <address class="section-hero__address wam-adresse-globale">
            <p class="text-sm">
                <?php echo nl2br(esc_html(wam_adresse_lieu())); ?>
            </p>
        </address>
    <?php endif; ?>
</section>
