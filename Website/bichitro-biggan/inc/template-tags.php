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

	$color      = bb_term_color( $term );
	$text_color = bb_contrast_color( $color );
	$class      = trim( 'bb-badge ' . $extra_class );
	$style      = 'background:' . esc_attr( $color ) . ';color:' . esc_attr( $text_color ) . ';';

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
 * Polymorphic Hero Card that handles BOTH regular articles AND podcast posts in any slot.
 *
 * @param array $args
 */
function bb_hero_card( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'class'       => '',
		'image_size'  => 'bb-hero',
		'byline'      => true,
		'title_clamp' => '',
		'overlay'     => 'bb-overlay--deep',
		'custom_time' => '',
	) );

	$post_id    = get_the_ID();
	$is_podcast = bb_is_podcast_post( $post_id );
	$video_url  = bb_get_post_video_url( $post_id );
	$cat        = bb_primary_category( $post_id );
	
	// Check for specific custom duration field first, then arg, then reading time
	$video_duration = get_post_meta( $post_id, 'video_duration', true );
	$duration = ! empty( $video_duration ) ? $video_duration : ( ! empty( $args['custom_time'] ) ? $args['custom_time'] : bb_reading_time( $post_id ) );

	if ( $is_podcast || ! empty( $video_url ) ) {
		$podcast_class = trim( $args['class'] . ' bb-hero__podcast' . ( ! empty( $video_url ) ? ' bb-has-video' : '' ) );
		$video_attr    = ! empty( $video_url ) ? ' data-bb-video="' . esc_url( $video_url ) . '"' : '';
		$thumb_src     = bb_thumb_url( $post_id, 'full' );
		?>
		<a class="<?php echo esc_attr( $podcast_class ); ?>" href="<?php the_permalink(); ?>"<?php echo $video_attr; ?><?php if ( empty( $video_url ) ) { bb_article_attr( $post_id ); } ?>>
			<img class="bb-hero__podcast-bg" src="<?php echo esc_url( $thumb_src ); ?>" alt="" loading="lazy" aria-hidden="true" />
			<img class="bb-hero__podcast-img" src="<?php echo esc_url( $thumb_src ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
			<?php if ( ! empty( $video_url ) ) : ?>
				<span class="bb-hero__play-btn" role="button" aria-label="<?php esc_attr_e( 'ভিডিও সরাসরি দেখুন', 'bichitro-biggan' ); ?>">
					<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
				</span>
			<?php endif; ?>
			<span class="bb-hero__podcast-inner">
				<span class="bb-hero__podcast-chip">
					<?php if ( has_custom_logo() ) :
						$bb_custom_logo_id = get_theme_mod( 'custom_logo' );
						$bb_logo_src       = wp_get_attachment_image_url( $bb_custom_logo_id, 'medium' );
						?>
						<span class="l1 l1--logo"><img src="<?php echo esc_url( $bb_logo_src ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="bb-hero__podcast-logo" /></span>
					<?php else : ?>
						<span class="l1"><?php bloginfo( 'name' ); ?></span>
					<?php endif; ?>
					<span class="l2"><?php echo esc_html( $cat ? $cat->name : __( 'পডকাস্ট', 'bichitro-biggan' ) ); ?></span>
					<span class="l3">🎙</span>
				</span>
				<span>
					<?php bb_badge( $cat ); ?>
					<span class="bb-overlay__title <?php echo esc_attr( $args['title_clamp'] ); ?>"><span class="bb-title-text"><?php the_title(); ?></span></span>
					<span class="bb-overlay__byline">
						<?php if ( $args['byline'] ) : ?>
							<strong><?php the_author(); ?></strong> · <span><?php echo esc_html( bb_post_date() ); ?></span> · 
						<?php endif; ?>
						<span class="bb-readtime-pill bb-readtime-pill--overlay">⏱ <?php echo esc_html( $duration ); ?></span>
					</span>
				</span>
			</span>
		</a>
		<?php
		return;
	}

	/* Standard article */
	bb_overlay_panel( array(
		'class'       => $args['class'],
		'image_size'  => $args['image_size'],
		'byline'      => $args['byline'],
		'title_clamp' => $args['title_clamp'],
		'overlay'     => $args['overlay'],
	) );
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
	$show_thumb = (bool) get_theme_mod( 'bb_footer_show_thumbs', false );
	?>
	<a class="bb-footer__item<?php echo $show_thumb ? ' bb-footer__item--has-thumb' : ''; ?>" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
		<?php if ( $show_thumb ) : ?>
			<span class="bb-footer__item-thumb">
				<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-small' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
			</span>
		<?php endif; ?>
		<span class="bb-footer__item-content">
			<span class="bb-footer__item-title"><?php the_title(); ?></span>
			<span class="bb-footer__item-meta">
				<strong><?php the_author(); ?></strong> · <span><?php echo esc_html( bb_post_date() ); ?></span> · 
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

