<?php
require_once dirname(__FILE__, 4) . '/wp-load.php';

header('Content-Type: text/plain');

$q = new WP_Query([
    'post_type' => 'cours',
    'posts_per_page' => -1
]);

echo "Total cours: " . $q->found_posts . "\n\n";

foreach ($q->posts as $p) {
    echo "ID: " . $p->ID . " - " . $p->post_title . "\n";
    $meta = get_post_meta($p->ID, 'prof_cours', true);
    echo "Raw prof_cours meta: ";
    var_dump($meta);
    echo "Author: " . $p->post_author . "\n";
    echo "--------------------------\n";
}
