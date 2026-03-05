<?php
/**
 * Functions and definitions
 *
 * @package wamv1
 */

if (!function_exists('wamv1_setup')):
    function wamv1_setup()
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('editor-styles');
        add_theme_support('html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ));
    }
endif;
add_action('after_setup_theme', 'wamv1_setup');

/**
 * Enqueue scripts and styles.
 */
function wamv1_scripts()
{
    wp_enqueue_style('wamv1-style', get_stylesheet_uri(), array(), wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'wamv1_scripts');
