/**
 * External dependencies
 */
import { selectInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-select', {
	...metadata,
	title: __('Select', 'flx-blocks'),
	/* translators: block description */
	description: __('Flux Form select input', 'flx-blocks'),
	keywords: ['flxbk'],
	parent: ['flx/advanced-form'],
	icon: {
		src: selectInputIcon,
	},
	edit,
	save: () => null,
});
