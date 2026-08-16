<?php
/**
 * Template Name: Posts Page (সকল লেখা)
 * Template Post Type: page
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$bb_posts_q = new WP_Query( array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => bb_all_posts_count(),
	'paged'               => $paged,
	'ignore_sticky_posts' => 1,
) );
?>

<div class="bb-container bb-section">
	<div class="bb-cols">

		<div class="bb-col-main">
			<?php
			$bb_title = get_the_title();
			if ( ! $bb_title ) {
				$bb_title = __( 'সকল লেখা', 'bichitro-biggan' );
			}
			bb_section_heading( $bb_title, '#1a1a1a' );
			?>

			<div data-bb-list="posts">
			<?php if ( $bb_posts_q->have_posts() ) : ?>
				<div class="bb-grid bb-grid--2">
					<?php
					while ( $bb_posts_q->have_posts() ) :
						$bb_posts_q->the_post();
						bb_card( array(
							'height_class' => 'bb-ratio-44',
							'title_class'  => 'bb-card__title--sm',
						) );
					endwhile;
					?>
				</div>

				<?php bb_pagination( $bb_posts_q ); ?>
			</div><!-- [data-bb-list] -->
			<?php else : ?>
			</div><!-- [data-bb-list] -->
				<div class="bb-empty">
					<p class="bb-empty__title"><?php esc_html_e( 'কোনো লেখা পাওয়া যায়নি', 'bichitro-biggan' ); ?></p>
					<?php get_search_form(); ?>
				</div>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</div>

		<?php get_sidebar(); ?>

	</div>
</div>

<?php
get_footer();
