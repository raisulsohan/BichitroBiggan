<?php
/**
 * Template Name: Homepage (প্রথম পাতা)
 * Template Post Type: page
 *
 * Front page — the magazine layout from the design.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

/* Posts already shown, so the lower blocks can skip them where it matters. */
$bb_used = array();
?>

<?php
/* =====================================================================
 * HERO MOSAIC
 * ================================================================== */
$bb_podcast_cat = bb_block_cat( 'bb_cat_podcast', '%e0%a6%aa%e0%a6%a1%e0%a6%95%e0%a6%be%e0%a6%b8%e0%a7%8d%e0%a6%9f' );

$bb_podcast_post = null;
if ( $bb_podcast_cat ) {
	$bb_pod_q = bb_query( $bb_podcast_cat, 1 );
	if ( $bb_pod_q->have_posts() ) {
		$bb_podcast_post = $bb_pod_q->posts[0];
		$bb_used[]       = $bb_podcast_post->ID;
	}
	wp_reset_postdata();
}

$bb_hero = bb_query( 0, $bb_podcast_post ? 3 : 4, $bb_used );

if ( $bb_hero->have_posts() ) :
	$bb_hero_posts = $bb_hero->posts;
	foreach ( $bb_hero_posts as $bb_p ) {
		$bb_used[] = $bb_p->ID;
	}
	?>
	<section class="bb-hero">
		<div class="bb-hero__grid">

			<div class="bb-hero__col">
				<?php
				$bb_hero->the_post();
				bb_overlay_panel( array(
					'class'   => 'bb-hero__main',
					'byline'  => true,
					'overlay' => 'bb-overlay--deep',
				) );
				?>
			</div>

			<div class="bb-hero__col">
				<?php
				if ( $bb_hero->have_posts() ) :
					$bb_hero->the_post();
					bb_overlay_panel( array(
						'class'      => 'bb-hero__top',
						'image_size' => 'bb-card',
					) );
				endif;
				?>

				<div class="bb-hero__row">
					<?php
					if ( $bb_hero->have_posts() ) :
						$bb_hero->the_post();
						bb_overlay_panel( array(
							'class'       => 'bb-hero__tile',
							'image_size'  => 'bb-thumb',
							'overlay'     => 'bb-overlay--deep',
							'title_clamp' => 'bb-clamp-1',
						) );
					endif;
					?>

					<?php if ( $bb_podcast_post ) : ?>
						<?php
						$bb_pod_id  = $bb_podcast_post->ID;
						$bb_pod_cat = bb_primary_category( $bb_pod_id );
						?>
						<a class="bb-hero__tile bb-hero__podcast" href="<?php echo esc_url( get_permalink( $bb_pod_id ) ); ?>"<?php bb_article_attr( $bb_pod_id ); ?>>
							<img src="<?php echo esc_url( bb_thumb_url( $bb_pod_id, 'bb-thumb' ) ); ?>" alt="<?php echo esc_attr( get_the_title( $bb_pod_id ) ); ?>" loading="lazy" />
							<span class="bb-hero__podcast-inner">
								<span class="bb-hero__podcast-chip">
									<span class="l1"><?php bloginfo( 'name' ); ?></span>
									<span class="l2"><?php echo esc_html( $bb_pod_cat ? $bb_pod_cat->name : __( 'পডকাস্ট', 'bichitro-biggan' ) ); ?></span>
									<span class="l3">🎙</span>
								</span>
								<span>
									<?php bb_badge( $bb_pod_cat ); ?>
									<span class="bb-overlay__title" style="font-size:12px;"><span class="bb-title-text"><?php echo esc_html( get_the_title( $bb_pod_id ) ); ?></span></span>
								</span>
							</span>
						</a>
					<?php elseif ( $bb_hero->have_posts() ) : ?>
						<?php
						$bb_hero->the_post();
						bb_overlay_panel( array(
							'class'       => 'bb-hero__tile',
							'image_size'  => 'bb-thumb',
							'overlay'     => 'bb-overlay--deep',
							'title_clamp' => 'bb-clamp-1',
						) );
						?>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</section>
	<?php
	wp_reset_postdata();
endif;
?>

<?php
/* =====================================================================
 * BLOCK 1 — featured category + secondary category grid
 * ================================================================== */
