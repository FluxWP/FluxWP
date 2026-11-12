/**
 * External dependencies
 */
import { timeInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-time', {
	...metadata,
	title: __('Time', 'flx-blocks'),
	description: __('Flux Form time input field', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: timeInputIcon,
	},
	edit,
	save: () => null,
});
