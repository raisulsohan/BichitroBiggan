<?php
/**
 * Bichitro Biggan theme functions.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BB_VERSION', '7.9.0' );

/**
 * The built copy of an asset, when there is one and it is not stale.
 *
 * npm run build writes style.min.css and theme.min.js. If a build has never
 * been run, or a source file has been edited since, the source is served
 * instead — a stale minified file is worse than an unminified one.
 *
 * @param string $relative Path from the theme root, e.g. '/style.css'.
 * @return string
 */
function bb_asset_path( $relative ) {
	$dir   = get_template_directory();
	$built = preg_replace( '/\.(css|js)$/', '.min.$1', $relative );

	if ( $built !== $relative
		&& file_exists( $dir . $built )
		&& file_exists( $dir . $relative )
		&& filemtime( $dir . $built ) >= filemtime( $dir . $relative ) ) {
		return $built;
	}

	return $relative;
}

/**
 * Cache-busting version for an asset.
 *
 * The file's modification time is appended, so an edited stylesheet or script
 * always gets a fresh URL even when the theme version itself does not change.
 * Without this a caching plugin or CDN happily serves the previous build.
 *
 * @param string $abs_path Absolute path to the asset.
 */
function bb_file_version( $abs_path ) {
	$time = file_exists( $abs_path ) ? filemtime( $abs_path ) : 0;

	return $time ? BB_VERSION . '.' . $time : BB_VERSION;
}

/* -------------------------------------------------------------------------
 * Performance & Bloat Cleanup
 * ---------------------------------------------------------------------- */

/**
 * Clean up WordPress head bloat and disable unused features.
 */
function bb_cleanup_head() {
	// Remove emoji scripts & styles (reduces HTTP requests & CSS bloat).
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	// Remove generator, RSD & manifest link tags.
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
}
add_action( 'init', 'bb_cleanup_head' );

/**
 * Remove dashicons on frontend for non-logged-in visitors (saves ~30KB CSS).
 */
function bb_clean_frontend_styles() {
	if ( ! is_user_logged_in() ) {
		wp_deregister_style( 'dashicons' );
	}
}
add_action( 'wp_enqueue_scripts', 'bb_clean_frontend_styles', 100 );

/**
 * Defer non-critical theme scripts for faster First Contentful Paint.
 */
