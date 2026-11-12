import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { headerColumnBlockIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

registerBlockType('flx/header-column', {
	...metadata,
	title: _x('Header Column', 'block title', 'flx-blocks'),
	description: _x('Header Column.', 'block description', 'flx-blocks'),
	icon: {
		src: headerColumnBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
