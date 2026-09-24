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

/**
 * Bump when the shape changes. 2 added the hour, 3 the country, 4 the time
 * spent reading and the table of things readers do.
 */
define( 'BB_STATS_SCHEMA', 4 );

/** How many minutes count as "right now". */
define( 'BB_STATS_PULSE_MINUTES', 30 );

/** @return string The table, with the install's prefix. */
function bb_stats_table() {
	global $wpdb;

	return $wpdb->prefix . 'bb_stats';
}

/** @return string Where how far, and how long, people read is kept. */
function bb_stats_depth_table() {
	global $wpdb;

	return $wpdb->prefix . 'bb_depth';
}

/** @return string Where the things readers do — share, save, follow a link — are counted. */
function bb_stats_events_table() {
	global $wpdb;

	return $wpdb->prefix . 'bb_events';
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
			country char(2) NOT NULL DEFAULT '',
			hits int unsigned NOT NULL DEFAULT 0,
			visits int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,hour,post_id,lang,device,source,country),
			KEY day (day),
			KEY post_id (post_id)
		) {$collate};"
	);

	// How far down an article people got, in quarters, and how long they stayed.
	dbDelta(
		'CREATE TABLE ' . bb_stats_depth_table() . " (
			day date NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			bucket tinyint unsigned NOT NULL DEFAULT 0,
			n int unsigned NOT NULL DEFAULT 0,
			secs bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,post_id,bucket),
			KEY day (day)
		) {$collate};"
	);

	/*
	 * The things a reader does rather than merely reads: following a link out,
	 * sharing a piece, saving it for later. One row per day per kind per label,
	 * counted up, exactly like the rest.
	 */
	dbDelta(
		'CREATE TABLE ' . bb_stats_events_table() . " (
			day date NOT NULL,
			kind varchar(12) NOT NULL DEFAULT '',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(120) NOT NULL DEFAULT '',
			n int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,kind,post_id,label),
			KEY day (day),
			KEY kind (kind)
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

	if ( is_array( $columns ) && ! in_array( 'country', $columns, true ) ) {
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN country char(2) NOT NULL DEFAULT '' AFTER source" );
	}

	$key_columns = $wpdb->get_col( "SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'", 4 );

	if ( is_array( $key_columns ) && ( ! in_array( 'hour', $key_columns, true ) || ! in_array( 'country', $key_columns, true ) ) ) {
		$wpdb->query( "ALTER TABLE {$table} DROP PRIMARY KEY, ADD PRIMARY KEY (day,hour,post_id,lang,device,source,country)" );
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
 * Which country the reader is in — without ever knowing their address.
 *
 * Three ways, in order of how much they can be trusted:
 *
 * 1. The host's own header, when there is one. A CDN in front of the site has
 *    already resolved the country; it is the most accurate answer available
 *    and costs nothing.
 * 2. The browser's time zone, which the page sends. "Asia/Dhaka" is Bangladesh
 *    and nothing else. This is how the country is known for almost every
 *    reader: no address, no lookup service, no third party told anything.
 * 3. The language the browser asks for — bn-BD says Bangladesh.
 *
 * The answer is two letters, stored as one more column on a counted row. It
 * can never be narrowed to a person.
 *
 * @param string $timezone What the browser reported, e.g. "Asia/Dhaka".
 * @return string Two-letter code, or '' when nothing said.
 */
function bb_stats_country( $timezone = '' ) {
	foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY', 'GEOIP_COUNTRY_CODE' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}

		$code = strtoupper( substr( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ), 0, 2 ) );

		if ( preg_match( '/^[A-Z]{2}$/', $code ) && 'XX' !== $code && 'T1' !== $code ) {
			return $code;
		}
	}

	$timezone = trim( (string) $timezone );

	if ( $timezone ) {
		$map  = bb_stats_timezone_countries();
		$code = isset( $map[ $timezone ] ) ? $map[ $timezone ] : '';

		if ( $code ) {
			return $code;
		}
	}

	// Last resort: bn-BD, en-GB, hi-IN — the region half of the first language.
	$accept = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';

	if ( preg_match( '/^[a-z]{2,3}-([A-Za-z]{2})/', $accept, $match ) ) {
		return strtoupper( $match[1] );
	}

	return '';
}

