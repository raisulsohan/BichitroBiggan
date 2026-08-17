<?php
/**
 * Customizer options.
 *
 * Only the things that benefit from a live preview stay here — the logo,
 * the header line and the contact email. Everything else moved to the
 * dashboard page: Theme Settings (admin.php?page=bb-theme-settings).
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category dropdown choices, shared with the dashboard settings page.
 */
function bb_category_choices() {
	$choices = array( 0 => __( '— Select Category —', 'bichitro-biggan' ) );

	$cats = get_categories( array( 'hide_empty' => false ) );
	foreach ( $cats as $cat ) {
		$choices[ $cat->term_id ] = $cat->name . ' (' . $cat->count . ')';
	}

	return $choices;
}

function bb_customize_register( $wp_customize ) {

	/* ---------------------------------------------------------------
	 * Site identity extras
	 * ------------------------------------------------------------ */
	$wp_customize->add_setting( 'bb_tagline_text', array(
		'default'           => bb_default_tagline(),
		'sanitize_callback' => 'wp_kses_post',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_tagline_text', array(
		'label'       => __( 'Header Tagline / Bio', 'bichitro-biggan' ),
		'section'     => 'title_tagline',
		'type'        => 'textarea',
		'description' => __( 'Italic tagline text displayed next to the header logo.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_contact_email', array(
		'default'           => 'bichitrobiggan@gmail.com',
		'sanitize_callback' => 'sanitize_email',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_contact_email', array(
		'label'       => __( 'Contact Email', 'bichitro-biggan' ),
		'section'     => 'title_tagline',
		'type'        => 'email',
		'description' => __( 'Email copied to clipboard when clicking the ✉ icon in top bar and footer.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_fallback_image', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'bb_fallback_image', array(
		'label'       => __( 'Default Fallback Image', 'bichitro-biggan' ),
		'section'     => 'title_tagline',
		'description' => __( 'Image displayed when a post does not have a featured image.', 'bichitro-biggan' ),
	) ) );

	/* ---------------------------------------------------------------
	 * Header & Masthead controls — live sliders & toggles
	 * ------------------------------------------------------------ */
	$wp_customize->add_section( 'bb_header_masthead', array(
		'title'       => __( 'Header & Masthead Layout', 'bichitro-biggan' ),
		'priority'    => 21,
		'description' => __( 'Drag sliders to customize logo height, tagline font size, tagline max width, and YouTube button with live preview.', 'bichitro-biggan' ),
	) );

	/* 1. Logo sizes */
	$bb_header_sliders = array(
		'bb_logo_height' => array(
			'label'   => __( 'Header Logo Height (px)', 'bichitro-biggan' ),
			'default' => 70,
			'min'     => 30,
			'max'     => 200,
		),
		'bb_logo_height_sticky' => array(
			'label'   => __( 'Sticky Navigation Logo Height (px)', 'bichitro-biggan' ),
			'default' => 34,
			'min'     => 16,
			'max'     => 90,
		),
		'bb_logo_height_footer' => array(
			'label'   => __( 'Footer Logo Height (px)', 'bichitro-biggan' ),
			'default' => 44,
			'min'     => 16,
			'max'     => 140,
		),
		'bb_tagline_font_size' => array(
			'label'   => __( 'Tagline / Bio Font Size (px)', 'bichitro-biggan' ),
			'default' => 12,
			'min'     => 9,
			'max'     => 20,
		),
		'bb_tagline_max_width' => array(
			'label'   => __( 'Tagline Max Width (px)', 'bichitro-biggan' ),
			'default' => 520,
			'min'     => 200,
			'max'     => 900,
		),
		'bb_yt_icon_size' => array(
			'label'   => __( 'YouTube Icon Size (px)', 'bichitro-biggan' ),
			'default' => 24,
			'min'     => 16,
			'max'     => 48,
		),
		'bb_yt_font_size' => array(
			'label'   => __( 'YouTube Button Text Size (px)', 'bichitro-biggan' ),
			'default' => 12,
			'min'     => 9,
			'max'     => 18,
		),
	);

	foreach ( $bb_header_sliders as $key => $cfg ) {
		$wp_customize->add_setting( $key, array(
			'default'           => $cfg['default'],
			'sanitize_callback' => 'absint',
			'transport'         => 'postMessage',
		) );
		$wp_customize->add_control( $key, array(
			'label'       => $cfg['label'],
			'section'     => 'bb_header_masthead',
			'type'        => 'range',
			'input_attrs' => array( 'min' => $cfg['min'], 'max' => $cfg['max'], 'step' => 1 ),
		) );
	}

	/* 2. Text logo size (fallback) */
	$wp_customize->add_setting( 'bb_logo_text_size', array(
		'default'           => 38,
		'sanitize_callback' => 'absint',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_logo_text_size', array(
		'label'       => __( 'Text Logo Size (px) — when no image is uploaded', 'bichitro-biggan' ),
		'section'     => 'bb_header_masthead',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 14, 'max' => 64, 'step' => 1 ),
	) );

	$wp_customize->add_setting( 'bb_show_youtube', array(
		'default'           => true,
		'sanitize_callback' => 'bb_sanitize_checkbox',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_show_youtube', array(
		'label'       => __( 'Show YouTube Button in Header (Desktop)', 'bichitro-biggan' ),
		'section'     => 'bb_header_masthead',
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'bb_show_youtube_mobile', array(
		'default'           => false,
		'sanitize_callback' => 'bb_sanitize_checkbox',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_show_youtube_mobile', array(
		'label'       => __( 'Show YouTube Button on Mobile (< 640px)', 'bichitro-biggan' ),
		'section'     => 'bb_header_masthead',
		'type'        => 'checkbox',
		'description' => __( 'Uncheck to hide the YouTube button on mobile screens so the logo stays clean and centered.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_youtube_text', array(
		'default'           => 'সাবস্ক্রাইব করুন',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_youtube_text', array(
		'label'       => __( 'YouTube Button Text', 'bichitro-biggan' ),
		'section'     => 'bb_header_masthead',
		'type'        => 'text',
		'description' => __( 'e.g. সাবস্ক্রাইব করুন, YouTube Channel, or leave blank for icon only.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_youtube_url', array(
		'default'           => 'https://www.youtube.com/@bigganbichitro',
		'sanitize_callback' => 'esc_url_raw',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_youtube_url', array(
		'label'       => __( 'YouTube Channel URL', 'bichitro-biggan' ),
		'section'     => 'bb_header_masthead',
		'type'        => 'url',
	) );

	/* ---------------------------------------------------------------
	 * Homepage layout — live slider controls
	 * ------------------------------------------------------------ */
	$wp_customize->add_panel( 'bb_layout', array(
		'title'       => __( 'Homepage Layout', 'bichitro-biggan' ),
		'priority'    => 23,
		'description' => __( 'Drag sliders to adjust block heights and widths with live preview. Click Publish to save.', 'bichitro-biggan' ),
	) );

	$bb_layout_sections = array(
		'bb_layout_hero'    => __( 'Hero Mosaic', 'bichitro-biggan' ),
		'bb_layout_block1'  => __( 'Block 1 — Featured Card & Side List', 'bichitro-biggan' ),
		'bb_layout_block23' => __( 'Blocks 2 & 3 — Cards & Wide Rows', 'bichitro-biggan' ),
		'bb_layout_block45' => __( 'Blocks 4 & 5 — Tall & Dark Cards', 'bichitro-biggan' ),
		'bb_layout_lists'   => __( 'All Posts & Category Pages', 'bichitro-biggan' ),
		'bb_layout_modal'   => __( 'Article Popup Reading Modal (পপ-আপ রিডার)', 'bichitro-biggan' ),
	);

	$bb_section_priority = 10;
	foreach ( $bb_layout_sections as $bb_id => $bb_title ) {
		$wp_customize->add_section( $bb_id, array(
			'title'    => $bb_title,
			'panel'    => 'bb_layout',
			'priority' => $bb_section_priority,
		) );
		$bb_section_priority += 10;
	}

	foreach ( bb_layout_settings() as $bb_key => $bb_cfg ) {
		$unit_label = isset( $bb_cfg['unit'] ) ? $bb_cfg['unit'] : 'px';
		$wp_customize->add_setting( $bb_key, array(
			'default'           => $bb_cfg['default'],
			'sanitize_callback' => 'absint',
			'transport'         => 'postMessage',
		) );
		$wp_customize->add_control( $bb_key, array(
			'label'       => $bb_cfg['label'] . ' (' . $unit_label . ')',
			'section'     => $bb_cfg['section'],
			'type'        => 'range',
			'input_attrs' => array(
				'min'  => $bb_cfg['min'],
				'max'  => $bb_cfg['max'],
				'step' => isset( $bb_cfg['step'] ) ? $bb_cfg['step'] : 1,
			),
		) );
	}

	/* ---------------------------------------------------------------
	 * Hero Mosaic — Category & Post selectors for Slots 1 to 4
	 * ------------------------------------------------------------ */
	$bb_post_list = bb_post_choices();
	$bb_cat_list  = array( 0 => __( '— All Categories —', 'bichitro-biggan' ) ) + bb_category_choices();

	$bb_hero_slot_defs = array(
		1 => array(
			'title' => __( 'Hero Slot 1 — Main Large Post (Left)', 'bichitro-biggan' ),
		),
		2 => array(
			'title' => __( 'Hero Slot 2 — Top Right Post', 'bichitro-biggan' ),
		),
		3 => array(
			'title' => __( 'Hero Slot 3 — Bottom Left/Mid Post', 'bichitro-biggan' ),
		),
		4 => array(
			'title' => __( 'Hero Slot 4 — Bottom Right / Podcast Post', 'bichitro-biggan' ),
		),
	);

	foreach ( $bb_hero_slot_defs as $slot_num => $slot_info ) {
		$cat_key  = 'bb_hero_cat_' . $slot_num;
		$post_key = 'bb_hero_slot_' . $slot_num;

		/* Category Filter */
		$wp_customize->add_setting( $cat_key, array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $cat_key, array(
			'label'       => sprintf( __( 'Slot %d — Filter by Category', 'bichitro-biggan' ), $slot_num ),
			'section'     => 'bb_layout_hero',
			'type'        => 'select',
			'choices'     => $bb_cat_list,
			'description' => __( 'Select category to filter posts below.', 'bichitro-biggan' ),
		) );

		/* Post Selector */
		$wp_customize->add_setting( $post_key, array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $post_key, array(
			'label'       => sprintf( __( 'Slot %d — Post', 'bichitro-biggan' ), $slot_num ),
			'section'     => 'bb_layout_hero',
			'type'        => 'select',
			'choices'     => $bb_post_list,
			'description' => __( 'Select specific post or leave on Auto.', 'bichitro-biggan' ),
		) );
	}

	/* Custom Podcast Duration */
	$wp_customize->add_setting( 'bb_podcast_custom_time', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_podcast_custom_time', array(
		'label'       => __( 'Custom Podcast Duration (Optional)', 'bichitro-biggan' ),
		'section'     => 'bb_layout_hero',
		'type'        => 'text',
		'description' => __( 'E.g., "১৫:৪৫ মিনিট" or "45:20". If empty, calculated reading time is shown.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_hero_show_author', array(
		'default'           => true,
		'sanitize_callback' => 'bb_sanitize_checkbox',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_hero_show_author', array(
		'label'       => __( 'Show Author & Date on all Hero Cards', 'bichitro-biggan' ),
		'section'     => 'bb_layout_hero',
		'type'        => 'checkbox',
	) );

	/* Counts change the markup, so these reload the preview rather than
	   updating a variable. */
	$wp_customize->add_setting( 'bb_feature_list_count', array(
		'default'           => 4,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_feature_list_count', array(
		'label'       => __( 'Block 1 Side List Post Count', 'bichitro-biggan' ),
		'section'     => 'bb_layout_block1',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
		'description' => __( 'Number of posts displayed in the side list next to the main card.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_all_posts_count', array(
		'default'           => 6,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_all_posts_count', array(
		'label'       => __( 'All Posts Page — Posts Per Page', 'bichitro-biggan' ),
		'section'     => 'bb_layout_lists',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 2, 'max' => 24, 'step' => 1 ),
	) );

	$wp_customize->add_setting( 'bb_archive_posts_count', array(
		'default'           => 11,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_archive_posts_count', array(
		'label'       => __( 'Category Pages — Posts Per Page', 'bichitro-biggan' ),
		'section'     => 'bb_layout_lists',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 1, 'max' => 48, 'step' => 1 ),
		'description' => __( 'To preview, open a category page in the preview frame.', 'bichitro-biggan' ),
	) );

	$wp_customize->add_setting( 'bb_archive_featured_count', array(
		'default'           => 4,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_archive_featured_count', array(
		'label'       => __( 'Featured Hero Mosaic Post Count', 'bichitro-biggan' ),
		'section'     => 'bb_layout_lists',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 0, 'max' => 4, 'step' => 1 ),
	) );

	/* ---------------------------------------------------------------
	 * Footer Columns (Editor Picks, Popular Posts, Popular Categories)
	 * ------------------------------------------------------------ */
	$wp_customize->add_section( 'bb_footer_columns_section', array(
		'title'       => __( 'Footer 3 Columns (Editor Picks & Popular)', 'bichitro-biggan' ),
		'priority'    => 24,
		'description' => __( 'Customize headings, post counts, and pick specific articles for Editor Picks and Popular Posts.', 'bichitro-biggan' ),
	) );

	// Thumbnail Toggle for Footer Posts
	$wp_customize->add_setting( 'bb_footer_show_thumbs', array(
		'default'           => false,
		'sanitize_callback' => 'wp_validate_boolean',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_footer_show_thumbs', array(
		'label'       => __( 'ফুটার পোস্টের ছবি/থাম্বনেইল দেখাবেন? (Show Thumbnails)', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'checkbox',
		'description' => __( 'টিক চিহ্ন না দিলে শুধু লেখার টাইটেল, লেখক ও তারিখ দেখাবে।', 'bichitro-biggan' ),
	) );

	// 1. Editor Picks Heading
	$wp_customize->add_setting( 'bb_editor_picks_title', array(
		'default'           => 'EDITOR PICKS',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_editor_picks_title', array(
		'label'       => __( 'Editor Picks Heading', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'text',
	) );

	// Editor Picks Count
	$wp_customize->add_setting( 'bb_editor_picks_count', array(
		'default'           => 3,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_editor_picks_count', array(
		'label'       => __( 'Editor Picks Post Count', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
	) );

	// Editor Picks Individual Slots (1 to 6)
	for ( $bb_ep_i = 1; $bb_ep_i <= 6; $bb_ep_i++ ) {
		$wp_customize->add_setting( "bb_editor_picks_{$bb_ep_i}", array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( "bb_editor_picks_{$bb_ep_i}", array(
			'label'       => sprintf( __( 'Editor Pick #%d (লেখা নির্বাচন করুন)', 'bichitro-biggan' ), $bb_ep_i ),
			'section'     => 'bb_footer_columns_section',
			'type'        => 'select',
			'choices'     => $bb_post_list,
		) );
	}

	// 2. Popular Posts Heading
	$wp_customize->add_setting( 'bb_popular_title', array(
		'default'           => 'POPULAR POSTS',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_popular_title', array(
		'label'       => __( 'Popular Posts Heading', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'text',
	) );

	// Popular Posts Count
	$wp_customize->add_setting( 'bb_popular_count', array(
		'default'           => 3,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_popular_count', array(
		'label'       => __( 'Popular Posts Count', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
	) );

	// 3. Popular Category Heading
	$wp_customize->add_setting( 'bb_popular_cat_title', array(
		'default'           => 'POPULAR CATEGORY',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_popular_cat_title', array(
		'label'       => __( 'Popular Category Heading', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'text',
	) );

	// Popular Category Count
	$wp_customize->add_setting( 'bb_popular_cat_count', array(
		'default'           => 5,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_popular_cat_count', array(
		'label'       => __( 'Popular Category Count', 'bichitro-biggan' ),
		'section'     => 'bb_footer_columns_section',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 3, 'max' => 10, 'step' => 1 ),
	) );

	/* ---------------------------------------------------------------
	 * Footer Settings (About, Contact, Social)
	 * ------------------------------------------------------------ */
	$wp_customize->add_section( 'bb_footer_section', array(
		'title'       => __( 'Footer About & Contact Info', 'bichitro-biggan' ),
		'priority'    => 25,
		'description' => __( 'Customize footer About Us, Contact, and Subscribe elements with live preview.', 'bichitro-biggan' ),
	) );

	// About Us Title
	$wp_customize->add_setting( 'bb_about_title', array(
		'default'           => 'ABOUT US',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_about_title', array(
		'label'       => __( 'About Us Heading', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'type'        => 'text',
	) );

	// About Us Text
	$wp_customize->add_setting( 'bb_about_text', array(
		'default'           => 'BichitroBiggan is your source for science news, discoveries, and insights. We bring you the latest updates, research breakthroughs, and engaging stories from the world of science and technology.',
		'sanitize_callback' => 'wp_kses_post',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_about_text', array(
		'label'       => __( 'About Us Description', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'type'        => 'textarea',
	) );

	// Contact Us Title
	$wp_customize->add_setting( 'bb_contact_title', array(
		'default'           => 'CONTACT US',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_contact_title', array(
		'label'       => __( 'Contact Us Heading', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'type'        => 'text',
	) );

	// Contact Email
	$wp_customize->add_setting( 'bb_contact_email', array(
		'default'           => 'bichitrobiggan@gmail.com',
		'sanitize_callback' => 'sanitize_email',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_contact_email_footer', array(
		'label'       => __( 'Contact Email (copies on ✉ click)', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'settings'    => 'bb_contact_email',
		'type'        => 'email',
	) );

	// Subscribe Us Title
	$wp_customize->add_setting( 'bb_subscribe_title', array(
		'default'           => 'SUBSCRIBE US',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_subscribe_title', array(
		'label'       => __( 'Subscribe Us Heading', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'type'        => 'text',
	) );

	// Footer YouTube URL
	$wp_customize->add_setting( 'bb_footer_youtube_url', array(
		'default'           => 'https://www.youtube.com/@bigganbichitro',
		'sanitize_callback' => 'esc_url_raw',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_footer_youtube_url', array(
		'label'       => __( 'Footer YouTube Channel URL', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'type'        => 'url',
	) );

	// Footer YouTube Link Text
	$wp_customize->add_setting( 'bb_footer_youtube_text', array(
		'default'           => 'সাবস্ক্রাইব করুন',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_footer_youtube_text', array(
		'label'       => __( 'Footer YouTube Link Text', 'bichitro-biggan' ),
		'section'     => 'bb_footer_section',
		'type'        => 'text',
	) );

	/* Live preview for the blogname in the logo. */
	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->get_setting( 'blogname' )->transport = 'postMessage';
		$wp_customize->selective_refresh->add_partial( 'blogname', array(
			'selector'        => '.bb-logo__text',
			'render_callback' => function () {
				return get_bloginfo( 'name', 'display' );
			},
		) );
	}
}
add_action( 'customize_register', 'bb_customize_register' );

function bb_sanitize_checkbox( $checked ) {
	return ( isset( $checked ) && true === (bool) $checked );
}
