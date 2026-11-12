import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { __, _x } from '@wordpress/i18n';

import { logoBlockIcon } from '@flx-blocks/icons';

import edit from './edit';
import metadata from './block.json';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/identity', {
	...metadata,
	title: _x('Site Identity', 'block title', 'flx-blocks'),
	description: _x('Flux site identity block', 'block description', 'flx-blocks'),
	keywords: [__('logo', 'flx-blocks'), __('identity', 'flx-blocks'), 'flux'],
	icon: {
		src: logoBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
	example: {
		attributes: {
			showSiteTagline: true,
		},
	},
});
