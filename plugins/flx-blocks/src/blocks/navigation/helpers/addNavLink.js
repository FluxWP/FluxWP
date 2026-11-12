import { createBlock } from '@wordpress/blocks';

export default function addNavLink(parentClientId, index = 99999) {
	const navItem = createBlock('flx/navigation-link', {});

	wp.data.dispatch('core/block-editor').insertBlocks(navItem, index, parentClientId);
}
