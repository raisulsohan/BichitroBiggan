<?php
/**
 * Built-in SEO meta box — Yoast-এর মতো, কিন্তু আলাদা প্লাগইন ছাড়া।
 *
 * পোস্ট ও পেজ এডিটরের নিচে একটি SEO মেটা বক্স দেখায়:
 *   • Focus keyphrase
 *   • Keyphrase synonyms
 *   • Google preview (mobile + desktop)
 *   • SEO title (variable tags সহ)
 *   • Slug (read-only)
 *   • Meta description
 *
 * ফ্রন্টএন্ডে <head>-এ meta description, Open Graph ও Twitter Card
 * ট্যাগ অটোমেটিক আউটপুট করে।
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 0. Yoast fallback — read from Yoast meta if our key is empty
 * ======================================================================= */

/**
 * Yoast meta key mapping.
 *
 * Our key => Yoast key.
 */
function bb_seo_yoast_map() {
	return array(
		'bb_focus_keyphrase'    => '_yoast_wpseo_focuskw',
		'bb_keyphrase_synonyms' => '_yoast_wpseo_keywordsynonyms',
		'bb_seo_title'          => '_yoast_wpseo_title',
		'bb_seo_description'    => '_yoast_wpseo_metadesc',
	);
}

/**
 * Get an SEO field value with Yoast fallback.
 *
 * Checks our meta key first. If empty, checks the corresponding Yoast key.
 * This ensures all existing Yoast data keeps working even before migration.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Our meta key (e.g. 'bb_seo_title').
 * @return string
 */
function bb_seo_get_field( $post_id, $meta_key ) {
	$value = get_post_meta( $post_id, $meta_key, true );

	if ( $value ) {
		return $value;
	}

	// Fallback to Yoast.
	$map = bb_seo_yoast_map();

	if ( isset( $map[ $meta_key ] ) ) {
		$yoast_value = get_post_meta( $post_id, $map[ $meta_key ], true );

		if ( $yoast_value ) {
			// Yoast synonyms are stored as JSON array.
			if ( 'bb_keyphrase_synonyms' === $meta_key ) {
				$decoded = json_decode( $yoast_value, true );
				if ( is_array( $decoded ) ) {
					return implode( ', ', array_filter( $decoded ) );
				}
			}

			// Yoast title may contain %%title%% style variables — convert them.
			if ( 'bb_seo_title' === $meta_key ) {
				$yoast_value = str_replace(
					array( '%%title%%', '%%sep%%', '%%sitename%%', '%%page%%', '%%primary_category%%' ),
					array( '%title%',   '%sep%',   '%sitename%',   '',         '' ),
					$yoast_value
				);
			}

			return $yoast_value;
		}
	}

	return '';
}

/* =========================================================================
 * 1. Register the meta box
 * ======================================================================= */

/**
 * Add the SEO meta box to post and page editors.
 */
function bb_seo_add_meta_box() {
	$post_types = array( 'post', 'page' );

	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'bb_seo_meta_box',
			__( 'বিচিত্র বিজ্ঞান — SEO', 'bichitro-biggan' ),
			'bb_seo_render_meta_box',
			$post_type,
			'normal',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'bb_seo_add_meta_box' );

/* =========================================================================
 * 2. Render the meta box HTML
 * ======================================================================= */

/**
 * Output the meta box form fields.
 *
 * @param WP_Post $post Current post object.
 */
