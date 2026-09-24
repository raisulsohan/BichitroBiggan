<?php
/**
 * The English edition — bichitrobiggan.com/en
 *
 * One WordPress install read two ways. Every address under /en is the same
 * post, category or archive as the Bengali one; what changes is which fields
 * are printed, which language the interface speaks, and how dates and numbers
 * are written.
 *
 * The prefix is taken off the request before WordPress parses it and put back
 * on by a home_url() filter, so permalinks, pagination, category links, the
 * search form, the feed and the reading modal all carry /en without a single
 * template knowing this file exists.
 *
 * A post appears in the English edition only when its English fields are
 * filled in and "Ready" is ticked. Everything else stays Bengali-only, and
 * /en never shows a Bengali article pretending to be English.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The single path segment the English edition lives under. */
function bb_en_prefix() {
	return (string) apply_filters( 'bb_en_prefix', 'en' );
}

/* -------------------------------------------------------------------------
 * 1. Which language is this request?
 * ---------------------------------------------------------------------- */

/**
 * Read — or set — the language of the request being rendered.
 *
 * The REST search endpoint and the language switcher both need to render
 * something "as" the other language for a moment, so this doubles as a setter.
 *
 * @param string|null $set 'en' or 'bn' to switch, null to read.
 * @return string 'en' or 'bn'.
 */
function bb_lang( $set = null ) {
	static $lang = 'bn';

	if ( null !== $set ) {
		$lang = ( 'en' === $set ) ? 'en' : 'bn';
	}

	return $lang;
}

/** True while the English edition is being rendered. */
function bb_is_en() {
	return 'en' === bb_lang();
}

/**
 * Run something as if the other language were being served.
 *
 * @param string   $lang     'en' or 'bn'.
 * @param callable $callback What to run.
 * @return mixed Whatever the callback returned.
 */
function bb_en_in_lang( $lang, $callback ) {
	$previous = bb_lang();
	bb_lang( $lang );

	try {
		$result = call_user_func( $callback );
	} finally {
		bb_lang( $previous );
	}

	return $result;
}

/**
 * The request path with the /en prefix already taken off, kept so the
 * hreflang tags and the switcher can rebuild either address.
 *
 * @param string|null $set Internal — set during detection.
 * @return string e.g. "/category/space/page/2/".
 */
function bb_en_neutral_uri( $set = null ) {
	static $uri = '/';

	if ( null !== $set ) {
		$uri = $set;
	}

	return $uri;
}

/**
 * The path exactly as the reader asked for it, before the prefix was taken
 * off — the only way to tell /en from /en/ once WordPress has the request.
 *
 * @param string|null $set Internal — set during detection.
 * @return string
 */
function bb_en_original_path( $set = null ) {
	static $path = '/';

	if ( null !== $set ) {
		$path = $set;
	}

	return $path;
}

/**
 * Spot /en at the front of the request and take it off.
 *
 * This runs while functions.php is still being read — before WordPress parses
 * the request and before the theme's text domain is loaded — because the
 * locale of the whole page depends on the answer.
 */
function bb_en_detect_request() {
	if ( ( is_admin() && ! wp_doing_ajax() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' === $request ) {
		return;
	}

	$path  = (string) wp_parse_url( $request, PHP_URL_PATH );
	$query = (string) wp_parse_url( $request, PHP_URL_QUERY );

	bb_en_original_path( $path );

	// The install may live in a subdirectory; only what comes after it counts.
	$home = trim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
	$rest = trim( $path, '/' );

	if ( '' !== $home ) {
		if ( $rest !== $home && 0 !== strpos( $rest, $home . '/' ) ) {
			return;
		}
		$rest = trim( substr( $rest, strlen( $home ) ), '/' );
	}

	$segments = ( '' === $rest ) ? array() : explode( '/', $rest );
	$prefix   = bb_en_prefix();

	bb_en_neutral_uri( '/' . ( '' === $rest ? '' : $rest . '/' ) );

	if ( empty( $segments ) || $segments[0] !== $prefix ) {
		return;
	}

	array_shift( $segments );
	bb_lang( 'en' );

	$neutral = empty( $segments ) ? '/' : '/' . implode( '/', $segments ) . '/';
	bb_en_neutral_uri( $neutral );

	// What WordPress will now parse: the same request, minus the prefix.
	$rebuilt = ( '' === $home ? '' : '/' . $home ) . $neutral;

	if ( '' !== $query ) {
		$rebuilt .= '?' . $query;
	}

	$_SERVER['REQUEST_URI'] = $rebuilt;
}
bb_en_detect_request();

/* -------------------------------------------------------------------------
 * 2. Addresses
 * ---------------------------------------------------------------------- */

/**
 * Put /en back into every link the site builds from home_url().
 *
 * @param string      $url    The finished URL.
 * @param string      $path   Path passed to home_url().
 * @param string|null $scheme Scheme asked for.
 * @return string
 */
function bb_en_home_url( $url, $path = '', $scheme = null ) {
	if ( ! bb_is_en() ) {
		return $url;
	}

	if ( in_array( $scheme, array( 'rest', 'admin', 'login', 'login_post', 'rpc' ), true ) ) {
		return $url;
	}

	$parts = wp_parse_url( $url );

	if ( empty( $parts ) ) {
		return $url;
	}

	if ( ! isset( $parts['path'] ) ) {
		$parts['path'] = '/';
	}

	$home = trim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
	$rest = trim( $parts['path'], '/' );

	if ( '' !== $home ) {
		if ( $rest !== $home && 0 !== strpos( $rest, $home . '/' ) ) {
			return $url;
		}
		$rest = trim( substr( $rest, strlen( $home ) ), '/' );
	}

	// WordPress's own machinery, never the reader's side of the site.
	$reserved = array( 'wp-json', 'wp-admin', 'wp-content', 'wp-includes', 'wp-login.php', 'wp-cron.php', 'xmlrpc.php', 'wp-signup.php' );
	$first    = ( '' === $rest ) ? '' : strtok( $rest, '/' );

	if ( in_array( $first, $reserved, true ) || bb_en_prefix() === $first ) {
		return $url;
	}

	$new_path = '/' . ( '' === $home ? '' : $home . '/' ) . bb_en_prefix() . ( '' === $rest ? '/' : '/' . $rest );

	if ( '' !== $rest && '/' === substr( $parts['path'], -1 ) ) {
		$new_path .= '/';
	}

	$tail = $new_path
		. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
		. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );

	// home_url( $path, 'relative' ) hands us a bare path, with no host to keep.
	if ( empty( $parts['host'] ) ) {
		return $tail;
	}

	return ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//' )
		. $parts['host']
		. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
		. $tail;
}
add_filter( 'home_url', 'bb_en_home_url', 10, 3 );

/** The query string of the current request, "?" included, or "". */
function bb_en_current_query() {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$query   = (string) wp_parse_url( $request, PHP_URL_QUERY );

	return ( '' === $query ) ? '' : '?' . $query;
}

