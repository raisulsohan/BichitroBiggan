<?php
/**
 * Theme Settings — dashboard page for theme configuration.
 *
 * Values are stored as theme mods, so everything that reads them elsewhere in
 * the theme keeps working unchanged.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every field on the page, grouped by tab.
 *
 * type: select | number | text | url | textarea | checkbox
 */
function bb_settings_schema() {
	return array(
		'homepage' => array(
			'label'  => __( 'Homepage', 'bichitro-biggan' ),
			'intro'  => __( 'Select which category to show in each homepage block. Leave empty to automatically detect categories. For block sizes & layout adjustments, go to: Appearance → Customize → Homepage Layout.', 'bichitro-biggan' ),
			'fields' => array(
				'bb_hero_cat_1'          => array( 'type' => 'category', 'label' => __( 'Hero Slot 1 — Filter by Category', 'bichitro-biggan' ), 'hint' => 'All' ),
				'bb_hero_slot_1'         => array( 'type' => 'post_select', 'label' => __( 'Hero Slot 1 — Main Large Post (Left)', 'bichitro-biggan' ), 'desc' => __( 'Select specific post or leave as Auto.', 'bichitro-biggan' ) ),
				'bb_hero_cat_2'          => array( 'type' => 'category', 'label' => __( 'Hero Slot 2 — Filter by Category', 'bichitro-biggan' ), 'hint' => 'All' ),
				'bb_hero_slot_2'         => array( 'type' => 'post_select', 'label' => __( 'Hero Slot 2 — Top Right Post', 'bichitro-biggan' ), 'desc' => __( 'Select specific post or leave as Auto.', 'bichitro-biggan' ) ),
				'bb_hero_cat_3'          => array( 'type' => 'category', 'label' => __( 'Hero Slot 3 — Filter by Category', 'bichitro-biggan' ), 'hint' => 'All' ),
				'bb_hero_slot_3'         => array( 'type' => 'post_select', 'label' => __( 'Hero Slot 3 — Bottom Left/Mid Post', 'bichitro-biggan' ), 'desc' => __( 'Select specific post or leave as Auto.', 'bichitro-biggan' ) ),
				'bb_hero_cat_4'          => array( 'type' => 'category', 'label' => __( 'Hero Slot 4 — Filter by Category', 'bichitro-biggan' ), 'hint' => 'All / Podcast' ),
				'bb_hero_slot_4'         => array( 'type' => 'post_select', 'label' => __( 'Hero Slot 4 — Bottom Right / Podcast Post', 'bichitro-biggan' ), 'desc' => __( 'Select specific post or leave as Auto.', 'bichitro-biggan' ) ),
				'bb_podcast_custom_time' => array( 'type' => 'text', 'label' => __( 'Custom Podcast Duration (Optional)', 'bichitro-biggan' ), 'desc' => __( 'E.g., "১৫:৪৫ মিনিট" or "45:20"', 'bichitro-biggan' ) ),
				'bb_hero_show_author'    => array( 'type' => 'checkbox', 'label' => __( 'Show Author & Date on all Hero Cards', 'bichitro-biggan' ), 'default' => true ),
				'bb_cat_block1_left'  => array( 'type' => 'category', 'label' => __( 'Block 1 — Left Side', 'bichitro-biggan' ), 'hint' => 'Quantum Science' ),
				'bb_cat_block1_right' => array( 'type' => 'category', 'label' => __( 'Block 1 — Right Side', 'bichitro-biggan' ), 'hint' => 'Nobel Prizes' ),
				'bb_cat_block2_left'  => array( 'type' => 'category', 'label' => __( 'Block 2 — Left Side', 'bichitro-biggan' ), 'hint' => 'Science of Life' ),
				'bb_cat_block2_right' => array( 'type' => 'category', 'label' => __( 'Block 2 — Right Side', 'bichitro-biggan' ), 'hint' => 'Physics' ),
				'bb_cat_block3_left'  => array( 'type' => 'category', 'label' => __( 'Block 3 — Left Side', 'bichitro-biggan' ), 'hint' => 'Miscellaneous Science' ),
				'bb_cat_block3_right' => array( 'type' => 'category', 'label' => __( 'Block 3 — Right Side', 'bichitro-biggan' ), 'hint' => 'Recent Science' ),
				'bb_cat_block4_a'     => array( 'type' => 'category', 'label' => __( 'Block 4 — Column 1', 'bichitro-biggan' ), 'hint' => 'Space Science' ),
				'bb_cat_block4_b'     => array( 'type' => 'category', 'label' => __( 'Block 4 — Column 2', 'bichitro-biggan' ), 'hint' => 'Stories of Scientists' ),
				'bb_cat_block5_a'     => array( 'type' => 'category', 'label' => __( 'Block 5 — Column 1', 'bichitro-biggan' ), 'hint' => 'Wonders of Universe' ),
				'bb_cat_block5_b'     => array( 'type' => 'category', 'label' => __( 'Block 5 — Column 2', 'bichitro-biggan' ), 'hint' => 'Nature & Environment' ),
				'bb_cat_block5_c'     => array( 'type' => 'category', 'label' => __( 'Block 5 — Column 3', 'bichitro-biggan' ), 'hint' => 'Science & Technology' ),
				'bb_cat_podcast'      => array( 'type' => 'category', 'label' => __( 'Podcast Tile (Hero)', 'bichitro-biggan' ), 'hint' => 'Podcast' ),
				'bb_show_ticker'      => array( 'type' => 'checkbox', 'label' => __( 'Show "New Posts" Ticker', 'bichitro-biggan' ), 'default' => true ),
				'bb_ticker_count'     => array( 'type' => 'number', 'label' => __( 'Ticker Post Count', 'bichitro-biggan' ), 'default' => 5, 'min' => 1, 'max' => 10 ),
				'bb_show_search'      => array( 'type' => 'checkbox', 'label' => __( 'Show Search Bar in Header', 'bichitro-biggan' ), 'default' => true ),
				'bb_show_years'       => array( 'type' => 'checkbox', 'label' => __( 'Show Year-wise Archive Tabs', 'bichitro-biggan' ), 'default' => true ),
			),
		),

		'interaction' => array(
			'label'  => __( 'Popup, Search & Editor', 'bichitro-biggan' ),
			'intro'  => __( 'Site interaction settings. If disabled, standard full-page navigation will be used.', 'bichitro-biggan' ),
			'fields' => array(
				'bb_enable_modal'     => array(
					'type'    => 'checkbox',
					'label'   => __( 'Open articles in popup modal', 'bichitro-biggan' ),
					'default' => true,
					'desc'    => __( 'Pushes real post URL to browser address bar and supports back/forward navigation.', 'bichitro-biggan' ),
				),
				'bb_enable_ajax_list' => array( 'type' => 'checkbox', 'label' => __( 'Enable AJAX pagination without full reload', 'bichitro-biggan' ), 'default' => true ),
				'bb_live_search'      => array(
					'type'    => 'checkbox',
					'label'   => __( 'Enable live search popup', 'bichitro-biggan' ),
					'default' => true,
					'desc'    => __( 'Opens live search overlay with instant results as you type.', 'bichitro-biggan' ),
				),
				'bb_slider_slides'    => array(
					'type'    => 'number',
					'label'   => __( 'Category slider slides count', 'bichitro-biggan' ),
					'default' => 3,
					'min'     => 1,
					'max'     => 5,
					'desc'    => __( 'Setting to 1 hides the ‹ › navigation arrows.', 'bichitro-biggan' ),
				),
				'bb_classic_editor'   => array(
					'type'    => 'checkbox',
					'label'   => __( 'Enable Classic Editor with Justify button', 'bichitro-biggan' ),
					'default' => true,
					'desc'    => __( 'Enables classic editor without extra plugins. Unchecking restores the Block Editor (Gutenberg).', 'bichitro-biggan' ),
				),
			),
		),

		'footer' => array(
			'label'  => __( 'Footer', 'bichitro-biggan' ),
			'intro'  => __( 'Configure footer content, lists, and credit links.', 'bichitro-biggan' ),
			'fields' => array(
				'bb_editor_picks_count'   => array(
					'type'    => 'number',
					'label'   => __( 'EDITOR PICKS — Post Count', 'bichitro-biggan' ),
					'default' => 3,
					'min'     => 1,
					'max'     => 8,
				),
				'bb_editor_picks'         => array(
					'type'  => 'posts',
					'label' => __( 'EDITOR PICKS — Select Posts', 'bichitro-biggan' ),
					'slots' => 8,
					'desc'  => __( 'Displays selected posts in order. If fewer posts are chosen, recent posts fill remaining slots.', 'bichitro-biggan' ),
				),
				'bb_popular_count'        => array(
					'type'    => 'number',
					'label'   => __( 'POPULAR POSTS — Post Count', 'bichitro-biggan' ),
					'default' => 3,
					'min'     => 1,
					'max'     => 8,
					'desc'    => __( 'Displays most viewed posts sorted by view count.', 'bichitro-biggan' ),
				),
				'bb_about_title'          => array( 'type' => 'text', 'label' => __( 'ABOUT US Heading', 'bichitro-biggan' ), 'default' => 'ABOUT US' ),
				'bb_about_text'           => array( 'type' => 'textarea', 'label' => __( 'ABOUT US text', 'bichitro-biggan' ), 'default' => 'BichitroBiggan is your source for science news, discoveries, and insights. We bring you the latest updates, research breakthroughs, and engaging stories from the world of science and technology.' ),
				'bb_contact_title'        => array( 'type' => 'text', 'label' => __( 'CONTACT US Heading', 'bichitro-biggan' ), 'default' => 'CONTACT US' ),
				'bb_subscribe_title'      => array( 'type' => 'text', 'label' => __( 'SUBSCRIBE US Heading', 'bichitro-biggan' ), 'default' => 'SUBSCRIBE US' ),
				'bb_footer_youtube_url'   => array( 'type' => 'url', 'label' => __( 'Footer YouTube Channel URL', 'bichitro-biggan' ), 'placeholder' => 'https://www.youtube.com/@bigganbichitro' ),
				'bb_footer_youtube_text'  => array( 'type' => 'text', 'label' => __( 'Footer YouTube Button Label', 'bichitro-biggan' ), 'default' => 'সাবস্ক্রাইব করুন' ),
				'bb_footer_author'        => array( 'type' => 'text', 'label' => __( 'Author and Editor — Name', 'bichitro-biggan' ), 'default' => 'Tanvir Hossain' ),
				'bb_footer_author_url'    => array( 'type' => 'url', 'label' => __( 'Author and Editor — URL', 'bichitro-biggan' ), 'placeholder' => 'https://' ),
				'bb_footer_copyright'     => array( 'type' => 'text', 'label' => __( 'Copyright Text', 'bichitro-biggan' ), 'default' => '©BichitroBiggan', 'desc' => __( 'Current year will be appended automatically.', 'bichitro-biggan' ) ),
				'bb_footer_developer'     => array( 'type' => 'text', 'label' => __( 'Developed by — Name', 'bichitro-biggan' ), 'default' => 'Raisul Islam' ),
				'bb_footer_developer_url' => array( 'type' => 'url', 'label' => __( 'Developed by — URL', 'bichitro-biggan' ), 'placeholder' => 'https://' ),
				'bb_author_bio_site'      => array( 'type' => 'text', 'label' => __( 'Site name displayed in author bio', 'bichitro-biggan' ), 'default' => 'bichitrobiggan.com' ),
			),
		),
	);
}

