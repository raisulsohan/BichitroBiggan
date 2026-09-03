<?php
/**
 * ফ্রন্টএন্ড এসইও লেয়ার — টাইটেল ট্যাগ, রোবটস, ক্যানোনিকাল ও JSON-LD স্কিমা।
 *
 * seo-meta-box.php এডিটরের বক্স ও <head> মেটা ট্যাগ সামলায়।
 * এই ফাইলটি তার উপরে বসে বাকি অংশটা করে:
 *
 *   • <title> ট্যাগে কাস্টম SEO টাইটেল বসানো (pre_get_document_title)
 *   • robots ডিরেক্টিভ (wp_robots ফিল্টার — কোরের ট্যাগেই যোগ হয়, ডুপ্লিকেট নয়)
 *   • একটিমাত্র ক্যানোনিকাল ট্যাগ (কোরের rel_canonical সরিয়ে সব পেজে নিজেরা দিই)
 *   • NewsArticle / WebPage / Organization / WebSite / BreadcrumbList JSON-LD
 *
 * নিরাপত্তার নিয়ম: কোনো ডেডিকেটেড এসইও প্লাগইন (Yoast, Rank Math, AIOSEO,
 * SEOPress) সক্রিয় থাকলে এই ফাইলের কিছুই আউটপুট হয় না — যাতে কখনো দুটো
 * টাইটেল, দুটো ক্যানোনিকাল বা দুটো স্কিমা গ্রাফ তৈরি না হয়।
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. Guards — কখন আমরা <head> স্পর্শ করব
 * ======================================================================= */

/**
 * কোনো ডেডিকেটেড এসইও প্লাগইন <head> সামলাচ্ছে কি না।
 *
 * @return bool
 */
function bb_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' )        // Yoast SEO.
		|| defined( 'RANK_MATH_VERSION' )    // Rank Math.
		|| defined( 'FLAVOR_SEO_VERSION' )   // The SEO Framework fork.
		|| defined( 'AIOSEO_VERSION' )       // All in One SEO.
		|| defined( 'SEOPRESS_VERSION' );    // SEOPress.
}

/**
 * এই রিকোয়েস্টে আমাদের মেটা ট্যাগ আউটপুট করা উচিত কি না।
 *
 * সার্চ রেজাল্ট ও 404 বাদ — সেগুলো ইনডেক্স হওয়ার কথাই নয়, তাই ক্যানোনিকাল
 * বা OG ট্যাগ দিলে উল্টো ক্ষতি। ওদের noindex কোর নিজেই দেয়।
 *
 * @return bool
 */
function bb_seo_should_output() {
	if ( bb_seo_plugin_active() ) {
		return false;
	}

	if ( is_feed() || is_404() || is_search() || is_trackback() ) {
		return false;
	}

	if ( function_exists( 'is_embed' ) && is_embed() ) {
		return false;
	}

	return true;
}

/* =========================================================================
 * 2. Context — বর্তমান পেজের টাইটেল, বর্ণনা, ক্যানোনিকাল ও ছবি
 * ======================================================================= */

/**
 * নন-সিঙ্গুলার পেজের জন্য ফলব্যাক OG ছবি (কাস্টম লোগো, নাহলে সাইট আইকন)।
 *
 * @return string
 */
function bb_seo_fallback_image() {
	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $logo_id ) {
		$img = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( $img ) {
			return $img[0];
		}
	}

	$icon = get_site_icon_url( 512 );

	return $icon ? $icon : '';
}

/**
 * দুই টুকরো টেক্সট সেপারেটর দিয়ে জোড়া দেয়, ফাঁকা অংশ বাদ দিয়ে।
 *
 * @param array $parts টেক্সট টুকরো।
 * @return string
 */
function bb_seo_join_title( array $parts ) {
	$parts = array_filter( array_map( 'trim', $parts ) );

	return implode( ' — ', $parts );
}

/**
 * বর্তমান আর্কাইভের বেস URL — পেজিনেশন সহ।
 *
 * পেজ ২-এর ক্যানোনিকাল পেজ ১-এ পাঠানো একটি পরিচিত এসইও ভুল, তাই paged > 1
 * হলে get_pagenum_link() দিয়ে আসল পেজড URL-ই দিই।
 *
 * @param string $base  পেজ ১-এর URL।
 * @param int    $paged বর্তমান পেজ নম্বর।
 * @return string
 */
function bb_seo_paged_url( $base, $paged ) {
	if ( $paged > 1 ) {
		$link = get_pagenum_link( $paged );

		return $link ? $link : $base;
	}

	return $base;
}