$bb_b1l_id   = bb_block_cat( 'bb_cat_block1_left', 'quantum-sciences', 0 );
$bb_b1r_id   = bb_block_cat( 'bb_cat_block1_right', 'nobel-prizes', 1 );
$bb_b1l_name = bb_block_cat_name( $bb_b1l_id, __( 'কোয়ান্টাম বিজ্ঞান', 'bichitro-biggan' ) );
$bb_b1r_name = bb_block_cat_name( $bb_b1r_id, __( 'নোবেল পুরষ্কার', 'bichitro-biggan' ) );
$bb_b1l_col  = $bb_b1l_id ? bb_term_color( $bb_b1l_id ) : '#f5c800';
$bb_b1r_col  = $bb_b1r_id ? bb_term_color( $bb_b1r_id ) : '#1a1a1a';

$bb_slides = bb_slider_slides();

/* Fetch enough for every slide; the arrows page through these. */
$bb_b1l_per = 1 + bb_feature_list_count();
$bb_b1l = bb_query( $bb_b1l_id, $bb_b1l_per * $bb_slides );
$bb_b1r = bb_query( $bb_b1r_id, 4 * $bb_slides );

$bb_b1l_chunks = array_chunk( $bb_b1l->posts, $bb_b1l_per );
$bb_b1r_chunks = array_chunk( $bb_b1r->posts, 4 );

if ( $bb_b1l->have_posts() || $bb_b1r->have_posts() ) :
	?>
	<section class="bb-container bb-section">
		<div class="bb-cols bb-cols--stretch">

			<div class="bb-col-main">
				<?php if ( ! empty( $bb_b1l_chunks ) ) : ?>
					<?php bb_section_heading( $bb_b1l_name, $bb_b1l_col, false, $bb_b1l_id ? get_category_link( $bb_b1l_id ) : '' ); ?>

					<div data-bb-slider>
						<?php foreach ( $bb_b1l_chunks as $bb_i => $bb_chunk ) : ?>
							<div class="bb-slider__slide"<?php echo $bb_i ? ' hidden' : ''; ?>>
								<div class="bb-feature">
									<div class="bb-feature__main">
										<?php
										bb_setup_post( $bb_chunk[0] );
										bb_card( array(
											'image_size'   => 'bb-card',
											'height_class' => 'bb-ratio-44',
											'title_class'  => 'bb-card__title--md',
											'excerpt'      => true,
											'excerpt_len'  => 20,
											'clamp'        => 'bb-clamp-4',
										) );
										bb_slider_arrows( count( $bb_b1l_chunks ) );
										?>
									</div>

									<div class="bb-feature__list bb-divide-y">
										<?php
										foreach ( array_slice( $bb_chunk, 1 ) as $bb_p ) {
											bb_setup_post( $bb_p );
											bb_list_item();
										}
										?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<?php wp_reset_postdata(); ?>
				<?php endif; ?>
			</div>

			<div class="bb-col-side">
				<?php if ( ! empty( $bb_b1r_chunks ) ) : ?>
					<?php bb_section_heading( $bb_b1r_name, $bb_b1r_col, false, $bb_b1r_id ? get_category_link( $bb_b1r_id ) : '' ); ?>

					<div data-bb-slider>
						<?php foreach ( $bb_b1r_chunks as $bb_i => $bb_chunk ) : ?>
							<div class="bb-slider__slide"<?php echo $bb_i ? ' hidden' : ''; ?>>
								<div class="bb-grid bb-grid--2-always bb-grid--gap-xs">
									<?php foreach ( $bb_chunk as $bb_p ) : ?>
										<?php bb_setup_post( $bb_p ); ?>
										<article class="bb-card">
											<a class="bb-thumb bb-ratio-24" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>>
												<img src="<?php echo esc_url( bb_thumb_url( get_the_ID(), 'bb-thumb' ) ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
												<span class="bb-thumb__badge bb-thumb__badge--tight"><?php bb_badge(); ?></span>
											</a>
											<h3 class="bb-card__title" style="font-size:12px;margin-top:6px;">
												<a href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?>><?php the_title(); ?></a>
											</h3>
											<p class="bb-card__byline" style="font-size:11px;margin-top:2px;">
												<span class="bb-readtime-pill">⏱ <?php echo esc_html( bb_reading_time() ); ?></span>
											</p>
										</article>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>

						<?php bb_slider_arrows( count( $bb_b1r_chunks ) ); ?>
					</div>
					<?php wp_reset_postdata(); ?>
				<?php endif; ?>
			</div>

		</div>
	</section>
	<?php
endif;
?>

<?php
/* =====================================================================
 * BLOCK 2 — two cards + list, and a single-column feature
 * ================================================================== */