function bb_seo_render_meta_box( $post ) {
	// Security nonce.
	wp_nonce_field( 'bb_seo_save', 'bb_seo_nonce' );

	// Current values (with Yoast fallback).
	$focus_keyphrase    = bb_seo_get_field( $post->ID, 'bb_focus_keyphrase' );
	$keyphrase_synonyms = bb_seo_get_field( $post->ID, 'bb_keyphrase_synonyms' );
	$seo_title          = bb_seo_get_field( $post->ID, 'bb_seo_title' );
	$seo_description    = bb_seo_get_field( $post->ID, 'bb_seo_description' );

	// Site info for Google preview.
	$site_name = get_bloginfo( 'name' );
	$site_url  = home_url( '/' );
	$post_date = get_the_date( 'M j, Y', $post );

	// Favicon URL for preview.
	$favicon_url = get_site_icon_url( 32 );
	if ( ! $favicon_url ) {
		$favicon_url = '';
	}

	// Featured image URL for Google snippet preview.
	$featured_img_url = '';
	if ( has_post_thumbnail( $post->ID ) ) {
		$img_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'medium' );
		if ( ! $img_src ) {
			$img_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'thumbnail' );
		}
		if ( $img_src ) {
			$featured_img_url = $img_src[0];
		}
	}
	?>
	<div class="bb-seo-box">

		<!-- Top Overall SEO Score Indicator Bar (Yoast-style) -->
		<div class="bb-seo-topbar">
			<div class="bb-seo-tab-btn bb-seo-tab-btn--active">
				<span id="bb-seo-score-indicator" class="bb-seo-traffic-dot bb-seo-traffic-dot--bad"></span>
				<span class="bb-seo-tab-label">SEO</span>
				<span id="bb-seo-score-badge" class="bb-seo-score-badge bb-seo-score-badge--bad">অপর্যাপ্ত</span>
			</div>
		</div>

		<!-- ============================================================
		     Focus Keyphrase Section
		     ============================================================ -->
		<div class="bb-seo-section bb-seo-section--keyphrase">
			<div class="bb-seo-section-header" role="button" tabindex="0">
				<span class="bb-seo-section-title"><?php esc_html_e( 'Focus keyphrase', 'bichitro-biggan' ); ?></span>
				<span class="bb-seo-section-arrow" aria-hidden="true">&#9650;</span>
			</div>
			<div class="bb-seo-section-body">

				<div class="bb-seo-field">
					<label class="bb-seo-label" for="bb-focus-keyphrase">
						<?php esc_html_e( 'Focus keyphrase', 'bichitro-biggan' ); ?>
					</label>
					<input
						type="text"
						id="bb-focus-keyphrase"
						name="bb_focus_keyphrase"
						class="bb-seo-input"
						value="<?php echo esc_attr( $focus_keyphrase ); ?>"
						placeholder="<?php esc_attr_e( 'এখানে টাইপ করুন', 'bichitro-biggan' ); ?>"
					/>
					<p class="bb-seo-help">
						<?php esc_html_e( 'আপনার কনটেন্ট যেই মূল শব্দ বা ফ্রেজ দিয়ে খুঁজে পাওয়া যাবে সেটি লিখুন।', 'bichitro-biggan' ); ?>
					</p>
				</div>

				<div class="bb-seo-field">
					<label class="bb-seo-label" for="bb-keyphrase-synonyms">
						<?php esc_html_e( 'Keyphrase synonyms', 'bichitro-biggan' ); ?>
					</label>
					<input
						type="text"
						id="bb-keyphrase-synonyms"
						name="bb_keyphrase_synonyms"
						class="bb-seo-input"
						value="<?php echo esc_attr( $keyphrase_synonyms ); ?>"
						placeholder="<?php esc_attr_e( 'কমা দিয়ে আলাদা করুন', 'bichitro-biggan' ); ?>"
					/>
				</div>

			</div>
		</div>

		<!-- ============================================================
		     Search Appearance Section
		     ============================================================ -->
		<div class="bb-seo-section bb-seo-section--appearance">
			<div class="bb-seo-section-header" role="button" tabindex="0">
				<span class="bb-seo-section-title"><?php esc_html_e( 'Search appearance', 'bichitro-biggan' ); ?></span>
				<span class="bb-seo-section-arrow" aria-hidden="true">&#9650;</span>
			</div>
			<div class="bb-seo-section-body">

				<!-- Google Preview -->
				<div class="bb-seo-field">
					<div class="bb-seo-preview-header">
						<span class="bb-seo-label"><?php esc_html_e( 'Google preview', 'bichitro-biggan' ); ?></span>
						<div class="bb-seo-toggle">
							<button type="button" id="bb-seo-toggle-mobile" class="bb-seo-toggle-btn" title="Mobile">
								<?php esc_html_e( 'Mobile', 'bichitro-biggan' ); ?>
							</button>
							<button type="button" id="bb-seo-toggle-desktop" class="bb-seo-toggle-btn bb-seo-toggle-btn--active" title="Desktop">
								<?php esc_html_e( 'Desktop', 'bichitro-biggan' ); ?>
							</button>
						</div>
					</div>
					<p class="bb-seo-help" style="margin-top:0;">
						<?php esc_html_e( 'Google সার্চ রেজাল্টে আপনার পোস্ট কেমন দেখাবে তার প্রিভিউ।', 'bichitro-biggan' ); ?>
					</p>

					<div class="bb-seo-preview">
						<div class="bb-seo-preview-card bb-seo-preview--desktop">
							<!-- Site identity row -->
							<div class="bb-seo-preview-site">
								<?php if ( $favicon_url ) : ?>
									<img
										class="bb-seo-preview-favicon"
										src="<?php echo esc_url( $favicon_url ); ?>"
										alt=""
										width="28"
										height="28"
									/>
								<?php else : ?>
									<span class="bb-seo-preview-favicon bb-seo-preview-favicon--placeholder">
										<?php echo esc_html( bb_str_sub( $site_name, 0, 1 ) ); ?>
									</span>
								<?php endif; ?>
								<div class="bb-seo-preview-site-info">
									<span class="bb-seo-preview-site-name"><?php echo esc_html( $site_name ); ?></span>
									<span class="bb-seo-preview-url"><?php echo esc_html( $site_url ); ?></span>
								</div>
								<span class="bb-seo-preview-dots" aria-hidden="true">&#8942;</span>
							</div>

							<!-- Preview main body (text + featured image snippet) -->
							<div class="bb-seo-preview-main">
								<div class="bb-seo-preview-text-col">
									<!-- Title -->
									<div class="bb-seo-preview-title">
										<?php
										if ( $seo_title ) {
											echo esc_html( bb_seo_resolve_variables( $seo_title, $post ) );
										} else {
											echo esc_html( $post->post_title . ' — ' . $site_name );
										}
										?>
									</div>
									<!-- Description -->
									<div class="bb-seo-preview-desc">
										<span class="bb-seo-preview-date"><?php echo esc_html( $post_date ); ?></span>
										<?php
										if ( $seo_description ) {
											echo ' — ' . esc_html( $seo_description );
										} else {
											esc_html_e( 'এখানে আপনার মেটা ডেসক্রিপশন দেখাবে...', 'bichitro-biggan' );
										}
										?>
									</div>
								</div>

								<!-- Featured Image thumbnail snippet -->
								<div class="bb-seo-preview-image-col" id="bb-seo-preview-img-wrap" style="<?php echo $featured_img_url ? '' : 'display:none;'; ?>">
									<img
										id="bb-seo-preview-img"
										src="<?php echo esc_url( $featured_img_url ); ?>"
										alt=""
									/>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- SEO Title -->
				<div class="bb-seo-field">
					<div class="bb-seo-field-header">
						<label class="bb-seo-label" for="bb-seo-title">
							<?php esc_html_e( 'SEO title', 'bichitro-biggan' ); ?>
						</label>
						<span id="bb-seo-title-counter" class="bb-seo-counter bb-seo-counter--good">
							0 / 60 <?php esc_html_e( 'অক্ষর', 'bichitro-biggan' ); ?>
						</span>
					</div>
					<input
						type="text"
						id="bb-seo-title"
						name="bb_seo_title"
						class="bb-seo-input"
						value="<?php echo esc_attr( $seo_title ); ?>"
						placeholder="<?php echo esc_attr( $post->post_title . ' — ' . $site_name ); ?>"
					/>
					<div class="bb-seo-progress">
						<div id="bb-seo-title-progress-bar" class="bb-seo-progress-bar"></div>
					</div>
					<div class="bb-seo-tags">
						<button type="button" class="bb-seo-tag-btn" data-tag="%title%">Title</button>
						<button type="button" class="bb-seo-tag-btn" data-tag="%sep%">Separator</button>
						<button type="button" class="bb-seo-tag-btn" data-tag="%sitename%">Site title</button>
					</div>
				</div>

				<!-- Slug (Editable) -->
				<div class="bb-seo-field">
					<label class="bb-seo-label" for="bb-seo-slug">
						<?php esc_html_e( 'Slug', 'bichitro-biggan' ); ?>
					</label>
					<input
						type="text"
						id="bb-seo-slug"
						name="bb_seo_slug"
						class="bb-seo-input bb-seo-slug"
						value="<?php echo esc_attr( $post->post_name ); ?>"
						placeholder="<?php esc_attr_e( 'post-url-slug', 'bichitro-biggan' ); ?>"
					/>
					<p class="bb-seo-help">
						<?php esc_html_e( 'পোস্টের পারমালিঙ্ক বা স্লাগ পরিবর্তন করতে এখানে টাইপ করুন।', 'bichitro-biggan' ); ?>
					</p>
				</div>

				<!-- Meta Description -->
				<div class="bb-seo-field">
					<div class="bb-seo-field-header">
						<label class="bb-seo-label" for="bb-seo-description">
							<?php esc_html_e( 'Meta description', 'bichitro-biggan' ); ?>
						</label>
						<span id="bb-seo-desc-counter" class="bb-seo-counter bb-seo-counter--good">
							0 / 160 <?php esc_html_e( 'অক্ষর', 'bichitro-biggan' ); ?>
						</span>
					</div>
					<textarea
						id="bb-seo-description"
						name="bb_seo_description"
						class="bb-seo-input bb-seo-textarea"
						rows="3"
						placeholder="<?php esc_attr_e( 'সার্চ রেজাল্টে যেই বর্ণনা দেখাতে চান সেটি লিখুন...', 'bichitro-biggan' ); ?>"
					><?php echo esc_textarea( $seo_description ); ?></textarea>
					<div class="bb-seo-progress">
						<div id="bb-seo-desc-progress-bar" class="bb-seo-progress-bar"></div>
					</div>
				</div>

			</div>
		</div>

		<!-- ============================================================
		     SEO Analysis Section (Yoast-style Traffic Lights)
		     ============================================================ -->
		<div class="bb-seo-section bb-seo-section--analysis">
			<div class="bb-seo-section-header" role="button" tabindex="0">
				<span class="bb-seo-section-title">
					<?php esc_html_e( 'SEO বিশ্লেষণ (SEO Analysis)', 'bichitro-biggan' ); ?>
				</span>
				<span class="bb-seo-section-arrow" aria-hidden="true">&#9650;</span>
			</div>
			<div class="bb-seo-section-body">
				<div class="bb-seo-analysis-wrapper">
					<div id="bb-seo-analysis-status" class="bb-seo-analysis-status">
						<!-- Overall score bar -->
					</div>
					<ul id="bb-seo-analysis-list" class="bb-seo-analysis-list">
						<!-- Live JS generated criteria with 🟢 🟠 🔴 -->
					</ul>
				</div>
			</div>
		</div>

	</div>
	<?php
}

