<?php
/**
 * Plugin Name: বিচিত্র বিজ্ঞান — থিম সেটআপ
 * Description: Bichitro Biggan থিম অ্যাক্টিভেট করার পর মেন্যু লোকেশন, ক্যাটাগরির রঙ, হোমপেজ ব্লক ও Reading সেটিংস এক ক্লিকে বসিয়ে দেয়। সেটআপ শেষ হলে প্লাগইনটি ডিঅ্যাকটিভেট করে দিতে পারেন। কোনো পোস্ট বা কনটেন্ট স্পর্শ করে না।
 * Version: 1.8.5
 * Author: Raisul Islam
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: bb-setup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BB_SETUP_BACKUP_OPTION', 'bb_setup_backup' );

/* -------------------------------------------------------------------------
 * Data maps — keyed by the category slugs on bichitrobiggan.com.
 *
 * Slugs, not names: the live site stores কোয়ান্টাম বিজ্ঞান with U+09DF while
 * the same word typed elsewhere uses U+09AF + U+09BC, so name matching fails.
 * ---------------------------------------------------------------------- */

function bb_setup_podcast_slug() {
	return '%e0%a6%aa%e0%a6%a1%e0%a6%95%e0%a6%be%e0%a6%b8%e0%a7%8d%e0%a6%9f';
}

function bb_setup_colors() {
	return array(
		'space-science'           => '#1565c0',
		'wonders-of-the-universe' => '#6a0dad',
		'quantum-sciences'        => '#f5c800',
		'science-of-life'         => '#2e7d32',
		'nature-and-environment'  => '#00796b',
		'science-and-technology'  => '#d84315',
		'stories-of-scientists'   => '#6a0dad',
		'miscellaneous-science'   => '#607d8b',
		'nobel-prizes'            => '#1a1a1a',
		'physics'                 => '#1a1a1a',
		'latest-science'          => '#1a1a1a',
		bb_setup_podcast_slug()   => '#e65100',
	);
}

function bb_setup_blocks() {
	return array(
		'bb_cat_block1_left'  => 'quantum-sciences',
		'bb_cat_block1_right' => 'nobel-prizes',
		'bb_cat_block2_left'  => 'science-of-life',
		'bb_cat_block2_right' => 'physics',
		'bb_cat_block3_left'  => 'miscellaneous-science',
		'bb_cat_block3_right' => 'latest-science',
		'bb_cat_block4_a'     => 'space-science',
		'bb_cat_block4_b'     => 'stories-of-scientists',
		'bb_cat_block5_a'     => 'wonders-of-the-universe',
		'bb_cat_block5_b'     => 'nature-and-environment',
		'bb_cat_block5_c'     => 'science-and-technology',
		'bb_cat_podcast'      => bb_setup_podcast_slug(),
	);
}

/**
 * Look a category up by slug, tolerating percent-encoding.
 */
function bb_setup_term( $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );

	if ( ( ! $term || is_wp_error( $term ) ) && false !== strpos( $slug, '%' ) ) {
		$term = get_term_by( 'slug', rawurldecode( $slug ), 'category' );
	}

	return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/**
 * The menu we want in the theme's primary location.
 */
