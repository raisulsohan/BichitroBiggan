<?php
/**
 * The English edition's sitemap.
 *
 * Core's sitemap lists every post once, at its Bengali address. The English
 * addresses are a second set of pages as far as a search engine is concerned,
 * so they get their own file: /wp-sitemap-en-1.xml, linked from the index.
 *
 * Only posts whose English version is finished are listed.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) {
	return;
}

/**
 * Lists /en/ and every translated article under it.
 */
class BB_EN_Sitemap_Provider extends WP_Sitemaps_Provider {

	/**
	 * How many English URLs go in one sitemap file.
	 *
	 * @var int
	 */
	const PER_PAGE = 500;

	/**
	 * Set the provider's name and object type.
	 */
	public function __construct() {
		$this->name        = 'en';
		$this->object_type = 'en';
	}

	/**
	 * The posts with a finished English version.
	 *
	 * @param int $page_num Page of results, 1-based.
	 * @return int[] Post IDs.
	 */
	private function translated_ids( $page_num ) {
		return get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => self::PER_PAGE,
				'paged'            => max( 1, (int) $page_num ),
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'meta_key'         => 'bb_en_ready', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
	}

	/**
	 * How many translated posts there are in total.
	 *
	 * @return int
	 */
	private function translated_count() {
		$query = new WP_Query(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => 'bb_en_ready', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => true,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * One page of English URLs.
	 *
	 * @param int    $page_num       Page of results.
	 * @param string $object_subtype Unused.
	 * @return array[] Sitemap entries.
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$page_num = max( 1, (int) $page_num );
		$entries  = array();

		if ( 1 === $page_num ) {
			$entries[] = array(
				'loc' => bb_en_in_lang( 'en', 'bb_en_home_link' ),
			);
		}

		foreach ( $this->translated_ids( $page_num ) as $post_id ) {
			if ( ! bb_en_has( $post_id ) ) {
				continue;
			}

			$url = bb_en_in_lang(
				'en',
				function () use ( $post_id ) {
					return get_permalink( $post_id );
				}
			);

			if ( $url ) {
				$entries[] = array( 'loc' => $url );
			}
		}

		/**
		 * Filter the English sitemap entries.
		 *
		 * @param array[] $entries  Sitemap entries.
		 * @param int     $page_num Page of results.
		 */
		return apply_filters( 'bb_en_sitemap_entries', $entries, $page_num );
	}

	/**
	 * How many sitemap files the English edition needs.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		$count = $this->translated_count();

		return max( 1, (int) ceil( $count / self::PER_PAGE ) );
	}
}
