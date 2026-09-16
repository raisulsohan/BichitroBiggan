<?php
/**
 * The licence: what it unlocks, and what it must never do.
 *
 * It gates theme updates. It does not gate the site — a missing licence once
 * answered every visitor and every crawler with a 403.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Apps Script Web App URL.
 * Replace this with your deployed Apps Script URL after setup.
 */
define( 'BB_LICENSE_API_URL', 'https://script.google.com/macros/s/AKfycbxV0k_ydJr9SJttf_7fgRXuG3KqCKStgg7cEUS80uCAqZzzNqz51RyI07o7mzrZzHjRDQ/exec' );

/**
 * Add License page under Appearance menu.
 */
function bb_license_menu() {
	add_theme_page(
		'Theme License',
		'Theme License',
		'manage_options',
		'bb-license',
		'bb_license_page_html'
	);
}
add_action( 'admin_menu', 'bb_license_menu' );

/**
 * Handle license activation form submission.
 */
function bb_handle_license_activation() {
	if ( ! isset( $_POST['bb_license_activate'] ) || ! check_admin_referer( 'bb_license_nonce' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$license_key = isset( $_POST['bb_license_key'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['bb_license_key'] ) ) ) : '';
	if ( empty( $license_key ) ) {
		add_settings_error( 'bb_license', 'empty', 'লাইসেন্স কী খালি রাখা যাবে না।', 'error' );
		return;
	}

	$domain = sanitize_text_field( $_SERVER['SERVER_NAME'] );

	$response = wp_remote_get(
		add_query_arg(
			array(
				'action'  => 'activate',
				'key'     => $license_key,
				'domain'  => $domain,
			),
			BB_LICENSE_API_URL
		),
		array( 'timeout' => 15, 'sslverify' => true )
	);

	if ( is_wp_error( $response ) ) {
		add_settings_error( 'bb_license', 'conn', 'সার্ভারের সাথে কানেক্ট করা যাচ্ছে না: ' . $response->get_error_message(), 'error' );
		return;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! empty( $body['success'] ) ) {
		update_option( 'bb_license_key', $license_key );
		update_option( 'bb_license_status', 'valid' );
		update_option( 'bb_license_domain', $domain );
		add_settings_error( 'bb_license', 'ok', 'লাইসেন্স সফলভাবে অ্যাক্টিভেট হয়েছে! ✅', 'updated' );
	} else {
		$msg = ! empty( $body['message'] ) ? $body['message'] : 'ভুল লাইসেন্স কী!';
		add_settings_error( 'bb_license', 'fail', 'অ্যাক্টিভেশন ব্যর্থ: ' . $msg, 'error' );
	}
}
add_action( 'admin_init', 'bb_handle_license_activation' );

/**
 * Check if the theme is licensed.
 */
function bb_is_licensed() {
	// Always allow localhost development.
	$host = isset( $_SERVER['SERVER_NAME'] ) ? strtolower( $_SERVER['SERVER_NAME'] ) : '';
	if ( strpos( $host, 'localhost' ) === 0 || $host === '127.0.0.1' ) {
		return true;
	}

	return get_option( 'bb_license_status' ) === 'valid';
}

/**
 * Updates are what the license unlocks. The public site is never blocked: a
 * lost option — a database restore, a migration — used to answer every
 * visitor and every crawler with a 403 until someone noticed.
 */
add_filter( 'tgu_updates_enabled', 'bb_is_licensed' );

/**
 * Admin notice when not licensed.
 */
function bb_license_admin_notice() {
	if ( bb_is_licensed() ) {
		return;
	}
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'bb-license' ) {
		return;
	}

	$url = admin_url( 'themes.php?page=bb-license' );
	echo '<div class="notice notice-error"><p><strong>বিচিত্র বিজ্ঞান থিম:</strong> '
		. 'থিমের স্বয়ংক্রিয় আপডেট পেতে <a href="' . esc_url( $url ) . '">লাইসেন্স অ্যাক্টিভেট করুন</a>। লাইসেন্স ছাড়াও সাইট স্বাভাবিকভাবে চলবে।</p></div>';
}
add_action( 'admin_notices', 'bb_license_admin_notice' );

/**
 * Render the license activation page.
 */
function bb_license_page_html() {
	$status = get_option( 'bb_license_status' );
	$key    = get_option( 'bb_license_key' );
	$domain = get_option( 'bb_license_domain' );
	?>
	<div class="wrap">
		<h1>বিচিত্র বিজ্ঞান — লাইসেন্স অ্যাক্টিভেশন</h1>
		<?php settings_errors( 'bb_license' ); ?>

		<div class="card" style="max-width: 520px; padding: 24px; margin-top: 20px;">
			<?php if ( $status === 'valid' ) : ?>
				<p style="color: #00a32a; font-weight: bold; font-size: 15px;">✅ লাইসেন্স অ্যাক্টিভ আছে!</p>
				<table class="form-table">
					<tr><th>লাইসেন্স কী:</th><td><code><?php echo esc_html( $key ); ?></code></td></tr>
					<tr><th>ডোমেইন:</th><td><code><?php echo esc_html( $domain ); ?></code></td></tr>
				</table>
			<?php else : ?>
				<p>থিমের স্বয়ংক্রিয় আপডেট পেতে আপনার লাইসেন্স কী (License Key) দিন। লাইসেন্স ছাড়াও সাইট সম্পূর্ণ চালু থাকে।</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'bb_license_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="bb_license_key">লাইসেন্স কী:</label></th>
							<td><input type="text" id="bb_license_key" name="bb_license_key" value="" style="width:100%;padding:8px;" placeholder="BB-XXXX-XXXX-XXXX" /></td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="bb_license_activate" class="button-primary" value="অ্যাক্টিভেট করুন" />
					</p>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
