import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

import { offCanvasTriggerBlockIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

registerBlockType('flx/off-canvas-trigger', {
	...metadata,
	title: _x('Off Canvas Trigger', 'block title', 'flx-blocks'),
	description: _x('Toggles the display of an off canvas block.', 'block description', 'flx-blocks'),
	icon: {
		src: offCanvasTriggerBlockIcon,
	},
	edit,
	save() {
		return null;
	},
});
