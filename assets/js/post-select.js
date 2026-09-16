/**
 * Type-to-search over the post pickers.
 *
 * Each picker keeps its own list of the newest posts; typing two characters
 * replaces that list with search results from the whole archive, and clearing
 * the box puts the original list back. The current selection is never lost.
 */
(function () {
	'use strict';

	var D = window.bbPostSearch || {};

	function attach(select) {
		if (!select || select.getAttribute('data-bb-search')) return;
		select.setAttribute('data-bb-search', '1');

		var box = document.createElement('input');
		box.type = 'search';
		box.className = 'bb-post-search';
		box.placeholder = D.placeholder || 'Search posts…';
		box.style.width = '100%';
		box.style.maxWidth = '520px';
		box.style.marginBottom = '4px';

		select.parentNode.insertBefore(box, select);

		var original = select.innerHTML;
		var timer = null;

		box.addEventListener('input', function () {
			var query = box.value.trim();
			window.clearTimeout(timer);

			if (query.length < 2) {
				restore();
				return;
			}

			timer = window.setTimeout(function () {
				search(query);
			}, 300);
		});

		// The picker sits inside a form; Enter would submit the page.
		box.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') e.preventDefault();
		});

		function restore() {
			var current = select.value;
			select.innerHTML = original;
			select.value = current;
		}

		function search(query) {
			var url = D.ajaxUrl + '?action=bb_search_posts&nonce=' + encodeURIComponent(D.nonce || '') +
				'&q=' + encodeURIComponent(query);

			fetch(url, { credentials: 'same-origin' })
				.then(function (r) {
					if (!r.ok) throw new Error('HTTP ' + r.status);
					return r.json();
				})
				.then(function (res) {
					if (!res || !res.success) return;

					var current = select.value;
					var currentOption = select.options[select.selectedIndex];
					var currentLabel = currentOption ? currentOption.text : '';

					select.innerHTML = '';

					// Whatever is already chosen stays at the top, so searching
					// never silently changes the saved value.
					var keep = document.createElement('option');
					keep.value = current;
					keep.textContent = currentLabel;
					select.appendChild(keep);

					res.data.forEach(function (item) {
						if (String(item.id) === String(current)) return;
						var option = document.createElement('option');
						option.value = item.id;
						option.textContent = item.label;
						select.appendChild(option);
					});

					if (!res.data.length) {
						var empty = document.createElement('option');
						empty.disabled = true;
						empty.textContent = D.noResults || 'No results';
						select.appendChild(empty);
					}

					select.value = current;
				})
				.catch(function () {
					restore();
				});
		}
	}

	function scan() {
		var selects = document.querySelectorAll(
			'.bb-post-select,' +
			'[id^="customize-control-bb_hero_slot_"] select,' +
			'[id^="customize-control-bb_editor_picks_"] select'
		);

		Array.prototype.forEach.call(selects, attach);
	}

	function start() {
		scan();
		// Customizer sections are drawn after their panel opens.
		window.setTimeout(scan, 1200);
		window.setTimeout(scan, 3000);
	}

	if (document.readyState !== 'loading') {
		start();
	} else {
		document.addEventListener('DOMContentLoaded', start);
	}
})();
