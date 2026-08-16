<?php
/**
 * Reusable markup pieces — every card style used in the design.
 *
 * All of these assume they run inside a loop (the_post() already called).
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks a link as an article link so the popup can intercept it.
 * Without JS (or with the popup switched off) it stays an ordinary link.
 */
function bb_article_attr( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	echo ' data-bb-article="' . esc_attr( $post_id ) . '"';
}

/**
 * Make a post from a slider chunk the current post.
 */
function bb_setup_post( $post ) {
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $GLOBALS['post'] );
}

/**
 * How many slides each category section holds.
 */
function bb_slider_slides() {
	$slides = (int) get_theme_mod( 'bb_slider_slides', 3 );

	return max( 1, min( 5, $slides ) );
}

/**
 * The ‹ › control under a category section. Only rendered when there is
 * somewhere to slide to.
 */
function bb_slider_arrows( $count ) {
	if ( $count < 2 ) {
		return;
	}
	?>
	<div class="bb-arrow-group">
		<button type="button" class="bb-arrow-btn bb-arrow-btn--lg" data-bb-slide="prev" aria-label="<?php esc_attr_e( 'আগের লেখাগুলো', 'bichitro-biggan' ); ?>">‹</button>
		<button type="button" class="bb-arrow-btn bb-arrow-btn--lg" data-bb-slide="next" aria-label="<?php esc_attr_e( 'পরের লেখাগুলো', 'bichitro-biggan' ); ?>">›</button>
		<button type="button" class="bb-arrow-btn bb-arrow-btn--reset" data-bb-slide="reset" disabled
			title="<?php esc_attr_e( 'প্রথম স্লাইডে ফিরে যান', 'bichitro-biggan' ); ?>"
			aria-label="<?php esc_attr_e( 'প্রথম স্লাইডে ফিরে যান', 'bichitro-biggan' ); ?>">↺</button>
	</div>
	<?php
}

/**
 * A footer credit name, linked when a URL has been set for it.
 */
function bb_footer_credit( $name_key, $name_default, $url_key ) {
	$name = get_theme_mod( $name_key, $name_default );
	$url  = get_theme_mod( $url_key, '' );

	if ( '' === trim( (string) $name ) ) {
		return;
	}

	if ( $url ) {
		printf(
			'<a class="hl" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $url ),
			esc_html( $name )
		);
		return;
	}

	printf( '<span class="hl">%s</span>', esc_html( $name ) );
}

/**
 * Category badge.
 *
 * @param WP_Term|null $term        Term to render. Defaults to the post's primary category.
 * @param string       $extra_class Extra CSS class.
 * @param bool         $link        Wrap in a link to the category archive.
 */
function bb_badge( $term = null, $extra_class = '', $link = false ) {
	$term = $term ? $term : bb_primary_category();

	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	$color = bb_term_color( $term );
	$class = trim( 'bb-badge ' . $extra_class );
	$style = 'background:' . esc_attr( $color ) . ';';

	if ( $link ) {
		printf(
			'<a class="%1$s" style="%2$s" href="%3$s">%4$s</a>',
			esc_attr( $class ),
			esc_attr( $style ),
			esc_url( get_category_link( $term ) ),
			esc_html( $term->name )
		);
		return;
	}

	printf(
		'<span class="%1$s" style="%2$s">%3$s</span>',
		esc_attr( $class ),
		esc_attr( $style ),
		esc_html( $term->name )
	);
}

/**
 * Coloured section heading with the trailing rule.
 *
 * @param string $label Heading text.
 * @param string $color Background colour.
 * @param bool   $upper Render as an uppercase latin label.
 * @param string $url   Optional link for the label.
 */
