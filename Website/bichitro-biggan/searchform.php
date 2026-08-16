<?php
/**
 * Search form — matches the header search bar in the design.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form role="search" method="get" class="bb-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="bb-screen-reader-text" for="bb-search-<?php echo esc_attr( wp_unique_id() ); ?>">
		<?php esc_html_e( 'খুঁজুন', 'bichitro-biggan' ); ?>
	</label>
	<input
		type="search"
		class="bb-searchform__input"
		placeholder="<?php esc_attr_e( 'খুঁজুন...', 'bichitro-biggan' ); ?>"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		name="s"
	/>
	<button type="submit" class="bb-searchform__submit"><?php esc_html_e( 'Search', 'bichitro-biggan' ); ?></button>
</form>
