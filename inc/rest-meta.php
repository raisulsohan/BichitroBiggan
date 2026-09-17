<?php
/**
 * The SEO and তথ্যসূত্র fields, through the REST API.
 *
 * The meta boxes save these from the editor's own form, which leaves a post
 * sent in over the REST API — from a publishing script signed in with an
 * Application Password — with no way to carry them. Registered here, the same
 * request that creates the post can set its focus keyphrase, SEO title and
 * description, so nothing has to be typed in afterwards.
 *
 * Writing needs the edit_post capability, the same check the meta boxes make.
 * Reading shows nothing new: every one of these is already printed in the
 * page's <head> or under the article.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta key => post types and the sanitizer its meta box uses.
 *
 * @return array<string, array{types:string[],sanitize:string}>
 */
function bb_rest_meta_fields() {
	return array(
		'bb_focus_keyphrase'    => array( 'types' => array( 'post', 'page' ), 'sanitize' => 'sanitize_text_field' ),
		'bb_keyphrase_synonyms' => array( 'types' => array( 'post', 'page' ), 'sanitize' => 'sanitize_text_field' ),
		'bb_seo_title'          => array( 'types' => array( 'post', 'page' ), 'sanitize' => 'sanitize_text_field' ),
		'bb_seo_description'    => array( 'types' => array( 'post', 'page' ), 'sanitize' => 'sanitize_textarea_field' ),
		'bb_references'         => array( 'types' => array( 'post' ), 'sanitize' => 'sanitize_textarea_field' ),
	);
}

function bb_register_rest_meta() {
	foreach ( bb_rest_meta_fields() as $meta_key => $field ) {
		foreach ( $field['types'] as $post_type ) {
			register_post_meta(
				$post_type,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $field['sanitize'],
					'auth_callback'     => 'bb_rest_meta_can_edit',
				)
			);
		}
	}
}
add_action( 'init', 'bb_register_rest_meta' );

/**
 * @param bool   $allowed  Whether the user can edit this meta.
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post the meta belongs to.
 * @return bool
 */
function bb_rest_meta_can_edit( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', $post_id );
}
