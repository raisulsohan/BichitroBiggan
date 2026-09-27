<?php
/**
 * Fewer background requests from the dashboard.
 *
 * The host answers 429 Too Many Requests when one address sends a burst, and
 * then refuses that address for a few minutes. An open dashboard is a steady
 * source of such requests: every admin screen runs the Heartbeat API, which
 * posts to admin-ajax.php every 15 to 60 seconds for post locks, the
 * "session expired" prompt and autosave notices. Two or three tabs plus the
 * publisher working beside them is enough to be shut out mid-task.
 *
 * Heartbeat is kept where it does something — the post editor, for the lock
 * that stops two people editing one post, and the Customizer, for its
 * changeset lock — and there it runs at its longest allowed interval, two
 * minutes. Every other admin screen goes without it. Nothing here touches
 * the front of the site: readers never load Heartbeat, and the editor's own
 * autosave runs over REST on its own clock, only when something has changed.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current admin screen has a use for Heartbeat.
 *
 * post.php and post-new.php share the base "post"; customize.php is "customize".
 *
 * @return bool
 */
function bb_heartbeat_wanted() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return $screen && in_array( $screen->base, array( 'post', 'customize' ), true );
}

/**
 * Heartbeat at its slowest: once every two minutes, the longest WordPress allows.
 *
 * The post lock is renewed on every beat and only counts as stale after 150
 * seconds, so two minutes still keeps a post locked while it is being edited.
 *
 * @param array $settings Heartbeat settings.
 * @return array
 */
function bb_heartbeat_settings( $settings ) {
	$settings['interval'] = 120;

	return $settings;
}
add_filter( 'heartbeat_settings', 'bb_heartbeat_settings' );

/**
 * Leave Heartbeat out of every admin screen that has no use for it.
 *
 * Deregistering the script also drops what depends on it (wp-auth-check,
 * autosave), which is the point: none of it does anything on a list table or
 * a settings page. This runs before core enqueues them.
 */
function bb_heartbeat_only_where_needed() {
	if ( bb_heartbeat_wanted() ) {
		return;
	}

	wp_deregister_script( 'heartbeat' );
}
add_action( 'admin_enqueue_scripts', 'bb_heartbeat_only_where_needed', 1 );
