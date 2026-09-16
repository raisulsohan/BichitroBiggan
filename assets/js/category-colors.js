/**
 * The category colour table: keeps the swatch, the hex field and the three
 * badge previews in step, and measures each option's contrast as it goes.
 *
 * The measurement is WCAG's: the same one the theme uses when it picks the
 * text colour itself.
 */
(function () {
	'use strict';

	var L = window.bbCategoryColors || {};
	var AUTO_DARK = '#1a1a1a';
	var AUTO_LIGHT = '#ffffff';

	function parseHex(value) {
		var hex = String(value || '').trim().replace(/^#/, '');

		if (hex.length === 3) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}

		if (!/^[0-9a-f]{6}$/i.test(hex)) return null;

		return {
			r: parseInt(hex.slice(0, 2), 16),
			g: parseInt(hex.slice(2, 4), 16),
			b: parseInt(hex.slice(4, 6), 16)
		};
	}

	function luminance(rgb) {
		var channel = function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		};

		return 0.2126 * channel(rgb.r) + 0.7152 * channel(rgb.g) + 0.0722 * channel(rgb.b);
	}

	function contrast(a, b) {
		var l1 = luminance(a);
		var l2 = luminance(b);

		return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
	}

	function verdict(ratio) {
		if (ratio >= 4.5) return L.good || 'good';
		if (ratio >= 3) return L.ok || 'ok';
		return L.poor || 'poor';
	}

	function refresh(row) {
		var hexField = row.querySelector('[data-bb-color-hex]');
		var picker = row.querySelector('[data-bb-color-picker]');
		var background = parseHex(hexField.value);

		if (!background) return;

		picker.value = '#' + [background.r, background.g, background.b]
			.map(function (n) { return n.toString(16).padStart(2, '0'); }).join('');

		var white = contrast(background, parseHex(AUTO_LIGHT));
		var dark = contrast(background, parseHex(AUTO_DARK));
		var auto = dark > white ? AUTO_DARK : AUTO_LIGHT;

		Array.prototype.forEach.call(row.querySelectorAll('[data-bb-preview]'), function (chip) {
			var choice = chip.getAttribute('data-bb-preview');
			var text = choice === 'auto' ? auto : choice;
			var ratio = text === AUTO_DARK ? dark : white;

			chip.style.background = picker.value;
			chip.style.color = text;

			var readout = row.querySelector('[data-bb-ratio="' + choice + '"]');

			if (readout) {
				readout.textContent = ratio.toFixed(1) + ':1 — ' + verdict(ratio) +
					(choice === 'auto' ? ' (' + (text === AUTO_DARK ? L.autoLabel || 'auto' : L.autoLabel || 'auto') + ')' : '');
			}
		});
	}

	function bind(row) {
		var hexField = row.querySelector('[data-bb-color-hex]');
		var picker = row.querySelector('[data-bb-color-picker]');

		if (!hexField || !picker) return;

		picker.addEventListener('input', function () {
			hexField.value = picker.value;
			refresh(row);
		});

		hexField.addEventListener('input', function () {
			refresh(row);
		});

		// Clicking the badge itself is the quickest way to choose it.
		Array.prototype.forEach.call(row.querySelectorAll('[data-bb-preview]'), function (chip) {
			chip.addEventListener('click', function () {
				var label = chip.closest('label');
				var radio = label && label.querySelector('[data-bb-text-choice]');
				if (radio) radio.checked = true;
			});
		});

		refresh(row);
	}

	function start() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-bb-row]'), bind);
	}

	if (document.readyState !== 'loading') start();
	else document.addEventListener('DOMContentLoaded', start);
})();
