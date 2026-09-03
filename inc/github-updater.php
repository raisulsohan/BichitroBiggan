<?php
/**
 * GitHub Theme Updater — portable drop-in.
 *
 * Watches a public GitHub repository for a newer theme version and feeds the
 * answer into WordPress's own update system, so "Update Available" appears in
 * Appearance → Themes and Dashboard → Updates exactly as it does for a theme
 * from wordpress.org. No plugin, no service, no API key.
 *
 * INSTALL
 *   1. Copy this file to  <your-theme>/inc/github-updater.php
 *   2. Add to functions.php:
 *        require_once get_template_directory() . '/inc/github-updater.php';
 *   3. Make sure style.css has a GitHub address in Theme URI:
 *        Theme URI: https://github.com/owner/repo
 *
 * That is the whole configuration for a theme that sits at the root of its
 * repository. Two optional style.css headers cover the other layouts:
 *
 *        Update Path:   Website/my-theme     (theme lives in a subdirectory)
 *        Update Branch: master               (default is "main")
 *        Update URI:    https://github.com/owner/repo
 *
 * Use Update URI when Theme URI already points somewhere else — the live site,
 * usually. It is checked first and Theme URI is only read if it is absent.
 *
 * @package GitHub_Theme_Updater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Theme_GitHub_Updater' ) ) :

/**
 * Class Theme_GitHub_Updater
 */
final class Theme_GitHub_Updater {

	/** @var bool Whether an instance was already booted. */
	private static $booted = false;

	/** @var string owner/repo, read from the Theme URI header. */
	private $repo = '';

	/** @var string The theme's directory name — what WordPress calls it. */
	private $slug;

	/** @var string Version currently installed. */
	private $version;

	/** @var string Branch to watch. */
	private $branch;

	/** @var string Path to the theme inside the repo, '' when it is the root. */
	private $path;

	/** @var string Transient holding the last answer from GitHub. */
	private $key;

	/**
	 * What GitHub said during this page load.
	 *
	 * Null means nobody has asked yet. Anything else — including false, meaning
	 * the question could not be answered — is a settled answer and is handed
	 * back without asking again. See remote() for why that matters.
	 *
	 * @var array|false|null
	 */
	private $remote = null;

	/**
	 * Read the theme's own headers and hook in.
	 */
	public function __construct() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		$theme = wp_get_theme( get_template() );

		$this->slug    = $theme->get_stylesheet();
		$this->version = $theme->get( 'Version' );
		$this->branch  = $this->header( $theme, 'Update Branch', 'main' );
		$this->path    = trim( $this->header( $theme, 'Update Path', '' ), '/' );
		$this->key     = 'tgu_' . md5( $this->slug );

		/*
		 * Where the theme is published. A dedicated Update URI header wins, so a
		 * theme whose Theme URI is its live site can still be updated from
		 * GitHub without pointing visitors at a repository.
		 */
		$source = $this->header( $theme, 'Update URI', '' );

		if ( '' === $source ) {
			$source = (string) $theme->get( 'ThemeURI' );
		}

		$host = wp_parse_url( $source, PHP_URL_HOST );
		$path = trim( (string) wp_parse_url( $source, PHP_URL_PATH ), '/' );

		if ( 'github.com' !== strtolower( (string) $host ) || substr_count( $path, '/' ) < 1 ) {
			return; // Not published on GitHub — stay out of the way entirely.
		}

		$parts      = explode( '/', $path );
		$this->repo = $parts[0] . '/' . $parts[1];

