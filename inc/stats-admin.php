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
	</span>
	<span class="bb-stats-card__against"><?php esc_html_e( 'vs the period before', 'bichitro-biggan' ); ?></span>
	<?php
}

/**
 * The line: reads per hour, or per day.
 *
 * The plot is a fixed height whatever the window is doing, so the screen is not
 * half chart on a wide monitor. That means the drawing stretches horizontally
 * and nothing that must keep its shape can live inside it: the numbers, the
 * dates, the guide and the dot are ordinary HTML laid over the top, and the SVG
 * holds only the lines — which stretch without minding, their thickness pinned
 * by vector-effect.
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

	$ceiling = bb_stats_nice_ceiling( max( $hits ) );

	// Everything is in percentages of the plot, which makes the overlay trivial.
	$x = function ( $i ) use ( $count ) {
		return $count > 1 ? round( 100 * $i / ( $count - 1 ), 3 ) : 50;
	};

	$y = function ( $value ) use ( $ceiling ) {
		return round( 100 - ( 100 * $value / $ceiling ), 3 );
	};

	$points = array();
	$data   = array();

	foreach ( $hits as $i => $value ) {
		$px = $x( $i );
		$py = $y( $value );

		$points[] = $px . ',' . $py;
		$data[]   = array(
			'x' => $px,
			'y' => $py,
			'v' => bb_stats_number( $value ),
			'l' => isset( $rows[ $i ]['tip'] ) ? $rows[ $i ]['tip'] : $rows[ $i ]['label'],
		);
	}

	$line = implode( ' ', $points );
	$area = '0,100 ' . $line . ' 100,100';

	// Four lines across, including the floor, at round numbers.
	$grid = array();

	for ( $step = 4; $step >= 0; $step-- ) {
		$value  = $ceiling * $step / 4;
		$grid[] = array(
			'y'     => $y( $value ),
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
			'x'    => $x( $i ),
			'text' => $row['label'],
		);
	}
	?>
	<div class="bb-stats-chart" data-bb-chart="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
		<div class="bb-stats-chart__scale">
			<?php foreach ( $grid as $row ) : ?>
				<span style="top:<?php echo esc_attr( $row['y'] ); ?>%"><?php echo esc_html( $row['label'] ); ?></span>
			<?php endforeach; ?>
		</div>

		<div class="bb-stats-chart__plot">
			<svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img"
				aria-label="<?php esc_attr_e( 'Reads over time', 'bichitro-biggan' ); ?>">
				<?php foreach ( $grid as $index => $row ) : ?>
					<line x1="0" y1="<?php echo esc_attr( $row['y'] ); ?>" x2="100" y2="<?php echo esc_attr( $row['y'] ); ?>"
						stroke="<?php echo ( count( $grid ) - 1 ) === $index ? '#c3c4c7' : '#eceef0'; ?>"
						stroke-width="1" vector-effect="non-scaling-stroke" />
				<?php endforeach; ?>

				<polygon fill="rgba(0,128,255,0.10)" points="<?php echo esc_attr( $area ); ?>" />
				<polyline fill="none" stroke="#0080ff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
					vector-effect="non-scaling-stroke" points="<?php echo esc_attr( $line ); ?>" />
			</svg>

			<span class="bb-stats-chart__guide" hidden></span>
			<span class="bb-stats-chart__dot" hidden></span>
		</div>

		<div class="bb-stats-chart__labels">
			<?php foreach ( $labels as $label ) : ?>
				<span style="left:<?php echo esc_attr( $label['x'] ); ?>%"><?php echo esc_html( $label['text'] ); ?></span>
			<?php endforeach; ?>
		</div>

		<div class="bb-stats-chart__tip" hidden>
			<span class="bb-stats-chart__tip-label"><?php esc_html_e( 'Reads', 'bichitro-biggan' ); ?></span>
			<strong class="bb-stats-chart__tip-value"></strong>
			<span class="bb-stats-chart__tip-when"></span>
		</div>
	</div>

	<script>
	/* The pointer reads the figure off the line: a dotted guide, a dot on the
	   nearest point, and a small card with the number and when it was. */
	( function () {
		var charts = document.querySelectorAll( '[data-bb-chart]' );

		Array.prototype.forEach.call( charts, function ( chart ) {
			var points = JSON.parse( chart.getAttribute( 'data-bb-chart' ) || '[]' );
			if ( ! points.length ) return;

			var plot = chart.querySelector( '.bb-stats-chart__plot' );
			var guide = chart.querySelector( '.bb-stats-chart__guide' );
			var dot = chart.querySelector( '.bb-stats-chart__dot' );
			var tip = chart.querySelector( '.bb-stats-chart__tip' );
			var value = chart.querySelector( '.bb-stats-chart__tip-value' );
			var when = chart.querySelector( '.bb-stats-chart__tip-when' );

			function show( clientX ) {
				var box = plot.getBoundingClientRect();
				if ( ! box.width ) return;

				var inside = ( clientX - box.left ) / box.width * 100;
				var nearest = 0;

				for ( var i = 1; i < points.length; i++ ) {
					if ( Math.abs( points[ i ].x - inside ) < Math.abs( points[ nearest ].x - inside ) ) nearest = i;
				}

				var point = points[ nearest ];

				guide.style.left = point.x + '%';
				dot.style.left = point.x + '%';
				dot.style.top = point.y + '%';
				guide.hidden = false;
				dot.hidden = false;

				value.textContent = point.v;
				when.textContent = point.l;
				tip.hidden = false;

				var left = ( point.x / 100 ) * box.width;
				var width = tip.offsetWidth;

				tip.style.left = Math.max( 0, Math.min( box.width - width, left - width / 2 ) ) + 'px';
				tip.style.top = Math.max( -8, ( point.y / 100 ) * box.height - tip.offsetHeight - 12 ) + 'px';
			}

			function hide() {
				guide.hidden = true;
				dot.hidden = true;
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

	// 14 reads as "2 pm" here, the same as everywhere else on the screen.
	$clock = function ( $hour ) {
		$hour = (int) $hour;
		$half = ( 0 === $hour % 12 ) ? 12 : $hour % 12;

		return $half . ( $hour < 12 ? ' am' : ' pm' );
	};
	?>
	<div class="bb-stats-hours">
		<?php foreach ( $hours as $hour => $value ) : ?>
			<?php
			$title = sprintf(
				/* translators: 1: hour of the day, 2: how many reads. */
				__( '%1$s — %2$s reads', 'bichitro-biggan' ),
				$clock( $hour ),
				bb_stats_number( $value )
			);
			?>
			<div class="bb-stats-hours__col" title="<?php echo esc_attr( $title ); ?>">
				<span class="bb-stats-hours__bar" style="height:<?php echo (int) max( 2, round( 100 * $value / $top ) ); ?>%"></span>
				<?php if ( 0 === $hour % 6 ) : ?>
					<span class="bb-stats-hours__tick"><?php echo esc_html( $clock( $hour ) ); ?></span>
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
 * A stretch of time, said the way a person would.
 *
 * @param int $seconds Seconds.
 * @return string
 */
function bb_stats_duration( $seconds ) {
	$seconds = max( 0, (int) $seconds );

	if ( $seconds < 60 ) {
		/* translators: %s: a number of seconds. */
		return sprintf( __( '%ss', 'bichitro-biggan' ), bb_stats_number( $seconds ) );
	}

	$minutes = (int) floor( $seconds / 60 );
	$rest    = $seconds % 60;

	/* translators: 1: minutes, 2: seconds. */
	return sprintf( __( '%1$sm %2$ss', 'bichitro-biggan' ), bb_stats_number( $minutes ), bb_stats_number( $rest ) );
}

/**
 * The name of a page in a list, whether it is an article or everything else.
 *
 * @param int $post_id Post, or 0.
 * @return string
 */
function bb_stats_page_name( $post_id ) {
	if ( ! $post_id ) {
		return __( 'Home page and archives', 'bichitro-biggan' );
	}

	$title = get_the_title( $post_id );

	return $title ? $title : sprintf( '#%d', (int) $post_id );
}

/**
 * One panel on the screen: a heading, a line explaining it, and whatever the
 * section shows.
 *
 * Everything used to sit inside two enormous boxes, headings and tables and
 * notes running into one another with nothing between them. A section is its
 * own card now, so the space around a thing belongs to the thing.
 *
 * @param string $title Heading.
 * @param string $hint  A line under it, or ''.
 * @param bool   $wide  Span the whole width rather than one column.
 * @return void
 */
function bb_stats_section_open( $title, $hint = '', $wide = false ) {
	?>
	<section class="bb-stats-section<?php echo $wide ? ' bb-stats-section--wide' : ''; ?>">
		<div class="bb-stats-section__head">
			<h2 class="bb-stats-section__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $hint ) : ?>
				<p class="bb-stats-section__hint"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
		</div>
		<div class="bb-stats-section__body">
	<?php
}

/**
 * Close what bb_stats_section_open() opened.
 *
 * @return void
 */
function bb_stats_section_close() {
	?>
		</div>
	</section>
	<?php
}

/**
 * The screen.
 */
function bb_stats_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bichitro-biggan' ) );
	}

	// Forgetting the addresses that led nowhere, once they have been dealt with.
	if ( isset( $_POST['bb_stats_clear_404'] ) && check_admin_referer( 'bb_stats_clear_404' ) ) {
		bb_stats_clear_404();
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'The list of missing addresses has been emptied.', 'bichitro-biggan' )
			. '</p></div>';
	}

	$ranges = bb_stats_ranges();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing how far back to look changes nothing.
	$key = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '24h';
	$key = isset( $ranges[ $key ] ) ? $key : '24h';

	$totals    = bb_stats_totals( $key );
	$before    = bb_stats_totals( $key, true );
	$series    = bb_stats_series( $key );
	$top       = bb_stats_top_posts( $key, 15 );
	$sources   = bb_stats_grouped( 'source', $key );
	$devices   = bb_stats_grouped( 'device', $key );
	$langs     = bb_stats_grouped( 'lang', $key );
	$hours     = bb_stats_by_hour( $key );
	$weekdays  = bb_stats_by_weekday( $key );
	$countries = bb_stats_grouped( 'country', $key, 12 );
	$depth     = bb_stats_depth_summary( $key );
	$per_post  = bb_stats_depth_by_post( $key );
	$trending  = bb_stats_trending( $key, 8 );
	$entries   = bb_stats_entry_pages( $key, 8 );
	$outbound  = bb_stats_events( 'out', $key, 10 );
	$shares    = bb_stats_events( 'share', $key, 8 );
	$saves     = bb_stats_events( 'save', $key, 8 );
	$searches  = bb_stats_top_searches( 12 );
	$missed    = bb_stats_missed_searches( 12 );
	$missing   = bb_stats_not_found( 10 );
	$first     = bb_stats_first_day();
	$now       = bb_stats_pulse( 30 );
	$per_visit = $totals['visits'] > 0 ? round( $totals['hits'] / $totals['visits'], 1 ) : 0;
	?>
	<div class="wrap bb-stats">
		<h1 class="bb-stats__title"><?php esc_html_e( 'Statistics', 'bichitro-biggan' ); ?></h1>

		<p class="bb-stats__lede">
			<?php esc_html_e( 'The site\'s own count — no outside service, no account, no permission that can be withdrawn. Nothing that identifies a reader is stored, and visits by the people who run the site are left out.', 'bichitro-biggan' ); ?>
		</p>

		<nav class="bb-stats__range" aria-label="<?php esc_attr_e( 'How far back to look', 'bichitro-biggan' ); ?>">
			<?php foreach ( $ranges as $value => $label ) : ?>
				<a class="bb-stats__range-tab<?php echo ( $value === $key ) ? ' is-on' : ''; ?>"
					<?php echo ( $value === $key ) ? 'aria-current="page"' : ''; ?>
					href="<?php echo esc_url( admin_url( 'admin.php?page=bb-stats&range=' . rawurlencode( $value ) ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

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
				<span class="bb-stats-card__note">
					<?php
					printf(
						/* translators: %s: average number of pages read in one visit. */
						esc_html__( '%s pages a visit', 'bichitro-biggan' ),
						esc_html( number_format_i18n( $per_visit, 1 ) )
					);
					?>
				</span>
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

		<div class="bb-stats__grid">

			<?php
			bb_stats_section_open(
				'24h' === $key ? __( 'Reads by the hour', 'bichitro-biggan' ) : __( 'Reads per day', 'bichitro-biggan' ),
				'',
				true
			);
			bb_stats_chart( $series );
			bb_stats_section_close();
			?>

			<?php bb_stats_section_open( __( 'Most read', 'bichitro-biggan' ), '', true ); ?>
				<?php if ( empty( $top ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'No article was read in this period.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="bb-stats-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Article', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Bengali', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num">EN</th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Total', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Read', 'bichitro-biggan' ); ?></th>
								<th class="bb-stats-table__num"><?php esc_html_e( 'Time', 'bichitro-biggan' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $top as $row ) :
								$reading = isset( $per_post[ $row['post_id'] ] ) ? $per_post[ $row['post_id'] ] : null;
								?>
								<tr>
									<td>
										<a href="<?php echo esc_url( get_permalink( $row['post_id'] ) ); ?>" target="_blank" rel="noopener">
											<?php echo esc_html( get_the_title( $row['post_id'] ) ); ?>
										</a>
									</td>
									<td class="bb-stats-table__num"><?php echo esc_html( bb_stats_number( $row['bn'] ) ); ?></td>
									<td class="bb-stats-table__num"><?php echo esc_html( $row['en'] ? bb_stats_number( $row['en'] ) : '—' ); ?></td>
									<td class="bb-stats-table__num"><strong><?php echo esc_html( bb_stats_number( $row['hits'] ) ); ?></strong></td>
									<td class="bb-stats-table__num">
										<?php echo $reading ? esc_html( bb_stats_number( $reading['depth'] ) . '%' ) : '—'; ?>
									</td>
									<td class="bb-stats-table__num">
										<?php echo $reading && $reading['seconds'] ? esc_html( bb_stats_duration( $reading['seconds'] ) ) : '—'; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="bb-stats__foot"><?php esc_html_e( '“Read” is how far down the article a reader got on average, “Time” how long they stayed with it.', 'bichitro-biggan' ); ?></p>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'Climbing', 'bichitro-biggan' ),
				__( 'Read more in this period than in the one before it. Articles with only a handful of reads are left out — with small numbers every rise looks enormous.', 'bichitro-biggan' )
			);
			?>
				<?php if ( empty( $trending ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing is climbing in this period.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="bb-stats-table">
						<tbody>
							<?php foreach ( $trending as $row ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( get_permalink( $row['post_id'] ) ); ?>" target="_blank" rel="noopener">
											<?php echo esc_html( get_the_title( $row['post_id'] ) ); ?>
										</a>
									</td>
									<td class="bb-stats-table__num">
										<span class="bb-stats-change bb-stats-change--up">▲ <?php echo esc_html( bb_stats_number( $row['change'] ) ); ?>%</span>
									</td>
									<td class="bb-stats-table__num">
										<?php echo esc_html( bb_stats_number( $row['before'] ) . ' → ' . bb_stats_number( $row['now'] ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'Where visits begin', 'bichitro-biggan' ),
				__( 'The first page of a visit — where readers come in, rather than what they read next.', 'bichitro-biggan' )
			);
			?>
				<?php if ( empty( $entries ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing to show yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max = (int) $entries[0]['visits'];
						foreach ( $entries as $row ) {
							bb_stats_bar( bb_stats_page_name( $row['post_id'] ), $row['visits'], $max, $totals['visits'] );
						}
						?>
					</ul>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'When they read', 'bichitro-biggan' ),
				__( 'Reads by hour of the day across this whole period, on the site\'s own clock.', 'bichitro-biggan' ),
				true
			);
			bb_stats_hour_pattern( $hours );
			bb_stats_section_close();
			?>

			<?php
			bb_stats_section_open(
				__( 'Which day', 'bichitro-biggan' ),
				__( 'The days of the week readers turn up on — worth knowing before deciding when to publish.', 'bichitro-biggan' )
			);

			$weekday_names = array(
				__( 'Sunday', 'bichitro-biggan' ),
				__( 'Monday', 'bichitro-biggan' ),
				__( 'Tuesday', 'bichitro-biggan' ),
				__( 'Wednesday', 'bichitro-biggan' ),
				__( 'Thursday', 'bichitro-biggan' ),
				__( 'Friday', 'bichitro-biggan' ),
				__( 'Saturday', 'bichitro-biggan' ),
			);
			$weekday_top   = max( 1, max( $weekdays ) );
			?>
				<ul class="bb-stats-bars">
					<?php
					foreach ( $weekdays as $index => $value ) {
						bb_stats_bar( $weekday_names[ $index ], $value, $weekday_top, max( 1, array_sum( $weekdays ) ) );
					}
					?>
				</ul>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'How far they read', 'bichitro-biggan' ),
				__( 'Measured as a reader leaves an article — a view says it was opened, this says it was read.', 'bichitro-biggan' )
			);
			bb_stats_depth_bars( $depth );
			bb_stats_section_close();
			?>

			<?php bb_stats_section_open( __( 'Where readers come from', 'bichitro-biggan' ) ); ?>
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
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'Where in the world', 'bichitro-biggan' ),
				__( 'From the browser\'s own time zone, never from an address — nothing is sent anywhere to work this out.', 'bichitro-biggan' )
			);
			?>
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
			<?php bb_stats_section_close(); ?>

			<?php bb_stats_section_open( __( 'What they read on', 'bichitro-biggan' ) ); ?>
				<?php if ( empty( $devices ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing to show yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max = (int) $devices[0]['hits'];
						foreach ( $devices as $row ) {
							bb_stats_bar( bb_stats_plain_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
						}
						?>
					</ul>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php bb_stats_section_open( __( 'Which edition', 'bichitro-biggan' ) ); ?>
				<?php if ( empty( $langs ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing to show yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max = (int) $langs[0]['hits'];
						foreach ( $langs as $row ) {
							bb_stats_bar( bb_stats_plain_label( $row['label'] ), $row['hits'], $max, $totals['hits'] );
						}
						?>
					</ul>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'Searches that found nothing', 'bichitro-biggan' ),
				__( 'Readers came wanting these and the site did not have them. The best list there is of what to write next.', 'bichitro-biggan' )
			);
			?>
				<?php if ( empty( $missed ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Every search so far has found something.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="bb-stats-table">
						<tbody>
							<?php foreach ( $missed as $row ) : ?>
								<tr>
									<td>
										<?php echo esc_html( $row['term'] ); ?>
										<?php if ( 'en' === $row['lang'] ) : ?>
											<span class="bb-stats-tag">EN</span>
										<?php endif; ?>
									</td>
									<td class="bb-stats-table__num"><?php echo esc_html( bb_stats_number( $row['miss'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'What they searched for', 'bichitro-biggan' ),
				__( 'Typed into the site\'s own search box — all time, most asked first.', 'bichitro-biggan' )
			);
			?>
				<?php if ( empty( $searches ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nobody has searched yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="bb-stats-table">
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
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'Links they followed out', 'bichitro-biggan' ),
				__( 'Sources and other links inside articles that readers actually clicked.', 'bichitro-biggan' )
			);
			?>
				<?php if ( empty( $outbound ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'No link out of an article has been followed yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max   = (int) $outbound[0]['hits'];
						$total = array_sum( wp_list_pluck( $outbound, 'hits' ) );
						foreach ( $outbound as $row ) {
							bb_stats_bar( $row['label'], $row['hits'], $max, $total );
						}
						?>
					</ul>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php bb_stats_section_open( __( 'How they shared it', 'bichitro-biggan' ) ); ?>
				<?php if ( empty( $shares ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nothing has been shared from the buttons yet.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max   = (int) $shares[0]['hits'];
						$total = array_sum( wp_list_pluck( $shares, 'hits' ) );
						foreach ( $shares as $row ) {
							bb_stats_bar( bb_stats_source_label( $row['label'] ), $row['hits'], $max, $total );
						}
						?>
					</ul>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php bb_stats_section_open( __( 'Saved to read later', 'bichitro-biggan' ) ); ?>
				<?php if ( empty( $saves ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nobody has saved an article in this period.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<ul class="bb-stats-bars">
						<?php
						$max   = (int) $saves[0]['hits'];
						$total = array_sum( wp_list_pluck( $saves, 'hits' ) );
						foreach ( $saves as $row ) {
							bb_stats_bar( bb_stats_page_name( $row['post_id'] ), $row['hits'], $max, $total );
						}
						?>
					</ul>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

			<?php
			bb_stats_section_open(
				__( 'Addresses that led nowhere', 'bichitro-biggan' ),
				__( 'Pages readers asked for and did not get — a broken link somewhere, and the one thing here you can actually fix. Scanners poking at /graphql and the like are left out.', 'bichitro-biggan' ),
				true
			);
			?>
				<?php if ( empty( $missing ) ) : ?>
					<p class="bb-stats__empty"><?php esc_html_e( 'Nobody has hit a missing page. Good.', 'bichitro-biggan' ); ?></p>
				<?php else : ?>
					<table class="bb-stats-table">
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

					<form method="post" class="bb-stats-clear">
						<?php wp_nonce_field( 'bb_stats_clear_404' ); ?>
						<button type="submit" name="bb_stats_clear_404" value="1" class="button button-secondary">
							<?php esc_html_e( 'Empty this list', 'bichitro-biggan' ); ?>
						</button>
						<span class="bb-stats__foot"><?php esc_html_e( 'Once the broken links are fixed, clear it and see what turns up next.', 'bichitro-biggan' ); ?></span>
					</form>
				<?php endif; ?>
			<?php bb_stats_section_close(); ?>

		</div>
	</div>

	<style>
		/* The screen's own palette, so a colour is named once and not guessed at
		   again fifteen declarations later. */
		.bb-stats {
			--bb-ink: #0f1419;
			--bb-body: #3c434a;
			--bb-mute: #6a7581;
			--bb-line: #e3e6ea;
			--bb-hair: #eef0f3;
			--bb-soft: #f7f9fb;
			--bb-accent: #0080ff;
			--bb-accent-soft: #e9f3ff;
			--bb-up: #0a7c42;
			--bb-up-soft: #e8f7ee;
			--bb-down: #b83b36;
			--bb-down-soft: #fdeceb;
			--bb-round: 14px;
			--bb-shadow: 0 1px 2px rgba(15,20,25,.05), 0 10px 26px -20px rgba(15,20,25,.45);
		}

		.bb-stats .bb-stats__title { font-size: 23px; font-weight: 600; letter-spacing: -.01em; color: var(--bb-ink); margin: 0 0 4px; padding: 0; }
		.bb-stats__lede { max-width: 820px; margin: 0 0 16px; color: var(--bb-mute); font-size: 13px; line-height: 1.6; }

		/* One control, five settings — a segmented switch rather than five tabs
		   welded to the top of a box that is no longer there. */
		.bb-stats__range { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px; margin: 0 0 22px; background: #eef0f3; border-radius: 999px; }
		.bb-stats__range-tab { padding: 6px 15px; border-radius: 999px; font-size: 13px; line-height: 1.4; color: var(--bb-mute); text-decoration: none; transition: background .15s, color .15s; }
		.bb-stats__range-tab:hover { color: var(--bb-ink); }
		.bb-stats__range-tab:focus { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--bb-accent); outline: 0; }
		.bb-stats__range-tab.is-on { background: #fff; color: var(--bb-ink); font-weight: 600; box-shadow: 0 1px 2px rgba(15,20,25,.12); }

		.bb-stats__cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin: 0 0 20px; }
		.bb-stats-card { background: #fff; border: 1px solid var(--bb-line); border-radius: var(--bb-round); box-shadow: var(--bb-shadow); padding: 16px 18px 17px; }
		.bb-stats-card--now { border-color: #bfe6cb; background: linear-gradient(180deg, #f3fbf6 0%, #fff 60%); }
		.bb-stats-card__label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--bb-mute); }
		.bb-stats-card__value { display: block; font-size: 32px; font-weight: 600; line-height: 1.15; letter-spacing: -.02em; margin: 8px 0 6px; color: var(--bb-ink); font-variant-numeric: tabular-nums; }
		.bb-stats-card__value--small { font-size: 17px; font-weight: 600; letter-spacing: 0; }
		.bb-stats-card__note { display: block; font-size: 12px; line-height: 1.5; color: var(--bb-mute); }
		.bb-stats-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #00a32a; margin-right: 6px; vertical-align: 1px; box-shadow: 0 0 0 3px rgba(0,163,42,.15); }

		/* The rise or fall, as a chip rather than a line of text. */
		.bb-stats-change { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; background: #f0f2f4; color: var(--bb-mute); font-size: 11px; font-weight: 600; line-height: 1.7; white-space: nowrap; font-variant-numeric: tabular-nums; }
		.bb-stats-change--up { background: var(--bb-up-soft); color: var(--bb-up); }
		.bb-stats-change--down { background: var(--bb-down-soft); color: var(--bb-down); }
		.bb-stats-card__against { display: block; margin-top: 6px; font-size: 11px; color: var(--bb-mute); }

		/* Each section is a card of its own, and a card is two columns wide when
		   what is inside it needs the room. */
		.bb-stats__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; align-items: start; }
		.bb-stats-section { background: #fff; border: 1px solid var(--bb-line); border-radius: var(--bb-round); box-shadow: var(--bb-shadow); padding: 18px 20px 20px; min-width: 0; }
		.bb-stats-section--wide { grid-column: 1 / -1; }
		@media (max-width: 1100px) { .bb-stats__grid { grid-template-columns: 1fr; } }

		.bb-stats-section__head { margin: 0 0 14px; }
		.bb-stats .bb-stats-section__title { margin: 0; padding: 0; font-size: 15px; font-weight: 600; line-height: 1.3; color: var(--bb-ink); }
		.bb-stats-section__hint { margin: 5px 0 0; font-size: 12px; line-height: 1.55; color: var(--bb-mute); }
		.bb-stats-section__body > :first-child { margin-top: 0; }
		.bb-stats-section__body > :last-child { margin-bottom: 0; }

		/* A note that belongs under a thing rather than over it — the old one had
		   a negative top margin, which glued it to the table above. */
		.bb-stats__foot { margin: 12px 0 0; font-size: 12px; line-height: 1.55; color: var(--bb-mute); }
		.bb-stats__empty { margin: 0; color: var(--bb-mute); font-size: 13px; }

		.bb-stats-chart { position: relative; padding: 4px 0 22px 46px; }
		.bb-stats-chart__plot { position: relative; height: 180px; }
		.bb-stats-chart__plot svg { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
		.bb-stats-chart__scale { position: absolute; top: 4px; left: 0; width: 40px; height: 180px; }
		.bb-stats-chart__scale span { position: absolute; right: 0; transform: translateY(-50%); font-size: 11px; color: var(--bb-mute); white-space: nowrap; font-variant-numeric: tabular-nums; }
		.bb-stats-chart__labels { position: relative; height: 18px; margin-top: 8px; }
		.bb-stats-chart__labels span { position: absolute; transform: translateX(-50%); font-size: 11px; color: var(--bb-mute); white-space: nowrap; }
		.bb-stats-chart__guide { position: absolute; top: 0; bottom: 0; width: 0; border-left: 1px dashed #b5bbc2; pointer-events: none; }
		.bb-stats-chart__dot { position: absolute; width: 9px; height: 9px; margin: -4.5px 0 0 -4.5px; border-radius: 50%; background: var(--bb-accent); box-shadow: 0 0 0 3px #fff, 0 0 0 5px rgba(0,128,255,.2); pointer-events: none; }
		.bb-stats-chart__tip { position: absolute; z-index: 5; background: var(--bb-ink); border-radius: 10px; box-shadow: 0 8px 24px rgba(15,20,25,.22); padding: 8px 12px; pointer-events: none; white-space: nowrap; }
		.bb-stats-chart__tip-label { display: block; font-size: 11px; color: #a7b0b9; }
		.bb-stats-chart__tip-value { display: block; font-size: 17px; font-weight: 600; line-height: 1.3; color: #fff; font-variant-numeric: tabular-nums; }
		.bb-stats-chart__tip-when { display: block; font-size: 11px; color: #a7b0b9; }
		@media (max-width: 782px) { .bb-stats-chart__plot { height: 150px; } .bb-stats-chart__scale { height: 150px; } }

		.bb-stats-hours { display: flex; align-items: flex-end; gap: 4px; height: 120px; margin: 0 0 22px; }
		.bb-stats-hours__col { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; }
		.bb-stats-hours__bar { display: block; width: 100%; background: linear-gradient(180deg, #4da6ff 0%, var(--bb-accent) 100%); border-radius: 4px 4px 2px 2px; transition: filter .15s; }
		.bb-stats-hours__col:hover .bb-stats-hours__bar { filter: brightness(.9); }
		.bb-stats-hours__tick { position: absolute; bottom: -18px; font-size: 10px; color: var(--bb-mute); white-space: nowrap; }

		/* The tables are the theme's own now, not WordPress's list tables with a
		   class fighting them — which is what pushed the figures out of line. */
		.bb-stats-table { width: 100%; border-collapse: collapse; font-size: 13px; }
		.bb-stats-table th { text-align: left; padding: 0 12px 9px 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--bb-mute); border-bottom: 1px solid var(--bb-line); }
		.bb-stats-table td { padding: 10px 12px 10px 0; border-bottom: 1px solid var(--bb-hair); color: var(--bb-body); vertical-align: middle; }
		.bb-stats-table tbody tr:last-child td { border-bottom: 0; }
		.bb-stats-table tbody tr:hover td { background: var(--bb-soft); }
		.bb-stats-table th:last-child, .bb-stats-table td:last-child { padding-right: 0; }
		.bb-stats-table th.bb-stats-table__num, .bb-stats-table td.bb-stats-table__num { text-align: right; width: 78px; font-variant-numeric: tabular-nums; }
		.bb-stats-table td strong { color: var(--bb-ink); font-weight: 600; }
		.bb-stats-table a { color: var(--bb-ink); font-weight: 500; text-decoration: none; }
		.bb-stats-table a:hover { color: var(--bb-accent); text-decoration: underline; }
		.bb-stats-table code { font-size: 12px; background: var(--bb-soft); border-radius: 4px; padding: 2px 6px; color: var(--bb-body); }
		.bb-stats-tag { display: inline-block; background: var(--bb-accent-soft); color: #0067cc; border-radius: 4px; font-size: 10px; font-weight: 600; padding: 1px 5px; margin-left: 6px; vertical-align: 1px; }

		.bb-stats-bars { margin: 0; padding: 0; list-style: none; }
		.bb-stats-bar { display: grid; grid-template-columns: minmax(0, 1fr) 110px 86px; align-items: center; gap: 12px; margin: 0 0 10px; font-size: 13px; }
		.bb-stats-bar:last-child { margin-bottom: 0; }
		.bb-stats-bar__label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--bb-body); }
		.bb-stats-bar__track { background: #eef0f3; border-radius: 999px; height: 8px; overflow: hidden; }
		.bb-stats-bar__fill { display: block; height: 8px; background: linear-gradient(90deg, var(--bb-accent) 0%, #5cb0ff 100%); border-radius: 999px; }
		.bb-stats-bar__value { text-align: right; font-variant-numeric: tabular-nums; color: var(--bb-ink); font-weight: 500; }
		.bb-stats-bar__value small { color: var(--bb-mute); font-weight: 400; margin-left: 5px; }
		@media (max-width: 600px) { .bb-stats-bar { grid-template-columns: minmax(0, 1fr) 70px; } .bb-stats-bar__track { display: none; } }

		.bb-stats-depth__average { margin: 0 0 12px; font-size: 13px; color: var(--bb-body); }
		.bb-stats-clear { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 16px 0 0; padding-top: 14px; border-top: 1px solid var(--bb-hair); }
		.bb-stats-clear .bb-stats__foot { margin: 0; }
	</style>
	<?php
}

/* -------------------------------------------------------------------------
 * On the dashboard's own front page
 * ---------------------------------------------------------------------- */

/**
 * A small panel on the WordPress dashboard: today at a glance, and the way
 * through to the whole screen.
 */
function bb_stats_dashboard_widget() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_add_dashboard_widget( 'bb_stats_today', __( 'Statistics — today', 'bichitro-biggan' ), 'bb_stats_dashboard_panel' );
}
add_action( 'wp_dashboard_setup', 'bb_stats_dashboard_widget' );

/**
 * What the widget shows.
 */
function bb_stats_dashboard_panel() {
	$totals = bb_stats_totals( '24h' );
	$before = bb_stats_totals( '24h', true );
	$top    = bb_stats_top_posts( '24h', 3 );
	$now    = bb_stats_pulse( 30 );
	?>
	<div class="bb-dash">
		<div class="bb-dash__figures">
			<div class="bb-dash__figure">
				<strong><span class="bb-dash__dot" aria-hidden="true"></span><?php echo esc_html( bb_stats_number( $now ) ); ?></strong>
				<span><?php esc_html_e( 'right now', 'bichitro-biggan' ); ?></span>
			</div>
			<div class="bb-dash__figure">
				<strong><?php echo esc_html( bb_stats_number( $totals['hits'] ) ); ?></strong>
				<span><?php esc_html_e( 'reads today', 'bichitro-biggan' ); ?></span>
			</div>
			<div class="bb-dash__figure">
				<strong><?php echo esc_html( bb_stats_number( $totals['visits'] ) ); ?></strong>
				<span><?php esc_html_e( 'visits', 'bichitro-biggan' ); ?></span>
			</div>
		</div>

		<p class="bb-dash__change"><?php bb_stats_change( $totals['hits'], $before['hits'] ); ?></p>

		<?php if ( ! empty( $top ) ) : ?>
			<ul class="bb-dash__top">
				<?php foreach ( $top as $row ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $row['post_id'] ) ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( get_the_title( $row['post_id'] ) ); ?>
						</a>
						<span><?php echo esc_html( bb_stats_number( $row['hits'] ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="bb-dash__empty"><?php esc_html_e( 'No article has been read yet today.', 'bichitro-biggan' ); ?></p>
		<?php endif; ?>

		<p class="bb-dash__more">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bb-stats' ) ); ?>">
				<?php esc_html_e( 'The whole picture', 'bichitro-biggan' ); ?> &rarr;
			</a>
		</p>
	</div>

	<style>
		.bb-dash__figures { display: flex; gap: 26px; margin: 0 0 8px; }
		.bb-dash__figure strong { display: block; font-size: 26px; font-weight: 600; line-height: 1.15; letter-spacing: -.02em; color: #0f1419; font-variant-numeric: tabular-nums; }
		.bb-dash__figure span { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #6a7581; }
		.bb-dash__dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #00a32a; margin-right: 7px; vertical-align: 4px; box-shadow: 0 0 0 3px rgba(0,163,42,.15); }
		/* The chip has to be described here as well — the statistics screen's own
		   stylesheet is not on the dashboard. */
		.bb-dash .bb-stats-change { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; background: #f0f2f4; color: #6a7581; font-size: 11px; font-weight: 600; font-variant-numeric: tabular-nums; }
		.bb-dash .bb-stats-change--up { background: #e8f7ee; color: #0a7c42; }
		.bb-dash .bb-stats-change--down { background: #fdeceb; color: #b83b36; }
		.bb-dash .bb-stats-card__against { font-size: 11px; color: #6a7581; margin-left: 6px; }
		.bb-dash__change { margin: 0 0 14px; }
		.bb-dash__top { margin: 0 0 12px; padding: 0; list-style: none; border-top: 1px solid #eef0f3; }
		.bb-dash__top li { display: flex; justify-content: space-between; gap: 12px; margin: 0; padding: 8px 0; border-bottom: 1px solid #eef0f3; font-size: 13px; }
		.bb-dash__top a { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; color: #0f1419; }
		.bb-dash__top a:hover { color: #0080ff; }
		.bb-dash__top span { color: #6a7581; font-variant-numeric: tabular-nums; }
		.bb-dash__empty { color: #6a7581; }
		.bb-dash__more { margin: 0; }
		.bb-dash__more a { text-decoration: none; font-weight: 500; }
	</style>
	<?php
}
