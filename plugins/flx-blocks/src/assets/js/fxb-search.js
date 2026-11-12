/**
 * File fxb-search.js.
 */
(function () {
	'use strict';
	window.flxbkSearchBlock = {
		cache: {},
		initSearch() {
			window.flxbkSearchBlock.cache = document.querySelectorAll('.fxb-search-modal-container');
			if (!window.flxbkSearchBlock.cache.length) {
				return;
			}

			for (let n = 0; n < window.flxbkSearchBlock.cache.length; n++) {
				// Initialize listener (backward support)
				const rootElement = window.flxbkSearchBlock.cache[n];
				const openModalButton = rootElement.querySelector(
					'.wp-block-flx-advancedbtn > .wp-block-flx-singlebtn:nth-of-type(1)'
				);
				const modalDiv = rootElement.querySelector('.fxb-search-modal');
				const closeModalButton = rootElement.querySelector('.fxb-search-modal .fxb-search-close-btn');
				const searchInput = rootElement.querySelector('.fxb-search-input');
				const iconSubmitButton = rootElement.querySelector('.fxb-search-modal .fxb-search-icon-submit');

				// Initialize listener
				if (openModalButton) {
					const showModalButton = function (e) {
						e.preventDefault();
						modalDiv.classList.add('active');
						openModalButton.setAttribute('aria-hidden', 'true');
						closeModalButton.removeAttribute('aria-hidden');
						closeModalButton.setAttribute('aria-expanded', 'true');
						return false;
					};
					const hideModalButton = function (e) {
						e.preventDefault();
						modalDiv.classList.remove('active');
						openModalButton.removeAttribute('aria-hidden');
						closeModalButton.setAttribute('aria-hidden', 'true');
						closeModalButton.setAttribute('aria-expanded', 'false');
						return false;
					};
					openModalButton.addEventListener('click', showModalButton);
					closeModalButton.addEventListener('click', hideModalButton);
					closeModalButton.setAttribute('aria-hidden', 'true');

					// New event listener for clicking outside the input
					modalDiv.addEventListener('click', function (e) {
						if (e.target !== searchInput && !searchInput.contains(e.target)) {
							hideModalButton(e);
						}
					});

					// Prevent modal from closing when icon submit is clicked.
					if (iconSubmitButton) {
						iconSubmitButton.addEventListener('click', function (event) {
							event.stopPropagation();
						});
					}

					document.addEventListener('keydown', function (e) {
						if (e.key === 'Escape' && modalDiv.classList.contains('active')) {
							hideModalButton(e);
						}
					});
				}
			}
		},
		// Initiate sticky when the DOM loads.
		init() {
			window.flxbkSearchBlock.initSearch();
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', window.flxbkSearchBlock.init);
	} else {
		// The DOM has already been loaded.
		window.flxbkSearchBlock.init();
	}
	document.addEventListener('flxbkJSInitReload', function () {
		window.flxbkSearchBlock.init();
	});
	document.addEventListener('fxb-query-loaded', function () {
		window.flxbkSearchBlock.init();
	});
})();
