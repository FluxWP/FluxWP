import { googleMapsIcon } from '@flx-blocks/icons';

import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import save from './save';
import transforms from './transforms';

registerBlockType('flx/googlemaps', {
	...metadata,
	title: _x('Google Maps', 'block title', 'flx-blocks'),
	description: __('Display google maps on your site.', 'flx-blocks'),
	keywords: [__('google', 'flx-blocks'), __('maps', 'flx-blocks'), 'flux'],
	icon: {
		src: googleMapsIcon,
	},
	getEditWrapperProps(attributes) {
		return {
			'data-align': attributes.align,
		};
	},
	transforms,
	edit,
	save,
	example: {},
});
