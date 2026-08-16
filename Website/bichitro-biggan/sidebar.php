<?php
/**
 * Sidebar: archive accordion and widget area.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<aside class="bb-col-side bb-col-side--sidebar" role="complementary">

	<?php
	$bb_tree = bb_get_archive_tree();
	if ( ! empty( $bb_tree ) ) :
		?>
		<div class="bb-sidebar-block bb-accordion" data-bb-accordion>
			<?php foreach ( $bb_tree as $bb_year => $bb_months ) : ?>
				<div class="bb-accordion__item">
					<button type="button" class="bb-accordion__head" aria-expanded="false">
						<span><?php echo esc_html( $bb_year ); ?></span>
						<span class="bb-accordion__caret">▼</span>
					</button>
					<div class="bb-accordion__panel">
						<?php foreach ( $bb_months as $bb_month ) : ?>
							<a class="bb-accordion__link" href="<?php echo esc_url( $bb_month['url'] ); ?>">
								<?php echo esc_html( $bb_month['label'] . ' ' . $bb_year ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	endif;
	?>

	<?php if ( is_active_sidebar( 'bb-sidebar' ) ) : ?>
		<?php dynamic_sidebar( 'bb-sidebar' ); ?>
	<?php endif; ?>

</aside>
