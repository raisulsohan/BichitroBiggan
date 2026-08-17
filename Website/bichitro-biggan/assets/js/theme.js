/**
 * Bichitro Biggan — front-end behaviour.
 * Mirrors the interactions from the Figma Make prototype.
 */
(function () {
	'use strict';

	var SCROLL_TRIGGER = 80;
	var D = window.BBData || {};

	document.addEventListener('DOMContentLoaded', function () {
		initStickyNav();
		initMobileMenu();
		initTicker();
		initBackToTop();
		initCopyEmail();
		initAccordion();
		initTocScroll();
		initArticleModal();
		initAjaxList();
		initSliders();
		initLiveSearch();
		initResumeBar();
		initDirectSingleProgress();
	});

	/* ---------------------------------------------------------------
	 * Live search
	 *
	 * The header search box opens an overlay and results arrive while
	 * you type. Submitting still goes to the normal results page, so
	 * the feature is additive.
	 * ------------------------------------------------------------ */
	function initLiveSearch() {
		var modal = document.getElementById('bb-search-modal');

		if (!modal || !D.liveSearch || !supportsAjax()) {
			return;
		}

		var input = modal.querySelector('.bb-search-modal__input');
		var results = modal.querySelector('.bb-search-modal__results');
		var foot = modal.querySelector('.bb-search-modal__foot');
		var clearBtn = modal.querySelector('[data-bb-search-clear]');

		var timer = null;
		var controller = null;
		var lastQuery = '';

		// Any search field on the page becomes a trigger.
		document.addEventListener('focusin', function (e) {
			var field = closestMatch(e.target, '.bb-searchform__input');
			if (!field) return;
			field.blur();
			open(field.value);
		});

		document.addEventListener('click', function (e) {
			var field = closestMatch(e.target, '.bb-searchform__input');
			if (field) {
				e.preventDefault();
				open(field.value);
			}
		});

		Array.prototype.forEach.call(modal.querySelectorAll('[data-bb-search-close]'), function (btn) {
			btn.addEventListener('click', close);
		});

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				input.focus();
				render(null, '');
				clearBtn.hidden = true;
				// Otherwise typing the same term again is treated as "no change"
				// and no results are fetched.
				lastQuery = '';
			});
		}

		modal.addEventListener('click', function (e) {
			if (e.target === modal) close();
		});

		document.addEventListener('keydown', function (e) {
			if ((e.key === 'Escape' || e.key === 'Esc') && !modal.hidden) {
				close();
			}
		});

		input.addEventListener('input', function () {
			var value = input.value.trim();
			if (clearBtn) clearBtn.hidden = value === '';

			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				search(value);
			}, 250);
		});

		// A result opens in the article popup when that is switched on.
		results.addEventListener('click', function () {
			window.setTimeout(close, 0);
		});

		function open(prefill) {
			modal.hidden = false;
			document.body.classList.add('bb-noscroll');

			if (prefill && !input.value) {
				input.value = prefill;
			}

			input.focus();

			if (clearBtn) clearBtn.hidden = input.value === '';

			if (input.value.trim()) {
				search(input.value.trim());
			} else {
				render(null, '');
			}
		}

		function close() {
			modal.hidden = true;
			document.body.classList.remove('bb-noscroll');
			// Reopening with the same term should search again, not sit blank.
			lastQuery = '';
		}

		function state(text) {
			results.innerHTML = '<p class="bb-search-modal__state">' + text + '</p>';
			foot.hidden = true;
		}

		function render(data, query) {
			if (data === null) {
				results.innerHTML = '';
				foot.hidden = true;
				return;
			}

			if (!data.results.length) {
				state(D.noResults || 'No results');
				return;
			}

			var html = '';
			for (var i = 0; i < data.results.length; i++) {
				var r = data.results[i];
				html += '<a class="bb-search-hit" href="' + r.url + '" data-bb-article="' + r.id + '">'
					+ '<span class="bb-search-hit__head">'
					+ '<span class="bb-search-hit__title"></span>'
					+ '<span class="bb-search-hit__date"></span>'
					+ '</span>'
					+ '<span class="bb-search-hit__excerpt">' + r.excerpt + '</span>'
					+ '</a>';
			}
			results.innerHTML = html;

			// Titles are set as text so nothing in them can be interpreted as markup.
			var hits = results.querySelectorAll('.bb-search-hit');
			for (var n = 0; n < hits.length; n++) {
				hits[n].querySelector('.bb-search-hit__title').textContent = data.results[n].title;
				hits[n].querySelector('.bb-search-hit__date').textContent = data.results[n].date;
			}

			foot.hidden = false;
			foot.textContent = data.total === 1
				? (D.resultOne || '1 result')
				: (D.resultMany || '%s results').replace('%s', data.total);
		}

		function search(query) {
			if (query.length < 2) {
				if (query.length === 0) {
					render(null, '');
				} else {
					state(D.hint || '');
				}
				lastQuery = '';
				return;
			}

			if (query === lastQuery) return;
			lastQuery = query;

			if (controller && controller.abort) {
				controller.abort();
			}
			controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;

			state('<span class="bb-spinner"></span> ' + (D.searching || 'Searching…'));

			fetch(D.searchUrl + '?q=' + encodeURIComponent(query), {
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined
			})
				.then(function (r) {
					if (!r.ok) throw new Error('HTTP ' + r.status);
					return r.json();
				})
				.then(function (data) {
					if (query !== lastQuery) return;
					render(data, query);
				})
				.catch(function (err) {
					if (err && err.name === 'AbortError') return;
					state(D.noResults || 'No results');
				});
		}
	}

	/* ---------------------------------------------------------------
	 * Category sliders
	 *
	 * Every slide is already in the page; the arrows just swap which one
	 * is visible, so there is no request and no flash of empty space.
	 * ------------------------------------------------------------ */
	function initSliders() {
		// The reset control is only useful once you have moved off slide one.
		function syncReset(root, index) {
			Array.prototype.forEach.call(root.querySelectorAll('[data-bb-slide="reset"]'), function (b) {
				b.disabled = index === 0;
			});
		}

		Array.prototype.forEach.call(document.querySelectorAll('[data-bb-slider]'), function (root) {
			syncReset(root, 0);
		});

		document.addEventListener('click', function (e) {
			var btn = closestMatch(e.target, '[data-bb-slide]');
			if (!btn || btn.disabled) return;

			var root = closestMatch(btn, '[data-bb-slider]');
			if (!root) return;

			var slides = root.querySelectorAll('.bb-slider__slide');
			if (slides.length < 2) return;

			var current = 0;
			Array.prototype.forEach.call(slides, function (s, i) {
				if (!s.hidden) current = i;
			});

			var action = btn.getAttribute('data-bb-slide');
			var next;

			if (action === 'reset') {
				next = 0;
			} else {
				var step = action === 'prev' ? -1 : 1;
				next = (current + step + slides.length) % slides.length;
			}

			if (next === current) return;

			slides[current].hidden = true;
			slides[next].hidden = false;

			// Restart the animation even when returning to a slide already seen.
			slides[next].classList.remove('is-in');
			void slides[next].offsetWidth;
			slides[next].classList.add('is-in');

			syncReset(root, next);
		});
	}

	/* ---------------------------------------------------------------
	 * Shared helpers
	 * ------------------------------------------------------------ */

	// A plain click — not a new-tab / new-window / download intent.
	function isPlainClick(e, link) {
		if (e.defaultPrevented) return false;
		if (e.button !== 0) return false;
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
		if (link.target && link.target !== '' && link.target !== '_self') return false;
		if (link.hasAttribute('download')) return false;
		return true;
	}

	function closestMatch(el, selector) {
		while (el && el !== document) {
			if (el.nodeType === 1 && el.matches && el.matches(selector)) return el;
			el = el.parentNode;
		}
		return null;
	}

	function fetchDoc(url) {
		return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.text();
			})
			.then(function (html) {
				return new DOMParser().parseFromString(html, 'text/html');
			});
	}

	function supportsAjax() {
		return !!(window.fetch && window.DOMParser && window.history && history.pushState);
	}

	/* ---------------------------------------------------------------
	 * Sticky navigation
	 * ------------------------------------------------------------ */
	function initStickyNav() {
		var nav = document.getElementById('bb-nav');
		if (!nav) {
			return;
		}

		var spacer = document.createElement('div');
		spacer.className = 'bb-nav-spacer';
		spacer.style.height = '0px';
		nav.parentNode.insertBefore(spacer, nav.nextSibling);

		var stuck = false;

		function update() {
			var shouldStick = window.pageYOffset > SCROLL_TRIGGER;

			if (shouldStick === stuck) {
				return;
			}
			stuck = shouldStick;

			if (stuck) {
				spacer.style.height = nav.offsetHeight + 'px';
				nav.classList.add('is-stuck');
			} else {
				nav.classList.remove('is-stuck');
				spacer.style.height = '0px';
				closeMenu();
			}
		}

		window.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', function () {
			if (stuck) {
				spacer.style.height = nav.offsetHeight + 'px';
			}
		});
		update();
	}

	/* ---------------------------------------------------------------
	 * Mobile menu
	 * ------------------------------------------------------------ */
	function getMenu() {
		return document.getElementById('bb-mobile-menu');
	}

	function closeMenu() {
		var menu = getMenu();
		if (!menu) {
			return;
		}
		menu.classList.remove('is-open');
		toggleButtons(false);
	}

	function toggleButtons(expanded) {
		var buttons = document.querySelectorAll('[data-bb-toggle="menu"]');
		Array.prototype.forEach.call(buttons, function (btn) {
			btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			btn.textContent = expanded ? '✕' : '☰';
		});
	}

	function initMobileMenu() {
		var buttons = document.querySelectorAll('[data-bb-toggle="menu"]');
		var menu = getMenu();

		if (!menu || !buttons.length) {
			return;
		}

		Array.prototype.forEach.call(buttons, function (btn) {
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				var isOpen = menu.classList.toggle('is-open');
				toggleButtons(isOpen);
			});
		});

		document.addEventListener('click', function (e) {
			if (!menu.classList.contains('is-open')) return;
			if (menu.contains(e.target)) return;
			closeMenu();
		});
	}

	/* ---------------------------------------------------------------
	 * Headline ticker
	 * ------------------------------------------------------------ */
	function initTicker() {
		var items = document.querySelectorAll('[data-bb-ticker-item]');
		if (items.length < 2) {
			return;
		}

		var index = 0;

		function show(i) {
			index = (i + items.length) % items.length;
			Array.prototype.forEach.call(items, function (el, n) {
				el.style.display = n === index ? '' : 'none';
			});
		}

		var prev = document.querySelector('[data-bb-ticker="prev"]');
		var next = document.querySelector('[data-bb-ticker="next"]');

		if (prev) prev.addEventListener('click', function () { show(index - 1); });
		if (next) next.addEventListener('click', function () { show(index + 1); });

		show(0);
	}

	/* ---------------------------------------------------------------
	 * Back to top (page)
	 * ------------------------------------------------------------ */
	function initBackToTop() {
		var btn = document.getElementById('bb-backtop');
		if (!btn) {
			return;
		}

		function update() {
			btn.classList.toggle('is-visible', window.pageYOffset > SCROLL_TRIGGER);
		}

		window.addEventListener('scroll', update, { passive: true });
		btn.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
		update();
	}

	/* ---------------------------------------------------------------
	 * Copy the contact email
	 * ------------------------------------------------------------ */
	function initCopyEmail() {
		var buttons = document.querySelectorAll('.bb-copy-email');
		var toast = document.getElementById('bb-toast');

		if (!buttons.length) {
			return;
		}

		var email = D.email || '';

		function showToast() {
			if (!toast) return;
			toast.classList.add('is-visible');
			window.setTimeout(function () {
				toast.classList.remove('is-visible');
			}, 2500);
		}

		function fallbackCopy(text) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand('copy');
			} catch (err) {
				/* nothing else to try */
			}
			document.body.removeChild(area);
		}

		Array.prototype.forEach.call(buttons, function (btn) {
			btn.addEventListener('click', function () {
				if (!email) return;

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(email).then(showToast, function () {
						fallbackCopy(email);
						showToast();
					});
				} else {
					fallbackCopy(email);
					showToast();
				}
			});
		});
	}

	/* ---------------------------------------------------------------
	 * Archive accordion (delegated, so it survives AJAX swaps)
	 * ------------------------------------------------------------ */
	function initAccordion() {
		document.addEventListener('click', function (e) {
			var head = closestMatch(e.target, '.bb-accordion__head');
			if (!head) return;

			var root = closestMatch(head, '[data-bb-accordion]');
			if (!root) return;

			var item = head.parentNode;
			var willOpen = !item.classList.contains('is-open');

			// One panel open at a time, like the prototype.
			Array.prototype.forEach.call(root.querySelectorAll('.bb-accordion__item'), function (other) {
				other.classList.remove('is-open');
				var caret = other.querySelector('.bb-accordion__caret');
				var otherHead = other.querySelector('.bb-accordion__head');
				if (caret) caret.textContent = '▼';
				if (otherHead) otherHead.setAttribute('aria-expanded', 'false');
			});

			if (willOpen) {
				item.classList.add('is-open');
				head.setAttribute('aria-expanded', 'true');
				var openCaret = item.querySelector('.bb-accordion__caret');
				if (openCaret) openCaret.textContent = '▲';
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Table of contents — delegated so it works inside the popup too
	 * ------------------------------------------------------------ */
	function initTocScroll() {
		document.addEventListener('click', function (e) {
			// Handle TOC toggle button
			var toggle = closestMatch(e.target, '.bb-toc-toggle');
			if (toggle) {
				var container = closestMatch(toggle, '.bb-toc-container');
				if (container) {
					var isOpen = container.classList.toggle('is-open');
					toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				}
				return;
			}

			// If clicked outside open TOC container, close it
			var openContainer = document.querySelector('.bb-toc-container.is-open');
			if (openContainer && !openContainer.contains(e.target)) {
				openContainer.classList.remove('is-open');
				var openToggle = openContainer.querySelector('.bb-toc-toggle');
				if (openToggle) openToggle.setAttribute('aria-expanded', 'false');
			}

			var link = closestMatch(e.target, '[data-bb-toc]');
			if (!link) return;

			var hash = link.getAttribute('href') || '';
			if (hash.charAt(0) !== '#') return;

			// Scope the lookup so a popup scrolls its own copy of the article.
			var scope = closestMatch(link, '.bb-modal__dialog') || document;
			var target = scope.querySelector(hash) || document.querySelector(hash);
			if (!target) return;

			e.preventDefault();

			// Auto close TOC when a section link is clicked
			if (openContainer) {
				openContainer.classList.remove('is-open');
				var openToggle = openContainer.querySelector('.bb-toc-toggle');
				if (openToggle) openToggle.setAttribute('aria-expanded', 'false');
			}

			var dialog = closestMatch(link, '.bb-modal__dialog') || document.querySelector('#bb-modal:not([hidden]) .bb-modal__dialog');
			if (dialog) {
				dialog.scrollTo({ top: target.offsetTop - 16, behavior: 'smooth' });
				return;
			}

			var nav = document.getElementById('bb-nav');
			var offset = nav && nav.classList.contains('is-stuck') ? nav.offsetHeight + 12 : 12;
			var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
			window.scrollTo({ top: top, behavior: 'smooth' });
		});
	}

	/* ---------------------------------------------------------------
	 * Article popup
	 *
	 * Clicking a card fetches the article's own page and drops its
	 * <article class="bb-single"> into the dialog, then pushes the real
	 * permalink into the address bar. Direct visits, search engines and
	 * shared links still get the full page — nothing is JS-only.
	 * ------------------------------------------------------------ */
	function initArticleModal() {
		var modal = document.getElementById('bb-modal');

		if (!modal || !D.modal || !supportsAjax()) {
			return;
		}

		var dialog = modal.querySelector('.bb-modal__dialog');
		var body = modal.querySelector('.bb-modal__body');
		var topBtn = modal.querySelector('.bb-modal__top');

		var open = false;
		var depth = 0;              // how many history entries we pushed
		var baseTitle = document.title;
		var lastFocus = null;
		var cache = {};

		var currentArticleUrl = '';
		var progressBar = modal.querySelector('.bb-reading-progress__bar');

		document.addEventListener('click', function (e) {
			var link = closestMatch(e.target, 'a[data-bb-article]');
			if (!link || !isPlainClick(e, link)) return;

			e.preventDefault();
			lastFocus = link;
			load(link.href);
		});

		modal.addEventListener('click', function (e) {
			// Backdrop click — the dialog itself stops here.
			if (e.target === modal) close();
		});

		Array.prototype.forEach.call(modal.querySelectorAll('[data-bb-modal-close]'), function (btn) {
			btn.addEventListener('click', close);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' || e.key === 'Esc') {
				if (open) close(); else closeMenu();
			}
		});

		var scrollTicking = false;
		var saveReadTimer = null;

		dialog.addEventListener('scroll', function () {
			if (topBtn) topBtn.hidden = dialog.scrollTop <= 300;

			if (!scrollTicking) {
				scrollTicking = true;
				window.requestAnimationFrame(function () {
					var maxScroll = dialog.scrollHeight - dialog.clientHeight;
					var progress = maxScroll > 0 ? (dialog.scrollTop / maxScroll) * 100 : 0;
					if (progressBar) {
						progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
					}
					scrollTicking = false;
				});
			}

			if (currentArticleUrl) {
				clearTimeout(saveReadTimer);
				saveReadTimer = setTimeout(function () {
					var maxScroll = dialog.scrollHeight - dialog.clientHeight;
					if (maxScroll <= 0) return;
					var percent = (dialog.scrollTop / maxScroll) * 100;
					if (percent > 3 && percent < 95) {
						try {
							var postTitle = (body.querySelector('.bb-single__title') || {}).textContent || document.title;
							var data = { url: currentArticleUrl, title: postTitle, top: dialog.scrollTop, percent: Math.round(percent), time: Date.now() };
							localStorage.setItem('bb_read_' + currentArticleUrl, JSON.stringify(data));
							localStorage.setItem('bb_last_read', JSON.stringify(data));
							window.dispatchEvent(new Event('bb_resume_update'));
						} catch(e) {}
					} else if (percent >= 95) {
						try {
							localStorage.removeItem('bb_read_' + currentArticleUrl);
							var last = JSON.parse(localStorage.getItem('bb_last_read') || 'null');
							if (last && last.url === currentArticleUrl) {
								localStorage.removeItem('bb_last_read');
								window.dispatchEvent(new Event('bb_resume_update'));
							}
						} catch(e) {}
					}
				}, 250);
			}
		}, { passive: true });

		if (topBtn) {
			topBtn.addEventListener('click', function () {
				dialog.scrollTo({ top: 0, behavior: 'smooth' });
			});
		}

		window.addEventListener('popstate', function () {
			if (open) hide();
			depth = 0;
		});

		function show() {
			modal.hidden = false;
			document.body.classList.add('bb-noscroll');
			open = true;
			window.dispatchEvent(new Event('bb_resume_update'));
		}

		function hide() {
			modal.hidden = true;
			document.body.classList.remove('bb-noscroll');
			document.title = baseTitle;
			open = false;
			if (topBtn) topBtn.hidden = true;
			if (progressBar) progressBar.style.width = '0%';
			if (lastFocus && document.contains(lastFocus)) {
				lastFocus.focus({ preventScroll: true });
			}
			window.dispatchEvent(new Event('bb_resume_update'));
		}

		function close() {
			if (!open) return;
			hide();
			if (depth > 0) {
				var d = depth;
				depth = 0;
				history.go(-d);
			}
		}

		function render(html, title, url) {
			body.innerHTML = html;
			currentArticleUrl = url;
			dialog.scrollTop = 0;
			if (progressBar) progressBar.style.width = '0%';
			if (topBtn) topBtn.hidden = true;

			history.pushState({ bbArticle: url }, '', url);
			depth++;

			if (title) document.title = title;

			// Auto resume scroll position if previously read
			try {
				var saved = JSON.parse(localStorage.getItem('bb_read_' + url) || 'null');
				if (saved && saved.top && saved.top > 50) {
					window.setTimeout(function () {
						dialog.scrollTo({ top: saved.top, behavior: 'smooth' });
					}, 150);
				}
			} catch(e) {}

			var heading = body.querySelector('.bb-single__title');
			if (heading) {
				heading.setAttribute('tabindex', '-1');
				heading.focus({ preventScroll: true });
			}
		}

		function load(url) {
			if (cache[url]) {
				show();
				render(cache[url].html, cache[url].title, url);
				return;
			}

			body.innerHTML = '<div class="bb-modal__state"><span class="bb-spinner"></span> ' +
				(D.loading || 'Loading…') + '</div>';
			show();

			fetchDoc(url).then(function (doc) {
				var article = doc.querySelector('.bb-single');
				if (!article) throw new Error('no article');

				var titleEl = doc.querySelector('title');
				var title = titleEl ? titleEl.textContent : '';

				cache[url] = { html: article.outerHTML, title: title };
				render(cache[url].html, title, url);
			}).catch(function () {
				// Anything unexpected: fall back to a normal page load.
				hide();
				window.location.href = url;
			});
		}
	}

	/* ---------------------------------------------------------------
	 * Continue Reading (Homepage Reminder)
	 * ------------------------------------------------------------ */
	function initResumeBar() {
		var bar = document.getElementById('bb-resume-bar');
		if (!bar) return;

		var btn = document.getElementById('bb-resume-btn');
		var closeBtn = document.getElementById('bb-resume-close');
		var titleEl = bar.querySelector('.bb-resume-bar__title');

		function update() {
			var modal = document.getElementById('bb-modal');
			if (modal && !modal.hidden) {
				bar.hidden = true;
				document.body.classList.remove('bb-has-resume-bar');
				return;
			}

			try {
				var last = JSON.parse(localStorage.getItem('bb_last_read') || 'null');
				if (last && last.url && last.title && (Date.now() - last.time < 7 * 86400000)) {
					if (titleEl) titleEl.textContent = last.title;
					if (btn) {
						btn.href = last.url;
						btn.setAttribute('data-bb-article', last.url);
					}
					bar.hidden = false;
					document.body.classList.add('bb-has-resume-bar');
				} else {
					bar.hidden = true;
					document.body.classList.remove('bb-has-resume-bar');
				}
			} catch(e) {
				bar.hidden = true;
				document.body.classList.remove('bb-has-resume-bar');
			}
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				bar.hidden = true;
				document.body.classList.remove('bb-has-resume-bar');
				try {
					localStorage.removeItem('bb_last_read');
				} catch(e) {}
			});
		}

		window.addEventListener('bb_resume_update', update);
		window.addEventListener('storage', update);
		update();
	}

	/* ---------------------------------------------------------------
	 * Direct single post reading tracker
	 * ------------------------------------------------------------ */
	function initDirectSingleProgress() {
		var single = document.querySelector('.bb-single');
		var modal = document.getElementById('bb-modal');
		if (!single || (modal && !modal.hidden)) return;

		var url = window.location.href;
		try {
			var saved = JSON.parse(localStorage.getItem('bb_read_' + url) || 'null');
			if (saved && saved.top && saved.top > 50) {
				window.setTimeout(function () {
					window.scrollTo({ top: saved.top, behavior: 'smooth' });
				}, 150);
			}
		} catch(e) {}

		var singleSaveTimer = null;
		var cachedSingleTitle = (document.querySelector('.bb-single__title') || {}).textContent || document.title;

		window.addEventListener('scroll', function () {
			clearTimeout(singleSaveTimer);
			singleSaveTimer = setTimeout(function () {
				var max = document.documentElement.scrollHeight - window.innerHeight;
				if (max <= 0) return;
				var percent = (window.pageYOffset / max) * 100;
				if (percent > 3 && percent < 95) {
					try {
						var data = { url: url, title: cachedSingleTitle, top: window.pageYOffset, percent: Math.round(percent), time: Date.now() };
						localStorage.setItem('bb_read_' + url, JSON.stringify(data));
						localStorage.setItem('bb_last_read', JSON.stringify(data));
					} catch(e) {}
				} else if (percent >= 95) {
					try {
						localStorage.removeItem('bb_read_' + url);
						var last = JSON.parse(localStorage.getItem('bb_last_read') || 'null');
						if (last && last.url === url) localStorage.removeItem('bb_last_read');
					} catch(e) {}
				}
			}, 250);
		}, { passive: true });
	}

	/* ---------------------------------------------------------------
	 * Pagination without a full page load
	 * ------------------------------------------------------------ */
	function initAjaxList() {
		if (!D.ajaxList || !supportsAjax()) {
			return;
		}

		var containers = document.querySelectorAll('[data-bb-list]');
		if (!containers.length) {
			return;
		}

		// Remember which URL each container currently shows, and make sure the
		// first history entry carries state — otherwise going back to page 1
		// leaves the later page's cards on screen.
		Array.prototype.forEach.call(containers, function (c) {
			c.setAttribute('data-bb-list-url', location.href);
		});
		if (!history.state) {
			history.replaceState({ bbList: containers[0].getAttribute('data-bb-list') }, '', location.href);
		}

		document.addEventListener('click', function (e) {
			var link = closestMatch(e.target, '.bb-pagination a');
			if (!link || !isPlainClick(e, link)) return;

			var container = closestMatch(link, '[data-bb-list]');
			if (!container) return;

			e.preventDefault();
			swap(container, link.href, true);
		});

		window.addEventListener('popstate', function () {
			// Re-sync any list whose contents no longer match the address bar.
			Array.prototype.forEach.call(document.querySelectorAll('[data-bb-list]'), function (c) {
				if (c.getAttribute('data-bb-list-url') !== location.href) {
					swap(c, location.href, false);
				}
			});
		});

		function swap(container, url, push) {
			var key = container.getAttribute('data-bb-list');
			container.classList.add('is-loading');

			fetchDoc(url).then(function (doc) {
				var next = doc.querySelector('[data-bb-list="' + key + '"]');
				if (!next) throw new Error('no list');

				container.innerHTML = next.innerHTML;
				container.setAttribute('data-bb-list-url', url);

				if (push) {
					history.pushState({ bbList: key }, '', url);
				}

				var titleEl = doc.querySelector('title');
				if (titleEl) document.title = titleEl.textContent;

				var nav = document.getElementById('bb-nav');
				var offset = (nav && nav.classList.contains('is-stuck') ? nav.offsetHeight : 0) + 20;
				var top = container.getBoundingClientRect().top + window.pageYOffset - offset;
				window.scrollTo({ top: top, behavior: 'smooth' });
			}).catch(function () {
				// A click deserves a real navigation; a history event does not —
				// the address bar may simply be pointing at an article page.
				if (push) window.location.href = url;
			}).then(function () {
				container.classList.remove('is-loading');
			});
		}
	}
})();