function bb_defer_scripts( $tag, $handle, $src ) {
	if ( in_array( $handle, array( 'bb-script' ), true ) ) {
		if ( false === strpos( $tag, 'defer' ) ) {
			$tag = str_replace( ' src', ' defer="defer" src', $tag );
		}
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'bb_defer_scripts', 10, 3 );

/**
 * Ensure async image decoding for smoother rendering.
 */
function bb_async_images( $attr ) {
	if ( ! isset( $attr['decoding'] ) ) {
		$attr['decoding'] = 'async';
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'bb_async_images' );

/* -------------------------------------------------------------------------
 * 1. Theme setup
 * ---------------------------------------------------------------------- */

function bb_setup() {
	load_theme_textdomain( 'bichitro-biggan', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 56,
		'width'       => 320,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	register_nav_menus( array(
		'primary' => __( 'প্রধান মেন্যু', 'bichitro-biggan' ),
	) );

	// Image sizes matching the design.
	add_image_size( 'bb-hero', 900, 720, true );    // large mosaic panels
	add_image_size( 'bb-card', 600, 380, true );    // standard cards
	add_image_size( 'bb-thumb', 360, 240, true );   // grid thumbs
	add_image_size( 'bb-small', 160, 120, true );   // list thumbnails
	add_image_size( 'bb-og', 1200, 630, true );    // Facebook / Twitter card

	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'bb_setup' );

function bb_content_width() {
	$GLOBALS['content_width'] = 760;
}
add_action( 'after_setup_theme', 'bb_content_width', 0 );

/* -------------------------------------------------------------------------
 * 2. Assets
 * ---------------------------------------------------------------------- */

function bb_enqueue_assets() {
	wp_enqueue_style(
		'bb-fonts',
		get_template_directory_uri() . '/assets/css/fonts.css',
		array(),
		bb_file_version( get_template_directory() . '/assets/css/fonts.css' )
	);

	$bb_stylesheet = bb_asset_path( '/style.css' );

	wp_enqueue_style(
		'bb-style',
		get_template_directory_uri() . $bb_stylesheet,
		array(),
		bb_file_version( get_template_directory() . $bb_stylesheet )
	);

	$bb_theme_js = bb_asset_path( '/assets/js/theme.js' );

	wp_enqueue_script(
		'bb-script',
		get_template_directory_uri() . $bb_theme_js,
		array(),
		bb_file_version( get_template_directory() . $bb_theme_js ),
		true
	);
	wp_script_add_data( 'bb-script', 'strategy', 'defer' );

	wp_localize_script( 'bb-script', 'BBData', array(
		'email'      => bb_get_contact_email(),
		'copiedText' => __( 'ইমেইল কপি হয়েছে', 'bichitro-biggan' ),
		'modal'      => (bool) get_theme_mod( 'bb_enable_modal', true ),
		'ajaxList'   => (bool) get_theme_mod( 'bb_enable_ajax_list', true ),
		'loading'    => __( 'লোড হচ্ছে…', 'bichitro-biggan' ),
		'failed'     => __( 'লেখাটি আনা যায়নি।', 'bichitro-biggan' ),
		'liveSearch' => (bool) get_theme_mod( 'bb_live_search', true ),
		'searchUrl'  => esc_url_raw( rest_url( 'bb/v1/search' ) ),
		'searching'  => __( 'খোঁজা হচ্ছে…', 'bichitro-biggan' ),
		'noResults'  => __( 'কিছু পাওয়া যায়নি', 'bichitro-biggan' ),
		'resultOne'  => __( '১টি ফলাফল', 'bichitro-biggan' ),
		'resultMany' => __( '%s টি ফলাফল', 'bichitro-biggan' ),
		'hint'       => __( 'অন্তত দুটি অক্ষর লিখুন', 'bichitro-biggan' ),
		'ajaxUrl'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
		'viewUrl'    => esc_url_raw( rest_url( 'bb/v1/view' ) ),
		'copiedLink' => __( 'লিংক কপি হয়েছে', 'bichitro-biggan' ),
		'shareText'  => __( 'শেয়ার করুন', 'bichitro-biggan' ),
		'tickerStop' => __( 'খবরের স্ক্রল থামান', 'bichitro-biggan' ),
		'tickerPlay' => __( 'খবরের স্ক্রল চালু করুন', 'bichitro-biggan' ),
		'themeDark'  => __( 'ডার্ক মোড চালু করুন', 'bichitro-biggan' ),
		'themeLight' => __( 'লাইট মোড চালু করুন', 'bichitro-biggan' ),
		'katexCss'   => BB_KATEX_CSS,
		'katexJs'    => BB_KATEX_JS,
		'katexAuto'  => BB_KATEX_AUTO,
	) );

	wp_add_inline_style( 'bb-style', bb_dynamic_css() );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'bb_enqueue_assets' );

/* KaTeX renders the equations in physics and quantum pieces. Pinned, and
   asked for only on posts that actually contain maths. */
define( 'BB_KATEX_VERSION', '0.16.11' );
define( 'BB_KATEX_CSS', 'https://cdn.jsdelivr.net/npm/katex@' . BB_KATEX_VERSION . '/dist/katex.min.css' );
define( 'BB_KATEX_JS', 'https://cdn.jsdelivr.net/npm/katex@' . BB_KATEX_VERSION . '/dist/katex.min.js' );
define( 'BB_KATEX_AUTO', 'https://cdn.jsdelivr.net/npm/katex@' . BB_KATEX_VERSION . '/dist/contrib/auto-render.min.js' );

/**
 * Does this post contain anything KaTeX would need to render?
 *
 * @param int $post_id Post to check.
 * @return bool
 */
function bb_post_has_math( $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );

	return (bool) preg_match( '/\$\$|\\\(|\\\[/', $content );
}

/**
 * Load KaTeX on an article that has equations. Ordinary prose never pays
 * for it; the popup loads it on demand instead (see theme.js).
 */
function bb_enqueue_katex() {
	if ( ! is_singular( 'post' ) || ! bb_post_has_math( get_queried_object_id() ) ) {
		return;
	}

	wp_enqueue_style( 'katex', BB_KATEX_CSS, array(), BB_KATEX_VERSION );
	wp_enqueue_script( 'katex', BB_KATEX_JS, array(), BB_KATEX_VERSION, true );
	wp_enqueue_script( 'katex-auto-render', BB_KATEX_AUTO, array( 'katex' ), BB_KATEX_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'bb_enqueue_katex' );

/**
 * Preload the one face a Bengali page cannot draw a word without.
 *
 * The stylesheet is local now, so the old preload-and-swap dance around
 * Google's is gone; the font file itself is what is worth fetching early.
 */
function bb_preload_fonts() {
	printf(
		'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
		esc_url( get_template_directory_uri() . '/assets/fonts/noto-sans-bengali-400-bengali.woff2' )
	);
}
add_action( 'wp_head', 'bb_preload_fonts', 2 );

/**
 * Preload the Largest Contentful Paint (LCP) image in <head> for fast mobile rendering.
 */
function bb_preload_lcp_image() {
	$thumb_id = 0;
	$size     = 'bb-hero';

	if ( is_front_page() || is_home() ) {
		$slot1_id = absint( get_theme_mod( 'bb_hero_slot_1', 0 ) );
		$cat1_id  = absint( get_theme_mod( 'bb_hero_cat_1', 0 ) );

		if ( $slot1_id > 0 ) {
			$thumb_id = get_post_thumbnail_id( $slot1_id );
		} elseif ( $cat1_id > 0 ) {
			$q = new WP_Query( array(
				'cat'                 => $cat1_id,
				'posts_per_page'      => 1,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			) );
			if ( $q->have_posts() ) {
				$thumb_id = get_post_thumbnail_id( $q->posts[0]->ID );
			}
		} else {
			$q = new WP_Query( array(
				'posts_per_page'      => 1,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			) );
			if ( $q->have_posts() ) {
				$thumb_id = get_post_thumbnail_id( $q->posts[0]->ID );
			}
		}
	} elseif ( is_singular( 'post' ) ) {
		$post_id  = get_the_ID();
		$thumb_id = get_post_thumbnail_id( $post_id );
		$size     = 'full';
	}

	if ( ! $thumb_id ) {
		return;
	}

	$src = wp_get_attachment_image_url( $thumb_id, $size );
	if ( ! $src ) {
		return;
	}

	$srcset = wp_get_attachment_image_srcset( $thumb_id, $size );
	$sizes  = wp_get_attachment_image_sizes( $thumb_id, $size );

	if ( $srcset && $sizes ) {
		printf(
			'<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" fetchpriority="high">' . "\n",
			esc_url( $src ),
			esc_attr( $srcset ),
			esc_attr( $sizes )
		);
	} else {
		printf(
			'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
			esc_url( $src )
		);
	}
}
add_action( 'wp_head', 'bb_preload_lcp_image', 1 );

/**
 * Live preview for the postMessage settings (the logo sliders).
 */
function bb_customize_preview_js() {
	wp_enqueue_script(
		'bb-customizer-preview',
		get_template_directory_uri() . '/assets/js/customizer-preview.js',
		array( 'customize-preview', 'jquery' ),
		bb_file_version( get_template_directory() . '/assets/js/customizer-preview.js' ),
		true
	);

	$map = array();
	foreach ( bb_layout_settings() as $key => $cfg ) {
		$map[ $key ] = array(
			'var'  => $cfg['var'],
			'min'  => $cfg['min'],
			'unit' => isset( $cfg['unit'] ) ? $cfg['unit'] : 'px',
		);
	}

	wp_localize_script( 'bb-customizer-preview', 'BBLayout', array( 'map' => $map ) );
}
add_action( 'customize_preview_init', 'bb_customize_preview_js' );

/**
 * Customizer controls JS for dynamic category filtering.
 */
function bb_customize_controls_js() {
	wp_enqueue_script(
		'bb-customizer-controls',
		get_template_directory_uri() . '/assets/js/customizer-controls.js',
		array( 'jquery', 'customize-controls' ),
		BB_VERSION,
		true
	);
	wp_localize_script( 'bb-customizer-controls', 'bbPostCatMap', bb_get_post_cat_map() );

	bb_enqueue_post_select();
}
add_action( 'customize_controls_enqueue_scripts', 'bb_customize_controls_js' );

/**
 * Media picker and settings helpers for admin.
 */
function bb_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php', 'toplevel_page_bb-theme-settings' ), true ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'bb-admin',
		get_template_directory_uri() . '/assets/js/admin.js',
		array( 'jquery' ),
		BB_VERSION,
		true
	);
	wp_localize_script( 'bb-admin', 'bbPostCatMap', bb_get_post_cat_map() );

	bb_enqueue_post_select();
}

/**
 * The search box over the post pickers, used by the settings page and the
 * Customizer alike.
 */
function bb_enqueue_post_select() {
	if ( ! function_exists( 'bb_post_search_data' ) ) {
		return;
	}

	wp_enqueue_script(
		'bb-post-select',
		get_template_directory_uri() . '/assets/js/post-select.js',
		array(),
		bb_file_version( get_template_directory() . '/assets/js/post-select.js' ),
		true
	);

	wp_localize_script( 'bb-post-select', 'bbPostSearch', bb_post_search_data() );
}
add_action( 'admin_enqueue_scripts', 'bb_admin_assets' );

/**
 * Customizer-driven CSS variables (logo sizing).
 */
function bb_dynamic_css() {
	$header        = (int) get_theme_mod( 'bb_logo_height', 70 );
	$sticky        = (int) get_theme_mod( 'bb_logo_height_sticky', 34 );
	$footer        = (int) get_theme_mod( 'bb_logo_height_footer', 44 );
	$text          = (int) get_theme_mod( 'bb_logo_text_size', 38 );
	$tagline_font  = (int) get_theme_mod( 'bb_tagline_font_size', 12 );
	$tagline_width = (int) get_theme_mod( 'bb_tagline_max_width', 520 );
	$yt_icon_size  = (int) get_theme_mod( 'bb_yt_icon_size', 24 );
	$yt_font_size  = (int) get_theme_mod( 'bb_yt_font_size', 12 );

	$css = sprintf(
		'--bb-logo-h:%dpx;--bb-logo-h-sticky:%dpx;--bb-logo-h-footer:%dpx;--bb-logo-text:%dpx;--bb-tagline-font:%dpx;--bb-tagline-max-w:%dpx;--bb-yt-icon-sz:%dpx;--bb-yt-font-sz:%dpx;',
		max( 12, $header ),
		max( 12, $sticky ),
		max( 12, $footer ),
		max( 10, $text ),
		max( 9, $tagline_font ),
		max( 150, $tagline_width ),
		max( 14, $yt_icon_size ),
		max( 9, $yt_font_size )
	);

	foreach ( bb_layout_settings() as $key => $cfg ) {
		$value = (int) get_theme_mod( $key, $cfg['default'] );
		$value = max( $cfg['min'], min( $cfg['max'], $value ) );
		$unit  = isset( $cfg['unit'] ) ? $cfg['unit'] : 'px';
		$css  .= $cfg['var'] . ':' . $value . $unit . ';';
	}

	return ':root{' . $css . '}';
}

/**
 * Every homepage block size, as a CSS variable the Customizer can drive live.
 *
 * key => var name, range and default. Grouped by the Customizer section they
 * appear in so the panel and the CSS never drift apart.
 */
function bb_layout_settings() {
	return array(
		// Hero Mosaic
		'bb_hero_height'          => array( 'var' => '--bb-hero-h', 'default' => 520, 'min' => 300, 'max' => 800, 'unit' => 'px', 'section' => 'bb_layout_hero', 'label' => __( 'Hero Total Height (মোট উচ্চতা)', 'bichitro-biggan' ) ),
		'bb_hero_left_width'      => array( 'var' => '--bb-hero-left-w', 'default' => 56, 'min' => 30, 'max' => 75, 'unit' => '%', 'section' => 'bb_layout_hero', 'label' => __( 'Left Main Hero Width (১নং বড় হিরোর প্রস্থ)', 'bichitro-biggan' ) ),
		'bb_hero_top_height'      => array( 'var' => '--bb-hero-top-h', 'default' => 50, 'min' => 25, 'max' => 75, 'unit' => '%', 'section' => 'bb_layout_hero', 'label' => __( 'Right Top Card Height (২নং ওপরের কার্ডের উচ্চতা)', 'bichitro-biggan' ) ),
		'bb_hero_bot_left_width'  => array( 'var' => '--bb-hero-bot-left-w', 'default' => 50, 'min' => 20, 'max' => 80, 'unit' => '%', 'section' => 'bb_layout_hero', 'label' => __( 'Bottom Left Card Width (৩নং কার্ডের প্রস্থ অনুপাত)', 'bichitro-biggan' ) ),

		// Block 1
		'bb_feature_width'        => array( 'var' => '--bb-feature-w', 'default' => 240, 'min' => 140, 'max' => 480, 'section' => 'bb_layout_block1', 'label' => __( 'Featured Column Width', 'bichitro-biggan' ) ),
		'bb_feature_image_height' => array( 'var' => '--bb-feature-img-h', 'default' => 116, 'min' => 100, 'max' => 400, 'section' => 'bb_layout_block1', 'label' => __( 'Featured Card Image Height', 'bichitro-biggan' ) ),
		'bb_list_thumb_width'     => array( 'var' => '--bb-list-thumb-w', 'default' => 90, 'min' => 56, 'max' => 180, 'section' => 'bb_layout_block1', 'label' => __( 'Side List Thumbnail Width', 'bichitro-biggan' ) ),
		'bb_list_thumb_height'    => array( 'var' => '--bb-list-thumb-h', 'default' => 64, 'min' => 40, 'max' => 130, 'section' => 'bb_layout_block1', 'label' => __( 'Side List Thumbnail Height', 'bichitro-biggan' ) ),
		'bb_grid_card_height'     => array( 'var' => '--bb-grid-card-h', 'default' => 116, 'min' => 70, 'max' => 240, 'section' => 'bb_layout_block1', 'label' => __( 'Right Grid Card Height', 'bichitro-biggan' ) ),

		// Blocks 2 and 3
		'bb_card_image_height'    => array( 'var' => '--bb-card-img-h', 'default' => 192, 'min' => 110, 'max' => 400, 'section' => 'bb_layout_block23', 'label' => __( 'Standard Card Image Height', 'bichitro-biggan' ) ),
		'bb_side_image_height'    => array( 'var' => '--bb-side-img-h', 'default' => 224, 'min' => 120, 'max' => 440, 'section' => 'bb_layout_block23', 'label' => __( 'Right Column Big Card Height', 'bichitro-biggan' ) ),
		'bb_wide_thumb_width'     => array( 'var' => '--bb-wide-thumb-w', 'default' => 210, 'min' => 100, 'max' => 340, 'section' => 'bb_layout_block23', 'label' => __( 'Wide Row Thumbnail Width', 'bichitro-biggan' ) ),
		'bb_wide_thumb_height'    => array( 'var' => '--bb-wide-thumb-h', 'default' => 140, 'min' => 70, 'max' => 260, 'section' => 'bb_layout_block23', 'label' => __( 'Wide Row Thumbnail Height', 'bichitro-biggan' ) ),

		// Blocks 4, 5 and the dark strip
		'bb_tall_height'          => array( 'var' => '--bb-tall-h', 'default' => 224, 'min' => 140, 'max' => 460, 'section' => 'bb_layout_block45', 'label' => __( 'Tall Card Height', 'bichitro-biggan' ) ),
		'bb_grid_gap'             => array( 'var' => '--bb-grid-gap', 'default' => 24, 'min' => 0, 'max' => 48, 'section' => 'bb_layout_block45', 'label' => __( 'Three Column Grid Gap', 'bichitro-biggan' ) ),

		// All posts + archives
		'bb_all_image_height'     => array( 'var' => '--bb-all-img-h', 'default' => 192, 'min' => 110, 'max' => 400, 'section' => 'bb_layout_lists', 'label' => __( 'All Posts Card Image Height', 'bichitro-biggan' ) ),
		'bb_archive_image_height' => array( 'var' => '--bb-archive-img-h', 'default' => 176, 'min' => 100, 'max' => 380, 'section' => 'bb_layout_lists', 'label' => __( 'Category Page Card Image Height', 'bichitro-biggan' ) ),

		// Article Reading Modal Popup
		'bb_modal_max_width'      => array( 'var' => '--bb-modal-max-w', 'default' => 860, 'min' => 500, 'max' => 1400, 'unit' => 'px', 'section' => 'bb_layout_modal', 'label' => __( 'Popup Reading Window Max Width', 'bichitro-biggan' ) ),
		'bb_modal_max_height'     => array( 'var' => '--bb-modal-max-h', 'default' => 90, 'min' => 50, 'max' => 98, 'unit' => 'vh', 'section' => 'bb_layout_modal', 'label' => __( 'Popup Reading Window Max Height', 'bichitro-biggan' ) ),
	);
}

function bb_body_classes( $classes ) {
	$classes[] = 'bb-body';

	if ( is_singular( 'post' ) ) {
		$classes[] = 'bb-single-page';
	}

	return $classes;
}
add_filter( 'body_class', 'bb_body_classes' );

/**
 * The language the site's content is written in.
 *
 * WordPress takes <html lang> from the admin language, and this site's admin
 * runs in English while every article is Bengali — screen readers then read
 * Bengali with an English voice, and search engines are told the wrong
 * language. The content language is set here, independent of the admin one.
 */
function bb_content_language() {
	return (string) apply_filters( 'bb_content_language', 'bn-BD' );
}

function bb_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}

	return preg_replace( '/\blang="[^"]*"/', 'lang="' . esc_attr( bb_content_language() ) . '"', $output );
}
add_filter( 'language_attributes', 'bb_language_attributes' );

