/**
 * External dependencies
 */
import { numberInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-number', {
	...metadata,
	title: __('Number', 'flx-blocks'),
	description: __('Flux Form number input', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: numberInputIcon,
	},
	edit,
	save: () => null,
});
