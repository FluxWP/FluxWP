/**
 * Template: other Features
 * Post Type: flxbk_navigation
 */

import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

const postMeta = {
	_flxbk_navigation_collapseSubMenus: '1',
	_flxbk_navigation_collapseSubMenusTablet: '1',
	_flxbk_navigation_collapseSubMenusMobile: '1',
	_flxbk_navigation_orientation: 'vertical',
	_flxbk_navigation_linkColor: 'palette3',
	_flxbk_navigation_linkColorHover: 'palette3',
	_flxbk_navigation_linkColorActive: 'palette3',
	_flxbk_navigation_background: 'palette9',
	_flxbk_navigation_backgroundHover: 'palette7',
	_flxbk_navigation_backgroundActive: 'palette7',
	_flxbk_navigation_typography: [
		{
			size: ['sm', '', ''],
			sizeType: 'px',
			lineHeight: ['', '', ''],
			lineType: '',
			letterSpacing: [0.5, '', ''],
			letterType: 'px',
			textTransform: '',
			family: '',
			google: false,
			style: '',
			weight: 'bold',
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
				{
					uniqueID: '320_31fdc9-c3',
					label: __('Add a short title', 'flx-blocks'),
					url: 'Add%20a%20short%20title',
					kind: 'custom',
				},
				[]
			),
			createBlock(
				'flx/navigation-link',
				{
					uniqueID: '320_fe64a2-93',
					label: __('Add a short title', 'flx-blocks'),
					url: 'Add%20a%20short%20title',
					kind: 'custom',
				},
				[]
			),
			createBlock(
				'flx/navigation-link',
				{
					uniqueID: '320_4c592b-41',
					label: __('Add a short title', 'flx-blocks'),
					url: 'Add%20a%20short%20title',
					kind: 'custom',
				},
				[]
			),
		]),
	];
}

export { postMeta, innerBlocks };
