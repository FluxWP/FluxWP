import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import { __, _x } from '@wordpress/i18n';
import { vectorBlockIcon } from '@flx-blocks/icons';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/vector', {
	...metadata,
	title: _x('Vector Graphic', 'block title', 'flx-blocks'),
	description: _x('Display SVGs on your site', 'block description', 'flx-blocks'),
	keywords: [__('svg', 'flx-blocks'), __('vector', 'flx-blocks'), 'KB'],
	icon: vectorBlockIcon,
	edit,
	save: () => null,
});
