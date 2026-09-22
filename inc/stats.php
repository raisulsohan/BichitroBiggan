<?php
/**
 * The site's own statistics.
 *
 * Google Analytics answers to a Google account; this answers to nobody. Every
 * read the site already counts is also written to one small table, by hour,
 * article, language, device and where the reader came from — enough to see what
 * is being read, from where, in which edition, without a third party and
 * without an account that can be taken away.
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

/** Bump when the table's shape changes. 2 added the hour column. */
define( 'BB_STATS_SCHEMA', 2 );

/** How many minutes count as "right now". */
define( 'BB_STATS_PULSE_MINUTES', 30 );

/** @return string The table, with the install's prefix. */
function bb_stats_table() {
	global $wpdb;

	return $wpdb->prefix . 'bb_stats';
}

/**
 * Create the table, once, and bring it up to date when its shape changes.
 *
 * One row per hour per combination, counted up — not one row per visit. A busy
 * year costs tens of thousands of rows, which is nothing to a database.
 */
function bb_stats_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = bb_stats_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			day date NOT NULL,
			hour tinyint unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lang char(2) NOT NULL DEFAULT 'bn',
			device varchar(8) NOT NULL DEFAULT 'desktop',
			source varchar(48) NOT NULL DEFAULT 'direct',
			hits int unsigned NOT NULL DEFAULT 0,
			visits int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,hour,post_id,lang,device,source),
			KEY day (day),
			KEY post_id (post_id)
		) {$collate};"
	);

	/*
	 * dbDelta adds a column but will not rebuild a primary key, so a table
	 * created before the hour column existed is corrected by hand. Without
	 * this, every hour of a day would collide on the old key and overwrite
	 * the hour before it.
	 */
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

	if ( is_array( $columns ) && ! in_array( 'hour', $columns, true ) ) {
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN hour tinyint unsigned NOT NULL DEFAULT 0 AFTER day" );
	}

	$key_columns = $wpdb->get_col( "SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'", 4 );

	if ( is_array( $key_columns ) && ! in_array( 'hour', $key_columns, true ) ) {
		$wpdb->query( "ALTER TABLE {$table} DROP PRIMARY KEY, ADD PRIMARY KEY (day,hour,post_id,lang,device,source)" );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
		'facebook'  => array( 'facebook.com', 'fb.com', 'messenger.com' ),
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
 * Add one read to this hour's row.
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
	$hour   = (int) wp_date( 'G' );
	$lang   = ( 'en' === $lang ) ? 'en' : 'bn';
	$device = bb_stats_device();
	$source = bb_stats_source( $referrer );
	$visit  = $first ? 1 : 0;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (day, hour, post_id, lang, device, source, hits, visits)
			 VALUES (%s, %d, %d, %s, %s, %s, 1, %d)
			 ON DUPLICATE KEY UPDATE hits = hits + 1, visits = visits + %d",
			$day,
			$hour,
			(int) $post_id,
			$lang,
			$device,
			$source,
			$visit,
			$visit
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	bb_stats_pulse_record();
}

/* -------------------------------------------------------------------------
 * Right now
 * ---------------------------------------------------------------------- */

/**
 * The last half hour of reads, as bare timestamps.
 *
 * Too short a span to be a row in the table, and the only thing the screen
 * needs is how many — so a short list of times is kept and trimmed on every
 * write. Nothing about who; just when.
 */
function bb_stats_pulse_record() {
	$pulse = get_option( 'bb_stats_pulse', array() );

	if ( ! is_array( $pulse ) ) {
		$pulse = array();
	}

	$now    = time();
	$cutoff = $now - ( BB_STATS_PULSE_MINUTES * MINUTE_IN_SECONDS );

	$pulse   = array_values( array_filter( $pulse, function ( $time ) use ( $cutoff ) {
		return (int) $time >= $cutoff;
	} ) );
	$pulse[] = $now;

	// A sudden rush must not turn one option into a megabyte.
	if ( count( $pulse ) > 500 ) {
		$pulse = array_slice( $pulse, -500 );
	}

	update_option( 'bb_stats_pulse', $pulse, false );
}

/**
 * How many reads in the last N minutes.
 *
 * @param int $minutes How far back.
 * @return int
 */
function bb_stats_pulse( $minutes = 30 ) {
	$pulse = get_option( 'bb_stats_pulse', array() );

	if ( ! is_array( $pulse ) ) {
		return 0;
	}

	$cutoff = time() - ( max( 1, (int) $minutes ) * MINUTE_IN_SECONDS );

	return count( array_filter( $pulse, function ( $time ) use ( $cutoff ) {
		return (int) $time >= $cutoff;
	} ) );
}

/* -------------------------------------------------------------------------
 * What readers looked for
 * ---------------------------------------------------------------------- */

/**
 * Remember a search term, with how often it has been asked for.
 *
 * What a reader typed into the search box is about the site, not about them —
 * it says which subjects people arrive wanting and do not find easily.
 *
 * @param string $term What was typed.
 * @param string $lang Which edition asked.
 * @return void
 */
