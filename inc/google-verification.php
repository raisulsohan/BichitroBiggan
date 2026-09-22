<?php
/**
 * Google Search Console's HTML-file verification.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The verification file name set in Theme Settings → Advanced, if any.
 *
 * It used to be hard-coded, so every site running the theme served this
 * site's Google token — and whoever holds that token could claim those
 * domains in Search Console.
 */
function bb_google_verify_file() {
	$file = strtolower( trim( (string) get_theme_mod( 'bb_google_verify_file', '' ) ) );

	return preg_match( '/^google[0-9a-f]+\.html$/', $file ) ? $file : '';
}

/**
 * Move the token that used to be hard-coded into the setting — on
 * bichitrobiggan.com only — so that site keeps its verification.
 */
function bb_migrate_google_verify_file() {
	if ( false !== get_theme_mod( 'bb_google_verify_file', false ) ) {
		return;
	}

	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$ours = in_array( $host, array( 'bichitrobiggan.com', 'www.bichitrobiggan.com' ), true );

	set_theme_mod( 'bb_google_verify_file', $ours ? 'google1c72e007995ed549.html' : '' );
}
add_action( 'init', 'bb_migrate_google_verify_file' );

/**
 * The Google tag (GA4) measurement ID from Theme Settings → Advanced.
 *
 * Analytics only ever matches a property to a site by finding this tag in the
 * pages themselves, so the ID is printed rather than merely stored.
 *
 * @return string e.g. "G-XXXXXXXXXX", or '' when none is set.
 */
function bb_google_tag_id() {
	$id = strtoupper( trim( (string) get_theme_mod( 'bb_google_tag_id', '' ) ) );

	return preg_match( '/^G-[A-Z0-9]{6,20}$/', $id ) ? $id : '';
}

/**
 * Print the Google tag, high in <head> as Google asks.
 *
 * It is printed for everyone, signed in or not: Google's "Test installation"
 * opens the site in your own browser, and a tag hidden from you there looks
 * like a tag that was never installed. Your own visits are better left out in
 * Analytics itself, under Admin → Data streams → Define internal traffic.
 */
function bb_google_tag() {
	$id = bb_google_tag_id();

	if ( ! $id || is_customize_preview() ) {
		return;
	}

	$src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id );
	?>
	<!-- Google tag (gtag.js) -->
	<script async src="<?php echo esc_url( $src ); ?>"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', <?php echo wp_json_encode( $id ); ?>);
	</script>
	<?php
}
add_action( 'wp_head', 'bb_google_tag', 1 );

/**
 * Answer the file's address as soon as the request is parsed — no rewrite
 * rule to flush, and no trailing-slash redirect in front of Google.
 *
 * @param WP $wp Current request.
 */
function bb_google_verify_render( $wp ) {
	$file = bb_google_verify_file();

	if ( ! $file || strtolower( trim( (string) $wp->request, '/' ) ) !== $file ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo 'google-site-verification: ' . esc_html( $file );
	exit;
}
add_action( 'parse_request', 'bb_google_verify_render' );


