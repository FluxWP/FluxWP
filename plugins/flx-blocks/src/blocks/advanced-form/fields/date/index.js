/**
 * External dependencies
 */
import { dateInputIcon } from '@flx-blocks/icons';

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
registerBlockType('flx/advanced-form-date', {
	...metadata,
	title: __('Date', 'flx-blocks'),
	description: __('Flux Form date', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: dateInputIcon,
	},
	edit,
	save: () => null,
});
