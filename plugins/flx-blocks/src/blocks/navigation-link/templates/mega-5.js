/**
 * Template: Mega Menu 3 (free)
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
				uniqueID: '358_0f6b0c-a4',
				columns: 1,
				colLayout: 'equal',
				bgColor: 'palette9',
				inheritMaxWidth: true,
				bgColorClass: 'theme-palette9',
				padding: ['sm', 'sm', 'sm', 'sm'],
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
				fxbVersion: 2,
			},
			[
				createBlock(
					'flx/column',
					{ borderWidth: ['', '', '', ''], uniqueID: '358_126566-59', fxbVersion: 2 },
					[
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '358_d084f8-f7',
								colLayout: 'left-golden',
								verticalAlignment: 'middle',
								padding: ['0', '0', '0', '0'],
								fxbVersion: 2,
							},
							[
								createBlock(
									'flx/column',
									{
										borderWidth: ['', '', '', ''],
										uniqueID: '358_c28a22-85',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedheading',
											{
												uniqueID: '358_578991-ed',
												color: 'palette4',
												fontWeight: '600',
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
												textTransform: 'capitalize',
												colorClass: 'theme-palette4',
												htmlTag: 'div',
												fontSize: ['md', '', ''],
												content: __('Type a short headline', 'flx-blocks'),
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
										uniqueID: '358_baf7a3-b4',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/advancedbtn',
											{ hAlign: 'right', uniqueID: '358_d5b916-7a' },
											[
												createBlock(
													'flx/singlebtn',
													{
														uniqueID: '358_ae164a-5e',
														text: __('Call to Action', 'flx-blocks'),
														sizePreset: 'small',
														color: 'palette3',
														borderStyle: [
															{
																top: ['palette3', '', ''],
																right: ['palette3', '', ''],
																bottom: ['palette3', '', ''],
																left: ['palette3', '', ''],
																unit: 'px',
															},
														],
														icon: 'fe_arrowRight',
														inheritStyles: 'outline',
													},
													[]
												),
											]
										),
									]
								),
							]
						),
						createBlock(
							'flx/rowlayout',
							{
								uniqueID: '358_7d6d7c-24',
								columns: 3,
								colLayout: 'equal',
								padding: ['xs', '', '', ''],
								fxbVersion: 2,
							},
							[
								createBlock(
									'flx/column',
									{
										borderWidth: ['', '', '', ''],
										uniqueID: '358_58dd7d-53',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/navigation',
											{ uniqueID: '358_00f12d-f7', templateKey: 'short-vertical' },
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 2,
										borderWidth: ['', '', '', ''],
										uniqueID: '358_4c8eab-d3',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/navigation',
											{ uniqueID: '358_91484a-8c', templateKey: 'short-vertical' },
											[]
										),
									]
								),
								createBlock(
									'flx/column',
									{
										id: 3,
										borderWidth: ['', '', '', ''],
										uniqueID: '358_983b99-d4',
										fxbVersion: 2,
									},
									[
										createBlock(
											'flx/navigation',
											{ uniqueID: '358_53ceee-49', templateKey: 'short-vertical' },
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
