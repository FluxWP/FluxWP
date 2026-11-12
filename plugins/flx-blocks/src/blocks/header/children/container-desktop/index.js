import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { headerDesktopBlockIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

registerBlockType('flx/header-desktop-container', {
	...metadata,
	title: _x('Header Desktop', 'block title', 'flx-blocks'),
	description: _x('Header content for desktop.', 'block description', 'flx-blocks'),
	icon: {
		src: headerDesktopBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
