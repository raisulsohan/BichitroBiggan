<?php
/**
 * Keep the login names out of public view.
 *
 * WordPress publishes the list of users at /wp-json/wp/v2/users, and turns
 * /?author=1 into a redirect to that author's page. Between them, anyone can
 * collect every account name on the site — which is half of a password guess
 * already done. The author pages themselves stay public; it is the account
 * names and the enumeration that stop here.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Close the users endpoint to anyone not signed in.
 *
 * Signed-in editors still need it — the block editor and the author dropdown
 * both read it — so it is only withdrawn from logged-out requests.
 *
 * @param array $endpoints REST endpoints.
 * @return array
 */
function bb_restrict_rest_users( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	unset( $endpoints['/wp/v2/users'] );
	unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

	return $endpoints;
}
add_filter( 'rest_endpoints', 'bb_restrict_rest_users' );

/**
 * Refuse /?author=1 and friends.
 *
 * The scan works by counting up from 1 and reading the name each redirect
 * lands on. An author's real page — /author/<name>/ — is untouched.
 */
function bb_block_author_enumeration() {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}

	// With plain permalinks this is how an author page is addressed at all.
	if ( ! get_option( 'permalink_structure' ) ) {
		return;
	}

	if ( ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'bb_block_author_enumeration', 0 );

/**
 * Leave the authors out of the sitemap.
 *
 * Their pages are linked from every article they wrote, so nothing is lost
 * from search — but a sitemap of account names is a ready-made list.
 *
 * @param WP_Sitemaps_Provider $provider Sitemap provider.
 * @param string               $name     Provider name.
 * @return WP_Sitemaps_Provider|false
 */
function bb_remove_users_sitemap( $provider, $name ) {
	return ( 'users' === $name ) ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'bb_remove_users_sitemap', 10, 2 );

/**
 * Stop the oEmbed card from carrying the author's account name.
 *
 * @param array $data oEmbed response.
 * @return array
 */
function bb_clean_oembed_author( $data ) {
	unset( $data['author_url'] );

	return $data;
}
add_filter( 'oembed_response_data', 'bb_clean_oembed_author' );
