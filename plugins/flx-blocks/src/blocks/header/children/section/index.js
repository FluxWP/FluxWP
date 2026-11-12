import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { headerSectionBlockIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

registerBlockType('flx/header-section', {
	...metadata,
	title: _x('Header Section', 'block title', 'flx-blocks'),
	description: _x('Header Section.', 'block description', 'flx-blocks'),
	icon: {
		src: headerSectionBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
