/**
 * Template: Multi-Row 4
 * Post Type: flxbk_header
 */

import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const postMeta = {
	_flxbk_header_typography: {
		color: 'palette9',
		size: ['', '', ''],
		sizeType: 'px',
		lineHeight: ['', '', ''],
		lineType: '',
		letterSpacing: ['', '', ''],
		letterType: 'px',
		textTransform: '',
		family: '',
		google: false,
		style: '',
		weight: '400',
		variant: '',
		subset: '',
		loadGoogle: true,
	},
	_flxbk_header_linkHoverColor: 'palette1',
	_flxbk_header_linkColor: 'palette9',
};

function innerBlocks() {
	return [
		createBlock('flx/header', {}, [
			createBlock('flx/header-container-desktop', { uniqueID: '8_1890d6-03' }, [
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_587800-7f',
						location: 'top',
						padding: ['xxs', 'sm', 'xxs', 'sm'],
						metadata: { name: __('Top Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_97bc57-fd',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_adf65f-81',
										location: 'left',
										metadata: { name: __('Left', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_76e931-b1',
										location: 'center-left',
										metadata: { name: __('Center Left', 'flx-blocks') },
									},
									[]
								),
							]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_c6a8f3-1e',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_49d7f5-95',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_148cdd-b1',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_2e0c59-c4',
										location: 'right',
										metadata: { name: __('Right', 'flx-blocks') },
									},
									[]
								),
							]
						),
					]
				),
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_f4c6f4-49',
						location: 'center',
						background: {
							color: 'palette3',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						padding: ['sm', 'sm', 'sm', 'sm'],
						metadata: { name: __('Middle Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_d094cb-24',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{ uniqueID: '8_b264d1-e5', location: 'left', metadata: { name: __('Left', 'flx-blocks') } },
									[
										createBlock(
											'flx/identity',
											{
												uniqueID: '6_eb8552-af',
												showSiteTitle: false,
											},
											[]
										),
									]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_6f1afc-1d',
										location: 'center-left',
										metadata: { name: __('Center Left', 'flx-blocks') },
									},
									[]
								),
							]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_ea2e80-5a',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_fec36b-63',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_ab437b-52',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{ uniqueID: '8_aabc3b-c5', location: 'right', metadata: { name: __('Right', 'flx-blocks') } },
									[
										createBlock(
											'flx/rowlayout',
											{
												uniqueID: '8_9dec1a-e0',
												colLayout: 'row',
												padding: ['', '', '', ''],
												fxbVersion: 2,
											},
											[
												createBlock(
													'flx/column',
													{
														id: 2,
														borderWidth: ['', '', '', ''],
														uniqueID: '8_f663ed-44',
														direction: ['horizontal', '', ''],
														justifyContent: ['flex-end', '', ''],
														verticalAlignment: 'middle',
														fxbVersion: 2,
													},
													[
														createBlock(
															'flx/navigation',
															{
																uniqueID: '8_fb93a2-43',
																templateKey: 'short',
															},
															[]
														),
														createBlock(
															'flx/advancedbtn',
															{ uniqueID: '8_6ac32e-04' },
															[
																createBlock(
																	'flx/singlebtn',
																	{
																		uniqueID: '8_c39aba-6d',
																		text: __('Subscribe', 'flx-blocks'),
																		sizePreset: 'small',
																		color: 'palette9',
																		colorHover: 'palette7',
																		borderStyle: [
																			{
																				top: ['palette9', '', 1],
																				right: ['palette9', '', 1],
																				bottom: ['palette9', '', 1],
																				left: ['palette9', '', 1],
																				unit: 'px',
																			},
																		],
																		borderRadius: [0, 0, 0, 0],
																		inheritStyles: 'outline',
																		typography: [
																			{
																				size: ['', '', ''],
																				sizeType: 'px',
																				lineHeight: ['', '', ''],
																				lineType: '',
																				letterSpacing: [1, '', ''],
																				letterType: 'px',
																				textTransform: 'uppercase',
																				family: '',
																				google: '',
																				style: '',
																				weight: '700',
																				variant: '',
																				subset: '',
																				loadGoogle: true,
																			},
																		],
																	},
																	[]
																),
															]
														),
													]
												),
												createBlock(
													'flx/column',
													{
														borderWidth: ['', '', '', ''],
														uniqueID: '8_b6adef-df',
														fxbVersion: 2,
													},
													[
														createBlock(
															'flx/navigation',
															{ uniqueID: '8_7739cd-d6', templateKey: 'long' },
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
					]
				),
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_6e8392-d8',
						location: 'bottom',
						metadata: { name: __('Bottom Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_4d7b8d-57',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_63025a-c5',
										location: 'left',
										metadata: { name: __('Left', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_4b3f85-cc',
										location: 'center-left',
										metadata: { name: __('Center Left', 'flx-blocks') },
									},
									[]
								),
							]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_584583-a0',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_40b7f4-b0',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_338366-a5',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_01ca9b-76',
										location: 'right',
										metadata: { name: __('Right', 'flx-blocks') },
									},
									[]
								),
							]
						),
					]
				),
			]),
			createBlock('flx/header-container-tablet', { uniqueID: '8_b336f2-0a' }, [
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_41f368-e9',
						location: 'top',
						background: {
							color: 'palette3',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						borderTablet: [
							{
								top: ['', '', ''],
								right: ['', '', ''],
								bottom: ['palette5', '', 1],
								left: ['', '', ''],
								unit: '',
							},
						],
						minHeightTablet: 40,
						vAlignTablet: 'center',
						metadata: { name: __('Top Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_d656b4-bf',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/advancedheading',
									{
										uniqueID: '8_a9128b-e5',
										color: 'palette9',
										tabletMargin: ['', '', '', 'xs'],
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
										colorClass: 'theme-palette9',
										htmlTag: 'div',
									},
									[]
								),
							]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_b5c118-e9',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_38830e-1c',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/off-canvas-trigger',
									{
										uniqueID: '8_68b6b6-18',
										icon: 'fe_alignJustify',
										iconSizeTablet: 30,
										iconColorTablet: 'palette9',
										paddingTablet: ['', 'xs', '', ''],
									},
									[]
								),
							]
						),
					]
				),
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_d5ca03-c6',
						location: 'center',
						background: {
							color: 'palette3',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						metadata: { name: __('Middle Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_c33ced-f8',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_27b60f-a0',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/identity',
									{
										uniqueID: '6_eb8552-af',
										showSiteTitle: false,
									},
									[]
								),
							]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_9d5720-39',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[]
						),
					]
				),
				createBlock(
					'flx/header-row',
					{ uniqueID: '8_a67161-28', location: 'bottom', metadata: { name: __('Bottom Row', 'flx-blocks') } },
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_2e804b-08',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_2c4abc-e6',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_a21857-97',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[]
						),
					]
				),
			]),
			createBlock(
				'flx/off-canvas',
				{
					uniqueID: '8_574392-6c',
					slideFrom: 'right',
					widthType: 'full',
					containerMaxWidthTablet: 300,
					backgroundColorTablet: 'palette4',
					paddingTablet: ['xxl', 'xxl', 'xxl', 'xxl'],
					hAlignTablet: 'center',
					vAlignTablet: 'center',
					closeIconSizeTablet: 30,
					closeIconColorTablet: 'palette9',
					closeIconPaddingTablet: ['xs', 'xs', 'xs', 'xs'],
				},
				[createBlock('flx/navigation', { uniqueID: '8_6c2466-33', templateKey: 'long-vertical' }, [])]
			),
		]),
	];
}

export { postMeta, innerBlocks };
