<?php
/**
 * Author pages that do not spell out the login name.
 *
 * WordPress builds /author/<user_nicename>/, and user_nicename is created from
 * the username — so the archive URL hands out the account name even after the
 * display name has been changed. The name cannot be edited from the profile
 * screen, and changing it means a database edit.
 *
 * So the public address is built from the display name instead, and a request
 * for it is mapped back to the right author. Nothing in the database changes,
 * and the old address redirects to the new one.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. The public slug for an author
 * ======================================================================= */

/**
 * What this author's page is called in public.
 *
 * The slug set on their profile wins; otherwise it comes from the display
 * name. It is never allowed to equal the login name.
 *
 * @param int $user_id User.
 * @return string Slug, or '' when there is no user.
 */
function bb_author_slug( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user ) {
		return '';
	}

	$login  = strtolower( $user->user_login );
	$custom = sanitize_title( (string) get_user_meta( $user->ID, 'bb_author_slug', true ) );

	if ( $custom && $custom !== $login ) {
		return $custom;
	}

	$slug = sanitize_title( $user->display_name );

	if ( ! $slug || $slug === $login ) {
		$slug = sanitize_title( trim( $user->first_name . ' ' . $user->last_name ) );
	}

	if ( ! $slug || $slug === $login ) {
		$slug = 'author-' . $user->ID;
	}

	/**
	 * Filter an author's public slug.
	 *
	 * @param string  $slug Slug.
	 * @param WP_User $user Author.
	 */
	return (string) apply_filters( 'bb_author_slug', $slug, $user );
}

/**
 * slug => user ID, for turning a request back into an author.
 *
 * @return array<string, int>
 */
function bb_author_slug_map() {
	$map = get_transient( 'bb_author_slugs' );

	if ( is_array( $map ) ) {
		return $map;
	}

	$map = array();

	foreach ( get_users( array( 'fields' => array( 'ID' ), 'number' => 200 ) ) as $user ) {
		$slug = bb_author_slug( $user->ID );

		if ( $slug && ! isset( $map[ $slug ] ) ) {
			$map[ $slug ] = (int) $user->ID;
		}
	}

	set_transient( 'bb_author_slugs', $map, DAY_IN_SECONDS );

	return $map;
}

function bb_flush_author_slugs() {
	delete_transient( 'bb_author_slugs' );
}
add_action( 'profile_update', 'bb_flush_author_slugs' );
add_action( 'user_register', 'bb_flush_author_slugs' );
add_action( 'deleted_user', 'bb_flush_author_slugs' );

/* =========================================================================
 * 2. Print the public address
 * ======================================================================= */

/**
 * Swap the nicename in an author link for the public slug.
 *
 * @param string $link     Author archive URL.
 * @param int    $user_id  Author.
 * @param string $nicename The name WordPress put in the URL.
 * @return string
 */
function bb_author_link( $link, $user_id, $nicename ) {
	if ( ! $nicename || ! get_option( 'permalink_structure' ) ) {
		return $link;
	}

	$slug = bb_author_slug( $user_id );

	if ( ! $slug || $slug === $nicename ) {
		return $link;
	}

	return preg_replace( '#/' . preg_quote( $nicename, '#' ) . '(/?)$#', '/' . $slug . '$1', $link );
}
add_filter( 'author_link', 'bb_author_link', 10, 3 );

/* =========================================================================
 * 3. Understand the public address on the way back in
 * ======================================================================= */

/**
 * Resolve /author/<public slug>/ to its author.
 *
 * WordPress looks the segment up as a nicename, finds nothing and answers 404,
 * so the lookup is repeated here against the public slugs.
 *
 * @param WP_Query $query Current query.
 */
function bb_resolve_author_slug( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$requested = (string) $query->get( 'author_name' );

	if ( '' === $requested ) {
		return;
	}

	// A real nicename still resolves on its own; see the redirect below.
	if ( get_user_by( 'slug', $requested ) ) {
		return;
	}

	$map = bb_author_slug_map();

	if ( empty( $map[ $requested ] ) ) {
		return;
	}

	$query->set( 'author_name', '' );
	$query->set( 'author', $map[ $requested ] );
}
add_action( 'pre_get_posts', 'bb_resolve_author_slug' );

/**
 * Send the old /author/<login name>/ address to the public one.
 *
 * It keeps any link already shared working, and stops the login name from
 * being served at an address of its own.
 */
function bb_redirect_nicename_author() {
	if ( is_admin() || ! is_author() ) {
		return;
	}

	$requested = (string) get_query_var( 'author_name' );

	if ( '' === $requested ) {
		return; // Already arrived by the public slug.
	}

	$user = get_queried_object();

	if ( ! $user instanceof WP_User ) {
		return;
	}

	$slug = bb_author_slug( $user->ID );

	if ( ! $slug || $slug === $requested ) {
		return;
	}

	wp_safe_redirect( get_author_posts_url( $user->ID ), 301 );
	exit;
}
add_action( 'template_redirect', 'bb_redirect_nicename_author', 1 );

/* =========================================================================
 * 4. The field on the profile screen
 * ======================================================================= */

/**
 * @param WP_User $user User being edited.
 */
function bb_author_slug_field( $user ) {
	$slug = get_user_meta( $user->ID, 'bb_author_slug', true );
	?>
	<h2><?php esc_html_e( 'লেখক পাতার ঠিকানা', 'bichitro-biggan' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="bb_author_slug"><?php esc_html_e( 'ঠিকানা', 'bichitro-biggan' ); ?></label></th>
			<td>
				<code><?php echo esc_html( trailingslashit( home_url( '/author' ) ) ); ?></code>
				<input type="text" name="bb_author_slug" id="bb_author_slug"
					value="<?php echo esc_attr( $slug ); ?>"
					placeholder="<?php echo esc_attr( bb_author_slug( $user->ID ) ); ?>"
					class="regular-text" />
				<p class="description">
					<?php esc_html_e( 'খালি রাখলে প্রদর্শিত নাম থেকে নিজে থেকেই তৈরি হবে। লগইন নাম কখনো ব্যবহার হবে না।', 'bichitro-biggan' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'bb_author_slug_field' );
add_action( 'edit_user_profile', 'bb_author_slug_field' );

function bb_save_author_slug_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['bb_author_slug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$slug = sanitize_title( wp_unslash( $_POST['bb_author_slug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$user = get_userdata( $user_id );

	// A slug that is the login name defeats the point of the field.
	if ( $user && $slug === strtolower( $user->user_login ) ) {
		$slug = '';
	}

	if ( $slug ) {
		update_user_meta( $user_id, 'bb_author_slug', $slug );
	} else {
		delete_user_meta( $user_id, 'bb_author_slug' );
	}

	bb_flush_author_slugs();
}
add_action( 'personal_options_update', 'bb_save_author_slug_field' );
add_action( 'edit_user_profile_update', 'bb_save_author_slug_field' );