/* -------------------------------------------------------------------------
 * 3. Sidebars
 * ---------------------------------------------------------------------- */

function bb_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Sidebar', 'bichitro-biggan' ),
		'id'            => 'bb-sidebar',
		'description'   => __( 'Right-hand sidebar for homepage and archives (displayed below Archive list).', 'bichitro-biggan' ),
		'before_widget' => '<div id="%1$s" class="bb-widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<div class="bb-sechead"><span class="bb-sechead__label bb-sechead__label--upper">',
		'after_title'   => '</span><span class="bb-sechead__rule"></span></div>',
	) );
}
add_action( 'widgets_init', 'bb_widgets_init' );

/* -------------------------------------------------------------------------
 * 4. Category colours
 * ---------------------------------------------------------------------- */

/**
 * Default colours from the design, keyed by the category slugs used on
 * bichitrobiggan.com. Slugs are checked first because they survive renames
 * and spelling variations.
 */
function bb_default_category_colors_by_slug() {
	return array(
		'space-science'           => '#1565c0',
		'wonders-of-the-universe' => '#6a0dad',
		'quantum-sciences'        => '#f5c800',
		'science-of-life'         => '#2e7d32',
		'nature-and-environment'  => '#00796b',
		'science-and-technology'  => '#d84315',
		'stories-of-scientists'   => '#6a0dad',
		'miscellaneous-science'   => '#607d8b',
		'nobel-prizes'            => '#1a1a1a',
		'physics'                 => '#1a1a1a',
		'latest-science'          => '#1a1a1a',
		// পডকাস্ট — WordPress stores this slug percent-encoded.
		'%e0%a6%aa%e0%a6%a1%e0%a6%95%e0%a6%be%e0%a6%b8%e0%a7%8d%e0%a6%9f' => '#e65100',
		'podcast'                 => '#e65100',
	);
}

