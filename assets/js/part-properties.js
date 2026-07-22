(function ($) {
	'use strict';

	function bindAttachmentField($field) {
		var frame;

		$field.on('click', '.wpep-attachment-select', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Select media',
				button: { text: 'Use media' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$field.find('.wpep-attachment-id').val(attachment.id);
				var link = $('<a/>')
					.attr('href', attachment.url)
					.attr('target', '_blank')
					.attr('rel', 'noopener noreferrer')
					.text(attachment.filename || attachment.title || ('#' + attachment.id));
				$field.find('.wpep-attachment-preview').empty().append(link);
			});

			frame.open();
		});

		$field.on('click', '.wpep-attachment-clear', function (event) {
			event.preventDefault();
			$field.find('.wpep-attachment-id').val('');
			$field.find('.wpep-attachment-preview').empty();
		});
	}

	$(function () {
		$('.wpep-attachment-field').each(function () {
			bindAttachmentField($(this));
		});
	});
})(jQuery);
