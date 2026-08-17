<?php
define('ABSPATH', __DIR__);
function get_template_directory() { return __DIR__; }
function get_template_directory_uri() { return 'http://localhost'; }
function add_action() {}
function remove_action() {}
function add_filter() {}
function remove_filter() {}
function get_theme_mod() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function is_admin() { return true; }
function add_meta_box() {}
function current_user_can() { return true; }
function wp_deregister_style() {}
function is_user_logged_in() {}
function wp_create_nonce() {}
function admin_url() {}
// mock some more if needed
class Walker_Nav_Menu {}
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'functions.php';
    echo "NO FATAL ERROR IN SCRIPT INCLUSION.";
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage();
}
