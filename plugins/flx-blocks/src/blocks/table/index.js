import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import edit from './edit';
import transforms from './transforms';
import metadata from './block.json';
import { tableBlockIcon } from '@flx-blocks/icons';
import { __, _x } from '@wordpress/i18n';

import './children/row/index';
import './children/data/index';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/table', {
	...metadata,
	title: _x('Table (Adv)', 'block title', 'flx-blocks'),
	description: _x('Display tables on your site', 'block description', 'flx-blocks'),
	keywords: [__('table', 'flx-blocks'), __('structure', 'flx-blocks'), 'KB'],
	icon: {
		src: tableBlockIcon,
	},
	edit,
	transforms,
	save: () => {
		return <InnerBlocks.Content />;
	},
	example: {},
});
