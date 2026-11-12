import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { headerTabletBlockIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

registerBlockType('flx/header-tablet-container', {
	...metadata,
	title: _x('Header Tablet/Mobile', 'block title', 'flx-blocks'),
	description: _x('Header content for tablet and mobile devices.', 'block description', 'flx-blocks'),
	icon: {
		src: headerTabletBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
