/* Keep the selected tab visible when a Flux screen mounts, changes tab or changes width. */
(function (w, d) {
	'use strict';
	var screens = new Map();

	function watch(screen) {
		var frame = 0;
		function reveal() {
			frame = 0;
			screen.querySelectorAll('.flx-tabs').forEach(function (nav) {
				var active = nav.querySelector('[aria-current="page"]');
				if (!active || !nav.clientWidth) {
					return;
				}
				var bounds = nav.getBoundingClientRect();
				var tab = active.getBoundingClientRect();
				var delta = tab.left < bounds.left ? tab.left - bounds.left : Math.max(0, tab.right - bounds.right);
				if (Math.abs(delta) > 1) {
					nav.scrollBy(delta, 0);
				}
			});
		}
		function schedule() {
			if (!frame) {
				frame = w.requestAnimationFrame(reveal);
			}
		}
		var content = new MutationObserver(schedule);
		content.observe(screen, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-current'] });
		var size = w.ResizeObserver ? new ResizeObserver(schedule) : null;
		if (size) {
			size.observe(screen);
		} else {
			w.addEventListener('resize', schedule);
		}
		if (d.fonts) {
			d.fonts.ready.then(schedule);
		}
		schedule();
		return function () {
			content.disconnect();
			if (size) {
				size.disconnect();
			} else {
				w.removeEventListener('resize', schedule);
			}
			w.cancelAnimationFrame(frame);
		};
	}

	function discover() {
		screens.forEach(function (stop, screen) {
			if (!screen.isConnected) {
				stop();
				screens.delete(screen);
			}
		});
		d.querySelectorAll('.flx-screen').forEach(function (screen) {
			if (!screens.has(screen)) {
				screens.set(screen, watch(screen));
			}
		});
	}

	function start() {
		discover();
		// The navigator replaces the root, while React also creates roots and nested tabs asynchronously.
		new MutationObserver(function (changes) {
			if (changes.some(function (change) {
				return Array.prototype.some.call(change.addedNodes, function (node) {
					return node.nodeType === 1 && (node.matches('.flx-screen') || node.querySelector('.flx-screen'));
				}) || Array.prototype.some.call(change.removedNodes, function (node) {
					return node.nodeType === 1 && (node.matches('.flx-screen') || node.querySelector('.flx-screen'));
				});
			})) {
				discover();
			}
		}).observe(d.body, { childList: true, subtree: true });
	}
	if (d.readyState === 'loading') {
		d.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(window, document);
