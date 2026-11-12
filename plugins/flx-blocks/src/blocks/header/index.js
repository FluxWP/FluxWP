import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { headerBlockIcon } from '@flx-blocks/icons';

/**
 * Import Css
 */
import './style.scss';

/**
 * Import child blocks
 */
import './children/container-desktop';
import './children/container-tablet';
import './children/row';
import './children/column';
import './children/section';
import './children/off-canvas';
import './children/off-canvas-trigger';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

registerBlockType('flx/header', {
	...metadata,
	title: _x('Header (Adv)', 'block title', 'flx-blocks'),
	description: _x('Create an advanced header or footer for your website.', 'block description', 'flx-blocks'),
	keywords: [__('header', 'flx-blocks'), __('footer', 'flx-blocks'), 'flux'],
	icon: {
		src: headerBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