function bb_settings_menu() {
	add_menu_page(
		__( 'Theme Settings', 'bichitro-biggan' ),
		__( 'Theme Settings', 'bichitro-biggan' ),
		'edit_theme_options',
		'bb-theme-settings',
		'bb_settings_page',
		'dashicons-admin-customizer',
		61
	);
}
add_action( 'admin_menu', 'bb_settings_menu' );

function bb_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bichitro-biggan' ) );
	}

	$schema = bb_settings_schema();
	$saved  = false;

	if ( isset( $_POST['bb_settings_submit'] ) && check_admin_referer( 'bb_settings' ) ) {
		bb_settings_save( $schema );
		$saved = true;
	}

	$tabs   = array_keys( $schema );
	$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $tabs[0];

	if ( ! isset( $schema[ $active ] ) ) {
		$active = $tabs[0];
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bichitro Biggan — Theme Settings', 'bichitro-biggan' ); ?></h1>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bichitro-biggan' ); ?></p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $schema as $key => $group ) : ?>
				<a class="nav-tab<?php echo $key === $active ? ' nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=bb-theme-settings&tab=' . $key ) ); ?>">
					<?php echo esc_html( $group['label'] ); ?>
				</a>
			<?php endforeach; ?>
			<a class="nav-tab" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>">
				<?php esc_html_e( 'Logo & Tagline (Customizer)', 'bichitro-biggan' ); ?>
			</a>
		</h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bb-theme-settings&tab=' . $active ) ); ?>">
			<?php wp_nonce_field( 'bb_settings' ); ?>
			<input type="hidden" name="bb_settings_tab" value="<?php echo esc_attr( $active ); ?>" />

			<?php if ( ! empty( $schema[ $active ]['intro'] ) ) : ?>
				<p class="description" style="max-width:820px;margin:14px 0 6px;"><?php echo esc_html( $schema[ $active ]['intro'] ); ?></p>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<?php foreach ( $schema[ $active ]['fields'] as $key => $field ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
						<td><?php bb_settings_field( $key, $field ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button( __( 'Save Settings', 'bichitro-biggan' ), 'primary', 'bb_settings_submit' ); ?>
		</form>
	</div>
	<?php
}

function bb_settings_field( $key, $field ) {
	$type    = isset( $field['type'] ) ? $field['type'] : 'text';
	$default = isset( $field['default'] ) ? $field['default'] : ( 'category' === $type || 'number' === $type ? 0 : '' );
	$value   = get_theme_mod( $key, $default );

	switch ( $type ) {
		case 'category':
			echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
			foreach ( bb_category_choices() as $id => $label ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $id ),
					selected( (int) $value, (int) $id, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			if ( ! empty( $field['hint'] ) ) {
				echo '<p class="description">' . esc_html( sprintf( /* translators: %s: category name */ __( 'Default: %s', 'bichitro-biggan' ), $field['hint'] ) ) . '</p>';
			}
			break;

		case 'post_select':
			echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" style="max-width:520px;width:100%;">';
			foreach ( bb_post_choices() as $id => $label ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $id ),
					selected( (int) $value, (int) $id, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			break;

		case 'posts':
			$slots    = isset( $field['slots'] ) ? (int) $field['slots'] : 6;
			$selected = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
			$options  = bb_settings_post_options();

			for ( $i = 0; $i < $slots; $i++ ) {
				$current = isset( $selected[ $i ] ) ? $selected[ $i ] : 0;

				echo '<p style="margin:0 0 6px;"><label style="display:inline-block;width:2.5em;color:#666;">'
					. esc_html( ( $i + 1 ) ) . '.</label> ';
				echo '<select name="' . esc_attr( $key ) . '[]" style="max-width:520px;width:100%;">';
				echo '<option value="0">' . esc_html__( '— None —', 'bichitro-biggan' ) . '</option>';

				foreach ( $options as $post_id => $label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $post_id ),
						selected( $current, $post_id, false ),
						esc_html( $label )
					);
				}

				echo '</select></p>';
			}
			break;

		case 'number':
			printf(
				'<input type="number" class="small-text" name="%1$s" id="%1$s" value="%2$s" min="%3$s" max="%4$s" />',
				esc_attr( $key ),
				esc_attr( $value ),
				esc_attr( isset( $field['min'] ) ? $field['min'] : 0 ),
				esc_attr( isset( $field['max'] ) ? $field['max'] : 999 )
			);
			break;

		case 'checkbox':
			printf(
				'<label><input type="checkbox" name="%1$s" id="%1$s" value="1"%2$s /> %3$s</label>',
				esc_attr( $key ),
				checked( (bool) $value, true, false ),
				esc_html__( 'Enabled', 'bichitro-biggan' )
			);
			break;

		case 'textarea':
			printf(
				'<textarea name="%1$s" id="%1$s" rows="4" class="large-text">%2$s</textarea>',
				esc_attr( $key ),
				esc_textarea( $value )
			);
			break;

		case 'url':
			printf(
				'<input type="url" class="regular-text" name="%1$s" id="%1$s" value="%2$s" placeholder="%3$s" />',
				esc_attr( $key ),
				esc_attr( $value ),
				esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' )
			);
			break;

		default:
			printf(
				'<input type="text" class="regular-text" name="%1$s" id="%1$s" value="%2$s" />',
				esc_attr( $key ),
				esc_attr( $value )
			);
	}

	if ( ! empty( $field['desc'] ) ) {
		echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
	}
}

/**
 * Published posts for the EDITOR PICKS dropdowns, newest first.
 */
function bb_settings_post_options() {
	static $options = null;

	if ( null !== $options ) {
		return $options;
	}

	$options = array();

	$posts = get_posts( array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'posts_per_page'   => 500,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => false,
	) );

	foreach ( $posts as $post_item ) {
		$options[ $post_item->ID ] = get_the_title( $post_item ) . ' — ' . get_the_date( 'j M Y', $post_item );
	}

	return $options;
}

