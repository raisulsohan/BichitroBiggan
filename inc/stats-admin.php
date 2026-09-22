<?php
/**
 * The statistics screen: Dashboard → পরিসংখ্যান.
 *
 * Everything on it comes from the site's own table — no account to sign in to,
 * no permission to be granted, nothing to expire. Drawn with plain HTML and one
 * inline SVG; no charting library is loaded.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The windows the screen offers, in days. */
function bb_stats_ranges() {
	return array(
		7   => __( 'গত ৭ দিন', 'bichitro-biggan' ),
		30  => __( 'গত ৩০ দিন', 'bichitro-biggan' ),
		90  => __( 'গত ৯০ দিন', 'bichitro-biggan' ),
		365 => __( 'গত এক বছর', 'bichitro-biggan' ),
	);
}

function bb_stats_menu() {
	add_menu_page(
		__( 'পরিসংখ্যান', 'bichitro-biggan' ),
		__( 'পরিসংখ্যান', 'bichitro-biggan' ),
		'edit_posts',
		'bb-stats',
		'bb_stats_page',
		'dashicons-chart-area',
		3
	);
}
add_action( 'admin_menu', 'bb_stats_menu' );

/**
 * A number in the site's own digits.
 *
 * @param int $number Number.
 * @return string
 */
function bb_stats_number( $number ) {
	return function_exists( 'bb_bangla_number' ) ? bb_bangla_number( number_format_i18n( (int) $number ) ) : number_format_i18n( (int) $number );
}

/**
 * The label a source is shown under.
 *
 * @param string $source Stored source.
 * @return string
 */
function bb_stats_source_label( $source ) {
	$names = array(
		'direct'    => __( 'সরাসরি / বুকমার্ক', 'bichitro-biggan' ),
		'internal'  => __( 'সাইটের ভেতর থেকে', 'bichitro-biggan' ),
		'google'    => 'Google',
		'facebook'  => 'Facebook',
		'youtube'   => 'YouTube',
		'whatsapp'  => 'WhatsApp',
		'bing'      => 'Bing',
		'x'         => 'X / Twitter',
		'linkedin'  => 'LinkedIn',
		'telegram'  => 'Telegram',
		'instagram' => 'Instagram',
		'reddit'    => 'Reddit',
		'wikipedia' => 'Wikipedia',
	);

	return isset( $names[ $source ] ) ? $names[ $source ] : $source;
}

/**
 * A device or language label.
 *
 * @param string $key Stored value.
 * @return string
 */
function bb_stats_plain_label( $key ) {
	$names = array(
		'mobile'  => __( 'মোবাইল', 'bichitro-biggan' ),
		'tablet'  => __( 'ট্যাবলেট', 'bichitro-biggan' ),
		'desktop' => __( 'কম্পিউটার', 'bichitro-biggan' ),
		'bn'      => __( 'বাংলা', 'bichitro-biggan' ),
		'en'      => __( 'ইংরেজি (/en)', 'bichitro-biggan' ),
	);

	return isset( $names[ $key ] ) ? $names[ $key ] : $key;
}

/**
 * The line chart: reads per day, with the visits underneath it.
 *
 * @param array $series day => array{hits,visits}.
 * @return void
 */
