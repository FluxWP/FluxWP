jQuery(document).ready(function ($) {
	$('.fxk-panel-left .nav-tab-link').click(function (event) {
		event.preventDefault();
		const contain = $(this).closest('.fxk-panel-left');
		const panel = contain.find('.nav-tab-wrapper');
		const active = panel.find('.nav-tab-active');
		const opencontent = $(this).closest('.fxk-panel-contain').find('.nav-tab-content.panel_open');
		const contentid = $(this).data('tab-id');
		const tab = panel.find('a[data-tab-id="' + contentid + '"]');
		if (active.data('tab-id') == contentid) {
			//leave
		} else {
			tab.addClass('nav-tab-active');
			active.removeClass('nav-tab-active');
			opencontent.removeClass('panel_open');
			$('#' + contentid).addClass('panel_open');
		}

		return false;
	});
	$('.fxt-blocks-config-settings').on('click', function (e) {
		e.preventDefault();
		const selectedSlug = $(this).val();
		const selectedName = $(this).data('name');
		const $dialogContiner = $('#js-settings-modal-content');
		let settingsContent = '';
		if (fxt_blocks_params.blockConfigSettings[selectedSlug]) {
			for (const key in fxt_blocks_params.blockConfigSettings[selectedSlug]) {
				// skip loop if the property is from prototype
				if (!fxt_blocks_params.blockConfigSettings[selectedSlug].hasOwnProperty(key)) {
					continue;
				}
				const obj = fxt_blocks_params.blockConfigSettings[selectedSlug][key];
				const settingDefault =
					fxt_blocks_params.blockConfig[selectedSlug] &&
					typeof fxt_blocks_params.blockConfig[selectedSlug][key] !== 'undefined'
						? fxt_blocks_params.blockConfig[selectedSlug][key]
						: obj.default;
				switch (obj.type) {
					case 'boolean':
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'">' +
							obj.name +
							'</label><input id="' +
							key +
							'" class="fxt-block-config-input" name="' +
							key +
							'" type="checkbox" ' +
							(false === settingDefault ? '' : 'checked') +
							'></div>';
						break;
					case 'info':
						settingsContent += '<div class="fxt-modal-settings-field"><p>' + obj.name + '</p></div>';
						break;
					case 'number':
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'">' +
							obj.name +
							'</label><input id="' +
							key +
							'" class="fxt-block-config-input" name="' +
							key +
							'" type="number" step="' +
							obj.options.step +
							'" max="' +
							obj.options.max +
							'" min="' +
							obj.options.min +
							'" value="' +
							settingDefault +
							'"></div>';
						break;
					case 'typoNumber':
						if (obj.units && 'em' === fxt_blocks_params.blockConfig[selectedSlug][obj.units]) {
							settingsContent +=
								'<div class="fxt-modal-settings-field"><label for="' +
								key +
								'">' +
								obj.name +
								'</label><input id="' +
								key +
								'" class="fxt-block-config-input" name="' +
								key +
								'" type="number" step="0.1" max="12" min="0.1" value="' +
								settingDefault +
								'"></div>';
						} else {
							settingsContent +=
								'<div class="fxt-modal-settings-field"><label for="' +
								key +
								'">' +
								obj.name +
								'</label><input id="' +
								key +
								'" class="fxt-block-config-input" name="' +
								key +
								'" type="number" step="' +
								obj.options.step +
								'" max="' +
								obj.options.max +
								'" min="' +
								obj.options.min +
								'" value="' +
								settingDefault +
								'"></div>';
						}
						break;
					case 'select':
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'">' +
							obj.name +
							'</label><select id="' +
							key +
							'" class="fxt-block-config-input" name="' +
							key +
							'">';
						for (const option in obj.options) {
							if (!obj.options.hasOwnProperty(option)) {
								continue;
							}
							const selected = settingDefault == obj.options[option].value ? 'selected' : '';
							settingsContent +=
								'<option value="' +
								obj.options[option].value +
								'" ' +
								selected +
								'>' +
								obj.options[option].name +
								'</option>';
						}
						settingsContent += '</select></div>';
						break;
					case 'numberArray':
						settingsContent +=
							'<div class="fxt-modal-settings-number-array" data-array-key="' +
							key +
							'"><h4>' +
							obj.name +
							'</h4>';
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'[0]"><i class="dashicons dashicons-arrow-up-alt"></i></label><input id="' +
							key +
							'[0]" class="fxt-block-config-input-number-array" name="' +
							key +
							'[0]" data-array-key="' +
							key +
							'" type="number" step="' +
							obj.options.step +
							'" max="' +
							obj.options.max +
							'" min="' +
							obj.options.min +
							'" value="' +
							(settingDefault ? settingDefault[0] : '') +
							'"></div>';
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'[1]"><i class="dashicons dashicons-arrow-right-alt"></i></label><input id="' +
							key +
							'[1]" class="fxt-block-config-input-number-array" name="' +
							key +
							'[1]" data-array-key="' +
							key +
							'" type="number" step="' +
							obj.options.step +
							'" max="' +
							obj.options.max +
							'" min="' +
							obj.options.min +
							'" value="' +
							(settingDefault ? settingDefault[1] : '') +
							'"></div>';
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'[2]"><i class="dashicons dashicons-arrow-down-alt"></i></label><input id="' +
							key +
							'[2]" class="fxt-block-config-input-number-array" name="' +
							key +
							'[2]" data-array-key="' +
							key +
							'" type="number" step="' +
							obj.options.step +
							'" max="' +
							obj.options.max +
							'" min="' +
							obj.options.min +
							'" value="' +
							(settingDefault ? settingDefault[2] : '') +
							'"></div>';
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'[3]"><i class="dashicons dashicons-arrow-left-alt"></i></label><input id="' +
							key +
							'[3]" class="fxt-block-config-input-number-array" name="' +
							key +
							'[3]" data-array-key="' +
							key +
							'" type="number" step="' +
							obj.options.step +
							'" max="' +
							obj.options.max +
							'" min="' +
							obj.options.min +
							'" value="' +
							(settingDefault ? settingDefault[3] : '') +
							'"></div>';
						settingsContent += '</div>';
						break;
					case 'color':
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'">' +
							obj.name +
							'</label><input class="fxt-block-config-input fxt-init-color" id="' +
							key +
							'" name="' +
							key +
							'" type="text" value="' +
							settingDefault +
							'"></div>';
						break;
					case 'array':
						settingsContent += '<div class="fxt-modal-settings-array"><h4>' + obj.name + '</h4>';
						for (const option in obj.options) {
							if (!obj.options.hasOwnProperty(option)) {
								continue;
							}
							const inner = fxt_blocks_params.blockConfigSettings[selectedSlug][key].options[option];
							switch (inner.type) {
								case 'number':
									settingsContent +=
										'<div class="fxt-modal-settings-field"><label for="' +
										option +
										'">' +
										inner.name +
										'</label><input id="' +
										option +
										'" class="fxt-block-config-input-array" data-array-key="' +
										key +
										'" name="' +
										option +
										'" type="number" step="' +
										inner.options.step +
										'" max="' +
										inner.options.max +
										'" min="' +
										inner.options.min +
										'" value="' +
										settingDefault[0][option] +
										'"></div>';
									break;
								case 'color':
									settingsContent +=
										'<div class="fxt-modal-settings-field"><label for="' +
										option +
										'">' +
										inner.name +
										'</label><input class="fxt-block-config-input-array fxt-init-color" data-array-key="' +
										key +
										'" id="' +
										option +
										'" name="' +
										option +
										'" type="text" value="' +
										settingDefault[0][option] +
										'"></div>';
									break;
								default:
									settingsContent +=
										'<div class="fxt-modal-settings-field"><label for="' +
										option +
										'">' +
										inner.name +
										'</label><input class="fxt-block-config-input-array" data-array-key="' +
										key +
										'" id="' +
										option +
										'" name="' +
										option +
										'" type="text" value="' +
										settingDefault[0][option] +
										'"></div>';
							}
						}
						settingsContent += '</div>';
						break;
					default:
						settingsContent +=
							'<div class="fxt-modal-settings-field"><label for="' +
							key +
							'">' +
							obj.name +
							'</label><input class="fxt-block-config-input" id="' +
							key +
							'" name="' +
							key +
							'" type="text" value="' +
							settingDefault +
							'"></div>';
				}
			}
		} else {
			settingsContent += '<div class="fxt-modal-settings-content">No Settings, Yet. Try again Soon!</div>';
		}
		const dialogOptions = $.extend({
			dialogClass: 'wp-dialog',
			resizable: false,
			height: 'auto',
			modal: true,
			width: 400,
			buttons: [
				{
					text: fxt_blocks_params.texts.close,
					click() {
						$(this).dialog('close');
					},
				},
				{
					text: fxt_blocks_params.texts.save,
					class: 'button  button-primary fxt-modal-save-button',
					click(event) {
						const $button = $(event.currentTarget);
						/**
						 * Keep button from running twice
						 */
						if ($button.hasClass('updating-message') || $button.hasClass('button-disabled')) {
							return;
						}
						const dataConfig = {};
						dataConfig[selectedSlug] = {};
						$('#js-settings-modal-content .fxt-block-config-input').each(function () {
							const attribute = $(this).attr('name');
							let value = $(this).val().trim();
							if ($(this).attr('type') === 'checkbox') {
								if (!this.checked) {
									value = false;
								} else {
									value = true;
								}
							}
							if (value !== '') {
								dataConfig[selectedSlug][attribute] = value;
							}
						});
						$('#js-settings-modal-content .fxt-block-config-input-array').each(function () {
							const arrayAttribute = $(this).attr('name');
							const arrayKey = $(this).attr('data-array-key');
							let value = $(this).val().trim();
							if ($(this).attr('type') === 'checkbox') {
								if (!this.checked) {
									value = false;
								} else {
									value = true;
								}
							}
							if (value !== '') {
								if (dataConfig[selectedSlug][arrayKey] !== undefined) {
									dataConfig[selectedSlug][arrayKey][0][arrayAttribute] = value;
								} else {
									dataConfig[selectedSlug][arrayKey] = {};
									dataConfig[selectedSlug][arrayKey][0] = {};
									dataConfig[selectedSlug][arrayKey][0][arrayAttribute] = value;
								}
							}
						});
						$('#js-settings-modal-content .fxt-modal-settings-number-array').each(function () {
							const numberValue = [];
							const arrayKey = $(this).attr('data-array-key');
							$(this)
								.find('.fxt-block-config-input-number-array')
								.each(function () {
									const value = $(this).val().trim();
									if (value !== '') {
										numberValue.push(value);
									}
								});
							if (Array.isArray(numberValue) && numberValue.length && 4 === numberValue.length) {
								if (dataConfig[selectedSlug][arrayKey] !== undefined) {
									dataConfig[selectedSlug][arrayKey] = numberValue;
								} else {
									dataConfig[selectedSlug][arrayKey] = {};
									dataConfig[selectedSlug][arrayKey] = numberValue;
								}
							}
						});

						/**
						 * Change button status to in-progress
						 * @param {string} message - the text output.
						 */
						function buttonStatusInProgress(message) {
							$button.addClass('updating-message').removeClass('button-disabled').text(message);
						}

						/**
						 * Change button status to disabled
						 * @param {string} message - the text output.
						 */
						function buttonStatusDisabled(message) {
							$button.removeClass('updating-message').addClass('button-disabled').text(message);
						}
						/**
						 * Change button status to fxt-block-active
						 * @param {string} message - the text output.
						 */
						function buttonStatusSucess(message) {
							$button.removeClass('updating-message').addClass('button-disabled').text(message);
							setTimeout(function () {
								location.reload();
							}, 500);
						}
						/**
						 * Save Block Settings
						 * @param {string} selectedSlug - the block slug.
						 * @param {array} data - the data to save.
						 */
						function saveBlockConfig() {
							buttonStatusInProgress(fxt_blocks_params.texts.updating);
							jQuery.ajax({
								type: 'POST',
								url: fxt_blocks_params.ajaxurl,
								data: {
									action: 'flxbk_save_config',
									wpnonce: fxt_blocks_params.wpnonce,
									fxt_block: selectedSlug,
									config: dataConfig,
								},
								success() {
									buttonStatusSucess(fxt_blocks_params.texts.updated);
								},
								error(jqxhr, textStatus, error) {
									console.log(error);
									buttonStatusDisabled('Error');
								},
							});
						}
						saveBlockConfig();
					},
				},
			],
		});
		$dialogContiner.prop('title', fxt_blocks_params.texts.config + ' ' + selectedName);
		$dialogContiner.html(
			'<p class="fxt-modal-item-title">' +
				selectedName +
				' ' +
				fxt_blocks_params.texts.settings +
				'</p>' +
				settingsContent
		);
		$dialogContiner.dialog(dialogOptions);
		$('.fxt-init-color').wpColorPicker();
	});
});
/**
 * Ajax activate, deactivate blocks
 *
 */
