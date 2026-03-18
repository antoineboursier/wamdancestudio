<?php
/**
 * Template Part : Header
 * $args['variant'] = 'home' | 'default' | 'center-forced'
 *
 * home          → logo masqué (hero en dessous)
 * default       → logo WAM à droite
 * center-forced → logo WAM centre + DANCE STUDIO à droite (pages & articles)
 *
 * @package wamv1
 */
$variant = $args['variant'] ?? 'default';
$is_home = $variant === 'home';
$is_center = $variant === 'center-forced';
$icon_dir = get_template_directory_uri() . '/assets/images/';
?>
<header id="wam-header" class="wam-header <?php echo $is_home ? 'wam-header--home' : ''; ?> sticky top-0 z-[100] w-full" role="banner">

    <div class="flex items-center justify-between max-w-screen-2xl mx-auto px-24 py-8 gap-8">

        <button
            class="wam-header__menu-btn js-menu-toggle inline-flex items-center gap-4 px-6 py-4 rounded-wam-xl border border-wam-bg500 bg-wam-bg600 cursor-pointer shadow-wam-card transition-colors duration-200 text-wam-subtext hover:bg-wam-bg500 focus-visible:ring-2 focus-visible:ring-wam-green"
            aria-label="<?php esc_attr_e('Ouvrir le menu', 'wamv1'); ?>" aria-expanded="false"
            aria-controls="wam-nav-overlay">

            <span class="wam-header__hamburger flex flex-col gap-1 flex-shrink-0" aria-hidden="true">
                <span class="block h-1 w-8 rounded-full bg-wam-green"></span>
                <span class="block h-1 w-8 rounded-full bg-wam-yellow"></span>
                <span class="block h-1 w-8 rounded-full bg-wam-orange"></span>
                <span class="block h-1 w-8 rounded-full bg-wam-pink"></span>
            </span>
            <span class="text-wam-xs font-outfit tracking-wider"><?php esc_html_e('Menu', 'wamv1'); ?></span>
        </button>

        <?php if ($is_center): ?>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center transition-opacity hover:opacity-80">
                <img src="<?php echo esc_url($icon_dir . 'wam_logo_hero.svg'); ?>"
                    alt="WAM Dance Studio" class="h-[58px] w-auto">
            </a>
            <span class="font-outfit font-bold text-wam-text leading-none tracking-[0.2em] text-[13px] uppercase text-right" aria-hidden="true">
                DANCE<br>STUDIO
            </span>

        <?php elseif (!$is_home): ?>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center transition-opacity hover:opacity-80">
                <img src="<?php echo esc_url($icon_dir . 'wam_logo_hero.svg'); ?>"
                    alt="WAM Dance Studio" class="h-[58px] w-auto">
            </a>

        <?php else: ?>
            <div class="w-[164px]" aria-hidden="true"></div>
        <?php endif; ?>

    </div>
</header>

<div id="wam-nav-overlay" class="wam-nav-overlay js-menu-overlay" aria-hidden="true"
    data-chevron-url="<?php echo esc_url(get_template_directory_uri() . '/assets/images/chevron_down.svg'); ?>">
    <div class="wam-nav__particles js-nav-particles"></div>

    <div class="wam-nav-panel">
        <button class="wam-nav__close js-menu-close btn-pause"
            aria-label="<?php esc_attr_e('Fermer le menu', 'wamv1'); ?>">
            <span class="btn-icon w-6 h-6"
                style="--icon-url: url('<?php echo esc_url(get_template_directory_uri() . '/assets/images/close.svg'); ?>');"></span>
        </button>

        <div class="wam-nav__header">
            <div class="wam-logo-menu w-[280px] h-auto">
                <?php
                $logo_path = get_template_directory() . '/assets/images/logo_menu_wam.svg';
                if (file_exists($logo_path)) {
                    echo file_get_contents($logo_path);
                }
                ?>
            </div>
        </div>

        <nav class="wam-nav__menu" aria-label="<?php esc_attr_e('Menu principal', 'wamv1'); ?>">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'wam-nav__list',
                'walker' => new WAM_Nav_Walker(),
                'fallback_cb' => false,
            ));
            ?>
        </nav>

        <div class="wam-nav__socials">
            <a href="#" class="wam-nav__social-link" aria-label="Facebook">
                <span class="btn-icon"
                    style="--icon-url: url('<?php echo esc_url(get_template_directory_uri() . '/assets/images/logo_facebook.svg'); ?>');"></span>
            </a>
            <a href="#" class="wam-nav__social-link" aria-label="Instagram">
                <span class="btn-icon"
                    style="--icon-url: url('<?php echo esc_url(get_template_directory_uri() . '/assets/images/logo_instagram.svg'); ?>');"></span>
            </a>
        </div>
    </div>
</div>