$bb_b2l_id   = bb_block_cat( 'bb_cat_block2_left', 'science-of-life', 2 );
$bb_b2r_id   = bb_block_cat( 'bb_cat_block2_right', 'physics', 3 );
$bb_b2l_name = bb_block_cat_name( $bb_b2l_id, __( 'জীবনের বিজ্ঞান', 'bichitro-biggan' ) );
$bb_b2r_name = bb_block_cat_name( $bb_b2r_id, __( 'পদার্থ বিজ্ঞান', 'bichitro-biggan' ) );
$bb_b2l_col  = $bb_b2l_id ? bb_term_color( $bb_b2l_id ) : '#2e7d32';
$bb_b2r_col  = $bb_b2r_id ? bb_term_color( $bb_b2r_id ) : '#1a1a1a';

$bb_b2l = bb_query( $bb_b2l_id, 6 * $bb_slides );
$bb_b2r = bb_query( $bb_b2r_id, 3 );

$bb_b2l_chunks = array_chunk( $bb_b2l->posts, 6 );

if ( $bb_b2l->have_posts() || $bb_b2r->have_posts() ) :
	?>
	<section class="bb-container bb-section bb-section--bordered">
		<div class="bb-cols">

			<div class="bb-col-main">
				<?php if ( ! empty( $bb_b2l_chunks ) ) : ?>
					<?php bb_section_heading( $bb_b2l_name, $bb_b2l_col, false, $bb_b2l_id ? get_category_link( $bb_b2l_id ) : '' ); ?>

					<div data-bb-slider>
						<?php foreach ( $bb_b2l_chunks as $bb_i => $bb_chunk ) : ?>
							<div class="bb-slider__slide"<?php echo $bb_i ? ' hidden' : ''; ?>>
								<div class="bb-grid bb-grid--2 bb-grid--gap-sm" style="margin-bottom:4px;">
									<?php
									foreach ( array_slice( $bb_chunk, 0, 2 ) as $bb_p ) {
										bb_setup_post( $bb_p );
										bb_card( array(
											'excerpt'      => true,
											'excerpt_len'  => 18,
											'height_class' => 'bb-ratio-44',
										) );
									}
									?>
								</div>

								<div class="bb-grid bb-grid--2 bb-grid--gap-sm" style="row-gap:0;">
									<?php
									foreach ( array_slice( $bb_chunk, 2 ) as $bb_p ) {
										bb_setup_post( $bb_p );
										echo '<div style="border-top:1px solid #f3f4f6;">';
										bb_list_item();
										echo '</div>';
									}
									?>
								</div>
							</div>
						<?php endforeach; ?>

						<?php bb_slider_arrows( count( $bb_b2l_chunks ) ); ?>
					</div>
					<?php wp_reset_postdata(); ?>
				<?php endif; ?>
			</div>

			<div class="bb-col-side">
				<?php if ( $bb_b2r->have_posts() ) : ?>
					<?php bb_section_heading( $bb_b2r_name, $bb_b2r_col, false, $bb_b2r_id ? get_category_link( $bb_b2r_id ) : '' ); ?>

					<?php
					$bb_b2r->the_post();
					echo '<div style="margin-bottom:4px;">';
					bb_card( array(
						'image_size'   => 'bb-card',
						'height_class' => 'bb-ratio-52',
						'title_class'  => 'bb-card__title--lg',
						'excerpt'      => true,
						'excerpt_len'  => 18,
					) );
					echo '</div>';
					?>

					<?php
					while ( $bb_b2r->have_posts() ) :
						$bb_b2r->the_post();
						echo '<div style="border-top:1px solid #f3f4f6;">';
						bb_list_item();
						echo '</div>';
					endwhile;
					wp_reset_postdata();
					?>
				<?php endif; ?>
			</div>

		</div>
	</section>
	<?php
endif;
?>

<?php
/* =====================================================================
 * BLOCK 3 — wide rows + two stacked features
 * ================================================================== */
$bb_b3l_id   = bb_block_cat( 'bb_cat_block3_left', 'miscellaneous-science', 4 );
$bb_b3r_id   = bb_block_cat( 'bb_cat_block3_right', 'latest-science', 5 );
$bb_b3l_name = bb_block_cat_name( $bb_b3l_id, __( 'বিবিধ বিজ্ঞান', 'bichitro-biggan' ) );
$bb_b3r_name = bb_block_cat_name( $bb_b3r_id, __( 'সাম্প্রতিক বিজ্ঞান', 'bichitro-biggan' ) );
$bb_b3l_col  = $bb_b3l_id ? bb_term_color( $bb_b3l_id ) : '#1a1a1a';
$bb_b3r_col  = $bb_b3r_id ? bb_term_color( $bb_b3r_id ) : '#1a1a1a';

