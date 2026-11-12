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
import { useBlockProps } from '@wordpress/block-editor';
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
registerBlockType('flx/countdown-timer', {
	...metadata,
	title: __('Countdown Timer', 'flx-blocks'),
	description: __('The countdown timer', 'flx-blocks'),
	keywords: [__('countdown', 'flx-blocks'), __('timer', 'flx-blocks'), 'flux'],
	icon: countdownInnerIcon,
	edit,
	save: (props) => {
		const {
			attributes: { uniqueID, className },
		} = props;

		const blockProps = useBlockProps.save({
			className: `fxb-countdown-timer-${uniqueID} fxb-countdown-timer${className ? ' ' + className : ''}`,
		});

		return (
			<div {...blockProps}>
				<div className="fxb-countdown-item fxb-countdown-date-item">
					<span className="fxb-countdown-number">&nbsp;</span>
					<span className="fxb-countdown-label">&nbsp;</span>
				</div>
			</div>
		);
	},
});