function bb_section_heading( $label, $color = '#1a1a1a', $upper = false, $url = '' ) {
	$text_color = bb_contrast_color( $color );
	$class      = 'bb-sechead__label' . ( $upper ? ' bb-sechead__label--upper' : '' );
	?>
	<div class="bb-sechead">
		<?php if ( $url ) : ?>
			<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>"
				style="background:<?php echo esc_attr( $color ); ?>;color:<?php echo esc_attr( $text_color ); ?>;">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php else : ?>
			<span class="<?php echo esc_attr( $class ); ?>"
				style="background:<?php echo esc_attr( $color ); ?>;color:<?php echo esc_attr( $text_color ); ?>;">
				<?php echo esc_html( $label ); ?>
			</span>
		<?php endif; ?>
		<span class="bb-sechead__rule" style="background:<?php echo esc_attr( $color ); ?>;"></span>
	</div>
	<?php
}

/**
 * Byline: author · date.
 */
function bb_byline( $show_readtime = true ) {
	?>
	<p class="bb-card__byline">
		<strong><?php the_author(); ?></strong>
		<span> · </span>
		<span><?php echo esc_html( bb_post_date() ); ?></span>
		<?php if ( $show_readtime ) : ?>
			<span> · </span>
			<span class="bb-readtime-pill">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Standard card: image, badge, title, byline, comment count, optional excerpt.
 *
 * @param array $args {
 *     @type string $image_size   Image size slug.
 *     @type string $height_class Height utility for the thumbnail.
 *     @type string $title_class  Title size utility.
 *     @type bool   $excerpt      Show the excerpt.
 *     @type int    $excerpt_len  Excerpt word count.
 *     @type string $clamp        Clamp class for the excerpt.
 * }
 */
function bb_card( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'image_size'   => 'bb-card',
		'height_class' => 'bb-ratio-44',
		'title_class'  => 'bb-card__title--md',
		'excerpt'      => false,
		'excerpt_len'  => 22,
		'clamp'        => 'bb-clamp-3',
		'meta'         => true,
	) );
	?>
	<article class="bb-card">
		<a class="bb-thumb <?php echo esc_attr( $args['height_class'] ); ?>" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?> aria-hidden="true" tabindex="-1">
			<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), $args['image_size'] ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
			<span class="bb-thumb__badge"><?php bb_badge(); ?></span>
		</a>
		<h3 class="bb-card__title <?php echo esc_attr( $args['title_class'] ); ?>">
			<a href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>><?php the_title(); ?></a>
		</h3>
		<?php if ( $args['meta'] ) : ?>
			<div class="bb-card__meta">
				<?php bb_byline( true ); ?>
				<span class="bb-count"><?php echo esc_html( bb_bangla_number( bb_comment_count() ) ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( $args['excerpt'] ) : ?>
			<p class="bb-card__excerpt <?php echo esc_attr( $args['clamp'] ); ?>">
				<?php echo esc_html( bb_excerpt( $args['excerpt_len'] ) ); ?>
			</p>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * Compact list row: small thumb + title + date.
 */
function bb_list_item( $size = 'bb-small' ) {
	?>
	<a class="bb-listitem" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<span class="bb-listitem__thumb">
			<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), $size ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
		</span>
		<span class="bb-listitem__body">
			<span class="bb-listitem__title"><?php the_title(); ?></span>
			<span class="bb-listitem__date">
				<span><?php echo esc_html( bb_post_date() ); ?></span>
				<span> · </span>
				<span class="bb-readtime-pill">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
			</span>
		</span>
	</a>
	<?php
}

/**
 * Wide row: large thumb on the left, title + byline + excerpt on the right.
 */
function bb_wide_row() {
	?>
	<a class="bb-wide-row" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<span class="bb-wide-row__thumb">
			<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-thumb' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
		</span>
		<span class="bb-wide-row__body">
			<span class="bb-wide-row__title"><?php the_title(); ?></span>
			<span class="bb-card__meta">
				<span class="bb-card__byline">
					<strong><?php the_author(); ?></strong> · <span><?php echo esc_html( bb_post_date() ); ?></span> · <span class="bb-readtime-pill">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
				</span>
				<span class="bb-count"><?php echo esc_html( bb_bangla_number( bb_comment_count() ) ); ?></span>
			</span>
			<span class="bb-card__excerpt bb-clamp-3"><?php echo esc_html( bb_excerpt( 24 ) ); ?></span>
		</span>
	</a>
	<?php
}

