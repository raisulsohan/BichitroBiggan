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
 * Drawn the way a reader of charts expects one — a scale up the left, lines
 * across it, and the exact figure under the pointer — but still one inline SVG
 * and about forty lines of script. No charting library is loaded for it.
 *
 * @param array $series Slot => array{hits,visits,label,tip}.
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
	$height = 260;
	$left   = 58;   // room for the scale.
	$right  = 14;
	$top    = 16;
	$bottom = 30;

	$ceiling = bb_stats_nice_ceiling( max( $hits ) );

	$x = function ( $i ) use ( $width, $left, $right, $count ) {
		return $count > 1 ? $left + ( ( $width - $left - $right ) * $i / ( $count - 1 ) ) : ( $width + $left ) / 2;
	};

	$y = function ( $value ) use ( $height, $top, $bottom, $ceiling ) {
		return $height - $bottom - ( ( $height - $top - $bottom ) * $value / $ceiling );
	};

	$points = array();
	$data   = array();

	foreach ( $hits as $i => $value ) {
		$px = round( $x( $i ), 1 );
		$py = round( $y( $value ), 1 );

		$points[] = $px . ',' . $py;
		$data[]   = array(
			'x' => $px,
			'y' => $py,
			'v' => bb_stats_number( $value ),
			'l' => isset( $rows[ $i ]['tip'] ) ? $rows[ $i ]['tip'] : $rows[ $i ]['label'],
		);
	}

	$line = implode( ' ', $points );
	$area = $left . ',' . ( $height - $bottom ) . ' ' . $line . ' ' . round( $x( $count - 1 ), 1 ) . ',' . ( $height - $bottom );

	// Four lines across, including the floor, at round numbers.
	$grid = array();

	for ( $step = 0; $step <= 4; $step++ ) {
		$value  = $ceiling * $step / 4;
		$grid[] = array(
			'y'     => round( $y( $value ), 1 ),
			'label' => bb_stats_number( (int) round( $value ) ),
		);
	}

	// A handful of labels along the bottom, not one per point.
	$every  = max( 1, (int) ceil( $count / 8 ) );
	$labels = array();

	foreach ( $rows as $i => $row ) {
		if ( 0 !== $i % $every && $i !== $count - 1 ) {
			continue;
		}

		$labels[] = array(
			'x'    => round( $x( $i ), 1 ),
			'text' => $row['label'],
		);
	}
	?>
	<div class="bb-stats-chart" data-bb-chart="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
		<svg viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" role="img"
			aria-label="<?php esc_attr_e( 'Reads over time', 'bichitro-biggan' ); ?>">

			<?php foreach ( $grid as $index => $row ) : ?>
				<line x1="<?php echo (int) $left; ?>" y1="<?php echo esc_attr( $row['y'] ); ?>"
					x2="<?php echo (int) ( $width - $right ); ?>" y2="<?php echo esc_attr( $row['y'] ); ?>"
					stroke="<?php echo 0 === $index ? '#c3c4c7' : '#eceef0'; ?>" stroke-width="1" />
				<text x="<?php echo (int) ( $left - 10 ); ?>" y="<?php echo esc_attr( $row['y'] + 4 ); ?>"
					text-anchor="end" font-size="12" fill="#646970"><?php echo esc_html( $row['label'] ); ?></text>
			<?php endforeach; ?>

			<polygon fill="rgba(0,128,255,0.10)" points="<?php echo esc_attr( $area ); ?>" />
			<polyline fill="none" stroke="#0080ff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
				points="<?php echo esc_attr( $line ); ?>" />

			<?php foreach ( $labels as $label ) : ?>
				<text x="<?php echo esc_attr( $label['x'] ); ?>" y="<?php echo (int) ( $height - 8 ); ?>"
					text-anchor="middle" font-size="12" fill="#646970"><?php echo esc_html( $label['text'] ); ?></text>
			<?php endforeach; ?>

			<line class="bb-chart-guide" x1="0" y1="<?php echo (int) $top; ?>" x2="0" y2="<?php echo (int) ( $height - $bottom ); ?>"
				stroke="#8c8f94" stroke-width="1" stroke-dasharray="3 3" opacity="0" />
			<circle class="bb-chart-dot" r="4.5" fill="#0080ff" stroke="#fff" stroke-width="2" opacity="0" />
		</svg>

		<div class="bb-stats-chart__tip" hidden>
			<span class="bb-stats-chart__tip-label"><?php esc_html_e( 'Reads', 'bichitro-biggan' ); ?></span>
			<strong class="bb-stats-chart__tip-value"></strong>
			<span class="bb-stats-chart__tip-when"></span>
		</div>
	</div>

	<script>
	/* The pointer reads the figure off the line: a dotted guide, a dot on the
	   point nearest the pointer, and a small card with the number and the day. */
	( function () {
		var charts = document.querySelectorAll( '[data-bb-chart]' );

		Array.prototype.forEach.call( charts, function ( chart ) {
			var points = JSON.parse( chart.getAttribute( 'data-bb-chart' ) || '[]' );
			if ( ! points.length ) return;

			var svg = chart.querySelector( 'svg' );
			var guide = chart.querySelector( '.bb-chart-guide' );
			var dot = chart.querySelector( '.bb-chart-dot' );
			var tip = chart.querySelector( '.bb-stats-chart__tip' );
			var value = chart.querySelector( '.bb-stats-chart__tip-value' );
			var when = chart.querySelector( '.bb-stats-chart__tip-when' );
			var viewBox = svg.viewBox.baseVal;

			function show( clientX ) {
				var box = svg.getBoundingClientRect();
				if ( ! box.width ) return;

				var inside = ( clientX - box.left ) / box.width * viewBox.width;
				var nearest = 0;

				for ( var i = 1; i < points.length; i++ ) {
					if ( Math.abs( points[ i ].x - inside ) < Math.abs( points[ nearest ].x - inside ) ) nearest = i;
				}

				var point = points[ nearest ];

				guide.setAttribute( 'x1', point.x );
				guide.setAttribute( 'x2', point.x );
				guide.setAttribute( 'opacity', '1' );
				dot.setAttribute( 'cx', point.x );
				dot.setAttribute( 'cy', point.y );
				dot.setAttribute( 'opacity', '1' );

				value.textContent = point.v;
				when.textContent = point.l;
				tip.hidden = false;

				var scale = box.width / viewBox.width;
				var left = point.x * scale;
				var width = tip.offsetWidth;

				tip.style.left = Math.max( 4, Math.min( box.width - width - 4, left - width / 2 ) ) + 'px';
				tip.style.top = Math.max( 0, point.y * scale - tip.offsetHeight - 14 ) + 'px';
			}

			function hide() {
				guide.setAttribute( 'opacity', '0' );
				dot.setAttribute( 'opacity', '0' );
				tip.hidden = true;
			}

			chart.addEventListener( 'mousemove', function ( e ) { show( e.clientX ); } );
			chart.addEventListener( 'mouseleave', hide );
			chart.addEventListener( 'touchstart', function ( e ) { if ( e.touches[0] ) show( e.touches[0].clientX ); }, { passive: true } );
			chart.addEventListener( 'touchmove', function ( e ) { if ( e.touches[0] ) show( e.touches[0].clientX ); }, { passive: true } );
			chart.addEventListener( 'touchend', hide );
		} );
	}() );
	</script>
	<?php
}

