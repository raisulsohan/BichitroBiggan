/**
 * Customizer live preview.
 *
 * Every layout setting maps to a CSS variable on :root, so dragging a slider
 * updates the preview immediately instead of waiting for a page refresh.
 */
(function ($) {
	'use strict';

	if (typeof wp === 'undefined' || !wp.customize) {
		return;
	}

	var root = document.documentElement;

	function bindPx(setting, cssVar, min, unit) {
		wp.customize(setting, function (value) {
			value.bind(function (next) {
				var val = parseInt(next, 10);
				if (isNaN(val) || val < min) val = min;
				root.style.setProperty(cssVar, val + (unit || 'px'));
			});
		});
	}

	/* ---- logo & masthead controls ---- */
	bindPx('bb_logo_height', '--bb-logo-h', 12);
	bindPx('bb_logo_height_sticky', '--bb-logo-h-sticky', 12);
	bindPx('bb_logo_height_footer', '--bb-logo-h-footer', 12);
	bindPx('bb_logo_text_size', '--bb-logo-text', 10);
	bindPx('bb_tagline_font_size', '--bb-tagline-font', 9);
	bindPx('bb_tagline_max_width', '--bb-tagline-max-w', 150);
	bindPx('bb_yt_icon_size', '--bb-yt-icon-sz', 14);
	bindPx('bb_yt_font_size', '--bb-yt-font-sz', 9);

	wp.customize('bb_youtube_text', function (value) {
		value.bind(function (next) {
			$('.bb-masthead__yt-text').text(next);
		});
	});

	/* ---- homepage & modal layout, block by block ---- */
	var layout = (window.BBLayout && window.BBLayout.map) || {};

	Object.keys(layout).forEach(function (setting) {
		bindPx(setting, layout[setting].var, layout[setting].min, layout[setting].unit || 'px');
	});

	/* ---- footer controls live preview ---- */
	wp.customize('bb_editor_picks_title', function (value) {
		value.bind(function (next) {
			$('#bb-editor-picks-title').text(next);
		});
	});

	wp.customize('bb_popular_title', function (value) {
		value.bind(function (next) {
			$('#bb-popular-title').text(next);
		});
	});

	wp.customize('bb_popular_cat_title', function (value) {
		value.bind(function (next) {
			$('#bb-popular-cat-title').text(next);
		});
	});

	wp.customize('bb_about_title', function (value) {
		value.bind(function (next) {
			$('.bb-footer__about-label').text(next);
		});
	});

	wp.customize('bb_about_text', function (value) {
		value.bind(function (next) {
			$('.bb-footer__about-text').html(next);
		});
	});

	wp.customize('bb_contact_title', function (value) {
		value.bind(function (next) {
			$('.bb-footer__contact-title').text(next);
		});
	});

	wp.customize('bb_subscribe_title', function (value) {
		value.bind(function (next) {
			$('.bb-footer__subscribe-title').text(next);
		});
	});

	wp.customize('bb_footer_youtube_text', function (value) {
		value.bind(function (next) {
			$('.bb-footer__yt-text').text(next);
		});
	});

	wp.customize('blogname', function (value) {
		value.bind(function (next) {
			$('.bb-logo__text').text(next);
		});
	});
})(jQuery);
