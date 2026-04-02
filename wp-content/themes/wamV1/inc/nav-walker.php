<?php
/**
 * Custom Nav Walker for WAM Dance Studio
 * Generates the menu structure for the slide-over overlay.
 */
class WAM_Nav_Walker extends Walker_Nav_Menu
{

    // Start Level: <ul> for sub-menus
    function start_lvl(&$output, $depth = 0, $args = null)
    {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"wam-nav__sub\">\n";
    }

    // Start Element: <li> and <a>
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $classes[] = 'wam-nav__item';
        $classes[] = 'wam-nav__item-' . $item->ID;

        // Current item indicator
        $is_current = in_array('current-menu-item', $classes) || in_array('current-page-ancestor', $classes);
        $link_class = $depth === 0 ? 'wam-nav__link' : 'wam-nav__sub-link';
        if ($is_current) {
            $link_class .= ' wam-nav__link--current';
        }

        $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth));
        
        // --- GESTION ACCESSIBILITÉ SÉPARATEUR ---
        $is_separator = in_array('nav-separator', $classes);
        $aria_attr    = $is_separator ? ' role="separator" aria-hidden="true"' : '';
        
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';

        $output .= $indent . '<li' . $class_names . $aria_attr . '>';

        // Si c'est un séparateur, on ne génère pas de lien <a>
        if ($is_separator) {
            $output .= apply_filters('walker_nav_menu_start_el', '', $item, $depth, $args);
            return;
        }
        // --- FIN GESTION SÉPARATEUR ---

        $atts = array();
        $atts['title'] = !empty($item->attr_title) ? $item->attr_title : '';
        $atts['target'] = !empty($item->target) ? $item->target : '';
        $atts['rel'] = !empty($item->xfn) ? $item->xfn : '';
        $atts['href'] = !empty($item->url) ? $item->url : '';
        $atts['class'] = $link_class;
        if ($is_current) {
            $atts['aria-current'] = 'page';
        }

        $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (!empty($value)) {
                $value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

        $title = apply_filters('the_title', $item->title, $item->ID);

        $item_output = $args->before;
        $item_output .= '<a' . $attributes . '>';
        $item_output .= $args->link_before . $title . $args->link_after;

        $item_output .= '</a>';
        $item_output .= $args->after;

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }
}
