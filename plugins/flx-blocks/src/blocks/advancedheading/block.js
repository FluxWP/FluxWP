/**
 * BLOCK: Flux Advanced Heading
 *
 * Registering a block with Gutenberg.
 */

/**
 * Import Icons
 */
import { advancedHeadingIcon } from '@flx-blocks/icons';

/**
 * Import Css
 */
import './style.scss';
import edit from './edit';
import save from './save';
import metadata from './block.json';
import backwardCompatibility from './deprecated';
import { markConvertedHeading } from './converted-headings';

/**
 * Internal block libraries
 */
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('flx/advancedheading', {
	...metadata,
	title: _x('Text (Adv)', 'block title', 'flx-blocks'),
	description: __(
		'Create a heading or paragraph and define sizes for desktop, tablet and mobile along with font family, colors, etc.',
		'flx-blocks'
	),
	keywords: [__('text', 'flx-blocks'), __('advanced', 'flx-blocks'), __('heading', 'flx-blocks'), 'KB'],
	icon: {
		src: advancedHeadingIcon,
	},
	transforms: {
		from: [
			{
				type: 'block',
				blocks: ['core/paragraph'],
				transform: ({ content }) => {
					return createBlock('flx/advancedheading', {
						content,
						htmlTag: 'p',
					});
				},
			},
			{
				type: 'block',
				blocks: ['core/heading'],
				transform: ({ content, level }) => {
					const block = createBlock('flx/advancedheading', {
						content,
						level,
					});
					markConvertedHeading(block.clientId);

					return block;
				},
			},
		],
		to: [
			{
				type: 'block',
				blocks: ['core/paragraph'],
				transform: ({ content }) => {
					return createBlock('core/paragraph', {
						content,
					});
				},
			},
			{
				type: 'block',
				blocks: ['core/heading'],
				transform: ({ content, level }) => {
					return createBlock('core/heading', {
						content,
						level,
					});
				},
			},
		],
	},
	edit,
	save,
	deprecated: backwardCompatibility,
	example: {
		attributes: {
			content: sprintf(
				/* translators: %s: A highlighted word, which shows the heading's highlight styles. */
				__('Example %s', 'flx-blocks'),
				`<mark class="fxt-highlight">${__('Heading', 'flx-blocks')}</mark>`
			),
		},
	},
});
