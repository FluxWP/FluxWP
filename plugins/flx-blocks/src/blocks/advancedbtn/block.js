/**
 * BLOCK: Flux Advanced Btn
 */
/**
 * Import Icons
 */
import { advancedBtnIcon } from '@flx-blocks/icons';

/**
 * Register sub blocks.
 */
import '../singlebtn/block.js';

/**
 * Import Css
 */
import './style.scss';

import edit from './edit';
import deprecated from './deprecated';
import save from './save';
import metadata from './block.json';
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
registerBlockType('flx/advancedbtn', {
	...metadata,
	title: _x('Buttons (Adv)', 'block title', 'flx-blocks'),
	description: __(
		'Create an advanced button or a row of buttons. Style each one, including hover controls',
		'flx-blocks'
	),
	keywords: [__('button', 'flx-blocks'), __('icon', 'flx-blocks'), 'KB'],
	icon: {
		src: advancedBtnIcon,
	},
	edit,
	save,
	deprecated,
	example: {
		innerBlocks: [
			{
				name: 'flx/singlebtn',
				attributes: {
					text: __('Click Me!', 'flx-blocks'),
				},
			},
		],
	},
});
