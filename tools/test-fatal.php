<?php
/**
 * ডেভেলপার স্মোক-টেস্ট — functions.php প্যারস/লোড হয় কি না যাচাই করে।
 *
 * এটি থিমের অংশ নয়। ওয়ার্ডপ্রেস ছাড়াই কমান্ড লাইন থেকে চালাতে হয়:
 *
 *     php tools/test-fatal.php
 *
 * শুধু CLI থেকে চলবে — ওয়েব সার্ভারে অ্যাক্সেস করা যাবে না।
 */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( 'CLI only.' );
}

define( 'BB_THEME_DIR', dirname( __DIR__ ) );

define( 'ABSPATH', BB_THEME_DIR . '/' );

/* ---- ন্যূনতম ওয়ার্ডপ্রেস API মক ---- */
function get_template_directory() { return BB_THEME_DIR; }
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
// প্রয়োজনে আরও মক যোগ করুন।
class Walker_Nav_Menu {}

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

try {
	require_once BB_THEME_DIR . '/functions.php';
	echo "OK — functions.php লোড হয়েছে, কোনো ফ্যাটাল এরর নেই।\n";
	exit( 0 );
} catch ( Throwable $e ) {
	echo 'FATAL ERROR: ' . $e->getMessage() . "\n";
	exit( 1 );
}
