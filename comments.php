<?php
/**
 * Comments.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="bb-comments">

	<?php if ( have_comments() ) : ?>
		<h3 class="bb-comments__title">
			<?php
			printf(
				/* translators: %s: comment count */
				esc_html( _n( '%s COMMENT', '%s COMMENTS', get_comments_number(), 'bichitro-biggan' ) ),
				esc_html( number_format_i18n( get_comments_number() ) )
			);
			?>
		</h3>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'    => 'ol',
				'callback' => 'bb_comment_callback',
				'avatar_size' => 36,
			) );
			?>
		</ol>

		<?php
		the_comments_pagination( array(
			'prev_text' => '‹',
			'next_text' => '›',
		) );
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="bb-card__excerpt"><?php esc_html_e( 'এই লেখায় মন্তব্য বন্ধ আছে।', 'bichitro-biggan' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'        => __( 'মন্তব্য করুন', 'bichitro-biggan' ),
		'title_reply_before' => '<h3 class="bb-comments__title">',
		'title_reply_after'  => '</h3>',
		'class_submit'       => 'submit',
		'comment_field'      => '<p><textarea id="comment" name="comment" rows="5" required placeholder="' . esc_attr__( 'আপনার মন্তব্য…', 'bichitro-biggan' ) . '"></textarea></p>',
	) );
	?>

</div>
