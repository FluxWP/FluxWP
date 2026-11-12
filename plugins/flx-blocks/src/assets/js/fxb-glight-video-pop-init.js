/* global GLightbox */
// cspell:ignore Efects glight lighbox ligthbox plyr zoomable
/**
 * File fxb-glight-video-init.js.
 * Gets video lighbox working for video popup.
 */

(function () {
	'use strict';
	var flxBlocksProVideoLightbox = {
		cache: [],
		wrapper: [],
		trigger: [],
		// The effect each anchor asked for, kept here because the attribute is removed during normalizing.
		effects: new WeakMap(),
		// Marks an anchor as already processed.
		sanitizedKey: Symbol.for('flxBlocksProVideoLightboxSanitized'),
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
		/**
		 * Check that a URL is a web or relative URL with no quote, bracket, backtick or whitespace characters.
		 *
		 * @param {string} url The URL to check.
		 *
		 *
		 * @return {boolean} Whether the URL resolves to http or https.
		 */
		/**
		 * Find the privacy hash of an unlisted Vimeo URL (vimeo.com/123/abc or ?h=abc).
		 *
		 * @param {string} href The video URL.
		 * @return {Array|null} Match-like array whose last item is the hash, or null.
		 */
		vimeoHash(href) {
			let u;
			try {
				u = new URL(String(href), window.location.href);
			} catch (error) {
				return null;
			}
			if (!/(^|\.)vimeo\.com$/i.test(u.hostname)) {
				return null;
			}
			const query = u.searchParams.get('h');
			if (query && /^[\da-f]+$/i.test(query)) {
				return [query];
			}
			const parts = u.pathname.split('/').filter(Boolean);
			const at = parts.findIndex((p) => /^\d+$/.test(p));
			if (at > -1 && parts[at + 1] && /^[\da-f]+$/i.test(parts[at + 1])) {
				return [parts[at + 1]];
			}
			return null;
		},
		isWebUrl(url) {
			if (/["'<>`\s]/.test(String(url).trim())) {
				return false;
			}
			try {
				return ['http:', 'https:'].includes(new URL(url, window.location.href).protocol);
			} catch (error) {
				return false;
			}
		},
		/**
		 * Normalize an anchor's slide data before GLightbox reads it.
		 *
		 * Only the href and a text caption are kept, and the href is limited to web or relative URLs. The attributes
		 * this script reads itself are left alone.
		 *
		 * @param {HTMLElement} el The anchor GLightbox will be bound to.
		 *
		 *
		 * @return {void}
		 */
		sanitizeSlideData(el) {
			if (el[flxBlocksProVideoLightbox.sanitizedKey]) {
				return;
			}
			el[flxBlocksProVideoLightbox.sanitizedKey] = true;

			const rawTitle = el.dataset.title || el.getAttribute('title') || '';
			flxBlocksProVideoLightbox.effects.set(el, el.getAttribute('data-effect'));
			// The GLightbox data attributes.
			[
				'data-glightbox',
				'data-href',
				'data-sizes',
				'data-srcset',
				'data-type',
				'data-video-provider',
				'data-description',
				'data-alt',
				'data-desc-position',
				'data-effect',
				'data-width',
				'data-height',
				'data-content',
				'data-zoomable',
				'data-draggable',
			].forEach((name) => el.removeAttribute(name));
			el.setAttribute('data-title', flxBlocksProVideoLightbox.escapeHtml(rawTitle));

			// Limit the href to web URLs that contain no quote, bracket, backtick or whitespace characters.
			if (
				el.hasAttribute('href') &&
				(!['http:', 'https:'].includes(el.protocol) || /["'<>`\s]/.test(el.getAttribute('href').trim()))
			) {
				el.removeAttribute('href');
			}
		},
		/**
		 * Normalize every anchor a selector matches, so the lightbox only binds anchors that have been processed.
		 *
		 * @param {string} selector The selector the lightbox will bind.
		 *
		 *
		 * @return {string} The selector.
		 */
		sanitizeMatching(selector) {
			document.querySelectorAll(selector).forEach(flxBlocksProVideoLightbox.sanitizeSlideData);

			return selector;
		},
		/**
		 * Initiate the script to process all
		 */
		initAll() {
			const allowedEffects = ['zoom', 'zoomBack', 'fade', 'slide', 'slideBack', 'flip', 'none'];

			flxBlocksProVideoLightbox.cache = document.querySelectorAll('.flxbk-video-popup-link');
			// Sanitize every anchor first, because one GLightbox instance binds all anchors that share a popup class.
			flxBlocksProVideoLightbox.cache.forEach(flxBlocksProVideoLightbox.sanitizeSlideData);
			for (let i = 0; i < flxBlocksProVideoLightbox.cache.length; i++) {
				var effect = allowedEffects[0];
				var attrEffect = flxBlocksProVideoLightbox.effects.get(flxBlocksProVideoLightbox.cache[i]);
				if (allowedEffects.includes(attrEffect)) {
					effect = attrEffect;
				} else if ('fade-right' == attrEffect || 'fade-left' == attrEffect) {
					effect = 'fade';
				} else if ('slide-right' == attrEffect) {
					effect = 'slideBack';
				} else if ('slide-left' == attrEffect) {
					effect = 'slide';
				} else if ('zoom-out' == attrEffect) {
					effect = 'zoomBack';
				} else if ('3d-unfold' == attrEffect) {
					effect = 'flip';
				}

				const isLocalMedia =
					flxBlocksProVideoLightbox.cache[i].classList.contains('flxbk-video-type-local');
				const popupId = flxBlocksProVideoLightbox.cache[i].getAttribute('data-popup-id');
				const popupClassWithId = flxBlocksProVideoLightbox.cache[i].getAttribute('data-popup-class');
				const popupAuto =
					flxBlocksProVideoLightbox.cache[i].getAttribute('data-popup-auto') === 'true' ? true : false;
				const youtubeCookies =
					flxBlocksProVideoLightbox.cache[i].getAttribute('data-youtube-cookies') === 'true'
						? true
						: false;

				const device = flxBlocksProVideoLightbox.getActiveSize();
				const mediaRatio = flxBlocksProVideoLightbox.cache[i].getAttribute('data-media-ratio') ?? '16:9';
				const mediaRatioMobile =
					flxBlocksProVideoLightbox.cache[i].getAttribute('data-media-ratio-mobile') ?? '';
				const mediaRatioToUse = device === 'mobile' && mediaRatioMobile ? mediaRatioMobile : mediaRatio;

				if (isLocalMedia) {
					flxBlocksProVideoLightbox.wrapper[i] = document.getElementById(popupId);

					if (device === 'mobile') {
						const videoElement =
							flxBlocksProVideoLightbox.wrapper[i]?.querySelector('.flxbk-local-video-popup');
						const mobileSrc = videoElement?.getAttribute('data-src-mobile');
						if (videoElement && mobileSrc) {
							videoElement.setAttribute('src', mobileSrc);
						}
					}

					flxBlocksProVideoLightbox.cache[i].addEventListener('click', function (event) {
						event.preventDefault();
						flxBlocksProVideoLightbox.trigger[i] = GLightbox({
							elements: [
								{
									href: flxBlocksProVideoLightbox.wrapper[i]
										.querySelector('.flxbk-local-video-popup')
										.getAttribute('src'),
									type: 'video',
									source: 'local',
								},
							],
							touchNavigation: true,
							skin: 'flxbk-dark ' + popupClassWithId,
							loop: false,
							openEffect: effect,
							closeEffect: effect,
							autoplayVideos: popupAuto,
							preload: false,
							plyr: {
								css: flxbk_pro_video_pop.plyr_css,
								js: flxbk_pro_video_pop.plyr_js,
								config: {
								iconUrl: flxbk_pro_video_pop.plyr_svg,
									ratio: mediaRatioToUse,
									hideControls: true,
								},
							},
							cssEfects: {
								flip: { in: 'flipIn', out: 'flipOut' },
								zoomBack: { in: 'zoomBackIn', out: 'zoomBackOut' },
							},
						});
						flxBlocksProVideoLightbox.trigger[i].on('close', () => {
							document.querySelectorAll('video.flxbk-video-poster').forEach((video) => {
								video.play();
							});
						});
						flxBlocksProVideoLightbox.trigger[i].on('open', () => {
							document.querySelectorAll('video.flxbk-video-poster').forEach((video) => {
								video.pause();
							});
						});
						flxBlocksProVideoLightbox.trigger[i].open();
					});
				} else {
					if (device === 'mobile') {
						const mobileHref = flxBlocksProVideoLightbox.cache[i]?.getAttribute('data-href-mobile');
						if (mobileHref && /^https?:\/\/[^\s"'<>`]+$/i.test(mobileHref)) {
							// GLightbox reads data-href before the link's own href.
							flxBlocksProVideoLightbox.cache[i].setAttribute('data-href', mobileHref);
						}
					}

					const lightbox = GLightbox({
						selector: flxBlocksProVideoLightbox.sanitizeMatching(
							'.flxbk-video-popup-link[data-popup-class="' +
								(/^[\w-]+$/.test(popupClassWithId || '') ? popupClassWithId : '') +
								'"]'
						),
						touchNavigation: true,
						skin: 'flxbk-dark',
						loop: false,
						openEffect: effect,
						closeEffect: effect,
						autoplayVideos: popupAuto,
						preload: false,
						plyr: {
							css: flxbk_pro_video_pop.plyr_css,
							js: flxbk_pro_video_pop.plyr_js,
							config: {
								iconUrl: flxbk_pro_video_pop.plyr_svg,
								hideControls: true,
								ratio: mediaRatioToUse,
								youtube: {
									noCookie: youtubeCookies,
								},
								vimeo: {
									dnt: youtubeCookies,
								},
							},
						},
						cssEfects: {
							flip: { in: 'flipIn', out: 'flipOut' },
							zoomBack: { in: 'zoomBackIn', out: 'zoomBackOut' },
						},
					});
					lightbox.on('slide_before_load', (data) => {
						const ligthboxBody = document.getElementById('glightbox-body');
						if (popupClassWithId && ligthboxBody) {
							ligthboxBody.classList = '';
							ligthboxBody.classList.add('glightbox-container');
							ligthboxBody.classList.add('glightbox-flxbk-dark');
							ligthboxBody.classList.add(popupClassWithId);
						}
					});

					lightbox.on('slide_after_load', (data) => {
						// Check for hash in Vimeo URL (unlisted video)
						const found = flxBlocksProVideoLightbox.vimeoHash(data.slideConfig.href);
						if (found) {
							// If hash is found, append it to the iframe src
							const iframe = data.slideNode.querySelector('iframe');
							iframe.src = iframe.src + '&h=' + found[found.length - 1];
						}
					});
					lightbox.on('close', () => {
						document.querySelectorAll('video.flxbk-video-poster').forEach((video) => {
							video.play();
						});
					});
					lightbox.on('open', () => {
						document.querySelectorAll('video.flxbk-video-poster').forEach((video) => {
							video.pause();
						});
					});
				}
			}
		},
		// Initiate the menus when the DOM loads.
		init() {
			if (typeof GLightbox == 'function') {
				flxBlocksProVideoLightbox.initAll();
			} else {
				var initLoadDelay = setInterval(function () {
					if (typeof GLightbox == 'function') {
						flxBlocksProVideoLightbox.initAll();
						clearInterval(initLoadDelay);
					}
				}, 200);
			}
		},

		getActiveSize() {
			const mobileBreakpoint = 768;
			if (mobileBreakpoint < window.innerWidth) {
				return 'desktop';
			}
			return 'mobile';
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', flxBlocksProVideoLightbox.init);
		document.addEventListener('fxb-query-loaded', flxBlocksProVideoLightbox.init);
	} else {
		// The DOM has already been loaded.
		flxBlocksProVideoLightbox.init();
	}
})();