/**
 * বর্তমান রিকোয়েস্টের এসইও কনটেক্সট তৈরি করে।
 *
 * সিঙ্গুলার পোস্ট/পেজ, হোমপেজ, ব্লগ ইনডেক্স ও সব আর্কাইভ কভার করে।
 *
 * @return array{title:string,description:string,canonical:string,image:string,og_type:string,post:?WP_Post}
 */
function bb_seo_get_context() {
	$site_name = get_bloginfo( 'name' );
	$tagline   = get_bloginfo( 'description' );
	$paged     = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

	$ctx = array(
		'title'       => '',
		'description' => '',
		'canonical'   => '',
		'image'       => '',
		'og_type'     => 'website',
		'post'        => null,
	);

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return $ctx;
		}

		$ctx['post']      = $post;
		$ctx['og_type']   = ( 'post' === $post->post_type ) ? 'article' : 'website';
		$ctx['canonical'] = (string) get_permalink( $post );

		$custom_title  = bb_seo_get_field( $post->ID, 'bb_seo_title' );
		$ctx['title']  = $custom_title
			? bb_seo_resolve_variables( $custom_title, $post )
			: bb_seo_join_title( array( $post->post_title, $site_name ) );

		$custom_desc        = bb_seo_get_field( $post->ID, 'bb_seo_description' );
		$ctx['description'] = $custom_desc
			? $custom_desc
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '...' );

		if ( has_post_thumbnail( $post ) ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'large' );
			if ( $img ) {
				$ctx['image'] = $img[0];
			}
		}
	} elseif ( is_front_page() ) {
		$ctx['title']       = bb_seo_join_title( array( $site_name, $tagline ) );
		$ctx['description'] = $tagline;
		$ctx['canonical']   = bb_seo_paged_url( home_url( '/' ), $paged );
	} elseif ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		$base       = $posts_page ? (string) get_permalink( $posts_page ) : home_url( '/' );

		$ctx['title']       = bb_seo_join_title(
			array( $posts_page ? get_the_title( $posts_page ) : __( 'ব্লগ', 'bichitro-biggan' ), $site_name )
		);
		$ctx['description'] = $posts_page ? wp_strip_all_tags( get_the_excerpt( $posts_page ) ) : $tagline;
		$ctx['canonical']   = bb_seo_paged_url( $base, $paged );
	} else {
		$ctx = bb_seo_archive_context( $ctx, $site_name, $paged );
	}

	if ( ! $ctx['image'] ) {
		$ctx['image'] = bb_seo_fallback_image();
	}

	$ctx['description'] = trim( wp_strip_all_tags( (string) $ctx['description'] ) );

	if ( $ctx['description'] ) {
		// bb_str_sub(): inc/live-search.php-এর মাল্টিবাইট-সেফ হেল্পার, mbstring
		// না থাকা হোস্টেও ফ্যাটাল হয় না।
		$ctx['description'] = bb_str_sub( $ctx['description'], 0, 160 );
	}

	/**
	 * এসইও কনটেক্সট ফিল্টার করার সুযোগ।
	 *
	 * @param array $ctx কনটেক্সট।
	 */
	return apply_filters( 'bb_seo_context', $ctx );
}

/**
 * আর্কাইভ পেজের কনটেক্সট (ক্যাটাগরি, ট্যাগ, ট্যাক্সোনমি, লেখক, তারিখ, CPT)।
 *
 * অচেনা কোনো আর্কাইভ টাইপ হলে ক্যানোনিকাল ফাঁকা রেখে দিই — ভুল ক্যানোনিকাল
 * দেওয়ার চেয়ে না দেওয়াই নিরাপদ।
 *
 * @param array  $ctx       আগের কনটেক্সট।
 * @param string $site_name সাইটের নাম।
 * @param int    $paged     বর্তমান পেজ নম্বর।
 * @return array
 */