/**
 * Same defaults keyed by name, including both spellings seen in the export
 * and in the design mock (পুরষ্কার / পুরস্কার, সাম্প্রতিক / সাপ্রতিক).
 */
function bb_default_category_colors() {
	return array(
		'মহাকাশ বিজ্ঞান'        => '#1565c0',
		'মহাবিশ্বের মহাবিস্ময়'  => '#6a0dad',
		'কোয়ান্টাম বিজ্ঞান'     => '#f5c800',
		'জীবনের বিজ্ঞান'        => '#2e7d32',
		'প্রকৃতি ও পরিবেশ'      => '#00796b',
		'বিজ্ঞান ও প্রযুক্তি'    => '#d84315',
		'বিজ্ঞানীদের কথা'       => '#6a0dad',
		'বিবিধ বিজ্ঞান'         => '#607d8b',
		'পডকাস্ট'               => '#e65100',
		'নোবেল পুরষ্কার'         => '#1a1a1a',
		'নোবেল পুরস্কার'         => '#1a1a1a',
		'পদার্থ বিজ্ঞান'        => '#1a1a1a',
		'সাম্প্রতিক বিজ্ঞান'     => '#1a1a1a',
		'সাপ্রতিক বিজ্ঞান'      => '#1a1a1a',
		'বিবর্তন'               => '#1a8cca',
		'ভূ রাজনীতি'            => '#1a1a1a',
	);
}

/**
 * Colour for a term: term meta first, then the design default, then black.
 */
function bb_term_color( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term );
	}
	if ( ! $term || is_wp_error( $term ) ) {
		return '#1a1a1a';
	}

	$meta = get_term_meta( $term->term_id, 'bb_color', true );
	if ( $meta ) {
		return $meta;
	}

	$by_slug = bb_default_category_colors_by_slug();
	if ( isset( $by_slug[ $term->slug ] ) ) {
		return $by_slug[ $term->slug ];
	}

	$defaults = bb_default_category_colors();
	if ( isset( $defaults[ $term->name ] ) ) {
		return $defaults[ $term->name ];
	}

	return '#1a1a1a';
}

/**
 * Relative luminance of a colour, the way WCAG defines it.
 *
 * @param string $hex Colour.
 * @return float|null Null when the colour cannot be read.
 */
function bb_relative_luminance( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		return null;
	}

	$channels = array(
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
	);

	foreach ( $channels as $i => $value ) {
		$value          = $value / 255;
		$channels[ $i ] = ( $value <= 0.03928 ) ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
}

/**
 * Whichever of the theme's two text colours reads better on this background.
 *
 * Measured against the colours actually used. An earlier version measured
 * against pure black while printing #1a1a1a, and on the orange of
 * বিজ্ঞান ও প্রযুক্তি that difference flipped the answer: it chose dark text at
 * 3.9:1 over the white at 4.4:1.
 *
 * @param string $hex Background colour.
 * @return string '#1a1a1a' or '#ffffff'.
 */
function bb_contrast_color( $hex ) {
	$background = bb_relative_luminance( $hex );

	if ( null === $background ) {
		return '#ffffff';
	}

	$light = bb_relative_luminance( '#ffffff' );
	$dark  = bb_relative_luminance( '#1a1a1a' );

	$with_light = ( $light + 0.05 ) / ( $background + 0.05 );
	$with_dark  = ( $background + 0.05 ) / ( $dark + 0.05 );

	return $with_dark > $with_light ? '#1a1a1a' : '#ffffff';
}

/**
 * The text colour for a category's badge.
 *
 * Measuring which of black or white is more legible gets it right more often
 * than not, but not always: white on the orange of বিজ্ঞান ও প্রযুক্তি measures
 * slightly worse and reads better. So the measurement is the default, and a
 * choice made in Theme Settings → ক্যাটাগরির রং overrides it.
 *
 * @param WP_Term|int $term Category.
 * @return string Hex colour.
 */
function bb_term_text_color( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return '#ffffff';
	}

	$choice = get_term_meta( $term->term_id, 'bb_text_color', true );

	if ( in_array( $choice, array( '#ffffff', '#1a1a1a' ), true ) ) {
		return $choice;
	}

	return bb_contrast_color( bb_term_color( $term ) );
}

/**
 * Primary category of a post.
 */
function bb_primary_category( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$cats    = get_the_category( $post_id );

	if ( empty( $cats ) ) {
		return null;
	}

	// Yoast / Rank Math primary category support.
	$primary = get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
	if ( ! $primary ) {
		$primary = get_post_meta( $post_id, 'rank_math_primary_category', true );
	}
	if ( $primary ) {
		$term = get_term( (int) $primary, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}
	}

	return $cats[0];
}

/* Colour field on the category add/edit screens. */

function bb_category_color_add_field() {
	?>
	<div class="form-field">
		<label for="bb_color"><?php esc_html_e( 'ক্যাটাগরির রঙ', 'bichitro-biggan' ); ?></label>
		<input type="text" name="bb_color" id="bb_color" value="" placeholder="#1a8cca" />
		<p><?php esc_html_e( 'ব্যাজ ও সেকশন হেডিং-এ এই রঙ ব্যবহার হবে। খালি রাখলে ডিফল্ট রঙ বসবে।', 'bichitro-biggan' ); ?></p>
		<p><?php esc_html_e( 'লেখার রং ও প্রিভিউ: Theme Settings → ক্যাটাগরির রং।', 'bichitro-biggan' ); ?></p>
	</div>
	<?php
}
add_action( 'category_add_form_fields', 'bb_category_color_add_field' );