/* =========================================================================
 * 3. Save meta box data
 * ======================================================================= */

/**
 * Save SEO fields when the post is saved.
 *
 * @param int $post_id Post ID.
 */
function bb_seo_save_meta_box( $post_id ) {
	// Verify nonce.
	if ( ! isset( $_POST['bb_seo_nonce'] ) || ! wp_verify_nonce( $_POST['bb_seo_nonce'], 'bb_seo_save' ) ) {
		return;
	}

	// Don't save on autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Save custom slug if edited.
	if ( isset( $_POST['bb_seo_slug'] ) ) {
		$raw_slug = sanitize_title( wp_unslash( $_POST['bb_seo_slug'] ) );
		$current_post = get_post( $post_id );
		if ( $raw_slug && $current_post && $current_post->post_name !== $raw_slug ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				array( 'post_name' => $raw_slug ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $post_id );
		}
	}

	// Field definitions: meta_key => sanitization callback.
	$fields = array(
		'bb_focus_keyphrase'    => 'sanitize_text_field',
		'bb_keyphrase_synonyms' => 'sanitize_text_field',
		'bb_seo_title'          => 'sanitize_text_field',
		'bb_seo_description'    => 'sanitize_textarea_field',
	);

	foreach ( $fields as $meta_key => $sanitize_fn ) {
		if ( isset( $_POST[ $meta_key ] ) ) {
			$value = call_user_func( $sanitize_fn, wp_unslash( $_POST[ $meta_key ] ) );

			if ( $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}
}
add_action( 'save_post', 'bb_seo_save_meta_box' );

/* =========================================================================
 * 4. Helper: Resolve template variables in SEO title
 * ======================================================================= */

/**
 * Replace %title%, %sep%, %sitename% in the SEO title template.
 *
 * @param string  $template The SEO title with placeholders.
 * @param WP_Post $post     Current post object.
 * @return string Resolved title.
 */
function bb_seo_resolve_variables( $template, $post = null ) {
	if ( ! $post ) {
		$post = get_post();
	}

	$replacements = array(
		'%title%'    => $post ? $post->post_title : '',
		'%sep%'      => '—',
		'%sitename%' => get_bloginfo( 'name' ),
	);

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
}

/* =========================================================================
 * 5. Frontend: Output meta tags in <head>
 * ======================================================================= */

/**
 * Output SEO meta tags on the frontend.
 *
 * সিঙ্গুলার পোস্ট/পেজ, হোমপেজ ও সব আর্কাইভ — সবখানেই কাজ করে।
 * টাইটেল/বর্ণনা/ক্যানোনিকাল রেজলভ করার আসল কাজটা inc/seo-frontend.php-এর
 * bb_seo_get_context() করে; এই ফাংশন শুধু সেটা ছাপায়।
 *
 * এসইও প্লাগইন সক্রিয় থাকলে, ফিডে, সার্চ রেজাল্টে বা 404-এ কিছুই ছাপে না।
 */
function bb_seo_head_meta() {
	if ( ! bb_seo_should_output() ) {
		return;
	}

	$ctx = bb_seo_get_context();

	if ( ! $ctx['title'] && ! $ctx['description'] && ! $ctx['canonical'] ) {
		return;
	}

	$site_name = get_bloginfo( 'name' );

	echo "\n<!-- বিচিত্র বিজ্ঞান SEO -->\n";

	// Meta description.
	if ( $ctx['description'] ) {
		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr( $ctx['description'] )
		);
	}

	// Meta keywords from focus keyphrase (সিঙ্গুলার পোস্টেই প্রযোজ্য).
	if ( $ctx['post'] instanceof WP_Post ) {
		$focus_keyphrase = bb_seo_get_field( $ctx['post']->ID, 'bb_focus_keyphrase' );

		if ( $focus_keyphrase ) {
			$synonyms = bb_seo_get_field( $ctx['post']->ID, 'bb_keyphrase_synonyms' );
			$keywords = $synonyms ? $focus_keyphrase . ', ' . $synonyms : $focus_keyphrase;

			printf(
				'<meta name="keywords" content="%s" />' . "\n",
				esc_attr( $keywords )
			);
		}
	}

	// Canonical URL — কোরের rel_canonical() সরিয়ে দেওয়া হয়েছে, তাই এটিই একমাত্র।
	if ( $ctx['canonical'] ) {
		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $ctx['canonical'] ) );
	}

	// Open Graph tags.
	printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $ctx['og_type'] ) );
	printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( get_locale() ) );
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( $site_name ) );

	if ( $ctx['title'] ) {
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $ctx['title'] ) );
	}

	if ( $ctx['description'] ) {
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $ctx['description'] ) );
	}

	if ( $ctx['canonical'] ) {
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $ctx['canonical'] ) );
	}

	if ( $ctx['image'] ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $ctx['image'] ) );
	}

	// Article-নির্দিষ্ট OG ট্যাগ।
	if ( 'article' === $ctx['og_type'] && $ctx['post'] instanceof WP_Post ) {
		printf(
			'<meta property="article:published_time" content="%s" />' . "\n",
			esc_attr( get_the_date( DATE_W3C, $ctx['post'] ) )
		);
		printf(
			'<meta property="article:modified_time" content="%s" />' . "\n",
			esc_attr( get_the_modified_date( DATE_W3C, $ctx['post'] ) )
		);

		$categories = get_the_category( $ctx['post']->ID );

		if ( $categories && ! is_wp_error( $categories ) ) {
			printf(
				'<meta property="article:section" content="%s" />' . "\n",
				esc_attr( $categories[0]->name )
			);
		}
	}

	// Twitter Card tags.
	printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );

	if ( $ctx['title'] ) {
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $ctx['title'] ) );
	}

	if ( $ctx['description'] ) {
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $ctx['description'] ) );
	}

	if ( $ctx['image'] ) {
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $ctx['image'] ) );
	}

	echo "<!-- / বিচিত্র বিজ্ঞান SEO -->\n\n";
}
add_action( 'wp_head', 'bb_seo_head_meta', 1 );

