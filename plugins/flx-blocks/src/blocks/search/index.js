import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { __, _x } from '@wordpress/i18n';

import { searchBlockIcon } from '@flx-blocks/icons';

import edit from './edit';
import metadata from './block.json';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/search', {
	...metadata,
	title: _x('Search (Adv)', 'block title', 'flx-blocks'),
	description: _x('Flux search block', 'block description', 'flx-blocks'),
	keywords: [__('search', 'flx-blocks'), __('find', 'flx-blocks'), 'flux'],
	icon: {
		src: searchBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
	example: {
		attributes: {},
	},
});
