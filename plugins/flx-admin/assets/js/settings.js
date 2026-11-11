/* Flux Admin · ayarlar: logo seçici. */
/* global wp, jQuery, FLXA_SET */
jQuery(function ($) {
	var frame;
	$(document).on('click', '[data-flxa-logo-pick]', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({ title: FLXA_SET.pick, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				var url = (a.sizes && (a.sizes.thumbnail || a.sizes.medium) || a).url;
				$('[data-flxa-logo-id]').val(a.id);
				$('[data-flxa-logo-preview]').attr('src', url);
				$('[data-flxa-logo-clear]').prop('hidden', false);
			});
		}
		frame.open();
	});
	$(document).on('click', '[data-flxa-logo-clear]', function (e) {
		e.preventDefault();
		var $id = $('[data-flxa-logo-id]');
		$id.val('0');
		if ($id.data('fallback')) {
			$('[data-flxa-logo-preview]').attr('src', $id.data('fallback'));
		}
		$(this).prop('hidden', true);
	});
});
