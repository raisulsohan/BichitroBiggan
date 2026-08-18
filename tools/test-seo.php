<?php
/**
 * এসইও লেয়ারের ফাংশনাল টেস্ট — ওয়ার্ডপ্রেস ছাড়াই।
 *
 * inc/seo-frontend.php ও inc/seo-meta-box.php এর <head> আউটপুট আসলেই
 * চালিয়ে দেখে: JSON-LD বৈধ কি না, ক্যানোনিকাল ঠিক আছে কি না, টাইটেল
 * ফিল্টার কাস্টম টাইটেল না থাকলে চুপ থাকে কি না, robots ভুল করে
 * noindex বসায় কি না।
 *
 *     php tools/test-seo.php
 *
 * @package BichitroBiggan
 */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( 'CLI only.' );
}

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

define( 'BB_THEME_DIR', dirname( __DIR__ ) . '/Website/bichitro-biggan' );
define( 'ABSPATH', BB_THEME_DIR . '/' );
define( 'DATE_W3C_FALLBACK', 'Y-m-d\TH:i:sP' );

/* =========================================================================
 * টেস্ট স্টেট — প্রতিটি সিনারিও এই গ্লোবালগুলো বদলে দেয়
 * ======================================================================= */

$GLOBALS['bb_state'] = array(
	'view'      => 'single',   // single | front | archive | search | 404
	'post_meta' => array(),
	'paged'     => 1,
);

function bb_t_view() {
	return $GLOBALS['bb_state']['view'];
}

/* =========================================================================
 * ন্যূনতম ওয়ার্ডপ্রেস মক
 * ======================================================================= */

class WP_Post {
	public $ID = 42;
	public $post_title = 'কোয়ান্টাম জড়াজড়ি আসলে কী?';
	public $post_content = 'কোয়ান্টাম এনট্যাঙ্গেলমেন্ট নিয়ে বিস্তারিত আলোচনা। [shortcode] দুটি কণা <b>যুক্ত</b> থাকে।';
	public $post_type = 'post';
	public $post_author = 7;
}

class WP_Term {
	public $term_id = 3;
	public $name = 'কোয়ান্টাম বিজ্ঞান';
	public $slug = 'quantum';
}

class WP_User {
	public $ID = 7;
}

class WP_Error {
	public $msg;
	public function __construct( $m = '' ) {
		$this->msg = $m;
	}
}

function is_wp_error( $t ) {
	return $t instanceof WP_Error;
}

/* --- কন্ডিশনাল ট্যাগ --- */
function is_feed() { return false; }
function is_404() { return '404' === bb_t_view(); }
function is_search() { return 'search' === bb_t_view(); }
function is_trackback() { return false; }
function is_embed() { return false; }
function is_admin() { return false; }
function is_singular() { return 'single' === bb_t_view(); }
function is_front_page() { return 'front' === bb_t_view(); }
function is_home() { return 'front' === bb_t_view(); }
function is_archive() { return 'archive' === bb_t_view(); }
function is_category() { return 'archive' === bb_t_view(); }
function is_tag() { return false; }
function is_tax() { return false; }
function is_author() { return false; }
function is_post_type_archive() { return false; }
function is_day() { return false; }
function is_month() { return false; }
function is_year() { return false; }

/* --- সাইট ইনফো --- */
function get_bloginfo( $k = '' ) {
	$map = array(
		'name'        => 'বিচিত্র বিজ্ঞান',
		'description' => 'বাংলা বিজ্ঞান সাময়িকী',
		'language'    => 'bn-BD',
	);
	return isset( $map[ $k ] ) ? $map[ $k ] : '';
}
function get_locale() { return 'bn_BD'; }
function home_url( $p = '' ) { return 'https://bichitrobiggan.com' . $p; }
function get_option( $k ) { return 'page_for_posts' === $k ? 0 : ''; }

/* --- কোয়েরি --- */
function get_query_var( $k ) {
	if ( 'paged' === $k ) { return $GLOBALS['bb_state']['paged']; }
	return '';
}
function get_queried_object() {
	if ( 'single' === bb_t_view() ) { return new WP_Post(); }
	if ( 'archive' === bb_t_view() ) { return new WP_Term(); }
	return null;
}
function get_queried_object_id() { return 42; }

