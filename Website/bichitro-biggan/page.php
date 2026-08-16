<?php
/**
 * Static page.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'bb-page' ); ?>>

		<?php bb_breadcrumb(); ?>

		<h1 class="bb-page__title"><?php the_title(); ?></h1>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="bb-single__hero">
				<?php the_post_thumbnail( 'bb-hero' ); ?>
			</figure>
		<?php endif; ?>

		<div class="bb-content">
			<?php the_content(); ?>
			<?php
			wp_link_pages( array(
				'before' => '<div class="bb-pagination__list" style="margin-top:16px;">',
				'after'  => '</div>',
			) );
			?>
		</div>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>

	</article>
	<?php
endwhile;

get_footer();