/**
 * A round number to put at the top of the scale — 1, 2 or 5 followed by
 * zeroes, so the lines across land on figures a person would choose.
 *
 * @param int $highest The biggest value in the series.
 * @return float
 */
function bb_stats_nice_ceiling( $highest ) {
	$highest = max( 1, (int) $highest );

	if ( $highest <= 4 ) {
		return 4;
	}

	$magnitude = pow( 10, floor( log10( $highest ) ) );
	$steps     = array( 1, 1.2, 1.6, 2, 2.5, 4, 5, 8, 10 );

	foreach ( $steps as $step ) {
		if ( $highest <= $step * $magnitude ) {
			return $step * $magnitude;
		}
	}

	return 10 * $magnitude;
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
 * A country's name, and its flag if the browser can draw one.
 *
 * The flag is the two letters of the code shifted into the regional-indicator
 * block — no image, no font, nothing to load.
 *
 * @param string $code Two-letter country code.
 * @return string
 */
function bb_stats_country_label( $code ) {
	$code = strtoupper( trim( (string) $code ) );

	if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
		return __( 'Unknown', 'bichitro-biggan' );
	}

	$names = array(
		'BD' => 'Bangladesh', 'IN' => 'India', 'PK' => 'Pakistan', 'NP' => 'Nepal', 'LK' => 'Sri Lanka',
		'BT' => 'Bhutan', 'MV' => 'Maldives', 'AF' => 'Afghanistan', 'MM' => 'Myanmar', 'TH' => 'Thailand',
		'SG' => 'Singapore', 'MY' => 'Malaysia', 'ID' => 'Indonesia', 'PH' => 'Philippines', 'VN' => 'Vietnam',
		'CN' => 'China', 'HK' => 'Hong Kong', 'TW' => 'Taiwan', 'JP' => 'Japan', 'KR' => 'South Korea',
		'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar', 'KW' => 'Kuwait',
		'OM' => 'Oman', 'BH' => 'Bahrain', 'IQ' => 'Iraq', 'IR' => 'Iran', 'IL' => 'Israel', 'TR' => 'Türkiye',
		'JO' => 'Jordan', 'LB' => 'Lebanon', 'GB' => 'United Kingdom', 'IE' => 'Ireland', 'FR' => 'France',
		'DE' => 'Germany', 'IT' => 'Italy', 'ES' => 'Spain', 'PT' => 'Portugal', 'NL' => 'Netherlands',
		'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria', 'SE' => 'Sweden', 'NO' => 'Norway',
		'DK' => 'Denmark', 'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czechia', 'GR' => 'Greece',
		'RO' => 'Romania', 'RU' => 'Russia', 'UA' => 'Ukraine', 'US' => 'United States', 'CA' => 'Canada',
		'MX' => 'Mexico', 'BR' => 'Brazil', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
		'EG' => 'Egypt', 'MA' => 'Morocco', 'DZ' => 'Algeria', 'NG' => 'Nigeria', 'KE' => 'Kenya',
		'ZA' => 'South Africa', 'ET' => 'Ethiopia', 'AU' => 'Australia', 'NZ' => 'New Zealand',
	);

	$name = isset( $names[ $code ] ) ? $names[ $code ] : $code;

	if ( ! function_exists( 'mb_chr' ) ) {
		return $name;
	}

	$flag = '';

	foreach ( str_split( $code ) as $letter ) {
		$flag .= mb_chr( 0x1F1E6 + ( ord( $letter ) - 65 ), 'UTF-8' );
	}

	return $flag . ' ' . $name;
}

