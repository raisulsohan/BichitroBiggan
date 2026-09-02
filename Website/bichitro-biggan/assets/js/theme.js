/**
 * Bichitro Biggan — front-end behaviour.
 * Mirrors the interactions from the Figma Make prototype.
 */
(function () {
	'use strict';

	/* ---------------------------------------------------------------
	 * Remove tracking parameters (like ?fbclid) from URL instantly
	 * ------------------------------------------------------------ */
	if (window.history && window.history.replaceState) {
		var currentUrl = new URL(window.location.href);
		if (currentUrl.searchParams.has('fbclid')) {
			currentUrl.searchParams.delete('fbclid');
			window.history.replaceState({}, document.title, currentUrl.toString());
		}
	}

	var SCROLL_TRIGGER = 80;
	var D = window.BBData || {};

	function showToast(msg, icon) {
		var existing = document.getElementById('bb-toast');
		if (existing && existing.parentNode) {
			existing.parentNode.removeChild(existing);
		}
		var toast = document.createElement('div');
		toast.id = 'bb-toast';
		toast.className = 'bb-toast';
		toast.innerHTML = '<span>' + (icon || '🔖') + '</span> <span>' + msg + '</span>';
		document.body.appendChild(toast);
		setTimeout(function () { toast.classList.add('is-visible'); }, 10);
		setTimeout(function () {
			toast.classList.remove('is-visible');
			setTimeout(function () {
				if (toast.parentNode) toast.parentNode.removeChild(toast);
			}, 300);
		}, 3000);
	}

	document.addEventListener('DOMContentLoaded', function () {
		try {
			localStorage.removeItem('bb_theme');
			document.documentElement.removeAttribute('data-theme');
		} catch (e) {}
		initBookmarks();
		initQuoteShare();
		initStickyNav();
		initMobileMenu();
		initTicker();
		initBackToTop();
		initCopyEmail();
		initAccordion();
		initTocScroll();
		initArticleModal();
		initHeroVideoPlayer();
		initAjaxList();
		initSliders();
		initLiveSearch();
		initResumeBar();
		initDirectSingleProgress();
		initPopularFilter();
		initVideoModal();
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
				return;
			}

			var searchBtn = closestMatch(e.target, '[data-bb-toggle="search"]');
			if (searchBtn) {
				e.preventDefault();
				open('');
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
		var ticker = document.querySelector('.bb-ticker');
		var items = document.querySelectorAll('[data-bb-ticker-item]');
		if (items.length < 2) {
			return;
		}

		var index = 0;
		var autoTimer = null;
		var INTERVAL = 4500;

		function show(i) {
			index = (i + items.length) % items.length;
			Array.prototype.forEach.call(items, function (el, n) {
				if (n === index) {
					el.style.opacity = '0';
					el.style.display = '';
					window.requestAnimationFrame(function () {
						el.style.opacity = '1';
					});
				} else {
					el.style.display = 'none';
				}
			});
		}

		function startAuto() {
			stopAuto();
			autoTimer = window.setInterval(function () {
				show(index + 1);
			}, INTERVAL);
		}

		function stopAuto() {
			if (autoTimer) {
				window.clearInterval(autoTimer);
				autoTimer = null;
			}
		}

		var prev = document.querySelector('[data-bb-ticker="prev"]');
		var next = document.querySelector('[data-bb-ticker="next"]');

		if (prev) prev.addEventListener('click', function () { show(index - 1); startAuto(); });
		if (next) next.addEventListener('click', function () { show(index + 1); startAuto(); });

		if (ticker) {
			ticker.addEventListener('mouseenter', stopAuto);
			ticker.addEventListener('mouseleave', startAuto);
			ticker.addEventListener('touchstart', stopAuto, { passive: true });
			ticker.addEventListener('touchend', function () {
				window.setTimeout(startAuto, 2000);
			}, { passive: true });
		}

		show(0);
		startAuto();
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

		function triggerToast() {
			showToast(D.copiedText || 'ইমেইল কপি হয়েছে', '✓');
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

				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(email).then(function () {
						triggerToast();
					}).catch(function () {
						fallbackCopy(email);
						triggerToast();
					});
				} else {
					fallbackCopy(email);
					triggerToast();
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
			if (e.target.closest('.bb-hero__play-btn, .bb-hero__video-wrap, .bb-hero__video-close, [data-bb-video]')) {
				return;
			}

			var link = closestMatch(e.target, 'a[data-bb-article]');
			if (!link || !isPlainClick(e, link)) return;

			// Prevent rapid multi-clicks from firing multiple requests
			if (body.querySelector('.bb-spinner')) {
				e.preventDefault();
				return;
			}

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
					if (titleEl) {
						titleEl.textContent = last.title;
						titleEl.href = last.url;
						titleEl.setAttribute('data-bb-article', last.url);
					}
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

			if (container.classList.contains('is-loading')) {
				e.preventDefault();
				return;
			}

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


	/* ---------------------------------------------------------------
	 * Read Later / Bookmarks System
	 * ------------------------------------------------------------ */
	function initBookmarks() {
		var STORAGE_KEY = 'bb_bookmarks';

		function getList() {
			try {
				var data = localStorage.getItem(STORAGE_KEY);
				return data ? JSON.parse(data) : [];
			} catch (e) {
				return [];
			}
		}

		function saveList(list) {
			try {
				localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
			} catch (e) {}
			updateBadges(list.length);
		}

		function updateBadges(count) {
			Array.prototype.forEach.call(document.querySelectorAll('[data-bb-count="bookmarks"]'), function (badge) {
				badge.textContent = count;
				badge.style.display = count > 0 ? 'inline-flex' : 'none';
			});
			var drawerCount = document.getElementById('bb-drawer-count');
			if (drawerCount) {
				drawerCount.textContent = count;
			}
			var footer = document.getElementById('bb-bookmarks-footer');
			if (footer) {
				footer.style.display = count > 0 ? 'block' : 'none';
			}
		}

		function syncButtons() {
			var list = getList();
			var ids = list.map(function (item) { return String(item.id); });

			Array.prototype.forEach.call(document.querySelectorAll('[data-bb-bookmark]'), function (btn) {
				var id = btn.getAttribute('data-bb-bookmark');
				var textEl = btn.querySelector('.bb-bookmark-text');
				if (ids.indexOf(id) !== -1) {
					btn.classList.add('is-bookmarked');
					if (textEl) textEl.textContent = 'সংরক্ষিত';
				} else {
					btn.classList.remove('is-bookmarked');
					if (textEl) textEl.textContent = 'পরে পড়ুন';
				}
			});
		}

		function renderDrawer() {
			var container = document.getElementById('bb-bookmarks-list');
			if (!container) return;

			var list = getList();
			if (list.length === 0) {
				container.innerHTML = '<div class="bb-drawer__empty">' +
					'<span class="bb-drawer__empty-icon">🔖</span>' +
					'<p class="bb-drawer__empty-title">কোনো লেখা সংরক্ষিত নেই</p>' +
					'<p class="bb-drawer__empty-desc">যেকোনো লেখার "পরে পড়ুন" বাটনে ক্লিক করে এখানে জমা রাখুন।</p>' +
					'</div>';
				return;
			}

			var html = '<div class="bb-drawer__items">';
			list.forEach(function (item) {
				html += '<div class="bb-drawer__item" data-id="' + item.id + '">' +
					(item.thumb ? '<a href="' + item.url + '" class="bb-drawer__thumb"><img src="' + item.thumb + '" alt="" loading="lazy" /></a>' : '') +
					'<div class="bb-drawer__content">' +
						'<a href="' + item.url + '" class="bb-drawer__item-title">' + item.title + '</a>' +
						'<div class="bb-drawer__item-meta">' +
							(item.date ? '<span>' + item.date + '</span> · ' : '') +
							'<span>⏱ ' + item.time + '</span>' +
						'</div>' +
					'</div>' +
					'<button type="button" class="bb-drawer__remove" data-bb-remove-bookmark="' + item.id + '" title="মুছে ফেলুন" aria-label="মুছে ফেলুন">✕</button>' +
				'</div>';
			});
			html += '</div>';
			container.innerHTML = html;
		}

		function openDrawer() {
			renderDrawer();
			var drawer = document.getElementById('bb-bookmarks-drawer');
			var overlay = document.getElementById('bb-bookmarks-overlay');
			if (drawer) {
				drawer.classList.add('is-open');
				drawer.setAttribute('aria-hidden', 'false');
			}
			if (overlay) {
				overlay.classList.add('is-open');
				overlay.setAttribute('aria-hidden', 'false');
			}
			document.body.style.overflow = 'hidden';
		}

		function closeDrawer() {
			var drawer = document.getElementById('bb-bookmarks-drawer');
			var overlay = document.getElementById('bb-bookmarks-overlay');
			if (drawer) {
				drawer.classList.remove('is-open');
				drawer.setAttribute('aria-hidden', 'true');
			}
			if (overlay) {
				overlay.classList.remove('is-open');
				overlay.setAttribute('aria-hidden', 'true');
			}
			document.body.style.overflow = '';
		}

		// Initial sync
		updateBadges(getList().length);
		syncButtons();

		// Click events
		document.addEventListener('click', function (e) {
			// Toggle bookmark on single/card
			var bookBtn = closestMatch(e.target, '[data-bb-bookmark]');
			if (bookBtn) {
				e.preventDefault();
				var id = bookBtn.getAttribute('data-bb-bookmark');
				var list = getList();
				var index = list.findIndex(function (item) { return String(item.id) === String(id); });

				if (index > -1) {
					list.splice(index, 1);
					saveList(list);
					syncButtons();
					showToast('লেখাটি বুকমার্ক থেকে সরানো হয়েছে');
				} else {
					var item = {
						id: id,
						title: bookBtn.getAttribute('data-title') || document.title,
						url: bookBtn.getAttribute('data-url') || location.href,
						thumb: bookBtn.getAttribute('data-thumb') || '',
						time: bookBtn.getAttribute('data-time') || '',
						date: bookBtn.getAttribute('data-date') || ''
					};
					list.unshift(item);
					saveList(list);
					syncButtons();
					showToast('লেখাটি পরবর্তীতে পড়ার জন্য সংরক্ষিত হয়েছে');
				}
				return;
			}

			// Open drawer
			var openBtn = closestMatch(e.target, '[data-bb-toggle="bookmarks"]');
			if (openBtn) {
				e.preventDefault();
				openDrawer();
				return;
			}

			// Close drawer
			if (closestMatch(e.target, '#bb-bookmarks-close') || e.target === document.getElementById('bb-bookmarks-overlay')) {
				e.preventDefault();
				closeDrawer();
				return;
			}

			// Remove single item from drawer
			var removeBtn = closestMatch(e.target, '[data-bb-remove-bookmark]');
			if (removeBtn) {
				e.preventDefault();
				var remId = removeBtn.getAttribute('data-bb-remove-bookmark');
				var curList = getList();
				curList = curList.filter(function (it) { return String(it.id) !== String(remId); });
				saveList(curList);
				renderDrawer();
				syncButtons();
				return;
			}

			// Clear all from drawer
			if (closestMatch(e.target, '#bb-bookmarks-clear')) {
				e.preventDefault();
				saveList([]);
				renderDrawer();
				syncButtons();
				showToast('সকল সংরক্ষিত লেখা মুছে ফেলা হয়েছে');
			}
		});

		document.addEventListener('keydown', function (e) {
			if ((e.key === 'Escape' || e.key === 'Esc') && document.getElementById('bb-bookmarks-drawer') && document.getElementById('bb-bookmarks-drawer').classList.contains('is-open')) {
				closeDrawer();
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Quote Highlight, Copy Attribution & Facebook Share
	 * ------------------------------------------------------------ */
	function initQuoteShare() {
		var tooltip = document.createElement('div');
		tooltip.id = 'bb-quote-tooltip';
		tooltip.className = 'bb-quote-tooltip';
		tooltip.style.display = 'none';
		tooltip.innerHTML = '<button type="button" class="bb-quote-btn bb-quote-btn--copy" id="bb-quote-copy-btn">📋 কপি</button>' +
			'<button type="button" class="bb-quote-btn bb-quote-btn--fb" id="bb-quote-fb-btn">💬 ফেসবুকে শেয়ার</button>';
		document.body.appendChild(tooltip);

		var copyBtn = tooltip.querySelector('#bb-quote-copy-btn');
		var fbBtn = tooltip.querySelector('#bb-quote-fb-btn');
		var currentSelectedText = '';

		function getArticleDetails() {
			var single = document.querySelector('.bb-single') || document.querySelector('.bb-content') || document.querySelector('.bb-modal__dialog');
			var titleEl = (single ? single.querySelector('.bb-single__title') : null) || document.querySelector('.bb-single__title');
			var title = titleEl ? titleEl.textContent.trim() : document.title;
			var url = window.location.href;
			return { title: title, url: url };
		}

		function getAttributionText(text) {
			var details = getArticleDetails();
			return '“' + text + '”\n\n— ' + details.title + '\nসূত্র: বিচিত্র বিজ্ঞান (' + details.url + ')';
		}

		function showTooltip(x, y) {
			tooltip.style.top = y + 'px';
			tooltip.style.left = x + 'px';
			tooltip.style.display = 'inline-flex';
		}

		function hideTooltip() {
			tooltip.style.display = 'none';
			currentSelectedText = '';
		}

		function handleSelection() {
			var sel = window.getSelection();
			if (!sel || sel.isCollapsed || sel.rangeCount === 0) {
				hideTooltip();
				return;
			}

			var text = sel.toString().trim();
			if (text.length < 5) {
				hideTooltip();
				return;
			}

			// Ensure selection is inside article content
			var anchor = sel.anchorNode;
			if (!anchor) return;
			var parentEl = anchor.nodeType === 1 ? anchor : anchor.parentElement;
			var contentContainer = closestMatch(parentEl, '.bb-content, .bb-single__content, .bb-single, .bb-modal__dialog');
			if (!contentContainer) {
				hideTooltip();
				return;
			}

			currentSelectedText = text;
			var range = sel.getRangeAt(0);
			var rect = range.getBoundingClientRect();
			if (!rect || (rect.width === 0 && rect.height === 0)) {
				hideTooltip();
				return;
			}

			var tooltipWidth = 190;
			var tooltipX = rect.left + (rect.width / 2) - (tooltipWidth / 2) + window.pageXOffset;
			var tooltipY = rect.top + window.pageYOffset - 44;

			// Keep within viewport boundary
			if (tooltipX < 10) tooltipX = 10;

			showTooltip(tooltipX, tooltipY);
		}

		document.addEventListener('mouseup', function (e) {
			if (tooltip.contains(e.target)) return;
			setTimeout(handleSelection, 60);
		});

		document.addEventListener('touchend', function (e) {
			if (tooltip.contains(e.target)) return;
			setTimeout(handleSelection, 120);
		});

		document.addEventListener('mousedown', function (e) {
			if (!tooltip.contains(e.target)) {
				hideTooltip();
			}
		});

		if (copyBtn) {
			copyBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (!currentSelectedText) return;

				var fullText = getAttributionText(currentSelectedText);
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(fullText).then(function () {
						showToast('উক্তি ও বিচিত্র বিজ্ঞানের লিঙ্ক কপি হয়েছে!', '📋');
					});
				} else {
					var ta = document.createElement('textarea');
					ta.value = fullText;
					ta.style.position = 'fixed';
					ta.style.opacity = '0';
					document.body.appendChild(ta);
					ta.select();
					try {
						document.execCommand('copy');
						showToast('উক্তি ও বিচিত্র বিজ্ঞানের লিঙ্ক কপি হয়েছে!', '📋');
					} catch(err) {}
					document.body.removeChild(ta);
				}

				hideTooltip();
			});
		}

		if (fbBtn) {
			fbBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (!currentSelectedText) return;

				var details = getArticleDetails();
				var quoteText = '“' + currentSelectedText + '” — ' + details.title + ' (বিচিত্র বিজ্ঞান)';
				var fbUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(details.url) + '&quote=' + encodeURIComponent(quoteText);
				window.open(fbUrl, 'bb_fb_share', 'width=640,height=520,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
				hideTooltip();
			});
		}

		// Handle standard keyboard copy (Ctrl + C / Cmd + C) inside article content
		document.addEventListener('copy', function (e) {
			var sel = window.getSelection();
			if (!sel || sel.isCollapsed) return;
			var selectedText = sel.toString().trim();
			if (selectedText.length < 15) return;

			var anchor = sel.anchorNode;
			if (!anchor) return;
			var parentEl = anchor.nodeType === 1 ? anchor : anchor.parentElement;
			var contentContainer = closestMatch(parentEl, '.bb-content, .bb-single__content, .bb-single');
			if (contentContainer) {
				var fullText = getAttributionText(selectedText);
				if (e.clipboardData) {
					e.clipboardData.setData('text/plain', fullText);
					e.preventDefault();
					showToast('কপি করা লেখার সাথে বিচিত্র বিজ্ঞানের লিঙ্ক যুক্ত হয়েছে', '📋');
				}
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Direct Video Player on Hero Cards
	 *
	 * When clicking a video/podcast card with data-bb-video, embeds
	 * the video directly into the card with autoplay and close button.
	 * ------------------------------------------------------------ */
	function initHeroVideoPlayer() {
		document.addEventListener('click', function (e) {
			var playBtn = e.target.closest('.bb-hero__play-btn');
			var card = e.target.closest('[data-bb-video]');

			if (!card && !playBtn) return;
			if (!card && playBtn) card = playBtn.closest('[data-bb-video]');
			if (!card) return;

			// If click is on a close button
			if (e.target.closest('.bb-hero__video-close')) {
				e.preventDefault();
				e.stopPropagation();
				var wrap = card.querySelector('.bb-hero__video-wrap');
				if (wrap) wrap.remove();
				return;
			}

			// If click is on the play button OR the card with video
			if (playBtn || card.classList.contains('bb-has-video')) {
				var videoUrl = card.getAttribute('data-bb-video');
				if (!videoUrl) return;

				e.preventDefault();
				e.stopPropagation();

				// If already playing, do nothing
				if (card.querySelector('.bb-hero__video-wrap')) return;

				// Append autoplay param
				var sep = videoUrl.indexOf('?') === -1 ? '?' : '&';
				var embedSrc = videoUrl + sep + 'autoplay=1&rel=0&enablejsapi=1';

				var wrap = document.createElement('div');
				wrap.className = 'bb-hero__video-wrap';
				wrap.innerHTML = '<iframe class="bb-hero__video-iframe" src="' + embedSrc + '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' +
					'<button type="button" class="bb-hero__video-close" aria-label="ভিডিও বন্ধ করুন" title="ভিডিও বন্ধ করুন">✕</button>';

				card.appendChild(wrap);
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Popular Posts Time Range Filter
	 * ------------------------------------------------------------ */
	function initPopularFilter() {
		var select = document.getElementById('bb-popular-range');
		var list = document.getElementById('bb-popular-list');
		if (!select || !list) return;

		select.addEventListener('change', function () {
			var range = select.value || 'all';
			var count = select.getAttribute('data-count') || 3;
			list.classList.add('is-loading');

			var ajaxUrl = (window.bbLiveSearch && window.bbLiveSearch.ajaxUrl) || (window.bb_ajax && window.bb_ajax.url) || '/wp-admin/admin-ajax.php';
			var url = ajaxUrl + '?action=bb_get_popular_posts&range=' + encodeURIComponent(range) + '&count=' + encodeURIComponent(count);

			fetch(url)
				.then(function (res) { return res.json(); })
				.then(function (res) {
					if (res && res.success && res.data && res.data.html) {
						list.innerHTML = res.data.html;
					}
				})
				.catch(function (err) {
					console.error('Popular filter error:', err);
				})
				.finally(function () {
					list.classList.remove('is-loading');
				});
		});
	}

	/* ---------------------------------------------------------------
	 * Global Video Modal (For Podcast/Grid Play buttons)
	 * ------------------------------------------------------------ */
	function initVideoModal() {
		var modal = null;
		var modalInner = null;

		document.addEventListener('click', function(e) {
			var trigger = e.target.closest('[data-bb-video-popup]');
			if (!trigger) return;

			e.preventDefault();
			var videoUrl = trigger.getAttribute('data-bb-video-popup');
			if (!videoUrl) return;
			
			var ratio = trigger.getAttribute('data-bb-video-ratio') || '16/9';

			if (!modal) {
				modal = document.createElement('div');
				modal.className = 'bb-video-modal';
				modal.innerHTML = '<div class="bb-video-modal__inner">' +
					'<button class="bb-video-modal__close" aria-label="Close">×</button>' +
					'<div id="bb-video-modal-frame"></div>' +
				'</div>';
				document.body.appendChild(modal);

				modalInner = modal.querySelector('.bb-video-modal__inner');

				modal.addEventListener('click', function(ev) {
					if (ev.target === modal || ev.target.closest('.bb-video-modal__close')) {
						closeModal();
					}
				});
			}
			
			modalInner.style.aspectRatio = ratio;
			var parts = ratio.split('/');
			var ratioW = parseFloat(parts[0]) || 16;
			var ratioH = parseFloat(parts[1]) || 9;
			modalInner.style.maxWidth = 'min(1000px, calc(85vh * ' + (ratioW / ratioH) + '))';

			// Append autoplay param
			var sep = videoUrl.indexOf('?') === -1 ? '?' : '&';
			var embedSrc = videoUrl + sep + 'autoplay=1&rel=0';

			document.getElementById('bb-video-modal-frame').innerHTML = '<iframe src="' + embedSrc + '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
			
			modal.classList.add('is-active');
			document.body.classList.add('bb-noscroll');
		});

		function closeModal() {
			if (!modal) return;
			modal.classList.remove('is-active');
			document.body.classList.remove('bb-noscroll');
			document.getElementById('bb-video-modal-frame').innerHTML = '';
		}
	}

})();

