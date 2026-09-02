<?php
/**
 * Bichitro Biggan theme functions.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BB_VERSION', '5.0.9' );

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
		'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'bb-style',
		get_stylesheet_uri(),
		array( 'bb-fonts' ),
		bb_file_version( get_stylesheet_directory() . '/style.css' )
	);

	wp_enqueue_script(
		'bb-script',
		get_template_directory_uri() . '/assets/js/theme.js',
		array(),
		bb_file_version( get_template_directory() . '/assets/js/theme.js' ),
		true
	);

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
	) );

	wp_add_inline_style( 'bb-style', bb_dynamic_css() );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'bb_enqueue_assets' );

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
		'bb_feature_image_height' => array( 'var' => '--bb-feature-img-h', 'default' => 192, 'min' => 100, 'max' => 400, 'section' => 'bb_layout_block1', 'label' => __( 'Featured Card Image Height', 'bichitro-biggan' ) ),
		'bb_list_thumb_width'     => array( 'var' => '--bb-list-thumb-w', 'default' => 90, 'min' => 56, 'max' => 180, 'section' => 'bb_layout_block1', 'label' => __( 'Side List Thumbnail Width', 'bichitro-biggan' ) ),
		'bb_list_thumb_height'    => array( 'var' => '--bb-list-thumb-h', 'default' => 64, 'min' => 40, 'max' => 130, 'section' => 'bb_layout_block1', 'label' => __( 'Side List Thumbnail Height', 'bichitro-biggan' ) ),
		'bb_grid_card_height'     => array( 'var' => '--bb-grid-card-h', 'default' => 120, 'min' => 70, 'max' => 240, 'section' => 'bb_layout_block1', 'label' => __( 'Right Grid Card Height', 'bichitro-biggan' ) ),

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
	return $classes;
}
add_filter( 'body_class', 'bb_body_classes' );

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
 * Black or white text depending on background luminance.
 */
