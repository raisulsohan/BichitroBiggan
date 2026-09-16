<?php
/**
 * Author archive.
 *
 * Who wrote this, what they know, and everything else they have written.
 * Readers of a science site weigh an explanation partly by who is giving it,
 * and Google reads the same page for the same reason.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bb_author    = get_queried_object();
$bb_author_id = $bb_author instanceof WP_User ? (int) $bb_author->ID : 0;
$bb_bio       = $bb_author_id ? get_the_author_meta( 'description', $bb_author_id ) : '';
$bb_site      = $bb_author_id ? get_the_author_meta( 'url', $bb_author_id ) : '';
$bb_count     = $bb_author_id ? (int) count_user_posts( $bb_author_id, 'post' ) : 0;
?>

<div class="bb-archive">
	<div class="bb-archive__inner">

		<?php bb_breadcrumb(); ?>

		<header class="bb-authorpage">
			<?php if ( $bb_author_id ) : ?>
				<div class="bb-authorpage__avatar"><?php echo get_avatar( $bb_author_id, 112 ); ?></div>
			<?php endif; ?>

			<div class="bb-authorpage__body">
				<h1 class="bb-authorpage__name"><?php echo esc_html( get_the_author_meta( 'display_name', $bb_author_id ) ); ?></h1>

				<p class="bb-authorpage__count">
					<?php
					printf(
						/* translators: %s: number of posts, in Bengali digits. */
						esc_html__( '%s টি লেখা', 'bichitro-biggan' ),
						esc_html( bb_bangla_number( $bb_count ) )
					);
					?>
				</p>

				<?php if ( $bb_bio ) : ?>
					<p class="bb-authorpage__bio"><?php echo esc_html( $bb_bio ); ?></p>
				<?php endif; ?>

				<?php if ( $bb_site ) : ?>
					<p class="bb-authorpage__site">
						<a href="<?php echo esc_url( $bb_site ); ?>" target="_blank" rel="noopener nofollow">
							<?php echo esc_html( preg_replace( '#^https?://(www\.)?#i', '', $bb_site ) ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</header>

		<div data-bb-list="archive">
		<?php if ( have_posts() ) : ?>

			<div class="bb-cols">
				<div class="bb-col-main">
					<div class="bb-grid bb-grid--2" style="margin-bottom:32px;">
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
				<?php get_search_form(); ?>
			</div>

		<?php endif; ?>

	</div>
</div>

<?php
get_footer();