/* =========================================================================
 * 6. Enqueue admin assets for the meta box
 * ======================================================================= */

/**
 * Enqueue the SEO meta box CSS and JS on post editor screens.
 *
 * @param string $hook Current admin page hook.
 */
function bb_seo_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	wp_enqueue_style(
		'bb-seo-meta-box',
		get_template_directory_uri() . '/assets/css/seo-meta-box.css',
		array(),
		bb_file_version( get_template_directory() . '/assets/css/seo-meta-box.css' )
	);

	wp_enqueue_script(
		'bb-seo-meta-box',
		get_template_directory_uri() . '/assets/js/seo-meta-box.js',
		array(),
		bb_file_version( get_template_directory() . '/assets/js/seo-meta-box.js' ),
		true
	);

	$post_id   = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0 );
	$thumb_url = '';
	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'medium' );
		if ( ! $img ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'thumbnail' );
		}
		if ( $img ) {
			$thumb_url = $img[0];
		}
	}

	wp_localize_script( 'bb-seo-meta-box', 'bbSeo', array(
		'siteTitle'     => get_bloginfo( 'name' ),
		'siteUrl'       => home_url( '/' ),
		'separator'     => '—',
		'featuredImage' => $thumb_url,
	) );
}
add_action( 'admin_enqueue_scripts', 'bb_seo_admin_assets' );

