import { registerBlockType } from '@wordpress/blocks';

/**
 * Register sub blocks.
 */
import '../testimonial/block.js';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import save from './save';
import deprecated from './deprecated';

const { name } = metadata;
import { testimonialBlockIcon } from '@flx-blocks/icons';
import { __, _x } from '@wordpress/i18n';
import './style.scss';

export { metadata, name };

registerBlockType('flx/testimonials', {
	...metadata,
	title: _x('Testimonials', 'block title', 'flx-blocks'),
	keywords: [
		__('testimonials', 'flx-blocks'),
		__('rating', 'flx-blocks'),
		'flux',		__('carousel', 'flx-blocks'),
	],
	icon: {
		src: testimonialBlockIcon,
	},
	edit,
	save,
	deprecated,
	example: {},
});
