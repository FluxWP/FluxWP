/**
 * Template: Mega Menu 2 (Pro)
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
				uniqueID: '372_bebd31-3e',
				columns: 1,
				collapseGutter: 'none',
				customRowGutter: [0, '', ''],
				colLayout: 'equal',
				templateLock: false,
				fxbVersion: 2,
				inheritMaxWidth: true,
				align: '',
				padding: ['0', '0', '0', '0'],
			},
			[
				createBlock(
					'flx/column',
					{
						borderWidth: ['', '', '', ''],
						uniqueID: '372_53b50a-51',
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '372_574ce0-88',
								columns: 3,
								columnGutter: 'none',
								customGutter: [0, '', ''],
								colLayout: 'equal',
								padding: ['0', '0', '0', '0'],
								fxbVersion: 2,
							},
							[
								createBlock(
									'flx/column',
									{
										borderWidth: ['', '', '', ''],
										uniqueID: '372_bd8024-99',
										padding: ['md', 'md', 'md', 'md'],
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '372_d0a5ca-4c',
												color: 'palette5',
												fontWeight: '500',
												margin: ['', '', 'md', ''],
												markBorder: '',
												markBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												tabletMarkBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												mobileMarkBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												colorClass: 'theme-palette5',
												htmlTag: 'div',
												fontSize: [16, '', ''],
												fontHeight: [18, '', ''],
												fontHeightType: 'px',
												content: __('Ecosystem', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '372_a6b73f-9a', templateKey: 'mega-4-nav-1', makePost: true },
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 2,
										borderWidth: ['', '', '', ''],
										uniqueID: '372_03407d-4c',
										padding: ['md', 'md', 'md', 'md'],
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '372_f3afba-97',
												color: 'palette5',
												fontWeight: '500',
												margin: ['', '', 'md', ''],
												markBorder: '',
												markBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												tabletMarkBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												mobileMarkBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												colorClass: 'theme-palette5',
												htmlTag: 'div',
												fontSize: [16, '', ''],
												fontHeight: [18, '', ''],
												fontHeightType: 'px',
												content: __('Main Features', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '372_df7b1f-d2', templateKey: 'mega-4-nav-2', makePost: true },
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 3,
										borderWidth: ['', '', '', ''],
										uniqueID: '372_8735ac-21',
										verticalAlignment: '',
										padding: ['md', 'md', 'md', 'md'],
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '372_f3afba-97',
												color: 'palette5',
												fontWeight: '500',
												margin: ['', '', 'md', ''],
												markBorder: '',
												markBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												tabletMarkBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												mobileMarkBorderStyles: [
													{
														top: [null, '', ''],
														right: [null, '', ''],
														bottom: [null, '', ''],
														left: [null, '', ''],
														unit: 'px',
													},
												],
												colorClass: 'theme-palette5',
												htmlTag: 'div',
												fontSize: [16, '', ''],
												fontHeight: [18, '', ''],
												fontHeightType: 'px',
												content: __('Company', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '372_22cb6a-fa', templateKey: 'mega-4-nav-3', makePost: true },
											[]
										),
									]
								),
							]
						),
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '372_328d2c-6c',
								columns: 1,
								padding: ['0', '0', '0', '0'],
								colLayout: 'equal',
								fxbVersion: 2,
							},
							[
								createBlock(
									'flx/column',
									{
										borderWidth: ['', '', '', ''],
										uniqueID: '372_c34df0-2c',
										direction: ['horizontal', '', ''],
										justifyContent: [null, '', ''],
										gutter: [16, '', ''],
										gutterVariable: ['sm', '', ''],
										flexBasis: [0, '', ''],
										borderStyle: [
											{
												top: ['palette7', '', 2],
												right: ['palette7', '', 0],
												bottom: ['palette7', '', 0],
												left: ['palette7', '', 0],
												unit: 'px',
											},
										],
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/navigation',
											{ uniqueID: '372_632e41-d5', templateKey: 'mega-4-nav-4', makePost: true },
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
