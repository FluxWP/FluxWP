/**
 * Template: Multi-row 2
 * Post Type: flxbk_header
 */

import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const postMeta = {};

function innerBlocks() {
	return [
		createBlock('flx/header', {}, [
			createBlock('flx/header-container-desktop', { uniqueID: '8_25c9b0-6c' }, [
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_8f8e7a-6a',
						location: 'top',
						background: {
							color: 'palette4',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						padding: ['', 'lg', '', 'lg'],
						metadata: { name: __('Top Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_6401bd-8b',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{ uniqueID: '8_1a6394-61', location: 'left', metadata: { name: __('Left', 'flx-blocks') } },
									[
										createBlock(
											'flx/navigation',
											{
												uniqueID: '8_4b69a7-2f',
												templateKey: 'long',
											},
											[]
										),
									]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_b1b207-01',
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
								uniqueID: '8_f6585e-d5',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_dd17ab-d9',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_cf98b5-84',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_22bfd0-cd',
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
						uniqueID: '8_93efb2-34',
						location: 'center',
						background: {
							color: 'palette9',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						padding: ['xxs', 'lg', 'xxs', 'lg'],
						metadata: { name: __('Middle Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_5f7254-ea',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{ uniqueID: '8_e41aed-7d', location: 'left', metadata: { name: __('Left', 'flx-blocks') } },
									[
										createBlock(
											'flx/navigation',
											{
												uniqueID: '8_35093b-2d',
												templateKey: 'long-vertical',
											},
											[]
										),
									]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_c82f51-63',
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
								uniqueID: '8_3f83fa-06',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_dbbe59-8e',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_0a72a7-f1',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{ uniqueID: '8_9a3913-96', location: 'right', metadata: { name: __('Right', 'flx-blocks') } },
									[
										createBlock(
											'flx/icon',
											{
												uniqueID: '8_1a291f-08',
												gap: ['md', '', ''],
											},
											[
												createBlock(
													'flx/single-icon',
													{
														uniqueID: '8_a6f5d6-26',
														icon: 'fas_shopping-bag',
														size: 20,
														color: 'palette5',
														hColor: 'palette3',
													},
													[]
												),
												createBlock(
													'flx/single-icon',
													{
														uniqueID: '8_7f092b-cd',
														icon: 'ic_person',
														size: 20,
														color: 'palette5',
														hColor: 'palette3',
													},
													[]
												),
												createBlock(
													'flx/single-icon',
													{
														uniqueID: '8_8ff89f-9f',
														icon: 'fas_share-alt-square',
														size: 20,
														color: 'palette5',
														hColor: 'palette3',
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
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_c471fc-9f',
						location: 'bottom',
						background: {
							color: 'palette6',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						padding: ['xs', 'lg', 'xs', 'lg'],
						metadata: { name: __('Bottom Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_ac1077-45',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{ uniqueID: '8_cc75e4-ee', location: 'left', metadata: { name: __('Left', 'flx-blocks') } },
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
										uniqueID: '8_70d8f2-9b',
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
								uniqueID: '8_161e55-d6',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[createBlock('flx/navigation', { uniqueID: '8_bfcf4e-2f', templateKey: 'short' }, [])]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_ccb71b-c0',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_868d52-5c',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_5ea247-14',
										location: 'right',
										metadata: { name: __('Right', 'flx-blocks') },
									},
									[
										createBlock('flx/icon', { uniqueID: '8_281164-82' }, [
											createBlock(
												'flx/single-icon',
												{
													uniqueID: '8_f5703a-bd',
													icon: 'fe_search',
													size: 20,
													color: 'palette9',
												},
												[]
											),
										]),
									]
								),
							]
						),
					]
				),
			]),
			createBlock('flx/header-container-tablet', { uniqueID: '8_2eccef-84' }, [
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_a3c4fb-20',
						location: 'top',
						metadata: { name: __('Top Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_5c2144-21',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_450dc9-7d',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_fa1001-eb',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[]
						),
					]
				),
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_61eded-91',
						location: 'center',
						background: {
							color: 'palette6',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						minHeightTablet: 60,
						metadata: { name: __('Middle Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_0bb774-bc',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_a273a9-b3',
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
								uniqueID: '8_996425-e5',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[
								createBlock('flx/icon', { uniqueID: '8_f05f7a-e0', tabletTextAlignment: 'right' }, [
									createBlock(
										'flx/single-icon',
										{
											uniqueID: '8_50d361-8c',
											icon: 'fe_search',
											color: 'palette9',
											hColor: 'palette9',
											tabletSize: 25,
										},
										[]
									),
								]),
							]
						),
					]
				),
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_987bd4-f2',
						location: 'bottom',
						background: {
							color: 'palette4',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						metadata: { name: __('Bottom Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_2fd048-47',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[createBlock('flx/navigation', { uniqueID: '8_cb4fbd-a1', templateKey: 'short' }, [])]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_89dd8b-37',
								metadata: {
									name: __('Center', 'flx-blocks'),
									location: 'tablet-center',
								},
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_b29fe1-88',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/off-canvas-trigger',
									{
										uniqueID: '8_a1c772-cf',
										icon: 'fe_alignJustify',
										iconSizeTablet: 25,
										iconColorTablet: 'palette9',
										iconColorHoverTablet: 'palette9',
									},
									[]
								),
							]
						),
					]
				),
			]),
			createBlock(
				'flx/off-canvas',
				{
					uniqueID: '8_4a9686-23',
					slideFrom: 'right',
					backgroundColorTablet: 'palette6',
					hAlignTablet: 'left',
					vAlignTablet: 'top',
					closeIcon: 'fe_xCircle',
					closeIconSizeTablet: 25,
					closeIconColorTablet: 'palette9',
					closeIconColorHoverTablet: 'palette9',
				},
				[createBlock('flx/navigation', { uniqueID: '8_22e6f9-15', templateKey: 'short' }, [])]
			),
		]),
	];
}

export { postMeta, innerBlocks };