function bb_setup_pick_menu() {
	$menu = wp_get_nav_menu_object( 'td-demo-header-menu' );
	if ( $menu ) {
		return $menu;
	}

	$menus = wp_get_nav_menus();
	if ( empty( $menus ) ) {
		return null;
	}

	usort( $menus, function ( $a, $b ) {
		return $b->count - $a->count;
	} );

	return $menus[0];
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

function bb_setup_menu() {
	add_management_page(
		__( 'বিচিত্র বিজ্ঞান সেটআপ', 'bb-setup' ),
		__( 'বিচিত্র বিজ্ঞান সেটআপ', 'bb-setup' ),
		'manage_options',
		'bb-setup',
		'bb_setup_page'
	);
}
add_action( 'admin_menu', 'bb_setup_menu' );

function bb_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'অনুমতি নেই।', 'bb-setup' ) );
	}

	$notices = array();

	if ( isset( $_POST['bb_setup_action'] ) && check_admin_referer( 'bb_setup' ) ) {
		$action = sanitize_key( wp_unslash( $_POST['bb_setup_action'] ) );

		if ( 'apply' === $action ) {
			$notices = bb_setup_apply( ! empty( $_POST['bb_set_front'] ) );
		} elseif ( 'revert' === $action ) {
			$notices = bb_setup_revert();
		}
	}

	$theme       = wp_get_theme();
	$theme_ok    = ( 'bichitro-biggan' === $theme->get_stylesheet() || 'bichitro-biggan' === $theme->get_template() );
	$menu        = bb_setup_pick_menu();
	$locations   = get_nav_menu_locations();
	$primary_set = ! empty( $locations['primary'] );
	$backup      = get_option( BB_SETUP_BACKUP_OPTION );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'বিচিত্র বিজ্ঞান — থিম সেটআপ', 'bb-setup' ); ?></h1>

		<?php foreach ( $notices as $n ) : ?>
			<div class="notice notice-<?php echo esc_attr( $n[0] ); ?>"><p><?php echo wp_kses_post( $n[1] ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( ! $theme_ok ) : ?>
			<div class="notice notice-warning">
				<p><?php
					printf(
						/* translators: %s: active theme name */
						esc_html__( 'এখন সক্রিয় থিম: %s। আগে "Bichitro Biggan" থিমটি অ্যাক্টিভেট করুন, তারপর এই সেটআপ চালান।', 'bb-setup' ),
						'<strong>' . esc_html( $theme->get( 'Name' ) ) . '</strong>'
					);
				?></p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'বর্তমান অবস্থা', 'bb-setup' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
				<tr>
					<td style="width:280px"><strong><?php esc_html_e( 'সক্রিয় থিম', 'bb-setup' ); ?></strong></td>
					<td><?php echo esc_html( $theme->get( 'Name' ) ); ?> <?php echo $theme_ok ? '✅' : '⚠️'; ?></td>
				</tr>
				<?php
				/* Do the files on disk actually contain the newest features? This
				   separates "the upload did not land" from "a cache is stale". */
				$bb_css  = $theme->get_stylesheet_directory() . '/style.css';
				$bb_js   = $theme->get_template_directory() . '/assets/js/theme.js';
				$bb_css_src = file_exists( $bb_css ) ? file_get_contents( $bb_css ) : ''; // phpcs:ignore
				$bb_js_src  = file_exists( $bb_js ) ? file_get_contents( $bb_js ) : '';   // phpcs:ignore
				$bb_has_modal_css = ( false !== strpos( $bb_css_src, '.bb-modal__dialog' ) );
				$bb_has_logo_css  = ( false !== strpos( $bb_css_src, '--bb-logo-h' ) );
				$bb_has_modal_js  = ( false !== strpos( $bb_js_src, 'initArticleModal' ) );
				$bb_has_slider_js = ( false !== strpos( $bb_js_src, 'initSliders' ) );
				$bb_has_search_js = ( false !== strpos( $bb_js_src, 'initLiveSearch' ) );
				?>
				<tr>
					<td><strong><?php esc_html_e( 'থিম ভার্সন', 'bb-setup' ); ?></strong></td>
					<td>
						<code><?php echo esc_html( $theme->get( 'Version' ) ); ?></code>
						<?php if ( version_compare( $theme->get( 'Version' ), '1.7.0', '<' ) ) : ?>
							— <?php esc_html_e( 'পুরোনো ফাইল। নতুন জিপটি আসলে আপলোড হয়নি।', 'bb-setup' ); ?> ⚠️
						<?php else : ?>
							✅
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'সার্ভারের ফাইলে নতুন ফিচার', 'bb-setup' ); ?></strong></td>
					<td>
						<?php echo $bb_has_logo_css ? '✅' : '❌'; ?> <?php esc_html_e( 'লোগোর সাইজ CSS', 'bb-setup' ); ?> &nbsp;|&nbsp;
						<?php echo $bb_has_modal_css ? '✅' : '❌'; ?> <?php esc_html_e( 'পপআপ CSS', 'bb-setup' ); ?> &nbsp;|&nbsp;
						<?php echo $bb_has_modal_js ? '✅' : '❌'; ?> <?php esc_html_e( 'পপআপ JS', 'bb-setup' ); ?> &nbsp;|&nbsp;
						<?php echo $bb_has_slider_js ? '✅' : '❌'; ?> <?php esc_html_e( 'স্লাইডার JS', 'bb-setup' ); ?> &nbsp;|&nbsp;
						<?php echo $bb_has_search_js ? '✅' : '❌'; ?> <?php esc_html_e( 'লাইভ সার্চ JS', 'bb-setup' ); ?>
						<?php if ( $bb_has_logo_css && $bb_has_modal_css && $bb_has_modal_js && $bb_has_slider_js && $bb_has_search_js ) : ?>
							<p class="description"><?php esc_html_e( 'ফাইল ঠিক আছে। সাইটে পরিবর্তন না দেখলে সেটা ক্যাশের সমস্যা — ক্যাশিং প্লাগইন ও Cloudflare/CDN পার্জ করে Ctrl+F5 দিন।', 'bb-setup' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'সার্ভারের ফাইলে নতুন কোড নেই — জিপটি আবার আপলোড করুন, অথবা FTP দিয়ে থিম ফোল্ডারটি রিপ্লেস করুন।', 'bb-setup' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'ব্রাউজার যে ফাইল পাচ্ছে', 'bb-setup' ); ?></strong></td>
					<td>
						<?php
						$bb_ver = defined( 'BB_VERSION' ) ? BB_VERSION : '';
						$bb_mt  = file_exists( $bb_css ) ? filemtime( $bb_css ) : 0;
						?>
						<code>style.css?ver=<?php echo esc_html( $bb_mt ? $bb_ver . '.' . $bb_mt : '?' ); ?></code>
						<p class="description"><?php esc_html_e( 'সাইটের সোর্স কোডে (Ctrl+U) style.css-এর পাশে এই নম্বরটাই থাকা উচিত। না থাকলে ক্যাশ পুরোনো ফাইল দিচ্ছে।', 'bb-setup' ); ?></p>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'প্রধান মেন্যু লোকেশন', 'bb-setup' ); ?></strong></td>
					<td>
						<?php
						if ( $primary_set ) {
							$assigned = wp_get_nav_menu_object( $locations['primary'] );
							echo esc_html( $assigned ? $assigned->name : '#' . $locations['primary'] ) . ' ✅';
						} else {
							echo esc_html__( 'সেট করা নেই', 'bb-setup' ) . ' ⚠️';
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'যে মেন্যুটি বসানো হবে', 'bb-setup' ); ?></strong></td>
					<td>
						<?php
						echo $menu
							? esc_html( $menu->name ) . ' (' . esc_html( $menu->count ) . ' ' . esc_html__( 'আইটেম', 'bb-setup' ) . ')'
							: esc_html__( 'কোনো মেন্যু পাওয়া যায়নি', 'bb-setup' );
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'হোমপেজ', 'bb-setup' ); ?></strong></td>
					<td>
						<?php
						if ( 'posts' === get_option( 'show_on_front' ) ) {
							echo esc_html__( 'সর্বশেষ পোস্ট', 'bb-setup' ) . ' ✅';
						} else {
							$fp = (int) get_option( 'page_on_front' );
							$pp = (int) get_option( 'page_for_posts' );
							printf(
								/* translators: 1: front page title, 2: posts page title */
								esc_html__( 'স্ট্যাটিক পেজ (হোমপেজ: %1$s, পোস্ট পেজ: %2$s) ✅', 'bb-setup' ),
								esc_html( $fp ? get_the_title( $fp ) : '—' ),
								esc_html( $pp ? get_the_title( $pp ) : '—' )
							);
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'পার্মালিংক', 'bb-setup' ); ?></strong></td>
					<td><?php echo esc_html( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : __( 'প্লেইন — Settings → Permalinks থেকে বদলান ⚠️', 'bb-setup' ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'ক্যাটাগরি ম্যাপিং', 'bb-setup' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'হোমপেজ ব্লক', 'bb-setup' ); ?></th>
					<th><?php esc_html_e( 'ক্যাটাগরি স্লাগ', 'bb-setup' ); ?></th>
					<th><?php esc_html_e( 'সাইটে পাওয়া গেল?', 'bb-setup' ); ?></th>
					<th><?php esc_html_e( 'রঙ', 'bb-setup' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$colors = bb_setup_colors();
			foreach ( bb_setup_blocks() as $mod => $slug ) :
				$term  = bb_setup_term( $slug );
				$color = isset( $colors[ $slug ] ) ? $colors[ $slug ] : '#1a1a1a';
				?>
				<tr>
					<td><code><?php echo esc_html( $mod ); ?></code></td>
					<td><code><?php echo esc_html( urldecode( $slug ) ); ?></code></td>
					<td>
						<?php
						echo $term
							? esc_html( $term->name ) . ' (' . esc_html( $term->count ) . ') ✅'
							: '❌ ' . esc_html__( 'নেই', 'bb-setup' );
						?>
					</td>
					<td><span style="display:inline-block;width:60px;height:16px;background:<?php echo esc_attr( $color ); ?>;border:1px solid #ccc"></span> <code><?php echo esc_html( $color ); ?></code></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'সেটআপ চালান', 'bb-setup' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'bb_setup' ); ?>
			<p>
				<label>
					<input type="checkbox" name="bb_set_front" value="1" />
					<?php esc_html_e( 'হোমপেজ সেটিংস পরিবর্তন করে "সর্বশেষ পোস্ট" করো (স্ট্যাটিক পেজ ব্যবহার করলে এটি আনচেক রাখুন)', 'bb-setup' ); ?>
				</label>
			</p>
			<p class="description" style="max-width:900px">
				<?php esc_html_e( 'এটি যা যা বদলাবে: প্রধান মেন্যু লোকেশন, ১২টি ক্যাটাগরির রঙ (term meta), হোমপেজ ব্লকের ক্যাটাগরি ম্যাপিং (theme mods), এবং Reading সেটিংস। পোস্ট, পেজ, ছবি বা কনটেন্ট কিছুই স্পর্শ করবে না। পুরোনো মানগুলো সংরক্ষণ করা হবে যাতে নিচের বাটন দিয়ে ফিরিয়ে আনা যায়।', 'bb-setup' ); ?>
			</p>
			<p>
				<button class="button button-primary" name="bb_setup_action" value="apply">
					<?php esc_html_e( 'সব সেট করো', 'bb-setup' ); ?>
				</button>
				<?php if ( $backup ) : ?>
					<button class="button" name="bb_setup_action" value="revert"
						onclick="return confirm('<?php echo esc_js( __( 'আগের সেটিংসে ফিরিয়ে নেব?', 'bb-setup' ) ); ?>')">
						<?php esc_html_e( 'আগের অবস্থায় ফিরিয়ে দাও', 'bb-setup' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Apply / revert
 * ---------------------------------------------------------------------- */

function bb_setup_apply( $set_front ) {
	$notices = array();
	$done    = array();

	// Snapshot everything we are about to change, once.
	if ( ! get_option( BB_SETUP_BACKUP_OPTION ) ) {
		update_option( BB_SETUP_BACKUP_OPTION, array(
			'nav_menu_locations' => get_nav_menu_locations(),
			'show_on_front'      => get_option( 'show_on_front' ),
			'page_on_front'      => get_option( 'page_on_front' ),
			'theme_mods'         => get_theme_mods(),
		), false );
	}

	// 1. Menu location.
	$menu = bb_setup_pick_menu();
	if ( $menu ) {
		$locations             = get_nav_menu_locations();
		$locations['primary']  = $menu->term_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		$done[] = sprintf(
			/* translators: %s: menu name */
			__( 'মেন্যু "%s" প্রধান মেন্যু লোকেশনে বসানো হয়েছে', 'bb-setup' ),
			$menu->name
		);
	} else {
		$notices[] = array( 'warning', __( 'কোনো নেভিগেশন মেন্যু পাওয়া যায়নি — Appearance → Menus থেকে বানিয়ে নিন।', 'bb-setup' ) );
	}

	// 2. Category colours.
	$colored = 0;
	$missing = array();
	foreach ( bb_setup_colors() as $slug => $color ) {
		$term = bb_setup_term( $slug );
		if ( ! $term ) {
			$missing[] = urldecode( $slug );
			continue;
		}
		update_term_meta( $term->term_id, 'bb_color', $color );
		$colored++;
	}
	$done[] = sprintf(
		/* translators: %d: number of categories */
		__( '%d টি ক্যাটাগরিতে রঙ বসানো হয়েছে', 'bb-setup' ),
		$colored
	);
	if ( $missing ) {
		$notices[] = array( 'warning', sprintf(
			/* translators: %s: comma separated slugs */
			__( 'এই স্লাগগুলো সাইটে পাওয়া যায়নি: %s', 'bb-setup' ),
			esc_html( implode( ', ', $missing ) )
		) );
	}

	// 3. Homepage block mapping.
	$mapped = 0;
	foreach ( bb_setup_blocks() as $mod => $slug ) {
		$term = bb_setup_term( $slug );
		if ( ! $term ) {
			continue;
		}
		set_theme_mod( $mod, (int) $term->term_id );
		$mapped++;
	}
	$done[] = sprintf(
		/* translators: %d: number of blocks */
		__( '%d টি হোমপেজ ব্লকে ক্যাটাগরি ম্যাপ করা হয়েছে', 'bb-setup' ),
		$mapped
	);

	// 4. Front page.
	if ( $set_front && 'posts' !== get_option( 'show_on_front' ) ) {
		update_option( 'show_on_front', 'posts' );
		$done[] = __( 'হোমপেজ "সর্বশেষ পোস্ট" করা হয়েছে', 'bb-setup' );
	}

	flush_rewrite_rules( false );
	delete_transient( 'bb_post_years' );

	$notices[] = array( 'success', '<strong>' . esc_html__( 'সেটআপ সম্পন্ন:', 'bb-setup' ) . '</strong><br>• ' . implode( '<br>• ', array_map( 'esc_html', $done ) ) );

	return $notices;
}

function bb_setup_revert() {
	$backup = get_option( BB_SETUP_BACKUP_OPTION );

	if ( ! $backup ) {
		return array( array( 'error', __( 'ফিরিয়ে নেওয়ার মতো সংরক্ষিত সেটিংস নেই।', 'bb-setup' ) ) );
	}

	if ( isset( $backup['theme_mods'] ) && is_array( $backup['theme_mods'] ) ) {
		update_option( 'theme_mods_' . get_option( 'stylesheet' ), $backup['theme_mods'] );
	}
	if ( isset( $backup['show_on_front'] ) ) {
		update_option( 'show_on_front', $backup['show_on_front'] );
	}
	if ( isset( $backup['page_on_front'] ) ) {
		update_option( 'page_on_front', $backup['page_on_front'] );
	}

	delete_option( BB_SETUP_BACKUP_OPTION );
	flush_rewrite_rules( false );

	return array( array( 'success', __( 'আগের সেটিংস ফিরিয়ে আনা হয়েছে। ক্যাটাগরির রঙগুলো (term meta) রয়ে গেছে — সেগুলো ক্ষতিকর নয়, তবে চাইলে Posts → Categories থেকে মুছে দিতে পারেন।', 'bb-setup' ) ) );
}

/* -------------------------------------------------------------------------
 * Nudge after switching to the theme
 * ---------------------------------------------------------------------- */

function bb_setup_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( $screen && 'tools_page_bb-setup' === $screen->id ) {
		return;
	}

	$theme = wp_get_theme();
	if ( 'bichitro-biggan' !== $theme->get_stylesheet() && 'bichitro-biggan' !== $theme->get_template() ) {
		return;
	}

	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['primary'] ) ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'বিচিত্র বিজ্ঞান থিমের সেটআপ এখনো বাকি আছে।', 'bb-setup' ); ?>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=bb-setup' ) ); ?>"><?php esc_html_e( 'সেটআপ পেজে যান', 'bb-setup' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'bb_setup_admin_notice' );