$bb_b3l = bb_query( $bb_b3l_id, 5 );
$bb_b3r = bb_query( $bb_b3r_id, 2 );

if ( $bb_b3l->have_posts() || $bb_b3r->have_posts() ) :
	?>
	<section class="bb-container bb-section bb-section--bordered">
		<div class="bb-cols bb-cols--stretch">

			<div class="bb-col-main">
				<?php if ( $bb_b3l->have_posts() ) : ?>
					<?php bb_section_heading( $bb_b3l_name, $bb_b3l_col, false, $bb_b3l_id ? get_category_link( $bb_b3l_id ) : '' ); ?>
					<div class="bb-divide-y">
						<?php
						while ( $bb_b3l->have_posts() ) :
							$bb_b3l->the_post();
							bb_wide_row();
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				<?php endif; ?>
			</div>

			<div class="bb-col-side">
				<?php if ( $bb_b3r->have_posts() ) : ?>
					<?php bb_section_heading( $bb_b3r_name, $bb_b3r_col, false, $bb_b3r_id ? get_category_link( $bb_b3r_id ) : '' ); ?>
					<?php
					$bb_first = true;
					while ( $bb_b3r->have_posts() ) :
						$bb_b3r->the_post();
						$bb_wrap_style = $bb_first ? 'margin-bottom:12px;' : 'margin-top:16px;border-top:1px solid #f3f4f6;padding-top:16px;';
						echo '<div style="' . esc_attr( $bb_wrap_style ) . '">';
						bb_card( array(
							'image_size'   => 'bb-card',
							'height_class' => 'bb-ratio-52',
							'title_class'  => 'bb-card__title--lg',
							'excerpt'      => true,
							'excerpt_len'  => 20,
						) );
						echo '</div>';
						$bb_first = false;
					endwhile;
					wp_reset_postdata();
					?>
				<?php endif; ?>
			</div>

		</div>
	</section>
	<?php
endif;
?>

<?php
/* =====================================================================
 * BLOCK 4 — two tall overlay cards + "আরও পড়ুন"
 * ================================================================== */
$bb_b4a_id   = bb_block_cat( 'bb_cat_block4_a', 'space-science', 6 );
$bb_b4b_id   = bb_block_cat( 'bb_cat_block4_b', 'stories-of-scientists', 7 );
$bb_b4a_name = bb_block_cat_name( $bb_b4a_id, __( 'মহাকাশ বিজ্ঞান', 'bichitro-biggan' ) );
$bb_b4b_name = bb_block_cat_name( $bb_b4b_id, __( 'বিজ্ঞানীদের কথা', 'bichitro-biggan' ) );
$bb_b4a_col  = $bb_b4a_id ? bb_term_color( $bb_b4a_id ) : '#1565c0';
$bb_b4b_col  = $bb_b4b_id ? bb_term_color( $bb_b4b_id ) : '#6a0dad';

$bb_b4a  = bb_query( $bb_b4a_id, 1 );
$bb_b4b  = bb_query( $bb_b4b_id, 1 );
$bb_more = bb_query( 0, 3 );
?>
<section class="bb-container bb-section bb-section--bordered">
	<div class="bb-grid bb-grid--3 bb-grid--gap-lg">

		<div>
			<?php if ( $bb_b4a->have_posts() ) : ?>
				<?php bb_section_heading( $bb_b4a_name, $bb_b4a_col, false, $bb_b4a_id ? get_category_link( $bb_b4a_id ) : '' ); ?>
				<?php
				$bb_b4a->the_post();
				bb_overlay_panel( array(
					'class'  => 'bb-tallcard',
					'byline' => true,
				) );
				wp_reset_postdata();
				?>
			<?php endif; ?>
		</div>

		<div>
			<?php if ( $bb_b4b->have_posts() ) : ?>
				<?php bb_section_heading( $bb_b4b_name, $bb_b4b_col, false, $bb_b4b_id ? get_category_link( $bb_b4b_id ) : '' ); ?>
				<?php
				$bb_b4b->the_post();
				bb_overlay_panel( array(
					'class'  => 'bb-tallcard',
					'byline' => true,
				) );
				wp_reset_postdata();
				?>
			<?php endif; ?>
		</div>

		<div class="bb-span-2">
			<?php bb_section_heading( __( 'আরও পড়ুন', 'bichitro-biggan' ), '#1a1a1a' ); ?>
			<div class="bb-divide-y">
				<?php
				while ( $bb_more->have_posts() ) :
					$bb_more->the_post();
					bb_title_only();
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>

	</div>
