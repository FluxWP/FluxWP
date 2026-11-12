/**
 * External dependencies
 */
import { textInputIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import transforms from './transforms';
import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

registerBlockType('flx/advanced-form-text', {
	...metadata,
	title: __('Text Field', 'flx-blocks'),
	description: __('Flux Form text input field', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: textInputIcon,
	},
	edit,
	transforms,
	save: () => null,
});
