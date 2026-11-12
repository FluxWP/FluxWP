/**
 * External dependencies
 */
import { checkboxInputIcon } from '@flx-blocks/icons';

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
import { Icon } from '@wordpress/components';

// export { name, category, metadata, settings };
registerBlockType('flx/advanced-form-checkbox', {
	...metadata,
	title: __('Checkbox', 'flx-blocks'),
	description: __('Flux Form checkbox', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: checkboxInputIcon,
	},
	edit,
	save: () => null,
});
