<?php
/**
 * Plugin Name: Bichitro Biggan — Updater Diagnostics
 * Description: Temporary. Shows exactly what the theme's GitHub updater sees, so a missing "Update Available" notice can be traced in one look. Delete once the cause is found.
 * Version: 1.0.0
 * Author: Raisul Sohan
 *
 * INSTALL
 *   Upload as a normal plugin (Plugins → Add New → Upload) and activate,
 *   then open Tools → Updater Diagnostics.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_management_page(
		'Updater Diagnostics',
		'Updater Diagnostics',
		'update_themes',
		'bb-updater-debug',
		'bb_updater_debug_page'
	);
} );

function bb_updater_debug_row( $label, $value, $ok = null ) {
	$mark = '';
	if ( true === $ok ) {
		$mark = ' ✅';
	} elseif ( false === $ok ) {
		$mark = ' ❌';
	}

	printf(
		'<tr><td style="width:280px"><strong>%s</strong></td><td><code>%s</code>%s</td></tr>',
		esc_html( $label ),
		esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ),
		$mark // phpcs:ignore WordPress.Security.EscapeOutput
	);
}

function bb_updater_debug_page() {
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( 'nope' );
	}

	$theme = wp_get_theme( get_template() );
	$slug  = $theme->get_stylesheet();
	$css   = $theme->get_stylesheet_directory() . '/style.css';

	$headers = get_file_data( $css, array(
		'version' => 'Version',
		'uri'     => 'Update URI',
		'path'    => 'Update Path',
		'branch'  => 'Update Branch',
		'theme'   => 'Theme URI',
	) );

	echo '<div class="wrap"><h1>Updater Diagnostics</h1>';

	echo '<h2>1. What the theme declares</h2><table class="widefat striped">';
	bb_updater_debug_row( 'Theme slug (folder)', $slug );
	bb_updater_debug_row( 'Installed version', $theme->get( 'Version' ) );
	bb_updater_debug_row( 'style.css path', $css, file_exists( $css ) );
	bb_updater_debug_row( 'Update URI header', $headers['uri'] ? $headers['uri'] : '(empty)', (bool) $headers['uri'] );
	bb_updater_debug_row( 'Update Path header', $headers['path'] ? $headers['path'] : '(empty — repo root)' );
	bb_updater_debug_row( 'Update Branch header', $headers['branch'] ? $headers['branch'] : 'main (default)' );
	echo '</table>';

	echo '<h2>2. Is the updater class loaded?</h2><table class="widefat striped">';
	$loaded = class_exists( 'Theme_GitHub_Updater' );
	bb_updater_debug_row( 'Theme_GitHub_Updater exists', $loaded ? 'yes' : 'NO — the new file is not being required', $loaded );
	bb_updater_debug_row( 'Old bb_github_check_for_update()', function_exists( 'bb_github_check_for_update' ) ? 'still present (old updater)' : 'gone (expected)' );

	/* Which file actually declared the class. If this is not the theme's own
	   inc/github-updater.php, another copy won the race and the theme's file
	   hit its class_exists() guard and returned before registering anything. */
	if ( $loaded ) {
		$ref      = new ReflectionClass( 'Theme_GitHub_Updater' );
		$declared = $ref->getFileName();
		$expected = wp_normalize_path( get_template_directory() . '/inc/github-updater.php' );
		$same     = wp_normalize_path( $declared ) === $expected;
		bb_updater_debug_row( 'class declared in', $declared, $same );
		bb_updater_debug_row( 'expected location', $expected );
		if ( ! $same ) {
			echo '<tr><td colspan="2"><strong style="color:#b32d2e">A second copy of the updater declared the class first. '
				. 'The theme\'s own file then returned at its class_exists() guard without registering any filters.</strong></td></tr>';
		}
	}

	bb_updater_debug_row( 'after_setup_theme already fired', did_action( 'after_setup_theme' ) ? 'yes (' . did_action( 'after_setup_theme' ) . 'x)' : 'no', did_action( 'after_setup_theme' ) > 0 );

	global $wp_filter;
	$hooked_read = isset( $wp_filter['site_transient_update_themes'] ) ? count( $wp_filter['site_transient_update_themes']->callbacks, COUNT_RECURSIVE ) : 0;
	$hooked_set  = isset( $wp_filter['pre_set_site_transient_update_themes'] ) ? count( $wp_filter['pre_set_site_transient_update_themes']->callbacks, COUNT_RECURSIVE ) : 0;
	bb_updater_debug_row( 'callbacks on site_transient_update_themes', $hooked_read, $hooked_read > 0 );
	bb_updater_debug_row( 'callbacks on pre_set_site_transient_update_themes', $hooked_set, $hooked_set > 0 );
	echo '</table>';

	echo '<h2>2b. Walking the constructor step by step</h2><table class="widefat striped">';
	$t2 = wp_get_theme( get_template() );

	$read = function ( $name, $default = '' ) use ( $t2 ) {
		$v = $t2->get( $name );
		if ( ! $v ) {
			$f = $t2->get_stylesheet_directory() . '/style.css';
			if ( is_readable( $f ) ) {
				$d = get_file_data( $f, array( 'h' => $name ) );
				$v = isset( $d['h'] ) ? $d['h'] : '';
			}
		}
		$v = trim( (string) $v );
		return '' !== $v ? $v : $default;
	};

	$src   = $read( 'Update URI', '' );
	$src   = '' === $src ? (string) $t2->get( 'ThemeURI' ) : $src;
	$h     = wp_parse_url( $src, PHP_URL_HOST );
	$p     = trim( (string) wp_parse_url( $src, PHP_URL_PATH ), '/' );
	$hostok = ( 'github.com' === strtolower( (string) $h ) );
	$pathok = ( substr_count( $p, '/' ) >= 1 );

	bb_updater_debug_row( 'resolved source URL', $src ? $src : '(empty)', (bool) $src );
	bb_updater_debug_row( 'parsed host', $h ? $h : '(none)', $hostok );
	bb_updater_debug_row( 'parsed path', $p ? $p : '(none)', $pathok );
	bb_updater_debug_row( 'would pass the github.com gate', ( $hostok && $pathok ) ? 'yes' : 'NO — constructor returns here', $hostok && $pathok );
	bb_updater_debug_row( 'is_admin()', is_admin() ? 'true' : 'false', is_admin() );
	bb_updater_debug_row( 'branch header', $read( 'Update Branch', 'main' ) );
	bb_updater_debug_row( 'path header', $read( 'Update Path', '(none)' ) );
	echo '</table>';

	echo '<h2>3. What GitHub answers right now</h2><table class="widefat striped">';
	$repo   = trim( (string) wp_parse_url( $headers['uri'], PHP_URL_PATH ), '/' );
	$branch = $headers['branch'] ? $headers['branch'] : 'main';
	$path   = trim( $headers['path'], '/' );

	$raw = sprintf(
		'https://raw.githubusercontent.com/%s/%s/%sstyle.css',
		$repo,
		$branch,
		$path ? $path . '/' : ''
	);
	bb_updater_debug_row( 'style.css URL', $raw );

	$res  = wp_remote_get( $raw, array( 'timeout' => 15 ) );
	$code = is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res );
	bb_updater_debug_row( 'HTTP status', $code, 200 === $code );

	$remote_version = '';
	if ( 200 === $code && preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', wp_remote_retrieve_body( $res ), $m ) ) {
		$remote_version = trim( $m[1] );
	}
	bb_updater_debug_row( 'Remote version', $remote_version ? $remote_version : '(could not read)', (bool) $remote_version );

	$newer = $remote_version && version_compare( $remote_version, $theme->get( 'Version' ), '>' );
	bb_updater_debug_row(
		'Newer than installed?',
		sprintf( '%s > %s = %s', $remote_version, $theme->get( 'Version' ), $newer ? 'YES' : 'no' ),
		$newer
	);

	$sha_res = wp_remote_get(
		sprintf( 'https://api.github.com/repos/%s/commits/%s', $repo, $branch ),
		array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/vnd.github.sha', 'User-Agent' => 'bb-debug' ) )
	);
	$sha = is_wp_error( $sha_res ) ? $sha_res->get_error_message() : trim( wp_remote_retrieve_body( $sha_res ) );
	bb_updater_debug_row( 'HEAD commit sha', $sha, (bool) preg_match( '/^[0-9a-f]{40}$/', (string) $sha ) );
	echo '</table>';

	echo '<h2>4. What WordPress is holding</h2><table class="widefat striped">';
	$t = get_site_transient( 'update_themes' );
	bb_updater_debug_row( 'transient exists', $t ? 'yes' : 'no', (bool) $t );
	bb_updater_debug_row( 'last_checked', isset( $t->last_checked ) ? gmdate( 'Y-m-d H:i:s', $t->last_checked ) . ' UTC (' . human_time_diff( $t->last_checked ) . ' ago)' : 'n/a' );
	$has = isset( $t->response[ $slug ] );
	bb_updater_debug_row( 'response[' . $slug . '] present', $has ? 'YES — update should be showing' : 'NO — this is the problem', $has );
	if ( $has ) {
		bb_updater_debug_row( 'offered version', $t->response[ $slug ]['new_version'] );
		bb_updater_debug_row( 'package URL', $t->response[ $slug ]['package'] );
	}
	if ( isset( $t->no_update[ $slug ] ) ) {
		bb_updater_debug_row( 'listed under no_update', 'yes — WordPress thinks it is current' );
	}
	bb_updater_debug_row( 'our cached answer (transient)', get_transient( 'tgu_' . md5( $slug ) ) );
	echo '</table>';

	echo '<h2>5. Fix it now</h2>';
	echo '<form method="post"><p>';
	wp_nonce_field( 'bb_updater_debug' );
	submit_button( 'Clear all update caches and re-check', 'primary', 'bb_clear', false );
	echo '</p></form>';

	if ( isset( $_POST['bb_clear'] ) && check_admin_referer( 'bb_updater_debug' ) ) {
		delete_transient( 'tgu_' . md5( $slug ) );
		delete_transient( 'bb_github_remote_version' );
		delete_site_transient( 'update_themes' );
		wp_update_themes();
		echo '<div class="notice notice-success"><p>Cleared and re-checked. Reload this page, then look at section 4 again.</p></div>';
	}

	echo '<form method="post"><p>';
	wp_nonce_field( 'bb_updater_debug' );
	submit_button( 'Instantiate the updater by hand and re-check', 'secondary', 'bb_boot', false );
	echo '</p></form>';

	if ( isset( $_POST['bb_boot'] ) && check_admin_referer( 'bb_updater_debug' ) && class_exists( 'Theme_GitHub_Updater' ) ) {
		new Theme_GitHub_Updater();
		delete_transient( 'tgu_' . md5( $slug ) );
		delete_site_transient( 'update_themes' );
		wp_update_themes();
		$t3 = get_site_transient( 'update_themes' );
		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			isset( $t3->response[ $slug ] ) ? 'success' : 'error',
			isset( $t3->response[ $slug ] )
				? 'Update appeared after instantiating by hand — so the class was never constructed on a normal load. That is the bug.'
				: 'Still nothing, so the problem is inside the updater rather than in whether it was constructed.'
		);
	}

	echo '</div>';
}
