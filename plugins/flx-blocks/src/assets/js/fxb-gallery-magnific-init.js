jQuery(document).ready(function ($) {
	// Init Magnific
	$('.fxb-gallery-magnific-init').each(function () {
		const showCaption = $(this).attr('data-lightbox-caption');
		const filter = $(this).attr('data-image-filter');
		$(this)
			.find('li.flx-blocks-gallery-item a.fxb-gallery-item-link')
			.magnificPopup({
				type: 'image',
				mainClass: 'mfp-fxt-blocks fxb-gal-light-filter-' + filter,
				gallery: {
					enabled: true,
				},
				image: {
					titleSrc(item) {
						if ('true' == showCaption && item.el.find('.flx-blocks-gallery-item__caption').length) {
							return item.el.find('.flx-blocks-gallery-item__caption').html();
						}
						return '';
					},
				},
			});
		$(this)
			.find('.fxt-blocks-carousel .fxb-slide-item:not(.slick-cloned) a.fxb-gallery-item-link')
			.magnificPopup({
				type: 'image',
				mainClass: 'mfp-fxt-blocks fxb-gal-light-filter-' + filter,
				gallery: {
					enabled: true,
				},
				image: {
					titleSrc(item) {
						if ('true' == showCaption && item.el.find('.flx-blocks-gallery-item__caption').length) {
							return item.el.find('.flx-blocks-gallery-item__caption').html();
						}
						return '';
					},
				},
			});
	});
});
