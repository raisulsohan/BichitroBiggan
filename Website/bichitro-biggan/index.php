<?php
/**
 * Fallback template — also used for the posts page when a static front page is set.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="bb-container bb-section">
	<div class="bb-cols">

		<div class="bb-col-main">
			<?php
			$bb_title = is_home() && get_option( 'page_for_posts' )
				? get_the_title( get_option( 'page_for_posts' ) )
				: __( 'সকল লেখা', 'bichitro-biggan' );

			bb_section_heading( $bb_title, '#1a1a1a' );
			?>

			<div data-bb-list="posts">
			<?php if ( have_posts() ) : ?>
				<div class="bb-grid bb-grid--2">
					<?php
					while ( have_posts() ) :
						the_post();
						bb_card( array(
							'height_class' => 'bb-ratio-44',
							'title_class'  => 'bb-card__title--sm',
						) );
					endwhile;
					?>
				</div>

				<?php bb_pagination(); ?>
			</div><!-- [data-bb-list] -->
			<?php else : ?>
			</div><!-- [data-bb-list] -->
				<div class="bb-empty">
					<p class="bb-empty__title"><?php esc_html_e( 'কোনো লেখা পাওয়া যায়নি', 'bichitro-biggan' ); ?></p>
					<?php get_search_form(); ?>
				</div>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>

	</div>
</div>

<?php
get_footer();