/**
 * Time zone => country. Every zone the site's readers plausibly sit in, which
 * is most of the inhabited ones; anything unlisted simply counts as unknown.
 *
 * @return array<string, string>
 */
function bb_stats_timezone_countries() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map = array(
		'Asia/Dhaka' => 'BD', 'Asia/Kolkata' => 'IN', 'Asia/Calcutta' => 'IN', 'Asia/Karachi' => 'PK',
		'Asia/Kathmandu' => 'NP', 'Asia/Katmandu' => 'NP', 'Asia/Colombo' => 'LK', 'Asia/Thimphu' => 'BT',
		'Asia/Kabul' => 'AF', 'Asia/Yangon' => 'MM', 'Asia/Rangoon' => 'MM', 'Asia/Bangkok' => 'TH',
		'Asia/Singapore' => 'SG', 'Asia/Kuala_Lumpur' => 'MY', 'Asia/Jakarta' => 'ID', 'Asia/Makassar' => 'ID',
		'Asia/Manila' => 'PH', 'Asia/Ho_Chi_Minh' => 'VN', 'Asia/Saigon' => 'VN', 'Asia/Phnom_Penh' => 'KH',
		'Asia/Vientiane' => 'LA', 'Asia/Hong_Kong' => 'HK', 'Asia/Macau' => 'MO', 'Asia/Taipei' => 'TW',
		'Asia/Shanghai' => 'CN', 'Asia/Urumqi' => 'CN', 'Asia/Chongqing' => 'CN', 'Asia/Tokyo' => 'JP',
		'Asia/Seoul' => 'KR', 'Asia/Pyongyang' => 'KP', 'Asia/Ulaanbaatar' => 'MN',
		'Asia/Dubai' => 'AE', 'Asia/Muscat' => 'OM', 'Asia/Qatar' => 'QA', 'Asia/Bahrain' => 'BH',
		'Asia/Kuwait' => 'KW', 'Asia/Riyadh' => 'SA', 'Asia/Aden' => 'YE', 'Asia/Baghdad' => 'IQ',
		'Asia/Tehran' => 'IR', 'Asia/Jerusalem' => 'IL', 'Asia/Tel_Aviv' => 'IL', 'Asia/Gaza' => 'PS',
		'Asia/Hebron' => 'PS', 'Asia/Amman' => 'JO', 'Asia/Beirut' => 'LB', 'Asia/Damascus' => 'SY',
		'Asia/Nicosia' => 'CY', 'Asia/Istanbul' => 'TR', 'Europe/Istanbul' => 'TR', 'Asia/Baku' => 'AZ',
		'Asia/Tbilisi' => 'GE', 'Asia/Yerevan' => 'AM', 'Asia/Tashkent' => 'UZ', 'Asia/Almaty' => 'KZ',
		'Asia/Bishkek' => 'KG', 'Asia/Dushanbe' => 'TJ', 'Asia/Ashgabat' => 'TM',
		'Europe/London' => 'GB', 'Europe/Dublin' => 'IE', 'Europe/Lisbon' => 'PT', 'Europe/Madrid' => 'ES',
		'Europe/Paris' => 'FR', 'Europe/Brussels' => 'BE', 'Europe/Amsterdam' => 'NL', 'Europe/Luxembourg' => 'LU',
		'Europe/Berlin' => 'DE', 'Europe/Zurich' => 'CH', 'Europe/Vienna' => 'AT', 'Europe/Rome' => 'IT',
		'Europe/Malta' => 'MT', 'Europe/Prague' => 'CZ', 'Europe/Bratislava' => 'SK', 'Europe/Warsaw' => 'PL',
		'Europe/Budapest' => 'HU', 'Europe/Ljubljana' => 'SI', 'Europe/Zagreb' => 'HR', 'Europe/Belgrade' => 'RS',
		'Europe/Sarajevo' => 'BA', 'Europe/Skopje' => 'MK', 'Europe/Tirane' => 'AL', 'Europe/Athens' => 'GR',
		'Europe/Sofia' => 'BG', 'Europe/Bucharest' => 'RO', 'Europe/Chisinau' => 'MD', 'Europe/Kiev' => 'UA',
		'Europe/Kyiv' => 'UA', 'Europe/Minsk' => 'BY', 'Europe/Moscow' => 'RU', 'Asia/Yekaterinburg' => 'RU',
		'Asia/Novosibirsk' => 'RU', 'Asia/Vladivostok' => 'RU', 'Europe/Riga' => 'LV', 'Europe/Tallinn' => 'EE',
		'Europe/Vilnius' => 'LT', 'Europe/Helsinki' => 'FI', 'Europe/Stockholm' => 'SE', 'Europe/Oslo' => 'NO',
		'Europe/Copenhagen' => 'DK', 'Atlantic/Reykjavik' => 'IS',
		'America/New_York' => 'US', 'America/Detroit' => 'US', 'America/Chicago' => 'US', 'America/Denver' => 'US',
		'America/Phoenix' => 'US', 'America/Los_Angeles' => 'US', 'America/Anchorage' => 'US', 'Pacific/Honolulu' => 'US',
		'America/Toronto' => 'CA', 'America/Vancouver' => 'CA', 'America/Edmonton' => 'CA', 'America/Winnipeg' => 'CA',
		'America/Halifax' => 'CA', 'America/St_Johns' => 'CA', 'America/Mexico_City' => 'MX', 'America/Tijuana' => 'MX',
		'America/Guatemala' => 'GT', 'America/Havana' => 'CU', 'America/Jamaica' => 'JM', 'America/Panama' => 'PA',
		'America/Bogota' => 'CO', 'America/Lima' => 'PE', 'America/Caracas' => 'VE', 'America/Santiago' => 'CL',
		'America/Argentina/Buenos_Aires' => 'AR', 'America/Sao_Paulo' => 'BR', 'America/Bahia' => 'BR',
		'America/Manaus' => 'BR', 'America/Montevideo' => 'UY', 'America/Asuncion' => 'PY', 'America/La_Paz' => 'BO',
		'Africa/Cairo' => 'EG', 'Africa/Tripoli' => 'LY', 'Africa/Tunis' => 'TN', 'Africa/Algiers' => 'DZ',
		'Africa/Casablanca' => 'MA', 'Africa/Khartoum' => 'SD', 'Africa/Addis_Ababa' => 'ET', 'Africa/Nairobi' => 'KE',
		'Africa/Kampala' => 'UG', 'Africa/Dar_es_Salaam' => 'TZ', 'Africa/Lagos' => 'NG', 'Africa/Accra' => 'GH',
		'Africa/Abidjan' => 'CI', 'Africa/Dakar' => 'SN', 'Africa/Johannesburg' => 'ZA', 'Africa/Harare' => 'ZW',
		'Africa/Lusaka' => 'ZM', 'Africa/Maputo' => 'MZ', 'Africa/Kinshasa' => 'CD', 'Africa/Luanda' => 'AO',
		'Australia/Sydney' => 'AU', 'Australia/Melbourne' => 'AU', 'Australia/Brisbane' => 'AU',
		'Australia/Perth' => 'AU', 'Australia/Adelaide' => 'AU', 'Australia/Darwin' => 'AU', 'Australia/Hobart' => 'AU',
		'Pacific/Auckland' => 'NZ', 'Pacific/Fiji' => 'FJ', 'Pacific/Port_Moresby' => 'PG', 'Indian/Maldives' => 'MV',
		'Indian/Mauritius' => 'MU',
	);

	/**
	 * Filter the time-zone map, to add a zone the site's readers turn out to use.
	 *
	 * @param array<string, string> $map Zone => two-letter country code.
	 */
	$map = (array) apply_filters( 'bb_stats_timezone_countries', $map );

	return $map;
}

