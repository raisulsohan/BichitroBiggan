<?php
/**
 * Archive: category, tag, year, month, author.
 *
 * Layout mirrors the CategoryPage view from the design — breadcrumb, title,
 * a five-panel featured mosaic, then a three-across card grid.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

/* Heading colour: the category's own colour, blue for date archives. */
$bb_archive_color = '#1a8cca';
if ( is_category() || is_tax() ) {
	$bb_archive_color = bb_term_color( get_queried_object() );
}

$bb_archive_title = bb_current_label();
if ( is_category() || is_tag() || is_tax() ) {
	$bb_archive_title = single_term_title( '', false );
} elseif ( is_year() ) {
	/* translators: %s: year */
	$bb_archive_title = sprintf( __( '%s সালের লেখা', 'bichitro-biggan' ), get_the_date( 'Y' ) );
} elseif ( is_month() ) {
	$bb_archive_title = get_the_date( 'F Y' );
} elseif ( is_author() ) {
	$bb_archive_title = get_the_author();
}
?>

<div class="bb-archive">
	<div class="bb-archive__inner">

		<?php bb_breadcrumb(); ?>

		<h1 class="bb-archive__title"><?php echo esc_html( $bb_archive_title ); ?></h1>

		<?php
		$bb_desc = term_description();
		if ( $bb_desc ) :
			?>
			<div class="bb-archive__desc"><?php echo wp_kses_post( $bb_desc ); ?></div>
		<?php endif; ?>

		<div data-bb-list="archive">
		<?php if ( have_posts() ) : ?>

			<?php
			/* Collect this page's posts so the mosaic and the grid can split them. */
			$bb_posts = array();
			while ( have_posts() ) {
				the_post();
				$bb_posts[] = get_post();
			}
			rewind_posts();

			$bb_split    = bb_archive_featured_count();
			$bb_featured = $bb_split ? array_slice( $bb_posts, 0, $bb_split ) : array();
			$bb_grid     = array_slice( $bb_posts, count( $bb_featured ) );
			?>

			<?php if ( ! empty( $bb_featured ) ) : ?>
				<div class="bb-mosaic">

					<?php
					$GLOBALS['post'] = $bb_featured[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					setup_postdata( $GLOBALS['post'] );
					bb_overlay_panel( array(
						'class'  => 'bb-mosaic__big',
						'byline' => true,
					) );
					wp_reset_postdata();
					?>

					<?php if ( count( $bb_featured ) > 1 ) : ?>
						<div class="bb-mosaic__grid">
							<?php
							foreach ( array_slice( $bb_featured, 1 ) as $bb_fp ) :
								$GLOBALS['post'] = $bb_fp; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
								setup_postdata( $GLOBALS['post'] );
								bb_overlay_panel( array(
									'class'       => 'bb-mosaic__cell',
									'image_size'  => 'bb-thumb',
									'title_clamp' => 'bb-clamp-1',
								) );
							endforeach;
							wp_reset_postdata();
							?>
						</div>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php if ( ! empty( $bb_grid ) ) : ?>
				<div class="bb-grid bb-grid--3" style="margin-bottom:32px;">
					<?php
					foreach ( $bb_grid as $bb_gp ) :
						$GLOBALS['post'] = $bb_gp; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
						setup_postdata( $GLOBALS['post'] );
						bb_card( array(
							'height_class' => 'bb-ratio-40',
							'title_class'  => 'bb-card__title--sm',
						) );
					endforeach;
					wp_reset_postdata();
					?>
				</div>
			<?php endif; ?>

			<div style="margin-bottom:40px;">
				<?php bb_pagination(); ?>
			</div>
			</div><!-- [data-bb-list] -->

		<?php else : ?>
			</div><!-- [data-bb-list] -->

			<div class="bb-empty">
				<p class="bb-empty__title"><?php esc_html_e( 'কোনো লেখা পাওয়া যায়নি', 'bichitro-biggan' ); ?></p>
				<p class="bb-empty__text"><?php esc_html_e( 'এই বিভাগে এখনো কিছু প্রকাশ করা হয়নি। অন্য কিছু খুঁজে দেখুন।', 'bichitro-biggan' ); ?></p>
				<?php get_search_form(); ?>
			</div>

		<?php endif; ?>

	</div>
</div>

<?php
get_footer();
