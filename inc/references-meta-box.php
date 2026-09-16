<?php
/**
 * তথ্যসূত্র — the sources list printed under an article.
 *
 * One source per line, written as "নাম | https://example.com", or as a bare
 * address, or as plain text with no link at all. An empty box prints nothing,
 * so posts written before this existed are left exactly as they are.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. The editor box
 * ======================================================================= */

function bb_references_add_meta_box() {
	add_meta_box(
		'bb_references_meta_box',
		__( 'তথ্যসূত্র', 'bichitro-biggan' ),
		'bb_references_meta_box_html',
		'post',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'bb_references_add_meta_box' );

/**
 * @param WP_Post $post Post being edited.
 */
function bb_references_meta_box_html( $post ) {
	wp_nonce_field( 'bb_references_save', 'bb_references_nonce' );

	$value = get_post_meta( $post->ID, 'bb_references', true );
	?>
	<p class="description" style="margin:0 0 8px;">
		<?php esc_html_e( 'প্রতি লাইনে একটি সূত্র। নাম ও লিংক আলাদা করতে | চিহ্ন দিন।', 'bichitro-biggan' ); ?>
	</p>
	<textarea
		id="bb-references"
		name="bb_references"
		rows="6"
		class="large-text"
		placeholder="Nature | https://www.nature.com/articles/d41586-024-00001-x&#10;NASA — Mars Exploration Program | https://mars.nasa.gov/&#10;বিজ্ঞান ও প্রযুক্তি, জুলাই ২০২৫ সংখ্যা"
	><?php echo esc_textarea( $value ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'খালি রাখলে লেখার নিচে কিছুই দেখাবে না।', 'bichitro-biggan' ); ?>
	</p>
	<?php
}

function bb_references_save( $post_id ) {
	if ( ! isset( $_POST['bb_references_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bb_references_nonce'] ) ), 'bb_references_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['bb_references'] ) ) {
		return;
	}

	$raw = sanitize_textarea_field( wp_unslash( $_POST['bb_references'] ) );

	if ( '' === trim( $raw ) ) {
		delete_post_meta( $post_id, 'bb_references' );
		return;
	}

	update_post_meta( $post_id, 'bb_references', $raw );
}
add_action( 'save_post', 'bb_references_save' );

/* =========================================================================
 * 2. Reading the lines back
 * ======================================================================= */

/**
 * The sources as name/url pairs, in the order they were written.
 *
 * @param int|null $post_id Post to read.
 * @return array<int, array{name:string,url:string}>
 */
function bb_get_references( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$raw     = (string) get_post_meta( $post_id, 'bb_references', true );

	if ( '' === trim( $raw ) ) {
		return array();
	}

	$items = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		$name = $line;
		$url  = '';

		if ( false !== strpos( $line, '|' ) ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$name  = $parts[0];
			$url   = isset( $parts[1] ) ? $parts[1] : '';
		} elseif ( preg_match( '#^https?://#i', $line ) ) {
			// A bare address: show it without the scheme, which reads better.
			$url  = $line;
			$name = preg_replace( '#^https?://(www\.)?#i', '', $line );
		}

		$url = $url ? esc_url_raw( $url ) : '';

		if ( '' === $name ) {
			$name = $url;
		}

		if ( '' === $name ) {
			continue;
		}

		$items[] = array(
			'name' => $name,
			'url'  => $url,
		);
	}

	return $items;
}

/* =========================================================================
 * 3. The list under the article
 * ======================================================================= */

function bb_references_list( $post_id = null ) {
	$items = bb_get_references( $post_id );

	if ( empty( $items ) ) {
		return;
	}
	?>
	<section class="bb-references">
		<h2 class="bb-references__title"><?php esc_html_e( 'তথ্যসূত্র', 'bichitro-biggan' ); ?></h2>
		<ol class="bb-references__list">
			<?php foreach ( $items as $bb_ref ) : ?>
				<li class="bb-references__item">
					<?php if ( $bb_ref['url'] ) : ?>
						<a href="<?php echo esc_url( $bb_ref['url'] ); ?>" target="_blank" rel="noopener nofollow">
							<?php echo esc_html( $bb_ref['name'] ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $bb_ref['name'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>
	<?php
}

/* =========================================================================
 * 4. The same sources, in the article's structured data
 * ======================================================================= */

/**
 * Google reads citation as a signal that a piece is sourced, so the list the
 * reader sees and the list the crawler sees stay the same list.
 *
 * @param array $node Article node.
 * @return array
 */
function bb_references_schema( $node ) {
	if ( empty( $node['@type'] ) || ! in_array( $node['@type'], array( 'NewsArticle', 'Article', 'BlogPosting' ), true ) ) {
		return $node;
	}

	$items = bb_get_references();

	if ( empty( $items ) ) {
		return $node;
	}

	$citations = array();

	foreach ( $items as $item ) {
		$citation = array(
			'@type' => 'CreativeWork',
			'name'  => $item['name'],
		);

		if ( $item['url'] ) {
			$citation['url'] = $item['url'];
		}

		$citations[] = $citation;
	}

	$node['citation'] = $citations;

	return $node;
}
add_filter( 'bb_seo_article_node', 'bb_references_schema' );
