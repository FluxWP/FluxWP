import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { offCanvasBlockIcon } from '@flx-blocks/icons';

/**
 * Import Css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

registerBlockType('flx/off-canvas', {
	...metadata,
	title: _x('Off Canvas', 'block title', 'flx-blocks'),
	description: _x('Container for off canvas content.', 'block description', 'flx-blocks'),
	icon: {
		src: offCanvasBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
