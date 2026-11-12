/**
 * BLOCK: Flux Countdown
 */

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

function Save({ attributes }) {
	const {
		uniqueID,
		vsdesk,
		vstablet,
		vsmobile,
		timerLayout,
		countdownDivider,
		enableTimer,
		counterAlign,
		revealOnLoad,
		enablePauseButton,
		pauseButtonPosition,
	} = attributes;
	const classes = classnames({
		'fxb-countdown-container': true,
		[`fxb-countdown-container-${uniqueID}`]: uniqueID,
		[`fxb-countdown-timer-layout-${timerLayout}`]: enableTimer && timerLayout,
		'fxb-countdown-has-timer': enableTimer,
		'fxb-countdown-reveal-on-load': revealOnLoad,
		'fxb-countdown-enable-dividers': 'inline' !== timerLayout && countdownDivider && enableTimer,
		[`fxb-countdown-align-${counterAlign[0]}`]:
			undefined !== counterAlign && undefined !== counterAlign[0] && enableTimer ? counterAlign[0] : false,
		[`fxb-countdown-align-tablet-${counterAlign[1]}`]:
			undefined !== counterAlign && undefined !== counterAlign[1] && enableTimer ? counterAlign[1] : false,
		[`fxb-countdown-align-mobile-${counterAlign[2]}`]:
			undefined !== counterAlign && undefined !== counterAlign[2] && enableTimer ? counterAlign[2] : false,
		'fxvs-lg-false': vsdesk !== 'undefined' && vsdesk,
		'fxvs-md-false': vstablet !== 'undefined' && vstablet,
		'fxvs-sm-false': vsmobile !== 'undefined' && vsmobile,
	});

	const blockProps = useBlockProps.save({
		className: classes,
	});

	return (
		<div {...blockProps} data-id={uniqueID}>
			{enablePauseButton && (
				<button
					type="button"
					className={`fxb-countdown-pause-button fxb-countdown-pause-button-${pauseButtonPosition || 'top-right'}`}
					aria-label={__('Pause countdown timer', 'flx-blocks')}
					title={__('Pause countdown', 'flx-blocks')}
				>
					<span className="fxb-countdown-pause-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect x="6" y="4" width="4" height="16" rx="1" fill="currentColor" />
							<rect x="14" y="4" width="4" height="16" rx="1" fill="currentColor" />
						</svg>
					</span>
				</button>
			)}
			<InnerBlocks.Content />
		</div>
	);
}
export default Save;
