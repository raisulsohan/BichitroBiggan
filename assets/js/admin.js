/**
 * Admin helpers — currently just the profile photo picker.
 */
(function ($) {
	'use strict';

	$(function () {
		var frame;
		var $field = $('#bb_avatar_id');
		var $preview = $('#bb-avatar-preview');

		if (!$field.length) {
			return;
		}

		$('#bb-avatar-pick').on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'প্রোফাইল ছবি বেছে নিন',
				button: { text: 'এই ছবিটি ব্যবহার করুন' },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var url = (attachment.sizes && attachment.sizes.thumbnail)
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$field.val(attachment.id);
				$preview.html(
					$('<img>', {
						src: url,
						alt: '',
						css: { width: '96px', height: '96px', borderRadius: '50%', objectFit: 'cover' }
					})
				);
			});

			frame.open();
		});

		$('#bb-avatar-clear').on('click', function (e) {
			e.preventDefault();
			$field.val('');
			$preview.empty();
		});

		/* Hero slot category live filtering */
		var map = window.bbPostCatMap || {};

		function setupAdminSlotFilter(slotNum) {
			var $catSelect = $('#bb_hero_cat_' + slotNum);
			var $slotSelect = $('#bb_hero_slot_' + slotNum);

			if (!$catSelect.length || !$slotSelect.length) return;

			var originalOptions = $slotSelect.find('option').clone();

			function applyFilter(catId) {
				catId = parseInt(catId, 10) || 0;
				var currentVal = $slotSelect.val();

				$slotSelect.empty();

				originalOptions.each(function () {
					var $opt = $(this);
					var postId = parseInt($opt.val(), 10) || 0;

					if (postId === 0 || catId === 0) {
						$slotSelect.append($opt.clone());
						return;
					}

					var postCats = map[postId] || [];
					if (postCats.indexOf(catId) !== -1) {
						$slotSelect.append($opt.clone());
					}
				});

				if ($slotSelect.find('option[value="' + currentVal + '"]').length) {
					$slotSelect.val(currentVal);
				} else {
					$slotSelect.val(0);
				}
			}

			$catSelect.on('change', function () {
				applyFilter($(this).val());
			});

			applyFilter($catSelect.val());
		}

		for (var s = 1; s <= 4; s++) {
			setupAdminSlotFilter(s);
		}
	});
})(jQuery);
