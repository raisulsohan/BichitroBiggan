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
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="bb-skip-link" href="#bb-main"><?php esc_html_e( 'মূল কন্টেন্টে যান', 'bichitro-biggan' ); ?></a>

<div id="bb-page">

	<!-- Top bar -->
	<div class="bb-topbar">
		<div class="bb-topbar__inner">
			<span class="bb-topbar__date"><?php echo esc_html( wp_date( 'l, F j, Y' ) ); ?></span>
			<button type="button" class="bb-topbar__mail bb-copy-email" title="<?php esc_attr_e( 'ইমেইল কপি করুন', 'bichitro-biggan' ); ?>">✉</button>
		</div>
	</div>

	<!-- Masthead -->
	<header class="bb-masthead">
		<div class="bb-masthead__inner">
			<div style="flex-shrink:0;">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="bb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<span class="bb-logo__text"><?php bloginfo( 'name' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
			<?php
			$bb_tagline = bb_get_tagline();
			if ( $bb_tagline ) :
				?>
				<p class="bb-masthead__tagline"><?php echo wp_kses_post( $bb_tagline ); ?></p>
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
			<button type="button" class="bb-hamburger bb-hide-desktop" data-bb-toggle="menu" aria-expanded="false" aria-controls="bb-mobile-menu" aria-label="<?php esc_attr_e( 'মেন্যু', 'bichitro-biggan' ); ?>">☰</button>
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
				<button type="button" class="bb-hamburger" data-bb-toggle="menu" aria-expanded="false" aria-controls="bb-mobile-menu" aria-label="<?php esc_attr_e( 'মেন্যু', 'bichitro-biggan' ); ?>">☰</button>
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
		$bb_ticker = bb_query( 0, 3 );
		if ( $bb_ticker->have_posts() ) :
			?>
			<div class="bb-ticker">
				<div class="bb-ticker__inner">
					<span class="bb-ticker__label"><?php esc_html_e( 'নতুন লেখা', 'bichitro-biggan' ); ?></span>

					<div class="bb-ticker__track" style="flex:1 1 0%;min-width:0;">
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
