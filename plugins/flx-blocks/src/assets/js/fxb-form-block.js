// cSpell:ignore ajaxurl describedby grecaptcha telregex urlregex
/* global flxbk_form_params */
(function () {
	'use strict';
	window.flxbkForm = {
		error_item: 1,
		clearForm(form) {
			form.reset();
		},
		insertAfter(newNode, referenceNode) {
			referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
		},
		markError(item, error_type, form) {
			let error_string = '';
			if (!form.classList.contains('fxb-form-has-error')) {
				form.classList.add('fxb-form-has-error');
			}
			item.classList.add('has-error');
			if (error_type) {
				switch (error_type) {
					case 'required':
						error_string = item.getAttribute('data-required-message');
						if (!error_string || '' === error_string || undefined === error_string) {
							error_string = item.getAttribute('data-label');
							if (!error_string || '' === error_string || undefined === error_string) {
								error_string = flxbk_form_params.item;
							}
							error_string = error_string + ' ' + flxbk_form_params[error_type];
						}
						break;
					case 'mismatch':
						error_string = item.getAttribute('data-mismatch-message');
						if (!error_string || '' === error_string || undefined === error_string) {
							error_string = item.getAttribute('data-label');
							if (!error_string || '' === error_string || undefined === error_string) {
								error_string = flxbk_form_params.item;
							}
							error_string = error_string + ' ' + flxbk_form_params[error_type];
						}
						break;
					case 'validation':
						error_string = item.getAttribute('data-validation-message');
						if (!error_string || '' === error_string || undefined === error_string) {
							error_string = item.getAttribute('data-label');
							if (!error_string || '' === error_string || undefined === error_string) {
								error_string = flxbk_form_params.item;
							}
							error_string = error_string + ' ' + flxbk_form_params[error_type];
						}
						break;
				}
				const next = item.parentNode.querySelector('.fxb-form-error-msg');
				if (next) {
					next.remove();
				}
				const error_id = item.getAttribute('name') + '-error';
				item.setAttribute('aria-describedby', error_id);
				item.setAttribute('aria-invalid', 'true');
				const el = document.createElement('div');
				el.id = error_id;
				el.classList.add('fxb-form-error-msg');
				el.classList.add('flx-blocks-form-warning');
				el.setAttribute('role', 'alert');
				el.innerHTML = window.flxbkForm.strip_tags(error_string, '<div><a><b><i><u><p><ol><ul>');
				if (item.classList.contains('fxb-checkbox-style')) {
					item.parentNode.append(el);
				} else {
					window.flxbkForm.insertAfter(el, item);
				}
			}
			if (1 === window.flxbkForm.error_item) {
				item.focus();
			}
			window.flxbkForm.error_item++;
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
		addErrorNotice(form) {
			let error_message = form.getAttribute('data-error-message');
			if (!error_message || '' === error_message || undefined === error_message) {
				error_message = flxbk_form_params.error_message;
			}
			const el = document.createElement('div');
			el.classList.add('flx-blocks-form-message');
			el.classList.add('flx-blocks-form-warning');
			el.innerHTML = window.flxbkForm.strip_tags(error_message, '<div><a><b><i><u><p><ol><ul>');
			window.flxbkForm.insertAfter(el, form);
		},
		isValidEmail(email) {
			const pattern = new RegExp(
				/^[^\s@<>()\[\],;:\"]+@[^\s@<>()\[\],;:\".]+(?:\.[^\s@<>()\[\],;:\".]+)+$/i
			);
			return pattern.test(email);
		},
		isValidURL(url) {
			const urlregex = new RegExp(
				'^(http://www.|https://www.|ftp://www.|www.|http://|https://){1}([0-9A-Za-z]+.)'
			);
			return urlregex.test(url);
		},
		isValidTel(tel) {
			const telregex = new RegExp('/^[+]?[(]?[0-9]{3}[)]?[-s.]?[0-9]{3}[-s.]?[0-9]{4,6}$/im');
			return telregex.test(tel);
		},
		removeErrors(item) {
			if (item.classList.contains('fxb-form-has-error')) {
				item.classList.remove('fxb-form-has-error');
			}
			const errors = item.querySelectorAll('.has-error');
			if (errors.length) {
				for (var n = 0; n < errors.length; n++) {
					errors[n].classList.remove('has-error');
					errors[n].removeAttribute('aria-describedby');
					errors[n].removeAttribute('aria-invalid');
					const next = errors[n].parentNode.querySelector('.fxb-form-error-msg');
					if (next) {
						next.remove();
					}
				}
			}
			const message = document.querySelectorAll('.flx-blocks-form-message');
			if (message.length) {
				for (var n = 0; n < message.length; n++) {
					message[n].remove();
				}
			}
			const notices = item.querySelectorAll('.fxb-form-errors');
			if (notices.length) {
				for (var n = 0; n < notices.length; n++) {
					notices[n].remove();
				}
			}
		},
		serialize(data) {
			const obj = {};
			for (const [key, value] of data) {
				if (obj[key] !== undefined) {
					if (!Array.isArray(obj[key])) {
						obj[key] = [obj[key]];
					}
					obj[key].push(value);
				} else {
					obj[key] = value;
				}
			}
			return obj;
		},
		validateForm(self) {
			let error = false,
				error_type = '';
			// remove all initial errors if any.
			window.flxbkForm.removeErrors(self);
			// ===== Validate: Text and Textarea ========
			const required = self.querySelectorAll('[data-required="yes"]');
			if (required.length) {
				for (let n = 0; n < required.length; n++) {
					var data_type = required[n].getAttribute('data-type'),
						val = '';
					switch (data_type) {
						case 'textarea':
						case 'text':
							val = required[n].value.trim();

							if (val === '') {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;
						case 'tel':
							val = required[n].value.trim();
							if (val === '') {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;
						case 'accept':
							if (required[n].checked == false) {
								error = true;
								error_type = 'required';
								console.log('here');
								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;

						case 'select':
							val = required[n].value;
							//console.log(val );
							if (required[n].multiple) {
								if (val === null || val.length === 0) {
									error = true;
									error_type = 'required';

									// mark the error in the field.
									window.flxbkForm.markError(required[n], error_type, self);
								}
							} else if (!val || val === '-1') {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;

						case 'radio':
							var length = required[n].querySelector('input:checked');

							if (!length) {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;

						case 'checkbox':
							var length = required[n].querySelector('input:checked');

							if (!length) {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;

						case 'email':
							var val = required[n].value.trim();

							if (val !== '') {
								//run the validation
								if (!window.flxbkForm.isValidEmail(val)) {
									error = true;
									error_type = 'validation';

									// mark the error in the field.
									window.flxbkForm.markError(required[n], error_type, self);
								}
							} else if (val === '') {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;
						case 'url':
							var val = required[n].value.trim();

							if (val !== '') {
								//run the validation
								if (!window.flxbkForm.isValidURL(val)) {
									error = true;
									error_type = 'validation';

									// mark the error in the field.
									window.flxbkForm.markError(required[n], error_type, self);
								}
							} else if (val === '') {
								error = true;
								error_type = 'required';

								// mark the error in the field.
								window.flxbkForm.markError(required[n], error_type, self);
							}
							break;
					}
				}
			}

			// if already some error found, bail out
			if (error) {
				// add error notice
				window.flxbkForm.addErrorNotice(self);

				return false;
			}
			//var form_data = self.serialize();
			let form_data = new FormData(self);
			form_data.set('_fxb_form_verify', flxbk_form_params.nonce);
			//form_data = window.flxbkForm.serialize( form_data );
			form_data = new URLSearchParams(form_data);
			//form_data = form_data + '&_fxb_form_verify=' + flxbk_form_params.nonce;
			return form_data;
		},
		createElementFromHTML(htmlString) {
			const div = document.createElement('div');
			div.innerHTML = window.flxbkForm.strip_tags(htmlString, '<div><a><b><i><u><p><ol><ul>');

			// Change this to div.childNodes to support multiple top-level nodes
			return div.firstChild;
		},
		submit(e, form) {
			e.preventDefault();
			const event = new Event('fxb-form-start-submit');
			// Dispatch the event.
			window.document.body.dispatchEvent(event);
			const submitButton = form.querySelector('.fxb-forms-submit');
			const form_data = window.flxbkForm.validateForm(form);
			if (form_data) {
				const el = document.createElement('div');
				let waitingForRedirect = false;
				el.classList.add('fxb-form-loading');
				el.innerHTML = '<div class="fxb-form-loading-spin"><div></div><div></div><div></div><div></div></div>';
				form.append(el);
				submitButton.setAttribute('disabled', 'disabled');
				submitButton.classList.add('button-primary-disabled');
				const request = new XMLHttpRequest();
				request.open('POST', flxbk_form_params.ajaxurl, true);
				request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				request.onload = function () {
					if (this.status >= 200 && this.status < 400) {
						// If successful
						//console.log( JSON.parse( this.response ) );
						const response = JSON.parse(this.response);
						if (response.success) {
							const event = new CustomEvent('fxb-form-success', {
								detail: form.querySelector('input[name="_fxb_form_id"]')
									? form.querySelector('input[name="_fxb_form_id"]').value
									: '',
							});
							// Dispatch the event.
							window.document.body.dispatchEvent(event);
							if (response.redirect) {
								waitingForRedirect = true;
								window.location = response.redirect;
							} else {
								window.flxbkForm.insertAfter(
									window.flxbkForm.createElementFromHTML(response.html),
									form
								);
								if (form.querySelector('.g-recaptcha')) {
									grecaptcha.reset();
								}
								window.flxbkForm.clearForm(form);
							}
						} else if (response.data) {
							window.flxbkForm.insertAfter(
								window.flxbkForm.createElementFromHTML(response.data.html),
								form
							);
							if (response.data.required) {
								if (form.querySelector('[name="' + response.data.required + '"]')) {
									window.flxbkForm.markError(
										form.querySelector('[name="' + response.data.required + '"]'),
										'required',
										form
									);
								}
							}
						}
					}
					if (form.querySelector('.g-recaptcha')) {
						grecaptcha.reset();
					}
					// Prevents double submission while redirect is happening.
					if (!waitingForRedirect) {
						submitButton.removeAttribute('disabled');
						submitButton.classList.remove('button-primary-disabled');
					}
					form.querySelector('.fxb-form-loading').remove();
				};
				request.onerror = function () {
					// Connection error
					console.log('Connection error');
				};
				request.send(form_data.toString());
			}
		},
		checkParentClass(element, classname) {
			if (!element?.className) {
				return false;
			}
			if (element.className.split(' ').indexOf(classname) >= 0) {
				return element.id;
			}
			return element.parentNode && window.flxbkForm.checkParentClass(element.parentNode, classname);
		},
		verifySource(form) {
			const input = form.querySelector('input[name="_fxb_form_post_id"]');
			if (!input) {
				return;
			}
			if (!input.value || 'block-unknown' === input.value || '0' === input.value) {
				const theID = window.flxbkForm.checkParentClass(form.parentNode, 'widget_block');
				if (theID) {
					input.value = theID;
				}
			}
		},
		addAriaRequired(form) {
			const inputs = form.querySelectorAll('input, select, textarea');

			if (!inputs.length) {
				return;
			}

			for (let i = 0; i < inputs.length; i++) {
				const input = inputs[i];
				const isRequired = input.dataset?.required;
				const isHidden = input.getAttribute('type') == 'hidden';
				if (isRequired && !isHidden) {
					input.setAttribute('aria-required', 'true');
				}
			}
		},
		initForms() {
			const forms = document.querySelectorAll('form.fxb-form');
			if (!forms.length) {
				return;
			}
			const click_function = function (form) {
				return function curried_func(e) {
					window.flxbkForm.submit(e, form);
				};
			};
			for (let n = 0; n < forms.length; n++) {
				window.flxbkForm.verifySource(forms[n]);
				window.flxbkForm.addAriaRequired(forms[n]);
				forms[n].addEventListener('submit', click_function(forms[n]));
			}
		},
		init() {
			if (typeof flxbk_form_params === 'undefined') {
				return false;
			}
			window.flxbkForm.initForms();
		},
	};
	if ('loading' === document.readyState) {
		// The DOM has not yet been loaded.
		document.addEventListener('DOMContentLoaded', window.flxbkForm.init);
	} else {
		// The DOM has already been loaded.
		window.flxbkForm.init();
	}
})();
