import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { headerRowBlockIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/header-row', {
	...metadata,
	title: _x('Header Row', 'block title', 'flx-blocks'),
	description: _x('Header row.', 'block description', 'flx-blocks'),
	icon: {
		src: headerRowBlockIcon,
	},
	edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
