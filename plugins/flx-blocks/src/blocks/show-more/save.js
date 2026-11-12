/**
 * BLOCK: Flux Show More Block
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * External dependencies
 */
import classnames from 'classnames';

function Save({ attributes, innerBlocks }) {
	const { uniqueID } = attributes;
	const classes = classnames({
		'fxb-block-show-more-container': true,
		[`fxb-block-show-more-container${uniqueID}`]: true,
		'wp-block-flx-show-more': true,
	});

	return (
		<div {...useBlockProps.save({ className: classes })}>
			<div className="fxb-show-more-sr-excerpt" aria-live="polite" aria-atomic="true"></div>
			<InnerBlocks.Content />
		</div>
	);
}
export default Save;