function bb_settings_save( $schema ) {
	$tab = isset( $_POST['bb_settings_tab'] ) ? sanitize_key( wp_unslash( $_POST['bb_settings_tab'] ) ) : '';

	if ( ! isset( $schema[ $tab ] ) ) {
		return;
	}

	foreach ( $schema[ $tab ]['fields'] as $key => $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		if ( 'checkbox' === $type ) {
			set_theme_mod( $key, ! empty( $_POST[ $key ] ) );
			continue;
		}

		if ( 'posts' === $type ) {
			$ids = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array();
			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
			$ids = array_values( array_unique( $ids ) );
			set_theme_mod( $key, $ids );
			continue;
		}

		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$raw = wp_unslash( $_POST[ $key ] );

		switch ( $type ) {
			case 'category':
			case 'post_select':
				set_theme_mod( $key, absint( $raw ) );
				break;

			case 'number':
				$number = absint( $raw );
				if ( isset( $field['min'] ) ) {
					$number = max( (int) $field['min'], $number );
				}
				if ( isset( $field['max'] ) ) {
					$number = min( (int) $field['max'], $number );
				}
				set_theme_mod( $key, $number );
				break;

			case 'textarea':
				set_theme_mod( $key, wp_kses_post( $raw ) );
				break;

			case 'url':
				set_theme_mod( $key, esc_url_raw( $raw ) );
				break;

			default:
				set_theme_mod( $key, sanitize_text_field( $raw ) );
		}
	}
}

/**
 * A shortcut from the toolbar, since these settings are edited often.
 */
function bb_admin_bar_link( $wp_admin_bar ) {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$wp_admin_bar->add_node( array(
		'id'    => 'bb-theme-settings',
		'title' => __( 'Theme Settings', 'bichitro-biggan' ),
		'href'  => admin_url( 'admin.php?page=bb-theme-settings' ),
	) );
}
add_action( 'admin_bar_menu', 'bb_admin_bar_link', 80 );
