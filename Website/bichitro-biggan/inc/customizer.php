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
	 * Logo sizing — live preview, so it stays in the Customizer
	 * ------------------------------------------------------------ */
	$wp_customize->add_section( 'bb_logo', array(
		'title'       => __( 'Logo Sizing', 'bichitro-biggan' ),
		'priority'    => 21,
		'description' => sprintf(
			/* translators: %s: link to the theme settings page */
			__( 'Upload image logo in: Site Identity → Logo. Adjust logo heights here. For other theme settings, visit: %s', 'bichitro-biggan' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=bb-theme-settings' ) ) . '" target="_blank">' . esc_html__( 'Theme Settings', 'bichitro-biggan' ) . '</a>'
		),
	) );

	$bb_logo_sizes = array(
		'bb_logo_height' => array(
			'label'   => __( 'Header Logo Height (px)', 'bichitro-biggan' ),
			'default' => 56,
			'min'     => 20,
			'max'     => 160,
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
	);

	foreach ( $bb_logo_sizes as $key => $cfg ) {
		$wp_customize->add_setting( $key, array(
			'default'           => $cfg['default'],
			'sanitize_callback' => 'absint',
			'transport'         => 'postMessage',
		) );
		$wp_customize->add_control( $key, array(
			'label'       => $cfg['label'],
			'section'     => 'bb_logo',
			'type'        => 'range',
			'input_attrs' => array( 'min' => $cfg['min'], 'max' => $cfg['max'], 'step' => 1 ),
		) );
	}

	$wp_customize->add_setting( 'bb_logo_text_size', array(
		'default'           => 38,
		'sanitize_callback' => 'absint',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bb_logo_text_size', array(
		'label'       => __( 'Text Logo Size (px) — when no image logo is uploaded', 'bichitro-biggan' ),
		'section'     => 'bb_logo',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 14, 'max' => 64, 'step' => 1 ),
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
		$wp_customize->add_setting( $bb_key, array(
			'default'           => $bb_cfg['default'],
			'sanitize_callback' => 'absint',
			'transport'         => 'postMessage',
		) );
		$wp_customize->add_control( $bb_key, array(
			'label'       => $bb_cfg['label'] . ' (px)',
			'section'     => $bb_cfg['section'],
			'type'        => 'range',
			'input_attrs' => array(
				'min'  => $bb_cfg['min'],
				'max'  => $bb_cfg['max'],
				'step' => 1,
			),
		) );
	}

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
		'default'           => 5,
		'sanitize_callback' => 'absint',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'bb_archive_featured_count', array(
		'label'       => __( 'তার মধ্যে বড় মোজাইকে কয়টি', 'bichitro-biggan' ),
		'section'     => 'bb_layout_lists',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 0, 'max' => 5, 'step' => 1 ),
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
