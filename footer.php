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
					<h4 class="bb-footer__heading" id="bb-editor-picks-title"><?php echo esc_html( get_theme_mod( 'bb_editor_picks_title', 'EDITOR PICKS' ) ); ?></h4>
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
					<div class="bb-footer__heading-wrap">
						<h4 class="bb-footer__heading" id="bb-popular-title"><?php echo esc_html( get_theme_mod( 'bb_popular_title', 'POPULAR POSTS' ) ); ?></h4>
						<div class="bb-popular-filter">
							<select class="bb-popular-select" id="bb-popular-range" aria-label="<?php esc_attr_e( 'সময় নির্বাচন করুন', 'bichitro-biggan' ); ?>" data-count="<?php echo esc_attr( get_theme_mod( 'bb_popular_count', 3 ) ); ?>">
								<option value="week" selected><?php esc_html_e( 'এই সপ্তাহে', 'bichitro-biggan' ); ?></option>
								<option value="month"><?php esc_html_e( 'এই মাসে', 'bichitro-biggan' ); ?></option>
								<option value="year"><?php esc_html_e( 'এই বছরে', 'bichitro-biggan' ); ?></option>
								<option value="all"><?php esc_html_e( 'সব সময়', 'bichitro-biggan' ); ?></option>
							</select>
						</div>
					</div>
					<div class="bb-footer__list" id="bb-popular-list">
						<?php
						$bb_popular = bb_popular_query( get_theme_mod( 'bb_popular_count', 3 ), 'week' );

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
					<h4 class="bb-footer__heading" id="bb-popular-cat-title"><?php echo esc_html( get_theme_mod( 'bb_popular_cat_title', 'POPULAR CATEGORY' ) ); ?></h4>
					<div class="bb-catcount">
						<?php
						$bb_cat_limit = max( 3, min( 10, (int) get_theme_mod( 'bb_popular_cat_count', 5 ) ) );
						$bb_cats = get_categories( array(
							'orderby'    => 'count',
							'order'      => 'DESC',
							'number'     => $bb_cat_limit,
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
						<span class="bb-footer__about-label"><?php echo esc_html( get_theme_mod( 'bb_about_title', 'ABOUT US' ) ); ?></span>
						<span class="bb-footer__about-rule"></span>
					</div>
					<p class="bb-footer__about-text"><?php echo wp_kses_post( get_theme_mod( 'bb_about_text', 'BichitroBiggan is your source for science news, discoveries, and insights. We bring you the latest updates, research breakthroughs, and engaging stories from the world of science and technology.' ) ); ?></p>
				</div>

				<div class="bb-footer__contact">
					<div class="bb-footer__contact-item">
						<span class="bb-footer__contact-title"><?php echo esc_html( get_theme_mod( 'bb_contact_title', 'CONTACT US' ) ); ?></span>
						<button type="button" class="bb-footer__contact-mail bb-copy-email" title="<?php esc_attr_e( 'ইমেইল কপি করুন', 'bichitro-biggan' ); ?>">✉</button>
					</div>
					<?php
					$bb_ft_yt_url  = get_theme_mod( 'bb_footer_youtube_url', get_theme_mod( 'bb_youtube_url', 'https://www.youtube.com/@bigganbichitro' ) );
					$bb_ft_yt_text = get_theme_mod( 'bb_footer_youtube_text', 'সাবস্ক্রাইব করুন' );
					if ( $bb_ft_yt_url ) :
						?>
						<div class="bb-footer__subscribe">
							<span class="bb-footer__subscribe-title"><?php echo esc_html( get_theme_mod( 'bb_subscribe_title', 'SUBSCRIBE US' ) ); ?></span>
							<a class="bb-footer__yt-link" href="<?php echo esc_url( $bb_ft_yt_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $bb_ft_yt_text ); ?>">
								<svg class="bb-footer__yt-icon" viewBox="0 0 28 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path d="M27.42 3.13A3.51 3.51 0 0 0 24.96.65C22.76 0 14 0 14 0S5.24 0 3.04.65A3.51 3.51 0 0 0 .58 3.13 36.82 36.82 0 0 0 0 10a36.82 36.82 0 0 0 .58 6.87A3.51 3.51 0 0 0 3.04 19.35C5.24 20 14 20 14 20s8.76 0 10.96-.65a3.51 3.51 0 0 0 2.46-2.48A36.82 36.82 0 0 0 28 10a36.82 36.82 0 0 0-.58-6.87Z" fill="#FF0000"/>
									<path d="m11.2 14.29 7.33-4.29-7.33-4.29v8.58Z" fill="#fff"/>
								</svg>
								<?php if ( ! empty( $bb_ft_yt_text ) ) : ?>
									<span class="bb-footer__yt-text"><?php echo esc_html( $bb_ft_yt_text ); ?></span>
								<?php endif; ?>
							</a>
						</div>
					<?php endif; ?>
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
					<?php bb_footer_credit( 'bb_footer_developer', 'Raisul Sohan', 'bb_footer_developer_url' ); ?>
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
				<a href="#" class="bb-resume-bar__title" id="bb-resume-title-link"></a>
			</div>
			<a href="#" class="bb-resume-bar__btn" id="bb-resume-btn"><?php esc_html_e( 'পড়া চালিয়ে যান', 'bichitro-biggan' ); ?> →</a>
			<button type="button" class="bb-resume-bar__close" id="bb-resume-close" aria-label="<?php esc_attr_e( 'বন্ধ করুন', 'bichitro-biggan' ); ?>">✕</button>
		</div>
	</div>

	<button type="button" class="bb-backtop" id="bb-backtop" title="<?php esc_attr_e( 'উপরে যান', 'bichitro-biggan' ); ?>">↑</button>

</div><!-- #bb-page -->

<?php wp_footer(); ?>
</body>
</html>
