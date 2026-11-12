/**
 * External dependencies
 */
import { advancedBtnIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-submit', {
	...metadata,
	title: __('Submit Button', 'flx-blocks'),
	description: __('Flux Form Submit Button', 'flx-blocks'),
	keywords: ['submit', 'button', 'send'],
	ancestor: ['flx/advanced-form'],
	parent: ['flx/advanced-form', 'flx/column'],
	icon: {
		src: advancedBtnIcon,
	},
	edit,
	save: () => null,
});