/* --- পোস্ট ডাটা --- */
function get_permalink( $p = null ) { return 'https://bichitrobiggan.com/quantum-entanglement/'; }
function get_the_title( $id ) { return 'ব্লগ'; }
function get_the_excerpt( $id ) { return 'সব লেখা'; }
function get_post_meta( $id, $key, $single = false ) {
	$m = $GLOBALS['bb_state']['post_meta'];
	return isset( $m[ $key ] ) ? $m[ $key ] : '';
}
function get_the_date( $f = '', $p = null ) { return '2026-03-11T09:30:00+06:00'; }
function get_the_modified_date( $f = '', $p = null ) { return '2026-04-02T18:05:00+06:00'; }
function get_the_category( $id = 0 ) { return array( new WP_Term() ); }
function has_post_thumbnail( $p = null ) { return 'single' === bb_t_view(); }
function get_post_thumbnail_id( $p = null ) { return 99; }
function wp_get_attachment_image_src( $id, $size = '' ) {
	return array( 'https://bichitrobiggan.com/wp-content/uploads/quantum.jpg', 1200, 630 );
}
function get_site_icon_url( $s = 512 ) { return 'https://bichitrobiggan.com/icon-512.png'; }

/* --- টার্ম, লেখক, আর্কাইভ --- */
function get_term_link( $t ) { return 'https://bichitrobiggan.com/category/quantum/'; }
function term_description( $t = null ) { return 'কোয়ান্টাম বিজ্ঞানের লেখা।'; }
function get_the_archive_title() { return '<span>ক্যাটাগরি:</span> কোয়ান্টাম বিজ্ঞান'; }
function get_the_archive_description() { return ''; }
function get_author_posts_url( $id ) { return 'https://bichitrobiggan.com/author/raisul/'; }
function get_the_author_meta( $f, $id = 0 ) { return 'display_name' === $f ? 'রাইসুল ইসলাম' : ''; }
function get_post_type_archive_link( $t ) { return ''; }
function get_day_link( $y, $m, $d ) { return ''; }
function get_month_link( $y, $m ) { return ''; }
function get_year_link( $y ) { return ''; }
function get_pagenum_link( $n ) { return 'https://bichitrobiggan.com/category/quantum/page/' . (int) $n . '/'; }

