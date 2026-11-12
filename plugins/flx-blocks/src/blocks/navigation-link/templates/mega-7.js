/**
 * Template: Mega Menu 4 (Pro)
 * Post Type: flxbk_navigation
 */

import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
const postMeta = {};
function innerBlocks() {
	return [
		createBlock(
			'flx/rowlayout',
			{
				uniqueID: '376_6aa4e4-be',
				columns: 1,
				colLayout: 'equal',
				inheritMaxWidth: true,
				align: '',
				padding: ['sm', 'sm', 'sm', 'sm'],
				templateLock: false,
				fxbVersion: 2,
			},
			[
				createBlock(
					'flx/column',
					{ borderWidth: ['', '', '', ''], uniqueID: '376_288d85-16', fxbVersion: 2 },
					[
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '376_c6ffcd-d9',
								padding: ['0', '0', 'sm', '0'],
								columns: 3,
								colLayout: 'equal',
								fxbVersion: 2,
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
											'flx/advancedheading',
											{
												uniqueID: '376_791779-97',
												color: 'palette6',
												typography: 'var( --global-heading-font-family, inherit )',
												fontWeight: '600',
												colorClass: 'theme-palette6',
												htmlTag: 'div',
												content: __('Design', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '376_b3fe86-9e', templateKey: 'mega-7-nav-1', makePost: true },
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
											'flx/advancedheading',
											{
												uniqueID: '376_341e21-0d',
												color: 'palette6',
												typography: 'var( --global-heading-font-family, inherit )',
												fontWeight: '600',
												colorClass: 'theme-palette6',
												htmlTag: 'div',
												content: __('Development', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '376_e82bf6-37', templateKey: 'mega-7-nav-2', makePost: true },
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 3,
										borderWidth: ['', '', '', ''],
										uniqueID: '376_d8fdc6-ad',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_e89076-da',
												color: 'palette6',
												typography: 'var( --global-heading-font-family, inherit )',
												fontWeight: '600',
												colorClass: 'theme-palette6',
												htmlTag: 'div',
												content: __('SEO', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '376_0984bc-2f', templateKey: 'mega-7-nav-3', makePost: true },
											[]
										),
									]
								),
							]
						),
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '376_60f53b-5c',
								columns: 3,
								colLayout: 'equal',
								padding: ['sm', '0', '0', '0'],
								borderStyle: [
									{
										top: ['palette6', '', 1],
										right: ['', '', ''],
										bottom: ['', '', ''],
										left: ['', '', ''],
										unit: 'px',
									},
								],
								fxbVersion: 2,
							},
							[
								createBlock(
									'flx/column',
									{
										borderWidth: ['', '', '', ''],
										uniqueID: '376_77cba9-4f',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_ee8fd3-29',
												color: 'palette4',
												typography: 'var( --global-heading-font-family, inherit )',
												fontWeight: '600',
												colorClass: 'theme-palette4',
												htmlTag: 'div',
												content: __('Type A Short Headline', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_d2737b-cd',
												color: 'palette6',
												typography: 'var( --global-body-font-family, inherit )',
												fontWeight: 'inherit',
												colorClass: 'theme-palette6',
												htmlTag: 'p',
												fontSize: ['sm', '', ''],
												content: __(
													'Briefly and concisely explain what you do for your audience.',
													'flx-blocks'
												),
											},
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 2,
										borderWidth: ['', '', '', ''],
										uniqueID: '376_e57e9b-7b',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_98f682-bc',
												color: 'palette4',
												typography: 'var( --global-heading-font-family, inherit )',
												fontWeight: '600',
												colorClass: 'theme-palette4',
												htmlTag: 'div',
												content: __('Type A Short Headline', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_98f682-bc',
												color: 'palette6',
												typography: 'var( --global-body-font-family, inherit )',
												fontWeight: 'inherit',
												colorClass: 'theme-palette6',
												htmlTag: 'p',
												fontSize: ['sm', '', ''],
												content: __(
													'Briefly and concisely explain what you do for your audience.',
													'flx-blocks'
												),
											},
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 3,
										borderWidth: ['', '', '', ''],
										uniqueID: '376_c8658f-19',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_4b7431-19',
												color: 'palette4',
												typography: 'var( --global-heading-font-family, inherit )',
												fontWeight: '600',
												colorClass: 'theme-palette4',
												htmlTag: 'div',
												content: __('Type A Short Headline', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '376_4b7431-19',
												color: 'palette6',
												typography: 'var( --global-body-font-family, inherit )',
												fontWeight: 'inherit',
												colorClass: 'theme-palette6',
												htmlTag: 'p',
												fontSize: ['sm', '', ''],
												content: __(
													'Briefly and concisely explain what you do for your audience.',
													'flx-blocks'
												),
											},
											[]
										),
									]
								),
							]
						),
					]
				),
			]
		),
	];
}

export { postMeta, innerBlocks };