		/*
		 * Only the admin screens and cron need update information. Leaving the
		 * filters off the front end keeps visitors' requests away from GitHub.
		 */
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		add_filter( 'site_transient_update_themes', array( $this, 'inject' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'inject' ) );
		add_filter( 'themes_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_source' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'forget' ), 10, 2 );
	}

	/**
	 * Read a custom style.css header, falling back to a default.
	 */
	private function header( $theme, $name, $default ) {
		$value = $theme->get( $name );

		if ( ! $value && 'Update URI' === $name ) {
			$value = $theme->get( 'UpdateURI' );
		}

		if ( ! $value ) {
			/* WP_Theme only exposes headers it knows, so read the file for ours. */
			$value = '';
			$file  = $theme->get_stylesheet_directory() . '/style.css';

			if ( is_readable( $file ) ) {
				$data  = get_file_data( $file, array( 'h' => $name ) );
				$value = isset( $data['h'] ) ? $data['h'] : '';
			}
		}

		$value = trim( (string) $value );

		return '' !== $value ? $value : $default;
	}

	/* ------------------------------------------------------------------
	 * 1. Tell WordPress an update exists
	 * ---------------------------------------------------------------- */

	/**
	 * Add our theme to the update transient when GitHub is ahead.
	 *
	 * @param mixed $transient The update_themes transient.
	 * @return object
	 */
	public function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$installed = ! empty( $transient->checked[ $this->slug ] )
			? $transient->checked[ $this->slug ]
			: $this->version;

		$remote = $this->remote();

		if ( ! $remote || empty( $remote['version'] ) ) {
			return $transient;
		}

		if ( ! version_compare( $remote['version'], $installed, '>' ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $this->slug ] = array(
			'theme'       => $this->slug,
			'new_version' => $remote['version'],
			'url'         => 'https://github.com/' . $this->repo,
			'package'     => $this->zip_url( isset( $remote['sha'] ) ? $remote['sha'] : '' ),
		);

		return $transient;
	}

	/* ------------------------------------------------------------------
	 * 2. "View version details" popup
	 * ---------------------------------------------------------------- */

	/**
	 * Fill the details modal that the update notice links to.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function details( $result, $action, $args ) {
		if ( 'theme_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$remote = $this->remote();

		if ( ! $remote ) {
			return $result;
		}

		$theme = wp_get_theme( $this->slug );

		$info                = new stdClass();
		$info->name          = $theme->get( 'Name' );
		$info->slug          = $this->slug;
		$info->version       = $remote['version'];
		$info->author        = $theme->get( 'Author' );
		$info->homepage      = 'https://github.com/' . $this->repo;
		$info->download_link = $this->zip_url( isset( $remote['sha'] ) ? $remote['sha'] : '' );
		$info->sections      = array(
			'description' => $theme->get( 'Description' ),
			'changelog'   => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_url( 'https://github.com/' . $this->repo . '/commits/' . $this->branch ),
				esc_html__( 'গিটহাবে সম্পূর্ণ চেঞ্জলগ দেখুন →', 'bichitro-biggan' )
			),
		);

		return $info;
	}

	/* ------------------------------------------------------------------
	 * 3. Give the extracted folder the name WordPress expects
	 * ---------------------------------------------------------------- */

	/**
	 * A GitHub archive unpacks as "repo-<ref>/", and when the theme lives in a
	 * subdirectory the files sit deeper still. WordPress installs whatever
	 * folder it is handed under that folder's own name, so without this the
	 * theme arrives as a second, differently-named copy and the original is
	 * left behind, still active.
	 *
	 * @param string      $source        Where the archive was unpacked.
	 * @param string      $remote_source Parent of $source.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $args          Contains 'theme' during a theme update.
	 * @return string|WP_Error
	 */
	public function rename_source( $source, $remote_source, $upgrader, $args ) {
		global $wp_filesystem;

		if ( empty( $args['theme'] ) || $args['theme'] !== $this->slug ) {
			return $source;
		}

		$theme_dir = $this->locate_theme( $source );

		if ( ! $theme_dir ) {
			return new WP_Error(
				'tgu_no_stylesheet',
				__( 'ডাউনলোড করা zip-এ থিমের style.css খুঁজে পাওয়া যায়নি।', 'bichitro-biggan' )
			);
		}

		$wanted = trailingslashit( $remote_source ) . $this->slug;

		if ( untrailingslashit( $theme_dir ) === untrailingslashit( $wanted ) ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $wanted ) ) {
			$wp_filesystem->delete( $wanted, true );
		}

		if ( ! $wp_filesystem->move( untrailingslashit( $theme_dir ), untrailingslashit( $wanted ) ) ) {
			return new WP_Error(
				'tgu_rename_failed',
				__( 'zip ফোল্ডারের নাম থিমের স্লাগে বদলানো যায়নি।', 'bichitro-biggan' )
			);
		}

		/* Drop the leftover repo-<ref>/ wrapper. */
		if ( untrailingslashit( $source ) !== untrailingslashit( $wanted ) ) {
			$wp_filesystem->delete( $source, true );
		}

		return trailingslashit( $wanted );
	}

	/**
	 * Find the directory holding the theme's style.css inside an unpacked zip.
	 *
	 * Checks the archive root, then the configured Update Path, then walks two
	 * levels down — enough for every layout the header describes, without
	 * turning into a full filesystem crawl.
	 *
	 * @param string $source Unpacked archive directory.
	 * @return string|false
	 */
	private function locate_theme( $source ) {
		global $wp_filesystem;

		$source = trailingslashit( $source );

		if ( $wp_filesystem->exists( $source . 'style.css' ) ) {
			return $source;
		}

		if ( $this->path ) {
			$configured = $source . trailingslashit( $this->path );
			if ( $wp_filesystem->exists( $configured . 'style.css' ) ) {
				return $configured;
			}
		}

		foreach ( array( 1, 2 ) as $depth ) {
			$found = $this->scan_for_stylesheet( $source, $depth );
			if ( $found ) {
				return $found;
			}
		}

		return false;
	}

	/**
	 * Look for a style.css exactly $depth levels below $dir.
	 *
	 * @param string $dir   Directory to search under.
	 * @param int    $depth Levels remaining.
	 * @return string|false
	 */
	private function scan_for_stylesheet( $dir, $depth ) {
		global $wp_filesystem;

		$list = $wp_filesystem->dirlist( $dir );

		if ( ! is_array( $list ) ) {
			return false;
		}

		foreach ( $list as $name => $item ) {
			if ( 'd' !== $item['type'] ) {
				continue;
			}

			$child = trailingslashit( $dir ) . trailingslashit( $name );

			if ( 1 === $depth ) {
				if ( $wp_filesystem->exists( $child . 'style.css' ) ) {
					return $child;
				}
				continue;
			}

			$found = $this->scan_for_stylesheet( $child, $depth - 1 );

			if ( $found ) {
				return $found;
			}
		}

		return false;
	}

	/* ------------------------------------------------------------------
	 * 4. Housekeeping
	 * ---------------------------------------------------------------- */

	/**
	 * Forget the cached answer once an update has been installed.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Update context.
	 */
	public function forget( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'theme' !== $options['type'] ) {
			return;
		}

		delete_transient( $this->key );

		/* The answer held for this page load describes the theme that was just
		   replaced, so it is no longer an answer. */
		$this->remote = null;
	}

	/* ------------------------------------------------------------------
	 * Talking to GitHub
	 * ---------------------------------------------------------------- */

	/**
	 * The version GitHub is offering, and the commit it was read from.
	 *
	 * @return array|false
	 */
	private function remote() {
		global $pagenow;

		/*
		 * One page load asks GitHub once, whatever the answer.
		 *
		 * site_transient_update_themes is read several times while an admin
		 * screen is drawn, and on the Themes and Updates screens the transient
		 * below is deliberately skipped. Without this line each of those reads
		 * opens its own connection, every one free to wait ten seconds, so a
		 * slow GitHub can hold Appearance → Themes for most of a minute.
		 *
		 * A failure is remembered alongside a success, because repeating a
		 * failure is exactly what costs the ten seconds.
		 */
		if ( null !== $this->remote ) {
			return $this->remote;
		}

		$fresh = isset( $_GET['force-check'] )
			|| ( is_admin() && in_array( $pagenow, array( 'update-core.php', 'themes.php' ), true ) );

		if ( ! $fresh ) {
			$cached = get_transient( $this->key );

			if ( false !== $cached ) {
				$this->remote = $cached;
				return $cached;
			}
		}

		/* Fetch the current HEAD commit first. Fastly CDN caches the branch path for 5 minutes;
		   querying by commit SHA completely bypasses this delay with 0-second freshness. */
		$sha = $this->head_sha();
		$ref = $sha ? $sha : $this->branch;

		$style = sprintf(
			'https://raw.githubusercontent.com/%s/%s/%sstyle.css',
			$this->repo,
			rawurlencode( $ref ),
			$this->path ? trailingslashit( $this->path ) : ''
		);

		$response = wp_remote_get( $style, array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'text/plain' ),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->remote = false;
			return false;
		}

		if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', wp_remote_retrieve_body( $response ), $m ) ) {
			$this->remote = false;
			return false;
		}

		$data = array(
			'version' => trim( $m[1] ),
			'sha'     => $sha,
		);

		set_transient( $this->key, $data, 2 * HOUR_IN_SECONDS );

		$this->remote = $data;

		return $data;
	}

	/**
	 * The commit the branch currently points at.
	 *
	 * Requested as a bare string rather than the commit's full JSON — that
	 * Accept header makes GitHub answer with forty characters instead of
	 * several kilobytes nobody here reads.
	 *
	 * @return string Forty hex characters, or '' if it could not be had.
	 */
	private function head_sha() {
		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/commits/%s', $this->repo, rawurlencode( $this->branch ) ),
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.sha',
					'User-Agent' => 'WordPress-Theme-GitHub-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$sha = trim( wp_remote_retrieve_body( $response ) );

		/* Checked rather than trusted: this is about to become part of a
		   download address, and an error page would otherwise be built into
		   one. */
		return preg_match( '/^[0-9a-f]{40}$/', $sha ) ? $sha : '';
	}

	/**
	 * Where to download the update from.
	 *
	 * Pinned to a commit whenever one is known, because a branch's archive is a
	 * moving target: GitHub serves it from a cache that can still hold the
	 * previous snapshot for a while after a push. WordPress would install that
	 * older copy over the current one and report success, having done exactly
	 * what it was asked — the update simply arrives as a copy of what was
	 * already there.
	 *
	 * A commit's archive cannot go stale: its address changes whenever its
	 * content does, so a cached copy of it is always the right one.
	 *
	 * Falls back to the branch when the commit could not be looked up, since a
	 * possibly-stale update still beats no update at all.
	 *
	 * @param string $sha Commit to pin to, or '' for the branch.
	 * @return string
	 */
	private function zip_url( $sha = '' ) {
		if ( $sha ) {
			return sprintf( 'https://github.com/%s/archive/%s.zip', $this->repo, $sha );
		}

		return sprintf(
			'https://github.com/%s/archive/refs/heads/%s.zip',
			$this->repo,
			rawurlencode( $this->branch )
		);
	}
}
endif;

if ( did_action( 'after_setup_theme' ) ) {
	new Theme_GitHub_Updater();
} else {
	add_action( 'after_setup_theme', function () {
		new Theme_GitHub_Updater();
	} );
}
