<?php
/**
 * Search results.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="bb-container bb-section">

	<?php bb_breadcrumb(); ?>

	<h1 class="bb-archive__title">
		<?php
		printf(
			/* translators: %s: search term */
			esc_html__( '"%s" — সার্চ ফলাফল', 'bichitro-biggan' ),
			esc_html( get_search_query() )
		);
		?>
	</h1>

	<div class="bb-cols">

		<div class="bb-col-main">
			<div data-bb-list="search">
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
					<p class="bb-empty__title"><?php esc_html_e( 'কিছুই পাওয়া গেল না', 'bichitro-biggan' ); ?></p>
					<p class="bb-empty__text"><?php esc_html_e( 'অন্য কোনো শব্দ দিয়ে খুঁজে দেখুন।', 'bichitro-biggan' ); ?></p>
					<?php get_search_form(); ?>
				</div>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>

	</div>
</div>

<?php
get_footer();