/* =========================================================================
 * 7. Yoast → বিচিত্র বিজ্ঞান one-click migration
 * ======================================================================= */

/**
 * Check if there are any posts with Yoast data that haven't been migrated.
 *
 * @return int Number of posts with unmigrated Yoast data.
 */
function bb_seo_yoast_unmigrated_count() {
	global $wpdb;

	// Count posts that have Yoast title or description but NOT our keys.
	$count = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT p.ID)
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} ym
		   ON p.ID = ym.post_id
		   AND ym.meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_focuskw')
		   AND ym.meta_value != ''
		 LEFT JOIN {$wpdb->postmeta} bm
		   ON p.ID = bm.post_id
		   AND bm.meta_key = 'bb_seo_title'
		   AND bm.meta_value != ''
		 WHERE p.post_status IN ('publish','draft','pending','future','private')
		   AND p.post_type IN ('post','page')
		   AND bm.meta_id IS NULL"
	);

	return $count;
}

/**
 * Show an admin notice if Yoast data is detected and not yet migrated.
 */
function bb_seo_migration_notice() {
	// Only show to admins.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Don't show if already dismissed.
	if ( get_option( 'bb_seo_yoast_migrated' ) ) {
		return;
	}

	// Check if Yoast data exists.
	$count = bb_seo_yoast_unmigrated_count();

	if ( $count < 1 ) {
		return;
	}

	?>
	<div id="bb-seo-migration-notice" class="notice notice-info" style="padding:12px 16px;border-left-color:#0073aa;">
		<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
			<div style="flex:1;min-width:200px;">
				<p style="margin:0 0 4px;font-size:14px;">
					<strong>🔄 Yoast SEO ডেটা পাওয়া গেছে!</strong>
				</p>
				<p style="margin:0;color:#50575e;">
					<?php
					printf(
						/* translators: %d = number of posts */
						__( '%d টি পোস্ট/পেজে Yoast SEO ডেটা আছে যেগুলো এখনও থিমের SEO সিস্টেমে কপি হয়নি। এক ক্লিকে সব কপি করুন — Yoast-এর আসল ডেটা মুছবে না।', 'bichitro-biggan' ),
						$count
					);
					?>
				</p>
			</div>
			<div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
				<button type="button" id="bb-seo-migrate-btn" class="button button-primary" style="white-space:nowrap;">
					⚡ মাইগ্রেট করুন (<span id="bb-seo-migrate-count"><?php echo esc_html( $count ); ?></span>টি)
				</button>
				<button type="button" id="bb-seo-migrate-dismiss" class="button" style="white-space:nowrap;">
					পরে করবো
				</button>
			</div>
		</div>
		<div id="bb-seo-migrate-progress" style="display:none;margin-top:12px;">
			<div style="background:#e0e0e0;border-radius:3px;height:6px;overflow:hidden;">
				<div id="bb-seo-migrate-bar" style="height:100%;width:0;background:#0073aa;border-radius:3px;transition:width 0.3s ease;"></div>
			</div>
			<p id="bb-seo-migrate-status" style="margin:6px 0 0;font-size:13px;color:#50575e;"></p>
		</div>
	</div>

	<script>
	(function() {
		'use strict';

		var btn      = document.getElementById('bb-seo-migrate-btn');
		var dismiss  = document.getElementById('bb-seo-migrate-dismiss');
		var notice   = document.getElementById('bb-seo-migration-notice');
		var progress = document.getElementById('bb-seo-migrate-progress');
		var bar      = document.getElementById('bb-seo-migrate-bar');
		var status   = document.getElementById('bb-seo-migrate-status');

		if (!btn) return;

		btn.addEventListener('click', function() {
			btn.disabled = true;
			btn.textContent = '⏳ মাইগ্রেট হচ্ছে...';
			progress.style.display = 'block';
			status.textContent = 'শুরু হচ্ছে...';

			runBatch(0);
		});

		dismiss.addEventListener('click', function() {
			// AJAX dismiss.
			var fd = new FormData();
			fd.append('action', 'bb_seo_dismiss_migration');
			fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'bb_seo_migration' ) ); ?>');
			fetch(ajaxurl, { method: 'POST', body: fd });
			notice.style.display = 'none';
		});

		function runBatch(offset) {
			var fd = new FormData();
			fd.append('action', 'bb_seo_migrate_yoast');
			fd.append('offset', offset);
			fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'bb_seo_migration' ) ); ?>');

			fetch(ajaxurl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (!data.success) {
						status.textContent = '❌ ত্রুটি: ' + (data.data || 'অজানা সমস্যা');
						btn.disabled = false;
						return;
					}

					var d    = data.data;
					var pct  = Math.round((d.processed / d.total) * 100);
					bar.style.width = pct + '%';
					status.textContent = d.processed + ' / ' + d.total + ' পোস্ট প্রসেস হয়েছে...';

					if (d.done) {
						bar.style.width = '100%';
						bar.style.background = '#00a32a';
						status.textContent = '✅ সফল! ' + d.migrated + ' টি পোস্টের Yoast ডেটা কপি হয়েছে।';
						btn.textContent = '✅ সম্পন্ন';
						dismiss.style.display = 'none';

						// Auto-hide after 5 seconds.
						setTimeout(function() {
							notice.style.transition = 'opacity 0.5s';
							notice.style.opacity = '0';
							setTimeout(function() { notice.style.display = 'none'; }, 500);
						}, 5000);
					} else {
						// Next batch.
						runBatch(d.processed);
					}
				})
				.catch(function(err) {
					status.textContent = '❌ নেটওয়ার্ক ত্রুটি: ' + err.message;
					btn.disabled = false;
				});
		}
	})();
	</script>
	<?php
}
add_action( 'admin_notices', 'bb_seo_migration_notice' );

