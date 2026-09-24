<?php
/**
 * Two doors that were standing open.
 *
 * Neither of these blocks anything at the edge — by the time this file runs,
 * WordPress has already been loaded and the work is spent. What they do is
 * stop the two things that actually get small WordPress sites broken into:
 * an endpoint that lets a thousand passwords be tried in one request, and an
 * unlimited number of goes at the login form.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 1. XML-RPC
 * ---------------------------------------------------------------------- */

/*
 * The site does not use it. The publisher talks to the REST API, the mobile
 * app has for years, and nothing here wants pingbacks — but the endpoint was
 * answering, and its system.multicall will carry a thousand password guesses
 * inside a single request, which is why it is the most hammered address on
 * most WordPress sites after the login form.
 *
 * xmlrpc_enabled alone only turns off the methods that need a password. The
 * method list is emptied as well, so there is nothing left to call.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * @param array $methods Everything XML-RPC offers.
 * @return array
 */
function bb_xmlrpc_no_methods( $methods ) {
	unset( $methods );

	return array();
}
add_filter( 'xmlrpc_methods', 'bb_xmlrpc_no_methods' );

/**
 * And stop advertising it: no X-Pingback header, no RSD link in the head.
 *
 * @param array $headers Response headers.
 * @return array
 */
function bb_xmlrpc_no_pingback_header( $headers ) {
	unset( $headers['X-Pingback'] );

	return $headers;
}
add_filter( 'wp_headers', 'bb_xmlrpc_no_pingback_header' );
remove_action( 'wp_head', 'rsd_link' );

/* -------------------------------------------------------------------------
 * 2. How many goes at the login form
 * ---------------------------------------------------------------------- */

/** Wrong passwords allowed from one address before it is made to wait. */
if ( ! defined( 'BB_LOGIN_TRIES' ) ) {
	define( 'BB_LOGIN_TRIES', 8 );
}

/** How long it is counted over, and how long the wait then lasts. */
if ( ! defined( 'BB_LOGIN_WINDOW' ) ) {
	define( 'BB_LOGIN_WINDOW', 15 * MINUTE_IN_SECONDS );
}

/**
 * Where the count for this visitor is kept.
 *
 * The address is hashed with the site's own salt and never written down, the
 * same way the reading statistics treat it.
 *
 * @return string Transient name.
 */
function bb_login_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	return 'bb_login_' . substr( md5( wp_hash( $ip ) ), 0, 20 );
}

/**
 * Count a wrong password.
 *
 * Not the ones this file turned away itself: counting those would push the
 * wait further out every time somebody knocked, and a person who has locked
 * themselves out would never see it end.
 *
 * @param string        $username What was typed. Unused.
 * @param WP_Error|null $error    Why it failed.
 * @return void
 */
function bb_login_note_failure( $username, $error = null ) {
	unset( $username );

	if ( is_wp_error( $error ) && 'bb_login_wait' === $error->get_error_code() ) {
		return;
	}

	$key = bb_login_key();

	set_transient( $key, (int) get_transient( $key ) + 1, BB_LOGIN_WINDOW );
}
add_action( 'wp_login_failed', 'bb_login_note_failure', 10, 2 );

/**
 * A password that worked clears the slate.
 *
 * @return void
 */
function bb_login_forget() {
	delete_transient( bb_login_key() );
}
add_action( 'wp_login', 'bb_login_forget' );

/**
 * Turn away an address that has had its go.
 *
 * Deliberately not applied to REST requests. Application passwords are
 * authenticated through this same filter, and the publisher makes hundreds of
 * calls in a run — locking that out over a login form's counter would break
 * publishing for no security gained.
 *
 * @param null|WP_User|WP_Error $user     What has been decided so far.
 * @param string                $username What was typed.
 * @return null|WP_User|WP_Error
 */
function bb_login_gate( $user, $username = '' ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $user;
	}

	if ( '' === $username ) {
		return $user;
	}

	if ( (int) get_transient( bb_login_key() ) < BB_LOGIN_TRIES ) {
		return $user;
	}

	return new WP_Error(
		'bb_login_wait',
		sprintf(
			// The login screen renders its errors as HTML, the way core's own do.
			/* translators: %d: how many minutes the wait lasts. */
			__( '<strong>অনেকবার ভুল হয়েছে।</strong> %d মিনিট পরে আবার চেষ্টা করুন।', 'bichitro-biggan' ),
			(int) ( BB_LOGIN_WINDOW / MINUTE_IN_SECONDS )
		)
	);
}
add_filter( 'authenticate', 'bb_login_gate', 30, 2 );
