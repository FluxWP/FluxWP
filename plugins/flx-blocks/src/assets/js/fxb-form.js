jQuery(function ($) {
	// wc_single_product_params is required to continue.
	if (typeof flxbk_form_params === 'undefined') {
		return false;
	}
	var flxbk_form = {
		init() {
			$('form.fxb-form').on('submit', this.submit);
		},
		submit(e) {
			e.preventDefault();
			$('body').trigger('fxb-form-start-submit', $(this));
			const form = $(this),
				submitButton = form.find('input[type=submit]');
			form_data = flxbk_form.validateForm(form);
			if (form_data) {
				// send the request.
				form.parent('.wp-block-flx-form')
					.find('.flx-blocks-form-message')
					.slideUp('fast', function () {
						$(this).remove();
					});
				form.append(
					'<div class="fxb-form-loading"><div class="fxb-form-loading-spin"><div></div><div></div><div></div><div></div></div></div>'
				);
				submitButton.attr('disabled', 'disabled').addClass('button-primary-disabled');

				$.post(flxbk_form_params.ajaxurl, form_data, function (res) {
					if (res.success) {
						$('body').trigger('fxb-form-success', res);
						if (res.redirect) {
							window.location = res.redirect;
						} else {
							form.after(res.html);
							//focus
							// $( 'html, body' ).animate( {
							// 	scrollTop: $('.flx-blocks-form-message').offset().top - 100
							// }, 'fast' );
							if (form.find('.g-recaptcha').length > 0) {
								grecaptcha.reset();
							}
							flxbk_form.clearForm(form);
						}
					} else {
						if (form.find('.g-recaptcha').length > 0) {
							grecaptcha.reset();
						}
						form.after(res.data.html);
						if (res.data.required) {
							if (form.find('#' + res.data.required).length > 0) {
								flxbk_form.markError(form.find('#' + res.data.required), 'required');
							}
						}
						console.log(res.data.console);
						//console.log( res.data );

						submitButton.removeAttr('disabled');
					}
					submitButton.removeClass('button-primary-disabled');
					form.find('.fxb-form-loading').remove();
				});
			}
		},
		removeErrors(item) {
			$(item).parents('.fxb-form').removeClass('fxb-form-has-error');
			$(item).find('.has-error').removeClass('has-error');
			$('.fxb-form-error-msg').remove();
		},
		isValidEmail(email) {
			const pattern = new RegExp(
				/^[^\s@<>()\[\],;:\"]+@[^\s@<>()\[\],;:\".]+(?:\.[^\s@<>()\[\],;:\".]+)+$/i
			);
			return pattern.test(email);
		},
		removeErrorNotice(form) {
			$(form).find('.fxb-form-errors').remove();
		},
		markError(item, error_type) {
			let error_string = '';
			$(item).parents('.fxb-form').addClass('fxb-form-has-error');
			$(item).addClass('has-error');

			if (error_type) {
				error_string = $(item).data('label');

				if ('' === error_string || undefined === error_string) {
					error_string = flxbk_form_params.item;
				}
				switch (error_type) {
					case 'required':
						error_string = error_string + ' ' + flxbk_form_params[error_type];
						break;
					case 'mismatch':
						error_string = error_string + ' ' + flxbk_form_params[error_type];
						break;
					case 'validation':
						error_string = error_string + ' ' + flxbk_form_params[error_type];
						break;
				}
				$(item).siblings('.fxb-form-error-msg').remove();
				if ($(item).hasClass('fxb-checkbox-style')) {
					$(item)
						.parent('.flx-blocks-form-field')
						.append(
							'<div class="fxb-form-error-msg flx-blocks-form-warning" role="alert">' +
								error_string +
								'</div>'
						);
				} else {
					$(item).after(
						'<div class="fxb-form-error-msg flx-blocks-form-warning">' + error_string + '</div>'
					);
				}
			}

			$(item).focus();
		},
		/**
		 *
		 * @param form
		 * @param position (value = bottom or end) end if form is onepare, bottom, if form is multistep
		 */
		clearForm(form) {
			$(form)[0].reset();
		},
		/**
		 *
		 * @param form
		 * @param position (value = bottom or end) end if form is onepare, bottom, if form is multistep
		 */
		addErrorNotice(form) {
			$(form).after(
				'<div class="flx-blocks-form-message flx-blocks-form-warning">' +
					flxbk_form_params.error_message +
					'</div>'
			);
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
		validateForm(self) {
			let temp,
				error = false,
				error_type = '';

			// remove all initial errors if any
			flxbk_form.removeErrors(self);
			flxbk_form.removeErrorNotice(self);

			// ===== Validate: Text and Textarea ========
			const required = self.find('[data-required="yes"]:visible');

			required.each(function (i, item) {
				// console.log( $(item).data('type') );
				const data_type = $(item).data('type');
				val = '';

				switch (data_type) {
					case 'textarea':
					case 'text':
						val = $.trim($(item).val());

						if (val === '') {
							error = true;
							error_type = 'required';

							// make it warn collor
							flxbk_form.markError(item, error_type);
						}
						break;
					case 'tel':
						val = $.trim($(item).val());
						if (val === '') {
							error = true;
							error_type = 'required';

							// make it warn collor
							flxbk_form.markError(item, error_type);
						}
						break;
					case 'accept':
						if ($(item).prop('checked') == false) {
							error = true;
							error_type = 'required';

							// make it warn collor
							flxbk_form.markError(item, error_type);
						}
						break;

					case 'select':
						val = $(item).val();
						//console.log(val );
						if ($(item).prop('multiple')) {
							if (val === null || val.length === 0) {
								error = true;
								error_type = 'required';

								// make it warn collor
								flxbk_form.markError(item, error_type);
							}
						} else if (!val || val === '-1') {
							error = true;
							error_type = 'required';

							// make it warn collor
							flxbk_form.markError(item, error_type);
						}
						break;

					case 'radio':
						var length = $(item).find('input:checked').length;

						if (!length) {
							error = true;
							error_type = 'required';

							// make it warn collor
							flxbk_form.markError(item, error_type);
						}
						break;

					case 'checkbox':
						var length = $(item).find('input:checked').length;

						if (!length) {
							error = true;
							error_type = 'required';

							// make it warn collor
							flxbk_form.markError(item, error_type);
						}
						break;

					case 'email':
						var val = $(item).val();

						if (val !== '') {
							//run the validation
							if (!flxbk_form.isValidEmail(val)) {
								error = true;
								error_type = 'validation';

								flxbk_form.markError(item, error_type);
							}
						} else if (val === '') {
							error = true;
							error_type = 'required';

							flxbk_form.markError(item, error_type);
						}
						break;
					case 'url':
						var val = $(item).val();

						if (val !== '') {
							//run the validation
							if (!flxbk_form.isValidURL(val)) {
								error = true;
								error_type = 'validation';

								flxbk_form.markError(item, error_type);
							}
						}
						break;
				}
			});

			// if already some error found, bail out
			if (error) {
				// add error notice
				flxbk_form.addErrorNotice(self);

				return false;
			}
			let form_data = self.serialize();
			form_data = form_data + '&_fxb_form_verify=' + flxbk_form_params.nonce;
			return form_data;
		},
	};
	flxbk_form.init();
});
