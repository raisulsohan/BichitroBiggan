<?php
/**
 * Category Template: Podcast
 * 
 * Renders a music-app style grid (square 1:1 thumbnails) with a 
 * popup video player.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

\ = single_term_title( '', false );
?>

<div class="bb-archive bb-archive--podcast">
	<div class="bb-archive__inner">

		<?php bb_breadcrumb(); ?>
		<h1 class="bb-archive__title"><?php echo esc_html( \ ); ?></h1>

		<?php
		\ = term_description();
		if ( \ ) :
			?>
			<div class="bb-archive__desc"><?php echo wp_kses_post( \ ); ?></div>
		<?php endif; ?>

		<div data-bb-list="archive">
		<?php if ( have_posts() ) : ?>
			<div class="bb-podcast-grid" style="margin-bottom:32px;">
				<?php while ( have_posts() ) : the_post(); 
					\ = get_post_meta( get_the_ID(), 'bb_video_url', true );
					\  = bb_reading_time( get_the_ID() );
				?>
					<article class="bb-pod-item">
						<a href="<?php echo esc_url( \ ? \ : get_permalink() ); ?>" 
						   class="bb-pod-thumb" 
						   <?php if ( \ ) { echo 'data-bb-video-popup="' . esc_attr( \ ) . '"'; } else { bb_article_attr(); } ?>>
							<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-card' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
							<span class="bb-pod-play">
								<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
							</span>
						</a>
						<h3 class="bb-pod-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<div class="bb-pod-meta">
							<?php echo esc_html( get_the_date( 'Y' ) ); ?> &bull; <?php echo esc_html( \ ); ?>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

			<div style="margin-bottom:40px;">
				<?php bb_pagination(); ?>
			</div>
		<?php else : ?>
			<div class="bb-empty">
				<p class="bb-empty__title"><?php esc_html_e( 'কোনো পডকাস্ট পাওয়া যায়নি', 'bichitro-biggan' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>
		</div>

	</div>
</div>

<?php
get_footer();
