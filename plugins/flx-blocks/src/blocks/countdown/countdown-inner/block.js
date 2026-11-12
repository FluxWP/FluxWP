/**
 * BLOCK: Flux Countdown
 *
 * Registering a basic block with Gutenberg.
 */

/**
 * Import Icons
 */
import { countdownInnerIcon } from '@flx-blocks/icons';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Register: a Gutenberg Block.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('flx/countdown-inner', {
	...metadata,
	title: __('Countdown Content', 'flx-blocks'),
	description: __('Inner Container for Countdown Block', 'flx-blocks'),
	keywords: [__('countdown', 'flx-blocks'), __('timer', 'flx-blocks'), 'KB'],
	icon: countdownInnerIcon,
	edit,
	save({ attributes }) {
		const { location, uniqueID } = attributes;

		const blockProps = useBlockProps.save({
			className: `fxb-countdown-inner fxb-countdown-inner-${location} fxb-countdown-inner-${uniqueID}`,
		});

		return (
			<div {...blockProps}>
				<InnerBlocks.Content />
			</div>
		);
	},
});
