import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import { tableOfContentsIcon } from '@flx-blocks/icons';

/**
 * Import Css
 */
import './editor.scss';
import './style.scss';
import { __, _x } from '@wordpress/i18n';

registerBlockType('flx/tableofcontents', {
	...metadata,
	title: _x('Table of Contents', 'block title', 'flx-blocks'),
	keywords: [__('table of contents', 'flx-blocks'), __('summary', 'flx-blocks'), 'KB'],
	icon: {
		src: tableOfContentsIcon,
	},
	edit,
	save() {
		return null;
	},
	example: {},
});
