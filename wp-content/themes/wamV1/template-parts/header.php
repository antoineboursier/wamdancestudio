<?php
/**
 * Template Part : Header
 * Paramètres :
 *   $args['variant'] = 'home' | 'default'
 *
 * @package wamv1
 */
$variant = $args['variant'] ?? 'default';
$is_home = $variant === 'home';
?>
<header class="wam-header <?php echo $is_home ? 'wam-header--home' : ''; ?>" role="banner">
    <div class="wam-header__inner">

        <?php /* Bouton menu burger */ ?>
        <button class="wam-header__menu-btn" aria-label="<?php esc_attr_e('Ouvrir le menu', 'wamv1'); ?>"
            aria-expanded="false" aria-controls="wam-nav">
            <span class="wam-header__hamburger" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </span>
            <span>
                <?php esc_html_e('Menu', 'wamv1'); ?>
            </span>
        </button>

        <?php if (!$is_home): ?>
            <?php /* Logo WAM (texte) – masqué sur la variante home */ ?>
            <div class="wam-header__logo-wrapper">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/wam-logo.svg'); ?>"
                        alt="WAM Dance Studio" class="wam-header__logo" width="164" height="57">
                </a>
            </div>
            <div class="wam-header__logo-text-wrapper">
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/dance-studio.svg'); ?>" alt=""
                    aria-hidden="true" class="wam-header__logo-text" width="273" height="19">
            </div>
        <?php else: ?>
            <?php /* Espace vide à droite pour aligner le burger à gauche sur l'accueil */ ?>
            <div style="width: 164px;" aria-hidden="true"></div>
        <?php endif; ?>

    </div>
</header>
<?php /* Navigation (masquée par défaut, gérée par JS) */ ?>
<nav id="wam-nav" class="wam-nav" aria-label="<?php esc_attr_e('Navigation principale', 'wamv1'); ?>" hidden>
    <?php wp_nav_menu(array(
        'theme_location' => 'primary',
        'menu_class' => 'wam-nav__list',
        'fallback_cb' => false,
    )); ?>
</nav>