<?php
/**
 * Header WordPress — balise <head> complète
 * Appelé par get_header()
 *
 * @package wamv1
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php if ( ! has_site_icon() ) : ?>
        <link rel="icon" href="<?php echo esc_url( get_template_directory_uri() . '/favicon.png' ); ?>" sizes="any" />
        <link rel="apple-touch-icon" href="<?php echo esc_url( get_template_directory_uri() . '/favicon.png' ); ?>" />
    <?php endif; ?>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <ul class="skip-links-list" aria-label="<?php esc_attr_e('Liens d\'évitement', 'wamv1'); ?>">
        <li><a class="skip-link sr-only" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Aller à l\'accueil', 'wamv1'); ?></a></li>
        <li><a class="skip-link sr-only" href="#primary"><?php esc_html_e('Aller au contenu', 'wamv1'); ?></a></li>
        <li><a class="skip-link sr-only" href="#footer-action"><?php esc_html_e('Aller au pied de page', 'wamv1'); ?></a></li>
        <li><a class="skip-link sr-only" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Aller au contact', 'wamv1'); ?></a></li>
    </ul>
    <?php get_template_part('template-parts/site-header'); ?>