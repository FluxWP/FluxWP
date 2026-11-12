/**
 * External dependencies
 */
import { textareaInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-textarea', {
	...metadata,
	title: __('Text Area', 'flx-blocks'),
	description: __('Flux Form text input field', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: textareaInputIcon,
	},
	edit,
	save: () => null,
});
