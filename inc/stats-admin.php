<?php
/**
 * The statistics screen: Dashboard → Statistics.
 *
 * Everything on it comes from the site's own table — no account to sign in to,
 * no permission to be granted, nothing to expire. Drawn with plain HTML and
 * inline SVG; no charting library is loaded.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The windows the screen offers. The first one is what it opens on. */
function bb_stats_ranges() {
	return array(
		'24h'  => __( 'Last 24 hours', 'bichitro-biggan' ),
		'7d'   => __( 'Last 7 days', 'bichitro-biggan' ),
		'30d'  => __( 'Last 30 days', 'bichitro-biggan' ),
		'90d'  => __( 'Last 90 days', 'bichitro-biggan' ),
		'365d' => __( 'Last year', 'bichitro-biggan' ),
	);
}

function bb_stats_menu() {
	add_menu_page(
		__( 'Statistics', 'bichitro-biggan' ),
		__( 'Statistics', 'bichitro-biggan' ),
		'edit_posts',
		'bb-stats',
		'bb_stats_page',
		'dashicons-chart-area',
		3
	);
}
add_action( 'admin_menu', 'bb_stats_menu' );

/**
 * A number, grouped the way the dashboard's language groups numbers.
 *
 * @param int $number Number.
 * @return string
 */
function bb_stats_number( $number ) {
	return number_format_i18n( (int) $number );
}

/**
 * The label a source is shown under.
 *
 * @param string $source Stored source.
 * @return string
 */
function bb_stats_source_label( $source ) {
	$names = array(
		'direct'    => __( 'Direct / bookmark', 'bichitro-biggan' ),
		'internal'  => __( 'From this site', 'bichitro-biggan' ),
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
		'mobile'  => __( 'Phone', 'bichitro-biggan' ),
		'tablet'  => __( 'Tablet', 'bichitro-biggan' ),
		'desktop' => __( 'Computer', 'bichitro-biggan' ),
		'bn'      => __( 'Bengali', 'bichitro-biggan' ),
		'en'      => __( 'English (/en)', 'bichitro-biggan' ),
	);

	return isset( $names[ $key ] ) ? $names[ $key ] : $key;
}

/**
 * A day, written the way the dashboard writes dates.
 *
 * @param string $day Y-m-d.
 * @return string
 */
function bb_stats_day_label( $day ) {
	$time = strtotime( (string) $day . ' 12:00:00' );

	return $time ? wp_date( 'j M Y', $time ) : (string) $day;
}

/**
 * How this window compares with the one immediately before it.
 *
 * @param int $now    This window.
 * @param int $before The window before.
 * @return void
 */
function bb_stats_change( $now, $before ) {
	if ( $before <= 0 ) {
		if ( $now <= 0 ) {
			return;
		}
		?>
		<span class="bb-stats-change bb-stats-change--up"><?php esc_html_e( 'first of its kind', 'bichitro-biggan' ); ?></span>
		<?php
		return;
	}

	$change = (int) round( 100 * ( $now - $before ) / $before );

	if ( 0 === $change ) {
		?>
		<span class="bb-stats-change"><?php esc_html_e( 'level with the period before', 'bichitro-biggan' ); ?></span>
		<?php
		return;
	}

	$up = $change > 0;
	?>
	<span class="bb-stats-change <?php echo $up ? 'bb-stats-change--up' : 'bb-stats-change--down'; ?>">
		<?php echo $up ? '▲' : '▼'; ?>
		<?php echo esc_html( bb_stats_number( abs( $change ) ) ); ?>%
		<small><?php esc_html_e( 'vs the period before', 'bichitro-biggan' ); ?></small>
	</span>
	<?php
}

/**
 * The line: reads per hour, or per day.
 *
 * @param array $series Slot => array{hits,visits,label}.
 * @return void
 */
