/**
 * External dependencies
 */
import { hiddenInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-hidden', {
	...metadata,
	title: __('Hidden Field', 'flx-blocks'),
	description: __('Flux Form hidden field', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: hiddenInputIcon,
	},
	edit,
	save: () => null,
});
