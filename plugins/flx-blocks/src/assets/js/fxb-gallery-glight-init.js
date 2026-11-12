// cSpell:ignore galllery glightbox lightboxes
/* global GLightbox */
/**
 * File lightbox-init.js.
 * Gets Lightbox working for Flux Block Editor Gallery
 */

(function () {
	'use strict';

	var flxBlocksGLight = {
		carouselCache: {},
		carouselItem: {},
		lightboxes: {},
		foundClasses: {},
		// The anchors the gallery block renders.
		linkSelector: '.flx-blocks-gallery-item a.fxb-gallery-item-link',
		// Marks an anchor as already processed.
		sanitizedKey: Symbol.for('flxBlocksGLightSanitized'),
		// The data attributes kept on a gallery anchor.
		allowedDataAttributes: ['data-title', 'data-description', 'data-alt'],
		// The caption tags and attributes the gallery allows (matches the gallery's server-side filter).
		allowedCaptionTags: '<a><br><b><i><u><p><ol><ul><li><strong><small>',
		allowedCaptionAttributes: { a: ['href', 'target', 'rel'] },
		simulateClick(elem) {
			// Create our event (with options)
			const evt = new MouseEvent('click', {
				bubbles: true,
				cancelable: true,
				view: window,
			});
			// If cancelled, don't dispatch our event
			!elem.dispatchEvent(evt);
		},
		// checkImage: function( element ) {
		// 	return /(png|jpg|jpeg|gif|tiff|bmp|webp)$/.test(
		// 		element.getAttribute( 'href' ).toLowerCase().split( '?' )[0].split( '#' )[0]
		// 	);
		// },
		handleClones(element) {
			const foundClones = element.querySelectorAll(
				'.fxb-slide-item.splide__slide--clone ' + flxBlocksGLight.linkSelector
			);
			const foundRegular = element.querySelectorAll(
				'.fxb-slide-item:not(.splide__slide--clone) ' + flxBlocksGLight.linkSelector
			);
			for (let c = 0; c < foundClones.length; c++) {
				foundClones[c].addEventListener('click', function (event) {
					event.preventDefault();
					const the_href = foundClones[c].getAttribute('href');
					for (let b = 0; b < foundRegular.length; b++) {
						if (the_href === foundRegular[b].getAttribute('href')) {
							flxBlocksGLight.simulateClick(foundRegular[b]);
							break;
						}
					}
				});
			}
		},
		strip_tags(input, allowed) {
			// Rebuilds the markup from a token list: text is escaped, only allowed tags are kept and
			// only their allowed attributes (with safe URLs) are written back.
			const allowedTags = (allowed || '').toLowerCase().match(/[a-z][a-z0-9]*/g) || [];
			const allowedAttributes = { a: ['href', 'target', 'rel'] };
			const entities = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
			const decode = (s) =>
				String(s).replace(/&(#x[0-9a-f]{1,6}|#[0-9]{1,7}|[a-z]{2,6});/gi, (m, e) => {
					const k = e.toLowerCase();
					if ('#x' === k.slice(0, 2)) {
						return String.fromCodePoint(parseInt(k.slice(2), 16) || 32);
					}
					if ('#' === k[0]) {
						return String.fromCodePoint(parseInt(k.slice(1), 10) || 32);
					}
					return Object.prototype.hasOwnProperty.call(entities, k) ? entities[k] : m;
				});
			const esc = (s) =>
				String(s)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#039;');
			const isSafeUrl = (url) => {
				try {
					return ['http:', 'https:', 'mailto:', 'tel:'].includes(new URL(url, window.location.href).protocol);
				} catch (error) {
					return false;
				}
			};
			const text = String(input || '');
			const tagRe = /<(\/?)([a-z][a-z0-9]*)\b([^<>]*)>/gi;
			let out = '';
			let last = 0;
			let m;
			while ((m = tagRe.exec(text)) !== null) {
				out += esc(decode(text.slice(last, m.index)));
				last = tagRe.lastIndex;
				const name = m[2].toLowerCase();
				const tag = allowedTags.find((t) => t === name);
				if (!tag) {
					continue;
				}
				if (m[1]) {
					out += '</' + tag + '>';
					continue;
				}
				const keep = allowedAttributes[tag] || [];
				const attrRe = /([a-z-]+)\s*=\s*(?:"([^"]*)"|'([^']*)')/gi;
				let attrs = '';
				let a;
				while ((a = attrRe.exec(m[3])) !== null) {
					const attr = keep.find((k) => k === a[1].toLowerCase());
					const value = decode(undefined !== a[2] ? a[2] : a[3]);
					if (!attr || ('href' === attr && !isSafeUrl(value))) {
						continue;
					}
					attrs += ' ' + attr + '="' + esc(value) + '"';
				}
				out += '<' + tag + attrs + '>';
			}
			return out + esc(decode(text.slice(last)));
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
		/**
		 * Reduce a caption to the markup the gallery allows.
		 *
		 * @param {string} raw The caption, as markup.
		 *
		 *
		 * @return {string} The safe caption.
		 */
		sanitizeDescription(raw) {
			const description = flxBlocksGLight.strip_tags(raw, flxBlocksGLight.allowedCaptionTags);

			// Wrap values that would otherwise be read as something other than text.
			return '.' === description.charAt(0) || 'true' === description || 'false' === description
				? '<p>' + description + '</p>'
				: description;
		},
		/**
		 * Normalize the slide data on a gallery anchor before GLightbox reads it.
		 *
		 * Only the allowed data attributes are kept, the title is escaped, and the description is reduced to the
		 * caption markup the gallery already allows.
		 *
		 * @param {HTMLElement} el The anchor GLightbox will be bound to.
		 *
		 *
		 * @return {void}
		 */
		sanitizeSlideData(el) {
			if (el[flxBlocksGLight.sanitizedKey]) {
				return;
			}
			el[flxBlocksGLight.sanitizedKey] = true;

			const rawTitle = el.dataset.title || el.getAttribute('title') || '';
			const description = el.getAttribute('data-description');

			[...el.attributes].forEach(({ name }) => {
				if (name.startsWith('data-') && !flxBlocksGLight.allowedDataAttributes.includes(name)) {
					el.removeAttribute(name);
				}
			});
			// Remove description content nested inside the anchor.
			el.querySelectorAll('.glightbox-desc').forEach((child) => child.remove());

			el.setAttribute('data-title', flxBlocksGLight.escapeHtml(rawTitle));
			if (description) {
				el.setAttribute('data-description', flxBlocksGLight.sanitizeDescription(description));
			} else {
				el.removeAttribute('data-description');
			}

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
			document.querySelectorAll(selector).forEach(flxBlocksGLight.sanitizeSlideData);

			return selector;
		},
		findGalleries() {
			const foundGalleries = document.querySelectorAll('.fxb-gallery-magnific-init');
			if (!foundGalleries.length) {
				return;
			}
			if (foundGalleries) {
				for (let i = 0; i < foundGalleries.length; i++) {
					const galleryClass = foundGalleries[i].classList;
					const filter = foundGalleries[i].getAttribute('data-image-filter');
					const skin = filter ? 'flxbk-dark fxb-gal-light-filter-' + filter : 'flxbk-dark';
					const showCaption = foundGalleries[i].getAttribute('data-lightbox-caption');
					const galleryLinks = foundGalleries[i].querySelectorAll(flxBlocksGLight.linkSelector);
					for (let l = 0; l < galleryLinks.length; l++) {
						flxBlocksGLight.sanitizeSlideData(galleryLinks[l]);
						galleryLinks[l].setAttribute('aria-label', fxb_glightbox.lightBoxAriaLabel + ' ' + (l + 1));
					}
					flxBlocksGLight.foundClasses[i] = false;
					for (let n = 0; n < galleryClass.length; n++) {
						if (galleryClass[n].indexOf('fxb-gallery-id') !== -1) {
							foundGalleries[i].classList.add('fxb-light-gallery-' + i);
							flxBlocksGLight.foundClasses[i] = 'fxb-light-gallery-' + i;
							break;
						}
					}
					if ('true' == showCaption && !foundGalleries[i].classList.contains('fxb-gallery-non-static')) {
						// Static gallery is basically only for backward compatibility.
						const foundImages = foundGalleries[i].querySelectorAll(flxBlocksGLight.linkSelector);
						for (let x = 0; x < foundImages.length; x++) {
							const caption = foundImages[x].querySelector('.flx-blocks-gallery-item__caption');
							if (caption) {
								foundImages[x].setAttribute(
									'data-description',
									flxBlocksGLight.sanitizeDescription(caption.innerText)
								);
							}
						}
					}
					flxBlocksGLight.carouselItem[i] = foundGalleries[i].querySelector('.fxt-blocks-carousel-init');
					if (flxBlocksGLight.carouselItem[i]) {
						if (flxBlocksGLight.carouselItem[i].classList.contains('is-initialized')) {
							flxBlocksGLight.handleClones(flxBlocksGLight.carouselItem[i]);
							if (flxBlocksGLight.foundClasses[i]) {
								flxBlocksGLight.lightboxes[i] = new GLightbox({
									selector: flxBlocksGLight.sanitizeMatching(
										'.' +
											flxBlocksGLight.foundClasses[i] +
											' .fxb-slide-item:not(.splide__slide--clone) ' +
											flxBlocksGLight.linkSelector +
											':not([target="_blank"])'
									),
									touchNavigation: true,
									skin,
									loop: true,
									openEffect: 'fade',
									closeEffect: 'fade',
									moreText: fxb_glightbox.moreText,
								});
							}
						} else {
							flxBlocksGLight.carouselCache[i] = setInterval(function () {
								if (flxBlocksGLight.carouselItem[i].classList.contains('is-initialized')) {
									flxBlocksGLight.handleClones(flxBlocksGLight.carouselItem[i]);
									flxBlocksGLight.lightboxes[i] = new GLightbox({
										selector: flxBlocksGLight.sanitizeMatching(
											'.' +
												flxBlocksGLight.foundClasses[i] +
												' .fxb-slide-item:not(.splide__slide--clone) ' +
												flxBlocksGLight.linkSelector +
												':not([target="_blank"])'
										),
										touchNavigation: true,
										skin,
										loop: true,
										openEffect: 'fade',
										closeEffect: 'fade',
										moreText: fxb_glightbox.moreText,
									});
									clearInterval(flxBlocksGLight.carouselCache[i]);
								} else {
									console.log('waiting to initialize galllery lightbox');
								}
							}, 200);
						}
					} else if (flxBlocksGLight.foundClasses[i]) {
						flxBlocksGLight.lightboxes[i] = new GLightbox({
							selector: flxBlocksGLight.sanitizeMatching(
								'.' +
									flxBlocksGLight.foundClasses[i] +
									' ' +
									flxBlocksGLight.linkSelector +
									':not([target="_blank"])'
							),
							touchNavigation: true,
							skin,
							loop: true,
							openEffect: 'fade',
							closeEffect: 'fade',
							moreText: fxb_glightbox.moreText,
						});
					}
				}
			}
		},
		/**
		 * Initiate the script to process all
		 */
		initAll() {
			flxBlocksGLight.findGalleries();
		},
		// Initiate the menus when the DOM loads.
		init() {
			if (typeof GLightbox == 'function') {
				flxBlocksGLight.initAll();
			} else {
				var initLoadDelay = setInterval(function () {
					if (typeof GLightbox == 'function') {
						flxBlocksGLight.initAll();
						clearInterval(initLoadDelay);
					}
				}, 200);
			}
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', flxBlocksGLight.init);
	} else {
		// The DOM has already been loaded.
		flxBlocksGLight.init();
	}
})();