/**
 * Published post choices for Customizer and Theme Settings dropdowns.
 *
 * @return array Array of [ post_id => title_and_date ]
 */
function bb_post_choices() {
	static $choices = null;

	if ( null !== $choices ) {
		return $choices;
	}

	$choices = array( 0 => __( '— Auto (Latest Post) —', 'bichitro-biggan' ) );

	$posts = get_posts( array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'posts_per_page'   => 400,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => false,
	) );

	foreach ( $posts as $p ) {
		$cats = get_the_category( $p->ID );
		$cat_str = '';
		if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
			$cat_str = '[' . $cats[0]->name . '] ';
		}
		$choices[ $p->ID ] = $cat_str . get_the_title( $p ) . ' (' . get_the_date( 'j M Y', $p ) . ')';
	}

	return $choices;
}

/**
 * Return a map of post_id => array of category_ids for JS live filtering.
 *
 * @return array
 */
function bb_get_post_cat_map() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map = array();
	$posts = get_posts( array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'posts_per_page'   => 400,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => false,
	) );

	foreach ( $posts as $p ) {
		$cat_ids = wp_get_post_categories( $p->ID );
		$map[ $p->ID ] = array_map( 'intval', $cat_ids );
	}

	return $map;
}

/**
 * Detect if a post belongs to the podcast category or is a podcast.
 *
 * @param int|WP_Post $post Post ID or object.
 * @return bool
 */
function bb_is_podcast_post( $post = null ) {
	$post_id = $post ? ( is_object( $post ) ? $post->ID : absint( $post ) ) : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$categories = get_the_category( $post_id );
	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		return false;
	}

	$configured_pod_id = (int) get_theme_mod( 'bb_cat_podcast', 0 );

	foreach ( $categories as $cat ) {
		if ( $configured_pod_id && (int) $cat->term_id === $configured_pod_id ) {
			return true;
		}
		// bb_str_pos(): mbstring না থাকলেও ফলব্যাক করে, তাই সরাসরি mb_stripos() নয়।
		if ( false !== stripos( $cat->slug, 'podcast' ) || false !== bb_str_pos( $cat->name, 'পডকাস্ট' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Extract first video URL (YouTube, Vimeo, MP4) from post content or meta.
 *
 * @param int|WP_Post $post Post ID or object.
 * @return string Embed URL or empty string.
 */
function bb_get_post_video_url( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	// 1. Custom field override if provided
	$meta_url = get_post_meta( $post->ID, '_bb_video_url', true );
	if ( empty( $meta_url ) ) {
		$meta_url = get_post_meta( $post->ID, 'video_url', true );
	}
	if ( ! empty( $meta_url ) ) {
		return bb_normalize_video_embed_url( $meta_url );
	}

	// 2. Search post content for YouTube / Vimeo embed patterns
	$content = $post->post_content;
	if ( empty( $content ) ) {
		return '';
	}

	// YouTube match (watch?v=, youtu.be, embed, shorts)
	if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $content, $m ) ) {
		return 'https://www.youtube-nocookie.com/embed/' . $m[1];
	}

	// Vimeo match
	if ( preg_match( '/(?:vimeo\.com\/(?:video\/)?)([0-9]+)/i', $content, $m ) ) {
		return 'https://player.vimeo.com/video/' . $m[1];
	}

	// Extract iframe src
	if ( preg_match( '/src=["\'](https?:\/\/(?:www\.)?(?:youtube(?:-nocookie)?\.com\/embed\/[a-zA-Z0-9_-]+|player\.vimeo\.com\/video\/[0-9]+)[^"\']*)["\']/i', $content, $m ) ) {
		return $m[1];
	}

	// Any URLs in post
	$urls = wp_extract_urls( $content );
	foreach ( $urls as $url ) {
		$norm = bb_normalize_video_embed_url( $url );
		if ( ! empty( $norm ) ) {
			return $norm;
		}
	}

	return '';
}

/**
 * Normalizes a raw video URL to an iframe embed URL.
 *
 * @param string $url
 * @return string
 */
function bb_normalize_video_embed_url( $url ) {
	if ( empty( $url ) ) {
		return '';
	}
	if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $url, $m ) ) {
		return 'https://www.youtube-nocookie.com/embed/' . $m[1];
	}
	if ( preg_match( '/(?:vimeo\.com\/(?:video\/)?)([0-9]+)/i', $url, $m ) ) {
		return 'https://player.vimeo.com/video/' . $m[1];
	}
	return '';
}