function bb_stats_record_search( $term, $lang = 'bn' ) {
	$term = trim( wp_strip_all_tags( (string) $term ) );

	if ( bb_str_len( $term ) < 2 || bb_str_len( $term ) > 60 ) {
		return;
	}

	if ( bb_stats_is_bot() || ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return;
	}

	$searches = get_option( 'bb_stats_searches', array() );

	if ( ! is_array( $searches ) ) {
		$searches = array();
	}

	$key = ( 'en' === $lang ? 'en|' : 'bn|' ) . $term;

	if ( ! isset( $searches[ $key ] ) || ! is_array( $searches[ $key ] ) ) {
		$searches[ $key ] = array(
			'n' => 0,
			'd' => wp_date( 'Y-m-d' ),
		);
	}

	$searches[ $key ]['n'] = (int) $searches[ $key ]['n'] + 1;
	$searches[ $key ]['d'] = wp_date( 'Y-m-d' );

	// Keep the 400 most asked; the tail is noise and one-offs.
	if ( count( $searches ) > 400 ) {
		uasort( $searches, function ( $a, $b ) {
			return (int) $b['n'] <=> (int) $a['n'];
		} );
		$searches = array_slice( $searches, 0, 400, true );
	}

	update_option( 'bb_stats_searches', $searches, false );
}

/**
 * The most asked-for search terms.
 *
 * @param int $limit How many.
 * @return array<int, array{term:string,lang:string,hits:int,last:string}>
 */
function bb_stats_top_searches( $limit = 12 ) {
	$searches = get_option( 'bb_stats_searches', array() );

	if ( ! is_array( $searches ) || ! $searches ) {
		return array();
	}

	uasort( $searches, function ( $a, $b ) {
		return (int) $b['n'] <=> (int) $a['n'];
	} );

	$rows = array();

	foreach ( array_slice( $searches, 0, max( 1, (int) $limit ), true ) as $key => $row ) {
		$parts  = explode( '|', $key, 2 );
		$rows[] = array(
			'lang' => $parts[0],
			'term' => isset( $parts[1] ) ? $parts[1] : $key,
			'hits' => (int) $row['n'],
			'last' => isset( $row['d'] ) ? (string) $row['d'] : '',
		);
	}

	return $rows;
}

/**
 * The search page counts too — the live overlay is not the only way in, and a
 * reader who presses Enter has told the site the same thing.
 */
function bb_stats_record_page_search() {
	if ( ! is_search() || is_admin() ) {
		return;
	}

	bb_stats_record_search( get_search_query(), bb_lang() );
}
add_action( 'template_redirect', 'bb_stats_record_page_search', 20 );

/* -------------------------------------------------------------------------
 * Reading it back
 * ---------------------------------------------------------------------- */

/**
 * The windows the screen offers. 24 hours is the one it opens on.
 *
 * @return array<string, array{mode:string,length:int}>
 */
function bb_stats_windows() {
	return array(
		'24h'  => array(
			'mode'   => 'hours',
			'length' => 24,
		),
		'7d'   => array(
			'mode'   => 'days',
			'length' => 7,
		),
		'30d'  => array(
			'mode'   => 'days',
			'length' => 30,
		),
		'90d'  => array(
			'mode'   => 'days',
			'length' => 90,
		),
		'365d' => array(
			'mode'   => 'days',
			'length' => 365,
		),
	);
}

/**
 * The WHERE clause for a window, and the values it needs.
 *
 * Hours are compared as "Y-m-d H" strings built from the two columns, which
 * sorts correctly and needs no date arithmetic in SQL.
 *
 * @param string $key      A key of bb_stats_windows().
 * @param bool   $previous The window immediately before this one, for comparison.
 * @return array{0:string,1:array}
 */
function bb_stats_where( $key, $previous = false ) {
	$windows = bb_stats_windows();
	$window  = isset( $windows[ $key ] ) ? $windows[ $key ] : $windows['24h'];
	$length  = (int) $window['length'];

	if ( 'hours' === $window['mode'] ) {
		$back  = $previous ? $length * 2 - 1 : $length - 1;
		$until = $previous ? $length : 0;

		$from = wp_date( 'Y-m-d H', time() - ( $back * HOUR_IN_SECONDS ) );
		$to   = wp_date( 'Y-m-d H', time() - ( $until * HOUR_IN_SECONDS ) );

		return array(
			"CONCAT(day, ' ', LPAD(hour, 2, '0')) BETWEEN %s AND %s",
			array( $from, $to ),
		);
	}

	$back  = $previous ? $length * 2 - 1 : $length - 1;
	$until = $previous ? $length : 0;

	$from = wp_date( 'Y-m-d', time() - ( $back * DAY_IN_SECONDS ) );
	$to   = wp_date( 'Y-m-d', time() - ( $until * DAY_IN_SECONDS ) );

	return array( 'day BETWEEN %s AND %s', array( $from, $to ) );
}