/**
 * Add one read to this hour's row.
 *
 * @param int    $post_id  The article, or 0 for a page that is not one.
 * @param string $lang     'bn' or 'en'.
 * @param string $referrer Where the reader came from.
 * @param bool   $first    Whether this is the first page of their visit.
 * @param string $timezone The browser's time zone, for the country.
 * @return void
 */
function bb_stats_record( $post_id, $lang = 'bn', $referrer = '', $first = false, $timezone = '' ) {
	global $wpdb;

	if ( bb_stats_is_bot() ) {
		return;
	}

	// The people who run the site are not its audience.
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}

	$table = bb_stats_table();

	$day     = wp_date( 'Y-m-d' );
	$hour    = (int) wp_date( 'G' );
	$lang    = ( 'en' === $lang ) ? 'en' : 'bn';
	$device  = bb_stats_device();
	$source  = bb_stats_source( $referrer );
	$country = bb_stats_country( $timezone );
	$visit   = $first ? 1 : 0;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (day, hour, post_id, lang, device, source, country, hits, visits)
			 VALUES (%s, %d, %d, %s, %s, %s, %s, 1, %d)
			 ON DUPLICATE KEY UPDATE hits = hits + 1, visits = visits + %d",
			$day,
			$hour,
			(int) $post_id,
			$lang,
			$device,
			$source,
			$country,
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
 * A search that found nothing is worth more than one that found something: it
 * is a reader telling the site what it does not have.
 *
 * @param string $term    What was typed.
 * @param string $lang    Which edition asked.
 * @param int    $results How many articles came back, or -1 when not known.
 * @return void
 */
