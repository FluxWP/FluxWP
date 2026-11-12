/**
 * External dependencies
 */
import { fileInputIcon } from '@flx-blocks/icons';

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

registerBlockType('flx/advanced-form-file', {
	...metadata,
	title: __('File', 'flx-blocks'),
	description: __('Flux Form file upload', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: fileInputIcon,
	},
	edit,
	save: () => null,
});