function bb_stats_chart( array $series ) {
	$rows  = array_values( $series );
	$hits  = array_map(
		function ( $row ) {
			return (int) $row['hits'];
		},
		$rows
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

	// A handful of labels along the bottom, not one per point.
	$every  = max( 1, (int) ceil( $count / 8 ) );
	$labels = array();

	foreach ( $rows as $i => $row ) {
		if ( 0 !== $i % $every && $i !== $count - 1 ) {
			continue;
		}

		$labels[] = array(
			'x'    => $x( $i ),
			'text' => $row['label'],
		);
	}
	?>
	<div class="bb-stats-chart">
		<svg viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" preserveAspectRatio="none" role="img"
			aria-label="<?php esc_attr_e( 'Reads over time', 'bichitro-biggan' ); ?>">
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
		<div class="bb-stats-chart__labels">
			<?php foreach ( $labels as $label ) : ?>
				<span style="left:<?php echo esc_attr( round( 100 * $label['x'] / $width, 2 ) ); ?>%">
					<?php echo esc_html( $label['text'] ); ?>
				</span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * The hour-of-day pattern, as 24 small columns.
 *
 * @param array $hours Hour => reads.
 * @return void
 */
function bb_stats_hour_pattern( array $hours ) {
	$top = max( 1, max( $hours ) );
	?>
	<div class="bb-stats-hours">
		<?php foreach ( $hours as $hour => $value ) : ?>
			<?php
			$title = sprintf(
				/* translators: 1: hour of the day, 2: how many reads. */
				__( '%1$s — %2$s reads', 'bichitro-biggan' ),
				sprintf( '%02d:00', $hour ),
				bb_stats_number( $value )
			);
			?>
			<div class="bb-stats-hours__col" title="<?php echo esc_attr( $title ); ?>">
				<span class="bb-stats-hours__bar" style="height:<?php echo (int) max( 2, round( 100 * $value / $top ) ); ?>%"></span>
				<?php if ( 0 === $hour % 6 ) : ?>
					<span class="bb-stats-hours__tick"><?php echo esc_html( sprintf( '%02d', $hour ) ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
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
	$key = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '24h';
	$key = isset( $ranges[ $key ] ) ? $key : '24h';

	$totals   = bb_stats_totals( $key );
	$before   = bb_stats_totals( $key, true );
	$series   = bb_stats_series( $key );
	$top      = bb_stats_top_posts( $key, 15 );
	$sources  = bb_stats_grouped( 'source', $key );
	$devices  = bb_stats_grouped( 'device', $key );
	$langs    = bb_stats_grouped( 'lang', $key );
	$hours    = bb_stats_by_hour( $key );
	$searches = bb_stats_top_searches( 12 );
	$first    = bb_stats_first_day();
	$now      = bb_stats_pulse( 30 );
	?>
	<div class="wrap bb-stats">
		<h1><?php esc_html_e( 'Statistics', 'bichitro-biggan' ); ?></h1>

		<p class="bb-stats__lede">
			<?php esc_html_e( 'The site\'s own count — no outside service, no account, no permission that can be withdrawn. Nothing that identifies a reader is stored, and visits by the people who run the site are left out.', 'bichitro-biggan' ); ?>
		</p>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $ranges as $value => $label ) : ?>
				<a class="nav-tab <?php echo ( $value === $key ) ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=bb-stats&range=' . rawurlencode( $value ) ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<?php if ( ! $first ) : ?>
			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Counting has started.', 'bichitro-biggan' ); ?></strong>
					<?php esc_html_e( 'Nothing has been counted yet — it will appear here as soon as somebody visits. To see your own visit, open the site logged out, or in another browser.', 'bichitro-biggan' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<div class="bb-stats__cards">
			<div class="bb-stats-card bb-stats-card--now">
				<span class="bb-stats-card__label">
					<span class="bb-stats-dot" aria-hidden="true"></span>
					<?php esc_html_e( 'Right now', 'bichitro-biggan' ); ?>
				</span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $now ) ); ?></strong>
				<span class="bb-stats-card__note"><?php esc_html_e( 'reads in the last 30 minutes', 'bichitro-biggan' ); ?></span>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'Reads', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $totals['hits'] ) ); ?></strong>
				<?php bb_stats_change( $totals['hits'], $before['hits'] ); ?>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'Visits', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $totals['visits'] ) ); ?></strong>
				<?php bb_stats_change( $totals['visits'], $before['visits'] ); ?>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'Articles read', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value"><?php echo esc_html( bb_stats_number( $totals['articles'] ) ); ?></strong>
				<span class="bb-stats-card__note">
					<?php
					printf(
						/* translators: 1: reads of articles, 2: reads of every other page. */
						esc_html__( '%1$s article reads, %2$s elsewhere', 'bichitro-biggan' ),
						esc_html( bb_stats_number( $totals['article_hits'] ) ),
						esc_html( bb_stats_number( $totals['other_hits'] ) )
					);
					?>
				</span>
			</div>
			<div class="bb-stats-card">
				<span class="bb-stats-card__label"><?php esc_html_e( 'Counting since', 'bichitro-biggan' ); ?></span>
				<strong class="bb-stats-card__value bb-stats-card__value--small">
					<?php echo esc_html( $first ? bb_stats_day_label( $first ) : '—' ); ?>
				</strong>
				<span class="bb-stats-card__note"><?php esc_html_e( 'nothing from before this is here', 'bichitro-biggan' ); ?></span>
			</div>
		</div>

		<div class="bb-stats-panel">
			<h2>
				<?php echo esc_html( '24h' === $key ? __( 'Reads by the hour', 'bichitro-biggan' ) : __( 'Reads per day', 'bichitro-biggan' ) ); ?>
			</h2>
			<?php bb_stats_chart( $series ); ?>
		</div>

		<div class="bb-stats__columns">
			<div class="bb-stats-panel">
				<h2><?php esc_html_e( 'Most read', 'bichitro-biggan' ); ?></h2>
				<?php if ( empty( $top ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'No article was read in this period.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="widefat striped bb-stats-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Article', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Bengali', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num">EN</th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Total', 'bichitro-biggan' ); ?></th>
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

				<h2><?php esc_html_e( 'When they read', 'bichitro-biggan' ); ?></h2>
				<p class="bb-stats__hint"><?php esc_html_e( 'Reads by hour of the day across this whole period, on the site\'s own clock.', 'bichitro-biggan' ); ?></p>
				<?php bb_stats_hour_pattern( $hours ); ?>
			</div>

			<div class="bb-stats-panel">
				<h2><?php esc_html_e( 'Where readers come from', 'bichitro-biggan' ); ?></h2>
				<?php if ( empty( $sources ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing to show yet.', 'bichitro-biggan' ); ?></p>
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

				<h2><?php esc_html_e( 'What they read on', 'bichitro-biggan' ); ?></h2>
				<ul class="bb-stats-bars">
					<?php
					$max = ! empty( $devices ) ? (int) $devices[0]['hits'] : 0;
					foreach ( $devices as $row ) {
						bb_stats_bar( bb_stats_plain_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
					}
					?>
				</ul>

				<h2><?php esc_html_e( 'Which edition', 'bichitro-biggan' ); ?></h2>
				<ul class="bb-stats-bars">
					<?php
					$max = ! empty( $langs ) ? (int) $langs[0]['hits'] : 0;
					foreach ( $langs as $row ) {
						bb_stats_bar( bb_stats_plain_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
					}
					?>
				</ul>

				<h2><?php esc_html_e( 'What they searched for', 'bichitro-biggan' ); ?></h2>
				<p class="bb-stats__hint"><?php esc_html_e( 'Typed into the site\'s own search box — all time, most asked first.', 'bichitro-biggan' ); ?></p>
				<?php if ( empty( $searches ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nobody has searched yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="widefat striped bb-stats-table">
						<tbody>
							<?php foreach ( $searches as $row ) : ?>
								<tr>
									<td>
										<?php echo esc_html( $row['term'] ); ?>
										<?php if ( 'en' === $row['lang'] ) : ?>
											<span class="bb-stats-tag">EN</span>
										<?php endif; ?>
									</td>
									<td class="bb-stats-table__num"><?php echo esc_html( bb_stats_number( $row['hits'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<style>
		.bb-stats__lede { max-width: 820px; color: #50575e; }
		.bb-stats__hint { color: #646970; margin: -6px 0 10px; font-size: 12px; }
		.bb-stats__cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 20px 0; }
		.bb-stats-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 18px; }
		.bb-stats-card--now { border-color: #b7e3c0; background: #f4fbf5; }
		.bb-stats-card__label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #646970; }
		.bb-stats-card__value { display: block; font-size: 30px; line-height: 1.2; margin: 6px 0 2px; color: #1d2327; }
		.bb-stats-card__value--small { font-size: 17px; }
		.bb-stats-card__note { font-size: 12px; color: #646970; }
		.bb-stats-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #00a32a; margin-right: 5px; vertical-align: 1px; }
		.bb-stats-change { display: block; font-size: 12px; color: #646970; }
		.bb-stats-change--up { color: #00733c; }
		.bb-stats-change--down { color: #b32d2e; }
		.bb-stats-change small { color: #646970; font-size: 11px; }
		.bb-stats-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 6px 20px 18px; margin: 0 0 20px; }
		.bb-stats-panel h2 { font-size: 15px; }
		.bb-stats__columns { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; align-items: start; }
		@media (max-width: 1100px) { .bb-stats__columns { grid-template-columns: 1fr; } }
		.bb-stats-chart { position: relative; padding-bottom: 18px; }
		.bb-stats-chart svg { width: 100%; height: 220px; display: block; }
		.bb-stats-chart__scale { position: absolute; top: 18px; left: 0; height: 170px; display: flex; flex-direction: column; justify-content: space-between; font-size: 11px; color: #646970; }
		.bb-stats-chart__labels { position: relative; height: 16px; }
		.bb-stats-chart__labels span { position: absolute; transform: translateX(-50%); font-size: 11px; color: #646970; white-space: nowrap; }
		.bb-stats-hours { display: flex; align-items: flex-end; gap: 3px; height: 110px; margin: 0 0 24px; }
		.bb-stats-hours__col { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; }
		.bb-stats-hours__bar { display: block; width: 100%; background: #0080ff; border-radius: 3px 3px 0 0; opacity: .85; }
		.bb-stats-hours__col:hover .bb-stats-hours__bar { opacity: 1; }
		.bb-stats-hours__tick { position: absolute; bottom: -16px; font-size: 10px; color: #646970; }
		.bb-stats-table__num { text-align: right; width: 80px; }
		.bb-stats-tag { background: #edf4ff; color: #0073aa; border-radius: 3px; font-size: 10px; padding: 1px 5px; margin-left: 6px; vertical-align: 1px; }
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