/* --- থিম / হুক / এস্কেপিং --- */
function get_theme_mod( $k, $d = false ) {
	$map = array(
		'custom_logo'           => 0,
		'bb_youtube_url'        => 'https://www.youtube.com/@bigganbichitro',
		'bb_footer_youtube_url' => 'https://www.youtube.com/@bigganbichitro',
	);
	return isset( $map[ $k ] ) ? $map[ $k ] : $d;
}
function add_action() {}
function add_filter() {}
function remove_action() {}
function add_meta_box() {}
function current_user_can() { return true; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function wp_create_nonce() { return 'nonce'; }
function admin_url( $p = '' ) { return 'https://bichitrobiggan.com/wp-admin/' . $p; }
function apply_filters( $tag, $value ) { return $value; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function __( $s, $d = '' ) { return $s; }
function esc_html__( $s, $d = '' ) { return $s; }
function esc_attr__( $s, $d = '' ) { return $s; }
function _e( $s, $d = '' ) { echo $s; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function strip_shortcodes( $s ) { return preg_replace( '/\[[^\]]*\]/', '', (string) $s ); }
function wp_trim_words( $s, $n = 55, $more = '...' ) {
	$w = preg_split( '/\s+/u', trim( (string) $s ), -1, PREG_SPLIT_NO_EMPTY );
	return count( $w ) <= $n ? implode( ' ', $w ) : implode( ' ', array_slice( $w, 0, $n ) ) . $more;
}
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function get_post( $p = null ) { return new WP_Post(); }
function wp_kses_post( $v ) { return $v; }
function get_edit_post_link( $id = 0 ) { return ''; }
function number_format_i18n( $n ) { return (string) $n; }
function get_post_types() { return array(); }
function wp_verify_nonce() { return true; }
function wp_nonce_field() {}
function update_post_meta() { return true; }
function delete_post_meta() { return true; }
function wp_send_json_success() {}
function wp_send_json_error() {}
function get_posts() { return array(); }
function wp_next_scheduled() { return false; }
function selected( $a, $b, $e = true ) { return ''; }
function checked( $a, $b, $e = true ) { return ''; }
function get_sample_permalink() { return array( '', '' ); }
function urlencode_deep( $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }

if ( ! defined( 'DATE_W3C' ) ) {
	define( 'DATE_W3C', DATE_W3C_FALLBACK );
}

/* =========================================================================
 * থিমের আসল কোড লোড
 * ======================================================================= */

// live-search.php আগে — সেখানেই bb_str_sub()/bb_str_pos() মাল্টিবাইট হেল্পার আছে।
require_once BB_THEME_DIR . '/inc/live-search.php';
require_once BB_THEME_DIR . '/inc/seo-meta-box.php';
require_once BB_THEME_DIR . '/inc/seo-frontend.php';

/* =========================================================================
 * ছোট্ট অ্যাসার্শন হেল্পার
 * ======================================================================= */

$GLOBALS['bb_pass'] = 0;
$GLOBALS['bb_fail'] = 0;

function bb_ok( $cond, $label ) {
	if ( $cond ) {
		$GLOBALS['bb_pass']++;
		echo "  PASS  {$label}\n";
	} else {
		$GLOBALS['bb_fail']++;
		echo "  FAIL  {$label}\n";
	}
}

function bb_capture( $fn ) {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}

function bb_scenario( $view, array $meta = array(), $paged = 1 ) {
	$GLOBALS['bb_state']['view']      = $view;
	$GLOBALS['bb_state']['post_meta'] = $meta;
	$GLOBALS['bb_state']['paged']     = $paged;
}

/* =========================================================================
 * ১. সিঙ্গুলার পোস্ট — কাস্টম SEO টাইটেল সহ
 * ======================================================================= */

echo "\n[১] সিঙ্গুলার পোস্ট (কাস্টম SEO টাইটেল ও বর্ণনা সেট করা)\n";

bb_scenario(
	'single',
	array(
		'bb_seo_title'       => '%title% %sep% %sitename%',
		'bb_seo_description' => 'কোয়ান্টাম এনট্যাঙ্গেলমেন্ট সহজ ভাষায়।',
		'bb_focus_keyphrase' => 'কোয়ান্টাম জড়াজড়ি',
	)
);

$title = bb_seo_document_title( '' );
bb_ok( 'কোয়ান্টাম জড়াজড়ি আসলে কী? — বিচিত্র বিজ্ঞান' === $title, "<title> কাস্টম SEO টাইটেল ব্যবহার করছে: {$title}" );

$head = bb_capture( 'bb_seo_head_meta' );
bb_ok( 1 === substr_count( $head, 'rel="canonical"' ), 'ঠিক একটি ক্যানোনিকাল ট্যাগ' );
bb_ok( false !== strpos( $head, 'og:type" content="article"' ), 'og:type = article' );
bb_ok( false !== strpos( $head, 'article:published_time' ), 'article:published_time আছে' );
bb_ok( false !== strpos( $head, 'quantum.jpg' ), 'og:image ফিচার্ড ইমেজ ব্যবহার করছে' );
bb_ok( false !== strpos( $head, 'name="keywords"' ), 'focus keyphrase থেকে keywords' );
bb_ok( false === strpos( $head, '<b>' ), 'বর্ণনায় কোনো HTML ট্যাগ লিক করেনি' );

$schema = bb_capture( 'bb_seo_output_schema' );
$json   = trim( str_replace( array( '<script type="application/ld+json">', '</script>' ), '', $schema ) );
$data   = json_decode( $json, true );

bb_ok( JSON_ERROR_NONE === json_last_error(), 'JSON-LD বৈধ JSON (' . json_last_error_msg() . ')' );
bb_ok( is_array( $data ) && 'https://schema.org' === ( $data['@context'] ?? '' ), '@context = schema.org' );

$types = array();
foreach ( (array) ( $data['@graph'] ?? array() ) as $node ) {
	$types[] = $node['@type'] ?? '';
}

bb_ok( in_array( 'NewsArticle', $types, true ), 'NewsArticle নোড আছে (' . implode( ', ', $types ) . ')' );
bb_ok( in_array( 'Organization', $types, true ), 'Organization নোড আছে' );
bb_ok( in_array( 'BreadcrumbList', $types, true ), 'BreadcrumbList নোড আছে' );
bb_ok( 1 === substr_count( $schema, 'application/ld+json' ), 'পেজে একটিমাত্র ld+json স্ক্রিপ্ট' );

$article = null;
$crumbs  = null;
foreach ( (array) ( $data['@graph'] ?? array() ) as $node ) {
	if ( 'NewsArticle' === ( $node['@type'] ?? '' ) ) { $article = $node; }
	if ( 'BreadcrumbList' === ( $node['@type'] ?? '' ) ) { $crumbs = $node; }
}

bb_ok( is_array( $article ) && ! empty( $article['datePublished'] ), 'NewsArticle-এ datePublished' );
bb_ok( is_array( $article ) && ! empty( $article['author']['name'] ), 'NewsArticle-এ author' );
bb_ok( is_array( $article ) && bb_str_len( $article['headline'] ) <= 110, 'headline ≤ ১১০ ক্যারেক্টার' );
bb_ok( is_array( $article ) && ( $article['wordCount'] ?? 0 ) > 0, 'wordCount বাংলা লেখাতেও শূন্য নয় (' . ( $article['wordCount'] ?? 0 ) . ')' );
bb_ok( is_array( $crumbs ) && 3 === count( $crumbs['itemListElement'] ), 'ব্রেডক্রাম্ব: হোম ➔ ক্যাটাগরি ➔ লেখা' );

/* =========================================================================
 * ২. স্ক্রিপ্ট-ব্রেকআউট সুরক্ষা
 * ======================================================================= */

echo "\n[২] JSON-LD ইনজেকশন সুরক্ষা\n";

bb_scenario(
	'single',
	array( 'bb_seo_description' => 'ভাঙার চেষ্টা </script><script>alert(1)</script>' )
);

$evil = bb_capture( 'bb_seo_output_schema' );
bb_ok( 1 === substr_count( $evil, '</script>' ), 'কনটেন্টের </script> স্ক্রিপ্ট ট্যাগ ভাঙতে পারেনি' );
bb_ok( false === strpos( $evil, '<script>alert' ), 'ইনজেক্ট করা <script> এস্কেপ হয়েছে' );

/* =========================================================================
 * ৩. কাস্টম টাইটেল না থাকলে আগের ব্যবহার অপরিবর্তিত থাকা
 * ======================================================================= */

echo "\n[৩] রিগ্রেশন গার্ড — কাস্টম টাইটেল না থাকলে কিছুই বদলায় না\n";

bb_scenario( 'single', array() );
bb_ok( '' === bb_seo_document_title( '' ), 'pre_get_document_title ফাঁকা ফেরত দিচ্ছে (কোর নিজের টাইটেল বানাবে)' );

$head = bb_capture( 'bb_seo_head_meta' );
bb_ok( false !== strpos( $head, 'name="description"' ), 'কনটেন্ট থেকে ফলব্যাক ডেসক্রিপশন তৈরি হয়েছে' );
bb_ok( false === strpos( $head, 'name="keywords"' ), 'keyphrase না থাকলে keywords ট্যাগ নেই' );

/* =========================================================================
 * ৪. হোমপেজ ও আর্কাইভ — আগে যেখানে কিছুই ছিল না
 * ======================================================================= */

echo "\n[৪] হোমপেজ ও আর্কাইভ কভারেজ\n";

bb_scenario( 'front' );
$head = bb_capture( 'bb_seo_head_meta' );
bb_ok( false !== strpos( $head, 'name="description"' ), 'হোমপেজে মেটা ডেসক্রিপশন' );
bb_ok( false !== strpos( $head, 'og:type" content="website"' ), 'হোমপেজে og:type = website' );
bb_ok( false !== strpos( $head, 'icon-512.png' ), 'হোমপেজে ফলব্যাক og:image (সাইট আইকন)' );
bb_ok( 1 === substr_count( $head, 'rel="canonical"' ), 'হোমপেজে একটি ক্যানোনিকাল' );
bb_ok( false === strpos( $head, 'article:published_time' ), 'হোমপেজে article:* ট্যাগ নেই' );

bb_scenario( 'archive' );
$ctx = bb_seo_get_context();
bb_ok( 'https://bichitrobiggan.com/category/quantum/' === $ctx['canonical'], 'আর্কাইভ ক্যানোনিকাল = টার্ম লিংক' );
bb_ok( false === strpos( $ctx['title'], '<span>' ), 'আর্কাইভ টাইটেল থেকে HTML সরানো হয়েছে' );
bb_ok( '' !== $ctx['description'], 'আর্কাইভে বর্ণনা আছে' );

bb_scenario( 'archive', array(), 3 );
$ctx = bb_seo_get_context();
bb_ok( false !== strpos( $ctx['canonical'], '/page/3/' ), 'পেজ ৩-এর ক্যানোনিকাল পেজ ১-এ পাঠায় না' );

/* =========================================================================
 * ৫. সার্চ ও 404 — কোনো ক্যানোনিকাল/OG যাওয়া উচিত নয়
 * ======================================================================= */

echo "\n[৫] সার্চ ও 404 পেজে চুপ থাকা\n";

bb_scenario( 'search' );
bb_ok( '' === bb_capture( 'bb_seo_head_meta' ), 'সার্চ পেজে কোনো মেটা ট্যাগ নেই' );
bb_ok( '' === bb_capture( 'bb_seo_output_schema' ), 'সার্চ পেজে কোনো স্কিমা নেই' );

bb_scenario( '404' );
bb_ok( '' === bb_capture( 'bb_seo_head_meta' ), '404 পেজে কোনো মেটা ট্যাগ নেই' );

/* =========================================================================
 * ৬. robots ডিরেক্টিভ
 * ======================================================================= */

echo "\n[৬] robots ডিরেক্টিভ\n";

bb_scenario( 'single', array() );
$robots = bb_seo_robots( array( 'max-image-preview' => 'large' ) );
bb_ok( empty( $robots['noindex'] ), 'সাধারণ পোস্ট ভুল করে noindex হয়নি' );
bb_ok( -1 === $robots['max-snippet'], 'max-snippet:-1 যোগ হয়েছে' );
bb_ok( -1 === $robots['max-video-preview'], 'max-video-preview:-1 যোগ হয়েছে' );

bb_scenario( 'single', array( 'bb_seo_noindex' => '1' ) );
$robots = bb_seo_robots( array( 'max-image-preview' => 'large' ) );
bb_ok( true === ( $robots['noindex'] ?? false ), 'স্পষ্ট noindex সেট করা পোস্টে noindex বসেছে' );
bb_ok( ! isset( $robots['max-image-preview'] ), 'noindex পেজে প্রিভিউ ডিরেক্টিভ সরানো হয়েছে' );

bb_scenario( 'single', array( '_yoast_wpseo_meta-robots-noindex' => '1' ) );
$robots = bb_seo_robots( array() );
bb_ok( true === ( $robots['noindex'] ?? false ), 'Yoast-এর পুরনো noindex সেটিং এখনো কাজ করছে' );

/* =========================================================================
 * --dump দিলে আসল <head> আউটপুট দেখায় (চোখে দেখে যাচাই করার জন্য)
 *    সেকশন ৭-এর আগে, কারণ ওখানে WPSEO_VERSION define হলে আর কিছুই ছাপে না।
 * ======================================================================= */

if ( in_array( '--dump', (array) ( $argv ?? array() ), true ) ) {
	foreach ( array( 'single', 'front', 'archive' ) as $view ) {
		bb_scenario( $view, 'single' === $view ? array( 'bb_seo_description' => 'কোয়ান্টাম এনট্যাঙ্গেলমেন্ট সহজ ভাষায়।' ) : array() );
		echo "\n" . str_repeat( '=', 60 ) . "\n  {$view}\n" . str_repeat( '=', 60 ) . "\n";
		echo bb_capture( 'bb_seo_head_meta' );
		echo bb_capture( 'bb_seo_output_schema' );
	}
	echo "\n";
}

/* =========================================================================
 * ৭. এসইও প্লাগইন সক্রিয় হলে সম্পূর্ণ চুপ থাকা (ডুপ্লিকেট ট্যাগ প্রতিরোধ)
 *    এটি সবার শেষে, কারণ কনস্ট্যান্ট একবার define করলে আর মোছা যায় না।
 * ======================================================================= */

echo "\n[৭] Yoast সক্রিয় থাকলে আমাদের কিছুই আউটপুট হয় না\n";

define( 'WPSEO_VERSION', '99.9' );

bb_scenario( 'single', array( 'bb_seo_title' => 'কাস্টম টাইটেল' ) );

bb_ok( true === bb_seo_plugin_active(), 'প্লাগইন শনাক্ত হয়েছে' );
bb_ok( false === bb_seo_should_output(), 'should_output = false' );
bb_ok( '' === bb_capture( 'bb_seo_head_meta' ), 'কোনো মেটা ট্যাগ যায়নি' );
bb_ok( '' === bb_capture( 'bb_seo_output_schema' ), 'কোনো স্কিমা যায়নি' );
bb_ok( '' === bb_seo_document_title( '' ), '<title> স্পর্শ করা হয়নি' );

$robots = bb_seo_robots( array( 'max-image-preview' => 'large' ) );
bb_ok( array( 'max-image-preview' => 'large' ) === $robots, 'robots অ্যারে অপরিবর্তিত' );

/* =========================================================================
 * ফলাফল
 * ======================================================================= */

echo "\n" . str_repeat( '-', 60 ) . "\n";
printf( "পাস: %d | ফেল: %d\n", $GLOBALS['bb_pass'], $GLOBALS['bb_fail'] );

exit( $GLOBALS['bb_fail'] > 0 ? 1 : 0 );
