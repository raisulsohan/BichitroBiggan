<?php
/**
 * Theme License Manager
 * Uses Software License Manager (SLM) API
 */

// নিচের লিংকে আপনার ওয়েবসাইটের ঠিকানা দিন
define( 'BB_LICENSE_SERVER_URL', 'https://bichitrobiggan.com' );

// আপনার SLM প্লাগিন থেকে পাওয়া Secret Key টি এখানে বসান
define( 'BB_SLM_SECRET_KEY', 'YOUR_SECRET_KEY_HERE' ); 

define( 'BB_ITEM_REFERENCE', 'BichitroBiggan Theme' );

/**
 * Add License Menu in Admin
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
 * Handle License Activation
 */
function bb_handle_license_submission() {
	if ( isset( $_POST['bb_license_activate'] ) && check_admin_referer( 'bb_license_nonce' ) ) {
		$license_key = sanitize_text_field( $_POST['bb_license_key'] );
		
		$api_params = array(
			'slm_action'        => 'slm_activate',
			'secret_key'        => BB_SLM_SECRET_KEY,
			'license_key'       => $license_key,
			'registered_domain' => sanitize_text_field( $_SERVER['SERVER_NAME'] ),
			'item_reference'    => urlencode( BB_ITEM_REFERENCE ),
		);
		
		$query = esc_url_raw( add_query_arg( $api_params, BB_LICENSE_SERVER_URL ) );
		$response = wp_remote_get( $query, array( 'timeout' => 15, 'sslverify' => false ) );
		
		if ( is_wp_error( $response ) ) {
			add_settings_error( 'bb_license', 'api_error', 'সার্ভারের সাথে কানেক্ট করা যাচ্ছে না।', 'error' );
			return;
		}
		
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		
		if ( $license_data && $license_data->result == 'success' ) {
			update_option( 'bb_license_key', $license_key );
			update_option( 'bb_license_status', 'valid' );
			add_settings_error( 'bb_license', 'success', 'লাইসেন্স সফলভাবে অ্যাক্টিভেট হয়েছে!', 'updated' );
		} else {
			$error_msg = isset($license_data->message) ? $license_data->message : 'ভুল লাইসেন্স কী!';
			add_settings_error( 'bb_license', 'invalid', 'লাইসেন্স অ্যাক্টিভেট হয়নি: ' . $error_msg, 'error' );
		}
	}
}
add_action( 'admin_init', 'bb_handle_license_submission' );

/**
 * Show Admin Notice if not activated
 */
function bb_license_admin_notice() {
	$status = get_option( 'bb_license_status' );
	if ( $status !== 'valid' && ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'bb-license' ) ) {
		$url = admin_url( 'themes.php?page=bb-license' );
		echo '<div class="notice notice-error"><p><strong>বিচিত্র বিজ্ঞান থিম:</strong> থিমটি ব্যবহার করতে দয়া করে <a href="' . esc_url( $url ) . '">লাইসেন্স অ্যাক্টিভেট করুন</a>। লাইসেন্স ছাড়া থিমটি কাজ করবে না।</p></div>';
	}
}
add_action( 'admin_notices', 'bb_license_admin_notice' );

/**
 * Restrict Frontend Usage if not active
 */
function bb_restrict_theme_usage() {
	$status = get_option( 'bb_license_status' );
	if ( $status !== 'valid' ) {
		if ( ! is_admin() && ! in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ) {
			wp_die( '<h2>Bichitro Biggan Theme</h2><p>এই থিমটির লাইসেন্স অ্যাক্টিভেট করা নেই। ওয়েবসাইটটি দেখতে অ্যাডমিন ড্যাশবোর্ড থেকে লাইসেন্স কী প্রবেশ করান।</p>', 'License Required', array( 'response' => 403 ) );
		}
	}
}
add_action( 'template_redirect', 'bb_restrict_theme_usage' );

/**
 * Render License Page HTML
 */
function bb_license_page_html() {
	$status = get_option( 'bb_license_status' );
	$key = get_option( 'bb_license_key' );
	?>
	<div class="wrap">
		<h1>বিচিত্র বিজ্ঞান — লাইসেন্স অ্যাক্টিভেশন</h1>
		<?php settings_errors( 'bb_license' ); ?>
		
		<div class="card" style="max-width: 500px; padding: 20px; margin-top: 20px;">
			<p>থিমের সম্পূর্ণ ফিচার উপভোগ করতে আপনার লাইসেন্স কী (License Key) দিন।</p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'bb_license_nonce' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row" valign="top">লাইসেন্স কী:</th>
						<td>
							<input type="text" name="bb_license_key" value="<?php echo esc_attr( $key ); ?>" style="width: 100%; padding: 8px;" <?php echo ( $status === 'valid' ) ? 'readonly' : ''; ?> />
							<?php if ( $status === 'valid' ) : ?>
								<p style="color: green; font-weight: bold; margin-top: 10px;">✅ লাইসেন্স অ্যাক্টিভ আছে!</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				
				<?php if ( $status !== 'valid' ) : ?>
					<p class="submit">
						<input type="submit" name="bb_license_activate" class="button-primary" value="অ্যাক্টিভেট করুন" />
					</p>
				<?php else : ?>
					<p class="description">লাইসেন্স সংক্রান্ত যেকোনো সমস্যার জন্য আমাদের সাথে যোগাযোগ করুন।</p>
				<?php endif; ?>
			</form>
		</div>
	</div>
	<?php
}
