import sys
import re

path = 'd:/GitHub/BichitroBiggan/Website/bichitro-biggan/inc/template-tags.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

old_video_func = """function bb_get_post_video_url( $post_id ) {
	$video_url = get_post_meta( $post_id, 'video_url', true );
	if ( ! empty( $video_url ) ) {
		return $video_url;
	}

	$post    = get_post( $post_id );
	$content = $post->post_content;

	if ( preg_match( '/<iframe.*?src=\"(https:\/\/www\.youtube\.com\/embed\/[^\"?]+).*?\".*?><\/iframe>/i', $content, $matches ) ) {
		return $matches[1];
	}
	if ( preg_match( '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $content, $matches ) ) {
		return 'https://www.youtube.com/embed/' . $matches[1];
	}

	return '';
}"""

new_video_func = """function bb_get_post_video_url( $post_id ) {
	$video_url = get_post_meta( $post_id, 'video_url', true );
	if ( ! empty( $video_url ) ) {
		return $video_url;
	}

	// Only extract embedded videos if this is actually a Podcast/Video post.
	// We don't want standard articles with reference videos to turn into video cards.
	if ( function_exists( 'bb_is_podcast_post' ) && bb_is_podcast_post( $post_id ) ) {
		$post    = get_post( $post_id );
		$content = $post->post_content;

		if ( preg_match( '/<iframe.*?src=\"(https:\/\/www\.youtube\.com\/embed\/[^\"?]+).*?\".*?><\/iframe>/i', $content, $matches ) ) {
			return $matches[1];
		}
		if ( preg_match( '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $content, $matches ) ) {
			return 'https://www.youtube.com/embed/' . $matches[1];
		}
	}

	return '';
}

function bb_get_video_ratio( $post_id, $video_url ) {
	$ratio = get_post_meta( $post_id, 'video_ratio', true );
	if ( empty( $ratio ) && $video_url ) {
		if ( stripos( $video_url, 'shorts' ) !== false || stripos( $video_url, 'reel' ) !== false ) {
			return '9/16';
		}
		return '16/9';
	}
	return $ratio ? $ratio : '16/9';
}"""

content = content.replace(old_video_func, new_video_func)

old_hero_card_part = """$video_url = bb_get_post_video_url( get_the_ID() );

	$tag_text = '';"""

new_hero_card_part = """$video_url = bb_get_post_video_url( get_the_ID() );
	$video_ratio = $video_url ? bb_get_video_ratio( get_the_ID(), $video_url ) : '';

	$tag_text = '';"""

content = content.replace(old_hero_card_part, new_hero_card_part)

old_hero_click = """<a href="<?php echo esc_url( get_permalink() ); ?>" class="bb-hero__click" <?php if ( ! $video_url ) { bb_article_attr(); } ?>>
			<span class="screen-reader-text"><?php the_title(); ?></span>
		</a>

		<?php if ( $video_url ) : ?>
			<div class="bb-hero__video-wrapper">
				<iframe src="<?php echo esc_url( $video_url ); ?>" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
			</div>
			<div class="bb-hero__play-btn">
				<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
			</div>
		<?php endif; ?>"""

new_hero_click = """<a href="<?php echo esc_url( get_permalink() ); ?>" class="bb-hero__click" 
			<?php if ( $video_url ) : ?>
				data-bb-video-popup="<?php echo esc_attr( $video_url ); ?>"
				data-bb-video-ratio="<?php echo esc_attr( $video_ratio ); ?>"
			<?php else : ?>
				<?php bb_article_attr(); ?>
			<?php endif; ?>
		>
			<span class="screen-reader-text"><?php the_title(); ?></span>
		</a>

		<?php if ( $video_url ) : ?>
			<div class="bb-hero__play-btn">
				<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
			</div>
		<?php endif; ?>"""

content = content.replace(old_hero_click, new_hero_click)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print('Updated successfully.')