function bb_seo_archive_context( array $ctx, $site_name, $paged ) {
	if ( ! is_archive() ) {
		return $ctx;
	}

	$base = '';

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$link               = get_term_link( $term );
			$base               = is_wp_error( $link ) ? '' : (string) $link;
			$ctx['description'] = term_description( $term );
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();

		if ( $author instanceof WP_User ) {
			$base               = (string) get_author_posts_url( $author->ID );
			$ctx['description'] = get_the_author_meta( 'description', $author->ID );
		}
	} elseif ( is_post_type_archive() ) {
		$link = get_post_type_archive_link( get_query_var( 'post_type' ) );
		$base = $link ? (string) $link : '';
	} elseif ( is_day() ) {
		$base = (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
	} elseif ( is_month() ) {
		$base = (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
	} elseif ( is_year() ) {
		$base = (string) get_year_link( (int) get_query_var( 'year' ) );
	}

	$archive_title = wp_strip_all_tags( get_the_archive_title() );

	$ctx['title'] = bb_seo_join_title(
		array(
			$archive_title,
			$paged > 1 ? sprintf( __( 'পৃষ্ঠা %d', 'bichitro-biggan' ), $paged ) : '',
			$site_name,
		)
	);

	if ( ! $ctx['description'] ) {
		$ctx['description'] = wp_strip_all_tags( get_the_archive_description() );
	}

	if ( ! $ctx['description'] && $archive_title ) {
		/* translators: %1$s: archive title, %2$s: site name. */
		$ctx['description'] = sprintf( __( '%1$s বিষয়ে %2$s-এর সব লেখা।', 'bichitro-biggan' ), $archive_title, $site_name );
	}

	if ( $base ) {
		$ctx['canonical'] = bb_seo_paged_url( $base, $paged );
	}

	return $ctx;
}

/* =========================================================================
 * 3. <title> ট্যাগ — এডিটরে লেখা SEO টাইটেল আসলেই গুগলে যায়
 * ======================================================================= */

/**
 * কাস্টম SEO টাইটেল থাকলে সেটিই <title> ট্যাগ হিসেবে ব্যবহার করে।
 *
 * pre_get_document_title ফিল্টার নন-এম্পটি কিছু রিটার্ন করলে ওয়ার্ডপ্রেস
 * নিজের টাইটেল বানানো বাদ দেয়। তাই কাস্টম টাইটেল না থাকলে আমরা $title
 * (ফাঁকা স্ট্রিং) ফেরত দিই — অর্থাৎ আগের ব্যবহারে বিন্দুমাত্র পরিবর্তন হয় না।
 *
 * @param string $title বর্তমান টাইটেল (ডিফল্ট ফাঁকা)।
 * @return string
 */
function bb_seo_document_title( $title ) {
	if ( bb_seo_plugin_active() || is_admin() || is_feed() ) {
		return $title;
	}

	if ( ! is_singular() ) {
		return $title;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return $title;
	}

	$custom = bb_seo_get_field( $post->ID, 'bb_seo_title' );

	if ( ! $custom ) {
		return $title;
	}

	$resolved = trim( bb_seo_resolve_variables( $custom, $post ) );

	return $resolved ? $resolved : $title;
}
add_filter( 'pre_get_document_title', 'bb_seo_document_title' );

/* =========================================================================
 * 4. robots ডিরেক্টিভ — কোরের wp_robots ট্যাগেই যোগ হয়
 * ======================================================================= */

/**
 * robots ডিরেক্টিভ সমৃদ্ধ করে।
 *
 * নিজে আলাদা <meta name="robots"> ছাপাই না — ওয়ার্ডপ্রেস ৫.৭+ নিজেই একটা
 * দেয়, আমরা শুধু সেটার ভেতরের ডিরেক্টিভ ফিল্টার করি। ফলে ডুপ্লিকেট ট্যাগ
 * তৈরি হওয়ার সম্ভাবনা নেই।
 *
 * noindex কেবল তখনই বসে যখন পোস্টে স্পষ্টভাবে সেট করা আছে — সাইটের কোনো
 * অংশ ভুল করে ডিইনডেক্স হওয়ার ঝুঁকি নেই।
 *
 * @param array $robots ডিরেক্টিভের অ্যারে।
 * @return array
 */
function bb_seo_robots( $robots ) {
	if ( bb_seo_plugin_active() ) {
		return $robots;
	}

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$noindex = get_post_meta( $post_id, 'bb_seo_noindex', true );

		// Yoast থেকে আসা পুরনো সেটিং যাতে কাজ করা বন্ধ না করে।
		if ( '' === $noindex ) {
			$noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		}

		if ( '1' === (string) $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;

			unset( $robots['index'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );

			return $robots;
		}
	}

	// ইনডেক্সযোগ্য পেজে গুগলকে পূর্ণ স্নিপেট ও বড় ছবি দেখানোর অনুমতি।
	if ( empty( $robots['noindex'] ) ) {
		$robots['max-snippet']       = -1;
		$robots['max-image-preview'] = 'large';
		$robots['max-video-preview'] = -1;
	}

	return $robots;
}
add_filter( 'wp_robots', 'bb_seo_robots' );

/* =========================================================================
 * 5. ক্যানোনিকাল — সাইটে একটিমাত্র থাকবে
 * ======================================================================= */

/**
 * কোরের rel_canonical() সরিয়ে দেয়।
 *
 * কোর সিঙ্গুলার পেজে নিজেই একটা <link rel="canonical"> দেয়, আর আমাদের
 * seo-meta-box.php-ও একটা দিত — অর্থাৎ প্রতিটি পোস্টে দুটো ক্যানোনিকাল
 * যাচ্ছিল। এখন কোরেরটা সরিয়ে আমরা সব পেজে একটাই দিই।
 *
 * এসইও প্লাগইন সক্রিয় থাকলে কিছুই সরাই না — তখন প্লাগইনই সব সামলাবে।
 */
function bb_seo_manage_core_canonical() {
	if ( bb_seo_plugin_active() ) {
		return;
	}

	remove_action( 'wp_head', 'rel_canonical' );
}
add_action( 'wp', 'bb_seo_manage_core_canonical' );

/* =========================================================================
 * 6. JSON-LD স্ট্রাকচার্ড ডাটা
 * ======================================================================= */

/**
 * JSON-LD এনকোড করার নিরাপদ ফ্ল্যাগ।
 *
 * JSON_HEX_TAG ও JSON_HEX_AMP থাকায় কনটেন্টে `</script>` জাতীয় কিছু থাকলেও
 * স্ক্রিপ্ট ট্যাগ ভেঙে বেরোতে পারে না। JSON_UNESCAPED_UNICODE রাখায় বাংলা
 * লেখা `\uXXXX` না হয়ে পড়ার মতো অবস্থায় থাকে।
 *
 * @return int
 */
function bb_seo_json_flags() {
	return JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
}

/**
 * সাইটের Organization নোড (প্রকাশক)।
 *
 * @return array
 */
function bb_seo_organization_node() {
	$node = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);

	$tagline = get_bloginfo( 'description' );

	if ( $tagline ) {
		$node['description'] = $tagline;
	}

	$logo = bb_seo_fallback_image();

	if ( $logo ) {
		$node['logo'] = array(
			'@type' => 'ImageObject',
			'@id'   => home_url( '/#logo' ),
			'url'   => $logo,
		);
		// Google Organization-এর image হিসেবেও লোগোটাই আশা করে।
		$node['image'] = array( '@id' => home_url( '/#logo' ) );
	}

	$same_as = array_filter(
		array(
			get_theme_mod( 'bb_youtube_url' ),
			get_theme_mod( 'bb_footer_youtube_url' ),
		)
	);

	$same_as = array_values( array_unique( array_map( 'esc_url_raw', $same_as ) ) );

	if ( $same_as ) {
		$node['sameAs'] = $same_as;
	}

	return $node;
}

