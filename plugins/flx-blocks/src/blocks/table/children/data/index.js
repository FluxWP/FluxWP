import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import { tableDataBlockIcon } from '@flx-blocks/icons';
import { __, _x } from '@wordpress/i18n';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/table-data', {
	...metadata,
	title: _x('Table Data', 'block title', 'flx-blocks'),
	description: _x('Display tables on your site', 'block description', 'flx-blocks'),
	keywords: [__('table', 'flx-blocks'), __('structure', 'flx-blocks'), 'KB'],
	icon: {
		src: tableDataBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
	example: {},
});
