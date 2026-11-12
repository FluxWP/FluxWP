/**
 * Template: Mega Menu 1
 * Post Type: flxbk_navigation
 */

import { createBlock } from '@wordpress/blocks';

const postMeta = {};

function innerBlocks() {
	return [
		createBlock(
			'flx/rowlayout',
			{
				uniqueID: '252_56a2cc-f8',
				columns: 1,
				colLayout: 'equal',
				templateLock: false,
				inheritMaxWidth: true,
				fxbVersion: 2,
				padding: ['sm', 'sm', 'sm', 'sm'],
			},
			[]
		),
	];
}

export { postMeta, innerBlocks };
