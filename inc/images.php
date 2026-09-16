<?php
/**
 * What happens to a photograph on its way into the library.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save the sizes WordPress generates from a JPEG or PNG upload as WebP.
 *
 * This is WordPress's own conversion: the uploaded original stays on disk and
 * the converted files go through its usual unique-name checks. The theme used
 * to convert by hand instead — deleting the original, and overwriting any
 * .webp of the same name already in the folder.
 */
function bb_webp_output_format( $formats ) {
	static $supported = null;

	if ( null === $supported ) {
		$supported = false; // Holds while the check below runs, in case it re-enters.
		$supported = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	if ( $supported ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}

	return $formats;
}
add_filter( 'image_editor_output_format', 'bb_webp_output_format' );

/**
 * Scale very large uploads down to 1600px on the long side, as before.
 */
function bb_big_image_threshold() {
	return 1600;
}
add_filter( 'big_image_size_threshold', 'bb_big_image_threshold' );

/**
 * Set standard thumbnail generation quality to 80.
 */
function bb_image_quality( $quality ) {
	return 80;
}
add_filter( 'wp_editor_set_quality', 'bb_image_quality' );
