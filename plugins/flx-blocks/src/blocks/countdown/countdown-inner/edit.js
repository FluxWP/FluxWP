/**
 * BLOCK: Flux Tab
 *
 * Registering a basic block with Gutenberg.
 */

import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

import { useEffect } from '@wordpress/element';

import { useSelect, useDispatch } from '@wordpress/data';

import { uniqueIdHelper } from '@flx-blocks/helpers';

/**
 * Build the spacer edit
 */

function FlxbkCountdownInner(props) {
	const { attributes, clientId, setAttributes } = props;
	const { location, uniqueID } = attributes;

	uniqueIdHelper(props);

	const hasChildBlocks = wp.data.select('core/block-editor').getBlockOrder(clientId).length > 0;

	const blockProps = useBlockProps({
		className: `fxb-countdown-inner fxb-countdown-inner-${location} fxb-countdown-inner-${uniqueID}`,
	});

	return (
		<div {...blockProps}>
			<InnerBlocks
				templateLock={false}
				renderAppender={hasChildBlocks ? undefined : InnerBlocks.ButtonBlockAppender}
			/>
		</div>
	);
}
export default FlxbkCountdownInner;
