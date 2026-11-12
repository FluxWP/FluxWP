/**
 * BLOCK: Flux Icon
 */

import metadata from './block.json';

/**
 * Import Icon stuff
 */
import { iconIcon } from '@flx-blocks/icons';
import edit from './edit';
import save from './save';
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
registerBlockType('flx/single-icon', {
	...metadata,
	title: __('Single Icon', 'flx-blocks'),
	description: __('Single icon within Icon block', 'flx-blocks'),
	keywords: [__('icon', 'flx-blocks'), __('svg', 'flx-blocks'), 'flux'],
	icon: {
		src: iconIcon,
	},
	getEditWrapperProps({ blockAlignment }) {
		if ('left' === blockAlignment || 'right' === blockAlignment || 'center' === blockAlignment) {
			return { 'data-align': blockAlignment };
		}
	},
	edit,
	save,
	deprecated,
	example: {},
});
