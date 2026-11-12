/**
 * External dependencies
 */
import { acceptInputIcon } from '@flx-blocks/icons';

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

// export { name, category, metadata, settings };
registerBlockType('flx/advanced-form-accept', {
	...metadata,
	title: __('Accept', 'flx-blocks'),
	description: __('Flux Form accept input', 'flx-blocks'),
	keywords: ['flxbk'],
	parent: ['flx/advanced-form'],
	icon: {
		src: acceptInputIcon,
	},
	edit,
	save: () => null,
});
