<?php
/**
 * 404.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="bb-container">
	<div class="bb-empty">
		<p class="bb-empty__code">404</p>
		<p class="bb-empty__title"><?php esc_html_e( 'পাতাটি খুঁজে পাওয়া যায়নি', 'bichitro-biggan' ); ?></p>
		<p class="bb-empty__text"><?php esc_html_e( 'আপনি যে ঠিকানায় এসেছেন সেটি সরানো হয়েছে বা কখনো ছিল না।', 'bichitro-biggan' ); ?></p>
		<?php get_search_form(); ?>
	</div>

	<div class="bb-section">
		<?php bb_section_heading( __( 'সাম্প্রতিক লেখা', 'bichitro-biggan' ), '#1a1a1a' ); ?>
		<div class="bb-grid bb-grid--3">
			<?php
			$bb_recent = bb_query( 0, 3 );
			while ( $bb_recent->have_posts() ) :
				$bb_recent->the_post();
				bb_card( array(
					'height_class' => 'bb-ratio-40',
					'title_class'  => 'bb-card__title--sm',
				) );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</div>

<?php
get_footer();