function bb_category_color_edit_field( $term ) {
	$value = get_term_meta( $term->term_id, 'bb_color', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="bb_color"><?php esc_html_e( 'ক্যাটাগরির রঙ', 'bichitro-biggan' ); ?></label></th>
		<td>
			<input type="text" name="bb_color" id="bb_color" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( bb_term_color( $term ) ); ?>" />
			<p class="description">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bb-category-colors' ) ); ?>"><?php esc_html_e( 'লেখার রং ও প্রিভিউ দেখুন', 'bichitro-biggan' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'ব্যাজ ও সেকশন হেডিং-এ এই রঙ ব্যবহার হবে।', 'bichitro-biggan' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'category_edit_form_fields', 'bb_category_color_edit_field' );

function bb_save_category_color( $term_id ) {
	if ( ! isset( $_POST['bb_color'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$color = sanitize_hex_color( wp_unslash( $_POST['bb_color'] ) );
	if ( $color ) {
		update_term_meta( $term_id, 'bb_color', $color );
	} else {
		delete_term_meta( $term_id, 'bb_color' );
	}
}
add_action( 'created_category', 'bb_save_category_color' );
add_action( 'edited_category', 'bb_save_category_color' );

/* -------------------------------------------------------------------------
 * 5. Small helpers
 * ---------------------------------------------------------------------- */

/**
 * The values that belong to this particular publication rather than to the
 * theme: who runs it, where to write to it, where its videos live.
 *
 * Every one of them is also a setting — these are only what a fresh install
 * starts with. Anyone running the theme on another site can change them here,
 * in Theme Settings, or through the bb_default_value filter.
 *
 * @param string $key Which default.
 * @return string
 */
function bb_default( $key ) {
	$defaults = array(
		'contact_email'    => 'bichitrobiggan@gmail.com',
		'youtube_url'      => 'https://www.youtube.com/@bigganbichitro',
		'youtube_text'     => 'সাবস্ক্রাইব করুন',
		'footer_author'    => 'Tanvir Hossain',
		'footer_developer' => 'Raisul Sohan',
		'author_bio_site'  => 'bichitrobiggan.com',
		'copyright'        => '©BichitroBiggan',
	);

	$value = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';

	/**
	 * Filter a site default.
	 *
	 * @param string $value Default value.
	 * @param string $key   Which default.
	 */
	return (string) apply_filters( 'bb_default_value', $value, $key );
}

/**
 * The italic line beside the logo. Kept in one place so the Customizer default
 * and the header render the same string.
 */
function bb_default_tagline() {
	return __( 'জীবনের বিজ্ঞান, মহাবিশ্বের মহাবিস্ময়, মহাকাশ অভিযানের কাহিনী, পদার্থের স্বরূপ, কালজয়ী বিজ্ঞানীদের গল্প – এসব নানা চমকপ্রদ বিষয়ে বিভিন্ন সময়ে আমার লেখাগুলোকে নিয়ে তৈরি করেছি, ‘বিচিত্র বিজ্ঞান’ নামের এই ওয়েব সাইট।', 'bichitro-biggan' );
}

function bb_get_tagline() {
	// The default must be passed here: get_theme_mod() returns false, not the
	// registered default, once the Customizer preview is out of the picture.
	$tagline = get_theme_mod( 'bb_tagline_text', bb_default_tagline() );

	if ( '' === trim( (string) $tagline ) ) {
		return '';
	}

	return $tagline;
}

function bb_all_posts_count() {
	return max( 2, (int) get_theme_mod( 'bb_all_posts_count', 6 ) );
}

/** Posts per page on a category / tag / date / author archive. */
function bb_archive_posts_count() {
	return max( 1, (int) get_theme_mod( 'bb_archive_posts_count', 11 ) );
}

/** How many of those go into the featured mosaic at the top. */
function bb_archive_featured_count() {
	$count = (int) get_theme_mod( 'bb_archive_featured_count', 5 );

	return max( 0, min( 5, $count ) );
}

/** Side-list rows beside a featured card in the category blocks. */
function bb_feature_list_count() {
	return max( 1, min( 8, (int) get_theme_mod( 'bb_feature_list_count', 4 ) ) );
}

function bb_get_contact_email() {
	$email = get_theme_mod( 'bb_contact_email', bb_default( 'contact_email' ) );
	return sanitize_email( $email );
}

/**
 * Featured image URL with a graceful fallback.
 */
function bb_thumb_url( $post_id = null, $size = 'bb-card' ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		if ( $url ) {
			return $url;
		}
	}

	$fallback = get_theme_mod( 'bb_fallback_image' );
	if ( $fallback ) {
		return $fallback;
	}

	return get_template_directory_uri() . '/assets/img/placeholder.svg';
}

/**
 * Intrinsic dimensions + loading hints for a card image.
 *
 * The CSS already reserves the box, but Lighthouse still wants width/height,
 * and it protects the layout if the stylesheet is slow. Pass $priority = true
 * for the LCP image (the first hero panel) so it is fetched eagerly instead of
 * being lazy-loaded like everything else.
 *
 * @param int|null $post_id  Post whose thumbnail is being printed.
 * @param string   $size     Registered image size.
 * @param bool     $priority True for the LCP image.
 */
function bb_img_attrs( $post_id = null, $size = 'bb-card', $priority = false ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$width   = 0;
	$height  = 0;

	$thumb_id = $post_id ? get_post_thumbnail_id( $post_id ) : 0;

	if ( $thumb_id ) {
		$src = wp_get_attachment_image_src( $thumb_id, $size );
		if ( $src && ! empty( $src[1] ) && ! empty( $src[2] ) ) {
			$width  = (int) $src[1];
			$height = (int) $src[2];
		}
	}

	if ( ! $width ) {
		list( $width, $height ) = bb_image_size_dimensions( $size );
	}

	/* Without srcset a phone downloads the desktop crop of every card. */
	$srcset = $thumb_id ? wp_get_attachment_image_srcset( $thumb_id, $size ) : '';
	$sizes  = $srcset ? wp_get_attachment_image_sizes( $thumb_id, $size ) : '';

	printf(
		' width="%1$d" height="%2$d" decoding="async"%3$s%4$s%5$s',
		$width,
		$height,
		$srcset ? ' srcset="' . esc_attr( $srcset ) . '"' : '',
		( $srcset && $sizes ) ? ' sizes="' . esc_attr( $sizes ) . '"' : '',
		$priority ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"'
	);
}

/**
 * Declared size of the theme's own image sizes — used when a post has no
 * thumbnail and the placeholder is shown instead.
 */
function bb_image_size_dimensions( $size ) {
	$sizes = array(
		'bb-hero'  => array( 900, 720 ),
		'bb-card'  => array( 600, 380 ),
		'bb-thumb' => array( 360, 240 ),
		'bb-small' => array( 160, 120 ),
	);

	return isset( $sizes[ $size ] ) ? $sizes[ $size ] : array( 600, 380 );
}

/**
 * Send a real 404 for paginated URLs past the last page.
 *
 * front-page.php renders the hero and every category block regardless of the
 * page number, so /page/999/ used to answer 200 with an empty "All posts"
 * list — an unlimited supply of crawlable near-duplicate URLs.
 */
function bb_paged_404() {
	global $wp_query;

	if ( is_admin() || is_404() || is_robots() || is_feed() ) {
		return;
	}

	/*
	 * A static front page is singular, yet its "সকল লেখা" list pages like an
	 * archive — and /page/999/ answered 200 with a full copy of the homepage.
	 * Its page count is the published total over the list's page size, the
	 * same numbers front-page.php pages with.
	 */
	$static_front = is_front_page() && is_page();

	if ( is_singular() && ! $static_front ) {
		return;
	}

	$paged = $static_front
		? max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) )
		: (int) get_query_var( 'paged' );

	if ( $paged < 2 ) {
		return;
	}

	if ( $static_front ) {
		$counts = wp_count_posts( 'post' );
		$max    = (int) ceil( ( isset( $counts->publish ) ? (int) $counts->publish : 0 ) / bb_all_posts_count() );
	} else {
		$max = (int) $wp_query->max_num_pages;
	}

	if ( $max && $paged <= $max ) {
		return;
	}

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'bb_paged_404' );

/**
 * Point crawlers at the sitemap from robots.txt.
 */
function bb_robots_txt( $output ) {
	if ( ! get_option( 'blog_public' ) ) {
		return $output;
	}

	$sitemap = function_exists( 'wp_sitemaps_get_server' ) ? home_url( '/wp-sitemap.xml' ) : '';

	if ( $sitemap && false === strpos( $output, 'Sitemap:' ) ) {
		$output .= "\nSitemap: " . esc_url_raw( $sitemap ) . "\n";
	}

	return $output;
}
add_filter( 'robots_txt', 'bb_robots_txt' );

function bb_popular_query( $count = 3, $range = 'week' ) {
	$count = max( 1, (int) $count );

	/* Week and month come from the daily counts; the longer ranges fall back
	   to the lifetime total held on each post. */
	if ( 'week' === $range || 'month' === $range ) {
		$recent = bb_recent_view_counts( 'week' === $range ? 7 : 30 );
		$ids    = array_slice( array_keys( $recent ), 0, $count );

		if ( ! empty( $ids ) ) {
			$recent_query = new WP_Query( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => $count,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => 1,
			) );

			if ( $recent_query->have_posts() ) {
				return $recent_query;
			}
		}
	}
	$args  = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'meta_key'            => 'bb_views',
		'orderby'             => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
		'no_found_rows'       => true,
		'ignore_sticky_posts' => 1,
	);

	if ( 'week' === $range ) {
		$args['date_query'] = array( array( 'after' => '1 week ago' ) );
	} elseif ( 'month' === $range ) {
		$args['date_query'] = array( array( 'after' => '1 month ago' ) );
	} elseif ( 'year' === $range ) {
		$args['date_query'] = array( array( 'after' => '1 year ago' ) );
	}

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		return $query;
	}

	// Fallback without meta_key if view tracking is empty
	unset( $args['meta_key'] );
	$args['orderby'] = array( 'comment_count' => 'DESC', 'date' => 'DESC' );
	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		return $query;
	}

	// Fallback without date restriction if no posts in that short window
	unset( $args['date_query'] );
	return new WP_Query( $args );
}

