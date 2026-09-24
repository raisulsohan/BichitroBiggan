<?php
/**
 * WebP for the pictures that were already here.
 *
 * Uploads have been converted on their way in for a while now, but everything
 * from before that is still a JPEG or a PNG — on the front page that was 372KB
 * of 594KB, and the single biggest thing left on a phone.
 *
 * Two halves, and neither of them deletes anything:
 *
 *   1. A converter that writes a .webp beside each existing .jpg or .png — the
 *      original file, and every size WordPress made from it. It keeps the new
 *      file only when it is actually smaller; a flat PNG sometimes is not.
 *   2. A swap at render time, so a picture whose address in a post still ends
 *      .jpg is served the .webp lying next to it. Stored content is never
 *      rewritten, so there is nothing to undo: take these filters away and
 *      every page goes back to the file it always named.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How many attachments one pass through the converter looks at. */
define( 'BB_WEBP_BATCH', 3 );

/** Files larger than this are left alone, so a pass cannot run out of memory. */
define( 'BB_WEBP_MAX_BYTES', 12 * MB_IN_BYTES );

/* -------------------------------------------------------------------------
 * Finding the twin
 * ---------------------------------------------------------------------- */

/**
 * The .webp path that belongs beside a .jpg or .png.
 *
 * @param string $path Absolute path.
 * @return string Path, or '' if this is not a file we convert.
 */
function bb_webp_twin_path( $path ) {
	if ( ! preg_match( '/\.(jpe?g|png)$/i', (string) $path ) ) {
		return '';
	}

	return preg_replace( '/\.(jpe?g|png)$/i', '.webp', $path );
}

/**
 * Whether this request should be served the twin at all.
 *
 * Only what a reader downloads. The dashboard, the block editor and the REST
 * calls behind it go on seeing the files the library actually holds, so a
 * picture is inserted, looked up and edited exactly as it was before.
 *
 * @return bool
 */
function bb_webp_should_swap() {
	if ( is_admin() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	return true;
}

/**
 * Is there a usable .webp beside this URL, and what is its address?
 *
 * Only addresses inside this site's uploads folder are considered, and the
 * answer is remembered for the rest of the request — a front page asks about
 * the same handful of pictures many times over.
 *
 * @param string $url Image URL.
 * @return string The .webp URL, or '' when there is none.
 */
function bb_webp_swap_url( $url ) {
	static $seen = array();

	$url = (string) $url;

	if ( ! bb_webp_should_swap() ) {
		return '';
	}

	if ( '' === $url || ! preg_match( '/\.(jpe?g|png)(\?.*)?$/i', $url ) ) {
		return '';
	}

	if ( isset( $seen[ $url ] ) ) {
		return $seen[ $url ];
	}

	$seen[ $url ] = '';

	$uploads = wp_get_upload_dir();

	if ( empty( $uploads['baseurl'] ) || 0 !== strpos( $url, $uploads['baseurl'] ) ) {
		// Not ours to touch — another site, or a file outside the library.
		return '';
	}

	$relative = substr( $url, strlen( $uploads['baseurl'] ) );
	$relative = strtok( $relative, '?' );
	$path     = $uploads['basedir'] . $relative;
	$twin     = bb_webp_twin_path( $path );

	/*
	 * A twin has to exist and have something in it. A conversion that ran out
	 * of memory part way leaves a file of nothing behind, and trusting the
	 * name alone served a picture that was zero bytes long.
	 */
	if ( '' === $twin || ! file_exists( $twin ) || filesize( $twin ) < 1 ) {
		return '';
	}

	$seen[ $url ] = $uploads['baseurl'] . bb_webp_twin_path( $relative );

	return $seen[ $url ];
}

/* -------------------------------------------------------------------------
 * The swap, at the moment a page is drawn
 * ---------------------------------------------------------------------- */

/**
 * And every width offered alongside it.
 *
 * @param array $sources Width => source.
 * @return array
 */
function bb_webp_filter_srcset( $sources ) {
	if ( ! is_array( $sources ) ) {
		return $sources;
	}

	foreach ( $sources as $width => $source ) {
		if ( empty( $source['url'] ) ) {
			continue;
		}

		$swap = bb_webp_swap_url( $source['url'] );

		if ( '' !== $swap ) {
			$sources[ $width ]['url'] = $swap;
		}
	}

	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'bb_webp_filter_srcset', 20 );

/**
 * The src attribute, swapped only once the tag is finished.
 *
 * It cannot be swapped any earlier. WordPress works out a picture's widths by
 * checking the src it was handed against the sizes recorded in the library —
 * and the library records a .jpg. Handed a .webp, it finds no match and drops
 * the srcset altogether, which left a 240px slot downloading the 800px
 * original. So core is given the names it knows throughout, and the addresses
 * are changed here, in HTML nothing else will read.
 *
 * @param string $html A finished <img> tag, or a block of content.
 * @return string
 */
function bb_webp_filter_html( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, '<img' ) ) {
		return $html;
	}

	return (string) preg_replace_callback(
		'/\ssrc="([^"]+\.(?:jpe?g|png))"/i',
		function ( $m ) {
			$swap = bb_webp_swap_url( $m[1] );

			return '' !== $swap ? ' src="' . esc_url( $swap ) . '"' : $m[0];
		},
		$html
	);
}
add_filter( 'wp_get_attachment_image', 'bb_webp_filter_html', 20 );
add_filter( 'the_content', 'bb_webp_filter_html', 20 );
add_filter( 'post_thumbnail_html', 'bb_webp_filter_html', 20 );

