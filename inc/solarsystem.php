<?php
/**
 * The 3D solar system page at /solarsystem.
 *
 * page-solarsystem.php draws the page. This file loads what it needs — three.js
 * and the few addons it uses, served from assets/vendor/three, plus the page's
 * own script and stylesheet — on that page alone, so no other page pays for
 * them, and gives the page its SEO title, description and share picture when
 * the editor's SEO box leaves them empty.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BB_THREE_VERSION', '0.160.0' );

/**
 * The three.js addons the page imports, relative to assets/vendor/three/addons.
 * Their own helpers (Pass, ShaderPass, the shaders) are reached by relative
 * imports and need no entry here.
 *
 * @return string[]
 */
function bb_solarsystem_addons() {
	return array(
		'controls/OrbitControls.js',
		'postprocessing/EffectComposer.js',
		'postprocessing/RenderPass.js',
		'postprocessing/UnrealBloomPass.js',
		'postprocessing/OutputPass.js',
	);
}

/**
 * Remember whether this request is being drawn by page-solarsystem.php.
 *
 * Read from the template WordPress actually chose, so it holds both for the
 * page whose slug is "solarsystem" and for any page given the template by hand.
 *
 * @param string $template Path of the chosen template.
 * @return string
 */
function bb_solarsystem_flag_template( $template ) {
	$GLOBALS['bb_is_solarsystem'] = ( 'page-solarsystem.php' === basename( (string) $template ) );

	return $template;
}
add_filter( 'template_include', 'bb_solarsystem_flag_template', 99 );

/**
 * Is the solar system page being served?
 *
 * @return bool
 */
function bb_is_solarsystem_page() {
	return ! empty( $GLOBALS['bb_is_solarsystem'] ) && is_page();
}

/**
 * Will this page be drawn by page-solarsystem.php? For the checks that run
 * before the template is chosen (the English edition's redirect, hreflang,
 * the sitemap), worked out the way WordPress picks it: the template chosen
 * by hand, else the page whose slug is "solarsystem".
 *
 * @param int $post_id Post.
 * @return bool
 */
function bb_solarsystem_is_page_id( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	$template = get_page_template_slug( $post );

	return 'page-solarsystem.php' === $template || ( '' === $template && 'solarsystem' === $post->post_name );
}

/**
 * The page writes its own English, so /en/solarsystem opens without English
 * fields typed into the editor (they still win when they are filled in).
 *
 * @param bool $has     Whether the post has an English version.
 * @param int  $post_id Post.
 * @return bool
 */
function bb_solarsystem_en_has( $has, $post_id ) {
	return $has || bb_solarsystem_is_page_id( $post_id );
}
add_filter( 'bb_en_has', 'bb_solarsystem_en_has', 10, 2 );

/**
 * The page's English title when none was typed in: "3D Solar System" rather
 * than the Bengali one on the English edition.
 *
 * @param string $title   Title.
 * @param int    $post_id Post.
 * @return string
 */
function bb_solarsystem_en_title( $title, $post_id = 0 ) {
	if ( ! bb_is_en() || ! $post_id || '' !== bb_en_title( $post_id ) || ! bb_solarsystem_is_page_id( $post_id ) ) {
		return $title;
	}

	return __( 'সৌরজগৎ থ্রিডি', 'bichitro-biggan' );
}
add_filter( 'the_title', 'bb_solarsystem_en_title', 11, 2 );

/**
 * The page's stylesheet, three.js and its own module.
 *
 * WordPress 6.5 and later print the import map and the module tags themselves;
 * on an older install the same is printed by hand below.
 */
