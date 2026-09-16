/**
 * Finds text the reader cannot comfortably read.
 *
 * Paste this into the browser console on any page of the site — in dark mode
 * and again in light — and it lists every piece of visible text whose colour
 * is too close to what sits behind it, worst first. Guessing which corner of
 * a 3,000-line stylesheet the dark palette missed does not scale; this checks
 * all of them.
 *
 * WCAG AA wants 4.5:1 for body text and 3:1 for large text.
 */
(function () {
	'use strict';

	function parse(color) {
		var m = /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,/\s]+([\d.]+))?/i.exec(color || '');
		if (!m) return null;
		return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
	}

	function over(top, bottom) {
		if (top.a >= 1) return top;
		return {
			r: top.r * top.a + bottom.r * (1 - top.a),
			g: top.g * top.a + bottom.g * (1 - top.a),
			b: top.b * top.a + bottom.b * (1 - top.a),
			a: 1
		};
	}

	function luminance(c) {
		var channel = function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		};
		return 0.2126 * channel(c.r) + 0.7152 * channel(c.g) + 0.0722 * channel(c.b);
	}

	function ratio(a, b) {
		var l1 = luminance(a);
		var l2 = luminance(b);
		return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
	}

	// What is actually behind this element: the first ancestor that paints.
	function backdrop(el) {
		var node = el;
		var stack = [];

		while (node && node !== document.documentElement) {
			var cs = getComputedStyle(node);
			var bg = parse(cs.backgroundColor);

			if (cs.backgroundImage && cs.backgroundImage !== 'none') return { image: true, color: null };
			if (bg && bg.a > 0) {
				stack.push(bg);
				if (bg.a >= 1) break;
			}

			node = node.parentElement;
		}

		var base = parse(getComputedStyle(document.documentElement).backgroundColor);
		var result = (base && base.a >= 1) ? base : { r: 255, g: 255, b: 255, a: 1 };

		for (var i = stack.length - 1; i >= 0; i--) result = over(stack[i], result);

		return { image: false, color: result };
	}

	function describe(el) {
		var name = el.tagName.toLowerCase();
		if (el.id) name += '#' + el.id;
		if (el.className && typeof el.className === 'string') {
			name += '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.');
		}
		return name;
	}

	function visible(el) {
		var cs = getComputedStyle(el);
		if (cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity === 0) return false;
		var rect = el.getBoundingClientRect();
		return rect.width > 1 && rect.height > 1;
	}

	function ownText(el) {
		var text = '';
		for (var i = 0; i < el.childNodes.length; i++) {
			if (el.childNodes[i].nodeType === 3) text += el.childNodes[i].textContent;
		}
		return text.trim();
	}

	var findings = {};
	var checked = 0;

	Array.prototype.forEach.call(document.querySelectorAll('body *'), function (el) {
		var text = ownText(el);
		if (!text || !visible(el)) return;

		var cs = getComputedStyle(el);
		var fg = parse(cs.color);
		if (!fg) return;

		var behind = backdrop(el);
		if (behind.image || !behind.color) return;   // text over a photograph is its own question

		checked++;

		var colour = over(fg, behind.color);
		var contrast = ratio(colour, behind.color);
		var size = parseFloat(cs.fontSize);
		var weight = parseInt(cs.fontWeight, 10) || 400;
		var large = size >= 24 || (size >= 18.66 && weight >= 700);
		var required = large ? 3 : 4.5;

		if (contrast >= required) return;

		var key = describe(el) + '|' + cs.color + '|' + Math.round(contrast * 10);

		if (!findings[key]) {
			findings[key] = {
				element: describe(el),
				contrast: Math.round(contrast * 100) / 100,
				needs: required,
				color: cs.color,
				behind: 'rgb(' + Math.round(behind.color.r) + ', ' + Math.round(behind.color.g) + ', ' + Math.round(behind.color.b) + ')',
				size: Math.round(size) + 'px/' + weight,
				sample: text.slice(0, 30),
				count: 0
			};
		}

		findings[key].count++;
	});

	var list = Object.keys(findings).map(function (k) { return findings[k]; })
		.sort(function (a, b) { return a.contrast - b.contrast; });

	return {
		theme: document.documentElement.getAttribute('data-theme') || 'not set',
		url: location.pathname,
		textNodesChecked: checked,
		failing: list.length,
		worst: list.slice(0, 25)
	};
})();
