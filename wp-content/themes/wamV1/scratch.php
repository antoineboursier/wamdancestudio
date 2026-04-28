<?php
// Mock WP functions for syntax check
function add_action() {}
function add_filter() {}
function get_template_directory() { return "."; }
require_once "inc/cleanup.php";
?>
