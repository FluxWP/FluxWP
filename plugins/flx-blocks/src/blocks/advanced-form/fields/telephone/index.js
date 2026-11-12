/**
 * External dependencies
 */
import { phoneInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-telephone', {
	...metadata,
	title: __('Telephone Field', 'flx-blocks'),
	/* translators: block description */
	description: __('Flux Form telephone input field', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: phoneInputIcon,
	},
	edit,
	save: () => null,
});
