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
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <?php get_template_part('template-parts/site-header'); ?>