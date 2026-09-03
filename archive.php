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

		<?php if ( is_category() || is_tax() ) : ?>
			<h1 class="screen-reader-text bb-screen-reader-text"><?php echo esc_html( $bb_archive_title ); ?></h1>
		<?php else : ?>
			<h1 class="bb-archive__title"><?php echo esc_html( $bb_archive_title ); ?></h1>
		<?php endif; ?>

		<?php
		$bb_desc = term_description();
		if ( $bb_desc ) :
			?>
			<div class="bb-archive__desc"><?php echo wp_kses_post( $bb_desc ); ?></div>
		<?php endif; ?>

		<?php
		$bb_filter_y = isset( $_GET['bb_year'] ) ? absint( $_GET['bb_year'] ) : 0;
		$bb_filter_m = isset( $_GET['bb_month'] ) ? absint( $_GET['bb_month'] ) : 0;
		?>

		<?php if ( $bb_filter_y > 0 ) : ?>
			<?php
			$bb_filter_label = bb_bangla_number( $bb_filter_y ) . ' সালের ';
			if ( $bb_filter_m > 0 ) {
				$bb_filter_label = $GLOBALS['wp_locale']->get_month( $bb_filter_m ) . ' ' . $bb_filter_label;
			}
			$bb_filter_label .= 'আর্কাইভ';
			$bb_reset_url     = get_category_link( get_queried_object_id() );
			?>
			<div class="bb-filter-pill">
				<span>📅 <?php echo esc_html( $bb_filter_label ); ?></span>
				<a href="<?php echo esc_url( $bb_reset_url ); ?>" class="bb-filter-pill__reset" title="<?php esc_attr_e( 'ফিল্টার মুছুন', 'bichitro-biggan' ); ?>">✕ <?php esc_html_e( 'সব লেখা দেখুন', 'bichitro-biggan' ); ?></a>
			</div>
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

			$bb_paged     = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
			$bb_show_hero = ( 1 === $bb_paged && 0 === $bb_filter_y );
			$bb_split     = $bb_show_hero ? min( 4, (int) bb_archive_featured_count() ) : 0;
			$bb_featured  = $bb_split ? array_slice( $bb_posts, 0, min( $bb_split, count( $bb_posts ) ) ) : array();
			$bb_grid      = array_slice( $bb_posts, count( $bb_featured ) );
			?>

			<?php if ( ! empty( $bb_featured ) ) : ?>
				<div class="bb-hero" style="padding:0;margin-bottom:32px;">
					<div class="bb-hero__grid">

						<!-- Main Big Card (Left) -->
						<div class="bb-hero__col bb-hero__col--left">
							<?php
							$GLOBALS['post'] = $bb_featured[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
							setup_postdata( $GLOBALS['post'] );
							bb_hero_card( array(
								'class'      => 'bb-hero__main',
								'image_size' => 'bb-hero',
								'byline'     => true,
								'overlay'    => 'bb-overlay--deep',
							) );
							wp_reset_postdata();
							?>
						</div>

						<?php if ( count( $bb_featured ) > 1 ) : ?>
							<div class="bb-hero__col bb-hero__col--right">
								<!-- Top Right Card -->
								<?php
								$GLOBALS['post'] = $bb_featured[1]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
								setup_postdata( $GLOBALS['post'] );
								bb_hero_card( array(
									'class'      => 'bb-hero__top',
									'image_size' => 'bb-card',
									'byline'     => true,
								) );
								wp_reset_postdata();
								?>

								<?php if ( count( $bb_featured ) > 2 ) : ?>
									<div class="bb-hero__row">
										<!-- Bottom Left/Mid Card -->
										<?php
										$GLOBALS['post'] = $bb_featured[2]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
										setup_postdata( $GLOBALS['post'] );
										bb_hero_card( array(
											'class'       => 'bb-hero__tile bb-hero__tile--left',
											'image_size'  => 'bb-thumb',
											'overlay'     => 'bb-overlay--deep',
											'title_clamp' => 'bb-clamp-1',
											'byline'      => true,
										) );
										wp_reset_postdata();
										?>

										<!-- Bottom Right Card -->
										<?php if ( count( $bb_featured ) > 3 ) : ?>
											<?php
											$GLOBALS['post'] = $bb_featured[3]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
											setup_postdata( $GLOBALS['post'] );
											bb_hero_card( array(
											'class'       => 'bb-hero__tile bb-hero__tile--right',
											'image_size'  => 'bb-thumb',
											'overlay'     => 'bb-overlay--deep',
											'title_clamp' => 'bb-clamp-1',
											'byline'      => true,
										) );
										wp_reset_postdata();
										?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				</div>
			</div>
		<?php endif; ?>

			<div class="bb-cols">
				<div class="bb-col-main">
					<?php if ( ! empty( $bb_grid ) ) : ?>
						<div class="bb-grid bb-grid--2" style="margin-bottom:32px;">
							<?php
							foreach ( $bb_grid as $bb_gp ) :
								$GLOBALS['post'] = $bb_gp; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
								setup_postdata( $GLOBALS['post'] );
								bb_card( array(
									'height_class' => 'bb-ratio-44',
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
				</div>

				<?php get_sidebar(); ?>
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
