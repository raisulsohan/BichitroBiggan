<?php
/**
 * Template Name: সৌরজগৎ থ্রিডি
 *
 * The interactive 3D solar system at /solarsystem. WordPress picks this file
 * by itself for a page whose slug is "solarsystem"; any other page can choose
 * it under Page Attributes › Template. inc/solarsystem.php loads three.js and
 * the page's own script and stylesheet here, and nowhere else.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'bb-solar' ); ?>>

		<header class="bb-solar__head">
			<?php bb_breadcrumb(); ?>
			<h1 class="bb-solar__title"><?php the_title(); ?></h1>
			<p class="bb-solar__lead"><?php esc_html_e( 'মাউস বা আঙুলে ঘুরিয়ে দেখুন সূর্য আর তার আটটি গ্রহ। যেকোনো গ্রহে ক্লিক করলে ক্যামেরা সেখানে উড়ে যাবে, অথবা গাইডেড ট্যুরে পুরো সৌরজগৎ ঘুরে আসুন।', 'bichitro-biggan' ); ?></p>
		</header>

		<section id="bb-solar" class="bbs is-explore" data-lang="<?php echo esc_attr( bb_is_en() ? 'en' : 'bn' ); ?>" aria-label="<?php esc_attr_e( 'ইন্টারঅ্যাক্টিভ থ্রিডি সৌরজগৎ', 'bichitro-biggan' ); ?>">

			<div class="bbs__tags" data-s="tags"></div>

			<div class="bbs__title" data-s="title" aria-hidden="true">
				<p class="bbs__title-main"><?php esc_html_e( 'সৌরজগৎ', 'bichitro-biggan' ); ?></p>
				<p class="bbs__title-sub"><?php esc_html_e( 'সূর্য আর তার আটটি গ্রহের মাঝে এক যাত্রা', 'bichitro-biggan' ); ?></p>
			</div>
			<div class="bbs__outro" data-s="outro" aria-hidden="true">
				<p class="bbs__outro-main"><?php esc_html_e( 'আমাদের মহাজাগতিক ঠিকানা', 'bichitro-biggan' ); ?></p>
				<p class="bbs__outro-sub"><?php esc_html_e( 'দ্রষ্টব্য: গ্রহের আকার ও দূরত্ব বাস্তব অনুপাতে দেখানো হয়নি', 'bichitro-biggan' ); ?></p>
			</div>
			<div class="bbs__cap" data-s="cap" aria-hidden="true">
				<div class="bbs__cap-idx"></div>
				<p class="bbs__cap-name"></p>
				<ul></ul>
			</div>

			<div class="bbs__top">
				<button type="button" class="bbs-btn bbs-btn--tour" data-s="tour">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg>
					<span><?php esc_html_e( 'গাইডেড ট্যুর', 'bichitro-biggan' ); ?></span>
					<span class="bbs-btn__meta"><?php echo esc_html( bb_bangla_number( '1:15' ) ); ?></span>
				</button>
				<div class="bbs__tools">
					<button type="button" class="bbs-btn" data-s="home" title="<?php esc_attr_e( 'সূর্যে ফিরুন — পুরো সৌরজগৎ দেখুন', 'bichitro-biggan' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0-5 1.5 3h-3zm0 20-1.5-3h3zM2 12l3-1.5v3zm20 0-3 1.5v-3zM4.9 4.9l3.2 1.1-2.1 2.1zm14.2 14.2-3.2-1.1 2.1-2.1zM4.9 19.1l1.1-3.2 2.1 2.1zM19.1 4.9 18 8.1 15.9 6z"/></svg>
						<span><?php esc_html_e( 'পুরো সৌরজগৎ', 'bichitro-biggan' ); ?></span>
					</button>
					<button type="button" class="bbs-icon" data-s="orbits" aria-pressed="true"
						data-on="<?php esc_attr_e( 'গ্রহগুলোর চলা থামান', 'bichitro-biggan' ); ?>"
						data-off="<?php esc_attr_e( 'গ্রহগুলোকে আবার চালান', 'bichitro-biggan' ); ?>"
						title="<?php esc_attr_e( 'গ্রহগুলোর চলা থামান', 'bichitro-biggan' ); ?>"
						aria-label="<?php esc_attr_e( 'গ্রহগুলোর চলা থামান', 'bichitro-biggan' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path class="bbs-ico-on" d="M7 5h3.5v14H7zm6.5 0H17v14h-3.5z"/><path class="bbs-ico-off" d="M7 4v16l13-8z"/></svg>
					</button>
					<button type="button" class="bbs-icon" data-s="labels" aria-pressed="true"
						data-on="<?php esc_attr_e( 'নাম লুকান', 'bichitro-biggan' ); ?>"
						data-off="<?php esc_attr_e( 'নাম দেখান', 'bichitro-biggan' ); ?>"
						title="<?php esc_attr_e( 'নাম লুকান', 'bichitro-biggan' ); ?>"
						aria-label="<?php esc_attr_e( 'নাম লুকান', 'bichitro-biggan' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v3h-6.5v11h-3V8H4z"/><path class="bbs-ico-off" d="M3.4 2 22 20.6 20.6 22 2 3.4z"/></svg>
					</button>
					<button type="button" class="bbs-icon" data-s="fs"
						data-on="<?php esc_attr_e( 'পূর্ণ পর্দা', 'bichitro-biggan' ); ?>"
						data-off="<?php esc_attr_e( 'পূর্ণ পর্দা থেকে বেরোন', 'bichitro-biggan' ); ?>"
						title="<?php esc_attr_e( 'পূর্ণ পর্দা', 'bichitro-biggan' ); ?>"
						aria-label="<?php esc_attr_e( 'পূর্ণ পর্দা', 'bichitro-biggan' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h5v2H7v3H5zm9 0h5v5h-2V7h-3zM5 14h2v3h3v2H5zm12 0h2v5h-5v-2h3z"/></svg>
					</button>
				</div>
			</div>

			<p class="bbs__hint" data-s="hint" aria-hidden="true"></p>
			<button type="button" class="bbs__camreset" data-s="camreset" hidden><?php esc_html_e( 'ক্যামেরা ট্যুরে ফেরান', 'bichitro-biggan' ); ?></button>

			<nav class="bbs__chips" data-s="chips" aria-label="<?php esc_attr_e( 'সূর্য বা গ্রহ বেছে নিন', 'bichitro-biggan' ); ?>"></nav>

			<div class="bbs-card" data-s="card" role="region" aria-labelledby="bbs-card-title" aria-live="polite" hidden></div>

			<button type="button" class="bbs__bigplay" data-s="bigplay" aria-label="<?php esc_attr_e( 'ট্যুর চালু করুন', 'bichitro-biggan' ); ?>" hidden>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg>
			</button>

			<div class="bbs-bar" data-s="bar" role="group" aria-label="<?php esc_attr_e( 'ট্যুরের প্লেয়ার', 'bichitro-biggan' ); ?>">
				<input class="bbs-bar__seek" data-s="seek" type="range" min="0" max="75" step="0.0333" value="0" aria-label="<?php esc_attr_e( 'সময় নির্বাচন', 'bichitro-biggan' ); ?>">
				<div class="bbs-bar__row">
					<button type="button" data-s="restart" title="<?php esc_attr_e( 'শুরু থেকে (Home)', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'শুরু থেকে', 'bichitro-biggan' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h2v14H6zM20 5v14L9 12z"/></svg></button>
					<button type="button" data-s="prev" title="<?php esc_attr_e( 'আগের ফ্রেম (,)', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'আগের ফ্রেম', 'bichitro-biggan' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 5v14l-9-7zM6 5h2v14H6z"/></svg></button>
					<button type="button" data-s="play" title="<?php esc_attr_e( 'চালু/বিরতি (Space / K)', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'চালু করুন', 'bichitro-biggan' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path data-s="playIcon" d="M7 4v16l13-8z"/></svg></button>
					<button type="button" data-s="next" title="<?php esc_attr_e( 'পরের ফ্রেম (.)', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'পরের ফ্রেম', 'bichitro-biggan' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5v14l9-7zM16 5h2v14h-2z"/></svg></button>
					<span class="bbs-bar__read" data-s="time"><?php echo esc_html( bb_bangla_number( '00:00.00 / 01:15.00' ) ); ?></span>
					<span class="bbs-bar__read bbs-bar__read--dim" data-s="frame"><?php echo esc_html( sprintf( /* translators: 1: current frame, 2: total frames. */ __( 'ফ্রেম %1$s / %2$s', 'bichitro-biggan' ), bb_bangla_number( 1 ), bb_bangla_number( 2250 ) ) ); ?></span>
					<span class="bbs-bar__spacer"></span>
					<select data-s="speed" title="<?php esc_attr_e( 'গতি', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'গতি', 'bichitro-biggan' ); ?>">
						<?php foreach ( array( '0.25', '0.5', '0.75', '1', '1.5', '2' ) as $bb_speed ) : ?>
							<option value="<?php echo esc_attr( $bb_speed ); ?>"<?php selected( $bb_speed, '1' ); ?>><?php echo esc_html( bb_bangla_number( $bb_speed ) . '×' ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" data-s="loop" aria-pressed="false" title="<?php esc_attr_e( 'লুপ (L)', 'bichitro-biggan' ); ?>" aria-label="<?php esc_attr_e( 'লুপ', 'bichitro-biggan' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v3l4-4-4-4v3H5v6h2zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2z"/></svg></button>
					<button type="button" data-s="fs"
						data-on="<?php esc_attr_e( 'পূর্ণ পর্দা (F)', 'bichitro-biggan' ); ?>"
						data-off="<?php esc_attr_e( 'পূর্ণ পর্দা থেকে বেরোন (F)', 'bichitro-biggan' ); ?>"
						title="<?php esc_attr_e( 'পূর্ণ পর্দা (F)', 'bichitro-biggan' ); ?>"
						aria-label="<?php esc_attr_e( 'পূর্ণ পর্দা (F)', 'bichitro-biggan' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h5v2H7v3H5zm9 0h5v5h-2V7h-3zM5 14h2v3h3v2H5zm12 0h2v5h-5v-2h3z"/></svg></button>
					<button type="button" class="bbs-bar__exit" data-s="exit"><?php esc_html_e( 'ট্যুর শেষ করুন', 'bichitro-biggan' ); ?></button>
				</div>
			</div>

			<div class="bbs__loading" data-s="loading" role="status">
				<div class="bbs__orbit" aria-hidden="true"><span></span></div>
				<p data-s="loadtext"><?php esc_html_e( 'থ্রিডি সৌরজগৎ তৈরি হচ্ছে…', 'bichitro-biggan' ); ?></p>
				<div class="bbs__progress" aria-hidden="true"><span data-s="progress"></span></div>
			</div>

			<div class="bbs__msg" data-s="nogl" role="alert" hidden>
				<p class="bbs__msg-title"><?php esc_html_e( 'দুঃখিত, থ্রিডি সৌরজগৎ এখানে দেখানো যাচ্ছে না', 'bichitro-biggan' ); ?></p>
				<p><?php esc_html_e( 'আপনার ব্রাউজার বা ডিভাইসে WebGL চালু নেই, অথবা ব্রাউজারটি পুরোনো। Chrome, Firefox, Edge বা Safari-র নতুন সংস্করণে পাতাটি খুলে দেখুন, কিংবা ব্রাউজারের সেটিংসে হার্ডওয়্যার অ্যাক্সিলারেশন চালু করুন।', 'bichitro-biggan' ); ?></p>
			</div>
			<div class="bbs__msg bbs__msg--soft" data-s="glmsg" hidden>
				<p><?php esc_html_e( 'গ্রাফিক্স সাময়িকভাবে বন্ধ — ফিরিয়ে আনা হচ্ছে…', 'bichitro-biggan' ); ?></p>
			</div>
			<noscript>
				<div class="bbs__msg">
					<p><?php esc_html_e( 'থ্রিডি সৌরজগৎ দেখতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু করুন।', 'bichitro-biggan' ); ?></p>
				</div>
			</noscript>
		</section>
		<script>
			/* A browser too old for modules or import maps never runs the scene:
			   say so instead of leaving the loading screen up for ever. */
			(function () {
				var stage = document.getElementById('bb-solar');
				var old = !('noModule' in HTMLScriptElement.prototype) ||
					!(HTMLScriptElement.supports && HTMLScriptElement.supports('importmap'));
				function show() {
					stage.classList.add('is-nogl');
					stage.querySelector('[data-s="nogl"]').hidden = false;
					stage.querySelector('[data-s="loading"]').hidden = true;
				}
				if (old) {
					show();
					return;
				}
				setTimeout(function () {
					if (!stage.classList.contains('is-ready') && !stage.classList.contains('is-nogl')) {
						stage.querySelector('[data-s="loadtext"]').textContent = <?php echo wp_json_encode( __( 'লোড হতে দেরি হচ্ছে… সংযোগ ধীর হলে পাতাটি আবার লোড করে দেখুন।', 'bichitro-biggan' ) ); ?>;
					}
				}, 25000);
			})();
		</script>

		<div class="bb-solar__body">
			<h2 class="bb-solar__h2"><?php esc_html_e( 'কীভাবে দেখবেন', 'bichitro-biggan' ); ?></h2>
			<ul class="bb-solar__howto">
				<li><?php esc_html_e( 'ঘোরাতে: মাউস টেনে বা এক আঙুলে স্পর্শ করে সরান।', 'bichitro-biggan' ); ?></li>
				<li><?php esc_html_e( 'জুম করতে: মাউসের চাকা ঘোরান, বা দুই আঙুলে চিমটি কাটুন।', 'bichitro-biggan' ); ?></li>
				<li><?php esc_html_e( 'কোনো গ্রহ বা তার নামে ক্লিক করুন — ক্যামেরা সেখানে উড়ে যাবে, আর তথ্যের কার্ড খুলবে।', 'bichitro-biggan' ); ?></li>
				<li><?php esc_html_e( '“গাইডেড ট্যুর” চাপলে ৭৫ সেকেন্ডের এক যাত্রা শুরু হবে। ট্যুর থামালেই আবার নিজের মতো ঘুরে দেখতে পারবেন।', 'bichitro-biggan' ); ?></li>
				<li><?php esc_html_e( 'ট্যুরের কিবোর্ড শর্টকাট: Space বা K চালু/বিরতি, ← → এক সেকেন্ড পিছনে/সামনে, কমা ও দাঁড়ি এক ফ্রেম, Home/End শুরু/শেষ, L লুপ, F পূর্ণ পর্দা।', 'bichitro-biggan' ); ?></li>
			</ul>

			<?php
			/* The English edition shows the page's English text, and nothing rather than the Bengali one. */
			$bb_body = bb_is_en() ? bb_en_get( get_the_ID(), 'bb_en_content' ) : get_the_content();
			?>
			<?php if ( '' !== trim( $bb_body ) ) : ?>
				<div class="bb-content bb-solar__content">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<p class="bb-solar__note"><?php esc_html_e( 'দ্রষ্টব্য: দেখার সুবিধার জন্য গ্রহের আকার ও দূরত্ব বাস্তব অনুপাতে দেখানো হয়নি। গ্রহের চলার গতিও অনেক গুণ বাড়িয়ে দেখানো হয়েছে।', 'bichitro-biggan' ); ?></p>
			<p class="bb-solar__credit">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: link to Solar System Scope, 2: link to the licence. */
						esc_html__( 'গ্রহের ছবির টেক্সচার: %1$s, %2$s লাইসেন্সে (ছোট করে WebP-তে রূপান্তরিত)।', 'bichitro-biggan' ),
						'<a href="https://www.solarsystemscope.com/textures/" target="_blank" rel="noopener">Solar System Scope</a>',
						'<a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener license">CC BY 4.0</a>'
					)
				);
				?>
			</p>
		</div>

	</article>
	<?php
endwhile;

get_footer();
