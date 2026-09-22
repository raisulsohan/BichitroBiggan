<?php
/**
 * Type-to-search for the post pickers in Theme Settings and the Customizer.
 *
 * The dropdowns are filled with the newest 400 posts. The site passed 390 some
 * time ago, so the older half of the archive had quietly become unreachable
 * from those menus. This searches the whole archive instead.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search published posts for the picker. Editors only.
 */
function bb_ajax_search_posts() {
	check_ajax_referer( 'bb_post_search', 'nonce' );

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( array(), 403 );
	}

	$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

	if ( '' === $term ) {
		wp_send_json_success( array() );
	}

	$posts = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => 30,
			's'                => $term,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);

	$results = array();

	foreach ( $posts as $post_item ) {
		$cats   = get_the_category( $post_item->ID );
		$prefix = ( ! empty( $cats ) && ! is_wp_error( $cats ) ) ? '[' . $cats[0]->name . '] ' : '';

		$results[] = array(
			'id'    => $post_item->ID,
			'label' => $prefix . get_the_title( $post_item ) . ' (' . get_the_date( 'j M Y', $post_item ) . ')',
		);
	}

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_bb_search_posts', 'bb_ajax_search_posts' );

/**
 * The handful of values the search box needs.
 *
 * @return array
 */
function bb_post_search_data() {
	return array(
		'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		'nonce'       => wp_create_nonce( 'bb_post_search' ),
		'placeholder' => __( 'Search posts…', 'bichitro-biggan' ),
		'searching'   => __( 'Searching…', 'bichitro-biggan' ),
		'noResults'   => __( 'Nothing found', 'bichitro-biggan' ),
	);
}