/**
 * AJAX handler for popular posts time filter dropdown.
 */
function bb_ajax_popular_posts() {
	$range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'week';
	if ( ! in_array( $range, array( 'week', 'month', 'year', 'all' ), true ) ) {
		$range = 'week';
	}

	// Anyone can call this and admin-ajax is never page-cached, so the size is
	// held to the footer's own range — an open count returned every post on the
	// site in one response. The rendered list is kept for ten minutes.
	$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : (int) get_theme_mod( 'bb_popular_count', 3 );
	$count = max( 1, min( 8, $count ) );

	$cache_key = 'bb_popular_' . $range . '_' . $count . ( get_theme_mod( 'bb_footer_show_thumbs', false ) ? '_thumbs' : '' );
	$html      = get_transient( $cache_key );

	if ( false !== $html ) {
		wp_send_json_success( array( 'html' => $html ) );
	}

	$query = bb_popular_query( $count, $range );

	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			bb_footer_item();
		}
		wp_reset_postdata();
	} else {
		echo '<p class="bb-footer__empty">' . esc_html__( 'এই সময়ের কোনো লেখা পাওয়া যায়নি', 'bichitro-biggan' ) . '</p>';
	}
	$html = ob_get_clean();

	set_transient( $cache_key, $html, 10 * MINUTE_IN_SECONDS );

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_bb_get_popular_posts', 'bb_ajax_popular_posts' );
add_action( 'wp_ajax_nopriv_bb_get_popular_posts', 'bb_ajax_popular_posts' );

/**
 * Hand-picked EDITOR PICKS, topped up with recent posts when fewer are set.
 * Reads specific customizer slots bb_editor_picks_1 to bb_editor_picks_8.
 */
function bb_editor_picks_query() {
	$count = max( 1, min( 8, (int) get_theme_mod( 'bb_editor_picks_count', 3 ) ) );
	$picks = array();

	// Check slots 1 to $count
	for ( $i = 1; $i <= $count; $i++ ) {
		$slot_post_id = (int) get_theme_mod( "bb_editor_picks_{$i}", 0 );
		if ( $slot_post_id && 'publish' === get_post_status( $slot_post_id ) ) {
			$picks[] = $slot_post_id;
		}
	}

	// Legacy setting fallback if slots not configured
	if ( empty( $picks ) ) {
		$legacy = (array) get_theme_mod( 'bb_editor_picks', array() );
		$picks  = array_values( array_filter( array_map( 'absint', $legacy ) ) );
	}

	$picks = array_unique( $picks );
	$picks = array_slice( $picks, 0, $count );

	if ( count( $picks ) === $count ) {
		return new WP_Query( array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $picks,
			'orderby'             => 'post__in',
			'posts_per_page'      => $count,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => 1,
		) );
	}

	$fill = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count - count( $picks ),
		'post__not_in'        => $picks,
		'no_found_rows'       => true,
		'ignore_sticky_posts' => 1,
		'fields'              => 'ids',
	) );

	$ids = array_merge( $picks, (array) $fill->posts );

	if ( empty( $ids ) ) {
		return new WP_Query( array( 'post__in' => array( 0 ) ) );
	}

	return new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'post__in'            => $ids,
		'orderby'             => 'post__in',
		'posts_per_page'      => $count,
		'no_found_rows'       => true,
		'ignore_sticky_posts' => 1,
	) );
}

function bb_comment_count( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	return (int) get_comments_number( $post_id );
}

/**
 * Years that actually have posts — powers the year tab strip.
 */
function bb_get_post_years() {
	$cached = get_transient( 'bb_post_years' );
	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$years = $wpdb->get_col(
		"SELECT DISTINCT YEAR(post_date) AS y
		 FROM {$wpdb->posts}
		 WHERE post_status = 'publish' AND post_type = 'post'
		 ORDER BY y DESC"
	);

	$years = array_map( 'strval', (array) $years );
	set_transient( 'bb_post_years', $years, DAY_IN_SECONDS );

	return $years;
}

function bb_flush_year_cache() {
	delete_transient( 'bb_post_years' );

	/* The archive trees are cached per category; bumping the version retires
	   all of them at once, which a transient key cannot do on its own. */
	update_option( 'bb_cache_version', (int) get_option( 'bb_cache_version', 1 ) + 1, false );
}
add_action( 'save_post', 'bb_flush_year_cache' );
add_action( 'deleted_post', 'bb_flush_year_cache' );

