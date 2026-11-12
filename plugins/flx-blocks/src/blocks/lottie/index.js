import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import transforms from './transforms';
import deprecated from './deprecated';
import { lottieIcon } from '@flx-blocks/icons';
import { __, _x } from '@wordpress/i18n';

/**
 * Import Css
 */
import './style.scss';

registerBlockType('flx/lottie', {
	...metadata,
	title: _x('Lottie Animations', 'block title', 'flx-blocks'),
	description: _x('Display lottie animations on your site', 'block description', 'flx-blocks'),
	keywords: [__('lottie', 'flx-blocks'), __('animation', 'flx-blocks'), 'KB'],
	getEditWrapperProps(attributes) {
		return {
			'data-align': attributes.align,
		};
	},
	icon: {
		src: lottieIcon,
	},
	edit,
	deprecated,
	save: () => null,
	example: {},
});