function bb_stats_record_search( $term, $lang = 'bn', $results = -1 ) {
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

	if ( 0 === (int) $results ) {
		$searches[ $key ]['miss'] = ( isset( $searches[ $key ]['miss'] ) ? (int) $searches[ $key ]['miss'] : 0 ) + 1;
	}

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
			'miss' => isset( $row['miss'] ) ? (int) $row['miss'] : 0,
			'last' => isset( $row['d'] ) ? (string) $row['d'] : '',
		);
	}

	return $rows;
}

/**
 * The searches that came back with nothing — what readers came wanting and the
 * site does not have. The most useful list on the whole screen.
 *
 * @param int $limit How many.
 * @return array<int, array{term:string,lang:string,hits:int,miss:int,last:string}>
 */
function bb_stats_missed_searches( $limit = 12 ) {
	$rows = array_filter(
		bb_stats_top_searches( 400 ),
		function ( $row ) {
			return $row['miss'] > 0;
		}
	);

	usort(
		$rows,
		function ( $a, $b ) {
			return $b['miss'] <=> $a['miss'];
		}
	);

	return array_slice( $rows, 0, max( 1, (int) $limit ) );
}

/** Forget every address that led nowhere — after they have been fixed. */
function bb_stats_clear_404() {
	delete_option( 'bb_stats_404' );
}

/* -------------------------------------------------------------------------
 * How far down they got
 * ---------------------------------------------------------------------- */

/**
 * Record how much of an article a reader reached, in quarters.
 *
 * Views say a piece was opened; this says whether it was read. The number is
 * rounded to 0, 25, 50, 75 or 100 before it is stored — precise enough to see
 * where a long piece loses people, too coarse to be about anybody.
 *
 * @param int $post_id The article.
 * @param int $percent How far down, 0–100.
 * @param int $seconds How long they had it open and awake, in seconds.
 * @return void
 */
function bb_stats_record_depth( $post_id, $percent, $seconds = 0 ) {
	global $wpdb;

	$post_id = (int) $post_id;

	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( bb_stats_is_bot() || ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return;
	}

	$percent = max( 0, min( 100, (int) $percent ) );

	if ( $percent >= 90 ) {
		$bucket = 100;
	} elseif ( $percent >= 70 ) {
		$bucket = 75;
	} elseif ( $percent >= 45 ) {
		$bucket = 50;
	} elseif ( $percent >= 20 ) {
		$bucket = 25;
	} else {
		$bucket = 0;
	}

	// An hour is generous for the longest piece here; beyond that a tab was
	// left open rather than read, and the average would be nonsense.
	$seconds = max( 0, min( 3600, (int) $seconds ) );

	$table = bb_stats_depth_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (day, post_id, bucket, n, secs) VALUES (%s, %d, %d, 1, %d)
			 ON DUPLICATE KEY UPDATE n = n + 1, secs = secs + %d",
			wp_date( 'Y-m-d' ),
			$post_id,
			$bucket,
			$seconds,
			$seconds
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery
}

/* -------------------------------------------------------------------------
 * What readers do
 * ---------------------------------------------------------------------- */

/**
 * Count something a reader did: followed a link out, shared a piece, saved it.
 *
 * @param string $kind    'out', 'share' or 'save'.
 * @param int    $post_id The article it happened on, or 0.
 * @param string $label   Which link, which network — never who.
 * @return bool Whether it was counted.
 */
