/* FluxWP: Swiper ile slider. Editörde .flx-slider grubunun her doğrudan çocuğu bir slayttır. */
(function () {
	if (typeof window.Swiper === 'undefined') return;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	document.querySelectorAll('.flx-slider').forEach(function (root) {
		var slides = Array.prototype.slice.call(root.children);
		if (slides.length < 2) return;
		var isHero = root.classList.contains('flx-hero');
		var wrapper = document.createElement('div');
		wrapper.className = 'swiper-wrapper';
		slides.forEach(function (s) { s.classList.add('swiper-slide'); wrapper.appendChild(s); });
		root.appendChild(wrapper);
		root.classList.add('swiper');

		var pag = document.createElement('div'); pag.className = 'swiper-pagination';
		var prev = document.createElement('button'); prev.type = 'button'; prev.className = 'flx-swiper-prev'; prev.setAttribute('aria-label', (window.flxT ? flxT('prev', 'Önceki') : 'Önceki'));
		var next = document.createElement('button'); next.type = 'button'; next.className = 'flx-swiper-next'; next.setAttribute('aria-label', (window.flxT ? flxT('next', 'Sonraki') : 'Sonraki'));
		root.appendChild(pag); root.appendChild(prev); root.appendChild(next);
		if (isHero) {
			var bar = document.createElement('div'); bar.className = 'flx-swiper-progress'; bar.innerHTML = '<span></span>';
			root.appendChild(bar);
		}

		var opts = {
			loop: true,
			speed: isHero ? 900 : 600,
			grabCursor: true,
			keyboard: { enabled: true, onlyInViewport: true },
			pagination: { el: pag, clickable: true },
			navigation: { prevEl: prev, nextEl: next },
			a11y: { prevSlideMessage: 'Önceki slayt', nextSlideMessage: 'Sonraki slayt', paginationBulletMessage: '{{index}}. slayta git', slideLabelMessage: '{{index}} / {{slidesLength}}' },
			autoplay: reduce ? false : { delay: isHero ? 6500 : 7000, disableOnInteraction: false, pauseOnMouseEnter: false }
		};
		if (isHero) {
			opts.effect = 'creative';
			opts.creativeEffect = {
				prev: { shadow: true, translate: ['-20%', 0, -1] },
				next: { translate: ['100%', 0, 0] }
			};
			opts.on = {
				autoplayTimeLeft: function (s, time, progress) {
					var el = root.querySelector('.flx-swiper-progress span');
					if (el) el.style.transform = 'scaleX(' + (1 - progress) + ')';
					// Aktif nokta: gri zemin, slayt süresi boyunca beyazla dolar.
					var b = root.querySelector('.swiper-pagination-bullet-active');
					if (b) b.style.setProperty('--flx-fill', (1 - progress).toFixed(3));
				}
			};
		} else {
			opts.autoHeight = true;
			opts.spaceBetween = 24;
		}
		new window.Swiper(root, opts);
	});
})();
