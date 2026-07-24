(function ($) {
	'use strict';

	var $list = $('#flacso-hook-fields-list');
	if (!$list.length) return;

	function reindex() {
		$list.children('.flacso-hook-field').each(function (index) {
			$(this).find('[name]').each(function () {
				this.name = this.name.replace(/flacso_hook_fields\[[^\]]+\]/, 'flacso_hook_fields[' + index + ']');
			});
		});
	}

	$list.sortable({
		handle: '.flacso-hook-field-handle',
		axis: 'y',
		update: reindex
	});

	$('#flacso-hook-add-field').on('click', function () {
		var template = wp.template('flacso-hook-field');
		$list.append(template({ index: $list.children().length }));
	});

	$list.on('click', '.flacso-hook-remove-field', function () {
		$(this).closest('.flacso-hook-field').remove();
		reindex();
	});
})(jQuery);