function bb_stats_chart( array $series ) {
	$days = array_keys( $series );
	$hits = array_map(
		function ( $row ) {
			return (int) $row['hits'];
		},
		array_values( $series )
	);

	$count = count( $hits );

	if ( ! $count ) {
		return;
	}

	$width  = 980;
	$height = 220;
	$pad    = 28;
	$top    = max( 1, max( $hits ) );

	$x = function ( $i ) use ( $width, $pad, $count ) {
		return $count > 1 ? $pad + ( ( $width - $pad * 2 ) * $i / ( $count - 1 ) ) : $width / 2;
	};

	$y = function ( $value ) use ( $height, $pad, $top ) {
		return $height - $pad - ( ( $height - $pad * 2 ) * $value / $top );
	};

	$points = array();

	foreach ( $hits as $i => $value ) {
		$points[] = round( $x( $i ), 1 ) . ',' . round( $y( $value ), 1 );
	}

	$area = implode( ' ', $points );
	?>
	<div class="bb-stats-chart">
		<svg viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" preserveAspectRatio="none" role="img"
			aria-label="<?php esc_attr_e( 'প্রতিদিনের পাঠ', 'bichitro-biggan' ); ?>">
			<polygon fill="rgba(0,128,255,0.12)"
				points="<?php echo esc_attr( $pad . ',' . ( $height - $pad ) . ' ' . $area . ' ' . ( $width - $pad ) . ',' . ( $height - $pad ) ); ?>" />
			<polyline fill="none" stroke="#0080ff" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"
				points="<?php echo esc_attr( $area ); ?>" />
			<line x1="<?php echo (int) $pad; ?>" y1="<?php echo (int) ( $height - $pad ); ?>"
				x2="<?php echo (int) ( $width - $pad ); ?>" y2="<?php echo (int) ( $height - $pad ); ?>"
				stroke="#d1d5db" stroke-width="1" />
		</svg>
		<div class="bb-stats-chart__scale">
			<span><?php echo esc_html( bb_stats_number( $top ) ); ?></span>
			<span>0</span>
		</div>
		<div class="bb-stats-chart__days">
			<span><?php echo esc_html( bb_stats_day_label( reset( $days ) ) ); ?></span>
			<span><?php echo esc_html( bb_stats_day_label( end( $days ) ) ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * A day as the site writes dates.
 *
 * @param string $day Y-m-d.
 * @return string
 */
function bb_stats_day_label( $day ) {
	$time = strtotime( (string) $day . ' 12:00:00' );

	if ( ! $time ) {
		return (string) $day;
	}

	if ( function_exists( 'bb_bangla_timestamp' ) ) {
		return bb_bangla_timestamp( $time );
	}

	return wp_date( 'j M Y', $time );
}

/**
 * One bar in a breakdown list.
 *
 * @param string $label Row label.
 * @param int    $hits  Reads.
 * @param int    $max   The biggest row, for the bar's width.
 * @param int    $total Everything, for the share.
 * @return void
 */
function bb_stats_bar( $label, $hits, $max, $total ) {
	$width = $max > 0 ? max( 2, round( 100 * $hits / $max ) ) : 0;
	$share = $total > 0 ? round( 100 * $hits / $total ) : 0;
	?>
	<li class="bb-stats-bar">
		<span class="bb-stats-bar__label"><?php echo esc_html( $label ); ?></span>
		<span class="bb-stats-bar__track"><span class="bb-stats-bar__fill" style="width:<?php echo (int) $width; ?>%"></span></span>
		<span class="bb-stats-bar__value">
			<?php echo esc_html( bb_stats_number( $hits ) ); ?>
			<small><?php echo esc_html( bb_stats_number( $share ) ); ?>%</small>
		</span>
	</li>
	<?php
}

/**
 * The screen.
 */
function bb_stats_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bichitro-biggan' ) );
	}

	$ranges = bb_stats_ranges();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing how far back to look changes nothing.
	$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
	$days = isset( $ranges[ $days ] ) ? $days : 30;

	$totals  = bb_stats_totals( $days );
	$series  = bb_stats_daily( $days );
	$top     = bb_stats_top_posts( $days, 15 );
	$sources = bb_stats_grouped( 'source', $days );
	$devices = bb_stats_grouped( 'device', $days );
	$langs   = bb_stats_grouped( 'lang', $days );
	$first   = bb_stats_first_day();
	$per_day = $totals['days'] > 0 ? round( $totals['hits'] / $totals['days'] ) : 0;
	?>
	<div class="wrap bb-stats">
		<h1><?php esc_html_e( 'পরিসংখ্যান', 'bichitro-biggan' ); ?></h1>

		<p class="bb-stats__lede">
			<?php esc_html_e( 'সাইটের নিজের হিসাব — কোনো বাইরের সেবা, অ্যাকাউন্ট বা অনুমতি ছাড়াই। পাঠকের ঠিকানা বা কুকি কিছুই রাখা হয় না, আর যাঁরা সাইট চালান তাঁদের নিজেদের ভিজিট গোনা হয় না।', 'bichitro-biggan' ); ?>
		</p>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $ranges as $value => $label ) : ?>
				<a class="nav-tab <?php echo ( $value === $days ) ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=bb-stats&days=' . (int) $value ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<?php if ( ! $first ) : ?>
			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'গোনা শুরু হয়েছে।', 'bichitro-biggan' ); ?></strong>
					<?php esc_html_e( 'এখনো কোনো পাঠ জমা হয়নি — কেউ সাইটে এলেই এখানে দেখা যাবে। নিজের ভিজিট দেখতে চাইলে লগআউট করে (বা অন্য ব্রাউজারে) সাইটটা খুলুন।', 'bichitro-biggan' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<div class="bb-stats__cards">
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'মোট পাঠ', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $totals['hits'] ) ); ?></strong>
				<span class="bb-stats-card__note">
					<?php
					printf(
						/* translators: %s: reads per day. */
						esc_html__( 'দিনে গড়ে %s', 'bichitro-biggan' ),
						esc_html( bb_stats_number( $per_day ) )
					);
					?>
				</span>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'ভিজিট', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $totals['visits'] ) ); ?></strong>
				<span class="bb-stats-card__note"><?php esc_html_e( 'একেকজন পাঠকের একেকবার আসা', 'bichitro-biggan' ); ?></span>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'যতগুলো লেখা পড়া হয়েছে', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $totals['articles'] ) ); ?></strong>
				<span class="bb-stats-card__note">
					<?php
					printf(
						/* translators: %s: how many posts the site has. */
						esc_html__( 'সাইটে আছে %s', 'bichitro-biggan' ),
						esc_html( bb_stats_number( (int) wp_count_posts()->publish ) )
					);
					?>
				</span>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'গোনা শুরু', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value bb-stats-card__value--small">
					<?php echo esc_html( $first ? bb_stats_day_label( $first ) : '—' ); ?>
				</strong>
				<span class="bb-stats-card__note"><?php esc_html_e( 'এর আগের হিসাব এখানে নেই', 'bichitro-biggan' ); ?></span>
			</div>
		</div>

		<div class="bb-stats-panel">
			<h2><?php esc_html_e( 'প্রতিদিন কত পাঠ', 'bichitro-biggan' ); ?></h2>
			<?php bb_stats_chart( $series ); ?>
		</div>

		<div class="bb-stats__columns">
			<div class="bb-stats-panel">
				<h2><?php esc_html_e( 'সবচেয়ে বেশি পড়া লেখা', 'bichitro-biggan' ); ?></h2>
				<?php if ( empty( $top ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'এই সময়ে কোনো লেখা পড়া হয়নি।', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="widefat striped bb-stats-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'লেখা', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'বাংলা', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num">EN</th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'মোট', 'bichitro-biggan' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top as $row ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( get_permalink( $row['post_id'] ) ); ?>" target="_blank" rel="noopener">
											<?php echo esc_html( get_the_title( $row['post_id'] ) ); ?>
										</a>
									</td>
									<td class="bb-stats-table__num"><?php echo esc_html( bb_stats_number( $row['bn'] ) ); ?></td>
									<td class="bb-stats-table__num"><?php echo esc_html( $row['en'] ? bb_stats_number( $row['en'] ) : '—' ); ?></td>
									<td class="bb-stats-table__num"><strong><?php echo esc_html( bb_stats_number( $row['hits'] ) ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="bb-stats-panel">
				<h2><?php esc_html_e( 'পাঠক আসছে কোথা থেকে', 'bichitro-biggan' ); ?></h2>
				<?php if ( empty( $sources ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'এখনো কিছু জানা যায়নি।', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max = (int) $sources[0]['hits'];
						foreach ( $sources as $row ) {
							bb_stats_bar( bb_stats_source_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
						}
						?>
					</ul>
				<?php endif; ?>

				<h2><?php esc_html_e( 'কী দিয়ে পড়ছে', 'bichitro-biggan' ); ?></h2>
				<ul class="bb-stats-bars">
					<?php
					$max = ! empty( $devices ) ? (int) $devices[0]['hits'] : 0;
					foreach ( $devices as $row ) {
						bb_stats_bar( bb_stats_plain_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
					}
					?>
				</ul>

				<h2><?php esc_html_e( 'কোন ভাষায়', 'bichitro-biggan' ); ?></h2>
				<ul class="bb-stats-bars">
					<?php
					$max = ! empty( $langs ) ? (int) $langs[0]['hits'] : 0;
					foreach ( $langs as $row ) {
						bb_stats_bar( bb_stats_plain_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
					}
					?>
				</ul>
			</div>
		</div>
	</div>

	<style>
		.bb-stats__lede { max-width: 820px; color: #50575e; }
		.bb-stats__cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin: 20px 0; }
		.bb-stats-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 18px; }
		.bb-stats-card__label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #646970; }
		.bb-stats-card__value { display: block; font-size: 30px; line-height: 1.2; margin: 6px 0 2px; color: #1d2327; }
		.bb-stats-card__value--small { font-size: 17px; }
		.bb-stats-card__note { font-size: 12px; color: #646970; }
		.bb-stats-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 6px 20px 18px; margin: 0 0 20px; }
		.bb-stats-panel h2 { font-size: 15px; }
		.bb-stats__columns { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; align-items: start; }
		@media (max-width: 1100px) { .bb-stats__columns { grid-template-columns: 1fr; } }
		.bb-stats-chart { position: relative; }
		.bb-stats-chart svg { width: 100%; height: 220px; display: block; }
		.bb-stats-chart__scale { position: absolute; top: 18px; left: 0; height: 170px; display: flex; flex-direction: column; justify-content: space-between; font-size: 11px; color: #646970; }
		.bb-stats-chart__days { display: flex; justify-content: space-between; font-size: 12px; color: #646970; margin-top: -6px; }
		.bb-stats-table__num { text-align: right; width: 80px; }
		.bb-stats-bars { margin: 0 0 18px; }
		.bb-stats-bar { display: grid; grid-template-columns: 1fr 120px 92px; align-items: center; gap: 10px; margin: 0 0 8px; }
		.bb-stats-bar__label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.bb-stats-bar__track { background: #f0f0f1; border-radius: 999px; height: 8px; overflow: hidden; }
		.bb-stats-bar__fill { display: block; height: 8px; background: #0080ff; border-radius: 999px; }
		.bb-stats-bar__value { text-align: right; font-variant-numeric: tabular-nums; }
		.bb-stats-bar__value small { color: #646970; margin-left: 4px; }
		.bb-stats__empty { color: #646970; }
	</style>
	<?php
}
