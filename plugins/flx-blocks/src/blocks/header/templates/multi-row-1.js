/**
 * Template: Multi-Row 5
 * Post Type: flxbk_header
 */

import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const postMeta = {
	_flxbk_header_typography: {
		color: '',
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
};

function innerBlocks() {
	return [
		createBlock('flx/header', {}, [
			createBlock('flx/header-container-desktop', { uniqueID: '8_bfb1de-84' }, [
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_fefa38-05',
						location: 'top',
						background: {
							color: 'palette8',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						border: [
							{
								top: ['', '', ''],
								right: ['', '', ''],
								bottom: ['#cbd7eb', '', 1],
								left: ['', '', ''],
								unit: 'px',
							},
						],
						padding: ['sm', 'xs', 'xs', 'xs'],
						metadata: { name: __('Top Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_e9dda0-cc',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_0bb5c8-e1',
										location: 'left',
										metadata: { name: __('Left', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_1dcafd-1a',
										location: 'center-left',
										metadata: { name: __('Center Left', 'flx-blocks') },
									},
									[]
								),
							]
						),
						createBlock(
							'flx/header-column',
							{ uniqueID: '8_3851e3-8d', location: 'center', metadata: { name: __('Center', 'flx-blocks') } },
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
							'flx/header-section',
							{
								uniqueID: '8_e62566-91',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_b46f9e-9f',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_f7a977-8c',
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
						uniqueID: '8_8baf12-66',
						location: 'center',
						background: {
							color: 'palette8',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						padding: ['xxs', 'xxs', 'xxs', 'xxs'],
						metadata: { name: __('Middle Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_4b9af6-48',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_09478c-ee',
										location: 'left',
										metadata: { name: __('Left', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_5229e1-19',
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
								uniqueID: '8_e6bf9c-db',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[createBlock('flx/navigation', { uniqueID: '8_be1cc0-86', templateKey: 'long' }, [])]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_636d75-de',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_9cd82a-2d',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_b31977-5a',
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
						uniqueID: '8_aeb783-18',
						location: 'bottom',
						background: {
							color: 'palette8',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						padding: ['', '', 'sm', ''],
						metadata: { name: __('Bottom Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_0c9b7f-56',
								location: 'left',
								metadata: { name: __('Left Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_b2b367-1a',
										location: 'left',
										metadata: { name: __('Left', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_3750c7-6c',
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
								uniqueID: '8_87c30d-f5',
								location: 'center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/advancedheading',
									{
										uniqueID: '8_0ebb76-c1',
										typography: 'var( --global-heading-font-family, inherit )',
										fontWeight: '800',
										margin: ['', '0', '', ''],
										padding: ['', 'xs', '', ''],
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
										textTransform: 'uppercase',
										htmlTag: 'div',
										fontSize: ['sm', '', ''],
										fontHeight: [null, '', ''],
										fontHeightType: 'em',
										icon: 'fe_settings',
										borderStyle: [
											{
												top: ['', '', ''],
												right: ['palette3', '', 1],
												bottom: ['', '', ''],
												left: ['', '', ''],
												unit: 'px',
											},
										],
									},
									[]
								),
								createBlock(
									'flx/navigation',
									{ uniqueID: '8_5e5f9b-cb', templateKey: 'long-vertical' },
									[]
								),
							]
						),
						createBlock(
							'flx/header-section',
							{
								uniqueID: '8_6cf044-e7',
								location: 'right',
								metadata: { name: __('Right Section', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_9f8788-bb',
										location: 'center-right',
										metadata: { name: __('Center Right', 'flx-blocks') },
									},
									[]
								),
								createBlock(
									'flx/header-column',
									{
										uniqueID: '8_11a44d-7c',
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
			createBlock('flx/header-container-tablet', { uniqueID: '8_c2e5c9-98' }, [
				createBlock(
					'flx/header-row',
					{
						uniqueID: '8_457a2d-80',
						location: 'top',
						metadata: { name: __('Top Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_60bd87-af',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_1791f5-df',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_9826a0-6c',
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
						uniqueID: '8_e4e4cd-eb',
						location: 'center',
						background: {
							color: 'palette8',
							image: '',
							imageID: '',
							position: 'center center',
							size: 'cover',
							repeat: 'no-repeat',
							attachment: 'scroll',
							type: 'normal',
							gradient: '',
						},
						paddingTablet: ['md', 'md', 'md', 'md'],
						metadata: { name: __('Middle Row', 'flx-blocks') },
					},
					[
						createBlock(
							'flx/header-column',
							{ uniqueID: '8_f68495-b4', location: 'tablet-left', metadata: { name: __('Left', 'flx-blocks') } },
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
								uniqueID: '8_2d1e60-a9',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_2006d8-bf',
								location: 'tablet-right',
								metadata: { name: __('Right', 'flx-blocks') },
							},
							[
								createBlock(
									'flx/off-canvas-trigger',
									{
										uniqueID: '8_b5d85e-84',
										icon: 'fe_menu',
										iconSizeTablet: 30,
										iconColorTablet: 'palette3',
									},
									[]
								),
							]
						),
					]
				),
				createBlock(
					'flx/header-row',
					{ uniqueID: '8_377a88-c5', location: 'bottom', metadata: { name: __('Bottom Row', 'flx-blocks') } },
					[
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_cd71bf-3b',
								location: 'tablet-left',
								metadata: { name: __('Left', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_b92c1b-3d',
								location: 'tablet-center',
								metadata: { name: __('Center', 'flx-blocks') },
							},
							[]
						),
						createBlock(
							'flx/header-column',
							{
								uniqueID: '8_28fe4f-e1',
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
					uniqueID: '8_0e55b4-4b',
					widthType: 'full',
					backgroundColor: 'palette3',
					padding: ['lg', 'md', 'xl', 'md'],
					closeIconColor: 'palette9',
					closeIconPadding: ['xs', 'xs', 'xs', 'xs'],
				},
				[createBlock('flx/navigation', { uniqueID: '8_e09777-12', templateKey: 'short' }, [])]
			),
		]),
	];
}

export { postMeta, innerBlocks };
