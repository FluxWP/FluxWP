/**
 * BLOCK: Flux Info Box
 */

/**
 * Import Icons
 */
import { infoboxIcon } from '@flx-blocks/icons';
import metadata from './block.json';
import deprecated from './deprecated.js';
import save from './save';

import { IconRender, IconSpanTag } from '@flx-blocks/components';

/**
 * Import edit
 */
import edit from './edit';
/**
 * Import Css
 */
import './style.scss';

/**
 * Internal block libraries
 */
import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('flx/infobox', {
	...metadata,
	title: _x('Info Box', 'block title', 'flx-blocks'),
	description: _x('Create beautiful information boxes using icons or images.', 'block description', 'flx-blocks'),
	keywords: [__('icon', 'flx-blocks'), __('info', 'flx-blocks'), 'KB'],
	icon: {
		src: infoboxIcon,
	},
	deprecated,
	edit,
	save,
	example: {},
});
