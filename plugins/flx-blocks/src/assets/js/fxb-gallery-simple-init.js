/* global SimpleLightbox */
/**
 * File lightbox-init.js.
 * Gets Lightbox working for Flux Block Editor Gallery
 */

(function () {
	'use strict';
	var flxBlocksLightbox = {
		simulateClick(elem) {
			// Create our event (with options)
			const evt = new MouseEvent('click', {
				bubbles: true,
				cancelable: true,
				view: window,
			});
			// If cancelled, don't dispatch our event
			const canceled = !elem.dispatchEvent(evt);
		},
		checkImage(element) {
			return /(png|jpg|jpeg|gif|tiff|bmp|webp)$/.test(
				element.getAttribute('href').toLowerCase().split('?')[0].split('#')[0]
			);
		},
		handleClones(element) {
			const foundClones = element.querySelectorAll('.slick-cloned a.fxb-gallery-item-link');
			const foundRegular = element.querySelectorAll('.slick-slide:not(.slick-cloned) a.fxb-gallery-item-link');
			for (let c = 0; c < foundClones.length; c++) {
				foundClones[c].addEventListener('click', function (event) {
					event.preventDefault();
					const the_href = foundClones[c].getAttribute('href');
					for (let b = 0; b < foundRegular.length; b++) {
						if (the_href === foundRegular[b].getAttribute('href')) {
							flxBlocksLightbox.simulateClick(foundRegular[b]);
							break;
						}
					}
				});
			}
		},
		findGalleries() {
			const foundGalleries = document.querySelectorAll('.fxb-gallery-magnific-init');
			if (!foundGalleries.length) {
				return;
			}
			if (foundGalleries) {
				const carousel = [];
				for (let i = 0; i < foundGalleries.length; i++) {
					carousel[i] = foundGalleries[i].querySelector('.fxt-blocks-carousel-init');
					if (carousel[i]) {
						setTimeout(function () {
							flxBlocksLightbox.handleClones(carousel[i]);
						}, 200);
					}
					const galleryClass = foundGalleries[i].classList;
					const showCaption = foundGalleries[i].getAttribute('data-lightbox-caption');
					const filter = foundGalleries[i].getAttribute('data-image-filter');
					let foundGalleryClass = false;
					for (let n = 0; n < galleryClass.length; n++) {
						if (galleryClass[n].indexOf('fxb-gallery-id') !== -1) {
							foundGalleryClass = galleryClass[n];
							break;
						}
					}
					if ('true' == showCaption) {
						const foundImages = foundGalleries[i].querySelectorAll('a.fxb-gallery-item-link');
						for (let x = 0; x < foundImages.length; x++) {
							const caption = foundImages[x].querySelector('.flx-blocks-gallery-item__caption');
							if (caption) {
								foundImages[x].setAttribute('data-caption', caption.innerText);
							}
						}
					}
					if (foundGalleryClass) {
						if (filter) {
							SimpleLightbox.defaults.elementClass = 'slg-fxt-blocks fxb-gal-light-filter-' + filter;
						} else {
							SimpleLightbox.defaults.elementClass = 'slg-fxt-blocks';
						}
						new SimpleLightbox({
							elements: '.' + foundGalleryClass + ' a.fxb-gallery-item-link',
						});
					}
				}
			}
		},
		/**
		 * Initiate the script to process all
		 */
		initAll() {
			SimpleLightbox.defaults.captionAttribute = 'data-caption';
			flxBlocksLightbox.findGalleries();
		},
		// Initiate the menus when the DOM loads.
		init() {
			if (typeof SimpleLightbox == 'function') {
				flxBlocksLightbox.initAll();
			} else {
				var initLoadDelay = setInterval(function () {
					if (typeof SimpleLightbox == 'function') {
						flxBlocksLightbox.initAll();
						clearInterval(initLoadDelay);
					}
				}, 200);
			}
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', flxBlocksLightbox.init);
	} else {
		// The DOM has already been loaded.
		flxBlocksLightbox.init();
	}
})();