/* -------------------------------------------------------------------------
 * The converter
 * ---------------------------------------------------------------------- */

/**
 * Every file on disk that belongs to an attachment: the original, and each
 * size WordPress made from it.
 *
 * @param int $id Attachment.
 * @return array<int, string> Absolute paths.
 */
function bb_webp_files_for( $id ) {
	$original = get_attached_file( $id );

	if ( ! $original || ! file_exists( $original ) ) {
		return array();
	}

	$files = array( $original );
	$meta  = wp_get_attachment_metadata( $id );
	$dir   = trailingslashit( dirname( $original ) );

	if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$files[] = $dir . $size['file'];
			}
		}
	}

	// The untouched upload, kept when big_image_size_threshold scaled one down.
	if ( ! empty( $meta['original_image'] ) ) {
		$files[] = $dir . $meta['original_image'];
	}

	return array_values( array_unique( $files ) );
}

/**
 * Write the .webp beside one file.
 *
 * @param string $path Absolute path to a .jpg or .png.
 * @return string 'made', 'already', 'bigger', or 'skipped'.
 */
function bb_webp_convert_file( $path ) {
	$twin = bb_webp_twin_path( $path );

	if ( '' === $twin || ! file_exists( $path ) ) {
		return 'skipped';
	}

	if ( file_exists( $twin ) ) {
		if ( filesize( $twin ) > 0 ) {
			return 'already';
		}

		// Nothing in it — a conversion that failed half way. Try again.
		wp_delete_file( $twin );
	}

	$size = (int) filesize( $path );

	if ( $size <= 0 || $size > BB_WEBP_MAX_BYTES ) {
		return 'skipped';
	}

	$editor = wp_get_image_editor( $path );

	if ( is_wp_error( $editor ) ) {
		return 'skipped';
	}

	$editor->set_quality( 80 );

	$saved = $editor->save( $twin, 'image/webp' );

	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
		return 'skipped';
	}

	/*
	 * Saved is not the same as written. A conversion that runs out of memory
	 * can leave a file of nothing behind and still report success, and because
	 * the swap trusts a twin by finding it, that empty file then went out to
	 * readers in place of the picture. Anything that is not a real WebP is
	 * thrown away here instead.
	 */
	$written = (int) filesize( $saved['path'] );
	$reads   = $written > 0 ? @getimagesize( $saved['path'] ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a broken file is the thing being looked for.

	if ( $written < 1 || empty( $reads[0] ) ) {
		wp_delete_file( $saved['path'] );

		return 'skipped';
	}

	/*
	 * A WebP is not always the smaller file — a flat graphic saved as PNG can
	 * come out heavier. Keeping it would make the page worse, and because the
	 * swap above trusts a twin simply by finding it, the answer is to not
	 * leave one lying there.
	 */
	if ( $written >= $size ) {
		wp_delete_file( $saved['path'] );

		return 'bigger';
	}

	return 'made';
}

/**
 * The attachments that still have a JPEG or a PNG in them, oldest first.
 *
 * @param int $offset Where the last pass stopped.
 * @param int $limit  How many to take.
 * @return array<int, int> Attachment IDs.
 */
function bb_webp_candidates( $offset, $limit ) {
	return get_posts(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => array( 'image/jpeg', 'image/png' ),
			'posts_per_page'         => (int) $limit,
			'offset'                 => (int) $offset,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);
}

/** How many attachments the converter has to walk through in all. */
function bb_webp_total() {
	$counts = (array) wp_count_attachments();

	return (int) ( $counts['image/jpeg'] ?? 0 ) + (int) ( $counts['image/png'] ?? 0 );
}

/** Where the converter got to, and what it has done. */
function bb_webp_progress() {
	$saved = get_option( 'bb_webp_progress', array() );

	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'offset' => 0,
			'made'   => 0,
			'bigger' => 0,
			'seen'   => 0,
			'bytes'  => 0,
		)
	);
}

/**
 * One pass: a few attachments, every file inside them.
 *
 * @return array The progress after this pass, plus whether anything is left.
 */
function bb_webp_run_batch() {
	$progress = bb_webp_progress();
	$ids      = bb_webp_candidates( $progress['offset'], BB_WEBP_BATCH );

	foreach ( $ids as $id ) {
		foreach ( bb_webp_files_for( $id ) as $path ) {
			$before = file_exists( $path ) ? (int) filesize( $path ) : 0;
			$result = bb_webp_convert_file( $path );

			if ( 'made' === $result ) {
				++$progress['made'];
				$twin             = bb_webp_twin_path( $path );
				$progress['bytes'] += max( 0, $before - (int) filesize( $twin ) );
			} elseif ( 'bigger' === $result ) {
				++$progress['bigger'];
			}
		}

		++$progress['seen'];
	}

	$progress['offset'] += count( $ids );

	update_option( 'bb_webp_progress', $progress, false );

	return array(
		'progress' => $progress,
		'total'    => bb_webp_total(),
		'done'     => count( $ids ) < BB_WEBP_BATCH,
	);
}
