/**
 * Template: Features with icon
 * Post Type: flxbk_navigation
 */

import { createBlock } from '@wordpress/blocks';

const postMeta = {
	_flxbk_navigation_orientation: 'vertical',
};

function innerBlocks(uniqueID, getStash) {
	const existingSubMenuInnerBlocks = getStash(uniqueID);
	return [createBlock('flx/navigation', {}, existingSubMenuInnerBlocks)];
}

export { postMeta, innerBlocks };
