<?php
/**
 * Video Settings Meta Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bb_video_add_meta_box() {
	add_meta_box(
		'bb_video_meta_box',
		__( 'Video Settings (For Podcast/Video Grid)', 'bichitro-biggan' ),
		'bb_video_meta_box_html',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'bb_video_add_meta_box' );

function bb_video_meta_box_html( $post ) {
	wp_nonce_field( 'bb_video_meta_box_nonce_action', 'bb_video_meta_box_nonce' );

	$video_url      = get_post_meta( $post->ID, 'video_url', true );
	$video_duration = get_post_meta( $post->ID, 'video_duration', true );
	$video_ratio    = get_post_meta( $post->ID, 'video_ratio', true );
	?>
	<p>
		<label for="bb_video_url"><strong><?php esc_html_e( 'Video Embed URL (YouTube/Vimeo)', 'bichitro-biggan' ); ?></strong></label><br>
		<input type="url" id="bb_video_url" name="bb_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;" placeholder="https://www.youtube.com/watch?v=..." />
		<small><?php esc_html_e( 'If empty, the theme will try to extract the first video from the post content.', 'bichitro-biggan' ); ?></small>
	</p>
	<p>
		<label for="bb_video_duration"><strong><?php esc_html_e( 'Video Duration (Time)', 'bichitro-biggan' ); ?></strong></label><br>
		<input type="text" id="bb_video_duration" name="bb_video_duration" value="<?php echo esc_attr( $video_duration ); ?>" style="width:100%;" placeholder="e.g. ১২:৪৫ মিনিট or 12:45" />
		<small><?php esc_html_e( 'Overrides the default reading time calculation.', 'bichitro-biggan' ); ?></small>
	</p>
	<p>
		<label for="bb_video_ratio"><strong><?php esc_html_e( 'Video Aspect Ratio', 'bichitro-biggan' ); ?></strong></label><br>
		<select id="bb_video_ratio" name="bb_video_ratio" style="width:100%;">
			<option value="" <?php selected( $video_ratio, '' ); ?>><?php esc_html_e( 'Auto Detect (Default)', 'bichitro-biggan' ); ?></option>
			<option value="16/9" <?php selected( $video_ratio, '16/9' ); ?>><?php esc_html_e( 'Landscape (16:9)', 'bichitro-biggan' ); ?></option>
			<option value="9/16" <?php selected( $video_ratio, '9/16' ); ?>><?php esc_html_e( 'Portrait / Mobile (9:16)', 'bichitro-biggan' ); ?></option>
			<option value="4/5" <?php selected( $video_ratio, '4/5' ); ?>><?php esc_html_e( 'Standard Mobile (4:5)', 'bichitro-biggan' ); ?></option>
			<option value="1/1" <?php selected( $video_ratio, '1/1' ); ?>><?php esc_html_e( 'Square (1:1)', 'bichitro-biggan' ); ?></option>
		</select>
		<small><?php esc_html_e( 'Forces the video popup size. Auto will detect Shorts/Reels.', 'bichitro-biggan' ); ?></small>
	</p>
	<?php
}

function bb_video_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['bb_video_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['bb_video_meta_box_nonce'], 'bb_video_meta_box_nonce_action' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['bb_video_url'] ) ) {
		update_post_meta( $post_id, 'video_url', sanitize_text_field( $_POST['bb_video_url'] ) );
	}
	if ( isset( $_POST['bb_video_duration'] ) ) {
		update_post_meta( $post_id, 'video_duration', sanitize_text_field( $_POST['bb_video_duration'] ) );
	}
	if ( isset( $_POST['bb_video_ratio'] ) ) {
		update_post_meta( $post_id, 'video_ratio', sanitize_text_field( $_POST['bb_video_ratio'] ) );
	}
}
add_action( 'save_post', 'bb_video_save_meta_box' );
