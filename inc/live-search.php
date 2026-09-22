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
 * Bengali numerals, month names and dates live in functions.php:
 * bb_bangla_number(), bb_bangla_month() and bb_bangla_date(). This file used
 * to carry a second copy of all three.
 * ---------------------------------------------------------------------- */

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
				'q'    => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'lang' => array(
					'required'          => false,
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_register_search_route' );

function bb_rest_search( WP_REST_Request $request ) {
	$term = trim( (string) $request->get_param( 'q' ) );

	/*
	 * /wp-json/ carries no /en of its own, so the script says which edition
	 * is asking. Everything below — titles, links, dates, which posts are
	 * eligible at all — then comes back in that language.
	 */
	if ( 'en' === $request->get_param( 'lang' ) ) {
		bb_lang( 'en' );
	}

	if ( bb_str_len( $term ) < 2 ) {
		return rest_ensure_response( array(
			'total'   => 0,
			'results' => array(),
		) );
	}

	// What readers look for is worth knowing; who looked is not.
	if ( function_exists( 'bb_stats_record_search' ) ) {
		bb_stats_record_search( $term, bb_lang() );
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

		if ( bb_is_en() ) {
			$english = (string) get_post_meta( $post_obj->ID, 'bb_en_content', true );
			$source  = ( '' !== $english ) ? $english : $source;
		}

		$results[] = array(
			'id'      => get_the_ID(),
			'title'   => wp_strip_all_tags( get_the_title() ),
			'url'     => get_permalink(),
			'date'    => bb_bangla_date(),
			'excerpt' => bb_search_snippet( $source, $term ),
		);
	}

	wp_reset_postdata();

	return rest_ensure_response( array(
		'total'   => (int) $query->found_posts,
		'results' => $results,
	) );
}
