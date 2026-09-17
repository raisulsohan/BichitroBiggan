<?php
/**
 * An SEO column in the Posts and Pages lists: the editor's traffic light, one
 * per post, so the weak ones can be picked out without opening each.
 *
 * The score is the one the SEO box shows in the editor — updateSeoAnalysis() in
 * assets/js/seo-meta-box.js — worked out here from the saved fields, so a post
 * shows the same colour in the list as when it is opened. The two are kept in
 * step by hand: change a rule in one, change it in the other.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Length as JavaScript's String.length counts it, which is what the editor's
 * counters and limits use.
 *
 * @param string $text Text to measure.
 * @return int
 */
function bb_seo_js_length( $text ) {
	$text = (string) $text;

	if ( '' === $text ) {
		return 0;
	}

	if ( function_exists( 'mb_convert_encoding' ) ) {
		return (int) ( strlen( mb_convert_encoding( $text, 'UTF-16LE', 'UTF-8' ) ) / 2 );
	}

	return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
}

/**
 * @param string $text Text to lower-case.
 * @return string
 */
function bb_seo_lower( $text ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
}

/**
 * The editor's SEO score for a saved post.
 *
 * @param int|WP_Post $post Post.
 * @return array{status:string,label:string,notes:string[]}
 */
function bb_seo_score( $post ) {
	$post = get_post( $post );

	$keyphrase = bb_seo_lower( trim( bb_seo_get_field( $post->ID, 'bb_focus_keyphrase' ) ) );
	$raw_title = (string) bb_seo_get_field( $post->ID, 'bb_seo_title' );
	$raw_desc  = (string) bb_seo_get_field( $post->ID, 'bb_seo_description' );

	$title_text = bb_seo_lower(
		'' !== trim( $raw_title )
			? bb_seo_resolve_variables( trim( $raw_title ), $post )
			: trim( $post->post_title ) . ' — ' . get_bloginfo( 'name' )
	);
	$desc_text  = bb_seo_lower( trim( $raw_desc ) );

	$good  = 0;
	$bad   = 0;
	$notes = array();

	// 1. Focus keyphrase — missing counts double, as in the editor.
	if ( '' === $keyphrase ) {
		$bad    += 2;
		$notes[] = __( 'ফোকাস কি-ফ্রেজ নেই', 'bichitro-biggan' );
	} else {
		$good++;

		// 2. Keyphrase in the SEO title.
		if ( false !== strpos( $title_text, $keyphrase ) ) {
			$good++;
		} else {
			$bad++;
			$notes[] = __( 'এসইও টাইটেলে কি-ফ্রেজ নেই', 'bichitro-biggan' );
		}
	}

	// 3. SEO title length. The editor says nothing when the field is empty.
	$title_len = bb_seo_js_length( $raw_title );
	if ( $title_len >= 25 && $title_len <= 60 ) {
		$good++;
	} elseif ( $title_len > 60 ) {
		$bad++;
		/* translators: %d: characters in the SEO title. */
		$notes[] = sprintf( __( 'এসইও টাইটেল বেশি লম্বা (%d অক্ষর)', 'bichitro-biggan' ), $title_len );
	} elseif ( $title_len > 0 ) {
		/* translators: %d: characters in the SEO title. */
		$notes[] = sprintf( __( 'এসইও টাইটেল ছোট (%d অক্ষর)', 'bichitro-biggan' ), $title_len );
	}

	// 4. Keyphrase in the meta description.
	if ( '' !== $keyphrase && '' !== $desc_text ) {
		if ( false !== strpos( $desc_text, $keyphrase ) ) {
			$good++;
		} else {
			$notes[] = __( 'মেটা ডেসক্রিপশনে কি-ফ্রেজ নেই', 'bichitro-biggan' );
		}
	}

	// 5. Meta description length.
	$desc_len = bb_seo_js_length( $raw_desc );
	if ( $desc_len >= 80 && $desc_len <= 160 ) {
		$good++;
	} elseif ( $desc_len > 160 ) {
		$bad++;
		/* translators: %d: characters in the meta description. */
		$notes[] = sprintf( __( 'মেটা ডেসক্রিপশন বেশি লম্বা (%d অক্ষর)', 'bichitro-biggan' ), $desc_len );
	} elseif ( $desc_len > 0 ) {
		/* translators: %d: characters in the meta description. */
		$notes[] = sprintf( __( 'মেটা ডেসক্রিপশন ছোট (%d অক্ষর)', 'bichitro-biggan' ), $desc_len );
	} else {
		$bad++;
		$notes[] = __( 'মেটা ডেসক্রিপশন নেই', 'bichitro-biggan' );
	}

	// 6. Featured image.
	if ( has_post_thumbnail( $post ) ) {
		$good++;
	} else {
		$notes[] = __( 'ফিচার্ড ইমেজ নেই', 'bichitro-biggan' );
	}

	if ( $good >= 4 && 0 === $bad ) {
		return array( 'status' => 'good', 'label' => __( 'এসইও: চমৎকার', 'bichitro-biggan' ), 'notes' => $notes );
	}

	if ( $good >= 2 && $bad <= 2 ) {
		return array( 'status' => 'ok', 'label' => __( 'এসইও: সাধারণ', 'bichitro-biggan' ), 'notes' => $notes );
	}

	return array( 'status' => 'bad', 'label' => __( 'এসইও: অপর্যাপ্ত', 'bichitro-biggan' ), 'notes' => $notes );
}

