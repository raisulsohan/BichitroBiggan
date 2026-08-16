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
	});
})(jQuery);
