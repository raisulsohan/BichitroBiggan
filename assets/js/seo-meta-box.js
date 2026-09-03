/**
 * SEO Meta Box — Admin JavaScript
 * Theme: BichitroBiggan
 *
 * Handles:
 *  - Google preview live updates (title, description, URL, featured image thumbnail)
 *  - Mobile / Desktop preview toggle
 *  - Character counters with progress bars (SEO title & description)
 *  - Variable tag insertion (%title%, %sep%, %sitename%)
 *  - Two-way editable slug sync with WordPress permalink fields
 *  - Live featured image detection from #postimagediv
 *  - Yoast-style SEO score & Real-time SEO analysis checklist (🟢 🟠 🔴)
 *  - Collapsible section panels
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {

		/* =================================================================
		   Element references
		   ================================================================= */

		var keyphraseInput  = document.getElementById('bb-focus-keyphrase');
		var titleInput      = document.getElementById('bb-seo-title');
		var descInput       = document.getElementById('bb-seo-description');
		var previewTitle    = document.querySelector('.bb-seo-preview-title');
		var previewDescEl   = document.querySelector('.bb-seo-preview-desc');
		var previewDateEl   = document.querySelector('.bb-seo-preview-date');
		var previewUrl      = document.querySelector('.bb-seo-preview-url');
		var previewImgWrap  = document.getElementById('bb-seo-preview-img-wrap');
		var previewImg      = document.getElementById('bb-seo-preview-img');
		var postTitleInput  = document.getElementById('title');             // WP post title input
		var slugInput       = document.getElementById('bb-seo-slug');          // Editable slug input

		// WordPress classic editor slug elements.
		var wpSlugEditable  = document.getElementById('editable-post-name-full')
		                   || document.getElementById('editable-post-name');
		var wpSlugHidden    = document.getElementById('post_name');

		var toggleMobile    = document.getElementById('bb-seo-toggle-mobile');
		var toggleDesktop   = document.getElementById('bb-seo-toggle-desktop');
		var previewCard     = document.querySelector('.bb-seo-preview-card');

		var titleCounter     = document.getElementById('bb-seo-title-counter');
		var descCounter      = document.getElementById('bb-seo-desc-counter');
		var titleProgressBar = document.getElementById('bb-seo-title-progress-bar');
		var descProgressBar  = document.getElementById('bb-seo-desc-progress-bar');

		var tagButtons       = document.querySelectorAll('.bb-seo-tag-btn');
		var sectionHeaders   = document.querySelectorAll('.bb-seo-section-header');

		// Score & Analysis elements
		var scoreIndicator   = document.getElementById('bb-seo-score-indicator');
		var scoreBadge       = document.getElementById('bb-seo-score-badge');
		var analysisStatus   = document.getElementById('bb-seo-analysis-status');
		var analysisList     = document.getElementById('bb-seo-analysis-list');

		/* =================================================================
		   Localized data from PHP
		   ================================================================= */

		var siteTitle        = (typeof bbSeo !== 'undefined' && bbSeo.siteTitle) ? bbSeo.siteTitle : '';
		var siteUrl          = (typeof bbSeo !== 'undefined' && bbSeo.siteUrl)   ? bbSeo.siteUrl   : '';
		var separator        = (typeof bbSeo !== 'undefined' && bbSeo.separator) ? bbSeo.separator : '—';
		var initialThumbUrl  = (typeof bbSeo !== 'undefined' && bbSeo.featuredImage) ? bbSeo.featuredImage : '';

		/* =================================================================
		   Helpers
		   ================================================================= */

		/**
		 * Get the current post title from WordPress's #title input.
		 */
		function getPostTitle() {
			return postTitleInput ? postTitleInput.value.trim() : '';
		}

		/**
		 * Resolve variable tags in a template string.
		 */
		function resolveVars(template) {
			return template
				.replace(/%title%/g,    getPostTitle())
				.replace(/%sep%/g,      separator)
				.replace(/%sitename%/g, siteTitle);
		}

		/**
		 * Clean a slug string into standard URL slug format.
		 */
		function sanitizeSlug(str) {
			if (!str) return '';
			return str
				.toString()
				.toLowerCase()
				.trim()
				.replace(/\s+/g, '-')           // Replace spaces with -
				.replace(/[^\w\u0980-\u09FF-]+/g, '') // Allow alphanumeric, Bengali Unicode & hyphens
				.replace(/--+/g, '-')           // Replace multiple - with single -
				.replace(/^-+/, '')             // Trim - from start
				.replace(/-+$/, '');            // Trim - from end
		}

		/**
		 * Get current slug from our slugInput, or WP core fields, or post title.
		 */
		function getActiveSlug() {
			if (slugInput && slugInput.value.trim()) {
				return sanitizeSlug(slugInput.value.trim());
			}
			if (wpSlugEditable) {
				var s = wpSlugEditable.textContent || wpSlugEditable.value || '';
				if (s.trim()) return sanitizeSlug(s.trim());
			}
			if (wpSlugHidden && wpSlugHidden.value) {
				return sanitizeSlug(wpSlugHidden.value.trim());
			}
			return '';
		}

		/* =================================================================
		   1. Google Preview — Title
		   ================================================================= */

		function updatePreviewTitle() {
			if (!previewTitle) return;

			var raw = titleInput ? titleInput.value.trim() : '';

			// Fallback: use post title + sep + site name.
			if (!raw) {
				raw = '%title% %sep% %sitename%';
			}

			var resolved = resolveVars(raw);

			// Truncate for display.
			if (resolved.length > 60) {
				resolved = resolved.substring(0, 57) + '...';
			}

			previewTitle.textContent = resolved;

			// Update counter.
			if (titleInput) {
				updateCounter(titleInput.value.length, 60, titleCounter, titleProgressBar, [0, 50, 60]);
			}

			updateSeoAnalysis();
		}

		/* =================================================================
		   2. Google Preview — Description
		   ================================================================= */

		function updatePreviewDesc() {
			if (!previewDescEl) return;

			var desc = descInput ? descInput.value.trim() : '';

			if (!desc) {
				// Show placeholder style.
				previewDescEl.style.fontStyle = 'italic';
				previewDescEl.style.color = '#9aa0a6';
				desc = 'এখানে আপনার মেটা ডেসক্রিপশন দেখাবে...';
			} else {
				previewDescEl.style.fontStyle = '';
				previewDescEl.style.color = '';
			}

			// Truncate.
			if (desc.length > 160) {
				desc = desc.substring(0, 157) + '...';
			}

			// Build display: "Date — desc"
			if (previewDateEl) {
				var nodes = previewDescEl.childNodes;
				for (var i = nodes.length - 1; i >= 0; i--) {
					if (nodes[i] !== previewDateEl) {
						previewDescEl.removeChild(nodes[i]);
					}
				}
				previewDescEl.appendChild(document.createTextNode(' — ' + desc));
			} else {
				previewDescEl.textContent = desc;
			}

			// Update counter.
			if (descInput) {
				updateCounter(descInput.value.length, 160, descCounter, descProgressBar, [0, 120, 160]);
			}

			updateSeoAnalysis();
		}

		/* =================================================================
		   3. Google Preview — URL & Slug Synchronization
		   ================================================================= */

		function updatePreviewUrl() {
			if (!previewUrl) return;

			var slug = getActiveSlug() || 'post-url-slug';
			var base = siteUrl.replace(/\/+$/, '');
			previewUrl.textContent = base + '/' + slug + '/';

			updateSeoAnalysis();
		}

		// When user types in our #bb-seo-slug field:
		if (slugInput) {
			slugInput.addEventListener('input', function () {
				var clean = slugInput.value;
				updatePreviewUrl();

				// Sync with WordPress hidden field so WP saves it too.
				if (wpSlugHidden) {
					wpSlugHidden.value = clean;
				}
				if (wpSlugEditable) {
					if (wpSlugEditable.tagName === 'INPUT') {
						wpSlugEditable.value = clean;
					} else {
						wpSlugEditable.textContent = clean;
					}
				}
			});
		}

		// When WordPress updates its permalink slug, sync back to #bb-seo-slug:
		function syncFromWpSlug() {
			if (document.activeElement === slugInput) {
				return; // Don't overwrite while user is currently typing in the SEO slug field
			}
			var currentWpSlug = '';
			if (wpSlugEditable) {
				currentWpSlug = wpSlugEditable.textContent || wpSlugEditable.value || '';
			} else if (wpSlugHidden) {
				currentWpSlug = wpSlugHidden.value || '';
			}
			if (currentWpSlug && slugInput) {
				slugInput.value = currentWpSlug.trim();
				updatePreviewUrl();
			}
		}

		if (wpSlugEditable) {
			var obs = new MutationObserver(function () {
				syncFromWpSlug();
			});
			obs.observe(wpSlugEditable, {
				characterData: true,
				childList: true,
				subtree: true,
				attributes: true
			});
		}
		if (wpSlugHidden) {
			wpSlugHidden.addEventListener('change', syncFromWpSlug);
		}

		/* =================================================================
		   4. Google Preview — Featured Image Live Thumbnail
		   ================================================================= */

		function updateFeaturedImagePreview(url) {
			if (!previewImgWrap || !previewImg) return;

			if (url) {
				previewImg.src = url;
				previewImgWrap.style.display = 'block';
			} else {
				previewImg.src = '';
				previewImgWrap.style.display = 'none';
			}

			updateSeoAnalysis();
		}

		function checkFeaturedImage() {
			// Look inside WP classic editor's featured image metabox (#postimagediv).
			var wpImg = document.querySelector('#postimagediv .inside img')
			         || document.querySelector('#set-post-thumbnail img')
			         || document.querySelector('#postimagediv img');

			if (wpImg && wpImg.src && !wpImg.src.includes('placeholder')) {
				updateFeaturedImagePreview(wpImg.src);
				return;
			}

			// Check if remove button was clicked or container is empty.
			var postImageDiv = document.getElementById('postimagediv');
			if (postImageDiv) {
				var removeLink = document.getElementById('remove-post-thumbnail');
				var hasImage = postImageDiv.querySelector('img');
				if (!hasImage || (removeLink && removeLink.style.display === 'none')) {
					updateFeaturedImagePreview('');
					return;
				}
			}

			// Fallback to initial server-rendered thumbnail URL.
			if (initialThumbUrl) {
				updateFeaturedImagePreview(initialThumbUrl);
			} else {
				updateFeaturedImagePreview('');
			}
		}

		// Observe WP featured image metabox for live changes.
		var postImageMetabox = document.getElementById('postimagediv');
		if (postImageMetabox) {
			var imgObserver = new MutationObserver(function () {
				checkFeaturedImage();
			});
			imgObserver.observe(postImageMetabox, {
				childList: true,
				subtree: true,
				attributes: true
			});
		}

		// Also check on window focus / click in case media frame closes.
		document.addEventListener('click', function (e) {
			if (e.target && (e.target.id === 'set-post-thumbnail' || e.target.id === 'remove-post-thumbnail' || e.target.closest('#postimagediv'))) {
				setTimeout(checkFeaturedImage, 600);
			}
		});

		/* =================================================================
		   5. Character Counter & Progress Bar
		   ================================================================= */

		/**
		 * Update a counter element and progress bar.
		 *
		 * @param {number}      len        Current character count.
		 * @param {number}      max        Recommended maximum.
		 * @param {HTMLElement}  counterEl  The counter text span.
		 * @param {HTMLElement}  barEl      The progress bar div.
		 * @param {number[]}    thresholds [min, okStart, badStart]
		 */
		function updateCounter(len, max, counterEl, barEl, thresholds) {
			if (!counterEl || !barEl) return;

			counterEl.textContent = len + ' / ' + max + ' অক্ষর';

			// Progress width (cap at 100%).
			var pct = Math.min((len / max) * 100, 100);
			barEl.style.width = pct + '%';

			// Determine status.
			var status;
			if (len <= thresholds[1]) {
				status = 'good';
			} else if (len <= thresholds[2]) {
				status = 'ok';
			} else {
				status = 'bad';
			}

			var classes = ['bb-seo-counter--good', 'bb-seo-counter--ok', 'bb-seo-counter--bad'];
			var target  = 'bb-seo-counter--' + status;

			classes.forEach(function (cls) {
				counterEl.classList.remove(cls);
				barEl.classList.remove(cls);
			});

			counterEl.classList.add(target);
			barEl.classList.add(target);
		}

		/* =================================================================
		   6. Variable Tag Insertion
		   ================================================================= */

		function insertTag(tag) {
			if (!titleInput) return;

			var start = titleInput.selectionStart || 0;
			var end   = titleInput.selectionEnd   || 0;
			var val   = titleInput.value;

			titleInput.value = val.substring(0, start) + tag + val.substring(end);

			// Restore cursor position after the inserted tag.
			var newPos = start + tag.length;
			titleInput.focus();
			titleInput.setSelectionRange(newPos, newPos);

			// Trigger update.
			titleInput.dispatchEvent(new Event('input', { bubbles: true }));
		}

		/* =================================================================
		   7. SEO Analysis & Traffic Light Score Calculation (Yoast-style)
		   ================================================================= */

		function updateSeoAnalysis() {
			if (!analysisList) return;

			var keyphrase = (keyphraseInput ? keyphraseInput.value.trim() : '').toLowerCase();
			var rawTitle  = titleInput ? titleInput.value.trim() : '';
			var titleText = (rawTitle ? resolveVars(rawTitle) : (getPostTitle() + ' — ' + siteTitle)).toLowerCase();
			var descText  = (descInput ? descInput.value.trim() : '').toLowerCase();
			var slugText  = (getActiveSlug() || '').toLowerCase();
			var hasImage  = (previewImgWrap && previewImgWrap.style.display !== 'none');

			var items = [];
			var goodCount = 0;
			var badCount  = 0;

			// 1. Focus Keyphrase Presence
			if (!keyphrase) {
				items.push({
					status: 'bad',
					title: 'ফোকাস কি-ফ্রেজ',
					text: 'পোস্টের জন্য কোনো ফোকাস কি-ফ্রেজ নির্ধারণ করা হয়নি।'
				});
				badCount += 2;
			} else {
				items.push({
					status: 'good',
					title: 'ফোকাস কি-ফ্রেজ',
					text: 'ফোকাস কি-ফ্রেজ নির্ধারণ করা হয়েছে।'
				});
				goodCount++;
			}

			// 2. Keyphrase in SEO Title
			if (keyphrase) {
				if (titleText.includes(keyphrase)) {
					items.push({
						status: 'good',
						title: 'এসইও টাইটেলে কি-ফ্রেজ',
						text: 'এসইও টাইটেলে ফোকাস কি-ফ্রেজটি সুন্দরভাবে পাওয়া গেছে।'
					});
					goodCount++;
				} else {
					items.push({
						status: 'bad',
						title: 'এসইও টাইটেলে কি-ফ্রেজ',
						text: 'এসইও টাইটেলে আপনার ফোকাস কি-ফ্রেজটি খুঁজে পাওয়া যায়নি।'
					});
					badCount++;
				}
			}

			// 3. SEO Title Length
			var titleLen = titleInput ? titleInput.value.length : getPostTitle().length;
			if (titleLen >= 25 && titleLen <= 60) {
				items.push({
					status: 'good',
					title: 'এসইও টাইটেলের দৈর্ঘ্য',
					text: 'টাইটেলের দৈর্ঘ্য উপযুক্ত (' + titleLen + ' / ৬০ অক্ষর)।'
				});
				goodCount++;
			} else if (titleLen > 0 && titleLen < 25) {
				items.push({
					status: 'ok',
					title: 'এসইও টাইটেলের দৈর্ঘ্য',
					text: 'টাইটেলটি কিছুটা ছোট (' + titleLen + ' অক্ষর)। আরেকটু বর্ণনামূলক করতে পারেন।'
				});
			} else if (titleLen > 60) {
				items.push({
					status: 'bad',
					title: 'এসইও টাইটেলের দৈর্ঘ্য',
					text: 'টাইটেলটি বেশি দীর্ঘ (' + titleLen + ' অক্ষর)। ৬০ অক্ষরের মধ্যে রাখা ভালো।'
				});
				badCount++;
			}

			// 4. Keyphrase in Meta Description
			if (keyphrase && descText) {
				if (descText.includes(keyphrase)) {
					items.push({
						status: 'good',
						title: 'মেটা ডেসক্রিপশনে কি-ফ্রেজ',
						text: 'মেটা ডেসক্রিপশনে ফোকাস কি-ফ্রেজটি বিদ্যমান।'
					});
					goodCount++;
				} else {
					items.push({
						status: 'ok',
						title: 'মেটা ডেসক্রিপশনে কি-ফ্রেজ',
						text: 'মেটা ডেসক্রিপশনে ফোকাস কি-ফ্রেজটি যুক্ত করলে সার্চে ক্লিক রেট বাড়ে।'
					});
				}
			}

			// 5. Meta Description Length
			var descLen = descInput ? descInput.value.length : 0;
			if (descLen >= 80 && descLen <= 160) {
				items.push({
					status: 'good',
					title: 'মেটা ডেসক্রিপশনের দৈর্ঘ্য',
					text: 'মেটা ডেসক্রিপশনের দৈর্ঘ্য নিখুঁত (' + descLen + ' / ১৬০ অক্ষর)।'
				});
				goodCount++;
			} else if (descLen > 0 && descLen < 80) {
				items.push({
					status: 'ok',
					title: 'মেটা ডেসক্রিপশনের দৈর্ঘ্য',
					text: 'মেটা ডেসক্রিপশনটি কিছুটা ছোট (' + descLen + ' অক্ষর)। ৮০-১৬০ অক্ষর সুপারিশকৃত।'
				});
			} else if (descLen > 160) {
				items.push({
					status: 'bad',
					title: 'মেটা ডেসক্রিপশনের দৈর্ঘ্য',
					text: 'মেটা ডেসক্রিপশনটি অতিরিক্ত বড় (' + descLen + ' অক্ষর)। ১৬০ অক্ষরের মধ্যে রাখুন।'
				});
				badCount++;
			} else {
				items.push({
					status: 'bad',
					title: 'মেটা ডেসক্রিপশন',
					text: 'কোনো মেটা ডেসক্রিপশন দেওয়া হয়নি। গুগল নিজের মতো অংশবিশেষ কেটে নেবে।'
				});
				badCount++;
			}

			// 6. Featured Image Status
			if (hasImage) {
				items.push({
					status: 'good',
					title: 'ফিচার্ড ইমেজ',
					text: 'পোস্টে ফিচার্ড ইমেজ রয়েছে, যা গুগলে থাম্বনেইল হিসেবে দেখাবে।'
				});
				goodCount++;
			} else {
				items.push({
					status: 'ok',
					title: 'ফিচার্ড ইমেজ',
					text: 'ফিচার্ড ইমেজ যুক্ত করলে সামাজিক যোগাযোগ মাধ্যম ও সার্চে প্রিভিউ সুন্দর দেখায়।'
				});
			}

			// Render Checklist HTML
			var html = '';
			items.forEach(function (it) {
				html += '<li class="bb-seo-analysis-item bb-seo-item--' + it.status + '">';
				html += '<span class="bb-seo-item-bullet"></span>';
				html += '<div class="bb-seo-item-text"><strong>' + it.title + ':</strong> ' + it.text + '</div>';
				html += '</li>';
			});
			analysisList.innerHTML = html;

			// Determine overall score: good, ok, or bad
			var overallStatus = 'bad';
			var badgeText     = 'অপর্যাপ্ত (Needs work)';

			if (goodCount >= 4 && badCount === 0) {
				overallStatus = 'good';
				badgeText     = 'চমৎকার (Good)';
			} else if (goodCount >= 2 && badCount <= 2) {
				overallStatus = 'ok';
				badgeText     = 'সাধারণ (OK)';
			} else {
				overallStatus = 'bad';
				badgeText     = 'অপর্যাপ্ত (Needs work)';
			}

			// Update Indicator & Badge
			if (scoreIndicator && scoreBadge) {
				scoreIndicator.className = 'bb-seo-traffic-dot bb-seo-traffic-dot--' + overallStatus;
				scoreBadge.className     = 'bb-seo-score-badge bb-seo-score-badge--' + overallStatus;
				scoreBadge.textContent   = badgeText;
			}

			if (analysisStatus) {
				analysisStatus.innerHTML = '<span>মোট স্কোর: <strong>' + badgeText + '</strong></span><span>' + goodCount + ' টি পয়েন্ট সফল</span>';
			}
		}

		/* =================================================================
		   8. Section Collapse / Expand
		   ================================================================= */

		function setupSectionToggles() {
			sectionHeaders.forEach(function (header) {
				header.addEventListener('click', function () {
					var section = header.closest('.bb-seo-section');
					if (section) {
						section.classList.toggle('bb-seo-section--collapsed');
					}
				});

				// Keyboard accessibility: Enter / Space.
				header.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						header.click();
					}
				});
			});
		}

		/* =================================================================
		   9. Mobile / Desktop Toggle
		   ================================================================= */

		function setupViewToggle() {
			if (!toggleMobile || !toggleDesktop || !previewCard) return;

			toggleMobile.addEventListener('click', function (e) {
				e.preventDefault();
				previewCard.classList.add('bb-seo-preview--mobile');
				previewCard.classList.remove('bb-seo-preview--desktop');
				toggleMobile.classList.add('bb-seo-toggle-btn--active');
				toggleDesktop.classList.remove('bb-seo-toggle-btn--active');
			});

			toggleDesktop.addEventListener('click', function (e) {
				e.preventDefault();
				previewCard.classList.add('bb-seo-preview--desktop');
				previewCard.classList.remove('bb-seo-preview--mobile');
				toggleDesktop.classList.add('bb-seo-toggle-btn--active');
				toggleMobile.classList.remove('bb-seo-toggle-btn--active');
			});
		}

		/* =================================================================
		   10. Event Bindings
		   ================================================================= */

		// Focus keyphrase input
		if (keyphraseInput) {
			keyphraseInput.addEventListener('input', updateSeoAnalysis);
		}

		// SEO title / post title → preview title.
		if (titleInput)     titleInput.addEventListener('input', updatePreviewTitle);
		if (postTitleInput) postTitleInput.addEventListener('input', updatePreviewTitle);

		// Meta description → preview description.
		if (descInput) descInput.addEventListener('input', updatePreviewDesc);

		// Variable tag buttons.
		tagButtons.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var tag = btn.getAttribute('data-tag');
				if (tag) insertTag(tag);
			});
		});

		// Setup toggles.
		setupViewToggle();
		setupSectionToggles();

		/* =================================================================
		   11. Initial render
		   ================================================================= */

		updatePreviewTitle();
		updatePreviewDesc();
		updatePreviewUrl();
		checkFeaturedImage();
		updateSeoAnalysis();
	});
})();
