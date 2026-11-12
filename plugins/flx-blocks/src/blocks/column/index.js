/**
 * Import Icons
 */
import { blockColumnIcon } from '@flx-blocks/icons';

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
import { __, _x } from '@wordpress/i18n';

registerBlockType('flx/column', {
	...metadata,
	title: _x('Section', 'block title', 'flx-blocks'),
	description: __('A container to style a section of content.', 'flx-blocks'),
	keywords: [__('column', 'flx-blocks'), __('section', 'flx-blocks'), 'KB'],
	icon: {
		src: blockColumnIcon,
	},
	edit,
	save,
	deprecated,
	example: {
		attributes: {
			background: '#DADADA',
			padding: [30, 20, 30, 20],
		},
		innerBlocks: [
			{
				name: 'core/paragraph',
				attributes: {
					content: __('Section content', 'flx-blocks'),
				},
			},
		],
	},
});
