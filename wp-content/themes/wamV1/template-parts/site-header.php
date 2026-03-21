<?php
/**
 * Template Part : Header
 * Unified version: automatic logo display based on is_front_page()
 *
 * @package wamv1
 */
$is_home    = is_front_page();
$icon_dir   = get_template_directory_uri() . '/assets/images/';
$logo_src   = esc_url($icon_dir . 'wam_logo_header.svg');
$sub_src    = esc_url($icon_dir . 'dancestudio_header.svg');
?>
<header id="wam-header" class="wam-header <?php echo $is_home ? 'wam-header--home' : ''; ?>" role="banner">
    <?php if (!$is_home): ?>
        <div class="wam-header__separator" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="wam-header__inner">

        <button
            class="wam-header__menu-btn js-menu-toggle"
            aria-label="<?php esc_attr_e('Ouvrir le menu', 'wamv1'); ?>" aria-expanded="false"
            aria-controls="wam-nav-overlay">

            <span class="wam-header__hamburger" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </span>
            <span class="wam-header__menu-label"><?php esc_html_e('Menu', 'wamv1'); ?></span>
        </button>

        <?php if (!$is_home): ?>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="wam-header__logo-link">
                <img src="<?php echo $logo_src; ?>"
                    alt="WAM Dance Studio" class="wam-header__logo">
            </a>
            <img src="<?php echo $sub_src; ?>"
                alt="Dance Studio" class="wam-header__logo-subtitle">

        <?php else: ?>
            <div class="wam-header__logo-spacer" aria-hidden="true"></div>
        <?php endif; ?>

    </div>
</header>

<div id="wam-nav-overlay" class="wam-nav-overlay js-menu-overlay" aria-hidden="true"
    data-chevron-url="<?php echo esc_url(get_template_directory_uri() . '/assets/images/chevron_down.svg'); ?>">
    <div class="wam-nav__particles js-nav-particles"></div>

    <div class="wam-nav-panel">
        <button class="wam-nav__close js-menu-close btn-pause"
            aria-label="<?php esc_attr_e('Fermer le menu', 'wamv1'); ?>">
            <span class="btn-icon btn-icon--md"
                style="--icon-url: url('<?php echo esc_url(get_template_directory_uri() . '/assets/images/close.svg'); ?>');"></span>
        </button>

        <div class="wam-nav__header">
            <div class="wam-logo-menu">
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