function bb_solarsystem_assets() {
	if ( ! bb_is_solarsystem_page() ) {
		return;
	}

	$dir    = get_template_directory();
	$uri    = get_template_directory_uri();
	$vendor = $uri . '/assets/vendor/three';
	$css    = bb_asset_path( '/assets/css/solarsystem.css' );
	$js     = bb_asset_path( '/assets/js/solarsystem.js' );

	wp_enqueue_style( 'bb-solarsystem', $uri . $css, array( 'bb-style' ), bb_file_version( $dir . $css ) );

	if ( ! function_exists( 'wp_enqueue_script_module' ) ) {
		add_action( 'wp_head', 'bb_solarsystem_print_import_map', 1 );
		add_action( 'wp_footer', 'bb_solarsystem_print_module', 20 );
		return;
	}

	wp_register_script_module( 'three', $vendor . '/three.module.min.js', array(), BB_THREE_VERSION );

	$deps = array( 'three' );

	foreach ( bb_solarsystem_addons() as $addon ) {
		wp_register_script_module( 'three/addons/' . $addon, $vendor . '/addons/' . $addon, array( 'three' ), BB_THREE_VERSION );
		$deps[] = 'three/addons/' . $addon;
	}

	wp_enqueue_script_module( 'bb-solarsystem', $uri . $js, $deps, bb_file_version( $dir . $js ) );
}
add_action( 'wp_enqueue_scripts', 'bb_solarsystem_assets' );

/**
 * Import map for WordPress before 6.5, which has no script-module API.
 */
function bb_solarsystem_print_import_map() {
	$vendor  = get_template_directory_uri() . '/assets/vendor/three';
	$imports = array( 'three' => $vendor . '/three.module.min.js?ver=' . BB_THREE_VERSION );

	foreach ( bb_solarsystem_addons() as $addon ) {
		$imports[ 'three/addons/' . $addon ] = $vendor . '/addons/' . $addon . '?ver=' . BB_THREE_VERSION;
	}

	echo '<script type="importmap">' . wp_json_encode( array( 'imports' => $imports ), JSON_UNESCAPED_SLASHES ) . "</script>\n";
}

/**
 * The page's module tag for WordPress before 6.5.
 */
function bb_solarsystem_print_module() {
	$js = bb_asset_path( '/assets/js/solarsystem.js' );

	printf(
		'<script type="module" src="%s"></script>' . "\n",
		esc_url( get_template_directory_uri() . $js . '?ver=' . bb_file_version( get_template_directory() . $js ) )
	);
}

/**
 * A body class, for anything outside the stage that needs to know.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function bb_solarsystem_body_class( $classes ) {
	if ( bb_is_solarsystem_page() ) {
		$classes[] = 'bb-solar-page';
	}

	return $classes;
}
add_filter( 'body_class', 'bb_solarsystem_body_class' );

/**
 * The SEO title and description the page uses when its own fields are empty.
 *
 * @return array{title:string,description:string}
 */
function bb_solarsystem_seo_defaults() {
	return array(
		'title'       => __( 'সৌরজগৎ থ্রিডি: সূর্য আর আটটি গ্রহ ঘুরে দেখুন', 'bichitro-biggan' ),
		'description' => __( 'ইন্টারঅ্যাক্টিভ থ্রিডি সৌরজগৎ: মাউস বা আঙুলে ঘুরিয়ে দেখুন সূর্য আর আটটি গ্রহ, গ্রহে ক্লিক করে বাংলায় জানুন মজার তথ্য, কিংবা ঘুরে আসুন গাইডেড ট্যুরে।', 'bichitro-biggan' ),
	);
}

/**
 * Fill the page's SEO context: title, description and the 1200×630 share
 * picture. Anything written in the page's own SEO box, and a featured image,
 * still win. On the English edition the SEO box's English fields are the ones
 * read (lang.php answers bb_seo_get_field), and the defaults come translated.
 *
 * @param array $ctx SEO context from bb_seo_get_context().
 * @return array
 */