/** The address being served right now, prefix and all. */
function bb_en_current_url() {
	return home_url( bb_en_neutral_uri() ) . bb_en_current_query();
}

/**
 * This page's address in the language asked for, or '' when there is none.
 *
 * @param string $lang 'en' or 'bn'.
 * @return string
 */
function bb_en_url_for( $lang, $with_query = true ) {
	$lang = ( 'en' === $lang ) ? 'en' : 'bn';

	if ( is_singular() && ! is_front_page() ) {
		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			return '';
		}

		if ( 'en' === $lang && ! bb_en_has( $post_id ) ) {
			return '';
		}

		return (string) bb_en_in_lang(
			$lang,
			function () use ( $post_id ) {
				return get_permalink( $post_id );
			}
		);
	}

	$path = bb_en_neutral_uri();

	$base = (string) bb_en_in_lang(
		$lang,
		function () use ( $path ) {
			return home_url( $path );
		}
	);

	return $with_query ? $base . bb_en_current_query() : $base;
}

/* -------------------------------------------------------------------------
 * 3. The interface language
 * ---------------------------------------------------------------------- */

/**
 * Serve the English edition in en_US, which brings the theme's own
 * translation file and every core string — the comment form, month names,
 * "Leave a Reply" — across with it.
 *
 * @param string $locale Locale WordPress worked out.
 * @return string
 */
function bb_en_locale( $locale ) {
	if ( bb_is_en() && ! is_admin() ) {
		return 'en_US';
	}

	return $locale;
}
add_filter( 'locale', 'bb_en_locale' );

/**
 * Load the theme's English strings by hand.
 *
 * The file is languages/en.mo, not en_US.mo, on purpose: WordPress loads
 * {locale}.mo out of a theme automatically, and on an install already running
 * in en_US that would turn the Bengali site English too. Named this way it is
 * loaded here, only while /en is being served, whatever locale WordPress
 * itself is in.
 */
function bb_en_load_translations() {
	if ( ! bb_is_en() || is_admin() ) {
		return;
	}

	$mofile = get_template_directory() . '/languages/en.mo';

	if ( ! is_readable( $mofile ) ) {
		return;
	}

	unload_textdomain( 'bichitro-biggan' );
	load_textdomain( 'bichitro-biggan', $mofile );
}
add_action( 'after_setup_theme', 'bb_en_load_translations', 12 );

/**
 * <html lang> and every schema inLanguage.
 *
 * @param string $language Language tag.
 * @return string
 */
function bb_en_content_language( $language ) {
	return bb_is_en() ? 'en' : $language;
}
add_filter( 'bb_content_language', 'bb_en_content_language' );

/**
 * og:locale.
 *
 * @param string $locale Open Graph locale.
 * @return string
 */
function bb_en_og_locale( $locale ) {
	return bb_is_en() ? 'en_US' : $locale;
}
add_filter( 'bb_seo_og_locale', 'bb_en_og_locale' );

/**
 * A body class, so CSS can reach the English edition if it ever needs to.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function bb_en_body_class( $classes ) {
	if ( bb_is_en() ) {
		$classes[] = 'bb-lang-en';
	}

	return $classes;
}
add_filter( 'body_class', 'bb_en_body_class' );

/* -------------------------------------------------------------------------
 * 4. The English fields on a post
 * ---------------------------------------------------------------------- */

/**
 * Every English field, with the sanitiser each one is saved through.
 *
 * @return array<string, array{label:string,type:string,sanitize:string}>
 */
