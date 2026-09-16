<?php
/**
 * Theme Settings → ক্যাটাগরির রং
 *
 * Every category's badge colour and its text colour, each shown as the badge
 * itself. The theme can measure which of black or white is more legible, but
 * legible and right are not the same thing — so the measurement is offered as
 * advice and the choice stays with the editor.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bb_category_colors_menu() {
	add_submenu_page(
		'bb-theme-settings',
		__( 'ক্যাটাগরির রং', 'bichitro-biggan' ),
		__( 'ক্যাটাগরির রং', 'bichitro-biggan' ),
		'manage_categories',
		'bb-category-colors',
		'bb_category_colors_page'
	);
}
add_action( 'admin_menu', 'bb_category_colors_menu' );

/**
 * The live preview and the contrast readings.
 *
 * @param string $hook Current screen.
 */
function bb_category_colors_assets( $hook ) {
	if ( 'theme-settings_page_bb-category-colors' !== $hook && false === strpos( $hook, 'bb-category-colors' ) ) {
		return;
	}

	wp_enqueue_script(
		'bb-category-colors',
		get_template_directory_uri() . '/assets/js/category-colors.js',
		array(),
		bb_file_version( get_template_directory() . '/assets/js/category-colors.js' ),
		true
	);

	wp_localize_script(
		'bb-category-colors',
		'bbCategoryColors',
		array(
			'ratio'     => __( 'কনট্রাস্ট', 'bichitro-biggan' ),
			'good'      => __( 'ভালো', 'bichitro-biggan' ),
			'ok'        => __( 'চলনসই', 'bichitro-biggan' ),
			'poor'      => __( 'কম', 'bichitro-biggan' ),
			'autoLabel' => __( 'অটো', 'bichitro-biggan' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'bb_category_colors_assets' );

/**
 * Save the whole table at once.
 */
function bb_category_colors_save() {
	if ( ! isset( $_POST['bb_category_colors_submit'] ) || ! check_admin_referer( 'bb_category_colors' ) ) {
		return false;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return false;
	}

	$colors = isset( $_POST['bb_color'] ) ? (array) wp_unslash( $_POST['bb_color'] ) : array();
	$texts  = isset( $_POST['bb_text_color'] ) ? (array) wp_unslash( $_POST['bb_text_color'] ) : array();

	foreach ( $colors as $term_id => $value ) {
		$term_id = (int) $term_id;

		if ( ! $term_id ) {
			continue;
		}

		$color = sanitize_hex_color( $value );

		if ( $color ) {
			update_term_meta( $term_id, 'bb_color', $color );
		} else {
			delete_term_meta( $term_id, 'bb_color' );
		}

		$choice = isset( $texts[ $term_id ] ) ? sanitize_text_field( $texts[ $term_id ] ) : 'auto';

		if ( in_array( $choice, array( '#ffffff', '#1a1a1a' ), true ) ) {
			update_term_meta( $term_id, 'bb_text_color', $choice );
		} else {
			delete_term_meta( $term_id, 'bb_text_color' );
		}
	}

	return true;
}

function bb_category_colors_page() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bichitro-biggan' ) );
	}

	$saved = bb_category_colors_save();

	$categories = get_categories( array( 'hide_empty' => false ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ক্যাটাগরির রং', 'bichitro-biggan' ); ?></h1>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'সংরক্ষিত হয়েছে।', 'bichitro-biggan' ); ?></p></div>
		<?php endif; ?>

		<p class="description" style="max-width:820px;margin:14px 0 18px;">
			<?php esc_html_e( 'প্রতিটি ক্যাটাগরির ব্যাজ ও সেকশন হেডিংয়ের রং। লেখার রঙে তিনটি বিকল্প — নিচের ব্যাজগুলোতেই দেখতে পাবেন কোনটা কেমন দেখায়। পাশে কনট্রাস্টের মাপ দেওয়া আছে: ৪.৫ বা তার বেশি হলে সব পাঠকের জন্য আরামদায়ক।', 'bichitro-biggan' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bb-category-colors' ) ); ?>">
			<?php wp_nonce_field( 'bb_category_colors' ); ?>

			<table class="widefat striped" style="max-width:1000px;">
				<thead>
					<tr>
						<th style="width:22%;"><?php esc_html_e( 'ক্যাটাগরি', 'bichitro-biggan' ); ?></th>
						<th style="width:20%;"><?php esc_html_e( 'ব্যাজের রং', 'bichitro-biggan' ); ?></th>
						<th><?php esc_html_e( 'লেখার রং — যেটা পছন্দ সেটায় ক্লিক করুন', 'bichitro-biggan' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $categories as $category ) :
						$color  = bb_term_color( $category );
						$choice = get_term_meta( $category->term_id, 'bb_text_color', true );
						$choice = in_array( $choice, array( '#ffffff', '#1a1a1a' ), true ) ? $choice : 'auto';
						$id     = (int) $category->term_id;
						?>
						<tr data-bb-row>
							<td>
								<strong><?php echo esc_html( $category->name ); ?></strong><br />
								<span class="description">
									<?php
									printf(
										/* translators: %s: number of posts, in Bengali digits. */
										esc_html__( '%s টি লেখা', 'bichitro-biggan' ),
										esc_html( bb_bangla_number( (int) $category->count ) )
									);
									?>
								</span>
							</td>
							<td>
								<input type="color" value="<?php echo esc_attr( $color ); ?>" data-bb-color-picker
									style="width:46px;height:34px;vertical-align:middle;padding:2px;" />
								<input type="text" name="bb_color[<?php echo esc_attr( $id ); ?>]"
									value="<?php echo esc_attr( $color ); ?>" data-bb-color-hex
									maxlength="7" style="width:92px;vertical-align:middle;" />
							</td>
							<td>
								<?php
								$options = array(
									'auto'    => __( 'অটো — থিম নিজে বেছে নেবে', 'bichitro-biggan' ),
									'#ffffff' => __( 'সাদা', 'bichitro-biggan' ),
									'#1a1a1a' => __( 'কালো', 'bichitro-biggan' ),
								);

								foreach ( $options as $value => $label ) :
									?>
									<label style="display:inline-flex;align-items:center;gap:8px;margin:0 18px 6px 0;cursor:pointer;">
										<input type="radio" name="bb_text_color[<?php echo esc_attr( $id ); ?>]"
											value="<?php echo esc_attr( $value ); ?>"
											<?php checked( $choice, $value ); ?> data-bb-text-choice />
										<span data-bb-preview="<?php echo esc_attr( $value ); ?>"
											style="display:inline-block;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;line-height:1.4;">
											<?php echo esc_html( $category->name ); ?>
										</span>
										<em data-bb-ratio="<?php echo esc_attr( $value ); ?>" style="font-style:normal;font-size:12px;color:#646970;min-width:86px;"></em>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'সংরক্ষণ করুন', 'bichitro-biggan' ), 'primary', 'bb_category_colors_submit' ); ?>
		</form>
	</div>
	<?php
}
