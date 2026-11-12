/**
 * BLOCK: Flux Icon
 */

import metadata from './block.json';

/**
 * Import Icon stuff
 */
import { advancedBtnIcon } from '@flx-blocks/icons';
import edit from './edit';
import deprecated from './deprecated';

/**
 * Internal block libraries
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('flx/singlebtn', {
	...metadata,
	title: __('Single Button', 'flx-blocks'),
	description: __('Single button within a button block', 'flx-blocks'),
	keywords: [__('Button', 'flx-blocks'), __('btn', 'flx-blocks'), 'KB'],
	icon: {
		src: advancedBtnIcon,
	},
	edit,
	save() {
		return null;
	},
	example: {
		attributes: {
			text: __('Click Me!', 'flx-blocks'),
		},
	},
	deprecated,
});
