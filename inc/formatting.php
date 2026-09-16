<?php
/**
 * Bengali dates, numerals and reading time.
 *
 * Every date and every number the site shows a reader passes through here.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Month names in Bengali, for the archive list and anywhere else the site
 * shows a month without a full date.
 */
function bb_bangla_month( $month_number ) {
	$months = array(
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

	$month_number = (int) $month_number;

	return isset( $months[ $month_number ] ) ? $months[ $month_number ] : $GLOBALS['wp_locale']->get_month( $month_number );
}

/**
 * Today's date for the top bar, in Bengali to match the rest of the site.
 */
function bb_bangla_today() {
	$days = array(
		'Sunday'    => 'রবিবার',
		'Monday'    => 'সোমবার',
		'Tuesday'   => 'মঙ্গলবার',
		'Wednesday' => 'বুধবার',
		'Thursday'  => 'বৃহস্পতিবার',
		'Friday'    => 'শুক্রবার',
		'Saturday'  => 'শনিবার',
	);

	$day_en = wp_date( 'l' );
	$day    = isset( $days[ $day_en ] ) ? $days[ $day_en ] : $day_en;

	return sprintf(
		'%s, %s %s %s',
		$day,
		bb_bangla_number( wp_date( 'j' ) ),
		bb_bangla_month( wp_date( 'n' ) ),
		bb_bangla_number( wp_date( 'Y' ) )
	);
}

/**
 * Convert English digits to Bengali digits.
 */
function bb_bangla_number( $number ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$bn = array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' );
	return str_replace( $en, $bn, (string) $number );
}

/**
 * Post date in full Bengali format.
 */
function bb_bangla_date( $post_id = null ) {
	$post_id  = $post_id ? $post_id : get_the_ID();
	$day      = get_the_date( 'j', $post_id );
	$month_en = get_the_date( 'F', $post_id );
	$year     = get_the_date( 'Y', $post_id );

	$months = array(
		'January'   => 'জানুয়ারি',
		'February'  => 'ফেব্রুয়ারি',
		'March'     => 'মার্চ',
		'April'     => 'এপ্রিল',
		'May'       => 'মে',
		'June'      => 'জুন',
		'July'      => 'জুলাই',
		'August'    => 'আগস্ট',
		'September' => 'সেপ্টেম্বর',
		'October'   => 'অক্টোবর',
		'November'  => 'নভেম্বর',
		'December'  => 'ডিসেম্বর',
	);

	$month_bn = isset( $months[ $month_en ] ) ? $months[ $month_en ] : $month_en;
	$day_bn   = bb_bangla_number( $day );
	$year_bn  = bb_bangla_number( $year );

	return $day_bn . ' ' . $month_bn . ', ' . $year_bn;
}

/**
 * Post date in the site's format (Bengali).
 */
function bb_post_date( $post_id = null ) {
	return bb_bangla_date( $post_id );
}

/**
 * Calculate post reading time in Bengali.
 */
function bb_reading_time( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$minutes = (int) get_post_meta( $post_id, 'bb_read_minutes', true );

	if ( ! $minutes ) {
		$minutes = bb_calculate_reading_minutes( $post_id );
		update_post_meta( $post_id, 'bb_read_minutes', $minutes );
	}

	return bb_bangla_number( $minutes ) . ' মিনিট';
}

/**
 * Count the words once. A homepage card used to re-read the whole post to
 * print "৬ মিনিট", dozens of times per page load.
 */
function bb_calculate_reading_minutes( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );
	$clean   = wp_strip_all_tags( strip_shortcodes( $content ) );
	$words   = preg_split( '/\s+/u', trim( $clean ) );
	$count   = is_array( $words ) ? count( array_filter( $words ) ) : 0;

	return max( 1, (int) ceil( $count / 180 ) );
}

function bb_flush_reading_time( $post_id ) {
	delete_post_meta( $post_id, 'bb_read_minutes' );
}
add_action( 'save_post', 'bb_flush_reading_time' );

/**
 * A timestamp as a Bengali date — used for the "last updated" line.
 */
function bb_bangla_timestamp( $timestamp ) {
	return bb_bangla_number( wp_date( 'j', $timestamp ) )
		. ' ' . bb_bangla_month( wp_date( 'n', $timestamp ) )
		. ', ' . bb_bangla_number( wp_date( 'Y', $timestamp ) );
}

/**
 * Very small view counter — powers the 👁 figure on single posts.
 * Note: a full-page cache will suppress these increments.
 */
