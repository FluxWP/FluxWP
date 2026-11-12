/**
 * Template: Simple Menu 2
 * Post Type: flxbk_navigation
 */

import { createBlock } from '@wordpress/blocks';

const postMeta = {};

function innerBlocks() {
	return [
		createBlock(
			'flx/rowlayout',
			{
				uniqueID: '252_56a4rc-f8',
				columns: 2,
				colLayout: 'equal',
				templateLock: false,
				fxbVersion: 2,
				inheritMaxWidth: true,
				padding: ['sm', 'sm', 'sm', 'sm'],
			},
			[
				createBlock(
					'flx/column',
					{
						borderWidth: ['', '', '', ''],
						uniqueID: '376_4e3485-59',
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/navigation',
							{ uniqueID: '376_b3fe86-9e', templateKey: 'mega-simple-nav-1', makePost: true },
							[]
						),
					]
				),
				createBlock(
					'flx/column',
					{
						id: 2,
						borderWidth: ['', '', '', ''],
						uniqueID: '376_9a7926-1b',
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/navigation',
							{ uniqueID: '376_e82bf6-37', templateKey: 'mega-simple-nav-2', makePost: true },
							[]
						),
					]
				),
			]
		),
	];
}

export { postMeta, innerBlocks };
