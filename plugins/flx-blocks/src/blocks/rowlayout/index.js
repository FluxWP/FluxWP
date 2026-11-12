/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';
/**
 * Import Icons
 */
import { blockRowIcon } from '@flx-blocks/icons';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Import Css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import save from './save';
import deprecated from './deprecated';

registerBlockType('flx/rowlayout', {
	...metadata,
	title: _x('Row Layout', 'block title', 'flx-blocks'),
	description: __('A container to hold a grid layout.', 'flx-blocks'),
	keywords: [__('column', 'flx-blocks'), __('row/layout', 'flx-blocks'), 'KB'],
	icon: {
		src: blockRowIcon,
	},
	edit,
	save,
	deprecated,
	example: {
		attributes: {
			colLayout: 'equal',
			columns: 2,
			customGutter: [0, 0, 0],
		},
		innerBlocks: [
			{
				name: 'flx/column',
				attributes: {
					background: '#DADADA',
					padding: [30, 20, 30, 20],
				},
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: {
							content: __('Column 1', 'flx-blocks'),
						},
					},
				],
			},
			{
				name: 'flx/column',
				attributes: {
					background: '#f5f5f5',
					padding: [30, 20, 30, 20],
				},
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: {
							content: __('Column 2', 'flx-blocks'),
						},
					},
				],
			},
		],
	},
});
