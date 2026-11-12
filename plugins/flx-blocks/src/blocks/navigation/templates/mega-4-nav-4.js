/**
 * Template: Horizontal helful link menu
 * Post Type: flxbk_navigation
 */

import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const postMeta = {
	_flxbk_navigation_stretch: '1',
	_flxbk_navigation_collapseSubMenus: '1',
	_flxbk_navigation_collapseSubMenusTablet: '1',
	_flxbk_navigation_collapseSubMenusMobile: '1',
	_flxbk_navigation_margin: ['xs', '', 'xs', ''],
	_flxbk_navigation_spacing: ['', '2', '', '2'],
	_flxbk_navigation_linkColor: 'palette5',
	_flxbk_navigation_divider: [
		{ top: ['', '', ''], right: ['', '', ''], bottom: ['palette7', '', 2], left: ['', '', ''], unit: 'px' },
	],
	_flxbk_navigation_dividerTablet: [
		{ top: ['', '', ''], right: ['', '', ''], bottom: ['', '', ''], left: ['', '', ''], unit: '' },
	],
	_flxbk_navigation_dividerMobile: [
		{ top: ['', '', ''], right: ['', '', ''], bottom: ['', '', ''], left: ['', '', ''], unit: '' },
	],
	_flxbk_navigation_typography: [
		{
			size: [12, '', ''],
			sizeType: 'px',
			lineHeight: ['', '', ''],
			lineType: '',
			letterSpacing: ['', '', ''],
			letterType: 'px',
			textTransform: 'capitalize',
			family: '',
			google: false,
			style: '',
			weight: '',
			variant: '',
			subset: '',
			loadGoogle: true,
		},
	],
};

function innerBlocks() {
	return [
		createBlock('flx/navigation', {}, [
			createBlock(
				'flx/navigation-link',
				{ uniqueID: '372_6fa62d-de', label: __('View Demo', 'flx-blocks'), url: '#', kind: 'custom' },
				[]
			),
			createBlock(
				'flx/navigation-link',
				{ uniqueID: '372_6edcc1-8e', label: __('Contact Sales', 'flx-blocks'), url: '#', kind: 'custom' },
				[]
			),
			createBlock(
				'flx/navigation-link',
				{ uniqueID: '372_c4f166-e5', label: __('Documentation', 'flx-blocks'), url: '#', kind: 'custom' },
				[]
			),
		]),
	];
}

export { postMeta, innerBlocks };
