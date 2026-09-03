<?php
/**
 * Header: top bar, masthead, navigation, ticker, search and year tabs.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<script>
		// Remove tracking parameters instantly before page renders
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			var params = ['fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
			var modified = false;
			params.forEach(function(param) {
				if (url.searchParams.has(param)) {
					url.searchParams.delete(param);
					modified = true;
				}
			});
			if (modified) {
				window.history.replaceState({}, document.title, url.toString());
			}
		}
	</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="bb-skip-link" href="#bb-main"><?php esc_html_e( 'মূল কন্টেন্টে যান', 'bichitro-biggan' ); ?></a>

<div id="bb-page">

	<!-- Top bar -->
	<div class="bb-topbar">
		<div class="bb-topbar__inner">
			<span class="bb-topbar__date"><?php echo esc_html( bb_bangla_today() ); ?></span>
			<div class="bb-topbar__actions">
				<button type="button" class="bb-topbar__btn bb-topbar__btn--bookmarks" data-bb-toggle="bookmarks" title="<?php esc_attr_e( 'সংরক্ষিত লেখাগুলো দেখুন', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'সংরক্ষিত লেখাগুলো দেখুন', 'bichitro-biggan' ); ?>">
					<span aria-hidden="true">🔖</span>
					<span class="bb-topbar__btn-text"><?php esc_html_e( 'পরে পড়ুন', 'bichitro-biggan' ); ?></span>
					<span class="bb-count-pill" data-bb-count="bookmarks" style="display:none;">0</span>
				</button>
				<button type="button" class="bb-topbar__mail bb-copy-email" title="<?php esc_attr_e( 'ইমেইল কপি করুন', 'bichitro-biggan' ); ?>">✉</button>
			</div>
		</div>
	</div>

	<!-- Masthead -->
	<header class="bb-masthead">
		<div class="bb-masthead__inner">
			<div class="bb-masthead__logo">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="bb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<span class="bb-logo__text"><?php echo get_bloginfo( 'name' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
			<?php
			$bb_tagline = bb_get_tagline();
			if ( $bb_tagline ) :
				?>
				<p class="bb-masthead__tagline"><?php echo wp_kses_post( $bb_tagline ); ?></p>
			<?php endif; ?>
			<?php
			$bb_show_yt        = get_theme_mod( 'bb_show_youtube', true );
			$bb_show_yt_mobile = get_theme_mod( 'bb_show_youtube_mobile', false );
			if ( $bb_show_yt || $bb_show_yt_mobile ) :
				$bb_yt_url   = get_theme_mod( 'bb_youtube_url', 'https://www.youtube.com/@bigganbichitro' );
				$bb_yt_text  = get_theme_mod( 'bb_youtube_text', 'সাবস্ক্রাইব করুন' );
				$bb_soc_cls  = 'bb-masthead__social';
				if ( ! $bb_show_yt ) {
					$bb_soc_cls .= ' bb-hide-desktop';
				}
				if ( ! $bb_show_yt_mobile ) {
					$bb_soc_cls .= ' bb-hide-mobile';
				}
				?>
				<div class="<?php echo esc_attr( $bb_soc_cls ); ?>">
					<a class="bb-masthead__yt" href="<?php echo esc_url( $bb_yt_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $bb_yt_text ? $bb_yt_text : __( 'ইউটিউব চ্যানেল', 'bichitro-biggan' ) ); ?>">
						<svg class="bb-masthead__yt-icon" viewBox="0 0 28 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M27.42 3.13A3.51 3.51 0 0 0 24.96.65C22.76 0 14 0 14 0S5.24 0 3.04.65A3.51 3.51 0 0 0 .58 3.13 36.82 36.82 0 0 0 0 10a36.82 36.82 0 0 0 .58 6.87A3.51 3.51 0 0 0 3.04 19.35C5.24 20 14 20 14 20s8.76 0 10.96-.65a3.51 3.51 0 0 0 2.46-2.48A36.82 36.82 0 0 0 28 10a36.82 36.82 0 0 0-.58-6.87Z" fill="#FF0000"/>
							<path d="m11.2 14.29 7.33-4.29-7.33-4.29v8.58Z" fill="#fff"/>
						</svg>
						<?php if ( ! empty( $bb_yt_text ) ) : ?>
							<span class="bb-masthead__yt-text"><?php echo esc_html( $bb_yt_text ); ?></span>
						<?php endif; ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</header>

	<!-- Navigation -->
	<nav class="bb-nav" id="bb-nav" aria-label="<?php esc_attr_e( 'প্রধান মেন্যু', 'bichitro-biggan' ); ?>">

		<div class="bb-nav__stickybar">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="bb-logo bb-logo--sm" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="bb-logo__text"><?php bloginfo( 'name' ); ?></span>
				</a>
			<?php endif; ?>
			<div class="bb-nav__sticky-actions">
				<button type="button" class="bb-nav-action-btn bb-nav-action-btn--bookmarks" data-bb-toggle="bookmarks" title="<?php esc_attr_e( 'সংরক্ষিত লেখাগুলো দেখুন', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'সংরক্ষিত লেখাগুলো দেখুন', 'bichitro-biggan' ); ?>">
					<span aria-hidden="true">🔖</span>
					<span class="bb-nav-badge" data-bb-count="bookmarks" style="display:none;">0</span>
				</button>
				<?php
				$bb_sticky_yt_url = get_theme_mod( 'bb_youtube_url', 'https://www.youtube.com/@bigganbichitro' );
				if ( $bb_sticky_yt_url ) :
					?>
					<a class="bb-nav-action-btn bb-nav-action-btn--yt" href="<?php echo esc_url( $bb_sticky_yt_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'ইউটিউব চ্যানেল', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'ইউটিউব চ্যানেল', 'bichitro-biggan' ); ?>">
						<svg viewBox="0 0 28 20" width="18" height="13" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M27.42 3.13A3.51 3.51 0 0 0 24.96.65C22.76 0 14 0 14 0S5.24 0 3.04.65A3.51 3.51 0 0 0 .58 3.13 36.82 36.82 0 0 0 0 10a36.82 36.82 0 0 0 .58 6.87A3.51 3.51 0 0 0 3.04 19.35C5.24 20 14 20 14 20s8.76 0 10.96-.65a3.51 3.51 0 0 0 2.46-2.48A36.82 36.82 0 0 0 28 10a36.82 36.82 0 0 0-.58-6.87Z" fill="#FF0000"/>
							<path d="m11.2 14.29 7.33-4.29-7.33-4.29v8.58Z" fill="#fff"/>
						</svg>
					</a>
				<?php endif; ?>
				<button type="button" class="bb-nav-action-btn bb-nav-action-btn--search" data-bb-toggle="search" title="<?php esc_attr_e( 'খুঁজুন', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'খুঁজুন', 'bichitro-biggan' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
					</svg>
				</button>
				<button type="button" class="bb-hamburger bb-hide-desktop" data-bb-toggle="menu" aria-expanded="false" aria-controls="bb-mobile-menu" aria-label="<?php esc_attr_e( 'মেন্যু', 'bichitro-biggan' ); ?>">☰</button>
			</div>
		</div>

		<div class="bb-container-wide">

			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'bb-nav__list',
					'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
					'walker'         => new BB_Nav_Walker(),
					'depth'          => 2,
				) );
			} else {
				bb_nav_fallback();
			}
			?>

			<div class="bb-nav__mobilebar">
				<span class="bb-nav__current"><?php echo esc_html( bb_current_label() ); ?></span>
				<div class="bb-nav__mobile-actions">
					<button type="button" class="bb-nav-action-btn bb-nav-action-btn--bookmarks" data-bb-toggle="bookmarks" title="<?php esc_attr_e( 'সংরক্ষিত লেখাগুলো দেখুন', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'সংরক্ষিত লেখাগুলো দেখুন', 'bichitro-biggan' ); ?>">
						<span aria-hidden="true">🔖</span>
						<span class="bb-nav-badge" data-bb-count="bookmarks" style="display:none;">0</span>
					</button>
					<button type="button" class="bb-hamburger" data-bb-toggle="menu" aria-expanded="false" aria-controls="bb-mobile-menu" aria-label="<?php esc_attr_e( 'মেন্যু', 'bichitro-biggan' ); ?>">☰</button>
				</div>
			</div>

			<div class="bb-mobile-menu" id="bb-mobile-menu">
				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'bb-mobile-menu__list',
						'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
						'walker'         => new BB_Nav_Walker(),
						'depth'          => 2,
					) );
				} else {
					bb_nav_fallback( array( 'bb_mobile' => true ) );
				}
				?>
			</div>

		</div>
	</nav>

	<?php
	/* Ticker — latest posts */
	if ( get_theme_mod( 'bb_show_ticker', true ) ) :
		$bb_ticker = bb_query( 0, get_theme_mod( 'bb_ticker_count', 5 ) );
		if ( $bb_ticker->have_posts() ) :
			?>
			<div class="bb-ticker">
				<div class="bb-ticker__inner">
					<span class="bb-ticker__label"><?php esc_html_e( 'নতুন লেখা', 'bichitro-biggan' ); ?></span>

					<div class="bb-ticker__track">
						<?php
						$bb_i = 0;
						while ( $bb_ticker->have_posts() ) :
							$bb_ticker->the_post();
							?>
							<a class="bb-ticker__title" href="<?php the_permalink(); ?>"<?php bb_article_attr(); ?> data-bb-ticker-item="<?php echo esc_attr( $bb_i ); ?>"<?php echo 0 === $bb_i ? '' : ' style="display:none;"'; ?>>
								<?php the_title(); ?>
							</a>
							<?php
							$bb_i++;
						endwhile;
						?>
					</div>

					<?php if ( $bb_ticker->post_count > 1 ) : ?>
						<div class="bb-ticker__nav">
							<button type="button" class="bb-arrow-btn" data-bb-ticker="prev" aria-label="<?php esc_attr_e( 'আগের লেখা', 'bichitro-biggan' ); ?>">‹</button>
							<button type="button" class="bb-arrow-btn" data-bb-ticker="next" aria-label="<?php esc_attr_e( 'পরের লেখা', 'bichitro-biggan' ); ?>">›</button>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endif;
		wp_reset_postdata();
	endif;
	?>

	<?php if ( get_theme_mod( 'bb_show_search', true ) ) : ?>
		<div class="bb-searchbar">
			<div class="bb-container">
				<?php get_search_form(); ?>
			</div>
		</div>
	<?php endif; ?>

	<?php
	/* Year tabs */
	if ( get_theme_mod( 'bb_show_years', true ) ) :
		$bb_years = bb_get_post_years();
		if ( ! empty( $bb_years ) ) :
			$bb_active_year = is_year() || is_month() || is_day() ? get_the_date( 'Y' ) : '';
			?>
			<div class="bb-years">
				<div class="bb-years__inner">
					<?php foreach ( $bb_years as $bb_year ) : ?>
						<a class="bb-years__link<?php echo ( $bb_year === $bb_active_year ) ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( get_year_link( $bb_year ) ); ?>">
							<?php echo esc_html( $bb_year ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		endif;
	endif;
	?>

	<main id="bb-main">