/**
 * Bookmark toggle button.
 */
function bb_bookmark_btn( $post_id = null, $extra_class = '' ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return;
	}
	$title     = get_the_title( $post_id );
	$permalink = get_permalink( $post_id );
	$thumb_url = bb_thumb_url( $post_id, 'bb-small' );
	$read_time = bb_reading_time( $post_id );
	$post_date = bb_post_date( $post_id );
	?>
	<button type="button" 
		class="bb-bookmark-btn <?php echo esc_attr( $extra_class ); ?>" 
		data-bb-bookmark="<?php echo esc_attr( $post_id ); ?>" 
		data-title="<?php echo esc_attr( $title ); ?>" 
		data-url="<?php echo esc_url( $permalink ); ?>" 
		data-thumb="<?php echo esc_url( $thumb_url ); ?>" 
		data-time="<?php echo esc_attr( $read_time ); ?>" 
		data-date="<?php echo esc_attr( $post_date ); ?>" 
		title="<?php esc_attr_e( 'পরে পড়ার জন্য সংরক্ষণ করুন', 'bichitro-biggan' ); ?>" 
		aria-label="<?php esc_attr_e( 'পরে পড়ার জন্য সংরক্ষণ করুন', 'bichitro-biggan' ); ?>">
		<span class="bb-bookmark-icon">🔖</span>
		<span class="bb-bookmark-text"><?php esc_html_e( 'পরে পড়ুন', 'bichitro-biggan' ); ?></span>
	</button>
	<?php
}

/**
 * Bookmarks drawer slide-over modal markup.
 */
function bb_bookmarks_drawer() {
	?>
	<div class="bb-drawer-overlay" id="bb-bookmarks-overlay" aria-hidden="true"></div>
	<div class="bb-drawer" id="bb-bookmarks-drawer" role="dialog" aria-labelledby="bb-bookmarks-title" aria-modal="true" aria-hidden="true">
		<div class="bb-drawer__header">
			<div class="bb-drawer__title-wrap">
				<span class="bb-drawer__icon">🔖</span>
				<h3 class="bb-drawer__title" id="bb-bookmarks-title"><?php esc_html_e( 'সংরক্ষিত লেখাগুলো', 'bichitro-biggan' ); ?></h3>
				<span class="bb-count-pill" id="bb-drawer-count">0</span>
			</div>
			<button type="button" class="bb-drawer__close" id="bb-bookmarks-close" aria-label="<?php esc_attr_e( 'বন্ধ করুন', 'bichitro-biggan' ); ?>">✕</button>
		</div>
		<div class="bb-drawer__body" id="bb-bookmarks-list">
			<!-- Populated by JavaScript -->
		</div>
		<div class="bb-drawer__footer" id="bb-bookmarks-footer" style="display:none;">
			<button type="button" class="bb-drawer__clear-btn" id="bb-bookmarks-clear">
				<?php esc_html_e( 'সব মুছে ফেলুন', 'bichitro-biggan' ); ?>
			</button>
		</div>
	</div>
	<?php
}

