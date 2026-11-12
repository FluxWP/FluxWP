/* global SimpleLightbox */
/**
 * File fxb-init-video-popup.js.
 * Gets video lighbox working for buttons.
 */

(function () {
	'use strict';
	var flxBlocksVideoLightbox = {
		/**
		 * Initiate the script to process all
		 */
		initAll(element) {
			new SimpleLightbox({
				elements: document.querySelectorAll('.ktblocksvideopop'),
				videoRegex: new RegExp(/youtube.com|youtu.be|youtube-nocookie.com|vimeo.com/),
			});
		},
		// Initiate the menus when the DOM loads.
		init() {
			if (typeof SimpleLightbox == 'function') {
				flxBlocksVideoLightbox.initAll();
			} else {
				var initLoadDelay = setInterval(function () {
					if (typeof SimpleLightbox == 'function') {
						flxBlocksVideoLightbox.initAll();
						clearInterval(initLoadDelay);
					}
				}, 200);
			}
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', flxBlocksVideoLightbox.init);
	} else {
		// The DOM has already been loaded.
		flxBlocksVideoLightbox.init();
	}
})();
