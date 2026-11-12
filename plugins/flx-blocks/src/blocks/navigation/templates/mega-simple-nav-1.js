/**
 * Template: Mega Menu 4 sub mega menu
 * Post Type: flxbk_navigation
 */

import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const postMeta = {
	_flxbk_navigation_collapseSubMenus: '1',
	_flxbk_navigation_collapseSubMenusTablet: '1',
	_flxbk_navigation_collapseSubMenusMobile: '1',
	_flxbk_navigation_orientation: 'vertical',
	_flxbk_navigation_spacing: ['1', '', '1', ''],
	_flxbk_navigation_dropdownDividerTablet: [
		{ top: ['', '', ''], right: ['', '', ''], bottom: ['', '', ''], left: ['', '', ''], unit: '' },
	],
	_flxbk_navigation_dropdownDividerMobile: [
		{ top: ['', '', ''], right: ['', '', ''], bottom: ['', '', ''], left: ['', '', ''], unit: '' },
	],
	_flxbk_navigation_linkColor: 'palette4',
	_flxbk_navigation_linkColorHover: 'palette1',
	_flxbk_navigation_linkColorActive: 'palette1',
	_flxbk_navigation_descriptionColor: 'palette6',
	_flxbk_navigation_typography: [
		{
			size: ['sm', '', ''],
			sizeType: 'px',
			lineHeight: ['', '', ''],
			lineType: '',
			letterSpacing: ['', '', ''],
			letterType: 'px',
			textTransform: '',
			family: 'var( --global-body-font-family, inherit )',
			google: false,
			style: 'normal',
			weight: '400',
			variant: '',
			subset: '',
			loadGoogle: true,
		},
	],
	_flxbk_navigation_dropdownTypography: [
		{
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
			weight: '',
			variant: '',
			subset: '',
			loadGoogle: true,
		},
	],
	_flxbk_navigation_dropdownBorderTablet: [
		{
			top: ['', 'solid', ''],
			right: ['', 'solid', ''],
			bottom: ['', 'solid', ''],
			left: ['', 'solid', ''],
			unit: '',
		},
	],
	_flxbk_navigation_dropdownBorderMobile: [
		{
			top: ['', 'solid', ''],
			right: ['', 'solid', ''],
			bottom: ['', 'solid', ''],
			left: ['', 'solid', ''],
			unit: '',
		},
	],
};

function innerBlocks() {
	return [
		createBlock('flx/navigation', {}, [
			createBlock(
				'flx/navigation-link',
				{
					uniqueID: '376_03ert41d-5b',
					label: __('Short Title', 'flx-blocks'),
					url: '#',
					kind: 'custom',
				},
				[]
			),
			createBlock(
				'flx/navigation-link',
				{
					uniqueID: '376_032341d-5b',
					label: __('Short Title', 'flx-blocks'),
					url: '#',
					kind: 'custom',
				},
				[]
			),
			createBlock(
				'flx/navigation-link',
				{
					uniqueID: '376_4442342-d5',
					label: __('Short Title', 'flx-blocks'),
					url: '#',
					kind: 'custom',
				},
				[]
			),
			createBlock(
				'flx/navigation-link',
				{
					uniqueID: '376_6323e99-fb',
					label: __('Short Title', 'flx-blocks'),
					url: '#',
					kind: 'custom',
				},
				[]
			),
		]),
	];
}

export { postMeta, innerBlocks };