function bb_solarsystem_seo_context( $ctx ) {
	if ( ! bb_is_solarsystem_page() || ! ( $ctx['post'] instanceof WP_Post ) ) {
		return $ctx;
	}

	$post_id  = $ctx['post']->ID;
	$defaults = bb_solarsystem_seo_defaults();

	if ( ! bb_seo_get_field( $post_id, 'bb_seo_title' ) ) {
		$ctx['title'] = bb_seo_join_title( array( $defaults['title'], get_bloginfo( 'name' ) ) );
	}

	if ( ! bb_seo_get_field( $post_id, 'bb_seo_description' ) ) {
		$ctx['description'] = $defaults['description'];
	}

	if ( ! has_post_thumbnail( $post_id ) ) {
		$ctx['image']        = get_template_directory_uri() . ( bb_is_en() ? '/assets/img/solarsystem-og-en.jpg' : '/assets/img/solarsystem-og.jpg' );
		$ctx['image_width']  = 1200;
		$ctx['image_height'] = 630;
	}

	return $ctx;
}
add_filter( 'bb_seo_context', 'bb_solarsystem_seo_context', 20 );

/**
 * The <title> tag. inc/seo-frontend.php only sets it from the page's own SEO
 * field; without one, the page gets the Bengali default rather than its bare
 * page title.
 *
 * @param string $title Title so far (empty unless something set it).
 * @return string
 */
function bb_solarsystem_document_title( $title ) {
	if ( '' !== $title || ! bb_is_solarsystem_page() || bb_seo_plugin_active() ) {
		return $title;
	}

	$ctx = bb_seo_get_context();

	return $ctx['title'] ? $ctx['title'] : $title;
}
add_filter( 'pre_get_document_title', 'bb_solarsystem_document_title', 20 );

/**
 * The solar system page's address in the edition being served, or '' while
 * no published page answers to the slug — the header then shows no link
 * rather than one that leads nowhere.
 *
 * @return string
 */
function bb_solarsystem_url() {
	static $url = null;

	if ( null === $url ) {
		$page = get_page_by_path( 'solarsystem' );
		$url  = ( $page instanceof WP_Post && 'publish' === $page->post_status ) ? (string) get_permalink( $page ) : '';
		$url  = (string) apply_filters( 'bb_solarsystem_url', $url );
	}

	return $url;
}

/**
 * The link to the solar system in the header: icon and word in the top bar,
 * icon alone among the stuck menu's buttons.
 *
 * @param string $place 'topbar' or 'nav'.
 */
function bb_solarsystem_link( $place = 'topbar' ) {
	$url = bb_solarsystem_url();

	if ( '' === $url ) {
		return;
	}

	$icon    = '<svg class="bb-solar-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">'
		. '<ellipse cx="12" cy="12" rx="10" ry="4.2" transform="rotate(-20 12 12)" stroke="currentColor" stroke-width="1.5" opacity=".8"/>'
		. '<circle cx="12" cy="12" r="4" fill="#ffb703"/><circle cx="21" cy="8.7" r="1.8" fill="#4b8fd6"/></svg>';
	$label   = __( 'থ্রিডি সৌরজগৎ ঘুরে দেখুন', 'bichitro-biggan' );
	$current = bb_is_solarsystem_page() ? ' aria-current="page"' : '';

	if ( 'nav' === $place ) {
		printf(
			'<a class="bb-nav-action-btn bb-nav-action-btn--solar" href="%1$s" title="%2$s" aria-label="%2$s"%3$s>%4$s</a>',
			esc_url( $url ),
			esc_attr( $label ),
			$current, // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
			$icon // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
		);
		return;
	}

	printf(
		'<a class="bb-topbar__btn bb-topbar__btn--solar" href="%1$s" title="%2$s" aria-label="%2$s"%3$s>%4$s<span class="bb-topbar__btn-text">%5$s</span></a>',
		esc_url( $url ),
		esc_attr( $label ),
		$current, // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
		$icon, // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
		esc_html__( 'সৌরজগৎ', 'bichitro-biggan' )
	);
}