/**
 * Totals for a window.
 *
 * @param string $key      Window key.
 * @param bool   $previous The window before it.
 * @return array{hits:int,visits:int,articles:int,article_hits:int,other_hits:int}
 */
function bb_stats_totals( $key, $previous = false ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key, $previous );
	$table                  = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(hits) AS hits,
					SUM(visits) AS visits,
					COUNT(DISTINCT CASE WHEN post_id > 0 THEN post_id END) AS articles,
					SUM(CASE WHEN post_id > 0 THEN hits ELSE 0 END) AS article_hits,
					SUM(CASE WHEN post_id = 0 THEN hits ELSE 0 END) AS other_hits
			 FROM {$table} WHERE {$where}",
			$params
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array(
		'hits'         => (int) ( $row['hits'] ?? 0 ),
		'visits'       => (int) ( $row['visits'] ?? 0 ),
		'articles'     => (int) ( $row['articles'] ?? 0 ),
		'article_hits' => (int) ( $row['article_hits'] ?? 0 ),
		'other_hits'   => (int) ( $row['other_hits'] ?? 0 ),
	);
}

/**
 * The line under the chart: one point per hour, or per day.
 *
 * @param string $key Window key.
 * @return array<string, array{hits:int,visits:int,label:string}>
 */
function bb_stats_series( $key ) {
	global $wpdb;

	$windows = bb_stats_windows();
	$window  = isset( $windows[ $key ] ) ? $windows[ $key ] : $windows['24h'];
	$length  = (int) $window['length'];
	$hourly  = ( 'hours' === $window['mode'] );

	list( $where, $params ) = bb_stats_where( $key );
	$table                  = bb_stats_table();

	$select = $hourly ? "CONCAT(day, ' ', LPAD(hour, 2, '0')) AS slot" : 'day AS slot';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$select}, SUM(hits) AS hits, SUM(visits) AS visits
			 FROM {$table} WHERE {$where} GROUP BY slot ORDER BY slot ASC",
			$params
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$found = array();

	foreach ( (array) $rows as $row ) {
		$found[ $row['slot'] ] = array(
			'hits'   => (int) $row['hits'],
			'visits' => (int) $row['visits'],
		);
	}

	$series = array();

	for ( $i = $length - 1; $i >= 0; $i-- ) {
		$time = $hourly ? time() - ( $i * HOUR_IN_SECONDS ) : time() - ( $i * DAY_IN_SECONDS );
		$slot = $hourly ? wp_date( 'Y-m-d H', $time ) : wp_date( 'Y-m-d', $time );

		$series[ $slot ] = array(
			'hits'   => isset( $found[ $slot ] ) ? $found[ $slot ]['hits'] : 0,
			'visits' => isset( $found[ $slot ] ) ? $found[ $slot ]['visits'] : 0,
			'label'  => $hourly ? wp_date( 'H:00', $time ) : wp_date( 'j M', $time ),
		);
	}

	return $series;
}

/**
 * The most-read articles of the window.
 *
 * @param string $key   Window key.
 * @param int    $limit How many.
 * @return array<int, array{post_id:int,hits:int,bn:int,en:int}>
 */
function bb_stats_top_posts( $key, $limit = 15 ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );
	$table                  = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id,
					SUM(hits) AS hits,
					SUM(CASE WHEN lang = 'bn' THEN hits ELSE 0 END) AS bn,
					SUM(CASE WHEN lang = 'en' THEN hits ELSE 0 END) AS en
			 FROM {$table}
			 WHERE {$where} AND post_id > 0
			 GROUP BY post_id ORDER BY hits DESC LIMIT %d",
			array_merge( $params, array( max( 1, (int) $limit ) ) )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
 * @param string $key    Window key.
 * @param int    $limit  How many rows.
 * @return array<int, array{label:string,hits:int}>
 */
function bb_stats_grouped( $column, $key, $limit = 12 ) {
	global $wpdb;

	$allowed = array( 'source', 'device', 'lang' );

	if ( ! in_array( $column, $allowed, true ) ) {
		return array();
	}

	list( $where, $params ) = bb_stats_where( $key );
	$table                  = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$column} AS label, SUM(hits) AS hits
			 FROM {$table} WHERE {$where}
			 GROUP BY {$column} ORDER BY hits DESC LIMIT %d",
			array_merge( $params, array( max( 1, (int) $limit ) ) )
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

/**
 * When in the day people read, averaged across the window.
 *
 * @param string $key Window key.
 * @return array<int, int> Hour (0–23) => reads.
 */
function bb_stats_by_hour( $key ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );
	$table                  = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT hour, SUM(hits) AS hits FROM {$table} WHERE {$where} GROUP BY hour",
			$params
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$hours = array_fill( 0, 24, 0 );

	foreach ( (array) $rows as $row ) {
		$hours[ (int) $row['hour'] ] = (int) $row['hits'];
	}

	return $hours;
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
