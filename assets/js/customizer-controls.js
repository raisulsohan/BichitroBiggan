/**
 * Live controls logic for Customizer — Dynamic category filtering of Hero post slots.
 */
(function ($) {
	'use strict';

	wp.customize.bind('ready', function () {
		var map = window.bbPostCatMap || {};

		function setupSlotFilter(slotNum) {
			var catSetting = 'bb_hero_cat_' + slotNum;
			var slotSetting = 'bb_hero_slot_' + slotNum;

			var catControl = wp.customize.control(catSetting);
			var slotControl = wp.customize.control(slotSetting);

			if (!catControl || !slotControl) return;

			var $slotSelect = slotControl.container.find('select');
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
					if (wp.customize(slotSetting)) {
						wp.customize(slotSetting).set(0);
					}
				}
			}

			wp.customize(catSetting, function (value) {
				value.bind(function (newCatId) {
					applyFilter(newCatId);
				});
			});

			if (wp.customize(catSetting)) {
				applyFilter(wp.customize(catSetting).get());
			}
		}

		for (var i = 1; i <= 4; i++) {
			setupSlotFilter(i);
		}
	});
})(jQuery);
