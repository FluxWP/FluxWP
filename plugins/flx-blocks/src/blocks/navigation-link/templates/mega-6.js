/**
 * Template: Mega Menu 3 (pro)
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
				uniqueID: '374_23f870-45',
				colLayout: 'left-golden',
				bgColor: 'palette8',
				bgColorClass: 'theme-palette8',
				borderStyle: [
					{
						top: ['palette7', '', 1],
						right: ['palette7', '', 1],
						bottom: ['palette7', '', 1],
						left: ['palette7', '', 1],
						unit: 'px',
					},
				],
				templateLock: false,
				inheritMaxWidth: true,
				align: '',
				padding: ['sm', 'sm', 'sm', 'sm'],
				fxbVersion: 2,
			},
			[
				createBlock(
					'flx/column',
					{ borderWidth: ['', '', '', ''], uniqueID: '374_1b0c9b-d6', fxbVersion: 2 },
					[
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '374_2bad32-d4',
								columns: 1,
								customGutter: [32, '', ''],
								colLayout: 'equal',
								columnsInnerHeight: true,
								padding: ['', '', '', ''],
								fxbVersion: 2,
							},
							[
								createBlock(
									'flx/column',
									{
										background: 'palette9',
										borderWidth: ['', '', '', ''],
										borderRadius: [10, 10, 10, 10],
										uniqueID: '374_874bd3-28',
										padding: ['xs', 'xs', 'xs', 'xs'],
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '374_17037f-39',
												color: 'palette4',
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
												colorClass: 'theme-palette4',
												htmlTag: 'div',
												fontSize: [28, '', ''],
												content: __('Products', 'flx-blocks'),
											},
											[]
										),
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '374_72fe12-e2',
												color: 'palette6',
												markBorder: '',
												margin: ['0', '', 'sm', ''],
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
												colorClass: 'theme-palette6',
												htmlTag: 'p',
												fontSize: ['sm', '', ''],
												content: __(
													'Use this space to add a short description.',
													'flx-blocks'
												),
											},
											[]
										),
										createBlock(
											'flx/navigation',
											{ uniqueID: '374_016a69-aa', templateKey: 'mega-6-nav-1', makePost: true },
											[]
										),
									]
								),
							]
						),
					]
				),
				createBlock(
					'flx/column',
					{ id: 2, borderWidth: ['', '', '', ''], uniqueID: '374_142e27-de', fxbVersion: 2 },
					[
						createBlock(
							'flx/image',
							{
								sizeSlug: 'large',
								ratio: 'land21',
								useRatio: true,
								linkDestination: 'none',
								uniqueID: '374_b422c1-22',
								borderRadius: [10, 10, 10, 10],
								globalAlt: true,
								marginDesktop: ['0', '', '0', ''],
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/advancedheading',
							{
								uniqueID: '374_0d8c25-60',
								color: 'palette4',
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
								colorClass: 'theme-palette4',
								htmlTag: 'div',
								fontSize: [24, '', ''],
								content: __('Resources', 'flx-blocks'),
							},
							[]
						),
						createBlock(
							'flx/advancedheading',
							{
								uniqueID: '374_7bedef-47',
								color: 'palette6',
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
								colorClass: 'theme-palette6',
								htmlTag: 'p',
								fontSize: ['sm', '', ''],
								icon: 'fe_arrowRight',
								iconSide: 'right',
								content: '<a href="#">' + __('Call to Action', 'flx-blocks') + '</a>',
							},
							[]
						),
					]
				),
			]
		),
	];
}

export { postMeta, innerBlocks };
