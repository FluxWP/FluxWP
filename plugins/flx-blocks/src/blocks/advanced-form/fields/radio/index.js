/**
 * External dependencies
 */
import { radioInputIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import transforms from './transforms';
import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';

registerBlockType('flx/advanced-form-radio', {
	...metadata,
	title: __('Radio', 'flx-blocks'),
	description: __('Flux Form radio input', 'flx-blocks'),
	keywords: ['flxbk'],
	icon: {
		src: radioInputIcon,
	},
	edit,
	transforms,
	save: () => null,
});