/**
 * Overlay panel used by the hero mosaic and archive mosaic.
 *
 * @param array $args {
 *     @type string $class       Wrapper class.
 *     @type string $image_size  Image size slug.
 *     @type bool   $byline      Show the author/date line.
 *     @type string $title_clamp Clamp class for the title.
 *     @type string $overlay     Overlay modifier class.
 * }
 */
function bb_overlay_panel( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'class'       => '',
		'image_size'  => 'bb-hero',
		'byline'      => false,
		'title_clamp' => 'bb-clamp-1',
		'overlay'     => '',
	) );
	?>
	<a class="<?php echo esc_attr( $args['class'] ); ?>" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), $args['image_size'] ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
		<span class="bb-overlay <?php echo esc_attr( $args['overlay'] ); ?>">
			<?php bb_badge(); ?>
			<span class="bb-overlay__title <?php echo esc_attr( $args['title_clamp'] ); ?>"><?php the_title(); ?></span>
			<span class="bb-overlay__byline">
				<?php if ( $args['byline'] ) : ?>
					<strong><?php the_author(); ?></strong> · <span><?php echo esc_html( bb_post_date() ); ?></span> · 
				<?php endif; ?>
				<span class="bb-readtime-pill bb-readtime-pill--overlay">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
			</span>
		</span>
	</a>
	<?php
}

/**
 * Centred dark card (the three-across strip).
 */
function bb_dark_card() {
	?>
	<a class="bb-darkcard" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-card' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
		<span class="bb-darkcard__inner">
			<?php bb_badge(); ?>
			<span class="bb-darkcard__title"><?php the_title(); ?></span>
			<span class="bb-darkcard__meta">
				<strong><?php the_author(); ?></strong> · <span><?php echo esc_html( bb_post_date() ); ?></span> · 
				<span class="bb-readtime-pill bb-readtime-pill--dark">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
			</span>
		</span>
	</a>
	<?php
}

/**
 * Title-only centred row (আরও পড়ুন).
 */
function bb_title_only() {
	?>
	<a class="bb-titleonly" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<span class="bb-titleonly__title"><?php the_title(); ?></span>
		<span class="bb-titleonly__meta">
			<strong><?php the_author(); ?></strong> · <span><?php echo esc_html( bb_post_date() ); ?></span> · 
			<span class="bb-readtime-pill">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
		</span>
	</a>
	<?php
}

/**
 * Footer list item (EDITOR PICKS / POPULAR POSTS).
 */
function bb_footer_item() {
	?>
	<a class="bb-footer__item" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<span class="bb-footer__item-thumb">
			<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-small' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
		</span>
		<span style="flex:1 1 0%;min-width:0;">
			<span class="bb-footer__item-title"><?php the_title(); ?></span>
			<span class="bb-footer__item-date">
				<span><?php echo esc_html( bb_post_date() ); ?></span> · 
				<span class="bb-readtime-pill">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
			</span>
		</span>
	</a>
	<?php
}

/**
 * Breadcrumb line.
 */
