/**
 * External dependencies
 */
import { emailInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-email', {
	...metadata,
	icon: {
		src: emailInputIcon,
	},
	title: __('Email Field', 'flx-blocks'),
	description: __('Flux Form email input field', 'flx-blocks'),
	keywords: ['flxbk'],
	edit,
	save: () => null,
});