/**
 * সিঙ্গুলার পোস্ট/পেজের প্রধান নোড।
 *
 * পোস্ট হলে NewsArticle (বিজ্ঞান ম্যাগাজিনের জন্য সবচেয়ে মানানসই),
 * পেজ হলে সাধারণ WebPage।
 *
 * @param array   $ctx  এসইও কনটেক্সট।
 * @param WP_Post $post পোস্ট অবজেক্ট।
 * @return array
 */
function bb_seo_article_node( array $ctx, WP_Post $post ) {
	$is_post = ( 'post' === $post->post_type );
	$url     = $ctx['canonical'] ? $ctx['canonical'] : (string) get_permalink( $post );

	// Google NewsArticle-এর headline ১১০ ক্যারেক্টারের বেশি হলে উপেক্ষা করে।
	$headline = wp_strip_all_tags( $post->post_title );
	$headline = bb_str_sub( $headline, 0, 110 );

	$node = array(
		'@type'            => $is_post ? 'NewsArticle' : 'WebPage',
		'@id'              => $url . '#article',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => $url,
		),
		'headline'         => $headline,
		'url'              => $url,
		'datePublished'    => get_the_date( DATE_W3C, $post ),
		'dateModified'     => get_the_modified_date( DATE_W3C, $post ),
		'inLanguage'       => get_bloginfo( 'language' ),
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
	);

	if ( $ctx['description'] ) {
		$node['description'] = $ctx['description'];
	}

	if ( $ctx['image'] ) {
		$node['image'] = array(
			'@type' => 'ImageObject',
			'url'   => $ctx['image'],
		);
	}

	$author_id = (int) $post->post_author;

	if ( $author_id ) {
		$node['author'] = array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $author_id ),
			'url'   => get_author_posts_url( $author_id ),
		);
	}

	if ( $is_post ) {
		$categories = get_the_category( $post->ID );

		if ( $categories && ! is_wp_error( $categories ) ) {
			$node['articleSection'] = $categories[0]->name;
		}

		// str_word_count() বাংলা ইউনিকোড গুনতে পারে না, তাই ইউনিকোড-সেফ স্প্লিট।
		$words      = preg_split( '/\s+/u', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), -1, PREG_SPLIT_NO_EMPTY );
		$word_count = is_array( $words ) ? count( $words ) : 0;

		if ( $word_count > 0 ) {
			$node['wordCount'] = $word_count;
		}
	}

	return $node;
}

