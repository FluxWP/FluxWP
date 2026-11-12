/* global flxbk_countdown */
(function () {
	'use strict';
	window.flxbkCountdown = {
		cache: {},
		timers: JSON.parse(flxbk_countdown.timers),
		listenerCache: {},
		sliderEventCache: {},
		prefersReducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
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

			// Check if the countdown element is within the active slide
			return activeSlide.contains(el);
		},
		createCookie(name, value, length, unit) {
			if (length) {
				const date = new Date();
				if ('minutes' == unit) {
					date.setTime(date.getTime() + length * 60 * 1000);
				} else if ('hours' == unit) {
					date.setTime(date.getTime() + length * 60 * 60 * 1000);
				} else {
					date.setTime(date.getTime() + length * 24 * 60 * 60 * 1000);
				}
				var expires = '; expires=' + date.toGMTString();
			} else {
				var expires = '';
			}

			document.cookie = flxbk_countdown.site_slug + '-' + name + '=' + value + expires + '; path=/';
		},
		getCookie(name) {
			const value = '; ' + document.cookie;
			const parts = value.split('; ' + flxbk_countdown.site_slug + '-' + name + '=');
			if (parts.length == 2) {
				return parts.pop().split(';').shift();
			}
			return '';
		},
		getRepeaterTimeStamp(id) {
			const currentDate = new Date();
			const initialDate = new Date(parseInt(window.flxbkCountdown.timers[id].timestamp));
			const seconds = initialDate.getSeconds();
			const minutes = initialDate.getMinutes();
			const hours = initialDate.getHours();
			let futureDate = new Date();
			const daysPassed = (currentDate.getTime() - initialDate.getTime()) / (1000 * 3600 * 24);
			let offsetDays = 0;
			const repeatTimeStamp = 0;
			let dayOfMonth = initialDate.getDate();
			const futureDayOfMonth = currentDate.getDate();
			const initialMonth = initialDate.getMonth();
			const futureMonth =
				currentDate.getMonth() === 11
					? 0
					: futureDayOfMonth >= dayOfMonth
						? currentDate.getMonth() + 1
						: currentDate.getMonth();
			const futureYear =
				currentDate.getMonth() === 11 ? currentDate.getFullYear() + 1 : currentDate.getFullYear();
			const nextMonthDays = new Date(futureYear, futureMonth + 1, 0).getDate();

			switch (window.flxbkCountdown.timers[id].frequency) {
				case 'daily':
					// Check if the target time has already passed today
					const todayTargetTime = new Date();
					todayTargetTime.setHours(hours, minutes, seconds, 0);

					if (currentDate.getTime() >= todayTargetTime.getTime()) {
						// Target time has passed today, set for tomorrow
						offsetDays = 1;
					} else {
						// Target time hasn't passed today, set for today
						offsetDays = 0;
					}

					futureDate.setDate(currentDate.getDate() + offsetDays);
					futureDate.setHours(hours);
					futureDate.setMinutes(minutes);
					futureDate.setSeconds(seconds);
					break;
				case 'weekly':
					// Check if the target time has already passed today
					const todayWeeklyTargetTime = new Date();
					todayWeeklyTargetTime.setHours(hours, minutes, seconds, 0);

					// Calculate days since the initial date
					const daysSinceInitial = Math.floor(
						(currentDate.getTime() - initialDate.getTime()) / (1000 * 3600 * 24)
					);

					if (currentDate.getTime() >= todayWeeklyTargetTime.getTime()) {
						// Target time has passed today, find next week's occurrence
						offsetDays = 7 - (daysSinceInitial % 7);
					} else {
						// Target time hasn't passed today, check if today is the right day of week
						const dayOfWeekDiff = (currentDate.getDay() - initialDate.getDay() + 7) % 7;
						if (dayOfWeekDiff === 0) {
							// Today is the right day of week and time hasn't passed
							offsetDays = 0;
						} else {
							// Find next occurrence
							offsetDays = 7 - dayOfWeekDiff;
						}
					}

					futureDate.setDate(currentDate.getDate() + offsetDays);
					futureDate.setHours(hours);
					futureDate.setMinutes(minutes);
					futureDate.setSeconds(seconds);
					break;
				case 'monthly':
					if (dayOfMonth === 31 && nextMonthDays === 30) {
						dayOfMonth = 30;
					} else if (futureMonth === 0 && dayOfMonth >= 29) {
						dayOfMonth = dayOfMonth === 29 ? dayOfMonth : 28;
					}

					futureDate = new Date(futureYear, futureMonth, dayOfMonth, hours, minutes, seconds);
					break;
				case 'yearly':
					const datePassed =
						currentDate.getMonth() >= initialDate.getMonth() &&
						currentDate.getDate() >= initialDate.getDate() &&
						currentDate.getHours() >= hours &&
						currentDate.getMinutes() >= minutes &&
						currentDate.getSeconds() >= seconds;
					const nextYear = datePassed ? currentDate.getFullYear() : currentDate.getFullYear() + 1;
					futureDate = new Date(nextYear, initialMonth, dayOfMonth, hours, minutes, seconds);
					break;
				default:
					break;
			}

			return futureDate.getTime();
		},
		/**
		 * Decode a string that may contain markup into plain text.
		 *
		 * @param {string} html The string to convert.
		 *
		 * @return {string} The decoded plain text.
		 */
		stripHtml(html) {
			const entities = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0' };
			return String(html ?? '')
				.replace(/<[^>]*>/g, '')
				.replace(/[<>]/g, '')
				.replace(/&(#x[0-9a-f]{1,6}|#[0-9]{1,7}|[a-z]{2,6});/gi, (m, e) => {
					const k = e.toLowerCase();
					if ('#x' === k.slice(0, 2)) {
						return String.fromCodePoint(parseInt(k.slice(2), 16) || 32);
					}
					if ('#' === k[0]) {
						return String.fromCodePoint(parseInt(k.slice(1), 10) || 32);
					}
					return Object.prototype.hasOwnProperty.call(entities, k) ? entities[k] : m;
				});
		},
		createSafeElement(type, className, content) {
			const element = document.createElement(type);
			if (className) {
				element.className = className;
			}
			if (content !== undefined) {
				element.textContent = content;
			}
			return element;
		},
		announceToScreenReader(message) {
			// Create or get the live region for announcements
			let liveRegion = document.getElementById('fxb-countdown-announcements');
			if (!liveRegion) {
				liveRegion = document.createElement('div');
				liveRegion.id = 'fxb-countdown-announcements';
				liveRegion.setAttribute('role', 'status');
				liveRegion.setAttribute('aria-live', 'polite');
				liveRegion.setAttribute('aria-atomic', 'true');
				liveRegion.className = 'screen-reader-text';
				liveRegion.style.cssText =
					'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;';
				document.body.appendChild(liveRegion);
			}
			// Clear previous message and set new one
			liveRegion.textContent = '';
			setTimeout(() => {
				liveRegion.textContent = message;
			}, 100);
		},
		updateTimerInterval(element, id, parent) {
			// Check if paused
			if (this.cache[id] && this.cache[id].paused) {
				return;
			}

			const currentTimeStamp = new Date();
			const userTimezoneOffset = -1 * (new Date().getTimezoneOffset() / 60);
			let total = '';
			if (window.flxbkCountdown.timers[id].type === 'evergreen') {
				//Check for cookie.
				if ('' !== window.flxbkCountdown.cache[id].cookie) {
					total = Math.floor(window.flxbkCountdown.cache[id].cookie - currentTimeStamp.getTime());
				}
				// Check for database storage only for strict.
				if (
					!total &&
					window.flxbkCountdown.timers[id].strict &&
					'query' === window.flxbkCountdown.timers[id].evergreen
				) {
					// remove query so we don't run this twice.
					window.flxbkCountdown.timers[id].evergreen = '';
					window.flxbkCountdown.cache[id].request = new XMLHttpRequest();
					window.flxbkCountdown.cache[id].request.open('POST', flxbk_countdown.ajax_url, true);
					window.flxbkCountdown.cache[id].request.setRequestHeader(
						'Content-Type',
						'application/x-www-form-urlencoded'
					);
					window.flxbkCountdown.cache[id].request.onload = function () {
						if (this.status >= 200 && this.status < 400) {
							// If successful
							window.flxbkCountdown.cache[id].evergreen = parseInt(this.response);
							if (window.flxbkCountdown.cache[id].evergreen) {
								total = Math.floor(
									window.flxbkCountdown.cache[id].evergreen - currentTimeStamp.getTime()
								);
								window.flxbkCountdown.createCookie(
									window.flxbkCountdown.timers[id].campaign_id,
									window.flxbkCountdown.cache[id].evergreen,
									30,
									'days'
								);
								window.flxbkCountdown.cache[id].cookie = window.flxbkCountdown.cache[id].evergreen;
							}
						}
					};
					window.flxbkCountdown.cache[id].request.onerror = function () {
						// Connection error
					};
					window.flxbkCountdown.cache[id].request.send(
						'action=flxbk_get_evergreen&nonce=' +
							flxbk_countdown.ajax_nonce +
							'&site_slug=' +
							flxbk_countdown.site_slug +
							'&reset=' +
							window.flxbkCountdown.cache[id].reset +
							'&countdown_id=' +
							window.flxbkCountdown.timers[id].campaign_id
					);
				}
				// Check for loaded no cache mode.
				if (
					!total &&
					!window.flxbkCountdown.timers[id].strict &&
					window.flxbkCountdown.timers[id].evergreen &&
					'query' !== window.flxbkCountdown.timers[id].evergreen
				) {
					total = Math.floor(window.flxbkCountdown.timers[id].evergreen - currentTimeStamp.getTime());
				}
				// We've set the cache and it's counting.
				if (!total && window.flxbkCountdown.cache[id].evergreen) {
					total = Math.floor(window.flxbkCountdown.cache[id].evergreen - currentTimeStamp.getTime());
				}
				// Total is negative so past date, let check if we should reset it.
				if (total && total < 0) {
					// check if reset is needed.
					const resetDate = new Date();
					resetDate.setTime(
						window.flxbkCountdown.cache[id].cookie +
							Math.floor(window.flxbkCountdown.cache[id].reset) * 24 * 60 * 60 * 1000
					);
					const shouldRest = Math.floor(resetDate.getTime() - currentTimeStamp.getTime());
					if (shouldRest < 0) {
						total = '';
					}
				}
				// total is empty so lets set it, however if we are in strict mode we need to wait for the ajax request.
				if (
					!total &&
					((window.flxbkCountdown.timers[id].strict &&
						window.flxbkCountdown.cache[id].request &&
						window.flxbkCountdown.cache[id].request.readyState &&
						window.flxbkCountdown.cache[id].request.readyState === 4) ||
						!window.flxbkCountdown.timers[id].strict)
				) {
					const newDate = new Date();
					newDate.setTime(
						newDate.getTime() + Math.floor(window.flxbkCountdown.timers[id].hours) * 60 * 60 * 1000
					);
					newDate.setTime(
						newDate.getTime() + Math.floor(window.flxbkCountdown.timers[id].minutes) * 60 * 1000
					);
					window.flxbkCountdown.cache[id].evergreen = newDate.getTime() + 100;
					window.flxbkCountdown.createCookie(
						window.flxbkCountdown.timers[id].campaign_id,
						window.flxbkCountdown.cache[id].evergreen,
						30,
						'days'
					);
					total = Math.floor(newDate.getTime() - currentTimeStamp.getTime());
					const request = new XMLHttpRequest();
					request.open('POST', flxbk_countdown.ajax_url, true);
					request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					request.onload = function () {
						if (this.status >= 200 && this.status < 400) {
							// If successful
						}
					};
					request.onerror = function () {
						// Connection error
					};
					request.send(
						'action=flxbk_evergreen_timestamp&nonce=' +
							flxbk_countdown.ajax_nonce +
							'&site_slug=' +
							flxbk_countdown.site_slug +
							'&timestamp=' +
							window.flxbkCountdown.cache[id].evergreen +
							'&countdown_id=' +
							window.flxbkCountdown.timers[id].campaign_id
					);
				}
			} else {
				total = Math.floor(window.flxbkCountdown.timers[id].timestamp - currentTimeStamp.getTime());
			}

			const stopRepeating = !window.flxbkCountdown.timers[id].stopCount
				? true
				: new Date(window.flxbkCountdown.timers[id].endDate) <= new Date()
					? false
					: true;

			if (window.flxbkCountdown.timers[id].repeat && total <= 0 && stopRepeating) {
				const futureTimeStamp = window.flxbkCountdown.getRepeaterTimeStamp(id);
				total = Math.floor(futureTimeStamp - currentTimeStamp.getTime());
			}

			// Check if completed.
			if (total && total < 0) {
				if ('redirect' === window.flxbkCountdown.timers[id].action) {
					if (window.flxbkCountdown.timers[id].redirect) {
						window.location.href = window.flxbkCountdown.timers[id].redirect;
					}
				} else if ('hide' === window.flxbkCountdown.timers[id].action) {
					// Announce to screen readers before hiding
					window.flxbkCountdown.announceToScreenReader(
						wp.i18n.__('Countdown timer has ended.', 'flx-blocks')
					);
					parent.style.display = 'none';
				} else if ('message' === window.flxbkCountdown.timers[id].action) {
					if (parent.querySelector('.fxb-countdown-inner-first')) {
						parent.querySelector('.fxb-countdown-inner-first').style.display = 'none';
					}
					if (parent.querySelector('.fxb-countdown-timer')) {
						parent.querySelector('.fxb-countdown-timer').style.display = 'none';
					}
					if (parent.querySelector('.fxb-countdown-inner-second')) {
						parent.querySelector('.fxb-countdown-inner-second').style.display = 'none';
					}
					const completeContent = parent.querySelector('.fxb-countdown-inner-complete');
					if (completeContent) {
						completeContent.style.display = 'block';
						// Make the replacement content a live region for screen readers
						completeContent.setAttribute('role', 'status');
						completeContent.setAttribute('aria-live', 'polite');
						completeContent.setAttribute('aria-atomic', 'true');
						// Announce the content change
						const completeText = window.flxbkCountdown.stripHtml(
							completeContent.textContent || completeContent.innerText || ''
						);
						if (completeText.trim()) {
							window.flxbkCountdown.announceToScreenReader(
								wp.i18n.__('Countdown timer has ended.', 'flx-blocks') + ' ' + completeText
							);
						} else {
							window.flxbkCountdown.announceToScreenReader(
								wp.i18n.__('Countdown timer has ended. New content is now available.', 'flx-blocks')
							);
						}
					}
					parent.style.opacity = 1;
					if (window.flxbkCountdown.timers[id].revealOnLoad) {
						parent.style.height = parent.scrollHeight + 'px';
					}
				} else {
					if (window.flxbkCountdown.timers[id].timer) {
						const enableDividers = window.flxbkCountdown.timers[id].dividers;
						const timeNumbers = window.flxbkCountdown.timers[id].stopWatch;
						const units = window.flxbkCountdown.timers[id].units;
						const labels = {
							days: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].daysLabel),
							hours: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].hoursLabel),
							minutes: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].minutesLabel),
							seconds: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].secondsLabel),
						};
						const parts = {};

						if (
							undefined !== units &&
							undefined !== units[0] &&
							undefined !== units[0].days &&
							!units[0].days
						) {
							if (
								undefined !== units &&
								undefined !== units[0] &&
								undefined !== units[0].hours &&
								!units[0].hours
							) {
								if (
									undefined !== units &&
									undefined !== units[0] &&
									undefined !== units[0].minutes &&
									!units[0].minutes
								) {
									parts.seconds = 0;
								} else {
									parts.minutes = 0;
									parts.seconds = 0;
								}
							} else {
								parts.hours = 0;
								parts.minutes = 0;
								parts.seconds = 0;
							}
						} else {
							parts.days = 0;
							parts.hours = 0;
							parts.minutes = 0;
							parts.seconds = 0;
						}

						// Clear existing content
						element.innerHTML = '';

						// Add pre-label if exists
						if (window.flxbkCountdown.timers[id].preLabel) {
							const preTimer = this.createSafeElement('div', 'fxb-countdown-item fxb-pre-timer');
							const preInner = this.createSafeElement(
								'span',
								'fxb-pre-timer-inner',
								this.stripHtml(window.flxbkCountdown.timers[id].preLabel)
							);
							preTimer.appendChild(preInner);
							element.appendChild(preTimer);
						}

						// Add timer parts
						Object.keys(parts).forEach((part) => {
							const itemContainer = this.createSafeElement(
								'div',
								`fxb-countdown-item fxb-countdown-date-item fxb-countdown-date-item-${part}`
							);

							const numberSpan = this.createSafeElement(
								'span',
								'fxb-countdown-number',
								this.calculateNumberDesign(this.stripHtml(parts[part].toString()), timeNumbers)
							);
							itemContainer.appendChild(numberSpan);

							const labelSpan = this.createSafeElement(
								'span',
								'fxb-countdown-label',
								this.stripHtml(labels[part])
							);
							itemContainer.appendChild(labelSpan);

							element.appendChild(itemContainer);

							// Add dividers if needed
							if ('seconds' !== part && enableDividers) {
								const dividerContainer = this.createSafeElement(
									'div',
									`fxb-countdown-item fxb-countdown-date-item fxb-countdown-divider-item fxb-countdown-divider-item-${part}`
								);

								const dividerNumber = this.createSafeElement('span', 'fxb-countdown-number', ':');
								const dividerLabel = this.createSafeElement('span', 'fxb-countdown-label', ' ');

								dividerContainer.appendChild(dividerNumber);
								dividerContainer.appendChild(dividerLabel);
								element.appendChild(dividerContainer);
							}
						});

						// Add post label if exists
						if (window.flxbkCountdown.timers[id].postLabel) {
							const postTimer = this.createSafeElement('div', 'fxb-countdown-item fxb-post-timer');
							const postInner = this.createSafeElement(
								'span',
								'fxb-post-timer-inner',
								this.stripHtml(window.flxbkCountdown.timers[id].postLabel)
							);
							postTimer.appendChild(postInner);
							element.appendChild(postTimer);
						}
					}
					parent.style.opacity = 1;
					if (window.flxbkCountdown.timers[id].revealOnLoad) {
						parent.style.height = parent.scrollHeight + 'px';
					}
				}
				if (window.flxbkCountdown.cache[id].interval) {
					clearInterval(window.flxbkCountdown.cache[id].interval);
				}
				return;
			}

			if ((total || 0 === total) && window.flxbkCountdown.timers[id].timer) {
				const enableDividers = window.flxbkCountdown.timers[id].dividers;
				const timeNumbers = window.flxbkCountdown.timers[id].stopWatch;
				const units = window.flxbkCountdown.timers[id].units;
				const labels = {
					days: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].daysLabel),
					hours: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].hoursLabel),
					minutes: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].minutesLabel),
					seconds: window.flxbkCountdown.stripHtml(window.flxbkCountdown.timers[id].secondsLabel),
				};

				let calculateHours = Math.floor((total / (1000 * 60 * 60)) % 24);
				let calculateMinutes = Math.floor((total / 1000 / 60) % 60);
				let calculateSeconds = Math.floor((total / 1000) % 60);
				const parts = {};

				if (undefined !== units && undefined !== units[0] && undefined !== units[0].days && !units[0].days) {
					calculateHours = Math.floor(total / (1000 * 60 * 60));
					if (
						undefined !== units &&
						undefined !== units[0] &&
						undefined !== units[0].hours &&
						!units[0].hours
					) {
						calculateMinutes = Math.floor(total / 1000 / 60);
						if (
							undefined !== units &&
							undefined !== units[0] &&
							undefined !== units[0].minutes &&
							!units[0].minutes
						) {
							calculateSeconds = Math.floor(total / 1000);
							parts.seconds = calculateSeconds;
						} else {
							parts.minutes = calculateMinutes;
							parts.seconds = calculateSeconds;
						}
					} else {
						parts.hours = calculateHours;
						parts.minutes = calculateMinutes;
						parts.seconds = calculateSeconds;
					}
				} else {
					parts.days = Math.floor(total / (1000 * 60 * 60 * 24));
					parts.hours = calculateHours;
					parts.minutes = calculateMinutes;
					parts.seconds = calculateSeconds;
				}

				// Clear existing content
				element.innerHTML = '';

				// Add pre-label if exists
				// Add pre-label if exists
				if (window.flxbkCountdown.timers[id].preLabel) {
					const preTimer = this.createSafeElement('div', 'fxb-countdown-item fxb-pre-timer');
					const preInner = this.createSafeElement(
						'span',
						'fxb-pre-timer-inner',
						this.stripHtml(window.flxbkCountdown.timers[id].preLabel)
					);
					preTimer.appendChild(preInner);
					element.appendChild(preTimer);
				}

				// Add timer parts
				Object.keys(parts).forEach((part) => {
					const itemContainer = this.createSafeElement(
						'div',
						`fxb-countdown-item fxb-countdown-date-item fxb-countdown-date-item-${part}`
					);

					const numberSpan = this.createSafeElement(
						'span',
						'fxb-countdown-number',
						this.calculateNumberDesign(this.stripHtml(parts[part].toString()), timeNumbers)
					);
					itemContainer.appendChild(numberSpan);

					const labelSpan = this.createSafeElement(
						'span',
						'fxb-countdown-label',
						this.stripHtml(labels[part])
					);
					itemContainer.appendChild(labelSpan);

					element.appendChild(itemContainer);

					// Add dividers if needed
					if ('seconds' !== part && enableDividers) {
						const dividerContainer = this.createSafeElement(
							'div',
							`fxb-countdown-item fxb-countdown-date-item fxb-countdown-divider-item fxb-countdown-divider-item-${part}`
						);

						const dividerNumber = this.createSafeElement('span', 'fxb-countdown-number', ':');
						const dividerLabel = this.createSafeElement('span', 'fxb-countdown-label', ' ');

						dividerContainer.appendChild(dividerNumber);
						dividerContainer.appendChild(dividerLabel);
						element.appendChild(dividerContainer);
					}
				});

				// Add post label if exists
				if (window.flxbkCountdown.timers[id].postLabel) {
					const postTimer = this.createSafeElement('div', 'fxb-countdown-item fxb-post-timer');
					const postInner = this.createSafeElement(
						'span',
						'fxb-post-timer-inner',
						this.stripHtml(window.flxbkCountdown.timers[id].postLabel)
					);
					postTimer.appendChild(postInner);
					element.appendChild(postTimer);
				}
			}

			if ((total || 0 === total) && !window.flxbkCountdown.cache[id].revealed) {
				window.flxbkCountdown.cache[id].revealed = true;
				parent.style.opacity = 1;
				if (window.flxbkCountdown.timers[id].revealOnLoad) {
					const sticky = parent.closest('.flxbk-pro-fixed-wrap');
					if (sticky && !window.flxbkCountdown.timers[id].timer) {
						setTimeout(function () {
							parent.style.height = parent.scrollHeight + 'px';
							if (!window.flxbkCountdown.prefersReducedMotion) {
								sticky.style.transition = 'height 0.8s ease';
							}
							sticky.style.height = Math.floor(sticky.scrollHeight + parent.scrollHeight) + 'px';
						}, 200);
						setTimeout(function () {
							const event = new CustomEvent('flxbk-update-sticky');
							window.dispatchEvent(event);
							sticky.style.transition = '';
						}, 1000);
					} else {
						parent.style.height = parent.scrollHeight + 'px';
					}
				}
			}
		},
		calculateNumberDesign(number, timeNumbers = false) {
			if (timeNumbers) {
				return number > 9 ? '' + number : '0' + number;
			}
			return number;
		},
		setupSliderListeners(id, element) {
			const countdownElement = element.closest('.fxb-countdown-container');
			const slider = countdownElement.closest('.fxb-splide, .splide');

			if (!slider) {
				return; // Not in a slider, no need for slider listeners
			}

			// Create event handlers
			const mountedHandler = (e) => {
				window.flxbkCountdown.checkAndStartTimer(id, element, countdownElement);
			};

			const movedHandler = (e) => {
				window.flxbkCountdown.checkAndStartTimer(id, element, countdownElement);
			};

			// Add event listeners
			slider.addEventListener('splideMounted', mountedHandler);
			slider.addEventListener('splide:moved', movedHandler);

			// Store references for cleanup
			window.flxbkCountdown.sliderEventCache[id] = [
				{ element: slider, event: 'splideMounted', handler: mountedHandler },
				{ element: slider, event: 'splide:moved', handler: movedHandler },
			];
		},
		togglePause(id, button) {
			if (!window.flxbkCountdown.cache[id]) {
				return;
			}

			const isPaused = window.flxbkCountdown.cache[id].paused || false;

			if (isPaused) {
				// Resume
				window.flxbkCountdown.cache[id].paused = false;
				button.setAttribute('aria-pressed', 'false');
				button.setAttribute('aria-label', wp.i18n.__('Pause countdown timer', 'flx-blocks'));
				button.setAttribute('title', wp.i18n.__('Pause countdown', 'flx-blocks'));
				const icon = button.querySelector('.fxb-countdown-pause-icon');
				if (icon) {
					icon.innerHTML =
						'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="6" y="4" width="4" height="16" rx="1" fill="currentColor" /><rect x="14" y="4" width="4" height="16" rx="1" fill="currentColor" /></svg>';
				}
				button.classList.remove('fxb-countdown-paused');

				// Restart the interval
				if (window.flxbkCountdown.cache[id].element && window.flxbkCountdown.cache[id].parent) {
					// Update immediately
					window.flxbkCountdown.updateTimerInterval(
						window.flxbkCountdown.cache[id].element,
						id,
						window.flxbkCountdown.cache[id].parent
					);
					// Then set up interval
					window.flxbkCountdown.cache[id].interval = setInterval(function () {
						window.flxbkCountdown.updateTimerInterval(
							window.flxbkCountdown.cache[id].element,
							id,
							window.flxbkCountdown.cache[id].parent
						);
					}, 1000);
				}
			} else {
				// Pause
				window.flxbkCountdown.cache[id].paused = true;
				button.setAttribute('aria-pressed', 'true');
				button.setAttribute('aria-label', wp.i18n.__('Resume countdown timer', 'flx-blocks'));
				button.setAttribute('title', wp.i18n.__('Resume countdown', 'flx-blocks'));
				const icon = button.querySelector('.fxb-countdown-pause-icon');
				if (icon) {
					icon.innerHTML =
						'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7z" fill="currentColor" /></svg>';
				}
				button.classList.add('fxb-countdown-paused');

				// Clear the interval
				if (window.flxbkCountdown.cache[id].interval) {
					clearInterval(window.flxbkCountdown.cache[id].interval);
					window.flxbkCountdown.cache[id].interval = null;
				}
			}
		},
		checkAndStartTimer(id, element, parent) {
			// Only check for evergreen timers
			if (window.flxbkCountdown.timers[id].type !== 'evergreen') {
				return;
			}

			// Don't start if user prefers reduced motion
			if (window.flxbkCountdown.prefersReducedMotion) {
				// Just update once to show the initial time
				if (!window.flxbkCountdown.cache[id].started) {
					window.flxbkCountdown.cache[id].started = true;
					window.flxbkCountdown.updateTimerInterval(element, id, parent);
				}
				return;
			}

			// Don't start if paused
			if (window.flxbkCountdown.cache[id] && window.flxbkCountdown.cache[id].paused) {
				return;
			}

			// Check if timer should start based on viewport and active slide
			if (window.flxbkCountdown.isInViewport(parent) && window.flxbkCountdown.isInActiveSlide(parent)) {
				// Start the timer if it hasn't been started yet
				if (!window.flxbkCountdown.cache[id].started) {
					window.flxbkCountdown.cache[id].started = true;
					window.flxbkCountdown.updateTimerInterval(element, id, parent);
					window.flxbkCountdown.cache[id].interval = setInterval(function () {
						window.flxbkCountdown.updateTimerInterval(element, id, parent);
					}, 1000);

					// Remove scroll listener if it exists
					if (window.flxbkCountdown.listenerCache[id]) {
						document.removeEventListener('scroll', window.flxbkCountdown.listenerCache[id], false);
						delete window.flxbkCountdown.listenerCache[id];
					}

					// Remove slider event listeners
					if (window.flxbkCountdown.sliderEventCache[id]) {
						window.flxbkCountdown.sliderEventCache[id].forEach((listener) => {
							listener.element.removeEventListener(listener.event, listener.handler);
						});
						delete window.flxbkCountdown.sliderEventCache[id];
					}
				}
			}
		},
		updateTimer(element, id, parent) {
			window.flxbkCountdown.cache[id] = {};
			window.flxbkCountdown.cache[id].evergreen = '';
			window.flxbkCountdown.cache[id].request = '';
			window.flxbkCountdown.cache[id].revealed = false;
			window.flxbkCountdown.cache[id].cookie = '';
			window.flxbkCountdown.cache[id].started = false;
			window.flxbkCountdown.cache[id].paused = false; // Start unpaused so initial display works
			window.flxbkCountdown.cache[id].element = element;
			window.flxbkCountdown.cache[id].parent = parent;

			// Add accessibility role and live region to timer element.
			if (element) {
				element.setAttribute('role', 'timer');
				element.setAttribute('aria-live', 'polite');
			}

			if (
				window.flxbkCountdown.timers[id].type === 'evergreen' &&
				window.flxbkCountdown.timers[id].campaign_id
			) {
				window.flxbkCountdown.cache[id].cookie = window.flxbkCountdown.getCookie(
					window.flxbkCountdown.timers[id].campaign_id
				);
			}

			// Always update once to show the initial time (before setting paused state)
			window.flxbkCountdown.updateTimerInterval(element, id, parent);

			// Setup pause button if it exists
			const pauseButton = parent.querySelector('.fxb-countdown-pause-button');
			if (pauseButton) {
				// Remove any existing event listeners by cloning
				const newButton = pauseButton.cloneNode(true);
				pauseButton.parentNode.replaceChild(newButton, pauseButton);

				// If prefers reduced motion, set button to play state initially and pause the timer
				if (window.flxbkCountdown.prefersReducedMotion) {
					window.flxbkCountdown.cache[id].paused = true;
					newButton.setAttribute('aria-pressed', 'true');
					newButton.setAttribute('aria-label', wp.i18n.__('Resume countdown timer', 'flx-blocks'));
					newButton.setAttribute('title', wp.i18n.__('Resume countdown', 'flx-blocks'));
					const icon = newButton.querySelector('.fxb-countdown-pause-icon');
					if (icon) {
						icon.innerHTML =
							'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7z" fill="currentColor" /></svg>';
					}
					newButton.classList.add('fxb-countdown-paused');
				}

				// Add click handler
				newButton.addEventListener('click', (e) => {
					e.preventDefault();
					window.flxbkCountdown.togglePause(id, newButton);
				});

				// Keyboard support
				newButton.addEventListener('keydown', (e) => {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						window.flxbkCountdown.togglePause(id, newButton);
					}
				});
			}

			// Check if user prefers reduced motion - if so, don't start countdown interval
			if (window.flxbkCountdown.prefersReducedMotion) {
				// Timer is already displayed and paused, just don't start the interval
				return;
			}

			// For evergreen timers, check viewport and active slide before starting
			if (window.flxbkCountdown.timers[id].type === 'evergreen') {
				// Setup scroll listener
				window.flxbkCountdown.listenerCache[id] = function () {
					window.flxbkCountdown.checkAndStartTimer(id, element, parent);
				};
				document.addEventListener('scroll', window.flxbkCountdown.listenerCache[id], { passive: true });

				// Setup slider listeners if in a slider
				window.flxbkCountdown.setupSliderListeners(id, element);

				// Check immediately if conditions are met
				window.flxbkCountdown.checkAndStartTimer(id, element, parent);
			} else {
				// For non-evergreen timers, start immediately
				window.flxbkCountdown.updateTimerInterval(element, id, parent);
				window.flxbkCountdown.cache[id].interval = setInterval(function () {
					window.flxbkCountdown.updateTimerInterval(element, id, parent);
				}, 1000);
			}
		},
		initTimer() {
			const countdowns = document.querySelectorAll('.fxb-countdown-container');
			if (!countdowns.length) {
				return;
			}
			for (let n = 0; n < countdowns.length; n++) {
				const id = countdowns[n].getAttribute('data-id');
				if (id && window.flxbkCountdown.timers[id]) {
					const el = countdowns[n].querySelector('.fxb-countdown-timer');
					window.flxbkCountdown.updateTimer(el, id, countdowns[n]);
				}
			}
		},
	};
	// Listen for changes to prefers-reduced-motion
	if (window.matchMedia) {
		const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
		const handleMotionChange = (e) => {
			const wasReduced = window.flxbkCountdown.prefersReducedMotion;
			window.flxbkCountdown.prefersReducedMotion = e.matches;

			// If preference changed, restart all timers
			if (wasReduced !== e.matches) {
				// Reinitialize all timers
				window.flxbkCountdown.initTimer();
			}
		};

		if (motionQuery.addEventListener) {
			motionQuery.addEventListener('change', handleMotionChange);
		} else {
			// Fallback for older browsers
			motionQuery.addListener(handleMotionChange);
		}
	}
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', window.flxbkCountdown.initTimer);
	} else {
		// The DOM has already been loaded.
		window.flxbkCountdown.initTimer();
	}
})();
