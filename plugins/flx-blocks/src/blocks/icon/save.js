/**
 * BLOCK: Flux Spacer
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
/**
 * External dependencies
 */
import { IconSpanTag } from '@flx-blocks/components';
/**
 * External dependencies
 */
import classnames from 'classnames';
import { times } from 'lodash';

function Save({ attributes, className }) {
	const { blockAlignment, uniqueID, verticalAlignment } = attributes;

	const classes = classnames({
		'fxt-svg-icons': true,
		[`fxt-svg-icons${uniqueID}`]: uniqueID,
		[`align${blockAlignment ? blockAlignment : 'none'}`]: true,
		[`fxb-icon-valign-${verticalAlignment}`]: verticalAlignment,
	});

	const blockProps = useBlockProps.save({
		className: classes,
	});

	return (
		<div {...blockProps}>
			<InnerBlocks.Content />
		</div>
	);
}

export default Save;
