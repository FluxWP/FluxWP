/**
 * Template: Mega Menu 2 (free)
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
				uniqueID: '356_64edcb-b5',
				columns: 6,
				tabletLayout: 'three-grid',
				mobileRowGutter: 'none',
				customRowGutter: ['', '', 0],
				columnGutter: 'skinny',
				customGutter: [16, '', ''],
				colLayout: 'equal',
				columnsInnerHeight: true,
				inheritMaxWidth: true,
				align: '',
				padding: ['0', 'xs', '0', '0'],
				mobilePadding: ['md', 'md', '', 'md'],
				templateLock: false,
				fxbVersion: 2,
			},
			[
				createBlock(
					'flx/column',
					{
						background: 'palette7',
						borderWidth: ['', '', '', ''],
						uniqueID: '356_196251-23',
						textAlign: ['', '', 'center'],
						direction: ['', '', 'vertical'],
						justifyContent: ['', '', 'center'],
						rowGap: [10, 16, 0],
						rowGapVariable: ['', '', 'none'],
						verticalAlignment: 'middle',
						verticalAlignmentMobile: 'middle',
						padding: ['sm', 'sm', 'sm', 'sm'],
						tabletPadding: ['', '', 'sm', ''],
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/image',
							{
								align: 'center',
								imgMaxWidth: '',
								imgMaxWidthMobile: '',
								sizeSlug: 'full',
								linkDestination: 'none',
								uniqueID: '356_966520-f4',
								globalAlt: true,
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_6ed1df-cc',
								label: __('New Arrivals', 'flx-blocks'),
								url: 'New%20Arrivals',
								kind: 'custom',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_754bd9-ff',
								label: __('Best Sellers', 'flx-blocks'),
								url: 'Best%20Sellers',
								kind: 'custom',
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
						uniqueID: '356_162b49-5e',
						direction: ['', '', 'horizontal'],
						justifyContent: ['', '', 'center'],
						gutter: ['', '', 0],
						gutterVariable: ['', '', 'none'],
						rowGap: [10, '', 0],
						rowGapVariable: ['', '', 'none'],
						verticalAlignmentMobile: 'middle',
						padding: ['xs', '', 'xs', ''],
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/image',
							{
								imgMaxWidthMobile: 75,
								sizeSlug: 'large',
								ratio: 'square',
								useRatio: true,
								linkDestination: 'none',
								uniqueID: '356_c78042-04',
								globalAlt: true,
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_a30122-0e',
								label: __('Women', 'flx-blocks'),
								url: 'Women',
								kind: 'custom',
								typography: [
									{
										size: [16, '', ''],
										sizeType: 'px',
										lineHeight: ['', '', ''],
										lineType: '',
										letterSpacing: [1, '', ''],
										letterType: 'px',
										textTransform: 'uppercase',
										family: '',
										google: '',
										style: '',
										weight: 'bold',
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
				createBlock(
					'flx/column',
					{
						id: 2,
						borderWidth: ['', '', '', ''],
						uniqueID: '356_4fdb6e-9f',
						direction: ['', '', 'horizontal'],
						justifyContent: ['', '', 'center'],
						gutter: ['', '', 0],
						gutterVariable: ['', '', 'none'],
						rowGap: [10, 16, 0],
						rowGapVariable: ['', '', 'none'],
						verticalAlignmentMobile: 'middle',
						padding: ['xs', '', 'xs', ''],
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/image',
							{
								imgMaxWidthMobile: 75,
								sizeSlug: 'large',
								ratio: 'square',
								useRatio: true,
								linkDestination: 'none',
								uniqueID: '356_fb70e2-00',
								globalAlt: true,
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_8dd1df-5c',
								label: __('Children', 'flx-blocks'),
								url: 'Children',
								kind: 'custom',
								typography: [
									{
										size: [16, '', ''],
										sizeType: 'px',
										lineHeight: ['', '', ''],
										lineType: '',
										letterSpacing: [1, '', ''],
										letterType: 'px',
										textTransform: 'uppercase',
										family: '',
										google: '',
										style: '',
										weight: 'bold',
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
				createBlock(
					'flx/column',
					{
						id: 2,
						borderWidth: ['', '', '', ''],
						uniqueID: '356_a26c05-fc',
						direction: ['', '', 'horizontal'],
						justifyContent: ['', '', 'center'],
						gutter: ['', '', 0],
						gutterVariable: ['', '', 'none'],
						rowGap: [10, 64, 0],
						rowGapVariable: ['', '', 'none'],
						verticalAlignmentMobile: 'middle',
						padding: ['xs', '', 'xs', ''],
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/image',
							{
								imgMaxWidthMobile: 75,
								sizeSlug: 'large',
								ratio: 'square',
								useRatio: true,
								linkDestination: 'none',
								uniqueID: '356_38c2f8-eb',
								globalAlt: true,
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_1deae2-98',
								label: __('Men', 'flx-blocks'),
								url: 'Men',
								kind: 'custom',
								typography: [
									{
										size: [16, '', ''],
										sizeType: 'px',
										lineHeight: ['', '', ''],
										lineType: '',
										letterSpacing: [1, '', ''],
										letterType: 'px',
										textTransform: 'uppercase',
										family: '',
										google: '',
										style: '',
										weight: 'bold',
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
				createBlock(
					'flx/column',
					{
						id: 2,
						borderWidth: ['', '', '', ''],
						uniqueID: '356_12ad71-7d',
						direction: ['', '', 'horizontal'],
						justifyContent: ['', '', 'center'],
						gutter: ['', '', 0],
						gutterVariable: ['', '', 'none'],
						rowGap: [10, '', 0],
						rowGapVariable: ['', '', 'none'],
						verticalAlignmentMobile: 'middle',
						padding: ['xs', '', 'xs', ''],
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/image',
							{
								imgMaxWidthMobile: 75,
								sizeSlug: 'large',
								ratio: 'square',
								useRatio: true,
								linkDestination: 'none',
								uniqueID: '356_97546e-4e',
								globalAlt: true,
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_dc2bce-56',
								label: __('Accessories', 'flx-blocks'),
								url: 'Men',
								kind: 'custom',
								typography: [
									{
										size: [16, '', ''],
										sizeType: 'px',
										lineHeight: ['', '', ''],
										lineType: '',
										letterSpacing: [1, '', ''],
										letterType: 'px',
										textTransform: 'uppercase',
										family: '',
										google: '',
										style: '',
										weight: 'bold',
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
				createBlock(
					'flx/column',
					{
						id: 2,
						borderWidth: ['', '', '', ''],
						uniqueID: '356_b1ced8-77',
						direction: ['', '', 'horizontal'],
						justifyContent: ['', '', 'center'],
						gutter: ['', '', 0],
						gutterVariable: ['', '', 'none'],
						rowGap: [10, '', 0],
						rowGapVariable: ['', '', 'none'],
						verticalAlignmentMobile: 'middle',
						padding: ['xs', '', 'xs', ''],
						fxbVersion: 2,
					},
					[
						createBlock(
							'flx/image',
							{
								imgMaxWidthMobile: 75,
								sizeSlug: 'large',
								ratio: 'square',
								useRatio: true,
								linkDestination: 'none',
								uniqueID: '356_ed3c5b-d1',
								globalAlt: true,
								url: '/wp-content/plugins/flx-blocks/includes/assets/images/placeholder/gray.png',
							},
							[]
						),
						createBlock(
							'flx/navigation-link',
							{
								uniqueID: '356_2084d3-fc',
								label: __('Sale', 'flx-blocks'),
								url: 'Men',
								kind: 'custom',
								typography: [
									{
										size: [16, '', ''],
										sizeType: 'px',
										lineHeight: ['', '', ''],
										lineType: '',
										letterSpacing: [1, '', ''],
										letterType: 'px',
										textTransform: 'uppercase',
										family: '',
										google: '',
										style: '',
										weight: 'bold',
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
	];
}

export { postMeta, innerBlocks };