function bb_contrast_color( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#ffffff';
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

	return $luminance > 0.6 ? '#1a1a1a' : '#ffffff';
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
	$email = get_theme_mod( 'bb_contact_email', 'bichitrobiggan@gmail.com' );
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
 * Convert English digits to Bengali digits.
 */
function bb_bangla_number( $number ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$bn = array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' );
	return str_replace( $en, $bn, (string) $number );
}

/**
 * Post date in full Bengali format.
 */
function bb_bangla_date( $post_id = null ) {
	$post_id  = $post_id ? $post_id : get_the_ID();
	$day      = get_the_date( 'j', $post_id );
	$month_en = get_the_date( 'F', $post_id );
	$year     = get_the_date( 'Y', $post_id );

	$months = array(
		'January'   => 'জানুয়ারি',
		'February'  => 'ফেব্রুয়ারি',
		'March'     => 'মার্চ',
		'April'     => 'এপ্রিল',
		'May'       => 'মে',
		'June'      => 'জুন',
		'July'      => 'জুলাই',
		'August'    => 'আগস্ট',
		'September' => 'সেপ্টেম্বর',
		'October'   => 'অক্টোবর',
		'November'  => 'নভেম্বর',
		'December'  => 'ডিসেম্বর',
	);

	$month_bn = isset( $months[ $month_en ] ) ? $months[ $month_en ] : $month_en;
	$day_bn   = bb_bangla_number( $day );
	$year_bn  = bb_bangla_number( $year );

	return $day_bn . ' ' . $month_bn . ', ' . $year_bn;
}

/**
 * Post date in the site's format (Bengali).
 */
function bb_post_date( $post_id = null ) {
	return bb_bangla_date( $post_id );
}

/**
 * Calculate post reading time in Bengali.
 */
function bb_reading_time( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$content = get_post_field( 'post_content', $post_id );
	$clean   = wp_strip_all_tags( strip_shortcodes( $content ) );
	$words   = preg_split( '/\s+/u', trim( $clean ) );
	$count   = is_array( $words ) ? count( array_filter( $words ) ) : 0;
	$minutes = max( 1, (int) ceil( $count / 180 ) );

	return bb_bangla_number( $minutes ) . ' মিনিট';
}

/**
 * Very small view counter — powers the 👁 figure on single posts.
 * Note: a full-page cache will suppress these increments.
 */
function bb_get_views( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$views = get_post_meta( $post_id, 'bb_views', true );
	if ( '' !== $views ) {
		return (int) $views;
	}

	// Seed from the previous theme's counter so imported posts keep their totals.
	return (int) get_post_meta( $post_id, 'post_views_count', true );
}

function bb_track_views() {
	if ( ! is_singular( 'post' ) || is_preview() ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	update_post_meta( $post_id, 'bb_views', bb_get_views( $post_id ) + 1 );
}
add_action( 'wp_head', 'bb_track_views' );

/**
 * Give every published post a bb_views row so ordering by it is meaningful.
 * Imported posts start from the old theme's post_views_count. Runs in
 * batches on admin page loads and stops for good once finished.
 */
function bb_backfill_views() {
	if ( get_option( 'bb_views_backfilled' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$ids = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => 'bb_views',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	if ( empty( $ids ) ) {
		update_option( 'bb_views_backfilled', 1, false );
		return;
	}

	foreach ( $ids as $id ) {
		add_post_meta( $id, 'bb_views', (int) get_post_meta( $id, 'post_views_count', true ), true );
	}
}
add_action( 'admin_init', 'bb_backfill_views' );

/**
 * Most-viewed posts. Supports time filtering: 'all', 'week', 'month', 'year'.
 * Falls back to comment count while the backfill is still running or if no view data exists yet.
 */
function bb_popular_query( $count = 3, $range = 'week' ) {
	$count = max( 1, (int) $count );
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
	$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : (int) get_theme_mod( 'bb_popular_count', 3 );
	$query = bb_popular_query( $count, $range );

	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			bb_footer_item();
		}
		wp_reset_postdata();
	} else {
		echo '<p class="bb-footer__empty" style="font-size:13px;color:#9ca3af;padding:12px 0;">' . esc_html__( 'এই সময়ের কোনো লেখা পাওয়া যায়নি', 'bichitro-biggan' ) . '</p>';
	}
	$html = ob_get_clean();

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
				'label' => $GLOBALS['wp_locale']->get_month( $row->m ),
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
			'label' => $GLOBALS['wp_locale']->get_month( $row->m ),
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
				esc_html__( 'Page %1$s of %2$s', 'bichitro-biggan' ),
				esc_html( $current ),
				esc_html( $total )
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

require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/live-search.php';
require_once get_template_directory() . '/inc/editor.php';
require_once get_template_directory() . '/inc/seo-meta-box.php';
require_once get_template_directory() . '/inc/video-meta-box.php';
require_once get_template_directory() . '/inc/seo-frontend.php';

if ( is_admin() ) {
	require_once get_template_directory() . '/inc/admin-settings.php';
}

add_action( 'wp_footer', 'bb_bookmarks_drawer' );


/* =====================================================================
 * Google Site Verification Route
 * ================================================================== */
add_action( 'init', 'bb_google_site_verification_route' );
function bb_google_site_verification_route() {
	add_rewrite_rule( '^google1c72e007995ed549\.html$', 'index.php?bb_google_verify=1', 'top' );
}

add_filter( 'query_vars', 'bb_google_site_verification_query_vars' );
function bb_google_site_verification_query_vars( $query_vars ) {
	$query_vars[] = 'bb_google_verify';
	return $query_vars;
}

add_action( 'template_redirect', 'bb_google_site_verification_render' );
function bb_google_site_verification_render() {
	if ( get_query_var( 'bb_google_verify' ) ) {
		header( 'Content-Type: text/html' );
		echo 'google-site-verification: google1c72e007995ed549.html';
		exit;
	}
}


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


/**
 * Auto-convert uploaded images to WebP and resize them to save space.
 */
function bb_optimize_image_upload( $upload ) {
	if ( $upload['type'] === 'image/jpeg' || $upload['type'] === 'image/png' ) {
		$file_path = $upload['file'];
		
		if ( ! file_exists( $file_path ) ) {
			return $upload;
		}

		$image_editor = wp_get_image_editor( $file_path );
		
		if ( ! is_wp_error( $image_editor ) && $image_editor->supports_mime_type( 'image/webp' ) ) {
			$max_width = 1600;
			$size = $image_editor->get_size();
			if ( ! is_wp_error( $size ) && ( $size['width'] > $max_width || $size['height'] > $max_width ) ) {
				$image_editor->resize( $max_width, $max_width, false );
			}
			
			$image_editor->set_quality( 80 );
			
			$path_parts    = pathinfo( $file_path );
			$webp_filename = $path_parts['filename'] . '.webp';
			$webp_path     = $path_parts['dirname'] . '/' . $webp_filename;
			
			$saved = $image_editor->save( $webp_path, 'image/webp' );
			
			if ( ! is_wp_error( $saved ) && file_exists( $saved['path'] ) ) {
				@unlink( $file_path );
				
				$upload['file'] = $saved['path'];
				$url_parts      = pathinfo( $upload['url'] );
				$upload['url']  = $url_parts['dirname'] . '/' . $webp_filename;
				$upload['type'] = 'image/webp';
			}
		}
	}
	return $upload;
}
add_filter( 'wp_handle_upload', 'bb_optimize_image_upload' );

/**
 * Set standard thumbnail generation quality to 80.
 */
function bb_image_quality( $quality ) {
	return 80;
}
add_filter( 'wp_editor_set_quality', 'bb_image_quality' );

// Load GitHub Auto-Updater — native WordPress theme updates from GitHub.
require get_template_directory() . '/inc/github-updater.php';

/* -------------------------------------------------------------------------
 * License System — Google Sheets powered license key verification.
 * ---------------------------------------------------------------------- */

/**
 * Google Apps Script Web App URL.
 * Replace this with your deployed Apps Script URL after setup.
 */
define( 'BB_LICENSE_API_URL', 'https://script.google.com/macros/s/AKfycbxV0k_ydJr9SJttf_7fgRXuG3KqCKStgg7cEUS80uCAqZzzNqz51RyI07o7mzrZzHjRDQ/exec' );

/**
 * Add License page under Appearance menu.
 */
function bb_license_menu() {
	add_theme_page(
		'Theme License',
		'Theme License',
		'manage_options',
		'bb-license',
		'bb_license_page_html'
	);
}
add_action( 'admin_menu', 'bb_license_menu' );

/**
 * Handle license activation form submission.
 */
function bb_handle_license_activation() {
	if ( ! isset( $_POST['bb_license_activate'] ) || ! check_admin_referer( 'bb_license_nonce' ) ) {
		return;
	}

	$license_key = sanitize_text_field( trim( $_POST['bb_license_key'] ) );
	if ( empty( $license_key ) ) {
		add_settings_error( 'bb_license', 'empty', 'লাইসেন্স কী খালি রাখা যাবে না।', 'error' );
		return;
	}

	$domain = sanitize_text_field( $_SERVER['SERVER_NAME'] );

	$response = wp_remote_get(
		add_query_arg(
			array(
				'action'  => 'activate',
				'key'     => $license_key,
				'domain'  => $domain,
			),
			BB_LICENSE_API_URL
		),
		array( 'timeout' => 15, 'sslverify' => true )
	);

	if ( is_wp_error( $response ) ) {
		add_settings_error( 'bb_license', 'conn', 'সার্ভারের সাথে কানেক্ট করা যাচ্ছে না: ' . $response->get_error_message(), 'error' );
		return;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! empty( $body['success'] ) ) {
		update_option( 'bb_license_key', $license_key );
		update_option( 'bb_license_status', 'valid' );
		update_option( 'bb_license_domain', $domain );
		add_settings_error( 'bb_license', 'ok', 'লাইসেন্স সফলভাবে অ্যাক্টিভেট হয়েছে! ✅', 'updated' );
	} else {
		$msg = ! empty( $body['message'] ) ? $body['message'] : 'ভুল লাইসেন্স কী!';
		add_settings_error( 'bb_license', 'fail', 'অ্যাক্টিভেশন ব্যর্থ: ' . $msg, 'error' );
	}
}
add_action( 'admin_init', 'bb_handle_license_activation' );

/**
 * Check if the theme is licensed.
 */
function bb_is_licensed() {
	// Always allow localhost development.
	$host = isset( $_SERVER['SERVER_NAME'] ) ? strtolower( $_SERVER['SERVER_NAME'] ) : '';
	if ( strpos( $host, 'localhost' ) === 0 || $host === '127.0.0.1' ) {
		return true;
	}

	return get_option( 'bb_license_status' ) === 'valid';
}

/**
 * Block frontend if not licensed.
 */
function bb_license_block_frontend() {
	if ( bb_is_licensed() ) {
		return;
	}

	wp_die(
		'<div style="font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;text-align:center;">'
		. '<h1 style="font-size:28px;margin-bottom:12px;">Bichitro Biggan Theme</h1>'
		. '<p style="font-size:16px;color:#555;">This theme is not licensed for this domain.</p>'
		. '<p style="font-size:13px;color:#999;margin-top:24px;">Please activate your license key from the WordPress dashboard.</p>'
		. '</div>',
		'License Required',
		array( 'response' => 403 )
	);
}
add_action( 'template_redirect', 'bb_license_block_frontend' );

/**
 * Admin notice when not licensed.
 */
function bb_license_admin_notice() {
	if ( bb_is_licensed() ) {
		return;
	}
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'bb-license' ) {
		return;
	}

	$url = admin_url( 'themes.php?page=bb-license' );
	echo '<div class="notice notice-error"><p><strong>বিচিত্র বিজ্ঞান থিম:</strong> '
		. 'থিমটি ব্যবহার করতে দয়া করে <a href="' . esc_url( $url ) . '">লাইসেন্স অ্যাক্টিভেট করুন</a>।</p></div>';
}
add_action( 'admin_notices', 'bb_license_admin_notice' );

/**
 * Render the license activation page.
 */
function bb_license_page_html() {
	$status = get_option( 'bb_license_status' );
	$key    = get_option( 'bb_license_key' );
	$domain = get_option( 'bb_license_domain' );
	?>
	<div class="wrap">
		<h1>বিচিত্র বিজ্ঞান — লাইসেন্স অ্যাক্টিভেশন</h1>
		<?php settings_errors( 'bb_license' ); ?>

		<div class="card" style="max-width: 520px; padding: 24px; margin-top: 20px;">
			<?php if ( $status === 'valid' ) : ?>
				<p style="color: #00a32a; font-weight: bold; font-size: 15px;">✅ লাইসেন্স অ্যাক্টিভ আছে!</p>
				<table class="form-table">
					<tr><th>লাইসেন্স কী:</th><td><code><?php echo esc_html( $key ); ?></code></td></tr>
					<tr><th>ডোমেইন:</th><td><code><?php echo esc_html( $domain ); ?></code></td></tr>
				</table>
			<?php else : ?>
				<p>থিমের সম্পূর্ণ ফিচার উপভোগ করতে আপনার লাইসেন্স কী (License Key) দিন।</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'bb_license_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="bb_license_key">লাইসেন্স কী:</label></th>
							<td><input type="text" id="bb_license_key" name="bb_license_key" value="" style="width:100%;padding:8px;" placeholder="BB-XXXX-XXXX-XXXX" /></td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="bb_license_activate" class="button-primary" value="অ্যাক্টিভেট করুন" />
					</p>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
