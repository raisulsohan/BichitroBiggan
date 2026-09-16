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
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_register_view_route' );

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

	if ( ! $post_id || 'post' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
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