/**
 * Put the column just before the comment count, where Yoast keeps its own.
 *
 * @param array<string,string> $columns List columns.
 * @return array<string,string>
 */
function bb_seo_add_column( $columns ) {
	$label  = __( 'SEO', 'bichitro-biggan' );
	$anchor = isset( $columns['comments'] ) ? 'comments' : ( isset( $columns['date'] ) ? 'date' : '' );

	if ( ! $anchor ) {
		$columns['bb_seo'] = $label;
		return $columns;
	}

	$ordered = array();
	foreach ( $columns as $key => $value ) {
		if ( $key === $anchor ) {
			$ordered['bb_seo'] = $label;
		}
		$ordered[ $key ] = $value;
	}

	return $ordered;
}
add_filter( 'manage_post_posts_columns', 'bb_seo_add_column' );
add_filter( 'manage_page_posts_columns', 'bb_seo_add_column' );

/**
 * The dot, with what holds the score back in its tooltip.
 *
 * @param string $column  Column being printed.
 * @param int    $post_id Post in this row.
 */
function bb_seo_render_column( $column, $post_id ) {
	if ( 'bb_seo' !== $column ) {
		return;
	}

	$score = bb_seo_score( $post_id );
	$tip   = $score['label'] . ( $score['notes'] ? "\n• " . implode( "\n• ", $score['notes'] ) : '' );

	printf(
		'<span class="bb-seo-dot bb-seo-dot--%1$s" title="%2$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span>',
		esc_attr( $score['status'] ),
		esc_attr( $tip ),
		esc_html( str_replace( "\n", ' ', $tip ) )
	);
}
add_action( 'manage_post_posts_custom_column', 'bb_seo_render_column', 10, 2 );
add_action( 'manage_page_posts_custom_column', 'bb_seo_render_column', 10, 2 );

/**
 * Same colours as the SEO box in the editor (assets/css/seo-meta-box.css),
 * which is not loaded on the list screens.
 */
function bb_seo_column_styles() {
	?>
	<style id="bb-seo-column">
		.fixed .column-bb_seo { width: 3.5em; text-align: center; }
		.column-bb_seo .bb-seo-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; vertical-align: middle; cursor: help; }
		.bb-seo-dot--good { background: #00a32a; box-shadow: 0 0 0 2px rgba(0, 163, 42, .2); }
		.bb-seo-dot--ok { background: #dba617; box-shadow: 0 0 0 2px rgba(219, 166, 23, .2); }
		.bb-seo-dot--bad { background: #d63638; box-shadow: 0 0 0 2px rgba(214, 54, 56, .2); }
	</style>
	<?php
}
add_action( 'admin_head-edit.php', 'bb_seo_column_styles' );
