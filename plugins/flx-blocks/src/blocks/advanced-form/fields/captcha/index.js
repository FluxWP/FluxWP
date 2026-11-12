/**
 * External dependencies
 */
import { captchaIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

registerBlockType('flx/advanced-form-captcha', {
	...metadata,
	title: __('Captcha', 'flx-blocks'),
	description: __('Flux Form Captcha Block', 'flx-blocks'),
	keywords: ['captcha', 'verify', 'bot'],
	ancestor: ['flx/advanced-form'],
	parent: ['flx/advanced-form', 'flx/column'],
	icon: {
		src: captchaIcon,
	},
	edit,
	save: () => null,
});