function bb_en_meta_fields() {
	return array(
		'bb_en_ready'           => array(
			'label'    => __( 'Ready — show this post in the English edition', 'bichitro-biggan' ),
			'type'     => 'checkbox',
			'sanitize' => 'bb_en_sanitize_flag',
		),
		'bb_en_title'           => array(
			'label'    => __( 'English title', 'bichitro-biggan' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'bb_en_slug'            => array(
			'label'    => __( 'English slug (the /en/… address)', 'bichitro-biggan' ),
			'type'     => 'text',
			'sanitize' => 'bb_en_sanitize_slug',
		),
		'bb_en_excerpt'         => array(
			'label'    => __( 'English excerpt', 'bichitro-biggan' ),
			'type'     => 'textarea',
			'sanitize' => 'sanitize_textarea_field',
		),
		'bb_en_content'         => array(
			'label'    => __( 'English article', 'bichitro-biggan' ),
			'type'     => 'editor',
			'sanitize' => 'wp_kses_post',
		),
		'bb_en_seo_title'       => array(
			'label'    => __( 'English SEO title', 'bichitro-biggan' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'bb_en_seo_description' => array(
			'label'    => __( 'English meta description', 'bichitro-biggan' ),
			'type'     => 'textarea',
			'sanitize' => 'sanitize_textarea_field',
		),
		'bb_en_focus_keyphrase' => array(
			'label'    => __( 'English focus keyphrase', 'bichitro-biggan' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
	);
}

/**
 * A checkbox saved as '1' or ''.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function bb_en_sanitize_flag( $value ) {
	return ( '1' === (string) $value || 'on' === $value || true === $value ) ? '1' : '';
}

/**
 * A slug that stays readable in a URL.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function bb_en_sanitize_slug( $value ) {
	$value = sanitize_title( (string) $value );

	return $value;
}

/**
 * One English field.
 *
 * @param int    $post_id Post.
 * @param string $key     Meta key.
 * @return string
 */
function bb_en_get( $post_id, $key ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return '';
	}

	return trim( (string) get_post_meta( $post_id, $key, true ) );
}

/**
 * Does this post have an English version a reader should be shown?
 *
 * @param int $post_id Post.
 * @return bool
 */
function bb_en_has( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return false;
	}

	if ( '1' !== bb_en_get( $post_id, 'bb_en_ready' ) ) {
		return false;
	}

	return ( '' !== bb_en_get( $post_id, 'bb_en_title' ) && '' !== bb_en_get( $post_id, 'bb_en_content' ) );
}

/**
 * The English title, falling back to nothing so callers can decide.
 *
 * @param int $post_id Post.
 * @return string
 */
function bb_en_title( $post_id ) {
	return bb_en_get( $post_id, 'bb_en_title' );
}

/* -------------------------------------------------------------------------
 * 5. Printing English instead of Bengali
 * ---------------------------------------------------------------------- */

/**
 * @param string $title   Post title.
 * @param int    $post_id Post ID.
 * @return string
 */
function bb_en_filter_title( $title, $post_id = 0 ) {
	if ( ! bb_is_en() || ! $post_id ) {
		return $title;
	}

	$english = bb_en_title( $post_id );

	return ( '' === $english ) ? $title : $english;
}
add_filter( 'the_title', 'bb_en_filter_title', 10, 2 );

/**
 * The article body.
 *
 * the_content is applied to plenty of text that is not a post — a term
 * description, a widget — so the body is only swapped when what came in
 * really is this post's own content.
 *
 * @param string $content Content.
 * @return string
 */
function bb_en_filter_content( $content ) {
	if ( ! bb_is_en() ) {
		return $content;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return $content;
	}

	$english = bb_en_get( $post->ID, 'bb_en_content' );

	if ( '' === $english ) {
		return $content;
	}

	$raw   = bb_en_normalize_body( $post->post_content );
	$given = bb_en_normalize_body( $content );

	// An empty body, the whole body, or the part of it before a <!--more-->.
	if ( '' !== $given && false === strpos( $raw, $given ) ) {
		return $content;
	}

	return $english;
}
add_filter( 'the_content', 'bb_en_filter_content', 1 );

/**
 * Content with the editor's own markers and spacing taken out, so the text
 * WordPress hands the filter can be recognised as the post's own body even
 * after a <!--more--> has been cut out of it.
 *
 * @param string $text Content.
 * @return string
 */
function bb_en_normalize_body( $text ) {
	$text = (string) $text;
	$text = preg_replace( '/<!--\s*more(.*?)?\s*-->/s', '', $text );
	$text = preg_replace( '/<!--\s*nextpage\s*-->/', '', $text );
	$text = preg_replace( '/\s+/u', ' ', $text );

	return trim( (string) $text );
}

/**
 * @param string  $excerpt Excerpt.
 * @param WP_Post $post    Post.
 * @return string
 */
function bb_en_filter_excerpt( $excerpt, $post = null ) {
	if ( ! bb_is_en() ) {
		return $excerpt;
	}

	$post = $post ? get_post( $post ) : get_post();

	if ( ! $post instanceof WP_Post ) {
		return $excerpt;
	}

	return bb_en_excerpt_text( $post->ID, $excerpt );
}
add_filter( 'get_the_excerpt', 'bb_en_filter_excerpt', 1, 2 );

/**
 * The English excerpt — the field when it is filled in, otherwise the opening
 * of the English article.
 *
 * @param int    $post_id  Post.
 * @param string $fallback What to return when there is no English version.
 * @param int    $words    How many words to trim to.
 * @return string
 */
function bb_en_excerpt_text( $post_id, $fallback = '', $words = 22 ) {
	$excerpt = bb_en_get( $post_id, 'bb_en_excerpt' );

	if ( '' !== $excerpt ) {
		return $excerpt;
	}

	$content = bb_en_get( $post_id, 'bb_en_content' );

	if ( '' === $content ) {
		return $fallback;
	}

	$text = wp_strip_all_tags( strip_shortcodes( $content ) );

	return wp_trim_words( $text, (int) $words, '…' );
}

/**
 * The English slug in the permalink.
 *
 * @param string  $permalink Permalink.
 * @param WP_Post $post      Post.
 * @return string
 */
function bb_en_filter_permalink( $permalink, $post = null ) {
	if ( ! bb_is_en() || ! $post instanceof WP_Post ) {
		return $permalink;
	}

	$slug = bb_en_get( $post->ID, 'bb_en_slug' );

	if ( '' === $slug || $slug === $post->post_name || '' === $post->post_name ) {
		return $permalink;
	}

	$encoded = rawurlencode( $post->post_name );

	if ( false !== strpos( $permalink, '/' . $post->post_name ) ) {
		return preg_replace( '#/' . preg_quote( $post->post_name, '#' ) . '(/|$|\?)#', '/' . $slug . '$1', $permalink, 1 );
	}

	if ( false !== strpos( $permalink, '/' . $encoded ) ) {
		return preg_replace( '#/' . preg_quote( $encoded, '#' ) . '(/|$|\?)#', '/' . $slug . '$1', $permalink, 1 );
	}

	return $permalink;
}
add_filter( 'post_link', 'bb_en_filter_permalink', 10, 2 );

/**
 * Let /en/<english-slug>/ find the post the slug belongs to.
 *
 * @param array $vars Query variables WordPress parsed out of the request.
 * @return array
 */
function bb_en_filter_request( $vars ) {
	if ( ! bb_is_en() || empty( $vars['name'] ) ) {
		return $vars;
	}

	$slug = sanitize_title( (string) $vars['name'] );

	if ( '' === $slug ) {
		return $vars;
	}

	// A real Bengali slug already resolves; leave it to WordPress.
	if ( get_page_by_path( $vars['name'], OBJECT, 'post' ) ) {
		return $vars;
	}

	$found = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => 'bb_en_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);

	if ( empty( $found ) ) {
		return $vars;
	}

	unset( $vars['name'], $vars['year'], $vars['monthnum'], $vars['day'], $vars['category_name'] );
	$vars['p'] = (int) $found[0];

	return $vars;
}
add_filter( 'request', 'bb_en_filter_request' );

/* -------------------------------------------------------------------------
 * 6. Which posts the English edition lists
 * ---------------------------------------------------------------------- */

/**
 * Keep untranslated posts out of every English list — the homepage mosaic,
 * the ticker, categories, search, the feed, the footer columns.
 *
 * @param WP_Query $query Query about to run.
 * @return void
 */
function bb_en_filter_queries( $query ) {
	if ( ! bb_is_en() || is_admin() ) {
		return;
	}

	if ( $query->is_singular() || $query->get( 'p' ) || $query->get( 'name' ) || $query->get( 'page_id' ) || $query->get( 'pagename' ) ) {
		return;
	}

	$post_type = $query->get( 'post_type' );

	if ( ! empty( $post_type ) ) {
		$types = (array) $post_type;
		$known = array_diff( $types, array( 'post', 'page', 'any' ) );

		if ( ! empty( $known ) ) {
			return;
		}
	}

	$meta_query   = (array) $query->get( 'meta_query' );
	$meta_query[] = array(
		'key'   => 'bb_en_ready',
		'value' => '1',
	);

	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'bb_en_filter_queries' );

/**
 * The previous / next article links are built with their own SQL rather than
 * a WP_Query, so pre_get_posts never sees them. Under /en they join on the
 * ready flag and skip straight over anything untranslated.
 *
 * @param string $join The JOIN clause, where the posts table is aliased "p".
 * @return string
 */
function bb_en_adjacent_post_join( $join ) {
	global $wpdb;

	if ( ! bb_is_en() ) {
		return $join;
	}

	return $join . " INNER JOIN {$wpdb->postmeta} AS bb_en_ready_meta ON ( bb_en_ready_meta.post_id = p.ID AND bb_en_ready_meta.meta_key = 'bb_en_ready' AND bb_en_ready_meta.meta_value = '1' ) ";
}
add_filter( 'get_previous_post_join', 'bb_en_adjacent_post_join' );
add_filter( 'get_next_post_join', 'bb_en_adjacent_post_join' );

/* -------------------------------------------------------------------------
 * 7. Where an address should really go
 * ---------------------------------------------------------------------- */

/**
 * WordPress's own canonical redirect measures the request against a permalink
 * that now carries /en, and would bounce every English page back and forth.
 * The English edition does its own canonicalising in bb_en_template_redirect().
 *
 * @param string $redirect Where core wants to send the reader.
 * @return string|false
 */
function bb_en_stop_core_canonical( $redirect ) {
	return bb_is_en() ? false : $redirect;
}
add_filter( 'redirect_canonical', 'bb_en_stop_core_canonical' );

/**
 * Three rules, in order:
 *
 * 1. /en with no slash becomes /en/.
 * 2. An article with no English version sends the reader to the Bengali one.
 * 3. An article reached by its Bengali slug moves to its English slug.
 */
function bb_en_template_redirect() {
	if ( ! bb_is_en() || is_admin() || wp_doing_ajax() ) {
		return;
	}

	$asked = bb_en_original_path();

	if ( '' !== $asked && '/' !== substr( $asked, -1 ) && '/' === bb_en_neutral_uri() && ! is_feed() ) {
		wp_safe_redirect( home_url( '/' ) . bb_en_current_query(), 301 );
		exit;
	}

	/*
	 * The front page is a Page too, and it has no translation of its own —
	 * everything on it is built from the posts underneath. It stays put.
	 */
	if ( ! is_singular() || is_front_page() || is_preview() || is_embed() ) {
		return;
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return;
	}

	if ( ! bb_en_has( $post_id ) ) {
		$bengali = (string) bb_en_in_lang(
			'bn',
			function () use ( $post_id ) {
				return get_permalink( $post_id );
			}
		);

		if ( $bengali ) {
			wp_safe_redirect( $bengali, 302 );
			exit;
		}

		return;
	}

	$canonical = (string) get_permalink( $post_id );

	if ( ! $canonical ) {
		return;
	}

	$current = home_url( bb_en_neutral_uri() );

	if ( untrailingslashit( urldecode( $current ) ) !== untrailingslashit( urldecode( $canonical ) ) ) {
		wp_safe_redirect( $canonical . bb_en_current_query(), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'bb_en_template_redirect', 5 );

/**
 * A dead end on the Bengali side that is really an English article.
 *
 * An English article lives at /en/<english-slug>/. Strip the prefix — a link
 * shared by hand, a script checking the slug, a crawler guessing — and the
 * address lands on the Bengali site, where that slug belongs to nothing and
 * the reader gets a 404. The article does exist, so they are sent to it.
 */
function bb_en_rescue_404() {
	if ( ! is_404() || bb_is_en() || is_admin() ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path    = trim( (string) wp_parse_url( $request, PHP_URL_PATH ), '/' );

	// One segment, and one that could be a slug at all.
	if ( '' === $path || false !== strpos( $path, '/' ) ) {
		return;
	}

	$slug = sanitize_title( $path );

	if ( '' === $slug ) {
		return;
	}

	$found = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => 'bb_en_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);

	if ( empty( $found ) || ! bb_en_has( $found[0] ) ) {
		return;
	}

	$post_id = (int) $found[0];

	$url = (string) bb_en_in_lang(
		'en',
		function () use ( $post_id ) {
			return get_permalink( $post_id );
		}
	);

	if ( ! $url ) {
		return;
	}

	wp_safe_redirect( $url, 301 );
	exit;
}
add_action( 'template_redirect', 'bb_en_rescue_404', 8 );

/* -------------------------------------------------------------------------
 * 8. Categories and tags
 * ---------------------------------------------------------------------- */

/**
 * The English name of a term, or '' when it has none.
 *
 * @param int|WP_Term $term Term or term ID.
 * @return string
 */
function bb_en_term_name( $term ) {
	$term = ( $term instanceof WP_Term ) ? $term : get_term( (int) $term );

	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$english = trim( (string) get_term_meta( $term->term_id, 'bb_en_name', true ) );

	if ( '' !== $english ) {
		return $english;
	}

	/*
	 * Nothing was typed in — but this site's slugs are already English
	 * (space-science, wonders-of-the-universe), so the slug is a better name
	 * than the Bengali one. "Nature and Environment", not "প্রকৃতি ও পরিবেশ".
	 */
	return bb_en_name_from_slug( $term->slug );
}

/**
 * "wonders-of-the-universe" => "Wonders of the Universe".
 *
 * Returns '' for a slug that is not English to begin with, so a Bengali or
 * percent-encoded slug never turns into nonsense.
 *
 * @param string $slug Term slug.
 * @return string
 */
function bb_en_name_from_slug( $slug ) {
	$slug = (string) $slug;

	if ( '' === $slug || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
		return '';
	}

	// Words English keeps in lower case inside a title.
	$small = array( 'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'of', 'on', 'or', 'the', 'to', 'vs', 'with' );
	$words = explode( '-', $slug );
	$out   = array();

	foreach ( $words as $index => $word ) {
		$out[] = ( 0 !== $index && in_array( $word, $small, true ) ) ? $word : ucfirst( $word );
	}

	return implode( ' ', $out );
}

/**
 * @param WP_Term|mixed $term Term.
 * @return WP_Term|mixed
 */
function bb_en_swap_term( $term ) {
	if ( ! bb_is_en() || ! $term instanceof WP_Term ) {
		return $term;
	}

	$english = bb_en_term_name( $term );

	if ( '' !== $english ) {
		$term->name = $english;
	}

	$description = trim( (string) get_term_meta( $term->term_id, 'bb_en_description', true ) );

	if ( '' !== $description ) {
		$term->description = $description;
	}

	return $term;
}
add_filter( 'get_term', 'bb_en_swap_term' );

/**
 * @param array $terms Terms.
 * @return array
 */
function bb_en_swap_terms( $terms ) {
	if ( ! bb_is_en() || ! is_array( $terms ) ) {
		return $terms;
	}

	foreach ( $terms as $index => $term ) {
		$terms[ $index ] = bb_en_swap_term( $term );
	}

	return $terms;
}
add_filter( 'get_terms', 'bb_en_swap_terms' );
add_filter( 'wp_get_object_terms', 'bb_en_swap_terms' );

/**
 * A post's own terms, with the tags nobody has translated yet left out.
 *
 * Categories are a fixed handful and always have an English name; tags run
 * into the thousands and are translated a postful at a time, as each article
 * is. A Bengali tag under an English article — in the list below it and in the
 * keywords it reports to search engines — is worse than no tag at all.
 *
 * @param array|WP_Error $terms    The post's terms.
 * @param int            $post_id  Post.
 * @param string         $taxonomy Which taxonomy.
 * @return array|WP_Error
 */
function bb_en_post_terms( $terms, $post_id = 0, $taxonomy = '' ) {
	$terms = bb_en_swap_terms( $terms );

	if ( ! bb_is_en() || 'post_tag' !== $taxonomy || ! is_array( $terms ) ) {
		return $terms;
	}

	$kept = array();

	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term && '' === bb_en_term_name( $term ) ) {
			continue;
		}

		$kept[] = $term;
	}

	return $kept;
}
add_filter( 'get_the_terms', 'bb_en_post_terms', 10, 3 );

/**
 * The heading on a category or tag archive.
 *
 * @param string $title Title.
 * @return string
 */
function bb_en_term_title( $title ) {
	if ( ! bb_is_en() ) {
		return $title;
	}

	$term = get_queried_object();

	if ( ! $term instanceof WP_Term ) {
		return $title;
	}

	$english = bb_en_term_name( $term );

	return ( '' === $english ) ? $title : $english;
}
add_filter( 'single_cat_title', 'bb_en_term_title' );
add_filter( 'single_tag_title', 'bb_en_term_title' );
add_filter( 'single_term_title', 'bb_en_term_title' );

/**
 * The English name and description of a category or tag, through the REST API,
 * so they can be set by the same script that publishes posts.
 */
function bb_en_register_term_meta() {
	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		register_term_meta(
			$taxonomy,
			'bb_en_name',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => 'bb_en_term_meta_can_edit',
			)
		);

		register_term_meta(
			$taxonomy,
			'bb_en_description',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => 'bb_en_term_meta_can_edit',
			)
		);
	}
}
add_action( 'init', 'bb_en_register_term_meta' );

/** Writing a term's English name needs the same right as renaming it. */
function bb_en_term_meta_can_edit() {
	return current_user_can( 'manage_categories' );
}

/**
 * A writer's English name and biography, through the REST API, for the same
 * reason: so they can be filled in by a script rather than by hand.
 */
function bb_en_register_user_meta() {
	$fields = array(
		'bb_en_display_name' => 'sanitize_text_field',
		'bb_en_description'  => 'sanitize_textarea_field',
	);

	foreach ( $fields as $key => $sanitize ) {
		register_meta(
			'user',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => function ( $allowed, $meta_key, $object_id ) {
					return current_user_can( 'edit_user', $object_id );
				},
			)
		);
	}
}
add_action( 'init', 'bb_en_register_user_meta' );

/**
 * A video embedded by pasting its address carries the title YouTube holds for
 * it — which is Bengali, because the video is. Screen readers announce that
 * title, and it is the one scrap of Bengali left on an English article, so the
 * English edition puts the article's own English title there instead.
 *
 * @param string $html    The embed markup.
 * @param string $url     The address that was pasted.
 * @param array  $attr    Embed attributes.
 * @param int    $post_id The post it sits in.
 * @return string
 */
function bb_en_embed_title( $html, $url = '', $attr = array(), $post_id = 0 ) {
	if ( ! bb_is_en() || ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$english = bb_en_title( $post_id );

	if ( '' === $english ) {
		return $html;
	}

	$title = esc_attr( $english );

	if ( preg_match( '/<iframe\b[^>]*\btitle="/i', $html ) ) {
		return preg_replace( '/(<iframe\b[^>]*\btitle=")[^"]*(")/i', '${1}' . $title . '${2}', $html, 1 );
	}

	return preg_replace( '/<iframe\b/i', '<iframe title="' . $title . '"', $html, 1 );
}
add_filter( 'embed_oembed_html', 'bb_en_embed_title', 20, 4 );

/* -------------------------------------------------------------------------
 * 8b. Writers
 * ---------------------------------------------------------------------- */

/**
 * A writer's name as it should be spelled in English, or '' when nobody has
 * said. The byline, the author page and the schema all pass through here.
 *
 * @param int $user_id User.
 * @return string
 */
function bb_en_author_name( $user_id ) {
	$user_id = (int) $user_id;

	if ( ! $user_id ) {
		return '';
	}

	return trim( (string) get_user_meta( $user_id, 'bb_en_display_name', true ) );
}

/**
 * @param string $name    Display name.
 * @param int    $user_id User.
 * @return string
 */
function bb_en_filter_author_field( $name, $user_id = 0 ) {
	if ( ! bb_is_en() ) {
		return $name;
	}

	$english = bb_en_author_name( $user_id );

	return ( '' === $english ) ? $name : $english;
}
add_filter( 'get_the_author_display_name', 'bb_en_filter_author_field', 10, 2 );

/**
 * get_the_author() hands the filter nothing but the name, so the writer has
 * to be read off the global the loop set up.
 *
 * @param string $name Display name.
 * @return string
 */
function bb_en_filter_the_author( $name ) {
	if ( ! bb_is_en() ) {
		return $name;
	}

	$author = isset( $GLOBALS['authordata'] ) ? $GLOBALS['authordata'] : null;

	if ( ! $author instanceof WP_User ) {
		return $name;
	}

	$english = bb_en_author_name( $author->ID );

	return ( '' === $english ) ? $name : $english;
}
add_filter( 'the_author', 'bb_en_filter_the_author' );

/**
 * @param string $description Biography.
 * @param int    $user_id     User.
 * @return string
 */
function bb_en_filter_author_bio( $description, $user_id = 0 ) {
	if ( ! bb_is_en() ) {
		return $description;
	}

	$english = trim( (string) get_user_meta( (int) $user_id, 'bb_en_description', true ) );

	return ( '' === $english ) ? $description : $english;
}
add_filter( 'get_the_author_description', 'bb_en_filter_author_bio', 10, 2 );

/* -------------------------------------------------------------------------
 * 9. The English menu
 * ---------------------------------------------------------------------- */

/** A second menu location, used only by /en. */
function bb_en_register_menu() {
	register_nav_menu( 'primary_en', __( 'Primary Menu (English edition)', 'bichitro-biggan' ) );
}
add_action( 'after_setup_theme', 'bb_en_register_menu', 11 );

/**
 * Menu labels are typed by hand and stored in Bengali, so a menu built for the
 * Bengali site would read "প্রথম পাতা" and "মহাকাশ বিজ্ঞান" under /en.
 *
 * A label already written in English is left exactly as it is — that is how a
 * menu made for the English edition keeps its own wording. A Bengali one is
 * replaced by the category's English name, or, failing that, by the theme's own
 * translation of it, which is where "প্রথম পাতা" becomes "Home".
 *
 * @param object $item A menu item on its way to the walker.
 * @return object
 */
function bb_en_nav_menu_item( $item ) {
	if ( ! bb_is_en() ) {
		return $item;
	}

	/*
	 * A category or a page in the menu gets its address from get_term_link()
	 * or get_permalink(), and those are filtered — they arrive already under
	 * /en. A custom link does not: whatever was typed into the menu screen is
	 * what is stored, so "Home" pointing at the site root sent a reader on the
	 * English edition straight back to the Bengali front page.
	 *
	 * Only addresses on this site are touched. bb_en_home_url() leaves
	 * WordPress's own paths alone and will not prefix one twice, but it knows
	 * nothing of hosts, so a link to YouTube has to be ruled out here.
	 */
	if ( isset( $item->url ) && '' !== $item->url && ( ! isset( $item->type ) || 'custom' === $item->type ) ) {
		$host = wp_parse_url( $item->url, PHP_URL_HOST );
		$site = wp_parse_url( get_option( 'home' ), PHP_URL_HOST );

		if ( ! $host || strtolower( (string) $host ) === strtolower( (string) $site ) ) {
			$item->url = bb_en_home_url( $item->url );
		}
	}

	if ( ! isset( $item->title ) ) {
		return $item;
	}

	if ( ! preg_match( '/[\x{0980}-\x{09FF}]/u', (string) $item->title ) ) {
		return $item;
	}

	if ( isset( $item->type ) && 'taxonomy' === $item->type && ! empty( $item->object_id ) ) {
		$english = bb_en_term_name( (int) $item->object_id );

		if ( '' !== $english ) {
			$item->title = $english;

			return $item;
		}
	}

	if ( isset( $item->type ) && 'post_type' === $item->type && ! empty( $item->object_id ) ) {
		$english = bb_en_title( (int) $item->object_id );

		if ( '' !== $english ) {
			$item->title = $english;

			return $item;
		}
	}

	// translate(), not __(): the label is data, and only a string the theme
	// already knows — "প্রথম পাতা", "সকল লেখা" — comes back changed.
	$translated = translate( $item->title, 'bichitro-biggan' );

	if ( $translated !== $item->title ) {
		$item->title = $translated;
	}

	return $item;
}
add_filter( 'wp_setup_nav_menu_item', 'bb_en_nav_menu_item' );

/**
 * @param array $args wp_nav_menu() arguments.
 * @return array
 */
function bb_en_nav_menu_args( $args ) {
	if ( ! bb_is_en() ) {
		return $args;
	}

	if ( isset( $args['theme_location'] ) && 'primary' === $args['theme_location'] && has_nav_menu( 'primary_en' ) ) {
		$args['theme_location'] = 'primary_en';
	}

	return $args;
}
add_filter( 'wp_nav_menu_args', 'bb_en_nav_menu_args' );

/* -------------------------------------------------------------------------
 * 10. The site's own words
 * ---------------------------------------------------------------------- */

/**
 * The handful of Customizer texts the English edition needs its own copy of.
 *
 * Each one gets an "_en" twin setting; when that is empty the English default
 * here is used, so /en never falls back to a Bengali heading.
 *
 * @return array<string, array{label:string,type:string,default:string}>
 */
function bb_en_text_settings() {
	return array(
		'bb_tagline_text'      => array(
			'label'   => __( 'Tagline', 'bichitro-biggan' ),
			'type'    => 'textarea',
			'default' => 'The science of life, the wonders of the universe, the stories of space missions, the nature of matter and the lives of the scientists who changed how we see it all — gathered here, on Bichitro Biggan.',
		),
		'bb_editor_picks_title' => array(
			'label'   => __( "Editor's picks heading", 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => "Editor's Picks",
		),
		'bb_popular_title'     => array(
			'label'   => __( 'Popular posts heading', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Popular Reads',
		),
		'bb_popular_cat_title' => array(
			'label'   => __( 'Popular categories heading', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Popular Sections',
		),
		'bb_about_title'       => array(
			'label'   => __( 'About heading', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'About Us',
		),
		'bb_about_text'        => array(
			'label'   => __( 'About text', 'bichitro-biggan' ),
			'type'    => 'textarea',
			'default' => 'Bichitro Biggan is your source for science news, discoveries, and insights. We bring you the latest updates, research breakthroughs, and engaging stories from the world of science and technology.',
		),
		'bb_contact_title'     => array(
			'label'   => __( 'Contact heading', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Contact',
		),
		'bb_subscribe_title'   => array(
			'label'   => __( 'Subscribe heading', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Subscribe',
		),
		'bb_youtube_text'      => array(
			'label'   => __( 'YouTube button text', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Subscribe',
		),
		'bb_footer_youtube_text' => array(
			'label'   => __( 'YouTube button text (footer)', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Subscribe',
		),
		'bb_site_name'         => array(
			'label'   => __( 'Site name', 'bichitro-biggan' ),
			'type'    => 'text',
			'default' => 'Bichitro Biggan',
		),
	);
}

/**
 * The keys alone, without touching a translation function — this runs while
 * the theme is still being loaded, long before the text domain exists.
 *
 * @return string[]
 */
function bb_en_text_keys() {
	return array(
		'bb_tagline_text',
		'bb_editor_picks_title',
		'bb_popular_title',
		'bb_popular_cat_title',
		'bb_about_title',
		'bb_about_text',
		'bb_contact_title',
		'bb_subscribe_title',
		'bb_youtube_text',
		'bb_footer_youtube_text',
		'bb_site_name',
	);
}

/**
 * Swap each of those for its English twin while /en is being served.
 */
function bb_en_filter_text_settings() {
	foreach ( bb_en_text_keys() as $key ) {
		add_filter(
			'theme_mod_' . $key,
			function ( $value ) use ( $key ) {
				return bb_en_text_setting( $key, $value );
			}
		);
	}
}
bb_en_filter_text_settings();

/**
 * @param string $key   Setting key.
 * @param mixed  $value Bengali value.
 * @return mixed
 */
function bb_en_text_setting( $key, $value ) {
	if ( ! bb_is_en() ) {
		return $value;
	}

	$settings = bb_en_text_settings();

	if ( ! isset( $settings[ $key ] ) ) {
		return $value;
	}

	$english = get_theme_mod( $key . '_en', '' );
	$english = is_string( $english ) ? trim( $english ) : '';

	return ( '' === $english ) ? $settings[ $key ]['default'] : $english;
}

/**
 * The site's name in the masthead alt text, the title tag and the schema.
 *
 * @param string $name Site name.
 * @return string
 */
function bb_en_site_name( $name ) {
	if ( ! bb_is_en() ) {
		return $name;
	}

	$english = bb_en_text_setting( 'bb_site_name', '' );

	return ( '' === $english ) ? $name : $english;
}
add_filter( 'option_blogname', 'bb_en_site_name' );

/**
 * The site's own tagline, which WordPress stores separately from the
 * masthead one.
 *
 * @param string $description Site description.
 * @return string
 */
function bb_en_site_description( $description ) {
	if ( ! bb_is_en() ) {
		return $description;
	}

	$english = trim( (string) get_theme_mod( 'bb_site_description_en', '' ) );

	return ( '' === $english ) ? 'Science, space and the story of discovery — in Bengali and in English.' : $english;
}
add_filter( 'option_blogdescription', 'bb_en_site_description' );

/**
 * A Customizer section holding the English wording, so none of it has to be
 * edited in code. Left empty, each field falls back to the English default
 * above — never to the Bengali text.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 * @return void
 */
function bb_en_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'bb_english_section',
		array(
			'title'       => __( 'English edition (/en)', 'bichitro-biggan' ),
			'priority'    => 35,
			'description' => __( 'The wording the site uses under /en. Leave a field empty to use the built-in English text.', 'bichitro-biggan' ),
		)
	);

	$wp_customize->add_setting(
		'bb_en_logo',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'bb_en_logo',
			array(
				'label'       => __( 'Logo for the English edition', 'bichitro-biggan' ),
				'description' => __( 'Shown instead of the site logo under /en. Leave empty to use the same logo as the Bengali site.', 'bichitro-biggan' ),
				'section'     => 'bb_english_section',
				'mime_type'   => 'image',
			)
		)
	);

	$fields = bb_en_text_settings();

	$fields['bb_site_description'] = array(
		'label'   => __( 'Site description (search engines)', 'bichitro-biggan' ),
		'type'    => 'textarea',
		'default' => 'Science, space and the story of discovery — in Bengali and in English.',
	);

	foreach ( $fields as $key => $field ) {
		$setting = $key . '_en';

		$wp_customize->add_setting(
			$setting,
			array(
				'default'           => '',
				'sanitize_callback' => ( 'textarea' === $field['type'] ) ? 'wp_kses_post' : 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			$setting,
			array(
				'label'       => $field['label'],
				'section'     => 'bb_english_section',
				'type'        => $field['type'],
				'description' => sprintf(
					/* translators: %s: the built-in English wording. */
					__( 'Default: %s', 'bichitro-biggan' ),
					$field['default']
				),
			)
		);
	}
}
add_action( 'customize_register', 'bb_en_customize_register', 20 );

/* -------------------------------------------------------------------------
 * 11. Search
 * ---------------------------------------------------------------------- */

/*
 * The live-search endpoint sits at /wp-json/, outside /en, so the script sends
 * the edition along with the query and inc/live-search.php switches languages
 * from there.
 */

/* -------------------------------------------------------------------------
 * 11b. Comments
 * ---------------------------------------------------------------------- */

/**
 * A comment is posted to wp-comments-post.php, which knows nothing about /en
 * and would send the reader back to the Bengali article. The form says where
 * it came from.
 */
function bb_en_comment_form_field() {
	if ( ! bb_is_en() ) {
		return;
	}

	echo '<input type="hidden" name="bb_lang" value="en" />';
}
add_action( 'comment_form', 'bb_en_comment_form_field' );

/**
 * @param string     $location Where the reader is being sent.
 * @param WP_Comment $comment  The comment just posted.
 * @return string
 */
function bb_en_comment_redirect( $location, $comment ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp-comments-post.php has already checked the request; this only picks the language of the address to return to.
	$lang = isset( $_POST['bb_lang'] ) ? sanitize_key( wp_unslash( $_POST['bb_lang'] ) ) : '';

	if ( 'en' !== $lang || ! $comment instanceof WP_Comment ) {
		return $location;
	}

	$post_id = (int) $comment->comment_post_ID;

	$url = (string) bb_en_in_lang(
		'en',
		function () use ( $post_id ) {
			return get_permalink( $post_id );
		}
	);

	return $url ? $url . '#comment-' . (int) $comment->comment_ID : $location;
}
add_filter( 'comment_post_redirect', 'bb_en_comment_redirect', 10, 2 );

/* -------------------------------------------------------------------------
 * 12. hreflang
 * ---------------------------------------------------------------------- */

/**
 * Tell search engines the two editions are the same page in two languages.
 */
function bb_en_hreflang_tags() {
	if ( is_404() || is_search() ) {
		return;
	}

	/*
	 * Without the query string: an address arrived at with ?utm_source= or a
	 * cache-buster on it must not be offered to a search engine as the
	 * canonical other-language version of the page.
	 */
	$bengali = bb_en_url_for( 'bn', false );
	$english = bb_en_url_for( 'en', false );

	if ( ! $bengali ) {
		return;
	}

	printf( '<link rel="alternate" hreflang="bn" href="%s" />' . "\n", esc_url( $bengali ) );

	if ( $english ) {
		printf( '<link rel="alternate" hreflang="en" href="%s" />' . "\n", esc_url( $english ) );
	}

	printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $bengali ) );
}
add_action( 'wp_head', 'bb_en_hreflang_tags', 4 );

/* -------------------------------------------------------------------------
 * 13. SEO fields
 * ---------------------------------------------------------------------- */

/**
 * Wherever the SEO engine asks a post for its title, description or keyphrase,
 * the English edition answers with the English one — including the places that
 * read the field straight rather than through the page's context, like the
 * title tag and the keywords meta.
 *
 * @param string|null $value    Set by an earlier filter, or null.
 * @param int         $post_id  Post.
 * @param string      $meta_key Which field.
 * @return string|null
 */
function bb_en_seo_field( $value, $post_id, $meta_key ) {
	if ( ! bb_is_en() || null !== $value ) {
		return $value;
	}

	$map = array(
		'bb_seo_title'          => 'bb_en_seo_title',
		'bb_seo_description'    => 'bb_en_seo_description',
		'bb_focus_keyphrase'    => 'bb_en_focus_keyphrase',
		// There is no English synonyms field: an empty answer keeps the
		// Bengali synonyms out of the English keywords tag.
		'bb_keyphrase_synonyms' => '',
	);

	if ( ! isset( $map[ $meta_key ] ) ) {
		return $value;
	}

	if ( '' === $map[ $meta_key ] ) {
		return '';
	}

	/*
	 * '' rather than null even when the English field is empty: an empty
	 * answer sends the page back to its own English title, where null would
	 * let the Bengali SEO field through.
	 */
	return bb_en_get( $post_id, $map[ $meta_key ] );
}
add_filter( 'bb_seo_pre_field', 'bb_en_seo_field', 10, 3 );

/**
 * The English title and description for the page's own <head>.
 *
 * @param array $ctx SEO context.
 * @return array
 */
function bb_en_seo_context( $ctx ) {
	if ( ! bb_is_en() ) {
		return $ctx;
	}

	$post = isset( $ctx['post'] ) ? $ctx['post'] : null;

	if ( ! $post instanceof WP_Post ) {
		return $ctx;
	}

	$title = bb_en_get( $post->ID, 'bb_en_seo_title' );

	if ( '' === $title ) {
		$english = bb_en_title( $post->ID );

		if ( '' !== $english ) {
			$title = $english . ' — ' . get_bloginfo( 'name' );
		}
	}

	if ( '' !== $title ) {
		$ctx['title'] = $title;
	}

	$description = bb_en_get( $post->ID, 'bb_en_seo_description' );

	if ( '' === $description ) {
		$description = bb_en_excerpt_text( $post->ID, '', 30 );
	}

	if ( '' !== $description ) {
		$ctx['description'] = $description;
	}

	return $ctx;
}
add_filter( 'bb_seo_context', 'bb_en_seo_context' );

/**
 * The page title in the browser tab and in Google's results.
 *
 * The SEO engine reads bb_seo_title straight from the post rather than through
 * its own context, so the English title has to be put back afterwards. This
 * runs after that filter, on the value it produced.
 *
 * @param string $title Title so far.
 * @return string
 */
function bb_en_document_title( $title ) {
	if ( ! bb_is_en() || is_admin() || is_feed() || ! is_singular() ) {
		return $title;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return $title;
	}

	$english = bb_en_get( $post->ID, 'bb_en_seo_title' );

	if ( '' === $english ) {
		$english = bb_en_title( $post->ID );

		if ( '' !== $english ) {
			$english .= ' — ' . get_bloginfo( 'name' );
		}
	}

	return ( '' === $english ) ? $title : $english;
}
add_filter( 'pre_get_document_title', 'bb_en_document_title', 20 );

/* -------------------------------------------------------------------------
 * 13b. The English logo
 * ---------------------------------------------------------------------- */

/**
 * The English edition can carry a logo of its own — the same design with the
 * name set in English. Left unset, it wears the Bengali one.
 *
 * @param mixed $logo Attachment ID of the site logo.
 * @return mixed
 */
function bb_en_custom_logo( $logo ) {
	if ( ! bb_is_en() ) {
		return $logo;
	}

	$english = (int) get_theme_mod( 'bb_en_logo', 0 );

	return $english ? $english : $logo;
}
add_filter( 'theme_mod_custom_logo', 'bb_en_custom_logo' );

/**
 * Set either logo from a script.
 *
 * The Customizer is the usual place for this, but a logo that has just been
 * uploaded by the publishing tool should not need a trip through the browser
 * to be put in place. Only someone who could change it in the Customizer can
 * change it here, and only to an image already in the media library.
 */
function bb_en_register_logo_route() {
	register_rest_route(
		'bb/v1',
		'/logo',
		array(
			'methods'             => 'POST',
			'callback'            => 'bb_en_rest_set_logo',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
			'args'                => array(
				'custom_logo' => array( 'sanitize_callback' => 'absint' ),
				'en_logo'     => array( 'sanitize_callback' => 'absint' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'bb_en_register_logo_route' );

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function bb_en_rest_set_logo( WP_REST_Request $request ) {
	$pairs = array(
		'custom_logo' => 'custom_logo',
		'en_logo'     => 'bb_en_logo',
	);

	$changed = array();

	foreach ( $pairs as $param => $mod ) {
		if ( null === $request->get_param( $param ) ) {
			continue;
		}

		$id = (int) $request->get_param( $param );

		if ( $id > 0 && ! wp_attachment_is_image( $id ) ) {
			return new WP_Error(
				'bb_logo_not_an_image',
				sprintf( /* translators: %d: attachment ID. */ __( '#%d is not an image in the media library.', 'bichitro-biggan' ), $id ),
				array( 'status' => 400 )
			);
		}

		set_theme_mod( $mod, $id );
		$changed[ $mod ] = $id;
	}

	return rest_ensure_response(
		array(
			'changed'     => $changed,
			'custom_logo' => (int) get_theme_mod( 'custom_logo', 0 ),
			'en_logo'     => (int) get_theme_mod( 'bb_en_logo', 0 ),
		)
	);
}

/* -------------------------------------------------------------------------
 * 14. The switcher
 * ---------------------------------------------------------------------- */

/**
 * The little EN / বাং button that sits beside the dark-mode switch.
 *
 * @param string $class CSS class for the anchor.
 * @return void
 */
function bb_lang_switch( $class = 'bb-topbar__lang' ) {
	$to_english = ! bb_is_en();
	$target     = $to_english ? bb_en_url_for( 'en' ) : bb_en_url_for( 'bn' );

	if ( ! $target ) {
		// No English version of this page — offer the English front page.
		$target = $to_english
			? (string) bb_en_in_lang( 'en', 'bb_en_home_link' )
			: (string) bb_en_in_lang( 'bn', 'bb_en_home_link' );
	}

	if ( ! $target ) {
		return;
	}

	/*
	 * The language it leads to, spelled out — the same words the pill on an
	 * article uses, so the two switches read alike wherever a reader meets one.
	 */
	$label = $to_english ? 'English' : 'Bangla';
	$title = $to_english
		? __( 'ইংরেজিতে পড়ুন', 'bichitro-biggan' )
		: __( 'Read in Bengali', 'bichitro-biggan' );

	printf(
		'<a class="%1$s" href="%2$s" hreflang="%3$s" lang="%3$s" title="%4$s" aria-label="%4$s" rel="alternate">'
			. '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">'
			. '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"></path>'
			. '</svg><span>%5$s</span></a>',
		esc_attr( $class ),
		esc_url( $target ),
		esc_attr( $to_english ? 'en' : 'bn' ),
		esc_attr( $title ),
		esc_html( $label )
	);
}

/** The front page of whichever edition is current. Used by the switcher. */
function bb_en_home_link() {
	return home_url( '/' );
}

/**
 * The switch that belongs to one article — "read this same piece in the other
 * language", at the top of the article and inside the reading modal.
 *
 * Nothing is printed when there is nowhere to go: most posts have no English
 * version yet, and a button that lands the reader on a front page instead of
 * the article they were reading is worse than no button.
 *
 * @param int|null $post_id Post, or the current one.
 * @return void
 */
function bb_post_lang_switch( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	$to_english = ! bb_is_en();

	if ( $to_english && ! bb_en_has( $post_id ) ) {
		return;
	}

	$url = (string) bb_en_in_lang(
		$to_english ? 'en' : 'bn',
		function () use ( $post_id ) {
			return get_permalink( $post_id );
		}
	);

	if ( ! $url ) {
		return;
	}

	$label = $to_english ? 'English' : 'Bangla';
	$title = $to_english
		? __( 'এই লেখাটি ইংরেজিতে পড়ুন', 'bichitro-biggan' )
		: __( 'Read this article in Bangla', 'bichitro-biggan' );

	printf(
		'<a class="bb-lang-pill" href="%1$s" data-bb-lang-link hreflang="%2$s" lang="%2$s" rel="alternate" title="%3$s" aria-label="%3$s">'
			. '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">'
			. '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"></path>'
			. '</svg><span>%4$s</span></a>',
		esc_url( $url ),
		esc_attr( $to_english ? 'en' : 'bn' ),
		esc_attr( $title ),
		esc_html( $label )
	);
}

/* -------------------------------------------------------------------------
 * 15. The English sitemap
 * ---------------------------------------------------------------------- */

/**
 * Give Google its own list of the English addresses. Core's sitemap only
 * knows about the Bengali ones.
 */
function bb_en_register_sitemap( $sitemaps ) {
	if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! isset( $sitemaps->registry ) ) {
		return;
	}

	require_once get_template_directory() . '/inc/class-bb-en-sitemap-provider.php';

	if ( ! class_exists( 'BB_EN_Sitemap_Provider' ) ) {
		return;
	}

	$sitemaps->registry->add_provider( 'en', new BB_EN_Sitemap_Provider() );
}
add_action( 'wp_sitemaps_init', 'bb_en_register_sitemap' );