/**
 * BreadcrumbList নোড — হোম ➔ ক্যাটাগরি ➔ লেখা।
 *
 * @param array $ctx এসইও কনটেক্সট।
 * @return array|null আইটেম দুটোরও কম হলে null (একটিমাত্র ধাপের ব্রেডক্রাম্ব অর্থহীন)।
 */
function bb_seo_breadcrumb_node( array $ctx ) {
	$items = array(
		array(
			'name' => __( 'হোম', 'bichitro-biggan' ),
			'url'  => home_url( '/' ),
		),
	);

	$post = $ctx['post'];

	if ( $post instanceof WP_Post ) {
		if ( 'post' === $post->post_type ) {
			$categories = get_the_category( $post->ID );

			if ( $categories && ! is_wp_error( $categories ) ) {
				$term_link = get_term_link( $categories[0] );

				if ( ! is_wp_error( $term_link ) ) {
					$items[] = array(
						'name' => $categories[0]->name,
						'url'  => (string) $term_link,
					);
				}
			}
		}

		$items[] = array(
			'name' => wp_strip_all_tags( $post->post_title ),
			'url'  => $ctx['canonical'] ? $ctx['canonical'] : (string) get_permalink( $post ),
		);
	} elseif ( is_archive() ) {
		$archive_title = wp_strip_all_tags( get_the_archive_title() );

		if ( $archive_title && $ctx['canonical'] ) {
			$items[] = array(
				'name' => $archive_title,
				'url'  => $ctx['canonical'],
			);
		}
	}

	if ( count( $items ) < 2 ) {
		return null;
	}

	$list = array();

	foreach ( $items as $position => $item ) {
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $position + 1,
			'name'     => $item['name'],
			'item'     => $item['url'],
		);
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => ( $ctx['canonical'] ? $ctx['canonical'] : home_url( '/' ) ) . '#breadcrumb',
		'itemListElement' => $list,
	);
}

/**
 * পুরো স্কিমা গ্রাফ <head>-এ আউটপুট করে।
 *
 * সব নোড একটি @graph-এ থাকে, তাই পেজে একটিমাত্র ld+json স্ক্রিপ্ট ট্যাগ যায়।
 */
function bb_seo_output_schema() {
	if ( ! bb_seo_should_output() ) {
		return;
	}

	$ctx   = bb_seo_get_context();
	$graph = array( bb_seo_organization_node() );

	// WebSite নোড — সব পেজেই থাকে, Organization-এর সাথে বাঁধা।
	$graph[] = array(
		'@type'      => 'WebSite',
		'@id'        => home_url( '/#website' ),
		'url'        => home_url( '/' ),
		'name'       => get_bloginfo( 'name' ),
		'inLanguage' => get_bloginfo( 'language' ),
		'publisher'  => array( '@id' => home_url( '/#organization' ) ),
	);

	if ( $ctx['post'] instanceof WP_Post ) {
		$graph[] = bb_seo_article_node( $ctx, $ctx['post'] );
	} elseif ( $ctx['canonical'] ) {
		$page_node = array(
			'@type'      => is_front_page() ? 'WebPage' : 'CollectionPage',
			'@id'        => $ctx['canonical'],
			'url'        => $ctx['canonical'],
			'name'       => $ctx['title'],
			'inLanguage' => get_bloginfo( 'language' ),
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		);

		if ( $ctx['description'] ) {
			$page_node['description'] = $ctx['description'];
		}

		$graph[] = $page_node;
	}

	$breadcrumb = bb_seo_breadcrumb_node( $ctx );

	if ( $breadcrumb ) {
		$graph[] = $breadcrumb;
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	$json = wp_json_encode( $payload, bb_seo_json_flags() );

	if ( ! $json ) {
		return;
	}

	echo "\n<script type=\"application/ld+json\">" . $json . "</script>\n";
}
add_action( 'wp_head', 'bb_seo_output_schema', 5 );
