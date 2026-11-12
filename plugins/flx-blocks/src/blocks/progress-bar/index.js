import { progressIcon } from '@flx-blocks/icons';
import { registerBlockType } from '@wordpress/blocks';
import { _x, __ } from '@wordpress/i18n';

import './editor.scss';
import './style.scss';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

registerBlockType('flx/progress-bar', {
	...metadata,
	title: _x('Progress Bar', 'block title', 'flx-blocks'),
	description: _x('Flux progress bar', 'block description', 'flx-blocks'),
	keywords: [__('progress bar', 'flx-blocks'), 'flux'],
	icon: {
		src: progressIcon,
	},
	getEditWrapperProps(attributes) {
		if (
			'wide' === attributes.align ||
			'full' === attributes.align ||
			'left' === attributes.align ||
			'right' === attributes.align
		) {
			return {
				'data-align': attributes.align,
			};
		}
	},
	edit,
	save: () => null,
});
