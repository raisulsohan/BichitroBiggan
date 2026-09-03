<?php
/**
 * Live search — a REST endpoint plus the Bengali date helpers it needs.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Bengali numerals and month names
 * ---------------------------------------------------------------------- */

function bb_bn_digits( $value ) {
	return str_replace(
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
		array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' ),
		(string) $value
	);
}

function bb_bn_months() {
	return array(
		1  => 'জানুয়ারি',
		2  => 'ফেব্রুয়ারি',
		3  => 'মার্চ',
		4  => 'এপ্রিল',
		5  => 'মে',
		6  => 'জুন',
		7  => 'জুলাই',
		8  => 'আগস্ট',
		9  => 'সেপ্টেম্বর',
		10 => 'অক্টোবর',
		11 => 'নভেম্বর',
		12 => 'ডিসেম্বর',
	);
}

/**
 * "৩০ জুলাই ২০২৬"
 */
function bb_bn_date( $post_id = null ) {
	$raw = get_the_date( 'j|n|Y', $post_id );

	if ( ! $raw || false === strpos( $raw, '|' ) ) {
		return get_the_date( '', $post_id );
	}

	list( $day, $month, $year ) = explode( '|', $raw );
	$months = bb_bn_months();
	$name   = isset( $months[ (int) $month ] ) ? $months[ (int) $month ] : $month;

	return bb_bn_digits( $day ) . ' ' . $name . ' ' . bb_bn_digits( $year );
}

/* -------------------------------------------------------------------------
 * Multibyte-safe helpers with graceful fallbacks
 * ---------------------------------------------------------------------- */

function bb_str_len( $str ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $str, 'UTF-8' ) : strlen( $str );
}

function bb_str_sub( $str, $start, $length = null ) {
	if ( function_exists( 'mb_substr' ) ) {
		return null === $length ? mb_substr( $str, $start, null, 'UTF-8' ) : mb_substr( $str, $start, $length, 'UTF-8' );
	}
	return null === $length ? substr( $str, $start ) : substr( $str, $start, $length );
}

function bb_str_pos( $haystack, $needle ) {
	if ( function_exists( 'mb_stripos' ) ) {
		return mb_stripos( $haystack, $needle, 0, 'UTF-8' );
	}
	return stripos( $haystack, $needle );
}

/**
 * A short passage around the first match, with the term highlighted.
 * The text is escaped before any markup is added.
 */
function bb_search_snippet( $text, $term, $length = 150 ) {
	$text = wp_strip_all_tags( strip_shortcodes( $text ) );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return '';
	}

	$total = bb_str_len( $text );
	$pos   = '' === $term ? false : bb_str_pos( $text, $term );

	if ( false === $pos ) {
		$start = 0;
	} else {
		$start = max( 0, $pos - 45 );
	}

	$snippet = bb_str_sub( $text, $start, $length );
	$prefix  = $start > 0 ? '…' : '';
	$suffix  = ( $start + $length ) < $total ? '…' : '';

	$safe = esc_html( $snippet );

	if ( '' !== $term ) {
		$quoted = preg_quote( esc_html( $term ), '/' );
		$safe   = preg_replace( '/(' . $quoted . ')/iu', '<mark>$1</mark>', $safe );
	}

	return $prefix . $safe . $suffix;
}

/* -------------------------------------------------------------------------
 * REST endpoint
 * ---------------------------------------------------------------------- */

function bb_register_search_route() {
	register_rest_route(
		'bb/v1',
		'/search',
		array(
			'methods'             => 'GET',
			'callback'            => 'bb_rest_search',
			'permission_callback' => '__return_true',
			'args'                => array(
				'q' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_register_search_route' );

function bb_rest_search( WP_REST_Request $request ) {
	$term = trim( (string) $request->get_param( 'q' ) );

	if ( bb_str_len( $term ) < 2 ) {
		return rest_ensure_response( array(
			'total'   => 0,
			'results' => array(),
		) );
	}

	$query = new WP_Query( array(
		'post_type'           => array( 'post', 'page' ),
		'post_status'         => 'publish',
		's'                   => $term,
		'posts_per_page'      => 8,
		'ignore_sticky_posts' => 1,
	) );

	$results = array();

	while ( $query->have_posts() ) {
		$query->the_post();

		$post_obj = get_post();
		$source   = $post_obj->post_excerpt ? $post_obj->post_excerpt : $post_obj->post_content;

		$results[] = array(
			'id'      => get_the_ID(),
			'title'   => wp_strip_all_tags( get_the_title() ),
			'url'     => get_permalink(),
			'date'    => bb_bn_date(),
			'excerpt' => bb_search_snippet( $source, $term ),
		);
	}

	wp_reset_postdata();

	return rest_ensure_response( array(
		'total'   => (int) $query->found_posts,
		'results' => $results,
	) );
}
