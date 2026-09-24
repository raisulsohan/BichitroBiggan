<?php
/**
 * Tools → Convert pictures to WebP.
 *
 * The work is done a few attachments at a time, from the browser, because a
 * library of a few thousand files will not finish inside one request on shared
 * hosting. Closing the page stops it; opening it again carries on from where
 * it stopped.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bb_webp_menu() {
	add_management_page(
		__( 'Pictures to WebP', 'bichitro-biggan' ),
		__( 'Pictures to WebP', 'bichitro-biggan' ),
		'manage_options',
		'bb-webp',
		'bb_webp_page'
	);
}
add_action( 'admin_menu', 'bb_webp_menu' );

/** One pass, asked for by the page itself. */
function bb_webp_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'bichitro-biggan' ) ), 403 );
	}

	check_ajax_referer( 'bb_webp_run' );

	@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not every host allows it.

	wp_send_json_success( bb_webp_run_batch() );
}
add_action( 'wp_ajax_bb_webp_batch', 'bb_webp_ajax' );

/** Start again from the first attachment. */
function bb_webp_ajax_reset() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'bichitro-biggan' ) ), 403 );
	}

	check_ajax_referer( 'bb_webp_run' );
	delete_option( 'bb_webp_progress' );

	wp_send_json_success( array( 'progress' => bb_webp_progress(), 'total' => bb_webp_total() ) );
}
add_action( 'wp_ajax_bb_webp_reset', 'bb_webp_ajax_reset' );

