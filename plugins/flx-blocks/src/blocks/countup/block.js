/**
 * BLOCK: Flux Count Up
 */

/**
 * Import Icons
 */
import { countUpIcon } from '@flx-blocks/icons';

/**
 * Import edit
 */
import edit from './edit';

/**
 * Import save
 */
import save from './save';

/**
 * Import metadata
 */
import metadata from './block.json';

/**
 * Import deprecated
 */
import deprecated from './deprecated';
/**
 * Import Css
 */
import './editor.scss';

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
registerBlockType('flx/countup', {
	...metadata,
	title: _x('Count Up', 'block title', 'flx-blocks'),
	description: _x('An animated count up or down to a specific value.', 'block description', 'flx-blocks'),
	keywords: [
		__('count down', 'flx-blocks'),
		__('count up', 'flx-blocks'),
		__('counter', 'flx-blocks'),
		__('number', 'flx-blocks'),
		'KB',
	],
	icon: {
		src: countUpIcon,
	},
	edit,
	save,
	deprecated,
	example: {
		attributes: {
			title: __('My count up title', 'flx-blocks'),
		},
	},
});
