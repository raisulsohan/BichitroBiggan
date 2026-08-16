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

	function bindPx(setting, cssVar, min) {
		wp.customize(setting, function (value) {
			value.bind(function (next) {
				var px = parseInt(next, 10);
				if (isNaN(px) || px < min) px = min;
				root.style.setProperty(cssVar, px + 'px');
			});
		});
	}

	/* ---- logo ---- */
	bindPx('bb_logo_height', '--bb-logo-h', 12);
	bindPx('bb_logo_height_sticky', '--bb-logo-h-sticky', 12);
	bindPx('bb_logo_height_footer', '--bb-logo-h-footer', 12);
	bindPx('bb_logo_text_size', '--bb-logo-text', 10);

	/* ---- homepage layout, block by block ---- */
	var layout = (window.BBLayout && window.BBLayout.map) || {};

	Object.keys(layout).forEach(function (setting) {
		bindPx(setting, layout[setting].var, layout[setting].min);
	});

	wp.customize('blogname', function (value) {
		value.bind(function (next) {
			$('.bb-logo__text').text(next);
		});
	});
})(jQuery);
