import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

const { name } = metadata;
import { testimonialSingleBlockIcon } from '@flx-blocks/icons';
import { __, _x } from '@wordpress/i18n';

export { metadata, name };

registerBlockType('flx/testimonial', {
	...metadata,
	title: _x('Testimonial', 'block title', 'flx-blocks'),
	keywords: [__('testimonial', 'flx-blocks'), __('rating', 'flx-blocks'), 'KB'],
	icon: {
		src: testimonialSingleBlockIcon,
	},
	edit,
	example: {},
});
