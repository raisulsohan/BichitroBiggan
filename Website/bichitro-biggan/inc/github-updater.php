<?php
/**
 * GitHub Theme Auto-Updater
 *
 * Checks the public GitHub repository for a newer theme version and plugs into
 * the native WordPress theme-update UI so the user sees "Update Available"
 * exactly like any other theme from wordpress.org.
 *
 * @package Bichitro_Biggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Configuration ---------- */

define( 'BB_GITHUB_REPO',       'raisulsohan/BichitroBiggan' );
define( 'BB_GITHUB_BRANCH',     'main' );
define( 'BB_GITHUB_THEME_PATH', 'Website/bichitro-biggan' );

/* ---------- Remote version ---------- */

/**
 * Fetch the remote theme version from GitHub (cached for 6 hours).
 *
 * We read the raw style.css from the repo and parse the `Version:` header.
 *
 * @return string|false Version string or false on failure.
 */
function bb_github_get_remote_version() {
	$cached = get_transient( 'bb_github_remote_version' );
	if ( false !== $cached ) {
		return $cached;
	}

	$url = sprintf(
		'https://raw.githubusercontent.com/%s/%s/%s/style.css',
		BB_GITHUB_REPO,
		BB_GITHUB_BRANCH,
		BB_GITHUB_THEME_PATH
	);

	$response = wp_remote_get( $url, array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'text/plain' ),
	) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );

	if ( preg_match( '/^\s*Version:\s*(.+)/mi', $body, $m ) ) {
		$version = trim( $m[1] );
		set_transient( 'bb_github_remote_version', $version, 6 * HOUR_IN_SECONDS );
		return $version;
	}

	return false;
}

/* ---------- Inject update into WordPress ---------- */

/**
 * Tell WordPress a new version is available when the remote version is higher.
 */
function bb_github_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$theme_slug      = get_template();
	$current_version = wp_get_theme( $theme_slug )->get( 'Version' );
	$remote_version  = bb_github_get_remote_version();

	if ( $remote_version && version_compare( $remote_version, $current_version, '>' ) ) {
		$transient->response[ $theme_slug ] = array(
			'theme'       => $theme_slug,
			'new_version' => $remote_version,
			'url'         => sprintf( 'https://github.com/%s', BB_GITHUB_REPO ),
			'package'     => sprintf(
				'https://github.com/%s/archive/refs/heads/%s.zip',
				BB_GITHUB_REPO,
				BB_GITHUB_BRANCH
			),
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'bb_github_check_for_update' );

/* ---------- Fix extracted directory structure ---------- */

/**
 * GitHub ZIP extracts as:  BichitroBiggan-main/Website/bichitro-biggan/
 * WordPress expects:       bichitro-biggan/
 *
 * This filter moves the correct subdirectory to the top level so WordPress
 * can install it properly.
 */
function bb_github_fix_source( $source, $remote_source, $upgrader, $hook_extra ) {
	// Only act on our own theme.
	if ( ! isset( $hook_extra['theme'] ) || $hook_extra['theme'] !== get_template() ) {
		return $source;
	}

	global $wp_filesystem;

	$theme_subdir = trailingslashit( $source ) . BB_GITHUB_THEME_PATH;

	if ( ! $wp_filesystem->is_dir( $theme_subdir ) ) {
		return $source;
	}

	$corrected = trailingslashit( $remote_source ) . trailingslashit( get_template() );

	// Move Website/bichitro-biggan/ to the top level.
	$wp_filesystem->move( $theme_subdir, $corrected );

	// Remove the leftover BichitroBiggan-main/ directory.
	$wp_filesystem->delete( $source, true );

	return $corrected;
}
add_filter( 'upgrader_source_selection', 'bb_github_fix_source', 10, 4 );

/* ---------- Housekeeping ---------- */

/**
 * Clear the cached remote version after any theme update completes.
 */
function bb_github_clear_cache( $upgrader, $options ) {
	if ( isset( $options['type'] ) && 'theme' === $options['type'] ) {
		delete_transient( 'bb_github_remote_version' );
	}
}
add_action( 'upgrader_process_complete', 'bb_github_clear_cache', 10, 2 );

/**
 * Add a "Check for updates" link on the Themes page for easy manual refresh.
 */
function bb_github_admin_bar_check( $wp_admin_bar ) {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}

	$wp_admin_bar->add_node( array(
		'id'    => 'bb-check-update',
		'title' => '🔄 থিম আপডেট চেক',
		'href'  => admin_url( 'update-core.php?force-check=1' ),
	) );
}
add_action( 'admin_bar_menu', 'bb_github_admin_bar_check', 999 );