</section>

<?php
/* =====================================================================
 * BLOCK 5 — remaining categories, same tall-card pattern as block 4
 * so every category on the site appears somewhere on the homepage.
 * ================================================================== */
$bb_b5 = array(
	array( 'bb_cat_block5_a', 'wonders-of-the-universe', 8,  __( 'মহাবিশ্বের মহাবিস্ময়', 'bichitro-biggan' ), '#6a0dad' ),
	array( 'bb_cat_block5_b', 'nature-and-environment',  9,  __( 'প্রকৃতি ও পরিবেশ', 'bichitro-biggan' ), '#00796b' ),
	array( 'bb_cat_block5_c', 'science-and-technology',  10, __( 'বিজ্ঞান ও প্রযুক্তি', 'bichitro-biggan' ), '#d84315' ),
);

$bb_b5_cols = array();
foreach ( $bb_b5 as $bb_def ) {
	list( $bb_mod, $bb_slug, $bb_idx, $bb_name, $bb_fallback_col ) = $bb_def;

	$bb_id = bb_block_cat( $bb_mod, $bb_slug, $bb_idx );
	$bb_q  = bb_query( $bb_id, 1 );

	if ( $bb_q->have_posts() ) {
		$bb_b5_cols[] = array(
			'id'    => $bb_id,
			'name'  => bb_block_cat_name( $bb_id, $bb_name ),
			'color' => $bb_id ? bb_term_color( $bb_id ) : $bb_fallback_col,
			'query' => $bb_q,
		);
	}
}

if ( ! empty( $bb_b5_cols ) ) :
	?>
	<section class="bb-container bb-section bb-section--bordered">
		<div class="bb-grid bb-grid--3 bb-grid--gap-lg">
			<?php foreach ( $bb_b5_cols as $bb_col ) : ?>
				<div>
					<?php bb_section_heading( $bb_col['name'], $bb_col['color'], false, $bb_col['id'] ? get_category_link( $bb_col['id'] ) : '' ); ?>
					<?php
					$bb_col['query']->the_post();
					bb_overlay_panel( array(
						'class'  => 'bb-tallcard',
						'byline' => true,
					) );
					wp_reset_postdata();
					?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
endif;
?>

<?php
/* =====================================================================
 * DARK CARD STRIP
 * ================================================================== */
$bb_dark = bb_query( 0, 3 );
if ( $bb_dark->have_posts() ) :
	?>
	<section class="bb-container" style="padding-bottom:40px;">
		<!-- Same gap as the two blocks above so all three-column rows share
		     one grid and their edges line up. -->
		<div class="bb-grid bb-grid--3 bb-grid--gap-lg">
			<?php
			while ( $bb_dark->have_posts() ) :
				$bb_dark->the_post();
				bb_dark_card();
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</section>
	<?php
endif;
?>

<?php
/* =====================================================================
 * সকল লেখা + SIDEBAR
 * ================================================================== */
/* Use the main query here. A second query with its own page size would report
   a different number of pages than WordPress is willing to serve, and every
   page past that limit would 404. */
$bb_use_main = is_home();

if ( $bb_use_main ) {
	$bb_all = $GLOBALS['wp_query'];
} else {
	$bb_all = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => bb_all_posts_count(),
		'paged'               => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
		'ignore_sticky_posts' => 1,
	) );
}
?>
<section class="bb-container bb-section bb-section--bordered">
	<div class="bb-cols">

		<div class="bb-col-main">
			<?php bb_section_heading( __( 'সকল লেখা', 'bichitro-biggan' ), '#1a1a1a' ); ?>

			<div data-bb-list="all">
			<?php if ( $bb_all->have_posts() ) : ?>
				<div class="bb-grid bb-grid--2">
					<?php
					while ( $bb_all->have_posts() ) :
						$bb_all->the_post();
						bb_card( array(
							'height_class' => 'bb-ratio-44',
							'title_class'  => 'bb-card__title--sm',
						) );
					endwhile;
					?>
				</div>

				<?php bb_pagination( $bb_all ); ?>
			<?php else : ?>
				<p class="bb-card__excerpt"><?php esc_html_e( 'এখনো কোনো লেখা প্রকাশ করা হয়নি।', 'bichitro-biggan' ); ?></p>
			<?php endif; ?>
			</div><!-- [data-bb-list] -->
			<?php wp_reset_postdata(); ?>
		</div>

		<?php get_sidebar(); ?>

	</div>
</section>

<?php
get_footer();
