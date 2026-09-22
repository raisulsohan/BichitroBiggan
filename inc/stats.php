<?php
/**
 * The site's own statistics.
 *
 * Google Analytics answers to a Google account; this answers to nobody. Every
 * read the site already counts is also written to one small table, broken down
 * by day, article, language, device and where the reader came from — enough to
 * see what is being read, from where, in which edition, without a third party
 * and without an account that can be taken away.
 *
 * What is never stored: the reader's address, a cookie, or anything that could
 * follow a person from one visit to the next. The six-hour "seen it" key in
 * inc/views.php is a hash held in a transient; it expires and is never written
 * here.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bump when the table's shape changes. */
define( 'BB_STATS_SCHEMA', 1 );

/** @return string The table, with the install's prefix. */
function bb_stats_table() {
	global $wpdb;

	return $wpdb->prefix . 'bb_stats';
}

/**
 * Create the table, once, and again whenever its shape changes.
 *
 * One row per day per combination, counted up — not one row per visit. A busy
 * year costs a few thousand rows.
 */
function bb_stats_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = bb_stats_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			day date NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lang char(2) NOT NULL DEFAULT 'bn',
			device varchar(8) NOT NULL DEFAULT 'desktop',
			source varchar(48) NOT NULL DEFAULT 'direct',
			hits int unsigned NOT NULL DEFAULT 0,
			visits int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,post_id,lang,device,source),
			KEY day (day),
			KEY post_id (post_id)
		) {$collate};"
	);

	update_option( 'bb_stats_schema', BB_STATS_SCHEMA, false );
}
add_action( 'after_switch_theme', 'bb_stats_install' );

/** Cheap check on every load; the table is only touched when it is behind. */
function bb_stats_maybe_install() {
	if ( (int) get_option( 'bb_stats_schema', 0 ) === BB_STATS_SCHEMA ) {
		return;
	}

	bb_stats_install();
}
add_action( 'init', 'bb_stats_maybe_install' );

/* -------------------------------------------------------------------------
 * What a visit looks like
 * ---------------------------------------------------------------------- */

/**
 * Where the reader came from, as a short label.
 *
 * The full address is not kept — only which site sent them, and only for the
 * handful worth naming. Everything else is its own domain, or "direct".
 *
 * @param string $referrer The page the reader came from.
 * @return string
 */
function bb_stats_source( $referrer ) {
	$referrer = trim( (string) $referrer );

	if ( '' === $referrer ) {
		return 'direct';
	}

	$host = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
	$host = preg_replace( '/^(www|m|l|lm)\./', '', $host );

	if ( '' === $host ) {
		return 'direct';
	}

	// The site linking to itself is not a source.
	$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	if ( $host === preg_replace( '/^www\./', '', $own ) ) {
		return 'internal';
	}

	$known = array(
		'google'    => array( 'google.com', 'google.co.in', 'google.com.bd', 'googleusercontent.com', 'news.google.com' ),
		'facebook'  => array( 'facebook.com', 'fb.com', 'fbclid', 'messenger.com' ),
		'youtube'   => array( 'youtube.com', 'youtu.be' ),
		'whatsapp'  => array( 'whatsapp.com' ),
		'bing'      => array( 'bing.com' ),
		'x'         => array( 'twitter.com', 'x.com', 't.co' ),
		'linkedin'  => array( 'linkedin.com', 'lnkd.in' ),
		'telegram'  => array( 'telegram.org', 't.me' ),
		'instagram' => array( 'instagram.com' ),
		'reddit'    => array( 'reddit.com' ),
		'wikipedia' => array( 'wikipedia.org' ),
	);

	foreach ( $known as $label => $hosts ) {
		foreach ( $hosts as $candidate ) {
			if ( $host === $candidate || substr( $host, -strlen( '.' . $candidate ) ) === '.' . $candidate ) {
				return $label;
			}
		}
	}

	return substr( $host, 0, 48 );
}

/**
 * Phone, tablet or desktop, from the user agent alone.
 *
 * @return string
 */
function bb_stats_device() {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( preg_match( '/ipad|tablet|playbook|silk|(android(?!.*mobile))/', $agent ) ) {
		return 'tablet';
	}

	if ( wp_is_mobile() ) {
		return 'mobile';
	}

	return 'desktop';
}

/**
 * Crawlers, preview fetchers and uptime checks, which should never be counted
 * as readers. The beacon runs in JavaScript, so few of these ever arrive —
 * but headless crawlers do run scripts.
 *
 * @return bool
 */