function bb_stats_record_event( $kind, $post_id = 0, $label = '' ) {
	global $wpdb;

	$kinds = array( 'out', 'share', 'save' );

	if ( ! in_array( $kind, $kinds, true ) ) {
		return false;
	}

	if ( bb_stats_is_bot() || ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return false;
	}

	$label = sanitize_text_field( (string) $label );
	$label = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 120 ) : substr( $label, 0, 120 );

	$table = bb_stats_events_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (day, kind, post_id, label, n) VALUES (%s, %s, %d, %s, 1)
			 ON DUPLICATE KEY UPDATE n = n + 1",
			wp_date( 'Y-m-d' ),
			$kind,
			(int) $post_id,
			$label
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	return true;
}

/* -------------------------------------------------------------------------
 * Addresses that lead nowhere
 * ---------------------------------------------------------------------- */

/**
 * Is this address somebody poking at the site rather than a reader?
 *
 * A scanner asking for /graphql or /.env is not a broken link, and a browser
 * asking for /.well-known/traffic-advice is asking the site a question, not
 * failing to find a page. Both would otherwise crowd out the addresses on this
 * list that can actually be fixed.
 *
 * @param string $path The address that was asked for.
 * @return bool
 */
function bb_stats_is_probe( $path ) {
	$path = strtolower( (string) $path );

	$marks = array(
		'/.well-known/',
		'/.env',
		'/.git',
		'/.aws',
		'/vendor/',
		'/wp-config',
		'/xmlrpc',
		'/phpmyadmin',
		'/phpunit',
		'/autodiscover',
		'/owa/',
		'/cgi-bin/',
		'graphql',
		'/wp-content/plugins/',
		'/wp-content/uploads/../',
		'.php',
		'.asp',
		'.env',
		'.sql',
		'.zip',
		'.bak',
	);

	foreach ( $marks as $mark ) {
		if ( false !== strpos( $path, $mark ) ) {
			return true;
		}
	}

	/**
	 * Filter whether an address is a probe rather than a broken link.
	 *
	 * @param bool   $is_probe Whether it looks like one.
	 * @param string $path     The address asked for.
	 */
	return (bool) apply_filters( 'bb_stats_is_probe', false, $path );
}

/**
 * Remember a 404, and where the reader was sent from.
 *
 * A broken link is the one thing on this screen that can actually be fixed, so
 * the address matters more than the count. Kept as a small list, newest and
 * most asked first.
 */