/**
 * How far down articles were read, as a row of quarters.
 *
 * @param array $depth From bb_stats_depth_summary().
 * @return void
 */
function bb_stats_depth_bars( array $depth ) {
	if ( ! $depth['total'] ) {
		?>
		<p class="bb-stats__empty"><?php esc_html_e( 'Not measured yet — it is recorded as a reader leaves an article.', 'bichitro-biggan' ); ?></p>
		<?php
		return;
	}

	$labels = array(
		100 => __( 'Read to the end', 'bichitro-biggan' ),
		75  => __( 'Three quarters', 'bichitro-biggan' ),
		50  => __( 'Half', 'bichitro-biggan' ),
		25  => __( 'A quarter', 'bichitro-biggan' ),
		0   => __( 'Barely started', 'bichitro-biggan' ),
	);
	?>
	<p class="bb-stats-depth__average">
		<?php
		printf(
			/* translators: %s: average percentage of an article that gets read. */
			esc_html__( 'On average a reader gets %s of the way down.', 'bichitro-biggan' ),
			'<strong>' . esc_html( bb_stats_number( $depth['average'] ) ) . '%</strong>'
		);
		?>
	</p>
	<ul class="bb-stats-bars">
		<?php
		$max = max( $depth['buckets'] );

		foreach ( $labels as $bucket => $label ) {
			bb_stats_bar( $label, $depth['buckets'][ $bucket ], $max, $depth['total'] );
		}
		?>
	</ul>
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
	$hours     = bb_stats_by_hour( $key );
	$countries = bb_stats_grouped( 'country', $key, 12 );
	$depth     = bb_stats_depth_summary( $key );
	$searches  = bb_stats_top_searches( 12 );
	$missing   = bb_stats_not_found( 10 );
	$first     = bb_stats_first_day();
	$now       = bb_stats_pulse( 30 );
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

				<h2><?php esc_html_e( 'How far they read', 'bichitro-biggan' ); ?></h2>
				<p class="bb-stats__hint"><?php esc_html_e( 'Measured as a reader leaves an article — a view says it was opened, this says it was read.', 'bichitro-biggan' ); ?></p>
				<?php bb_stats_depth_bars( $depth ); ?>

				<h2><?php esc_html_e( 'Addresses that led nowhere', 'bichitro-biggan' ); ?></h2>
				<p class="bb-stats__hint"><?php esc_html_e( 'Pages readers asked for and did not get — a broken link somewhere, and the one thing here you can actually fix.', 'bichitro-biggan' ); ?></p>
				<?php if ( empty( $missing ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nobody has hit a missing page. Good.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="widefat striped bb-stats-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Address', 'bichitro-biggan' ); ?></th>
								<th><?php esc_html_e( 'Came from', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Times', 'bichitro-biggan' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $missing as $row ) : ?>
								<tr>
									<td><code><?php echo esc_html( $row['path'] ); ?></code></td>
									<td><?php echo esc_html( $row['from'] ? bb_stats_source_label( $row['from'] ) : '—' ); ?></td>
									<td class="bb-stats-table__num"><?php echo esc_html( bb_stats_number( $row['hits'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
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

				<h2><?php esc_html_e( 'Where in the world', 'bichitro-biggan' ); ?></h2>
				<p class="bb-stats__hint"><?php esc_html_e( 'From the browser\'s own time zone, never from an address — nothing is sent anywhere to work this out.', 'bichitro-biggan' ); ?></p>
				<?php if ( empty( $countries ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing to show yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max = (int) $countries[0]['hits'];
						foreach ( $countries as $row ) {
							bb_stats_bar( bb_stats_country_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
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
		.bb-stats-chart { position: relative; }
		.bb-stats-chart svg { width: 100%; height: auto; display: block; overflow: visible; }
		.bb-stats-chart__tip { position: absolute; z-index: 5; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.12); padding: 8px 12px; pointer-events: none; white-space: nowrap; }
		.bb-stats-chart__tip-label { display: block; font-size: 11px; color: #646970; }
		.bb-stats-chart__tip-value { display: block; font-size: 18px; line-height: 1.2; color: #1d2327; }
		.bb-stats-chart__tip-when { display: block; font-size: 11px; color: #646970; }
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
		.bb-stats-depth__average { margin: 0 0 10px; color: #1d2327; }
		.bb-stats-table code { font-size: 12px; }
	</style>
	<?php
}
