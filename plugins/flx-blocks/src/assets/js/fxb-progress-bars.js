/* global flxbk_progress_bars */
/* global ProgressBar */
(function () {
	'use strict';
	const flxbkProgressBars = {
		cache: {},
		scroller: {},
		items: JSON.parse(flxbk_progress_bars.items),
		windowResize() {
			if (!flxbkProgressBars?.items) {
				return;
			}
			Object.keys(flxbkProgressBars?.items).forEach(function (key) {
				flxbkProgressBars.updateContainer(flxbkProgressBars.items[key]);
			});
		},
		updateContainer(item) {
			const barContainers = document.querySelectorAll('.fxb-progress-bar-' + item.unique_id);
			const barSvgs = [];
			if (!barContainers?.length) {
				return;
			}
			for (let n = 0; n < barContainers.length; n++) {
				barSvgs[n] = barContainers[n].querySelector('svg');
				if (barSvgs?.[n]) {
					flxbkProgressBars.updatePathSize(barSvgs[n], item);
				}
			}
		},
		updatePathSize(barSvg, item) {
			const barPaths = barSvg.querySelectorAll('path');
			const path1 = barPaths[0];
			const path2 = barPaths[1];
			if (window.innerWidth < 768) {
				if (item.type === 'line') {
					barSvg.setAttribute('viewBox', '0 0 100 ' + item.stokeWidths[2]);
					path1.setAttribute('d', 'M 0,' + item.stokeWidths[2] / 2 + ' L 100,' + item.stokeWidths[2] / 2);
					path2.setAttribute('d', 'M 0,' + item.stokeWidths[2] / 2 + ' L 100,' + item.stokeWidths[2] / 2);
				}
				path1.setAttribute('stroke-width', item.stokeWidths[2]);
				path2.setAttribute('stroke-width', item.stokeWidths[2]);
			} else if (window.innerWidth < 1025) {
				if (item.type === 'line') {
					barSvg.setAttribute('viewBox', '0 0 100 ' + item.stokeWidths[1]);
					path1.setAttribute('d', 'M 0,' + item.stokeWidths[1] / 2 + ' L 100,' + item.stokeWidths[1] / 2);
					path2.setAttribute('d', 'M 0,' + item.stokeWidths[1] / 2 + ' L 100,' + item.stokeWidths[1] / 2);
				}
				path1.setAttribute('stroke-width', item.stokeWidths[1]);
				path2.setAttribute('stroke-width', item.stokeWidths[1]);
			} else {
				if (item.type === 'line') {
					barSvg.setAttribute('viewBox', '0 0 100 ' + item.stokeWidths[0]);
					path1.setAttribute('d', 'M 0,' + item.stokeWidths[0] / 2 + ' L 100,' + item.stokeWidths[0] / 2);
					path2.setAttribute('d', 'M 0,' + item.stokeWidths[0] / 2 + ' L 100,' + item.stokeWidths[0] / 2);
				}
				path1.setAttribute('stroke-width', item.stokeWidths[0]);
				path2.setAttribute('stroke-width', item.stokeWidths[0]);
			}
		},
		triggerAnimation(element, index, item) {
			if (flxbkProgressBars.cache[item.simple_id][index]) {
				const prefix = item?.prefix ? flxbkProgressBars.stripHtml(item.prefix) : '';
				const suffix = item?.suffix ? flxbkProgressBars.stripHtml(item.suffix) : '';
				flxbkProgressBars.cache[item.simple_id][index].animate(item.progress_real / item.progress_max, {
					duration: item.duration * 1000,
					step(state, bar) {
						let value = 0;
						const elementAbove = element.querySelector('.fxb-current-progress-above');
						const elementInside = element.querySelector('.fxb-current-progress-inside');
						const elementBelow = element.querySelector('.fxb-current-progress-below');

						//get current raw progress value
						if (item.is_relative) {
							value = bar.value() * 100;
						} else {
							value = bar.value() * item.progress_max;
						}

						//format the value according to decimal points indicated in settings
						if (item.decimal === 'one') {
							value = Math.round(value * 10) / 10;
							value = value.toFixed(1);
						} else if (item.decimal === 'two') {
							value = Math.round(value * 100) / 100;
							value = value.toFixed(2);
						} else {
							value = Math.round(value);
						}

						if (elementAbove) {
							elementAbove.textContent = prefix + value + suffix;
						} else if (elementInside) {
							elementInside.textContent = prefix + value + suffix;
						} else if (elementBelow) {
							elementBelow.textContent = prefix + value + suffix;
						}
					},
				});
			}
		},
		initSingleBarElement(element, index, item) {
			let initialStroke;
			if (window.innerWidth < 768) {
				initialStroke = item.stokeWidths?.[2] ? item.stokeWidths[2] : 2;
			} else if (window.innerWidth < 1025) {
				initialStroke = item.stokeWidths?.[1] ? item.stokeWidths[1] : 2;
			} else {
				initialStroke = item.stokeWidths?.[0] ? item.stokeWidths[0] : 2;
			}
			const progressElement = element.querySelector('.fxb-progress-bar');
			const args = {
				color: item.progressColor,
				trailColor: item.barBackground,
				duration: item.duration * 1000,
				easing: item.easing,
				strokeWidth: initialStroke,
			};
			if (!flxbkProgressBars.cache[item.simple_id]) {
				flxbkProgressBars.cache[item.simple_id] = [];
			}
			switch (item.type) {
				case 'circle':
					flxbkProgressBars.cache[item.simple_id][index] = new ProgressBar.Circle(progressElement, args);
					break;
				case 'line':
					flxbkProgressBars.cache[item.simple_id][index] = new ProgressBar.Line(progressElement, args);
					break;
				case 'semicircle':
					flxbkProgressBars.cache[item.simple_id][index] = new ProgressBar.SemiCircle(
						progressElement,
						args
					);
					break;
			}
			if (item.delay) {
				if (!flxbkProgressBars.scroller[item.simple_id]) {
					flxbkProgressBars.scroller[item.simple_id] = [];
				}
				flxbkProgressBars.scroller[item.simple_id][index] = new ScrollMagic.Controller();
				const desiredAnimation = new ScrollMagic.Scene({ triggerElement: element });
				desiredAnimation.triggerHook(0.88);
				desiredAnimation.addTo(flxbkProgressBars.scroller[item.simple_id][index]);
				desiredAnimation.on('start', function (e) {
					flxbkProgressBars.triggerAnimation(element, index, item);
				});
			} else {
				flxbkProgressBars.triggerAnimation(element, index, item);
			}
			// Remove the init class after initialization
			element.classList.remove('fxb-progress-bar-init');
		},
		initSingleBar(item) {
			// Only select containers that have the init class
			const barContainers = document.querySelectorAll(
				'.fxb-progress-bar-container' + item.unique_id + '.fxb-progress-bar-init'
			);
			if (!barContainers?.length) {
				return;
			}
			for (let n = 0; n < barContainers.length; n++) {
				flxbkProgressBars.initSingleBarElement(barContainers[n], n, item);
			}
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
		initAll() {
			if (!flxbkProgressBars?.items) {
				return;
			}
			Object.keys(flxbkProgressBars?.items).forEach(function (key) {
				flxbkProgressBars.initSingleBar(flxbkProgressBars.items[key]);
			});
		},
		// Initiate the menus when the DOM loads.
		init() {
			if (typeof ProgressBar !== 'undefined') {
				flxbkProgressBars.initAll();
			} else {
				var initLoadDelay = setInterval(function () {
					if (typeof ProgressBar !== 'undefined') {
						flxbkProgressBars.initAll();
						clearInterval(initLoadDelay);
					}
				}, 200);
			}
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', flxbkProgressBars.init);
	} else {
		// The DOM has already been loaded.
		flxbkProgressBars.init();
	}
	// Add event listener for fxb-query-loaded event
	document.addEventListener('fxb-query-loaded', flxbkProgressBars.init);
	window.addEventListener('resize', flxbkProgressBars.windowResize, false);
})();