function bb_breadcrumb() {
	$home = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'প্রথম পাতা', 'bichitro-biggan' ) . '</a>';

	echo '<p class="bb-breadcrumb">' . $home; // phpcs:ignore WordPress.Security.EscapeOutput

	if ( is_singular( 'post' ) ) {
		$cat = bb_primary_category();
		if ( $cat ) {
			echo ' &rsaquo; <a href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
		}
		echo ' &rsaquo; <span class="current">' . esc_html( wp_trim_words( get_the_title(), 6, '…' ) ) . '</span>';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		echo ' &rsaquo; <span class="current">' . esc_html( single_term_title( '', false ) ) . '</span>';
	} elseif ( is_search() ) {
		echo ' &rsaquo; <span class="current">' . esc_html( get_search_query() ) . '</span>';
	} elseif ( is_page() ) {
		echo ' &rsaquo; <span class="current">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_year() || is_month() || is_day() ) {
		echo ' &rsaquo; <span class="current">' . esc_html( bb_current_label() ) . '</span>';
	} elseif ( is_author() ) {
		echo ' &rsaquo; <span class="current">' . esc_html( get_the_author() ) . '</span>';
	}

	echo '</p>';
}

/**
 * Share buttons — same four as the design.
 */
function bb_share_buttons() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	$image = rawurlencode( bb_thumb_url( get_the_ID(), 'bb-card' ) );

	$buttons = array(
		array(
			'label' => 'f',
			'bg'    => '#1877f2',
			'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
			'name'  => 'Facebook',
		),
		array(
			'label' => '𝕏',
			'bg'    => '#000000',
			'url'   => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
			'name'  => 'X',
		),
		array(
			'label' => '𝐩',
			'bg'    => '#e60023',
			'url'   => 'https://pinterest.com/pin/create/button/?url=' . $url . '&media=' . $image . '&description=' . $title,
			'name'  => 'Pinterest',
		),
		array(
			'label' => '●',
			'bg'    => '#25d366',
			'url'   => 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url,
			'name'  => 'WhatsApp',
		),
	);
	?>
	<div class="bb-share">
		<span class="bb-share__label"><?php esc_html_e( 'Share', 'bichitro-biggan' ); ?></span>
		<?php foreach ( $buttons as $b ) : ?>
			<a class="bb-share__btn"
				style="background:<?php echo esc_attr( $b['bg'] ); ?>;"
				href="<?php echo esc_url( $b['url'] ); ?>"
				target="_blank"
				rel="noopener noreferrer nofollow"
				aria-label="<?php echo esc_attr( $b['name'] ); ?>">
				<?php echo esc_html( $b['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Table of contents built from the <h2> headings in the post content.
 * Also injects matching ids into the rendered content.
 */
function bb_get_toc( $content ) {
	$headings = array();

	if ( ! preg_match_all( '/<(h2|h3)\b[^>]*>(.*?)<\/\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
		return array( 'content' => $content, 'toc' => array() );
	}

	// Imported posts often use <h3> as their top level, so promote it when no
	// <h2> is present rather than rendering an all-indented list.
	$has_h2 = false;
	foreach ( $matches as $match ) {
		if ( 'h2' === strtolower( $match[1] ) ) {
			$has_h2 = true;
			break;
		}
	}

	$index  = 0;
	$offset = 0;

	foreach ( $matches as $match ) {
		$tag  = strtolower( $match[1] );
		$text = trim( wp_strip_all_tags( $match[2] ) );

		if ( '' === $text ) {
			continue;
		}

		$original = $match[0];
		$pos      = strpos( $content, $original, $offset );

		if ( false === $pos ) {
			continue;
		}

		if ( preg_match( '/\bid=["\']([^"\']+)["\']/i', $original, $id_match ) ) {
			$id      = $id_match[1];
			$offset  = $pos + strlen( $original );
		} else {
			$id          = 'bb-sec-' . $index;
			$replacement = preg_replace( '/^<' . $tag . '/i', '<' . $tag . ' id="' . $id . '"', $original, 1 );
			$content     = substr_replace( $content, $replacement, $pos, strlen( $original ) );
			$offset      = $pos + strlen( $replacement );
		}

		$headings[] = array(
			'id'    => $id,
			'text'  => $text,
			'level' => ( 'h2' === $tag || ! $has_h2 ) ? 1 : 2,
		);
		$index++;
	}

	return array( 'content' => $content, 'toc' => $headings );
}