/**
 * AJAX handler: Migrate Yoast data in batches of 50.
 */
function bb_seo_ajax_migrate_yoast() {
	check_ajax_referer( 'bb_seo_migration', '_wpnonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'অনুমতি নেই।' );
	}

	global $wpdb;

	$offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch     = 50;
	$map       = bb_seo_yoast_map();
	$migrated  = 0;

	// Get total count of posts with any Yoast data.
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT p.ID)
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} ym
		   ON p.ID = ym.post_id
		   AND ym.meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_focuskw','_yoast_wpseo_keywordsynonyms')
		   AND ym.meta_value != ''
		 WHERE p.post_status IN ('publish','draft','pending','future','private')
		   AND p.post_type IN ('post','page')"
	);

	// Get this batch of post IDs.
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT p.ID
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} ym
		   ON p.ID = ym.post_id
		   AND ym.meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_focuskw','_yoast_wpseo_keywordsynonyms')
		   AND ym.meta_value != ''
		 WHERE p.post_status IN ('publish','draft','pending','future','private')
		   AND p.post_type IN ('post','page')
		 ORDER BY p.ID ASC
		 LIMIT %d OFFSET %d",
		$batch,
		$offset
	) );

	foreach ( $post_ids as $pid ) {
		$did_migrate = false;

		foreach ( $map as $bb_key => $yoast_key ) {
			// Skip if our key already has a value.
			$existing = get_post_meta( $pid, $bb_key, true );
			if ( $existing ) {
				continue;
			}

			$yoast_value = get_post_meta( $pid, $yoast_key, true );
			if ( ! $yoast_value ) {
				continue;
			}

			// Convert Yoast synonyms from JSON array to comma string.
			if ( 'bb_keyphrase_synonyms' === $bb_key ) {
				$decoded = json_decode( $yoast_value, true );
				if ( is_array( $decoded ) ) {
					$yoast_value = implode( ', ', array_filter( $decoded ) );
				}
			}

			// Convert Yoast %%variable%% format to our %variable% format.
			if ( 'bb_seo_title' === $bb_key ) {
				$yoast_value = str_replace(
					array( '%%title%%', '%%sep%%', '%%sitename%%', '%%page%%', '%%primary_category%%' ),
					array( '%title%',   '%sep%',   '%sitename%',   '',         '' ),
					$yoast_value
				);
			}

			update_post_meta( $pid, $bb_key, sanitize_text_field( $yoast_value ) );
			$did_migrate = true;
		}

		if ( $did_migrate ) {
			$migrated++;
		}
	}

	$processed = $offset + count( $post_ids );
	$done      = $processed >= $total;

	// Mark as complete.
	if ( $done ) {
		update_option( 'bb_seo_yoast_migrated', true );
	}

	wp_send_json_success( array(
		'total'     => $total,
		'processed' => $processed,
		'migrated'  => $migrated,
		'done'      => $done,
	) );
}
add_action( 'wp_ajax_bb_seo_migrate_yoast', 'bb_seo_ajax_migrate_yoast' );

/**
 * AJAX handler: Dismiss the migration notice.
 */
function bb_seo_ajax_dismiss_migration() {
	check_ajax_referer( 'bb_seo_migration', '_wpnonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}

	update_option( 'bb_seo_yoast_migrated', true );
	wp_send_json_success();
}
add_action( 'wp_ajax_bb_seo_dismiss_migration', 'bb_seo_ajax_dismiss_migration' );
