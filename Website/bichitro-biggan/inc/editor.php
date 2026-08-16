<?php
/**
 * Classic editor, the justify button, and local profile photos.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Classic editor
 *
 * Built in so no separate plugin is needed. It can be switched off from
 * থিম সেটিংস, which hands posts back to the block editor.
 * ---------------------------------------------------------------------- */

function bb_classic_editor_enabled() {
	return (bool) get_theme_mod( 'bb_classic_editor', true );
}

function bb_disable_block_editor( $use_block_editor, $post = null ) {
	return bb_classic_editor_enabled() ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post', 'bb_disable_block_editor', 100, 2 );

function bb_disable_block_editor_for_type( $use_block_editor, $post_type = '' ) {
	return bb_classic_editor_enabled() ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'bb_disable_block_editor_for_type', 100, 2 );

/**
 * The block editor's front-end stylesheet is pointless once it is off.
 */
function bb_dequeue_block_styles() {
	if ( ! bb_classic_editor_enabled() ) {
		return;
	}
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
}
add_action( 'wp_enqueue_scripts', 'bb_dequeue_block_styles', 100 );

/**
 * Put the justify button back on the first toolbar row, next to the other
 * alignment buttons. WordPress dropped it in 4.7 but TinyMCE still has it.
 */
function bb_mce_add_justify( $buttons ) {
	if ( in_array( 'alignjustify', $buttons, true ) ) {
		return $buttons;
	}

	$position = array_search( 'alignright', $buttons, true );

	if ( false !== $position ) {
		array_splice( $buttons, $position + 1, 0, 'alignjustify' );
	} else {
		$buttons[] = 'alignjustify';
	}

	return $buttons;
}
add_filter( 'mce_buttons', 'bb_mce_add_justify' );

/**
 * Keep the justified paragraph readable in the editor itself.
 */
function bb_tinymce_settings( $settings ) {
	$settings['toolbar1'] = isset( $settings['toolbar1'] ) ? $settings['toolbar1'] : '';
	return $settings;
}
add_filter( 'tiny_mce_before_init', 'bb_tinymce_settings' );

/* -------------------------------------------------------------------------
 * Local profile photo
 *
 * Gravatar needs the author's email to be registered there; this lets an
 * image from the Media Library be used instead.
 * ---------------------------------------------------------------------- */

function bb_avatar_field( $user ) {
	if ( ! current_user_can( 'upload_files' ) && get_current_user_id() !== $user->ID ) {
		return;
	}

	$attachment_id = (int) get_user_meta( $user->ID, 'bb_avatar_id', true );
	$preview       = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
	?>
	<h2><?php esc_html_e( 'প্রোফাইল ছবি', 'bichitro-biggan' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="bb_avatar_id"><?php esc_html_e( 'ছবি', 'bichitro-biggan' ); ?></label></th>
			<td>
				<div id="bb-avatar-preview" style="margin-bottom:10px;">
					<?php if ( $preview ) : ?>
						<img src="<?php echo esc_url( $preview ); ?>" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;" />
					<?php endif; ?>
				</div>
				<input type="hidden" name="bb_avatar_id" id="bb_avatar_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
				<button type="button" class="button" id="bb-avatar-pick"><?php esc_html_e( 'ছবি বেছে নিন', 'bichitro-biggan' ); ?></button>
				<button type="button" class="button" id="bb-avatar-clear"><?php esc_html_e( 'সরিয়ে দিন', 'bichitro-biggan' ); ?></button>
				<p class="description">
					<?php esc_html_e( 'Media Library থেকে ছবি বেছে নিন। এটি Gravatar-এর বদলে সাইটের সব জায়গায় দেখাবে। বর্গাকার ছবি (যেমন ৫০০×৫০০) সবচেয়ে ভালো দেখায়।', 'bichitro-biggan' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'bb_avatar_field' );
add_action( 'edit_user_profile', 'bb_avatar_field' );

function bb_save_avatar_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( ! isset( $_POST['bb_avatar_id'] ) ) {
		return;
	}

	$attachment_id = absint( wp_unslash( $_POST['bb_avatar_id'] ) );

	if ( $attachment_id ) {
		update_user_meta( $user_id, 'bb_avatar_id', $attachment_id );
	} else {
		delete_user_meta( $user_id, 'bb_avatar_id' );
	}
}
add_action( 'personal_options_update', 'bb_save_avatar_field' );
add_action( 'edit_user_profile_update', 'bb_save_avatar_field' );

/**
 * Resolve whatever get_avatar() was given down to a user ID.
 */
function bb_avatar_user_id( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		return (int) $id_or_email;
	}

	if ( is_object( $id_or_email ) ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return (int) $id_or_email->user_id;
		}
		if ( ! empty( $id_or_email->comment_author_email ) ) {
			$user = get_user_by( 'email', $id_or_email->comment_author_email );
			return $user ? (int) $user->ID : 0;
		}
		return 0;
	}

	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		return $user ? (int) $user->ID : 0;
	}

	return 0;
}

function bb_avatar_url( $url, $id_or_email, $args ) {
	$user_id = bb_avatar_user_id( $id_or_email );

	if ( ! $user_id ) {
		return $url;
	}

	$attachment_id = (int) get_user_meta( $user_id, 'bb_avatar_id', true );

	if ( ! $attachment_id ) {
		return $url;
	}

	$size   = isset( $args['size'] ) ? (int) $args['size'] : 96;
	$custom = wp_get_attachment_image_url( $attachment_id, $size > 150 ? 'medium' : 'thumbnail' );

	return $custom ? $custom : $url;
}
add_filter( 'get_avatar_url', 'bb_avatar_url', 10, 3 );