function bb_stats_record_not_found() {
	if ( ! is_404() || is_admin() ) {
		return;
	}

	if ( bb_stats_is_bot() || ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
	$query   = (string) wp_parse_url( $request, PHP_URL_QUERY );

	/*
	 * The query belongs to the address here. Most 404s at the site root are
	 * ?p=<id> for a post that no longer exists; without the query every one of
	 * them piles up under "/" and says nothing about what is actually broken.
	 */
	if ( '' !== $query ) {
		$path .= '?' . $query;
	}

	$path = esc_url_raw( $path );

	if ( '' === $path || strlen( $path ) > 190 || bb_stats_is_probe( $path ) ) {
		return;
	}

	$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	$rows = get_option( 'bb_stats_404', array() );

	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	if ( ! isset( $rows[ $path ] ) || ! is_array( $rows[ $path ] ) ) {
		$rows[ $path ] = array(
			'n'    => 0,
			'from' => '',
		);
	}

	$rows[ $path ]['n']    = (int) $rows[ $path ]['n'] + 1;
	$rows[ $path ]['d']    = wp_date( 'Y-m-d' );
	$rows[ $path ]['from'] = $referrer ? bb_stats_source( $referrer ) : $rows[ $path ]['from'];

	if ( count( $rows ) > 200 ) {
		uasort( $rows, function ( $a, $b ) {
			return (int) $b['n'] <=> (int) $a['n'];
		} );
		$rows = array_slice( $rows, 0, 200, true );
	}

	update_option( 'bb_stats_404', $rows, false );
}
add_action( 'template_redirect', 'bb_stats_record_not_found', 30 );

/**
 * The addresses readers asked for and did not get, most asked first.
 *
 * @param int $limit How many.
 * @return array<int, array{path:string,hits:int,from:string,last:string}>
 */
function bb_stats_not_found( $limit = 10 ) {
	$rows = get_option( 'bb_stats_404', array() );

	if ( ! is_array( $rows ) || ! $rows ) {
		return array();
	}

	uasort( $rows, function ( $a, $b ) {
		return (int) $b['n'] <=> (int) $a['n'];
	} );

	$out = array();

	foreach ( array_slice( $rows, 0, max( 1, (int) $limit ), true ) as $path => $row ) {
		$out[] = array(
			'path' => (string) $path,
			'hits' => (int) $row['n'],
			'from' => isset( $row['from'] ) ? (string) $row['from'] : '',
			'last' => isset( $row['d'] ) ? (string) $row['d'] : '',
		);
	}

	return $out;
}

/**
 * The search page counts too — the live overlay is not the only way in, and a
 * reader who presses Enter has told the site the same thing.
 */
function bb_stats_record_page_search() {
	global $wp_query;

	if ( ! is_search() || is_admin() ) {
		return;
	}

	$found = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : -1;

	bb_stats_record_search( get_search_query(), bb_lang(), $found );
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
			// Short for the axis, longer for what the pointer reveals.
			'label'  => $hourly ? wp_date( 'g a', $time ) : wp_date( 'j M', $time ),
			'tip'    => $hourly ? wp_date( 'j M, g:00 a', $time ) : wp_date( 'j M Y', $time ),
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

	$allowed = array( 'source', 'device', 'lang', 'country' );

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

/**
 * How far readers got, across a window.
 *
 * @param string $key Window key.
 * @return array{average:int,buckets:array<int,int>,total:int}
 */
function bb_stats_depth_summary( $key ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );

	// The depth table keeps no hour, so an hourly window falls back to its days.
	$where = str_replace( "CONCAT(day, ' ', LPAD(hour, 2, '0'))", 'day', $where );
	$params = array_map(
		function ( $value ) {
			return substr( (string) $value, 0, 10 );
		},
		$params
	);

	$table = bb_stats_depth_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT bucket, SUM(n) AS n FROM {$table} WHERE {$where} GROUP BY bucket", $params ),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$buckets = array(
		0   => 0,
		25  => 0,
		50  => 0,
		75  => 0,
		100 => 0,
	);

	$total  = 0;
	$weight = 0;

	foreach ( (array) $rows as $row ) {
		$bucket = (int) $row['bucket'];
		$n      = (int) $row['n'];

		if ( ! isset( $buckets[ $bucket ] ) ) {
			continue;
		}

		$buckets[ $bucket ] += $n;
		$total              += $n;
		$weight             += $bucket * $n;
	}

	return array(
		'average' => $total > 0 ? (int) round( $weight / $total ) : 0,
		'buckets' => $buckets,
		'total'   => $total,
	);
}

/**
 * The articles climbing fastest — this period against the one before it.
 *
 * A piece read 30 times after 3 says something a top-ten list never will, so
 * anything with fewer than a handful of reads is left out: with small numbers
 * every rise is a thousand per cent and none of it means anything.
 *
 * @param string $key   Window key.
 * @param int    $limit How many.
 * @return array<int, array{post_id:int,now:int,before:int,change:int}>
 */
function bb_stats_trending( $key, $limit = 8 ) {
	global $wpdb;

	$table = bb_stats_table();
	$reads = function ( $previous ) use ( $wpdb, $table, $key ) {
		list( $where, $params ) = bb_stats_where( $key, $previous );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, SUM(hits) AS hits FROM {$table} WHERE {$where} AND post_id > 0 GROUP BY post_id",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['post_id'] ] = (int) $row['hits'];
		}

		return $out;
	};

	$now    = $reads( false );
	$before = $reads( true );
	$rising = array();

	foreach ( $now as $post_id => $hits ) {
		if ( $hits < 5 ) {
			continue;
		}

		$was = isset( $before[ $post_id ] ) ? $before[ $post_id ] : 0;

		if ( $hits <= $was ) {
			continue;
		}

		$rising[] = array(
			'post_id' => $post_id,
			'now'     => $hits,
			'before'  => $was,
			'change'  => $was > 0 ? (int) round( 100 * ( $hits - $was ) / $was ) : 100,
		);
	}

	usort(
		$rising,
		function ( $a, $b ) {
			if ( $a['change'] === $b['change'] ) {
				return $b['now'] <=> $a['now'];
			}

			return $b['change'] <=> $a['change'];
		}
	);

	return array_slice( $rising, 0, max( 1, (int) $limit ) );
}

