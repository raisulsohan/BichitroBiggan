<?php
/**
 * How many people have read a piece.
 *
 * The count arrives from the browser, because the page itself is usually
 * served from a cache the server never hears about.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bb_get_views( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$views = get_post_meta( $post_id, 'bb_views', true );
	if ( '' !== $views ) {
		return (int) $views;
	}

	// Seed from the previous theme's counter so imported posts keep their totals.
	return (int) get_post_meta( $post_id, 'post_views_count', true );
}

/**
 * Count a read from the browser.
 *
 * Counting in wp_head meant a page served from the cache — which is nearly
 * every page — was never counted at all, while every crawler that asked for
 * an uncached one was. A beacon fires only where JavaScript runs.
 */
function bb_register_view_route() {
	register_rest_route(
		'bb/v1',
		'/view',
		array(
			'methods'             => 'POST',
			'callback'            => 'bb_rest_count_view',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'    => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				/* What the site's own statistics need, and nothing more. */
				'lang'  => array(
					'required'          => false,
					'sanitize_callback' => 'sanitize_key',
				),
				'ref'   => array(
					'required'          => false,
					'sanitize_callback' => 'esc_url_raw',
				),
				'first' => array(
					'required'          => false,
					'sanitize_callback' => 'absint',
				),
				/* The browser's own time zone — how the country is known. */
				'tz'    => array(
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_register_view_route' );

/**
 * How far down the article the reader got, reported as they leave it.
 *
 * A view says the piece was opened. This says whether it was read — and it is
 * the only thing the browser sends that the page could not have said at the
 * moment it loaded.
 */
function bb_register_depth_route() {
	register_rest_route(
		'bb/v1',
		'/depth',
		array(
			'methods'             => 'POST',
			'callback'            => 'bb_rest_count_depth',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'    => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				'depth' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
				'secs'  => array(
					'required'          => false,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_register_depth_route' );

/**
 * The things a reader does rather than reads: follows a link out of an
 * article, shares it, saves it to read later. Each one is a click the page
 * knows about and the server never would.
 */
function bb_register_event_route() {
	register_rest_route(
		'bb/v1',
		'/event',
		array(
			'methods'             => 'POST',
			'callback'            => 'bb_rest_count_event',
			'permission_callback' => '__return_true',
			'args'                => array(
				'kind'  => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
				),
				'id'    => array(
					'required'          => false,
					'sanitize_callback' => 'absint',
				),
				'label' => array(
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_register_event_route' );

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function bb_rest_count_event( WP_REST_Request $request ) {
	if ( ! function_exists( 'bb_stats_record_event' ) ) {
		return rest_ensure_response( array( 'counted' => false ) );
	}

	$counted = bb_stats_record_event(
		(string) $request->get_param( 'kind' ),
		absint( $request->get_param( 'id' ) ),
		(string) $request->get_param( 'label' )
	);

	return rest_ensure_response( array( 'counted' => (bool) $counted ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function bb_rest_count_depth( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'id' ) );
	$depth   = absint( $request->get_param( 'depth' ) );

	if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
		return rest_ensure_response( array( 'counted' => false ) );
	}

	/*
	 * Once per article per visit, the same rule the view counter uses — a
	 * reader who scrolls up and down is one reader who finished it.
	 */
	$once = 'bb_depth_' . md5( $post_id . '|' . bb_visitor_key() );

	if ( get_transient( $once ) ) {
		return rest_ensure_response( array( 'counted' => false ) );
	}

	set_transient( $once, 1, 6 * HOUR_IN_SECONDS );

	if ( function_exists( 'bb_stats_record_depth' ) ) {
		bb_stats_record_depth( $post_id, $depth, absint( $request->get_param( 'secs' ) ) );
	}

	return rest_ensure_response( array( 'counted' => true ) );
}

/**
 * One reader, one post, once every six hours. The address is hashed with the
 * site's own salt and never stored.
 */
function bb_visitor_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	return wp_hash( $ip . '|' . $ua );
}

function bb_rest_count_view( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'id' ) );
	$lang    = 'en' === $request->get_param( 'lang' ) ? 'en' : 'bn';
	$ref     = (string) $request->get_param( 'ref' );
	$first   = (bool) $request->get_param( 'first' );

	$is_article = $post_id && 'post' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );

	/*
	 * The site's own statistics hear about every page, article or not — a
	 * homepage read is still a read. The per-post counter below is separate,
	 * and still only about articles.
	 */
	if ( function_exists( 'bb_stats_record' ) ) {
		bb_stats_record( $is_article ? $post_id : 0, $lang, $ref, $first, (string) $request->get_param( 'tz' ) );
	}

	if ( ! $is_article ) {
		return rest_ensure_response( array( 'counted' => false ) );
	}

	$once = 'bb_seen_' . md5( $post_id . '|' . bb_visitor_key() );

	if ( get_transient( $once ) ) {
		return rest_ensure_response( array( 'counted' => false ) );
	}

	set_transient( $once, 1, 6 * HOUR_IN_SECONDS );

	update_post_meta( $post_id, 'bb_views', bb_get_views( $post_id ) + 1 );
	bb_record_daily_view( $post_id );

	return rest_ensure_response( array( 'counted' => true ) );
}

/**
 * Per-day counts, so "এই সপ্তাহে" can mean posts read this week rather than
 * posts published this week. Five weeks are kept; older days are dropped.
 */
function bb_record_daily_view( $post_id ) {
	$days = get_option( 'bb_views_daily', array() );

	if ( ! is_array( $days ) ) {
		$days = array();
	}

	$today = wp_date( 'Y-m-d' );

	if ( ! isset( $days[ $today ] ) || ! is_array( $days[ $today ] ) ) {
		$days[ $today ] = array();
	}

	$days[ $today ][ $post_id ] = ( isset( $days[ $today ][ $post_id ] ) ? (int) $days[ $today ][ $post_id ] : 0 ) + 1;

	if ( count( $days ) > 35 ) {
		krsort( $days );
		$days = array_slice( $days, 0, 35, true );
	}

	update_option( 'bb_views_daily', $days, false );
}

/**
 * Views per post over the last N days, most read first.
 */
function bb_recent_view_counts( $days_back ) {
	$days   = get_option( 'bb_views_daily', array() );
	$totals = array();

	if ( ! is_array( $days ) ) {
		return $totals;
	}

	$cutoff = wp_date( 'Y-m-d', time() - ( (int) $days_back * DAY_IN_SECONDS ) );

	foreach ( $days as $date => $posts ) {
		if ( (string) $date < $cutoff ) {
			continue;
		}

		foreach ( (array) $posts as $pid => $hits ) {
			$pid            = (int) $pid;
			$totals[ $pid ] = ( isset( $totals[ $pid ] ) ? $totals[ $pid ] : 0 ) + (int) $hits;
		}
	}

	arsort( $totals );

	return $totals;
}

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