function bb_webp_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bichitro-biggan' ) );
	}

	$supported = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	$progress  = bb_webp_progress();
	$total     = bb_webp_total();
	?>
	<div class="wrap bb-webp">
		<h1><?php esc_html_e( 'Pictures to WebP', 'bichitro-biggan' ); ?></h1>

		<p class="bb-webp__lede">
			<?php esc_html_e( 'Everything uploaded since the theme started converting pictures is already WebP. Everything from before it is still a JPEG or a PNG, and on a phone that is the heaviest thing left on the page. This writes a WebP beside each of them — the original file and every size made from it — and the site serves whichever is smaller.', 'bichitro-biggan' ); ?>
		</p>

		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Nothing is deleted or overwritten.', 'bichitro-biggan' ); ?></strong>
				<?php esc_html_e( 'The pictures you uploaded stay exactly where they are, and no article is rewritten. A new file is written next to each old one; where WebP comes out larger — a flat graphic sometimes does — it is thrown away and the original is kept. Stopping halfway leaves the site working.', 'bichitro-biggan' ); ?>
			</p>
		</div>

		<?php if ( ! $supported ) : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'This server cannot write WebP. Nothing can be converted here.', 'bichitro-biggan' ); ?></p>
			</div>
		<?php else : ?>

			<div class="bb-webp__figures">
				<div class="bb-webp__figure">
					<strong id="bb-webp-seen"><?php echo esc_html( number_format_i18n( $progress['seen'] ) ); ?></strong>
					<span><?php
						printf(
							/* translators: %s: how many pictures there are in all. */
							esc_html__( 'of %s pictures looked at', 'bichitro-biggan' ),
							esc_html( number_format_i18n( $total ) )
						);
					?></span>
				</div>
				<div class="bb-webp__figure">
					<strong id="bb-webp-made"><?php echo esc_html( number_format_i18n( $progress['made'] ) ); ?></strong>
					<span><?php esc_html_e( 'files written', 'bichitro-biggan' ); ?></span>
				</div>
				<div class="bb-webp__figure">
					<strong id="bb-webp-saved"><?php echo esc_html( size_format( $progress['bytes'], 1 ) ); ?></strong>
					<span><?php esc_html_e( 'lighter', 'bichitro-biggan' ); ?></span>
				</div>
				<div class="bb-webp__figure">
					<strong id="bb-webp-bigger"><?php echo esc_html( number_format_i18n( $progress['bigger'] ) ); ?></strong>
					<span><?php esc_html_e( 'left as they were', 'bichitro-biggan' ); ?></span>
				</div>
			</div>

			<div class="bb-webp__bar"><span id="bb-webp-fill" style="width:<?php echo (int) ( $total ? min( 100, round( 100 * $progress['seen'] / $total ) ) : 0 ); ?>%"></span></div>

			<p class="bb-webp__buttons">
				<button type="button" class="button button-primary" id="bb-webp-start">
					<?php echo $progress['seen'] > 0
						? esc_html__( 'Carry on', 'bichitro-biggan' )
						: esc_html__( 'Start converting', 'bichitro-biggan' ); ?>
				</button>
				<button type="button" class="button" id="bb-webp-stop" hidden><?php esc_html_e( 'Stop', 'bichitro-biggan' ); ?></button>
				<button type="button" class="button button-link-delete" id="bb-webp-reset"><?php esc_html_e( 'Start from the beginning', 'bichitro-biggan' ); ?></button>
				<span class="bb-webp__status" id="bb-webp-status"></span>
			</p>

			<p class="bb-webp__note">
				<?php esc_html_e( 'Leave this page open while it runs. It works through a few pictures at a time so the server is never asked for too much at once, so a large library takes a while — closing the page stops it, and opening it again carries on from the same place.', 'bichitro-biggan' ); ?>
			</p>

			<script>
			( function () {
				var running = false;
				var nonce = <?php echo wp_json_encode( wp_create_nonce( 'bb_webp_run' ) ); ?>;
				var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var strings = <?php echo wp_json_encode( array(
					'working'  => __( 'working…', 'bichitro-biggan' ),
					'stopped'  => __( 'stopped', 'bichitro-biggan' ),
					'finished' => __( 'finished — every picture has been looked at', 'bichitro-biggan' ),
					'failed'   => __( 'the server did not answer; press Carry on to try again', 'bichitro-biggan' ),
					'carry'    => __( 'Carry on', 'bichitro-biggan' ),
				) ); ?>;
				var total = <?php echo (int) $total; ?>;

				var start = document.getElementById( 'bb-webp-start' );
				var stop = document.getElementById( 'bb-webp-stop' );
				var reset = document.getElementById( 'bb-webp-reset' );
				var status = document.getElementById( 'bb-webp-status' );

				function show( p ) {
					document.getElementById( 'bb-webp-seen' ).textContent = p.seen.toLocaleString();
					document.getElementById( 'bb-webp-made' ).textContent = p.made.toLocaleString();
					document.getElementById( 'bb-webp-bigger' ).textContent = p.bigger.toLocaleString();
					document.getElementById( 'bb-webp-saved' ).textContent = ( p.bytes / 1048576 ).toFixed( 1 ) + ' MB';
					document.getElementById( 'bb-webp-fill' ).style.width =
						( total ? Math.min( 100, Math.round( 100 * p.seen / total ) ) : 0 ) + '%';
				}

				function send( action ) {
					var body = new FormData();
					body.append( 'action', action );
					body.append( '_ajax_nonce', nonce );

					return fetch( ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
						.then( function ( r ) { return r.json(); } );
				}

				function pass() {
					if ( ! running ) { return; }

					send( 'bb_webp_batch' ).then( function ( res ) {
						if ( ! res || ! res.success ) { throw new Error( 'bad answer' ); }

						show( res.data.progress );
						total = res.data.total || total;

						if ( res.data.done ) {
							running = false;
							start.hidden = false;
							stop.hidden = true;
							status.textContent = strings.finished;
							return;
						}

						pass();
					} ).catch( function () {
						running = false;
						start.hidden = false;
						start.textContent = strings.carry;
						stop.hidden = true;
						status.textContent = strings.failed;
					} );
				}

				start.addEventListener( 'click', function () {
					running = true;
					start.hidden = true;
					stop.hidden = false;
					status.textContent = strings.working;
					pass();
				} );

				stop.addEventListener( 'click', function () {
					running = false;
					start.hidden = false;
					start.textContent = strings.carry;
					stop.hidden = true;
					status.textContent = strings.stopped;
				} );

				reset.addEventListener( 'click', function () {
					running = false;
					send( 'bb_webp_reset' ).then( function ( res ) {
						if ( res && res.success ) {
							show( res.data.progress );
							total = res.data.total || total;
							start.hidden = false;
							stop.hidden = true;
							status.textContent = '';
						}
					} );
				} );
			}() );
			</script>

		<?php endif; ?>
	</div>

	<style>
		.bb-webp__lede { max-width: 760px; color: #50575e; }
		.bb-webp__figures { display: flex; flex-wrap: wrap; gap: 28px; margin: 20px 0 14px; }
		.bb-webp__figure strong { display: block; font-size: 26px; font-weight: 600; line-height: 1.15; color: #0f1419; font-variant-numeric: tabular-nums; }
		.bb-webp__figure span { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #6a7581; }
		.bb-webp__bar { height: 8px; max-width: 760px; background: #eef0f3; border-radius: 999px; overflow: hidden; }
		.bb-webp__bar span { display: block; height: 8px; background: linear-gradient(90deg, #0080ff, #5cb0ff); border-radius: 999px; transition: width .2s; }
		.bb-webp__buttons { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 16px 0 0; }
		.bb-webp__status { color: #6a7581; font-size: 13px; }
		.bb-webp__note { max-width: 760px; color: #6a7581; font-size: 12px; }
	</style>
	<?php
}