/**
 * Filter category archive query by custom year & month if supplied in URL.
 */
function bb_category_date_filter( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_category() ) {
		$y = isset( $_GET['bb_year'] ) ? absint( $_GET['bb_year'] ) : 0;
		$m = isset( $_GET['bb_month'] ) ? absint( $_GET['bb_month'] ) : 0;
		if ( $y > 0 ) {
			$date_query = array( 'year' => $y );
			if ( $m > 0 ) {
				$date_query['month'] = $m;
			}
			$query->set( 'date_query', array( $date_query ) );
		}
	}
}
add_action( 'pre_get_posts', 'bb_category_date_filter' );

/**
 * Archive tree: year => array of months.
 * Dynamically adapts to category archives if on category page or $cat_id passed.
 */
function bb_get_archive_tree( $cat_id = 0 ) {
	if ( ! $cat_id && is_category() ) {
		$cat_id = get_queried_object_id();
	}

	$key    = 'bb_tree_' . (int) get_option( 'bb_cache_version', 1 ) . '_' . (int) $cat_id;
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$tree = bb_build_archive_tree( $cat_id );

	set_transient( $key, $tree, 12 * HOUR_IN_SECONDS );

	return $tree;
}

/**
 * The grouped query behind the sidebar's year and month list. It scans every
 * published post, so bb_get_archive_tree() caches what it returns.
 */
function bb_build_archive_tree( $cat_id = 0 ) {
	global $wpdb;

	if ( ! $cat_id && is_category() ) {
		$cat_id = get_queried_object_id();
	}

	if ( $cat_id > 0 ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT YEAR(p.post_date) AS y, MONTH(p.post_date) AS m, COUNT(p.ID) AS c
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
				 INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
				 WHERE p.post_status = 'publish' 
				   AND p.post_type = 'post'
				   AND tt.taxonomy = 'category'
				   AND tt.term_id = %d
				 GROUP BY y, m
				 ORDER BY y DESC, m DESC",
				$cat_id
			)
		);

		$cat_link = get_category_link( $cat_id );
		$tree     = array();
		foreach ( (array) $rows as $row ) {
			$month_url = add_query_arg(
				array(
					'bb_year'  => (int) $row->y,
					'bb_month' => (int) $row->m,
				),
				$cat_link
			);

			$tree[ $row->y ][] = array(
				'month' => (int) $row->m,
				'count' => (int) $row->c,
				'label' => bb_bangla_month( $row->m ),
				'url'   => $month_url,
			);
		}

		return $tree;
	}

	$rows = $wpdb->get_results(
		"SELECT YEAR(post_date) AS y, MONTH(post_date) AS m, COUNT(ID) AS c
		 FROM {$wpdb->posts}
		 WHERE post_status = 'publish' AND post_type = 'post'
		 GROUP BY y, m
		 ORDER BY y DESC, m DESC"
	);

	$tree = array();
	foreach ( (array) $rows as $row ) {
		$tree[ $row->y ][] = array(
			'month' => (int) $row->m,
			'count' => (int) $row->c,
			'label' => bb_bangla_month( $row->m ),
			'url'   => get_month_link( $row->y, $row->m ),
		);
	}

	return $tree;
}

/**
 * Excerpt trimmed for cards.
 */
function bb_excerpt( $words = 22, $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return '';
	}

	$text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
	$text = strip_shortcodes( $text );
	$text = excerpt_remove_blocks( $text );
	$text = wp_strip_all_tags( $text );

	return wp_trim_words( $text, $words, '…' );
}

function bb_excerpt_length() {
	return 22;
}
add_filter( 'excerpt_length', 'bb_excerpt_length', 999 );

function bb_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'bb_excerpt_more' );

/**
 * Query builder used by the front page blocks.
 *
 * @param int   $cat_id      Category ID (0 = any).
 * @param int   $count       Posts per page.
 * @param array $exclude_ids Post IDs to skip.
 */
function bb_query( $cat_id = 0, $count = 4, $exclude_ids = array() ) {
	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => (int) $count,
		'ignore_sticky_posts' => 1,
		'no_found_rows'       => true,
		'post_status'         => 'publish',
	);

	if ( $cat_id ) {
		$args['cat'] = (int) $cat_id;
	}
	if ( ! empty( $exclude_ids ) ) {
		$args['post__not_in'] = array_map( 'intval', $exclude_ids );
	}

	return new WP_Query( $args );
}

/**
 * Category ID for a homepage block.
 *
 * Order of preference: the Customizer setting, then the category slug from
 * bichitrobiggan.com, then the Nth most-used category so a fresh site still
 * gets a varied homepage instead of the same posts in every block.
 *
 * Matching is done by slug, not name: Bengali category names can differ by
 * Unicode normalisation (কোয়ান্টাম বিজ্ঞান on the live site uses U+09DF where
 * the same word typed elsewhere uses U+09AF + U+09BC), so name comparison is
 * not reliable.
 *
 * @param string $mod_key       Theme mod key.
 * @param string $fallback_slug Category slug.
 * @param int    $index         Position to take from the most-used list.
 */
function bb_block_cat( $mod_key, $fallback_slug = '', $index = -1 ) {
	$id = (int) get_theme_mod( $mod_key, 0 );

	if ( $id ) {
		return $id;
	}

	if ( $fallback_slug ) {
		$term = get_term_by( 'slug', $fallback_slug, 'category' );

		if ( ( ! $term || is_wp_error( $term ) ) && false !== strpos( $fallback_slug, '%' ) ) {
			// Percent-encoded Bengali slugs are sometimes stored decoded.
			$term = get_term_by( 'slug', rawurldecode( $fallback_slug ), 'category' );
		}

		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
	}

	if ( $index >= 0 ) {
		$cats = get_categories( array(
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => true,
		) );

		if ( ! empty( $cats ) ) {
			$pick = $cats[ $index % count( $cats ) ];
			return (int) $pick->term_id;
		}
	}

	return 0;
}

/**
 * Category name for a block heading.
 */
function bb_block_cat_name( $cat_id, $fallback ) {
	if ( $cat_id ) {
		$term = get_term( $cat_id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term->name;
		}
	}
	return $fallback;
}

/**
 * Archive pages show a 5-panel mosaic plus a 6-card grid, so they need 11 posts.
 */
function bb_archive_posts_per_page( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_category() || $query->is_tag() || $query->is_date() || $query->is_author() || $query->is_tax() ) {
		$query->set( 'posts_per_page', bb_archive_posts_count() );
		return;
	}

	// The "সকল লেখা" grid runs off the main query, so its page size has to be
	// the one the theme uses — otherwise WordPress stops at the number of pages
	// implied by Settings → Reading and later pages 404 while the pagination
	// still links to them.
	if ( $query->is_home() ) {
		$query->set( 'posts_per_page', bb_all_posts_count() );
	}
}
add_action( 'pre_get_posts', 'bb_archive_posts_per_page' );

/* -------------------------------------------------------------------------
 * 6. Pagination markup matching the design
 * ---------------------------------------------------------------------- */

