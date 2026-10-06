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
		<input type="text" id="bb_video_duration" name="bb_video_duration" value="<?php echo esc_attr( $video_duration ); ?>" style="width:100%;" placeholder="e.g. 12:45" />
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
		update_post_meta( $post_id, 'video_url', esc_url_raw( wp_unslash( $_POST['bb_video_url'] ) ) );
	}
	if ( isset( $_POST['bb_video_duration'] ) ) {
		update_post_meta( $post_id, 'video_duration', sanitize_text_field( wp_unslash( $_POST['bb_video_duration'] ) ) );
	}
	if ( isset( $_POST['bb_video_ratio'] ) ) {
		update_post_meta( $post_id, 'video_ratio', sanitize_text_field( wp_unslash( $_POST['bb_video_ratio'] ) ) );
	}
}
add_action( 'save_post', 'bb_video_save_meta_box' );

/**
 * The YouTube ID of a post's video, or '' when it has none.
 *
 * @param int|WP_Post $post Post ID or object.
 * @return string
 */
function bb_get_post_youtube_id( $post = null ) {
	$url = bb_get_post_video_url( $post );

	if ( $url && preg_match( '~youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
		return $m[1];
	}

	return '';
}

/**
 * Bring a YouTube video's still into the library as the post's featured image.
 *
 * Every card, the share preview and the schema read the featured image, so a
 * video posted with only its link showed the placeholder everywhere. The
 * largest still comes first; hqdefault is letterboxed but every video has one.
 *
 * @param int    $post_id  Post to give the image to.
 * @param string $video_id YouTube video ID.
 * @return bool Whether a featured image was set.
 */
function bb_video_sideload_thumbnail( $post_id, $video_id ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$slug  = (string) get_post_field( 'post_name', $post_id );
	$name  = preg_match( '/^[a-z0-9-]+$/', $slug ) ? $slug : 'video-' . $video_id;
	$title = get_the_title( $post_id );

	foreach ( array( 'maxresdefault', 'hq720', 'hqdefault' ) as $still ) {
		$tmp = download_url( 'https://i.ytimg.com/vi/' . $video_id . '/' . $still . '.jpg', 15 );

		if ( is_wp_error( $tmp ) ) {
			continue;
		}

		$file          = array(
			'name'     => $name . '.jpg',
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return false;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $title ) );

		return (bool) set_post_thumbnail( $post_id, $attachment_id );
	}

	return false;
}

/**
 * A YouTube post saved without a featured image gets the video's still.
 *
 * wp_after_insert_post fires once the meta is saved, from the editor and from
 * the REST API alike, so a link in the content or the Video URL field counts.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function bb_video_auto_thumbnail( $post_id, $post ) {
	if ( 'post' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) || has_post_thumbnail( $post_id ) ) {
		return;
	}

	$video_id = bb_get_post_youtube_id( $post );

	if ( '' !== $video_id ) {
		bb_video_sideload_thumbnail( $post_id, $video_id );
	}
}
add_action( 'wp_after_insert_post', 'bb_video_auto_thumbnail', 10, 2 );

/**
 * Video posts published before this existed get their still once, in the
 * background, the first time someone opens the dashboard.
 */
function bb_video_thumbnail_backfill() {
	$ids = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'future' ),
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_thumbnail_id',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	foreach ( $ids as $id ) {
		$video_id = bb_get_post_youtube_id( $id );

		if ( '' !== $video_id ) {
			bb_video_sideload_thumbnail( $id, $video_id );
		}
	}

	update_option( 'bb_video_thumbs_backfilled', 1, false );
}
add_action( 'bb_video_thumbnail_backfill', 'bb_video_thumbnail_backfill' );

function bb_video_schedule_backfill() {
	if ( get_option( 'bb_video_thumbs_backfilled' ) || wp_next_scheduled( 'bb_video_thumbnail_backfill' ) ) {
		return;
	}

	wp_schedule_single_event( time(), 'bb_video_thumbnail_backfill' );
}
add_action( 'admin_init', 'bb_video_schedule_backfill' );