function bb_stats_is_bot() {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' === $agent ) {
		return true;
	}

	$marks = array( 'bot', 'crawler', 'spider', 'slurp', 'facebookexternalhit', 'headlesschrome', 'phantomjs', 'python-requests', 'curl/', 'wget', 'node-fetch', 'axios', 'lighthouse', 'pingdom', 'uptime', 'preview', 'monitoring' );

	foreach ( $marks as $mark ) {
		if ( false !== strpos( $agent, $mark ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Add one read to today's row.
 *
 * @param int    $post_id  The article, or 0 for a page that is not one.
 * @param string $lang     'bn' or 'en'.
 * @param string $referrer Where the reader came from.
 * @param bool   $first    Whether this is the first page of their visit.
 * @return void
 */
function bb_stats_record( $post_id, $lang = 'bn', $referrer = '', $first = false ) {
	global $wpdb;

	if ( bb_stats_is_bot() ) {
		return;
	}

	// The people who run the site are not its audience.
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}

	$table = bb_stats_table();

	$day    = wp_date( 'Y-m-d' );
	$lang   = ( 'en' === $lang ) ? 'en' : 'bn';
	$device = bb_stats_device();
	$source = bb_stats_source( $referrer );
	$visit  = $first ? 1 : 0;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (day, post_id, lang, device, source, hits, visits)
			 VALUES (%s, %d, %s, %s, %s, 1, %d)
			 ON DUPLICATE KEY UPDATE hits = hits + 1, visits = visits + %d",
			$day,
			(int) $post_id,
			$lang,
			$device,
			$source,
			$visit,
			$visit
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery
}

/* -------------------------------------------------------------------------
 * Reading it back
 * ---------------------------------------------------------------------- */

/**
 * The first and last day either side of a window of N days, ending today.
 *
 * @param int $days How many days back.
 * @return array{0:string,1:string}
 */
function bb_stats_window( $days ) {
	$days = max( 1, (int) $days );
	$to   = wp_date( 'Y-m-d' );
	$from = wp_date( 'Y-m-d', time() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

	return array( $from, $to );
}

/**
 * Totals for a window: reads, visits, how many articles were read.
 *
 * @param int $days Days back.
 * @return array{hits:int,visits:int,articles:int,days:int}
 */
function bb_stats_totals( $days ) {
	global $wpdb;

	list( $from, $to ) = bb_stats_window( $days );
	$table             = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(hits) AS hits, SUM(visits) AS visits, COUNT(DISTINCT CASE WHEN post_id > 0 THEN post_id END) AS articles
			 FROM {$table} WHERE day BETWEEN %s AND %s",
			$from,
			$to
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	return array(
		'hits'     => (int) ( $row['hits'] ?? 0 ),
		'visits'   => (int) ( $row['visits'] ?? 0 ),
		'articles' => (int) ( $row['articles'] ?? 0 ),
		'days'     => max( 1, (int) $days ),
	);
}

/**
 * Reads per day across the window, every day present even when it saw none.
 *
 * @param int $days Days back.
 * @return array<string, array{hits:int,visits:int}>
 */
function bb_stats_daily( $days ) {
	global $wpdb;

	list( $from, $to ) = bb_stats_window( $days );
	$table             = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT day, SUM(hits) AS hits, SUM(visits) AS visits
			 FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY day ORDER BY day ASC",
			$from,
			$to
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	$found = array();

	foreach ( (array) $rows as $row ) {
		$found[ $row['day'] ] = array(
			'hits'   => (int) $row['hits'],
			'visits' => (int) $row['visits'],
		);
	}

	$series = array();

	for ( $i = max( 1, (int) $days ) - 1; $i >= 0; $i-- ) {
		$date            = wp_date( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );
		$series[ $date ] = isset( $found[ $date ] ) ? $found[ $date ] : array(
			'hits'   => 0,
			'visits' => 0,
		);
	}

	return $series;
}

/**
 * The most-read articles of the window.
 *
 * @param int $days  Days back.
 * @param int $limit How many.
 * @return array<int, array{post_id:int,hits:int,bn:int,en:int}>
 */
function bb_stats_top_posts( $days, $limit = 15 ) {
	global $wpdb;

	list( $from, $to ) = bb_stats_window( $days );
	$table             = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id,
					SUM(hits) AS hits,
					SUM(CASE WHEN lang = 'bn' THEN hits ELSE 0 END) AS bn,
					SUM(CASE WHEN lang = 'en' THEN hits ELSE 0 END) AS en
			 FROM {$table}
			 WHERE day BETWEEN %s AND %s AND post_id > 0
			 GROUP BY post_id ORDER BY hits DESC LIMIT %d",
			$from,
			$to,
			max( 1, (int) $limit )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	return array_map(
		function ( $row ) {
			return array(
				'post_id' => (int) $row['post_id'],
				'hits'    => (int) $row['hits'],
				'bn'      => (int) $row['bn'],
				'en'      => (int) $row['en'],
			);
		},
		(array) $rows
	);
}

/**
 * Reads grouped by one column — source, device or lang — biggest first.
 *
 * @param string $column One of source, device, lang.
 * @param int    $days   Days back.
 * @param int    $limit  How many rows.
 * @return array<int, array{label:string,hits:int}>
 */
function bb_stats_grouped( $column, $days, $limit = 12 ) {
	global $wpdb;

	$allowed = array( 'source', 'device', 'lang' );

	if ( ! in_array( $column, $allowed, true ) ) {
		return array();
	}

	list( $from, $to ) = bb_stats_window( $days );
	$table             = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$column} AS label, SUM(hits) AS hits
			 FROM {$table} WHERE day BETWEEN %s AND %s
			 GROUP BY {$column} ORDER BY hits DESC LIMIT %d",
			$from,
			$to,
			max( 1, (int) $limit )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array_map(
		function ( $row ) {
			return array(
				'label' => (string) $row['label'],
				'hits'  => (int) $row['hits'],
			);
		},
		(array) $rows
	);
}

/** The day the table first heard anything, or '' when it is still empty. */
function bb_stats_first_day() {
	global $wpdb;

	$table = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$day = $wpdb->get_var( "SELECT MIN(day) FROM {$table}" );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	return $day ? (string) $day : '';
}
