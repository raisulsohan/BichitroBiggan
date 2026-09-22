<?php
/**
 * Single post.
 *
 * This is the article view from the design (which was a modal in the demo),
 * rendered as a real page so it has its own URL, comments and metadata.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$bb_cat = bb_primary_category();

	/* Run the content through the filters, then pull the h2 headings out for the TOC. */
	$bb_rendered = apply_filters( 'the_content', get_the_content() );
	$bb_parsed   = bb_get_toc( $bb_rendered );
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'bb-single' ); ?>>

		<?php bb_breadcrumb(); ?>

		<?php if ( $bb_cat ) : ?>
			<div class="bb-single__badge"><?php bb_badge( $bb_cat, '', true ); ?></div>
		<?php endif; ?>

		<h1 class="bb-single__title"><?php the_title(); ?></h1>

		<div class="bb-authorrow">
			<div>
				<p class="bb-authorrow__date"><?php echo esc_html( bb_bangla_date() ); ?></p>
				<p class="bb-authorrow__reading-time">⏱ <?php echo esc_html( bb_reading_time_label() ); ?></p>
				<?php
				/* Most posts here are edited after publication; readers only ever saw the original date. */
				if ( get_the_modified_date( 'Y-m-d' ) !== get_the_date( 'Y-m-d' ) ) :
					?>
					<p class="bb-authorrow__updated">
						<?php
						printf(
							/* translators: %s: date the post was last edited. */
							esc_html__( 'সর্বশেষ হালনাগাদ: %s', 'bichitro-biggan' ),
							esc_html( bb_bangla_timestamp( get_post_modified_time( 'U', true ) ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<div class="bb-authorrow__stats">
				<span>👁 <?php echo esc_html( bb_bangla_number( bb_get_views() ) ); ?></span>
				<span>💬 <?php echo esc_html( bb_bangla_number( bb_comment_count() ) ); ?></span>
				<?php
				$bb_edit = get_edit_post_link();
				if ( $bb_edit ) :
					?>
					<a class="bb-editlink" href="<?php echo esc_url( $bb_edit ); ?>" target="_blank" rel="noopener">
						<span aria-hidden="true">✎</span> <?php esc_html_e( 'সম্পাদনা', 'bichitro-biggan' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php bb_share_buttons(); ?>

		<!-- Sticky Floating Actions Bar (TOC + Read Later Bookmark) -->
		<div class="bb-floating-bar">
			<?php if ( ! empty( $bb_parsed['toc'] ) ) : ?>
				<div class="bb-toc-container">
					<button type="button" class="bb-toc-toggle" aria-label="<?php esc_attr_e( 'সূচিপত্র দেখান বা লুকান', 'bichitro-biggan' ); ?>" aria-expanded="false">
						<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
						<span><?php esc_html_e( 'সূচিপত্র', 'bichitro-biggan' ); ?></span>
						<span class="bb-toc-caret">▼</span>
					</button>
					<nav class="bb-toc" aria-label="<?php esc_attr_e( 'সূচিপত্র', 'bichitro-biggan' ); ?>">
						<p class="bb-toc__title"><?php esc_html_e( 'সূচিপত্র', 'bichitro-biggan' ); ?></p>
						<ul class="bb-toc__list">
							<?php foreach ( $bb_parsed['toc'] as $bb_item ) : ?>
								<li class="<?php echo ( 2 === $bb_item['level'] ) ? 'bb-toc__sub' : ''; ?>">
									<a href="#<?php echo esc_attr( $bb_item['id'] ); ?>" data-bb-toc><?php echo esc_html( $bb_item['text'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</div>
			<?php endif; ?>

			<?php bb_bookmark_btn( get_the_ID(), 'bb-floating-bookmark-btn' ); ?>
		</div>

		<div class="bb-content">
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="bb-single__hero">
					<?php the_post_thumbnail( 'bb-hero', array( 'alt' => the_title_attribute( array( 'echo' => false ) ) ) ); ?>
				</figure>
			<?php endif; ?>
			<?php echo $bb_parsed['content']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php
			wp_link_pages( array(
				'before' => '<div class="bb-pagination__list" style="margin-top:16px;">',
				'after'  => '</div>',
			) );
			?>
		</div>

		<?php bb_references_list(); ?>

		<?php
		$bb_tags = get_the_tags();
		$bb_chips = array();
		if ( $bb_cat ) {
			$bb_chips[] = array( 'name' => $bb_cat->name, 'url' => get_category_link( $bb_cat ) );
		}
		if ( $bb_tags ) {
			foreach ( $bb_tags as $bb_tag ) {
				$bb_chips[] = array( 'name' => $bb_tag->name, 'url' => get_tag_link( $bb_tag ) );
			}
		}
		if ( ! empty( $bb_chips ) ) :
			?>
			<div class="bb-tags">
				<?php foreach ( $bb_chips as $bb_chip ) : ?>
					<a class="bb-tags__item" href="<?php echo esc_url( $bb_chip['url'] ); ?>"><?php echo esc_html( $bb_chip['name'] ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php bb_share_buttons(); ?>

		<?php
		$bb_prev = get_previous_post();
		$bb_next = get_next_post();
		if ( $bb_prev || $bb_next ) :
			?>
			<nav class="bb-prevnext">
				<?php if ( $bb_prev ) : ?>
					<a class="bb-prevnext__prev" href="<?php echo esc_url( get_permalink( $bb_prev ) ); ?>"<?php bb_article_attr( $bb_prev->ID ); ?>>← <?php esc_html_e( 'আগের আর্টিকেল', 'bichitro-biggan' ); ?></a>
				<?php endif; ?>
				<?php if ( $bb_next ) : ?>
					<a class="bb-prevnext__next" href="<?php echo esc_url( get_permalink( $bb_next ) ); ?>"<?php bb_article_attr( $bb_next->ID ); ?>><?php esc_html_e( 'পরের আর্টিকেল', 'bichitro-biggan' ); ?> →</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

		<?php $bb_bio = get_the_author_meta( 'description' ); ?>
		<div class="bb-authorbio">
			<?php echo get_avatar( get_the_author_meta( 'ID' ), 112 ); ?>
			<div>
				<p class="bb-authorbio__name">
					<a href="<?php echo esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a>
				</p>
				<p class="bb-authorbio__site"><?php echo esc_html( get_theme_mod( 'bb_author_bio_site', bb_default( 'author_bio_site' ) ) ); ?></p>
				<p class="bb-authorbio__text">
					<?php
					echo esc_html(
						$bb_bio ? $bb_bio : __( 'বিজ্ঞানের প্রতি গভীর আগ্রহ থেকে লেখালেখির শুরু। জটিল বৈজ্ঞানিক বিষয়গুলোকে সহজ বাংলায় পাঠকের কাছে পৌঁছে দেওয়াই লক্ষ্য।', 'bichitro-biggan' )
					);
					?>
				</p>
			</div>
		</div>

		<?php
		/* Related articles from the same category (indexed date order for fast TTFB). */
		$bb_related_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( get_the_ID() ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => 1,
			'orderby'             => 'date',
			'order'               => 'DESC',
		);

		/* Tags first. Two posts sharing a tag are related in a way that two
		   posts filed under the same broad category often are not — "মহাকাশ
		   বিজ্ঞান" holds everything from black holes to rocket launches. */
		$bb_tag_ids = wp_get_post_terms( get_the_ID(), 'post_tag', array( 'fields' => 'ids' ) );
		$bb_related = null;

		if ( ! is_wp_error( $bb_tag_ids ) && ! empty( $bb_tag_ids ) ) {
			$bb_related = new WP_Query( array_merge( $bb_related_args, array( 'tag__in' => $bb_tag_ids ) ) );
		}

		if ( ( ! $bb_related || ! $bb_related->have_posts() ) && $bb_cat ) {
			$bb_related = new WP_Query( array_merge( $bb_related_args, array( 'cat' => $bb_cat->term_id ) ) );
		}

		if ( ! $bb_related || ! $bb_related->have_posts() ) {
			$bb_related = new WP_Query( $bb_related_args );
		}

		if ( $bb_related->have_posts() ) :
			?>
			<section class="bb-related">
				<h3 class="bb-related__heading"><?php esc_html_e( 'এই বিভাগের আরও লেখা', 'bichitro-biggan' ); ?></h3>
				<div class="bb-related__grid">
					<?php
					while ( $bb_related->have_posts() ) :
						$bb_related->the_post();
						?>
						<a class="bb-related__item" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
							<span class="bb-related__thumb">
								<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-thumb' ) ); ?>" alt="<?php the_title_attribute(); ?>"<?php bb_img_attrs( get_the_ID(), 'bb-thumb' ); ?> />
								<span class="bb-related__badge"><?php bb_badge(); ?></span>
							</span>
							<span class="bb-related__title"><?php the_title(); ?></span>
						</a>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>

	</article>

	<?php
endwhile;

get_footer();