/**
 * Where visits begin — the first page a reader lands on.
 *
 * @param string $key   Window key.
 * @param int    $limit How many.
 * @return array<int, array{post_id:int,visits:int}>
 */
function bb_stats_entry_pages( $key, $limit = 10 ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );
	$table                  = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, SUM(visits) AS visits FROM {$table}
			 WHERE {$where} AND visits > 0
			 GROUP BY post_id ORDER BY visits DESC LIMIT %d",
			array_merge( $params, array( max( 1, (int) $limit ) ) )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array_map(
		function ( $row ) {
			return array(
				'post_id' => (int) $row['post_id'],
				'visits'  => (int) $row['visits'],
			);
		},
		(array) $rows
	);
}

/**
 * Which day of the week people read on.
 *
 * @param string $key Window key.
 * @return array<int, int> 0 = Sunday … 6 = Saturday.
 */
function bb_stats_by_weekday( $key ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );
	$table                  = bb_stats_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DAYOFWEEK(day) AS weekday, SUM(hits) AS hits FROM {$table} WHERE {$where} GROUP BY weekday",
			$params
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$days = array_fill( 0, 7, 0 );

	foreach ( (array) $rows as $row ) {
		// MySQL counts Sunday as 1.
		$days[ ( (int) $row['weekday'] + 6 ) % 7 ] = (int) $row['hits'];
	}

	return $days;
}

/**
 * How far, and how long, each article was read — by post, for the table.
 *
 * @param string $key Window key.
 * @return array<int, array{depth:int,seconds:int,n:int}>
 */
function bb_stats_depth_by_post( $key ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );

	// The depth table keeps no hour, so an hourly window falls back to its days.
	$where  = str_replace( "CONCAT(day, ' ', LPAD(hour, 2, '0'))", 'day', $where );
	$params = array_map(
		function ( $value ) {
			return substr( (string) $value, 0, 10 );
		},
		$params
	);

	$table = bb_stats_depth_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, SUM(n) AS n, SUM(bucket * n) AS weight, SUM(secs) AS secs
			 FROM {$table} WHERE {$where} GROUP BY post_id",
			$params
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$out = array();

	foreach ( (array) $rows as $row ) {
		$n = max( 1, (int) $row['n'] );

		$out[ (int) $row['post_id'] ] = array(
			'depth'   => (int) round( (int) $row['weight'] / $n ),
			'seconds' => (int) round( (int) $row['secs'] / $n ),
			'n'       => (int) $row['n'],
		);
	}

	return $out;
}

/**
 * The things readers did, of one kind, biggest first.
 *
 * @param string $kind  'out', 'share' or 'save'.
 * @param string $key   Window key.
 * @param int    $limit How many.
 * @return array<int, array{label:string,post_id:int,hits:int}>
 */
function bb_stats_events( $kind, $key, $limit = 10 ) {
	global $wpdb;

	list( $where, $params ) = bb_stats_where( $key );

	$where  = str_replace( "CONCAT(day, ' ', LPAD(hour, 2, '0'))", 'day', $where );
	$params = array_map(
		function ( $value ) {
			return substr( (string) $value, 0, 10 );
		},
		$params
	);

	$table  = bb_stats_events_table();
	$group  = ( 'save' === $kind ) ? 'post_id' : 'label';
	$select = ( 'save' === $kind ) ? "'' AS label, post_id" : 'label, 0 AS post_id';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$select}, SUM(n) AS n FROM {$table}
			 WHERE {$where} AND kind = %s
			 GROUP BY {$group} ORDER BY n DESC LIMIT %d",
			array_merge( $params, array( $kind, max( 1, (int) $limit ) ) )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array_map(
		function ( $row ) {
			return array(
				'label'   => (string) $row['label'],
				'post_id' => (int) $row['post_id'],
				'hits'    => (int) $row['n'],
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
