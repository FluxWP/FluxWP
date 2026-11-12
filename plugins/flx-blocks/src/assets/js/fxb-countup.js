/* global CountUp */
/**
 * File fxb-countup.js.
 * Gets the countup running in viewport.
 */
(function () {
	'use strict';
	window.flxbkCountUp = {
		cache: {},
		countUpItems: {},
		listenerCache: {},
		sliderEventCache: {},
		isInViewport(el) {
			const rect = el.getBoundingClientRect();
			return (
				rect.top >= 0 &&
				rect.left >= -300 &&
				rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
				rect.right <= (window.innerWidth || document.documentElement.clientWidth) + 300
			);
		},
		isInActiveSlide(el) {
			// Check if element is inside a slider
			const slider = el.closest('.fxb-splide, .splide');
			if (!slider) {
				return true; // Not in a slider, so always consider it "active"
			}

			// Find the active slide
			const activeSlide = slider.querySelector('.splide__slide.is-active');
			if (!activeSlide) {
				return true; // No active slide found, default to true
			}

			// Check if the countup element is within the active slide
			return activeSlide.contains(el);
		},
		/**
		 * Decode a string that may contain markup into plain text.
		 *
		 * @param {string} html The string to convert.
		 *
		 * @return {string} The decoded plain text.
		 */
		stripHtml(html) {
			const wrappedHtml = `<pre>${html}</pre>`;
			const doc = new DOMParser().parseFromString(wrappedHtml, 'text/html');

			return doc.body.textContent || '';
		},
		/**
		 * Escape a plain-text string for use in markup.
		 *
		 * @param {string} text The plain text to escape.
		 *
		 *
		 * @return {string} The escaped text.
		 */
		escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#x27;',
			};
			return String(text).replace(/[&<>"']/g, (match) => map[match]);
		},
		initScrollSpy() {
			window.flxbkCountUp.countUpItems = document.querySelectorAll('.fxb-count-up');
			if (!window.flxbkCountUp.countUpItems.length) {
				return;
			}
			for (let n = 0; n < window.flxbkCountUp.countUpItems.length; n++) {
				const self = window.flxbkCountUp.countUpItems[n],
					start = self.dataset.start,
					end = self.dataset.end,
					prefix = window.flxbkCountUp.stripHtml(self.dataset.prefix),
					suffix = window.flxbkCountUp.stripHtml(self.dataset.suffix),
					duration = self.dataset.duration,
					separator = window.flxbkCountUp.stripHtml(self.dataset.separator),
					decimal = self.dataset.decimal ? self.dataset.decimal : false,
					decimalSpaces = self.dataset.decimalSpaces ? self.dataset.decimalSpaces : false,
					el = self.querySelector('.fxb-count-up-process');
				let theSeparator = separator === 'true' ? ',' : separator;
				theSeparator = theSeparator === 'false' ? '' : theSeparator;
				// Escape the text options before they are handed to CountUp.
				const KbCounterOptions = {
					startVal: start ? start : 0,
					duration: duration ? duration : 2,
					prefix: prefix ? window.flxbkCountUp.escapeHtml(prefix) : '',
					suffix: suffix ? window.flxbkCountUp.escapeHtml(suffix) : '',
					separator: window.flxbkCountUp.escapeHtml(theSeparator),
					decimal: decimal ? window.flxbkCountUp.escapeHtml(decimal) : decimal,
					decimalPlaces: decimalSpaces,
				};
				window.flxbkCountUp.cache[n] = new countUp.CountUp(el, end, KbCounterOptions);
				window.flxbkCountUp.accessabilityModifications(el, end, prefix, suffix);
				// Initialize listener
				window.flxbkCountUp.listenerCache[n] = window.flxbkCountUp.listener(n);
				document.addEventListener('scroll', window.flxbkCountUp.listenerCache[n], { passive: true });
				// Setup slider listeners if in a slider
				window.flxbkCountUp.setupSliderListeners(n);
				window.flxbkCountUp.startCountUp(n);
			}
		},
		accessabilityModifications(el, end, prefix, suffix) {
			const div = document.createElement('div');
			div.classList.add('screen-reader-text');
			div.textContent = prefix + end + suffix;
			el.before(div);
			el.setAttribute('aria-hidden', 'true');
		},
		setupSliderListeners(index) {
			const countUpItem = window.flxbkCountUp.countUpItems[index];
			const slider = countUpItem.closest('.fxb-splide, .splide');

			if (!slider) {
				return; // Not in a slider, no need for slider listeners
			}

			// Create event handlers
			const mountedHandler = (e) => {
				window.flxbkCountUp.startCountUp(index);
			};

			const movedHandler = (e) => {
				window.flxbkCountUp.startCountUp(index);
			};

			// Add event listeners
			slider.addEventListener('splideMounted', mountedHandler);
			slider.addEventListener('splide:moved', movedHandler);

			// Store references for cleanup
			window.flxbkCountUp.sliderEventCache[index] = [
				{ element: slider, event: 'splideMounted', handler: mountedHandler },
				{ element: slider, event: 'splide:moved', handler: movedHandler },
			];
		},
		/**
		 * Start Listener.
		 */
		listener(index) {
			return function curried_func(e) {
				window.flxbkCountUp.startCountUp(index);
			};
		},
		/**
		 * Start function.
		 */
		startCountUp(index) {
			const countUpItem = window.flxbkCountUp.countUpItems[index];
			if (window.flxbkCountUp.isInViewport(countUpItem) && window.flxbkCountUp.isInActiveSlide(countUpItem)) {
				if (!window.flxbkCountUp.cache[index].error) {
					const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

					if (prefersReducedMotion) {
						window.flxbkCountUp.cache[index].printValue(window.flxbkCountUp.cache[index].endVal);
					} else {
						window.flxbkCountUp.cache[index].start();
					}
				}
				document.removeEventListener('scroll', window.flxbkCountUp.listenerCache[index], false);
				// Also remove slider event listeners if they exist
				if (window.flxbkCountUp.sliderEventCache[index]) {
					window.flxbkCountUp.sliderEventCache[index].forEach((listener) => {
						listener.element.removeEventListener(listener.event, listener.handler);
					});
					delete window.flxbkCountUp.sliderEventCache[index];
				}
			}
		},
		// Initiate sticky when the DOM loads.
		init() {
			window.flxbkCountUp.initScrollSpy();
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', window.flxbkCountUp.init);
	} else {
		// The DOM has already been loaded.
		window.flxbkCountUp.init();
	}
})();