(function ($, window, document, undefined) {
	'use strict';

	$(function () {
		$('.fxt_block_button').on('click', function (event) {
			const $button = $(event.target);
			event.preventDefault();
			/**
			 * Keep button from running twice
			 */
			if ($button.hasClass('updating-message') || $button.hasClass('button-disabled')) {
				return;
			}

			function ajaxCallback(response) {
				if (typeof response.success != 'undefined' && response.success) {
					if ($button.hasClass('fxt-block-active')) {
						buttonStatusToggled($button.data('deactivated-label'));
					} else {
						buttonStatusToggled($button.data('activated-label'));
					}
				} else {
					// error processing this block
					buttonStatusDisabled('Error');
				}
			}
			/**
			 * Toggle Block
			 */
			function toggleBlock() {
				if ($button.hasClass('fxt-block-active')) {
					buttonStatusInProgress($button.data('deactivating-label'));
				} else {
					buttonStatusInProgress($button.data('activating-label'));
				}
				jQuery.post(
					fxt_blocks_params.ajaxurl,
					{
						action: 'flxbk_activate_deactivate',
						wpnonce: fxt_blocks_params.wpnonce,
						fxt_block: $button.data('block-slug'),
					},
					ajaxCallback
				);
			}

			/**
			 * Change button status to in-progress
			 * @param {string} message - the text output.
			 */
			function buttonStatusInProgress(message) {
				$button.addClass('updating-message').removeClass('button-disabled').text(message);
			}

			/**
			 * Change button status to disabled
			 * @param {string} message - the text output.
			 */
			function buttonStatusDisabled(message) {
				$button.removeClass('updating-message').addClass('button-disabled').text(message);
			}

			/**
			 * Change button status to fxt-block-active
			 * @param {string} message - the text output.
			 */
			function buttonStatusToggled(message) {
				if ($button.hasClass('fxt-block-active')) {
					$button.removeClass('updating-message fxt-block-active').addClass('button-disabled').text(message);
					$button.closest('.fxt_plugin_box').addClass('fxt-block-inactive').removeClass('fxt-block-active');
					setTimeout(function () {
						$button
							.addClass('fxt-block-inactive')
							.removeClass('button-disabled')
							.text($button.data('inactive-label'));
					}, 2000);
				} else {
					$button.removeClass('updating-message fxt-block-inactive').addClass('button-disabled').text(message);
					$button.closest('.fxt_plugin_box').addClass('fxt-block-active').removeClass('fxt-block-inactive');
					setTimeout(function () {
						$button
							.addClass('fxt-block-active')
							.removeClass('button-disabled')
							.text($button.data('active-label'));
					}, 2000);
				}
			}

			toggleBlock();
		});
	});
})(jQuery, window, document);
