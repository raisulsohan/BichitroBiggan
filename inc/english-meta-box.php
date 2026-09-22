<?php
/**
 * Where the English version of a post is written.
 *
 * A box under the editor holding the English title, slug, excerpt, article and
 * SEO fields, plus the tick that decides whether /en shows the post at all.
 * The same fields are open to the REST API (inc/rest-meta.php), so a
 * translation can also be delivered by a script signed in with an Application
 * Password — nothing here has to be typed by hand.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types that can have an English version. */
function bb_en_post_types() {
	return (array) apply_filters( 'bb_en_post_types', array( 'post', 'page' ) );
}

/**
 * Add the box.
 */
function bb_en_add_meta_box() {
	foreach ( bb_en_post_types() as $post_type ) {
		add_meta_box(
			'bb-en-box',
			__( 'English edition (/en)', 'bichitro-biggan' ),
			'bb_en_render_meta_box',
			$post_type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'bb_en_add_meta_box' );

/**
 * Draw the box.
 *
 * @param WP_Post $post Post being edited.
 * @return void
 */
function bb_en_render_meta_box( $post ) {
	wp_nonce_field( 'bb_en_save_' . $post->ID, 'bb_en_nonce' );

	$ready    = bb_en_get( $post->ID, 'bb_en_ready' );
	$title    = bb_en_get( $post->ID, 'bb_en_title' );
	$slug     = bb_en_get( $post->ID, 'bb_en_slug' );
	$excerpt  = bb_en_get( $post->ID, 'bb_en_excerpt' );
	$content  = (string) get_post_meta( $post->ID, 'bb_en_content', true );
	$seo_t    = bb_en_get( $post->ID, 'bb_en_seo_title' );
	$seo_d    = bb_en_get( $post->ID, 'bb_en_seo_description' );
	$keyword  = bb_en_get( $post->ID, 'bb_en_focus_keyphrase' );
	$has      = bb_en_has( $post->ID );
	$view_url = '';

	if ( $has && 'publish' === $post->post_status ) {
		$post_id  = (int) $post->ID;
		$view_url = (string) bb_en_in_lang(
			'en',
			function () use ( $post_id ) {
				return get_permalink( $post_id );
			}
		);
	}
	?>
	<style>
		.bb-en-grid { display: grid; gap: 14px; }
		.bb-en-grid label { display: block; font-weight: 600; margin-bottom: 4px; }
		.bb-en-grid input[type="text"], .bb-en-grid textarea { width: 100%; }
		.bb-en-hint { color: #646970; font-weight: 400; font-size: 12px; }
		.bb-en-status { padding: 8px 12px; border-left: 4px solid #d1d5db; background: #f6f7f7; }
		.bb-en-status--live { border-left-color: #00a32a; }
		.bb-en-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
		@media (max-width: 782px) { .bb-en-row2 { grid-template-columns: 1fr; } }
	</style>

	<div class="bb-en-grid">

		<div class="bb-en-status <?php echo $has ? 'bb-en-status--live' : ''; ?>">
			<?php if ( $has ) : ?>
				<strong><?php esc_html_e( 'Live in the English edition.', 'bichitro-biggan' ); ?></strong>
				<?php if ( $view_url ) : ?>
					<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'bichitro-biggan' ); ?></a>
				<?php endif; ?>
			<?php else : ?>
				<strong><?php esc_html_e( 'Bengali only.', 'bichitro-biggan' ); ?></strong>
				<span class="bb-en-hint"><?php esc_html_e( 'A post needs an English title, an English article and the tick below before /en will show it.', 'bichitro-biggan' ); ?></span>
			<?php endif; ?>
		</div>

		<p>
			<label for="bb_en_ready">
				<input type="checkbox" id="bb_en_ready" name="bb_en_ready" value="1" <?php checked( '1', $ready ); ?> />
				<?php esc_html_e( 'Ready — show this post in the English edition', 'bichitro-biggan' ); ?>
			</label>
		</p>

		<div>
			<label for="bb_en_title"><?php esc_html_e( 'English title', 'bichitro-biggan' ); ?></label>
			<input type="text" id="bb_en_title" name="bb_en_title" value="<?php echo esc_attr( $title ); ?>" />
		</div>

		<div>
			<label for="bb_en_slug">
				<?php esc_html_e( 'English slug', 'bichitro-biggan' ); ?>
				<span class="bb-en-hint"><?php esc_html_e( '— the address under /en/. Leave empty to reuse the Bengali one.', 'bichitro-biggan' ); ?></span>
			</label>
			<input type="text" id="bb_en_slug" name="bb_en_slug" value="<?php echo esc_attr( $slug ); ?>" />
		</div>

		<div>
			<label for="bb_en_excerpt">
				<?php esc_html_e( 'English excerpt', 'bichitro-biggan' ); ?>
				<span class="bb-en-hint"><?php esc_html_e( '— used on cards and lists. Leave empty to trim the article.', 'bichitro-biggan' ); ?></span>
			</label>
			<textarea id="bb_en_excerpt" name="bb_en_excerpt" rows="2"><?php echo esc_textarea( $excerpt ); ?></textarea>
		</div>

		<div>
			<label for="bb_en_content"><?php esc_html_e( 'English article', 'bichitro-biggan' ); ?></label>
			<?php
			wp_editor(
				$content,
				'bb_en_content',
				array(
					'textarea_name' => 'bb_en_content',
					'textarea_rows' => 18,
					'media_buttons' => true,
					'teeny'         => false,
					'tinymce'       => true,
					'quicktags'     => true,
				)
			);
			?>
		</div>

		<div class="bb-en-row2">
			<div>
				<label for="bb_en_seo_title"><?php esc_html_e( 'English SEO title', 'bichitro-biggan' ); ?></label>
				<input type="text" id="bb_en_seo_title" name="bb_en_seo_title" value="<?php echo esc_attr( $seo_t ); ?>" />
			</div>
			<div>
				<label for="bb_en_focus_keyphrase"><?php esc_html_e( 'English focus keyphrase', 'bichitro-biggan' ); ?></label>
				<input type="text" id="bb_en_focus_keyphrase" name="bb_en_focus_keyphrase" value="<?php echo esc_attr( $keyword ); ?>" />
			</div>
		</div>

		<div>
			<label for="bb_en_seo_description">
				<?php esc_html_e( 'English meta description', 'bichitro-biggan' ); ?>
				<span class="bb-en-hint"><?php esc_html_e( '— up to about 155 characters.', 'bichitro-biggan' ); ?></span>
			</label>
			<textarea id="bb_en_seo_description" name="bb_en_seo_description" rows="3"><?php echo esc_textarea( $seo_d ); ?></textarea>
		</div>

	</div>
	<?php
}

/**
 * Save the box.
 *
 * @param int $post_id Post being saved.
 * @return void
 */
function bb_en_save_meta_box( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['bb_en_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['bb_en_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'bb_en_save_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( bb_en_meta_fields() as $key => $field ) {
		if ( 'checkbox' === $field['type'] ) {
			$value = isset( $_POST[ $key ] ) ? '1' : '';
		} elseif ( ! isset( $_POST[ $key ] ) ) {
			continue;
		} else {
			$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = call_user_func( $field['sanitize'], $raw );
		}

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		update_post_meta( $post_id, $key, $value );
	}

	delete_post_meta( $post_id, 'bb_en_read_minutes' );
}
add_action( 'save_post', 'bb_en_save_meta_box' );

/* -------------------------------------------------------------------------
 * The posts list
 * ---------------------------------------------------------------------- */

/**
 * @param string[] $columns Columns.
 * @return string[]
 */
function bb_en_admin_column( $columns ) {
	$columns['bb_en'] = __( 'EN', 'bichitro-biggan' );

	return $columns;
}
add_filter( 'manage_post_posts_columns', 'bb_en_admin_column' );

/**
 * @param string $column  Column key.
 * @param int    $post_id Post.
 * @return void
 */
function bb_en_admin_column_content( $column, $post_id ) {
	if ( 'bb_en' !== $column ) {
		return;
	}

	if ( bb_en_has( $post_id ) ) {
		echo '<span title="' . esc_attr__( 'Live in the English edition', 'bichitro-biggan' ) . '" style="color:#00a32a;font-weight:700;">EN</span>';
		return;
	}

	if ( '' !== bb_en_get( $post_id, 'bb_en_content' ) ) {
		echo '<span title="' . esc_attr__( 'Translated, not ticked as ready', 'bichitro-biggan' ) . '" style="color:#dba617;font-weight:700;">•••</span>';
		return;
	}

	echo '<span style="color:#c3c4c7;">—</span>';
}
add_action( 'manage_post_posts_custom_column', 'bb_en_admin_column_content', 10, 2 );

/* -------------------------------------------------------------------------
 * Writers
 * ---------------------------------------------------------------------- */

/**
 * English name and biography on the profile screen.
 *
 * @param WP_User $user User being edited.
 * @return void
 */
function bb_en_user_fields( $user ) {
	$name = (string) get_user_meta( $user->ID, 'bb_en_display_name', true );
	$bio  = (string) get_user_meta( $user->ID, 'bb_en_description', true );
	?>
	<h2><?php esc_html_e( 'English edition (/en)', 'bichitro-biggan' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="bb_en_display_name"><?php esc_html_e( 'Name in English', 'bichitro-biggan' ); ?></label></th>
			<td>
				<input type="text" name="bb_en_display_name" id="bb_en_display_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Used for the byline and the author page under /en.', 'bichitro-biggan' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="bb_en_description"><?php esc_html_e( 'Biography in English', 'bichitro-biggan' ); ?></label></th>
			<td><textarea name="bb_en_description" id="bb_en_description" rows="5" class="regular-text"><?php echo esc_textarea( $bio ); ?></textarea></td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'bb_en_user_fields' );
add_action( 'edit_user_profile', 'bb_en_user_fields' );

/**
 * Save them.
 *
 * @param int $user_id User.
 * @return void
 */
function bb_en_save_user_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
		return;
	}

	$pairs = array(
		'bb_en_display_name' => 'sanitize_text_field',
		'bb_en_description'  => 'sanitize_textarea_field',
	);

	foreach ( $pairs as $key => $sanitize ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$value = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === trim( $value ) ) {
			delete_user_meta( $user_id, $key );
			continue;
		}

		update_user_meta( $user_id, $key, $value );
	}
}
add_action( 'personal_options_update', 'bb_en_save_user_fields' );
add_action( 'edit_user_profile_update', 'bb_en_save_user_fields' );

/* -------------------------------------------------------------------------
 * Category and tag names
 * ---------------------------------------------------------------------- */

/**
 * The English name field on the "add category" form.
 *
 * @return void
 */
function bb_en_term_add_field() {
	?>
	<div class="form-field">
		<label for="bb_en_name"><?php esc_html_e( 'English name', 'bichitro-biggan' ); ?></label>
		<input type="text" name="bb_en_name" id="bb_en_name" value="" />
		<p><?php esc_html_e( 'Shown instead of the Bengali name under /en.', 'bichitro-biggan' ); ?></p>
	</div>
	<?php
}
add_action( 'category_add_form_fields', 'bb_en_term_add_field' );
add_action( 'post_tag_add_form_fields', 'bb_en_term_add_field' );

/**
 * The same field on the edit form.
 *
 * @param WP_Term $term Term being edited.
 * @return void
 */
function bb_en_term_edit_field( $term ) {
	$name        = (string) get_term_meta( $term->term_id, 'bb_en_name', true );
	$description = (string) get_term_meta( $term->term_id, 'bb_en_description', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="bb_en_name"><?php esc_html_e( 'English name', 'bichitro-biggan' ); ?></label></th>
		<td>
			<input type="text" name="bb_en_name" id="bb_en_name" value="<?php echo esc_attr( $name ); ?>" />
			<p class="description"><?php esc_html_e( 'Shown instead of the Bengali name under /en.', 'bichitro-biggan' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="bb_en_description"><?php esc_html_e( 'English description', 'bichitro-biggan' ); ?></label></th>
		<td>
			<textarea name="bb_en_description" id="bb_en_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
		</td>
	</tr>
	<?php
}
add_action( 'category_edit_form_fields', 'bb_en_term_edit_field' );
add_action( 'post_tag_edit_form_fields', 'bb_en_term_edit_field' );

/**
 * Save both term fields.
 *
 * WordPress checks the nonce and the capability for term forms before this
 * runs, the same way the category colour field relies on it.
 *
 * @param int $term_id Term.
 * @return void
 */
function bb_en_save_term_fields( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	if ( isset( $_POST['bb_en_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$name = sanitize_text_field( wp_unslash( $_POST['bb_en_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $name ) {
			delete_term_meta( $term_id, 'bb_en_name' );
		} else {
			update_term_meta( $term_id, 'bb_en_name', $name );
		}
	}

	if ( isset( $_POST['bb_en_description'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$description = sanitize_textarea_field( wp_unslash( $_POST['bb_en_description'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $description ) {
			delete_term_meta( $term_id, 'bb_en_description' );
		} else {
			update_term_meta( $term_id, 'bb_en_description', $description );
		}
	}
}
add_action( 'created_category', 'bb_en_save_term_fields' );
add_action( 'edited_category', 'bb_en_save_term_fields' );
add_action( 'created_post_tag', 'bb_en_save_term_fields' );
add_action( 'edited_post_tag', 'bb_en_save_term_fields' );
