<?php
/**
 * Footer.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</main><!-- #bb-main -->

	<footer class="bb-footer">

		<div class="bb-footer__top">
			<div class="bb-grid bb-grid--3 bb-grid--gap-8">

				<!-- EDITOR PICKS -->
				<div>
					<h4 class="bb-footer__heading"><?php esc_html_e( 'EDITOR PICKS', 'bichitro-biggan' ); ?></h4>
					<div class="bb-footer__list">
						<?php
						$bb_picks = bb_editor_picks_query();

						while ( $bb_picks->have_posts() ) :
							$bb_picks->the_post();
							bb_footer_item();
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>

				<!-- POPULAR POSTS -->
				<div>
					<h4 class="bb-footer__heading"><?php esc_html_e( 'POPULAR POSTS', 'bichitro-biggan' ); ?></h4>
					<div class="bb-footer__list">
						<?php
						$bb_popular = bb_popular_query( get_theme_mod( 'bb_popular_count', 3 ) );

						while ( $bb_popular->have_posts() ) :
							$bb_popular->the_post();
							bb_footer_item();
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>

				<!-- POPULAR CATEGORY -->
				<div class="bb-span-2">
					<h4 class="bb-footer__heading"><?php esc_html_e( 'POPULAR CATEGORY', 'bichitro-biggan' ); ?></h4>
					<div class="bb-catcount">
						<?php
						$bb_cats = get_categories( array(
							'orderby'    => 'count',
							'order'      => 'DESC',
							'number'     => 5,
							'hide_empty' => true,
						) );

						foreach ( $bb_cats as $bb_cat ) :
							?>
							<a class="bb-catcount__row" href="<?php echo esc_url( get_category_link( $bb_cat ) ); ?>">
								<span class="bb-catcount__name"><?php echo esc_html( $bb_cat->name ); ?></span>
								<span class="bb-catcount__num"><?php echo esc_html( $bb_cat->count ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>

			</div>
		</div>

		<div class="bb-footer__mid">
			<div class="bb-footer__mid-inner">

				<div>
					<?php if ( has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						<a class="bb-logo bb-logo--md" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
							<span class="bb-logo__text"><?php bloginfo( 'name' ); ?></span>
						</a>
					<?php endif; ?>
				</div>

				<div>
					<div class="bb-footer__about-head">
						<span class="bb-footer__about-label"><?php esc_html_e( 'ABOUT US', 'bichitro-biggan' ); ?></span>
						<span class="bb-footer__about-rule"></span>
					</div>
					<p class="bb-footer__about-text"><?php echo wp_kses_post( get_theme_mod( 'bb_about_text', 'BichitroBiggan is your source for science news, discoveries, and insights. We bring you the latest updates, research breakthroughs, and engaging stories from the world of science and technology.' ) ); ?></p>
				</div>

				<div class="bb-footer__contact">
					<h4 class="bb-footer__heading"><?php esc_html_e( 'Contact US', 'bichitro-biggan' ); ?></h4>
					<button type="button" class="bb-footer__contact-mail bb-copy-email" title="<?php esc_attr_e( 'ইমেইল কপি করুন', 'bichitro-biggan' ); ?>">✉</button>
				</div>

			</div>
		</div>

		<div class="bb-footer__bottom">
			<div class="bb-footer__bottom-inner">
				<span>
					<?php esc_html_e( 'Author and Editor', 'bichitro-biggan' ); ?>
					<?php bb_footer_credit( 'bb_footer_author', 'Tanvir Hossain', 'bb_footer_author_url' ); ?>
				</span>
				<span>
					<?php
					echo esc_html( get_theme_mod( 'bb_footer_copyright', '©BichitroBiggan' ) . ' ' . wp_date( 'Y' ) );
					?>
				</span>
				<span>
					<?php esc_html_e( 'Developed by', 'bichitro-biggan' ); ?>
					<?php bb_footer_credit( 'bb_footer_developer', 'Raisul Islam', 'bb_footer_developer_url' ); ?>
				</span>
			</div>
		</div>
	</footer>

	<?php if ( get_theme_mod( 'bb_live_search', true ) ) : ?>
		<div class="bb-search-modal" id="bb-search-modal" hidden>
			<div class="bb-search-modal__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'খুঁজুন', 'bichitro-biggan' ); ?>">
				<div class="bb-search-modal__bar">
					<span class="bb-search-modal__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
						</svg>
					</span>
					<input type="search" class="bb-search-modal__input" id="bb-search-input"
						placeholder="<?php esc_attr_e( 'খুঁজুন...', 'bichitro-biggan' ); ?>"
						autocomplete="off" />
					<button type="button" class="bb-search-modal__btn" data-bb-search-clear hidden
						aria-label="<?php esc_attr_e( 'মুছে ফেলুন', 'bichitro-biggan' ); ?>">✕</button>
					<span class="bb-search-modal__sep"></span>
					<button type="button" class="bb-search-modal__btn bb-search-modal__btn--close" data-bb-search-close
						aria-label="<?php esc_attr_e( 'বন্ধ করুন', 'bichitro-biggan' ); ?>">✕</button>
				</div>
				<div class="bb-search-modal__results" id="bb-search-results"></div>
				<div class="bb-search-modal__foot" id="bb-search-count" hidden></div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( get_theme_mod( 'bb_enable_modal', true ) ) : ?>
		<div class="bb-modal" id="bb-modal" hidden>
			<div class="bb-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'আর্টিকেল', 'bichitro-biggan' ); ?>">
				<div class="bb-reading-progress" id="bb-reading-progress"><div class="bb-reading-progress__bar"></div></div>
				<button type="button" class="bb-modal__close" data-bb-modal-close aria-label="<?php esc_attr_e( 'বন্ধ করুন', 'bichitro-biggan' ); ?>">✕</button>
				<div class="bb-modal__body"></div>
				<button type="button" class="bb-modal__top" hidden title="<?php esc_attr_e( 'উপরে যান', 'bichitro-biggan' ); ?>">↑</button>
			</div>
		</div>
	<?php endif; ?>

	<div class="bb-resume-bar" id="bb-resume-bar" hidden>
		<div class="bb-resume-bar__inner">
			<span class="bb-resume-bar__icon">📖</span>
			<div class="bb-resume-bar__text">
				<span class="bb-resume-bar__label"><?php esc_html_e( 'আপনি পড়ছিলেন:', 'bichitro-biggan' ); ?></span>
				<strong class="bb-resume-bar__title"></strong>
			</div>
			<a href="#" class="bb-resume-bar__btn" id="bb-resume-btn"><?php esc_html_e( 'পড়া চালিয়ে যান', 'bichitro-biggan' ); ?> →</a>
			<button type="button" class="bb-resume-bar__close" id="bb-resume-close" aria-label="<?php esc_attr_e( 'বন্ধ করুন', 'bichitro-biggan' ); ?>">✕</button>
		</div>
	</div>

	<button type="button" class="bb-backtop" id="bb-backtop" title="<?php esc_attr_e( 'উপরে যান', 'bichitro-biggan' ); ?>">↑</button>

	<div class="bb-toast" id="bb-toast" role="status" aria-live="polite">
		<span>✓</span> <span class="bb-toast__text"><?php esc_html_e( 'ইমেইল কপি হয়েছে', 'bichitro-biggan' ); ?></span>
	</div>

</div><!-- #bb-page -->

<?php wp_footer(); ?>
</body>
</html>
