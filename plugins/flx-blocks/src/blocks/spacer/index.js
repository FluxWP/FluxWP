import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import save from './save';
import transforms from './transforms';
import deprecated from './deprecated';
const { name } = metadata;
import { spacerIcon } from '@flx-blocks/icons';
import { __, _x } from '@wordpress/i18n';

/**
 * Import Css
 */
import './style.scss';

export { metadata, name };

registerBlockType('flx/spacer', {
	...metadata,
	title: _x('Spacer / Divider', 'block title', 'flx-blocks'),
	keywords: [
		__('spacer', 'flx-blocks'),
		__('divider', 'flx-blocks'),
		__('separator', 'flx-blocks'),
		'KB',
	],
	icon: {
		src: spacerIcon,
	},
	transforms,
	deprecated,
	edit,
	save,
	example: {
		attributes: {
			spacerHeight: 4,
			dividerColor: '#2B6CB0',
		},
	},
});