function bb_pagination( $query = null ) {
	global $wp_query;
	$query = $query ? $query : $wp_query;

	$total = (int) $query->max_num_pages;
	if ( $total <= 1 ) {
		return;
	}

	$current = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

	$links = paginate_links( array(
		'total'     => $total,
		'current'   => $current,
		'mid_size'  => 1,
		'end_size'  => 1,
		'prev_text' => '‹',
		'next_text' => '›',
		'type'      => 'array',
	) );

	if ( empty( $links ) ) {
		return;
	}
	?>
	<nav class="bb-pagination" aria-label="<?php esc_attr_e( 'পেজ নেভিগেশন', 'bichitro-biggan' ); ?>">
		<div class="bb-pagination__list">
			<?php echo implode( '', $links ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<span class="bb-pagination__info">
			<?php
			printf(
				/* translators: 1: current page, 2: total pages */
				esc_html__( 'পৃষ্ঠা %1$s / %2$s', 'bichitro-biggan' ),
				esc_html( bb_bangla_number( $current ) ),
				esc_html( bb_bangla_number( $total ) )
			);
			?>
		</span>
	</nav>
	<?php
}

/* -------------------------------------------------------------------------
 * 7. Navigation walker — adds the theme's classes
 * ---------------------------------------------------------------------- */

class BB_Nav_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '<ul class="bb-nav__sub">';
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'bb-nav__item';
		$classes[] = 'menu-item-' . $item->ID;

		$class_names = join( ' ', array_filter( array_map( 'sanitize_html_class', $classes ) ) );

		$output .= '<li class="' . esc_attr( $class_names ) . '">';

		$atts = array(
			'title'  => ! empty( $item->attr_title ) ? $item->attr_title : '',
			'target' => ! empty( $item->target ) ? $item->target : '',
			'rel'    => ! empty( $item->xfn ) ? $item->xfn : '',
			'href'   => ! empty( $item->url ) ? $item->url : '',
			'class'  => 'bb-nav__link',
		);

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( '' !== $value ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$output .= '<a' . $attributes . '>' . esc_html( $item->title ) . '</a>';
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= '</li>';
	}
}

/**
 * Menu fallback: list the categories, like the demo's nav strip.
 */
function bb_nav_fallback( $args = array() ) {
	$is_mobile = ! empty( $args['bb_mobile'] );
	$classes   = $is_mobile ? '' : 'bb-nav__list';

	echo '<ul class="' . esc_attr( $classes ) . '">';

	$home_class = ( is_front_page() || is_home() ) ? 'bb-nav__item is-active' : 'bb-nav__item';
	echo '<li class="' . esc_attr( $home_class ) . '"><a class="bb-nav__link" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'প্রথম পাতা', 'bichitro-biggan' ) . '</a></li>';

	$cats = get_categories( array(
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 12,
		'hide_empty' => true,
	) );

	foreach ( $cats as $cat ) {
		$active = ( is_category( $cat->term_id ) ) ? ' is-active' : '';
		echo '<li class="bb-nav__item' . esc_attr( $active ) . '"><a class="bb-nav__link" href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a></li>';
	}

	echo '</ul>';
}

/**
 * Label shown in the collapsed mobile nav bar.
 */
function bb_current_label() {
	if ( is_front_page() || is_home() ) {
		return __( 'প্রথম পাতা', 'bichitro-biggan' );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return single_term_title( '', false );
	}
	if ( is_year() ) {
		/* translators: %s: year */
		return sprintf( __( '%s সাল', 'bichitro-biggan' ), get_the_date( 'Y' ) );
	}
	if ( is_month() ) {
		return get_the_date( 'F Y' );
	}
	if ( is_search() ) {
		return __( 'সার্চ', 'bichitro-biggan' );
	}
	if ( is_author() ) {
		return get_the_author();
	}
	if ( is_singular() ) {
		$cat = bb_primary_category();
		return $cat ? $cat->name : get_the_title();
	}
	return get_bloginfo( 'name' );
}

/* -------------------------------------------------------------------------
 * 8. Comment list markup
 * ---------------------------------------------------------------------- */

function bb_comment_callback( $comment, $args, $depth ) {
	?>
	<li <?php comment_class(); ?> id="comment-<?php comment_ID(); ?>">
		<article class="comment-body">
			<div class="bb-authorrow" style="margin-bottom:8px;">
				<div class="bb-authorrow__avatar"><?php echo get_avatar( $comment, 36 ); ?></div>
				<div>
					<p class="bb-authorrow__name"><?php comment_author(); ?></p>
					<p class="bb-authorrow__date"><?php echo esc_html( get_comment_date() ); ?></p>
				</div>
			</div>
			<div class="bb-content"><?php comment_text(); ?></div>
			<?php
			comment_reply_link( array_merge( $args, array(
				'depth'     => $depth,
				'max_depth' => $args['max_depth'],
				'before'    => '<p style="font-size:12px;color:#1a8cca;margin-top:6px;">',
				'after'     => '</p>',
			) ) );
			?>
		</article>
	<?php
}

/* -------------------------------------------------------------------------
 * 9. Includes
 * ---------------------------------------------------------------------- */

require_once get_template_directory() . '/inc/formatting.php';
require_once get_template_directory() . '/inc/images.php';
require_once get_template_directory() . '/inc/views.php';
require_once get_template_directory() . '/inc/google-verification.php';
require_once get_template_directory() . '/inc/user-privacy.php';
require_once get_template_directory() . '/inc/author-slug.php';
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/live-search.php';
require_once get_template_directory() . '/inc/editor.php';
require_once get_template_directory() . '/inc/seo-meta-box.php';
require_once get_template_directory() . '/inc/video-meta-box.php';
require_once get_template_directory() . '/inc/references-meta-box.php';
require_once get_template_directory() . '/inc/rest-meta.php';
require_once get_template_directory() . '/inc/seo-frontend.php';

if ( is_admin() ) {
	require_once get_template_directory() . '/inc/admin-settings.php';
	require_once get_template_directory() . '/inc/admin-post-search.php';
	require_once get_template_directory() . '/inc/category-colors-admin.php';
	require_once get_template_directory() . '/inc/seo-admin-column.php';
}

add_action( 'wp_footer', 'bb_bookmarks_drawer' );


/**
 * Force the custom grid/popup template for Podcast/Video categories
 * regardless of the exact slug they use.
 */
add_filter( 'category_template', 'bb_force_podcast_category_template', 99 );
function bb_force_podcast_category_template( $template ) {
	$cat = get_queried_object();
	if ( $cat && $cat instanceof WP_Term && $cat->taxonomy === 'category' ) {
		$configured_pod_id = (int) get_theme_mod( 'bb_cat_podcast', 0 );
		$is_pod = false;

		if ( $configured_pod_id && (int) $cat->term_id === $configured_pod_id ) {
			$is_pod = true;
		} elseif ( false !== stripos( $cat->slug, 'podcast' ) || false !== stripos( $cat->slug, 'video' ) ) {
			$is_pod = true;
		} elseif ( function_exists('bb_str_pos') ) {
            if ( false !== bb_str_pos( $cat->name, 'পডকাস্ট' ) || false !== bb_str_pos( $cat->name, 'ভিডিও' ) ) {
                $is_pod = true;
            }
        }

		if ( $is_pod ) {
			$custom_template = locate_template( 'category-videos.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}
	}
	return $template;
}


// Load GitHub Auto-Updater — native WordPress theme updates from GitHub.
require get_template_directory() . '/inc/github-updater.php';
require_once get_template_directory() . '/inc/license.php';
