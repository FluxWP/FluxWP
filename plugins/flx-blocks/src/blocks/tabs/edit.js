/**
 * BLOCK: Flux Tabs
 */

/**
 * Import Icons
 */
import {
	accordionIcon,
	tabsBoldIcon,
	tabsCenterIcon,
	tabsIcon,
	tabsSimpleIcon,
	tabsVerticalIcon,
	vTabsIcon,
} from '@flx-blocks/icons';

/**
 * Import External
 */
import classnames from 'classnames';
import memoize from 'memize';
import { times, filter, map } from 'lodash';
import {
	FlxbkColorOutput,
	showSettings,
	getSpacingOptionOutput,
	getPreviewSize,
	getFontSizeOptionOutput,
	getBorderStyle,
	uniqueIdHelper,
} from '@flx-blocks/helpers';
import {
	PopColorControl,
	TypographyControls,
	WebfontLoader,
	FlxbkIconPicker,
	IconRender,
	FlxbkPanelBody,
	FlxbkBlockDefaults,
	ResponsiveMeasureRangeControl,
	InspectorControlTabs,
	CopyPasteAttributes,
	SmallResponsiveControl,
	ResponsiveMeasurementControls,
	ResponsiveBorderControl,
	ResponsiveRangeControls,
} from '@flx-blocks/components';

/**
 * Import Css
 */
import './editor.scss';
import metadata from './block.json';

import { createBlock } from '@wordpress/blocks';
import { withSelect, withDispatch, useDispatch, useSelect } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { useState, Fragment, useRef, useEffect } from '@wordpress/element';
import {
	InnerBlocks,
	InspectorControls,
	RichText,
	BlockControls,
	AlignmentToolbar,
	BlockAlignmentToolbar,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	ButtonGroup,
	Tooltip,
	TabPanel,
	Dashicon,
	RangeControl,
	ToggleControl,
	SelectControl,
	TextControl,
	ToolbarButton,
	ToolbarGroup,
	MenuGroup,
	MenuItem,
	ToolbarDropdownMenu,
} from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { plusCircle } from '@wordpress/icons';
/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';
import Select from 'react-select';

const ALLOWED_BLOCKS = ['flx/tab'];

/**
 * Regular expression matching invalid anchor characters for replacement.
 *
 * @type {RegExp}
 */
const ANCHOR_REGEX = /[\s#]/g;

/**
 * Returns the layouts configuration for a given number of panes.
 *
 * @param {number} panes Number of panes.
 *
 * @return {Object[]} Panes layout configuration.
 */
const getPanesTemplate = memoize((panes) => {
	return times(panes, (n) => ['flx/tab', { id: n + 1 }]);
});
/**
 * Build the row edit
 */
function FlxbkTabs(props) {
	const {
		attributes,
		clientId,
		className,
		isSelected,
		setAttributes,
		tabsBlock,
		realTabsCount,
		tabsInner,
		resetOrder,
		moveTab,
		insertTab,
		removeTab,
		previewDevice,
	} = props;
	const {
		uniqueID,
		showPresets,
		tabCount,
		blockAlignment,
		mobileLayout,
		currentTab,
		tabletLayout,
		layout,
		innerPadding,
		tabletInnerPadding,
		mobileInnerPadding,
		innerPaddingType,
		minHeight,
		tabletMinHeight,
		mobileMinHeight,
		maxWidth,
		tabletMaxWidth,
		mobileMaxWidth,
		titles,
		titleColor,
		titleColorHover,
		titleColorActive,
		titleBg,
		titleBgHover,
		titleBgActive,
		size,
		sizeType,
		lineType,
		lineHeight,
		tabLineHeight,
		tabSize,
		mobileSize,
		mobileLineHeight,
		letterSpacing,
		titleBorderWidth,
		tabletTitleBorderWidth,
		mobileTitleBorderWidth,
		titleBorderWidthUnit,
		titleBorderControl,
		titleBorder,
		titleBorderHover,
		titleBorderActive,
		typography,
		fontVariant,
		fontWeight,
		fontStyle,
		fontSubset,
		googleFont,
		loadGoogleFont,
		contentBorder,
		contentBorderColor,
		titlePadding,
		tabletTitlePadding,
		mobileTitlePadding,
		titlePaddingUnit,
		titleMargin,
		tabletTitleMargin,
		mobileTitleMargin,
		titleMarginUnit,
		contentBgColor,
		tabAlignment,
		titleBorderRadius,
		tabletTitleBorderRadius,
		mobileTitleBorderRadius,
		titleBorderRadiusUnit,
		iSize,
		tabletISize,
		mobileISize,
		startTab,
		enableSubtitle,
		subtitleFont,
		subtitleColor,
		subtitleColorHover,
		subtitleColorActive,
		tabWidth,
		gutter,
		widthType,
		textTransform,
		contentBorderRadius,
		tabletContentBorderRadius,
		mobileContentBorderRadius,
		contentBorderRadiusUnit,
		contentBorderStyles,
		tabletContentBorderStyles,
		mobileContentBorderStyles,
		verticalTabWidth,
		verticalTabWidthUnit,
		linkPaneCollapse,
	} = attributes;

	const [showPreset, setShowPreset] = useState(false);
	const [activeTab, setActiveTab] = useState('general');

	useEffect(() => {
		if (!uniqueID) {
			const blockConfigObject = flxbk_params.configuration
				? JSON.parse(flxbk_params.configuration)
				: [];
			if (
				blockConfigObject['flx/tabs'] !== undefined &&
				typeof blockConfigObject['flx/tabs'] === 'object'
			) {
			} else {
				setShowPreset(true);
			}
		}

		if (contentBorder[0] !== '' || contentBorder[1] !== '' || contentBorder[2] !== '' || contentBorder[3] !== '') {
			const tmpContentBorderColor = contentBorderColor ? contentBorderColor : '#dee2e6';

			setAttributes({
				contentBorderStyles: [
					{
						top: [tmpContentBorderColor, '', contentBorder[0]],
						right: [tmpContentBorderColor, '', contentBorder[1]],
						bottom: [tmpContentBorderColor, '', contentBorder[2]],
						left: [tmpContentBorderColor, '', contentBorder[3]],
						unit: 'px',
					},
				],
				contentBorder: ['', '', '', ''],
			});
		}
	}, []);

	uniqueIdHelper(props);

	const previewInnerPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== innerPadding ? innerPadding[0] : '',
		undefined !== tabletInnerPadding ? tabletInnerPadding[0] : '',
		undefined !== mobileInnerPadding ? mobileInnerPadding[0] : ''
	);
	const previewInnerPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== innerPadding ? innerPadding[1] : '',
		undefined !== tabletInnerPadding ? tabletInnerPadding[1] : '',
		undefined !== mobileInnerPadding ? mobileInnerPadding[1] : ''
	);
	const previewInnerPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== innerPadding ? innerPadding[2] : '',
		undefined !== tabletInnerPadding ? tabletInnerPadding[2] : '',
		undefined !== mobileInnerPadding ? mobileInnerPadding[2] : ''
	);
	const previewInnerPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== innerPadding ? innerPadding[3] : '',
		undefined !== tabletInnerPadding ? tabletInnerPadding[3] : '',
		undefined !== mobileInnerPadding ? mobileInnerPadding[3] : ''
	);

	const previewSubFontSize = getPreviewSize(
		previewDevice,
		undefined !== subtitleFont[0].size && undefined !== subtitleFont[0].size[0] ? subtitleFont[0].size[0] : '',
		undefined !== subtitleFont[0].size && undefined !== subtitleFont[0].size[1] ? subtitleFont[0].size[1] : '',
		undefined !== subtitleFont[0].size && undefined !== subtitleFont[0].size[2] ? subtitleFont[0].size[2] : ''
	);
	const previewSubLineHeight = getPreviewSize(
		previewDevice,
		undefined !== subtitleFont[0].lineHeight && undefined !== subtitleFont[0].lineHeight[0]
			? subtitleFont[0].lineHeight[0]
			: '',
		undefined !== subtitleFont[0].lineHeight && undefined !== subtitleFont[0].lineHeight[1]
			? subtitleFont[0].lineHeight[1]
			: '',
		undefined !== subtitleFont[0].lineHeight && undefined !== subtitleFont[0].lineHeight[2]
			? subtitleFont[0].lineHeight[2]
			: ''
	);

	const previewFontSize = getPreviewSize(
		previewDevice,
		undefined !== size ? size : '',
		undefined !== tabSize ? tabSize : '',
		undefined !== mobileSize ? mobileSize : ''
	);
	const previewLineHeight = getPreviewSize(
		previewDevice,
		undefined !== lineHeight ? lineHeight : '',
		undefined !== tabLineHeight ? tabLineHeight : '',
		undefined !== mobileLineHeight ? mobileLineHeight : ''
	);

	const previewContentRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== contentBorderRadius?.[0] ? contentBorderRadius[0] : '',
		undefined !== tabletContentBorderRadius ? tabletContentBorderRadius[0] : '',
		undefined !== mobileContentBorderRadius ? mobileContentBorderRadius[0] : ''
	);
	const previewContentRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== contentBorderRadius ? contentBorderRadius[1] : '',
		undefined !== tabletContentBorderRadius ? tabletContentBorderRadius[1] : '',
		undefined !== mobileContentBorderRadius ? mobileContentBorderRadius[1] : ''
	);
	const previewContentRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== contentBorderRadius ? contentBorderRadius[2] : '',
		undefined !== tabletContentBorderRadius ? tabletContentBorderRadius[2] : '',
		undefined !== mobileContentBorderRadius ? mobileContentBorderRadius[2] : ''
	);
	const previewContentRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== contentBorderRadius ? contentBorderRadius[3] : '',
		undefined !== tabletContentBorderRadius ? tabletContentBorderRadius[3] : '',
		undefined !== mobileContentBorderRadius ? mobileContentBorderRadius[3] : ''
	);

	const previewContentBorderTop = getBorderStyle(
		previewDevice,
		'top',
		contentBorderStyles,
		tabletContentBorderStyles,
		mobileContentBorderStyles
	);
	const previewContentBorderRight = getBorderStyle(
		previewDevice,
		'right',
		contentBorderStyles,
		tabletContentBorderStyles,
		mobileContentBorderStyles
	);
	const previewContentBorderBottom = getBorderStyle(
		previewDevice,
		'bottom',
		contentBorderStyles,
		tabletContentBorderStyles,
		mobileContentBorderStyles
	);
	const previewContentBorderLeft = getBorderStyle(
		previewDevice,
		'left',
		contentBorderStyles,
		tabletContentBorderStyles,
		mobileContentBorderStyles
	);

	const previewIconSize = getPreviewSize(previewDevice, iSize, tabletISize, mobileISize);

	const previewTitleRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== titleBorderRadius?.[0] ? titleBorderRadius[0] : '',
		undefined !== tabletTitleBorderRadius ? tabletTitleBorderRadius[0] : '',
		undefined !== mobileTitleBorderRadius ? mobileTitleBorderRadius[0] : ''
	);
	const previewTitleRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== titleBorderRadius ? titleBorderRadius[1] : '',
		undefined !== tabletTitleBorderRadius ? tabletTitleBorderRadius[1] : '',
		undefined !== mobileTitleBorderRadius ? mobileTitleBorderRadius[1] : ''
	);
	const previewTitleRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== titleBorderRadius ? titleBorderRadius[2] : '',
		undefined !== tabletTitleBorderRadius ? tabletTitleBorderRadius[2] : '',
		undefined !== mobileTitleBorderRadius ? mobileTitleBorderRadius[2] : ''
	);
	const previewTitleRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== titleBorderRadius ? titleBorderRadius[3] : '',
		undefined !== tabletTitleBorderRadius ? tabletTitleBorderRadius[3] : '',
		undefined !== mobileTitleBorderRadius ? mobileTitleBorderRadius[3] : ''
	);
	const previewTitleMarginTop = getPreviewSize(
		previewDevice,
		titleMargin && undefined !== titleMargin[0] ? titleMargin[0] : '',
		tabletTitleMargin && undefined !== tabletTitleMargin[0] ? tabletTitleMargin[0] : '',
		mobileTitleMargin && undefined !== mobileTitleMargin[0] ? mobileTitleMargin[0] : ''
	);
	const previewTitleMarginRight = getPreviewSize(
		previewDevice,
		titleMargin && undefined !== titleMargin[1] ? titleMargin[1] : '',
		tabletTitleMargin && undefined !== tabletTitleMargin[1] ? tabletTitleMargin[1] : '',
		mobileTitleMargin && undefined !== mobileTitleMargin[1] ? mobileTitleMargin[1] : ''
	);
	const previewTitleMarginBottom = getPreviewSize(
		previewDevice,
		titleMargin && undefined !== titleMargin[2] ? titleMargin[2] : '',
		tabletTitleMargin && undefined !== tabletTitleMargin[2] ? tabletTitleMargin[2] : '',
		mobileTitleMargin && undefined !== mobileTitleMargin[2] ? mobileTitleMargin[2] : ''
	);
	const previewTitleMarginLeft = getPreviewSize(
		previewDevice,
		titleMargin && undefined !== titleMargin[3] ? titleMargin[3] : '',
		tabletTitleMargin && undefined !== tabletTitleMargin[3] ? tabletTitleMargin[3] : '',
		mobileTitleMargin && undefined !== mobileTitleMargin[3] ? mobileTitleMargin[3] : ''
	);
	const previewTitlePaddingTop = getPreviewSize(
		previewDevice,
		titlePadding && undefined !== titlePadding[0] ? titlePadding[0] : '',
		tabletTitlePadding && undefined !== tabletTitlePadding[0] ? tabletTitlePadding[0] : '',
		mobileTitlePadding && undefined !== mobileTitlePadding[0] ? mobileTitlePadding[0] : ''
	);
	const previewTitlePaddingRight = getPreviewSize(
		previewDevice,
		titlePadding && undefined !== titlePadding[1] ? titlePadding[1] : '',
		tabletTitlePadding && undefined !== tabletTitlePadding[1] ? tabletTitlePadding[1] : '',
		mobileTitlePadding && undefined !== mobileTitlePadding[1] ? mobileTitlePadding[1] : ''
	);
	const previewTitlePaddingBottom = getPreviewSize(
		previewDevice,
		titlePadding && undefined !== titlePadding[2] ? titlePadding[2] : '',
		tabletTitlePadding && undefined !== tabletTitlePadding[2] ? tabletTitlePadding[2] : '',
		mobileTitlePadding && undefined !== mobileTitlePadding[2] ? mobileTitlePadding[2] : ''
	);
	const previewTitlePaddingLeft = getPreviewSize(
		previewDevice,
		titlePadding && undefined !== titlePadding[3] ? titlePadding[3] : '',
		tabletTitlePadding && undefined !== tabletTitlePadding[3] ? tabletTitlePadding[3] : '',
		mobileTitlePadding && undefined !== mobileTitlePadding[3] ? mobileTitlePadding[3] : ''
	);
	const previewTitleMarginUnit = titleMarginUnit ? titleMarginUnit : 'px';
	const previewTitlePaddingUnit = titlePaddingUnit ? titlePaddingUnit : 'px';

	const previewTitleBorderWidthTop = getPreviewSize(
		previewDevice,
		undefined !== titleBorderWidth?.[0] ? titleBorderWidth[0] : '',
		undefined !== tabletTitleBorderWidth?.[0] ? tabletTitleBorderWidth[0] : '',
		undefined !== mobileTitleBorderWidth?.[0] ? mobileTitleBorderWidth[0] : ''
	);
	const previewTitleBorderWidthRight = getPreviewSize(
		previewDevice,
		undefined !== titleBorderWidth?.[1] ? titleBorderWidth[1] : '',
		undefined !== tabletTitleBorderWidth?.[1] ? tabletTitleBorderWidth[1] : '',
		undefined !== mobileTitleBorderWidth?.[1] ? mobileTitleBorderWidth[1] : ''
	);
	const previewTitleBorderWidthBottom = getPreviewSize(
		previewDevice,
		undefined !== titleBorderWidth?.[2] ? titleBorderWidth[2] : '',
		undefined !== tabletTitleBorderWidth?.[2] ? tabletTitleBorderWidth[2] : '',
		undefined !== mobileTitleBorderWidth?.[2] ? mobileTitleBorderWidth[2] : ''
	);
	const previewTitleBorderWidthLeft = getPreviewSize(
		previewDevice,
		undefined !== titleBorderWidth?.[3] ? titleBorderWidth[3] : '',
		undefined !== tabletTitleBorderWidth?.[3] ? tabletTitleBorderWidth[3] : '',
		undefined !== mobileTitleBorderWidth?.[3] ? mobileTitleBorderWidth[3] : ''
	);
	const previewMaxWidth = getPreviewSize(previewDevice, maxWidth, tabletMaxWidth, mobileMaxWidth);
	const previewMinHeight = getPreviewSize(previewDevice, minHeight, tabletMinHeight, mobileMinHeight);
	const previewTitleBorderWidthUnit = titleBorderWidthUnit ? titleBorderWidthUnit : 'px';

	const previewTitleBorderRadiusUnit = titleBorderRadiusUnit ? titleBorderRadiusUnit : 'px';

	const previewLayout = getPreviewSize(
		previewDevice,
		undefined !== layout ? layout : 'tabs',
		undefined !== tabletLayout && '' !== tabletLayout && 'inherit' !== tabletLayout ? tabletLayout : '',
		undefined !== mobileLayout && '' !== mobileLayout && 'inherit' !== mobileLayout ? mobileLayout : ''
	);
	const previewTabWidth = getPreviewSize(
		previewDevice,
		tabWidth && tabWidth[0] ? tabWidth[0] : '',
		tabWidth && tabWidth[1] ? tabWidth[1] : '',
		tabWidth && tabWidth[2] ? tabWidth[2] : ''
	);
	const previewVerticalTabWidth = getPreviewSize(
		previewDevice,
		verticalTabWidth && verticalTabWidth[0] ? verticalTabWidth[0] : '',
		verticalTabWidth && verticalTabWidth[1] ? verticalTabWidth[1] : '',
		verticalTabWidth && verticalTabWidth[2] ? verticalTabWidth[2] : ''
	);
	const previewVerticalTabWidthUnit = verticalTabWidthUnit ? verticalTabWidthUnit : 'px';

	const saveArrayUpdate = (value, index) => {
		const newItems = titles.map((item, thisIndex) => {
			if (index === thisIndex) {
				item = { ...item, ...value };
			}

			return item;
		});
		setAttributes({
			titles: newItems,
		});
	};

	const onMove = (oldIndex, newIndex) => {
		const newTitles = [...titles];
		newTitles.splice(newIndex, 1, titles[oldIndex]);
		newTitles.splice(oldIndex, 1, titles[newIndex]);
		setAttributes({ titles: newTitles, currentTab: parseInt(newIndex + 1) });
		if (startTab === oldIndex + 1) {
			setAttributes({ startTab: newIndex + 1 });
		} else if (startTab === newIndex + 1) {
			setAttributes({ startTab: oldIndex + 1 });
		}
		//moveTab( tabsBlock.innerBlocks[ oldIndex ].clientId, newIndex );
		moveTab(oldIndex, newIndex);
		resetOrder();
		setAttributes({ currentTab: parseInt(newIndex + 1) });
	};

	const onMoveForward = (oldIndex) => {
		return () => {
			if (oldIndex === realTabsCount - 1) {
				return;
			}
			onMove(oldIndex, oldIndex + 1);
		};
	};

	const onMoveBack = (oldIndex) => {
		return () => {
			if (oldIndex === 0) {
				return;
			}
			onMove(oldIndex, oldIndex - 1);
		};
	};

	const layoutClass = !layout ? 'tabs' : layout;
	const gconfig = {
		google: {
			families: [typography + (fontVariant ? ':' + fontVariant : '')],
		},
	};
	const sgconfig = {
		google: {
			families: [
				(subtitleFont && subtitleFont[0] && subtitleFont[0].family ? subtitleFont[0].family : '') +
					(subtitleFont && subtitleFont[0] && subtitleFont[0].variant ? ':' + subtitleFont[0].variant : ''),
			],
		},
	};
	const sconfig = subtitleFont && subtitleFont[0] && subtitleFont[0].google ? sgconfig : '';
	const saveSubtitleFont = (value) => {
		let tempSubFont;
		if (undefined === subtitleFont || (undefined !== subtitleFont && undefined === subtitleFont[0])) {
			tempSubFont = [
				{
					size: ['', '', ''],
					sizeType: 'px',
					lineHeight: ['', '', ''],
					lineType: 'px',
					letterSpacing: '',
					textTransform: '',
					family: '',
					google: false,
					style: '',
					weight: '',
					variant: '',
					subset: '',
					loadGoogle: true,
					padding: [0, 0, 0, 0],
					paddingControl: 'linked',
					margin: [0, 0, 0, 0],
					marginControl: 'linked',
				},
			];
		} else {
			tempSubFont = subtitleFont;
		}
		const newUpdate = tempSubFont.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			subtitleFont: newUpdate,
		});
	};
	const startlayoutOptions = [
		{ key: 'skip', name: __('Skip', 'flx-blocks'), icon: __('Skip', 'flx-blocks') },
		{ key: 'simple', name: __('Simple', 'flx-blocks'), icon: tabsSimpleIcon },
		{ key: 'boldbg', name: __('Boldbg', 'flx-blocks'), icon: tabsBoldIcon },
		{ key: 'center', name: __('Center', 'flx-blocks'), icon: tabsCenterIcon },
		{ key: 'vertical', name: __('Vertical', 'flx-blocks'), icon: tabsVerticalIcon },
	];
	const setInitalLayout = (key) => {
		if ('skip' === key) {
		} else if ('simple' === key) {
			setAttributes({
				layout: 'tabs',
				tabAlignment: 'left',
				size: 1.1,
				sizeType: 'em',
				lineHeight: 1.4,
				lineType: 'em',
				titleBorderWidth: [1, 1, 0, 1],
				titleBorderRadius: [4, 4, 0, 0],
				titlePadding: [8, 20, 8, 20],
				titleMargin: [0, 8, -1, 0],
				titleColor: 'var(--global-palette5, #444444)',
				titleColorHover: 'var(--global-palette5, #444444)',
				titleColorActive: 'var(--global-palette5, #444444)',
				titleBg: 'var(--global-palette9, #ffffff)',
				titleBgHover: 'var(--global-palette9, #ffffff)',
				titleBgActive: 'var(--global-palette9, #ffffff)',
				titleBorder: 'var(--global-palette7, #eeeeee)',
				titleBorderHover: 'var(--global-palette8, #F7FAFC)',
				titleBorderActive: '#bcbcbc',
				contentBgColor: 'var(--global-palette9, #ffffff)',
				contentBorderStyles: [
					{
						top: ['#bcbcbc', '', 1],
						right: ['#bcbcbc', '', 1],
						bottom: ['#bcbcbc', '', 1],
						left: ['#bcbcbc', '', 1],
						unit: 'px',
					},
				],
			});
		} else if ('boldbg' === key) {
			setAttributes({
				layout: 'tabs',
				tabAlignment: 'left',
				size: 1.1,
				sizeType: 'em',
				lineHeight: 1.4,
				lineType: 'em',
				titleBorderWidth: [0, 0, 0, 0],
				titleBorderRadius: [4, 4, 0, 0],
				titlePadding: [8, 20, 8, 20],
				titleMargin: [0, 8, 0, 0],
				titleColor: 'var(--global-palette4, #2D3748)',
				titleColorHover: 'var(--global-palette3, #1A202C)',
				titleColorActive: '#ffffff',
				titleBg: 'var(--global-palette7, #eeeeee)',
				titleBgHover: 'var(--global-palette8, #F7FAFC)',
				titleBgActive: '#0a6689',
				titleBorder: 'var(--global-palette7, #eeeeee)',
				titleBorderHover: 'var(--global-palette7, #eeeeee)',
				titleBorderActive: 'var(--global-palette7, #eeeeee)',
				contentBgColor: 'var(--global-palette9, #ffffff)',
				contentBorderStyles: [
					{
						top: ['#0a6689', '', 3],
						right: ['#0a6689', '', 0],
						bottom: ['#0a6689', '', 0],
						left: ['#0a6689', '', 0],
						unit: 'px',
					},
				],
			});
		} else if ('center' === key) {
			setAttributes({
				layout: 'tabs',
				tabAlignment: 'center',
				size: 1.1,
				sizeType: 'em',
				lineHeight: 1.4,
				lineType: 'em',
				titleBorderWidth: [0, 0, 4, 0],
				titleBorderRadius: [4, 4, 0, 0],
				titlePadding: [8, 20, 8, 20],
				titleMargin: [0, 8, 0, 0],
				titleColor: 'var(--global-palette5, #444444)',
				titleColorHover: 'var(--global-palette5, #444444)',
				titleColorActive: '#0a6689',
				titleBg: 'var(--global-palette9, #ffffff)',
				titleBgHover: 'var(--global-palette9, #ffffff)',
				titleBgActive: 'var(--global-palette9, #ffffff)',
				titleBorder: 'var(--global-palette9, #ffffff)',
				titleBorderHover: 'var(--global-palette7, #eeeeee)',
				titleBorderActive: '#0a6689',
				contentBgColor: 'var(--global-palette9, #ffffff)',
				contentBorderStyles: [
					{
						top: ['var(--global-palette7, #eeeeee)', '', 1],
						right: ['var(--global-palette7, #eeeeee)', '', 0],
						bottom: ['var(--global-palette7, #eeeeee)', '', 0],
						left: ['var(--global-palette7, #eeeeee)', '', 0],
						unit: 'px',
					},
				],
			});
		} else if ('vertical' === key) {
			setAttributes({
				layout: 'vtabs',
				mobileLayout: 'accordion',
				tabAlignment: 'left',
				size: 1.1,
				sizeType: 'em',
				lineHeight: 1.4,
				lineType: 'em',
				titleBorderWidth: [4, 0, 4, 4],
				titleBorderRadius: [10, 0, 0, 10],
				titlePadding: [12, 8, 12, 20],
				titleMargin: [0, -4, 10, 0],
				titleColor: 'var(--global-palette5, #444444)',
				titleColorHover: 'var(--global-palette5, #444444)',
				titleColorActive: 'var(--global-palette5, #444444)',
				titleBg: 'var(--global-palette7, #eeeeee)',
				titleBgHover: 'var(--global-palette8, #F7FAFC)',
				titleBgActive: 'var(--global-palette9, #ffffff)',
				titleBorder: 'var(--global-palette7, #eeeeee)',
				titleBorderHover: 'var(--global-palette8, #F7FAFC)',
				titleBorderActive: 'var(--global-palette7, #eeeeee)',
				contentBgColor: 'var(--global-palette9, #ffffff)',
				contentBorderStyles: [
					{
						top: ['var(--global-palette7, #eeeeee)', '', 4],
						right: ['var(--global-palette7, #eeeeee)', '', 4],
						bottom: ['var(--global-palette7, #eeeeee)', '', 4],
						left: ['var(--global-palette7, #eeeeee)', '', 4],
						unit: 'px',
					},
				],
				minHeight: 400,
			});
		}
	};
	const config = googleFont ? gconfig : '';
	const tabLayoutClass = !tabletLayout ? 'inherit' : tabletLayout;
	const mobileLayoutClass = !mobileLayout ? 'inherit' : mobileLayout;

	const classes = classnames(
		className,
		`wp-block-flx-tabs fxt-tabs-wrap fxt-tabs-id${uniqueID} fxt-tabs-has-${tabCount}-tabs fxt-active-tab-${currentTab} fxt-tabs-layout-${layoutClass} fxt-tabs-block fxt-tabs-tablet-layout-${tabLayoutClass} fxt-tabs-mobile-layout-${mobileLayoutClass} fxt-tab-alignment-${tabAlignment}`
	);

	const nonTransAttrs = ['currentTab', 'tabCount', 'titles'];

	const isAccordionPreview =
		(previewDevice == 'Tablet' && tabletLayout == 'accordion') ||
		(previewDevice == 'Mobile' && mobileLayout == 'accordion');

	const mLayoutOptions = [
		{ value: 'tabs', label: __('Tabs', 'flx-blocks'), icon: tabsIcon },
		{ value: 'vtabs', label: __('Vertical Tabs', 'flx-blocks'), icon: vTabsIcon },
		{ value: 'accordion', label: __('Accordion', 'flx-blocks'), icon: accordionIcon },
	];
	const layoutOptions = [
		{ value: 'tabs', label: __('Tabs', 'flx-blocks'), icon: tabsIcon },
		{ value: 'vtabs', label: __('Vertical Tabs', 'flx-blocks'), icon: vTabsIcon },
	];

	const initialTabOptions = times(titles.length, (n) => {
		return { value: n + 1, label: titles[n].text };
	});

	const mobileControls = (
		<ButtonGroup className={'fxb-tab-block-layout-select'} aria-label={__('Mobile Layout', 'flx-blocks')}>
			{map(mLayoutOptions, ({ label, value, icon }) => (
				<Button
					key={value}
					className="fxb-tab-block-layout-item"
					isSmall
					label={label}
					isPrimary={mobileLayout === value}
					aria-pressed={mobileLayout === value}
					onClick={() => setAttributes({ mobileLayout: value })}
				>
					{icon}
				</Button>
			))}
		</ButtonGroup>
	);
	const tabletControls = (
		<ButtonGroup className={'fxb-tab-block-layout-select'} aria-label={__('Tablet Layout', 'flx-blocks')}>
			{map(mLayoutOptions, ({ label, value, icon }) => (
				<Button
					key={value}
					className="fxb-tab-block-layout-item"
					isSmall
					label={label}
					isPrimary={tabletLayout === value}
					aria-pressed={tabletLayout === value}
					onClick={() => setAttributes({ tabletLayout: value })}
				>
					{icon}
				</Button>
			))}
		</ButtonGroup>
	);
	const deskControls = (
		<>
			<ButtonGroup className={'fxb-tab-block-layout-select'} aria-label={__('Layout', 'flx-blocks')}>
				{map(layoutOptions, ({ label, value, icon }) => (
					<Button
						key={value}
						className="fxb-tab-block-layout-item"
						isPrimary={layout === value}
						aria-pressed={layout === value}
						label={label}
						onClick={() => {
							setAttributes({
								layout: value,
							});
						}}
					>
						{icon}
					</Button>
				))}
			</ButtonGroup>
			<p className="flxbk-control-title" style={{ marginTop: '24px', marginBottom: '5px' }}>
				{__('Set initial Open Tab', 'flx-blocks')}
			</p>
			<Select
				value={initialTabOptions.filter(function (option) {
					return option.value === startTab;
				})}
				onChange={(selection) => {
					setAttributes({ startTab: selection.value });
				}}
				options={initialTabOptions}
				maxMenuHeight={300}
				placeholder={__('Select an initial tab', 'flx-blocks')}
			/>
		</>
	);

	const saveFontSize = (key, value) => {
		const ucKey = key.charAt(0).toUpperCase() + key.slice(1);

		setAttributes({
			[key]: String(value[0]),
			['tab' + ucKey]: String(value[1]),
			['mobile' + ucKey]: String(value[2]),
		});
	};

	const saveFontAttribute = (key, value) => {
		const ucKey = key.charAt(0).toUpperCase() + key.slice(1);

		setAttributes({
			[key]: value[0],
			['tab' + ucKey]: value[1],
			['mobile' + ucKey]: value[2],
		});
	};

	const renderTitles = (index, isLast = false) => {
		const subFont =
			subtitleFont && subtitleFont[0] && undefined !== subtitleFont[0].sizeType
				? subtitleFont
				: [
						{
							size: ['', '', ''],
							sizeType: 'px',
							lineHeight: ['', '', ''],
							lineType: 'px',
							letterSpacing: '',
							textTransform: '',
							family: '',
							google: false,
							style: '',
							weight: '',
							variant: '',
							subset: '',
							loadGoogle: true,
							padding: [0, 0, 0, 0],
							paddingControl: 'linked',
							margin: [0, 0, 0, 0],
							marginControl: 'linked',
						},
					];
		return (
			<Fragment>
				<li
					className={`fxt-title-item fxt-title-item-${index} fxt-tabs-svg-show-${
						titles[index] && titles[index].onlyIcon ? 'only' : 'always'
					} fxt-tabs-icon-side-${
						titles[index] && titles[index].iconSide ? titles[index].iconSide : 'right'
					} fxt-tabs-has-icon-${titles[index] && titles[index].icon ? 'true' : 'false'} fxt-tab-title-${
						1 + index === currentTab ? 'active' : 'inactive'
					}${enableSubtitle ? ' fxb-tabs-have-subtitle' : ''}`}
					style={{
						marginTop:
							'' !== previewTitleMarginTop
								? getSpacingOptionOutput(previewTitleMarginTop, previewTitleMarginUnit)
								: '',
						marginRight:
							(isLast && 'vtabs' !== layout) || ('tabs' === layout && widthType === 'percent')
								? '0px'
								: '' !== previewTitleMarginRight
									? getSpacingOptionOutput(previewTitleMarginRight, previewTitleMarginUnit)
									: '',
						marginBottom:
							'' !== previewTitleMarginBottom
								? getSpacingOptionOutput(previewTitleMarginBottom, previewTitleMarginUnit)
								: '',
						marginLeft:
							'tabs' === layout && widthType === 'percent'
								? '0px'
								: '' !== previewTitleMarginLeft
									? getSpacingOptionOutput(previewTitleMarginLeft, previewTitleMarginUnit)
									: '',
					}}
				>
					<div
						className={`fxt-tab-title fxt-tab-title-${1 + index}`}
						style={{
							backgroundColor: FlxbkColorOutput(titleBg),
							color: FlxbkColorOutput(titleColor),
							fontSize: previewFontSize ? getFontSizeOptionOutput(previewFontSize, sizeType) : undefined,
							lineHeight: previewLineHeight ? previewLineHeight + lineType : undefined,
							fontWeight,
							fontStyle,
							letterSpacing: letterSpacing + 'px',
							textTransform: textTransform ? textTransform : undefined,
							fontFamily: typography ? typography : '',
							borderTopWidth: previewTitleBorderWidthTop + previewTitleBorderWidthUnit,
							borderRightWidth: previewTitleBorderWidthRight + previewTitleBorderWidthUnit,
							borderBottomWidth: previewTitleBorderWidthBottom + previewTitleBorderWidthUnit,
							borderLeftWidth: previewTitleBorderWidthLeft + previewTitleBorderWidthUnit,
							borderTopLeftRadius: previewTitleRadiusTop + previewTitleBorderRadiusUnit,
							borderTopRightRadius: previewTitleRadiusRight + previewTitleBorderRadiusUnit,
							borderBottomRightRadius: previewTitleRadiusBottom + previewTitleBorderRadiusUnit,
							borderBottomLeftRadius: previewTitleRadiusLeft + previewTitleBorderRadiusUnit,
							paddingTop:
								'' !== previewTitlePaddingTop
									? getSpacingOptionOutput(previewTitlePaddingTop, previewTitlePaddingUnit)
									: undefined,
							paddingRight:
								'' !== previewTitlePaddingRight
									? getSpacingOptionOutput(previewTitlePaddingRight, previewTitlePaddingUnit)
									: undefined,
							paddingBottom:
								'' !== previewTitlePaddingBottom
									? getSpacingOptionOutput(previewTitlePaddingBottom, previewTitlePaddingUnit)
									: undefined,
							paddingLeft:
								'' !== previewTitlePaddingLeft
									? getSpacingOptionOutput(previewTitlePaddingLeft, previewTitlePaddingUnit)
									: undefined,
							borderColor: FlxbkColorOutput(titleBorder),
							marginRight: 'tabs' === layout && widthType === 'percent' ? gutter[0] + 'px' : undefined,
						}}
						onClick={() => setAttributes({ currentTab: 1 + index })}
						onKeyPress={() => setAttributes({ currentTab: 1 + index })}
						tabIndex="0"
						role="button"
					>
						{titles[index] && titles[index].icon && 'right' !== titles[index].iconSide && (
							<IconRender
								className={`fxt-tab-svg-icon fxt-tab-svg-icon-${titles[index].icon} fxt-title-svg-side-${titles[index].iconSide}`}
								name={titles[index].icon}
								size={!previewIconSize ? '14' : previewIconSize}
								htmltag="span"
							/>
						)}
						{(undefined === enableSubtitle || !enableSubtitle) && (
							<RichText
								tagName="div"
								placeholder={__('Tab Title', 'flx-blocks')}
								value={titles[index] && titles[index].text ? titles[index].text : ''}
								unstableOnFocus={() => setAttributes({ currentTab: 1 + index })}
								onChange={(value) => {
									saveArrayUpdate({ text: value }, index);
								}}
								allowedFormats={applyFilters('flxbk.whitelist_richtext_formats', [
									'flx/insert-dynamic',
									'core/bold',
									'core/italic',
									'core/strikethrough',
									'toolset/inline-field',
								])}
								className={'fxt-title-text'}
								style={{
									lineHeight: lineHeight + lineType,
								}}
								keepPlaceholderOnFocus
							/>
						)}
						{enableSubtitle && (
							<div className="fxb-tab-titles-wrap">
								<RichText
									tagName="div"
									placeholder={__('Tab Title', 'flx-blocks')}
									value={titles[index] && titles[index].text ? titles[index].text : ''}
									unstableOnFocus={() => setAttributes({ currentTab: 1 + index })}
									onChange={(value) => {
										saveArrayUpdate({ text: value }, index);
									}}
									allowedFormats={applyFilters('flxbk.whitelist_richtext_formats', [
										'flx/insert-dynamic',
										'core/bold',
										'core/italic',
										'core/strikethrough',
										'toolset/inline-field',
									])}
									className={'fxt-title-text'}
									style={{
										lineHeight: lineHeight + lineType,
									}}
									keepPlaceholderOnFocus
								/>
								<RichText
									tagName="div"
									placeholder={__('Tab subtitle', 'flx-blocks')}
									value={
										undefined !== titles[index] && undefined !== titles[index].subText
											? titles[index].subText
											: ''
									}
									unstableOnFocus={() => setAttributes({ currentTab: 1 + index })}
									onChange={(value) => {
										saveArrayUpdate({ subText: value }, index);
									}}
									allowedFormats={applyFilters('flxbk.whitelist_richtext_formats', [
										'flx/insert-dynamic',
										'core/bold',
										'core/italic',
										'core/strikethrough',
										'toolset/inline-field',
									])}
									className={'fxt-title-sub-text'}
									style={{
										fontWeight: subFont[0].weight,
										fontStyle: subFont[0].style,
										fontSize: previewSubFontSize
											? getFontSizeOptionOutput(previewSubFontSize, subFont[0].sizeType)
											: undefined,
										lineHeight: previewSubLineHeight
											? previewSubLineHeight + subFont[0].lineType
											: undefined,
										textTransform: subFont[0].textTransform ? subFont[0].textTransform : undefined,
										letterSpacing: subFont[0].letterSpacing + 'px',
										fontFamily: subFont[0].family ? subFont[0].family : '',
										padding: subFont[0].padding
											? subFont[0].padding[0] +
												'px ' +
												subFont[0].padding[1] +
												'px ' +
												subFont[0].padding[2] +
												'px ' +
												subFont[0].padding[3] +
												'px'
											: '',
										margin: subFont[0].margin
											? subFont[0].margin[0] +
												'px ' +
												subFont[0].margin[1] +
												'px ' +
												subFont[0].margin[2] +
												'px ' +
												subFont[0].margin[3] +
												'px'
											: '',
									}}
									keepPlaceholderOnFocus
								/>
							</div>
						)}
						{titles[index] && titles[index].icon && 'right' === titles[index].iconSide && (
							<IconRender
								className={`fxt-tab-svg-icon fxt-tab-svg-icon-${titles[index].icon} fxt-title-svg-side-${titles[index].iconSide}`}
								name={titles[index].icon}
								size={!previewIconSize ? '14' : previewIconSize}
								htmltag="span"
							/>
						)}
					</div>
					{isSelected && (
						<div className="flx-blocks-tab-item__control-menu">
							{index !== 0 && (
								<Button
									icon={'vtabs' === layout ? 'arrow-up' : 'arrow-left'}
									onClick={index === 0 ? undefined : onMoveBack(index)}
									className="flx-blocks-tab-item__move-back"
									label={
										'vtabs' === layout
											? __('Move Tab Up', 'flx-blocks')
											: __('Move Tab Back', 'flx-blocks')
									}
									aria-disabled={index === 0}
									disabled={index === 0}
								/>
							)}
							{index + 1 !== tabCount && (
								<Button
									icon={'vtabs' === layout ? 'arrow-down' : 'arrow-right'}
									onClick={index + 1 === tabCount ? undefined : onMoveForward(index)}
									className="flx-blocks-tab-item__move-forward"
									label={
										'vtabs' === layout
											? __('Move Tab Down', 'flx-blocks')
											: __('Move Tab Forward', 'flx-blocks')
									}
									aria-disabled={index + 1 === tabCount}
									disabled={index + 1 === tabCount}
								/>
							)}
							{tabCount > 1 && (
								<Button
									icon="no-alt"
									onClick={() => {
										const currentItems = filter(titles, (item, i) => index !== i);
										const newCount = tabCount - 1;
										let newStartTab;
										if (startTab === index + 1) {
											newStartTab = '';
										} else if (startTab > index + 1) {
											newStartTab = startTab - 1;
										} else {
											newStartTab = startTab;
										}
										removeTab(index);
										setAttributes({
											titles: currentItems,
											tabCount: newCount,
											currentTab: index === 0 ? 1 : index,
											startTab: newStartTab,
										});
										resetOrder();
									}}
									className="flx-blocks-tab-item__remove"
									label={__('Remove Tab', 'flx-blocks')}
									disabled={!currentTab === index + 1}
								/>
							)}
						</div>
					)}
				</li>
			</Fragment>
		);
	};
	const renderPreviewArray = <Fragment>{times(tabCount, (n) => renderTitles(n, tabCount - 1 === n))}</Fragment>;
	const renderAnchorSettings = (index) => {
		return (
			<FlxbkPanelBody
				title={__('Tab', 'flx-blocks') + ' ' + (index + 1) + ' ' + __('Anchor', 'flx-blocks')}
				initialOpen={false}
				panelName={'fxb-tab-anchor-' + index}
			>
				<TextControl
					label={__('HTML Anchor', 'flx-blocks')}
					help={__('Anchors lets you link directly to a tab.', 'flx-blocks')}
					value={titles[index] && titles[index].anchor ? titles[index].anchor : ''}
					onChange={(nextValue) => {
						nextValue = nextValue.replace(ANCHOR_REGEX, '-');
						saveArrayUpdate({ anchor: nextValue }, index);
					}}
				/>
			</FlxbkPanelBody>
		);
	};
	const renderTitleSettings = (index) => {
		return (
			<FlxbkPanelBody
				title={__('Tab', 'flx-blocks') + ' ' + (index + 1) + ' ' + __('Icon', 'flx-blocks')}
				initialOpen={false}
				panelName={'fxb-tab-icon-' + index}
			>
				<FlxbkIconPicker
					value={titles[index] && titles[index].icon ? titles[index].icon : ''}
					allowClear={true}
					onChange={(value) => {
						saveArrayUpdate({ icon: value }, index);
					}}
				/>
				<SelectControl
					label={__('Icon Location', 'flx-blocks')}
					value={titles[index] && titles[index].iconSide ? titles[index].iconSide : 'right'}
					options={[
						{ value: 'right', label: __('Right', 'flx-blocks') },
						{ value: 'left', label: __('Left', 'flx-blocks') },
						{ value: 'top', label: __('Top', 'flx-blocks') },
					]}
					onChange={(value) => {
						saveArrayUpdate({ iconSide: value }, index);
					}}
				/>
				<ToggleControl
					label={__('Show Only Icon?', 'flx-blocks')}
					checked={titles[index] && titles[index].onlyIcon ? titles[index].onlyIcon : false}
					onChange={(value) => {
						saveArrayUpdate({ onlyIcon: value }, index);
					}}
				/>
			</FlxbkPanelBody>
		);
	};
	const normalSettings = (
		<Fragment>
			<PopColorControl
				label={__('Title Color', 'flx-blocks')}
				value={titleColor ? titleColor : ''}
				default={''}
				onChange={(value) => setAttributes({ titleColor: value })}
			/>
			<PopColorControl
				label={__('Title Background', 'flx-blocks')}
				value={titleBg ? titleBg : ''}
				default={''}
				onChange={(value) => setAttributes({ titleBg: value })}
			/>
			<PopColorControl
				label={__('Title Border Color', 'flx-blocks')}
				value={titleBorder ? titleBorder : ''}
				default={''}
				onChange={(value) => setAttributes({ titleBorder: value })}
			/>
		</Fragment>
	);
	const hoverSettings = (
		<Fragment>
			<PopColorControl
				label={__('Hover Color', 'flx-blocks')}
				value={titleColorHover ? titleColorHover : ''}
				default={''}
				onChange={(value) => setAttributes({ titleColorHover: value })}
			/>
			<PopColorControl
				label={__('Hover Background', 'flx-blocks')}
				value={titleBgHover ? titleBgHover : ''}
				default={''}
				onChange={(value) => setAttributes({ titleBgHover: value })}
			/>
			<PopColorControl
				label={__('Hover Border Color', 'flx-blocks')}
				value={titleBorderHover ? titleBorderHover : ''}
				default={''}
				onChange={(value) => setAttributes({ titleBorderHover: value })}
			/>
		</Fragment>
	);
	const activeSettings = (
		<Fragment>
			<PopColorControl
				label={__('Active Color', 'flx-blocks')}
				value={titleColorActive ? titleColorActive : ''}
				default={''}
				onChange={(value) => setAttributes({ titleColorActive: value })}
			/>
			<PopColorControl
				label={__('Active Background', 'flx-blocks')}
				value={titleBgActive ? titleBgActive : ''}
				default={'#ffffff'}
				onChange={(value) => setAttributes({ titleBgActive: value })}
			/>
			<PopColorControl
				label={__('Active Border Color', 'flx-blocks')}
				value={titleBorderActive ? titleBorderActive : ''}
				default={'#dee2e6'}
				onChange={(value) => setAttributes({ titleBorderActive: value })}
			/>
		</Fragment>
	);

	const subtitleNormalSettings = (
		<Fragment>
			<PopColorControl
				label={__('Subtitle Color', 'flx-blocks')}
				value={subtitleColor ? subtitleColor : ''}
				default={''}
				onChange={(value) => setAttributes({ subtitleColor: value })}
			/>
		</Fragment>
	);
	const subtitleHoverSettings = (
		<Fragment>
			<PopColorControl
				label={__('Hover Color', 'flx-blocks')}
				value={subtitleColorHover ? subtitleColorHover : ''}
				default={''}
				onChange={(value) => setAttributes({ subtitleColorHover: value })}
			/>
		</Fragment>
	);
	const subtitleActiveSettings = (
		<Fragment>
			<PopColorControl
				label={__('Active Color', 'flx-blocks')}
				value={subtitleColorActive ? subtitleColorActive : ''}
				default={''}
				onChange={(value) => setAttributes({ subtitleColorActive: value })}
			/>
		</Fragment>
	);

	const percentDesktopContent = (
		<Fragment>
			<RangeControl
				label={__('Columns', 'flx-blocks')}
				value={tabWidth && undefined !== tabWidth[0] ? tabWidth[0] : ''}
				onChange={(value) => setAttributes({ tabWidth: [value, tabWidth[1], tabWidth[2]] })}
				min={1}
				max={8}
				step={1}
			/>
			<RangeControl
				label={__('Gutter', 'flx-blocks')}
				value={gutter && undefined !== gutter[0] ? gutter[0] : ''}
				onChange={(value) => setAttributes({ gutter: [value, gutter[1], gutter[2]] })}
				min={0}
				max={50}
				step={1}
			/>
		</Fragment>
	);
	const percentTabletContent = (
		<Fragment>
			<RangeControl
				label={__('Tablet Columns', 'flx-blocks')}
				value={tabWidth && undefined !== tabWidth[1] ? tabWidth[1] : ''}
				onChange={(value) => setAttributes({ tabWidth: [tabWidth[0], value, tabWidth[2]] })}
				min={1}
				max={8}
				step={1}
			/>
			<RangeControl
				label={__('Tablet Gutter', 'flx-blocks')}
				value={gutter && undefined !== gutter[1] ? gutter[1] : ''}
				onChange={(value) => setAttributes({ gutter: [gutter[0], value, gutter[2]] })}
				min={0}
				max={50}
				step={1}
			/>
		</Fragment>
	);
	const percentMobileContent = (
		<Fragment>
			<RangeControl
				label={__('Mobile Columns', 'flx-blocks')}
				value={tabWidth && undefined !== tabWidth[2] ? tabWidth[2] : ''}
				onChange={(value) => setAttributes({ tabWidth: [tabWidth[0], tabWidth[1], value] })}
				min={1}
				max={8}
				step={1}
			/>
			<RangeControl
				label={__('Mobile Gutter', 'flx-blocks')}
				value={gutter && undefined !== gutter[2] ? gutter[2] : ''}
				onChange={(value) => setAttributes({ gutter: [gutter[0], gutter[1], value] })}
				min={0}
				max={50}
				step={1}
			/>
		</Fragment>
	);

	//generate accordion ordering for tab title and content elements
	let accordionOrderStyle = '';
	if (isAccordionPreview) {
		times(tabCount, (n) => {
			const output = `
					.fxt-title-item-${n} {
						order: ${2 * n}
					}
					.fxt-inner-tab-${n + 1} {
						order: ${2 * n + 1}
					}
				`;
			accordionOrderStyle += output;
		});
	}

	const renderCSS = (
		<style>
			{`.fxt-tabs-id${uniqueID} .fxt-title-item:hover .fxt-tab-title {
					${titleColorHover ? 'color:' + FlxbkColorOutput(titleColorHover) + '!important;' : ''}
					${titleBorderHover ? 'border-color:' + FlxbkColorOutput(titleBorderHover) + '!important;' : ''}
					${titleBgHover ? 'background-color:' + FlxbkColorOutput(titleBgHover) + '!important;' : ''}
				}
				.fxt-tabs-id${uniqueID} .fxt-title-item.fxt-tab-title-active .fxt-tab-title, .fxt-tabs-id${uniqueID} .fxt-title-item.fxt-tab-title-active:hover .fxt-tab-title {
					${titleColorActive ? 'color:' + FlxbkColorOutput(titleColorActive) + '!important;' : ''}
					${titleBorderActive ? 'border-color:' + FlxbkColorOutput(titleBorderActive) + '!important;' : ''}
					${titleBgActive ? 'background-color:' + FlxbkColorOutput(titleBgActive) + '!important;' : ''}
				}
				.fxt-tabs-id${uniqueID} .fxt-title-item .fxt-title-sub-text {
					${subtitleColor ? 'color:' + FlxbkColorOutput(subtitleColor) + '!important;' : ''}
				}
				.fxt-tabs-id${uniqueID} .fxt-title-item:hover .fxt-title-sub-text {
					${subtitleColorHover ? 'color:' + FlxbkColorOutput(subtitleColorHover) + '!important;' : ''}
				}
				.fxt-tabs-id${uniqueID} .fxt-title-item.fxt-tab-title-active .fxt-title-sub-text, .fxt-tabs-id${uniqueID} .fxt-title-item.fxt-tab-title-active:hover .fxt-title-sub-text {
					${subtitleColorActive ? 'color:' + FlxbkColorOutput(subtitleColorActive) + '!important;' : ''}
				}
				.fxt-tabs-id${uniqueID} > .fxt-tabs-wrap > .fxt-tabs-content-wrap > .block-editor-inner-blocks > .block-editor-block-list__layout > [data-tab="${currentTab}"] {
					display: block;
				}
				${accordionOrderStyle}
				`}
		</style>
	);

	const ref = useRef();
	const blockProps = useBlockProps({
		ref,
		className: 'wp-block-flx-tabs',
	});

	return (
		<div {...blockProps}>
			{renderCSS}
			<BlockControls>
				<BlockAlignmentToolbar
					value={blockAlignment}
					controls={['center', 'wide', 'full']}
					onChange={(value) => setAttributes({ blockAlignment: value })}
				/>
				<AlignmentToolbar
					value={tabAlignment}
					onChange={(nextAlign) => {
						setAttributes({ tabAlignment: nextAlign });
					}}
				/>
				<CopyPasteAttributes
					attributes={attributes}
					excludedAttrs={nonTransAttrs}
					defaultAttributes={metadata.attributes}
					blockSlug={metadata.name}
					onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
				/>
				<ToolbarGroup group="add-block">
					<ToolbarButton
						className="fxb-icons-add-icon"
						icon={plusCircle}
						onClick={() => {
							const newBlock = createBlock('flx/tab', { id: tabCount + 1 });
							insertTab(newBlock);
							//wp.data.dispatch( 'core/block-editor' ).insertBlock( newBlock, clientId );
							const newtabs = titles;
							// Translators: %d is the tab number.
							newtabs.push({
								// translators: %d: Tab number
								text: sprintf(__('Tab %d', 'flx-blocks'), tabCount + 1),
								icon: titles[0].icon,
								iconSide: titles[0].iconSide,
								onlyIcon: titles[0].onlyIcon,
								subText: '',
							});
							setAttributes({ titles: newtabs, tabCount: tabCount + 1 });
							saveArrayUpdate({ iconSide: titles[0].iconSide }, 0);
						}}
						label={__('Add Tab', 'flx-blocks')}
						showTooltip={true}
					/>
					{tabCount > 1 && (
						<ToolbarDropdownMenu icon="move" label={__('Move Tabs', 'flx-blocks')}>
							{({ onClose }) => (
								<MenuGroup>
									{/* Move Tab Back/Up */}
									{currentTab !== 1 && (
										<MenuItem
											icon={'vtabs' === layout ? 'arrow-up' : 'arrow-left'}
											onClick={currentTab === 1 ? undefined : onMoveBack(currentTab - 1)}
										>
											{'vtabs' === layout
												? __('Move Tab Up', 'flx-blocks')
												: __('Move Tab Back', 'flx-blocks')}
										</MenuItem>
									)}
									{/* Move Tab Forward/Down */}
									{currentTab !== tabCount && (
										<MenuItem
											icon={'vtabs' === layout ? 'arrow-down' : 'arrow-right'}
											onClick={
												currentTab === tabCount ? undefined : onMoveForward(currentTab - 1)
											}
										>
											{'vtabs' === layout
												? __('Move Tab Down', 'flx-blocks')
												: __('Move Tab Forward', 'flx-blocks')}
										</MenuItem>
									)}
									{/* Remove Tab */}
									{tabCount > 1 && (
										<MenuItem
											icon="no-alt"
											onClick={() => {
												const currentItems = filter(titles, (item, i) => currentTab !== i);
												const newCount = tabCount - 1;
												let newStartTab;
												if (startTab === currentTab + 1) {
													newStartTab = '';
												} else if (startTab > currentTab + 1) {
													newStartTab = startTab - 1;
												} else {
													newStartTab = startTab;
												}
												removeTab(currentTab);
												setAttributes({
													titles: currentItems,
													tabCount: newCount,
													currentTab: currentTab === 0 ? 1 : currentTab,
													startTab: newStartTab,
												});
												resetOrder();
												onClose();
											}}
										>
											{__('Remove Tab', 'flx-blocks')}
										</MenuItem>
									)}
								</MenuGroup>
							)}
						</ToolbarDropdownMenu>
					)}
				</ToolbarGroup>
			</BlockControls>
			{showSettings('allSettings', 'flx/tabs') && (
				<InspectorControls>
					<InspectorControlTabs
						panelName={'tabs'}
						setActiveTab={(value) => setActiveTab(value)}
						activeTab={activeTab}
					/>

					{activeTab === 'general' && (
						<>
							{showSettings('tabLayout', 'flx/tabs') && (
								<FlxbkPanelBody panelName={'fxb-tab-layout-select'}>
									<SmallResponsiveControl
										label={__('Layout', 'flx-blocks')}
										desktopChildren={deskControls}
										tabletChildren={tabletControls}
										mobileChildren={mobileControls}
									></SmallResponsiveControl>
									{previewLayout == 'accordion' && (
										<ToggleControl
											label={__('Accordions close when another opens', 'flx-blocks')}
											checked={linkPaneCollapse}
											onChange={(value) => setAttributes({ linkPaneCollapse: value })}
										/>
									)}
								</FlxbkPanelBody>
							)}
							{!showSettings('tabLayout', 'flx/tabs') && (
								<FlxbkPanelBody panelName={'fxb-tab-layout'}>
									<h2>{__('Set Initial Open Tab', 'flx-blocks')}</h2>
									<ButtonGroup aria-label={__('Initial Open Tab', 'flx-blocks')}>
										{times(tabCount, (n) => (
											<Button
												key={n + 1}
												className="fxt-init-open-tab"
												isSmall
												isPrimary={startTab === n + 1}
												aria-pressed={startTab === n + 1}
												onClick={() => setAttributes({ startTab: n + 1 })}
											>
												{__('Tab') + ' ' + (n + 1)}
											</Button>
										))}
									</ButtonGroup>
								</FlxbkPanelBody>
							)}
							{showSettings('tabContent', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Content Settings', 'flx-blocks')}
									panelName={'fxb-tab-content-settings'}
								>
									<PopColorControl
										label={__('Content Background', 'flx-blocks')}
										value={contentBgColor ? contentBgColor : ''}
										default={''}
										onChange={(value) => setAttributes({ contentBgColor: value })}
									/>

									<ResponsiveBorderControl
										label={__('Border', 'flx-blocks')}
										value={contentBorderStyles}
										tabletValue={tabletContentBorderStyles}
										mobileValue={mobileContentBorderStyles}
										onChange={(value) => setAttributes({ contentBorderStyles: value })}
										onChangeTablet={(value) => setAttributes({ tabletContentBorderStyles: value })}
										onChangeMobile={(value) => setAttributes({ mobileContentBorderStyles: value })}
									/>
									<ResponsiveMeasurementControls
										label={__('Border Radius', 'flx-blocks')}
										value={contentBorderRadius}
										tabletValue={tabletContentBorderRadius}
										mobileValue={mobileContentBorderRadius}
										onChange={(value) => setAttributes({ contentBorderRadius: value })}
										onChangeTablet={(value) => setAttributes({ tabletContentBorderRadius: value })}
										onChangeMobile={(value) => setAttributes({ mobileContentBorderRadius: value })}
										min={0}
										max={
											contentBorderRadiusUnit === 'em' || contentBorderRadiusUnit === 'rem'
												? 24
												: 100
										}
										step={
											contentBorderRadiusUnit === 'em' || contentBorderRadiusUnit === 'rem'
												? 0.1
												: 1
										}
										unit={contentBorderRadiusUnit}
										units={['px', 'em', 'rem', '%']}
										onUnit={(value) => setAttributes({ contentBorderRadiusUnit: value })}
										isBorderRadius={true}
										allowEmpty={true}
									/>
								</FlxbkPanelBody>
							)}
						</>
					)}

					{activeTab === 'style' && (
						<>
							{showSettings('titleColor', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Tab Title Color Settings', 'flx-blocks')}
									panelName={'fxb-tab-title-color'}
								>
									<TabPanel
										className="fxt-inspect-tabs fxt-no-ho-ac-tabs fxt-hover-tabs"
										activeClass="active-tab"
										tabs={[
											{
												name: 'normal',
												title: __('Normal', 'flx-blocks'),
												className: 'fxt-normal-tab',
											},
											{
												name: 'hover',
												title: __('Hover', 'flx-blocks'),
												className: 'fxt-hover-tab',
											},
											{
												name: 'active',
												title: __('Active', 'flx-blocks'),
												className: 'fxt-active-tab',
											},
										]}
									>
										{(tab) => {
											let tabout;
											if (tab.name) {
												if ('hover' === tab.name) {
													tabout = hoverSettings;
												} else if ('active' === tab.name) {
													tabout = activeSettings;
												} else {
													tabout = normalSettings;
												}
											}
											return (
												<div className={tab.className} key={tab.className}>
													{tabout}
												</div>
											);
										}}
									</TabPanel>
								</FlxbkPanelBody>
							)}
							{showSettings('subtitle', 'flx/tabs') && enableSubtitle && (
								<FlxbkPanelBody
									title={__('Tab Subtitle Color Settings', 'flx-blocks')}
									panelName={'fxb-tab-subtitle-color'}
								>
									<TabPanel
										className="fxt-inspect-tabs fxt-no-ho-ac-tabs fxt-hover-tabs"
										activeClass="active-tab"
										tabs={[
											{
												name: 'normal',
												title: __('Normal', 'flx-blocks'),
												className: 'fxt-normal-tab',
											},
											{
												name: 'hover',
												title: __('Hover', 'flx-blocks'),
												className: 'fxt-hover-tab',
											},
											{
												name: 'active',
												title: __('Active', 'flx-blocks'),
												className: 'fxt-active-tab',
											},
										]}
									>
										{(tab) => {
											let tabout;
											if (tab.name) {
												if ('hover' === tab.name) {
													tabout = subtitleHoverSettings;
												} else if ('active' === tab.name) {
													tabout = subtitleActiveSettings;
												} else {
													tabout = subtitleNormalSettings;
												}
											}
											return (
												<div className={tab.className} key={tab.className}>
													{tabout}
												</div>
											);
										}}
									</TabPanel>
								</FlxbkPanelBody>
							)}
							{showSettings('titleSpacing', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Tab Title Width/Spacing/Border', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-tab-title-spacing'}
								>
									{'vtabs' === previewLayout && (
										<ResponsiveRangeControls
											label={__('Tab Title Width', 'flx-blocks')}
											value={
												undefined !== verticalTabWidth && undefined !== verticalTabWidth[0]
													? verticalTabWidth[0]
													: ''
											}
											onChange={(value) => {
												setAttributes({
													verticalTabWidth: [
														value,
														undefined !== verticalTabWidth &&
														undefined !== verticalTabWidth[1]
															? verticalTabWidth[1]
															: '',
														undefined !== verticalTabWidth &&
														undefined !== verticalTabWidth[2]
															? verticalTabWidth[2]
															: '',
													],
												});
											}}
											tabletValue={
												undefined !== verticalTabWidth && undefined !== verticalTabWidth[1]
													? verticalTabWidth[1]
													: ''
											}
											onChangeTablet={(value) => {
												setAttributes({
													verticalTabWidth: [
														undefined !== verticalTabWidth &&
														undefined !== verticalTabWidth[0]
															? verticalTabWidth[0]
															: '',
														value,
														undefined !== verticalTabWidth &&
														undefined !== verticalTabWidth[2]
															? verticalTabWidth[2]
															: '',
													],
												});
											}}
											mobileValue={
												undefined !== verticalTabWidth && undefined !== verticalTabWidth[2]
													? verticalTabWidth[2]
													: ''
											}
											onChangeMobile={(value) => {
												setAttributes({
													verticalTabWidth: [
														undefined !== verticalTabWidth &&
														undefined !== verticalTabWidth[0]
															? verticalTabWidth[0]
															: '',
														undefined !== verticalTabWidth &&
														undefined !== verticalTabWidth[1]
															? verticalTabWidth[1]
															: '',
														value,
													],
												});
											}}
											min={0}
											max={verticalTabWidthUnit === 'px' ? 2000 : 100}
											step={1}
											unit={verticalTabWidthUnit ? verticalTabWidthUnit : '%'}
											onUnit={(value) => {
												setAttributes({ verticalTabWidthUnit: value });
											}}
											units={['px', '%', 'vw']}
										/>
									)}
									{'tabs' === layout && (
										<Fragment>
											<h2>{__('Tab Title Width', 'flx-blocks')}</h2>
											<TabPanel
												className="fxt-inspect-tabs fxt-hover-tabs"
												activeClass="active-tab"
												initialTabName={widthType}
												onSelect={(value) => {
													if (value !== widthType) {
														setAttributes({ widthType: value });
													}
												}}
												tabs={[
													{
														name: 'normal',
														title: __('Normal', 'flx-blocks'),
														className: 'fxt-normal-tab',
													},
													{
														name: 'percent',
														title: __('% Width', 'flx-blocks'),
														className: 'fxt-hover-tab',
													},
												]}
											>
												{(tab) => {
													let tabout;
													if (tab.name) {
														if ('percent' === tab.name) {
															tabout = (
																<Fragment>
																	<SmallResponsiveControl
																		desktopChildren={percentDesktopContent}
																		tabletChildren={percentTabletContent}
																		mobileChildren={percentMobileContent}
																	></SmallResponsiveControl>

																	<ResponsiveMeasureRangeControl
																		label={__('Title Padding', 'flx-blocks')}
																		value={titlePadding}
																		onChange={(value) =>
																			setAttributes({ titlePadding: value })
																		}
																		tabletValue={tabletTitlePadding}
																		onChangeTablet={(value) =>
																			setAttributes({ tabletTitlePadding: value })
																		}
																		mobileValue={mobileTitlePadding}
																		onChangeMobile={(value) =>
																			setAttributes({ mobileTitlePadding: value })
																		}
																		min={
																			titlePaddingUnit === 'em' ||
																			titlePaddingUnit === 'rem'
																				? 0
																				: 0
																		}
																		max={
																			titlePaddingUnit === 'em' ||
																			titlePaddingUnit === 'rem'
																				? 12
																				: 999
																		}
																		step={
																			titlePaddingUnit === 'em' ||
																			titlePaddingUnit === 'rem'
																				? 0.1
																				: 1
																		}
																		unit={titlePaddingUnit}
																		units={['px', 'em', 'rem']}
																		onUnit={(value) =>
																			setAttributes({ titlePaddingUnit: value })
																		}
																	/>
																	<ResponsiveMeasureRangeControl
																		label={__('Title Margin', 'flx-blocks')}
																		value={titleMargin}
																		onChange={(value) =>
																			setAttributes({ titleMargin: value })
																		}
																		tabletValue={tabletTitleMargin}
																		onChangeTablet={(value) =>
																			setAttributes({ tabletTitleMargin: value })
																		}
																		mobileValue={mobileTitleMargin}
																		onChangeMobile={(value) =>
																			setAttributes({ mobileTitleMargin: value })
																		}
																		min={
																			titleMarginUnit === 'em' ||
																			titleMarginUnit === 'rem'
																				? -2
																				: -999
																		}
																		max={
																			titleMarginUnit === 'em' ||
																			titleMarginUnit === 'rem'
																				? 12
																				: 999
																		}
																		step={
																			titleMarginUnit === 'em' ||
																			titleMarginUnit === 'rem'
																				? 0.1
																				: 1
																		}
																		unit={titleMarginUnit}
																		units={['px', 'em', 'rem']}
																		onUnit={(value) =>
																			setAttributes({ titleMarginUnit: value })
																		}
																		allowAuto={true}
																	/>
																	{__(
																		'Left & right title margins are ignored in % width tabs',
																		'flx-blocks'
																	)}

																	<br />
																	<br />
																</Fragment>
															);
														} else {
															tabout = (
																<Fragment>
																	<ResponsiveMeasureRangeControl
																		label={__('Title Padding', 'flx-blocks')}
																		value={titlePadding}
																		onChange={(value) =>
																			setAttributes({ titlePadding: value })
																		}
																		tabletValue={tabletTitlePadding}
																		onChangeTablet={(value) =>
																			setAttributes({ tabletTitlePadding: value })
																		}
																		mobileValue={mobileTitlePadding}
																		onChangeMobile={(value) =>
																			setAttributes({ mobileTitlePadding: value })
																		}
																		min={
																			titlePaddingUnit === 'em' ||
																			titlePaddingUnit === 'rem'
																				? -2
																				: -999
																		}
																		max={
																			titlePaddingUnit === 'em' ||
																			titlePaddingUnit === 'rem'
																				? 12
																				: 999
																		}
																		step={
																			titlePaddingUnit === 'em' ||
																			titlePaddingUnit === 'rem'
																				? 0.1
																				: 1
																		}
																		unit={titlePaddingUnit}
																		units={['px', 'em', 'rem']}
																		onUnit={(value) =>
																			setAttributes({ titlePaddingUnit: value })
																		}
																	/>
																	<ResponsiveMeasureRangeControl
																		label={__('Title Margin', 'flx-blocks')}
																		value={titleMargin}
																		onChange={(value) =>
																			setAttributes({ titleMargin: value })
																		}
																		tabletValue={tabletTitleMargin}
																		onChangeTablet={(value) =>
																			setAttributes({ tabletTitleMargin: value })
																		}
																		mobileValue={mobileTitleMargin}
																		onChangeMobile={(value) =>
																			setAttributes({ mobileTitleMargin: value })
																		}
																		min={
																			titleMarginUnit === 'em' ||
																			titleMarginUnit === 'rem'
																				? -2
																				: -999
																		}
																		max={
																			titleMarginUnit === 'em' ||
																			titleMarginUnit === 'rem'
																				? 12
																				: 999
																		}
																		step={
																			titleMarginUnit === 'em' ||
																			titleMarginUnit === 'rem'
																				? 0.1
																				: 1
																		}
																		unit={titleMarginUnit}
																		units={['px', 'em', 'rem']}
																		onUnit={(value) =>
																			setAttributes({ titleMarginUnit: value })
																		}
																	/>
																</Fragment>
															);
														}
													}
													return (
														<div className={tab.className} key={tab.className}>
															{tabout}
														</div>
													);
												}}
											</TabPanel>
										</Fragment>
									)}
									{'tabs' !== layout && (
										<Fragment>
											<ResponsiveMeasureRangeControl
												label={__('Title Padding', 'flx-blocks')}
												value={titlePadding}
												onChange={(value) => setAttributes({ titlePadding: value })}
												tabletValue={tabletTitlePadding}
												onChangeTablet={(value) => setAttributes({ tabletTitlePadding: value })}
												mobileValue={mobileTitlePadding}
												onChangeMobile={(value) => setAttributes({ mobileTitlePadding: value })}
												min={titlePaddingUnit === 'em' || titlePaddingUnit === 'rem' ? 0 : 0}
												max={titlePaddingUnit === 'em' || titlePaddingUnit === 'rem' ? 12 : 999}
												step={titlePaddingUnit === 'em' || titlePaddingUnit === 'rem' ? 0.1 : 1}
												unit={titlePaddingUnit}
												units={['px', 'em', 'rem']}
												onUnit={(value) => setAttributes({ titlePaddingUnit: value })}
											/>
											<ResponsiveMeasureRangeControl
												label={__('Title Margin', 'flx-blocks')}
												value={titleMargin}
												onChange={(value) => setAttributes({ titleMargin: value })}
												tabletValue={tabletTitleMargin}
												onChangeTablet={(value) => setAttributes({ tabletTitleMargin: value })}
												mobileValue={mobileTitleMargin}
												onChangeMobile={(value) => setAttributes({ mobileTitleMargin: value })}
												min={titleMarginUnit === 'em' || titleMarginUnit === 'rem' ? -2 : -999}
												max={titleMarginUnit === 'em' || titleMarginUnit === 'rem' ? 12 : 999}
												step={titleMarginUnit === 'em' || titleMarginUnit === 'rem' ? 0.1 : 1}
												unit={titleMarginUnit}
												units={['px', 'em', 'rem']}
												onUnit={(value) => setAttributes({ titleMarginUnit: value })}
												allowAuto={true}
											/>
										</Fragment>
									)}
									<ResponsiveMeasurementControls
										label={__('Title Border Width', 'flx-blocks')}
										value={titleBorderWidth}
										tabletValue={tabletTitleBorderWidth}
										mobileValue={mobileTitleBorderWidth}
										onChange={(value) => setAttributes({ titleBorderWidth: value })}
										onChangeTablet={(value) => setAttributes({ tabletTitleBorderWidth: value })}
										onChangeMobile={(value) => setAttributes({ mobileTitleBorderWidth: value })}
										min={0}
										max={titleBorderWidthUnit === 'em' || titleBorderWidthUnit === 'rem' ? 24 : 100}
										step={titleBorderWidthUnit === 'em' || titleBorderWidthUnit === 'rem' ? 0.1 : 1}
										unit={titleBorderWidthUnit}
										units={['px', 'em', 'rem']}
										onUnit={(value) => setAttributes({ titleBorderWidthUnit: value })}
										allowEmpty={true}
									/>
									<ResponsiveMeasurementControls
										label={__('Title Border Radius', 'flx-blocks')}
										value={titleBorderRadius}
										tabletValue={tabletTitleBorderRadius}
										mobileValue={mobileTitleBorderRadius}
										onChange={(value) => setAttributes({ titleBorderRadius: value })}
										onChangeTablet={(value) => setAttributes({ tabletTitleBorderRadius: value })}
										onChangeMobile={(value) => setAttributes({ mobileTitleBorderRadius: value })}
										min={0}
										max={
											titleBorderRadiusUnit === 'em' || titleBorderRadiusUnit === 'rem' ? 24 : 100
										}
										step={
											titleBorderRadiusUnit === 'em' || titleBorderRadiusUnit === 'rem' ? 0.1 : 1
										}
										unit={titleBorderRadiusUnit}
										units={['px', 'em', 'rem', '%']}
										onUnit={(value) => setAttributes({ titleBorderRadiusUnit: value })}
										isBorderRadius={true}
										allowEmpty={true}
									/>
								</FlxbkPanelBody>
							)}
							{showSettings('titleFont', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Tab Title Font Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-tab-title-font'}
								>
									<TypographyControls
										fontGroup={'body'}
										fontSize={[size, tabSize, mobileSize]}
										onFontSize={(value) => saveFontSize('size', value)}
										fontSizeType={sizeType ? sizeType : 'px'}
										onFontSizeType={(value) => setAttributes({ sizeType: value })}
										lineHeight={[lineHeight, tabLineHeight, mobileLineHeight]}
										onLineHeight={(value) => saveFontAttribute('lineHeight', value)}
										lineHeightType={lineType ? lineType : 'px'}
										onLineHeightType={(value) => setAttributes({ lineType: value })}
										fontFamily={typography}
										onFontFamily={(value) => setAttributes({ typography: value })}
										googleFont={googleFont}
										onFontChange={(select) => {
											setAttributes({
												typography: select.value,
												googleFont: select.google,
											});
										}}
										onGoogleFont={(value) => setAttributes({ googleFont: value })}
										loadGoogleFont={loadGoogleFont}
										onLoadGoogleFont={(value) => setAttributes({ loadGoogleFont: value })}
										fontVariant={fontVariant}
										onFontVariant={(value) => setAttributes({ fontVariant: value })}
										fontWeight={fontWeight}
										onFontWeight={(value) => setAttributes({ fontWeight: value })}
										fontStyle={fontStyle}
										onFontStyle={(value) => setAttributes({ fontStyle: value })}
										fontSubset={fontSubset}
										onFontSubset={(value) => setAttributes({ fontSubset: value })}
										textTransform={textTransform}
										onTextTransform={(value) => setAttributes({ textTransform: value })}
										letterSpacing={letterSpacing ? letterSpacing : ''}
										onLetterSpacing={(value) => setAttributes({ letterSpacing: value })}
									/>
								</FlxbkPanelBody>
							)}
							{showSettings('titleIcon', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Tab Title Icon Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-tab-title-icon'}
								>
									<ResponsiveRangeControls
										label={__('Icon Size', 'flx-blocks')}
										value={undefined !== iSize ? iSize : ''}
										onChange={(value) => setAttributes({ iSize: value })}
										tabletValue={undefined !== tabletISize ? tabletISize : ''}
										onChangeTablet={(value) => setAttributes({ tabletISize: value })}
										mobileValue={undefined !== mobileISize ? mobileISize : ''}
										onChangeMobile={(value) => setAttributes({ mobileISize: value })}
										min={2}
										max={120}
										step={1}
										unit={'px'}
										showUnit={true}
										units={['px']}
									/>
									{times(tabCount, (n) => renderTitleSettings(n))}
								</FlxbkPanelBody>
							)}
							{showSettings('subtitle', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Tab Subtitle Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-tab-subtitle-settings'}
								>
									<ToggleControl
										label={__('Show Subtitles?', 'flx-blocks')}
										checked={undefined !== enableSubtitle ? enableSubtitle : false}
										onChange={(value) => {
											setAttributes({ enableSubtitle: value });
										}}
									/>
									{enableSubtitle && (
										<TypographyControls
											fontGroup={'body'}
											fontSize={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].size
													? subtitleFont[0].size
													: ['', '', '']
											}
											onFontSize={(value) => saveSubtitleFont({ size: value })}
											fontSizeType={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].sizeType
													? subtitleFont[0].sizeType
													: 'px'
											}
											onFontSizeType={(value) => saveSubtitleFont({ sizeType: value })}
											lineHeight={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].lineHeight
													? subtitleFont[0].lineHeight
													: ['', '', '']
											}
											onLineHeight={(value) => saveSubtitleFont({ lineHeight: value })}
											lineHeightType={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].lineType
													? subtitleFont[0].lineType
													: 'px'
											}
											onLineHeightType={(value) => saveSubtitleFont({ lineType: value })}
											letterSpacing={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].letterSpacing
													? subtitleFont[0].letterSpacing
													: ''
											}
											onLetterSpacing={(value) => saveSubtitleFont({ letterSpacing: value })}
											fontFamily={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].family
													? subtitleFont[0].family
													: ''
											}
											onFontFamily={(value) => saveSubtitleFont({ family: value })}
											onFontChange={(select) => {
												saveSubtitleFont({
													family: select.value,
													google: select.google,
												});
											}}
											onFontArrayChange={(values) => saveSubtitleFont(values)}
											googleFont={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].google
													? subtitleFont[0].google
													: false
											}
											onGoogleFont={(value) => saveSubtitleFont({ google: value })}
											loadGoogleFont={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].loadGoogle
													? subtitleFont[0].loadGoogle
													: true
											}
											onLoadGoogleFont={(value) => saveSubtitleFont({ loadGoogle: value })}
											fontVariant={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].variant
													? subtitleFont[0].variant
													: ''
											}
											onFontVariant={(value) => saveSubtitleFont({ variant: value })}
											fontWeight={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].weight
													? subtitleFont[0].weight
													: ''
											}
											onFontWeight={(value) => saveSubtitleFont({ weight: value })}
											fontStyle={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].style
													? subtitleFont[0].style
													: ''
											}
											onFontStyle={(value) => saveSubtitleFont({ style: value })}
											fontSubset={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].subset
													? subtitleFont[0].subset
													: ''
											}
											onFontSubset={(value) => saveSubtitleFont({ subset: value })}
											textTransform={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].textTransform
													? subtitleFont[0].textTransform
													: ''
											}
											onTextTransform={(value) => saveSubtitleFont({ textTransform: value })}
											padding={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].padding
													? subtitleFont[0].padding
													: [0, 0, 0, 0]
											}
											onPadding={(value) => saveSubtitleFont({ padding: value })}
											paddingControl={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].paddingControl
													? subtitleFont[0].paddingControl
													: 'linked'
											}
											onPaddingControl={(value) => saveSubtitleFont({ paddingControl: value })}
											margin={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].margin
													? subtitleFont[0].margin
													: [0, 0, 0, 0]
											}
											onMargin={(value) => saveSubtitleFont({ margin: value })}
											marginControl={
												subtitleFont &&
												undefined !== subtitleFont[0] &&
												undefined !== subtitleFont[0].marginControl
													? subtitleFont[0].marginControl
													: 'linked'
											}
											onMarginControl={(value) => saveSubtitleFont({ marginControl: value })}
										/>
									)}
								</FlxbkPanelBody>
							)}
							{showSettings('titleAnchor', 'flx/tabs') && (
								<FlxbkPanelBody
									title={__('Tab Anchor Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-tab-anchor-settings'}
								>
									{times(tabCount, (n) => renderAnchorSettings(n))}
								</FlxbkPanelBody>
							)}
						</>
					)}

					{activeTab === 'advanced' && (
						<>
							<FlxbkPanelBody panelName={'fxb-tabs-spacing-settings'}>
								<ResponsiveMeasureRangeControl
									label={__('Padding', 'flx-blocks')}
									value={innerPadding}
									onChange={(value) => setAttributes({ innerPadding: value })}
									tabletValue={tabletInnerPadding}
									onChangeTablet={(value) => setAttributes({ tabletInnerPadding: value })}
									mobileValue={mobileInnerPadding}
									onChangeMobile={(value) => setAttributes({ mobileInnerPadding: value })}
									min={0}
									max={innerPaddingType === 'em' || innerPaddingType === 'rem' ? 25 : 999}
									step={innerPaddingType === 'em' || innerPaddingType === 'rem' ? 0.1 : 1}
									unit={innerPaddingType}
									units={['px', 'em', 'rem']}
									onUnit={(value) => setAttributes({ innerPaddingType: value })}
								/>

								{showSettings('structure', 'flx/tabs') && (
									<>
										<ResponsiveRangeControls
											label={__('Content Minimum Height', 'flx-blocks')}
											value={minHeight}
											onChange={(value) => setAttributes({ minHeight: value })}
											tabletValue={tabletMinHeight}
											onChangeTablet={(value) => setAttributes({ tabletMinHeight: value })}
											mobileValue={mobileMinHeight}
											onChangeMobile={(value) => setAttributes({ mobileMinHeight: value })}
											min={0}
											max={1000}
											step={1}
											unit={'px'}
											showUnit={true}
											units={['px']}
										/>
										<ResponsiveRangeControls
											label={__('Max Width', 'flx-blocks')}
											value={maxWidth}
											onChange={(value) => setAttributes({ maxWidth: value })}
											tabletValue={tabletMaxWidth}
											onChangeTablet={(value) => setAttributes({ tabletMaxWidth: value })}
											mobileValue={mobileMaxWidth}
											onChangeMobile={(value) => setAttributes({ mobileMaxWidth: value })}
											min={0}
											max={2000}
											step={1}
											unit={'px'}
											showUnit={true}
											units={['px']}
										/>
									</>
								)}
							</FlxbkPanelBody>

							<div className="fxt-sidebar-settings-spacer"></div>

							<FlxbkBlockDefaults
								attributes={attributes}
								defaultAttributes={metadata.attributes}
								blockSlug={metadata.name}
								excludedAttrs={nonTransAttrs}
							/>
						</>
					)}
				</InspectorControls>
			)}
			<div className={classes}>
				{showPreset && (
					<div className="fxt-select-starter-style-tabs">
						<div className="fxt-select-starter-style-tabs-title">
							{__('Select Initial Style', 'flx-blocks')}
						</div>
						<ButtonGroup
							className="fxt-init-tabs-btn-group"
							aria-label={__('Initial Style', 'flx-blocks')}
						>
							{map(startlayoutOptions, ({ name, key, icon }) => (
								<Button
									key={key}
									className="fxt-inital-tabs-style-btn"
									isSmall
									onClick={() => {
										setInitalLayout(key);
										setShowPreset(false);
									}}
								>
									{icon}
								</Button>
							))}
						</ButtonGroup>
					</div>
				)}
				{!showPreset && (
					<div
						className="fxt-tabs-wrap"
						style={{
							maxWidth: previewMaxWidth + 'px',
						}}
					>
						{/* <div className="fxb-add-new-tab-contain">
								<Button
									className="fxt-tab-add"
									isPrimary={ true }
									onClick={ () => {
										const newBlock = createBlock( 'flx/tab', { id: tabCount + 1 } );
										setAttributes( { tabCount: tabCount + 1 } );
										insertTab( newBlock );
										//wp.data.dispatch( 'core/block-editor' ).insertBlock( newBlock, clientId );
										const newtabs = titles;
										newtabs.push( {
											text: sprintf( __( 'Tab %d', 'flx-blocks' ), tabCount + 1 ),
											icon: titles[ 0 ].icon,
											iconSide: titles[ 0 ].iconSide,
											onlyIcon: titles[ 0 ].onlyIcon,
											subText: '',
										} );
										setAttributes( { titles: newtabs } );
										saveArrayUpdate( { iconSide: titles[ 0 ].iconSide }, 0 );
									} }
								>
									<Dashicon icon="plus" />
									{ __( 'Add Tab', 'flx-blocks' ) }
								</Button>
							</div> */}
						<ul
							className={`fxt-tabs-title-list${
								'tabs' === layout && widthType === 'percent'
									? ' fxb-tabs-list-columns fxb-tab-title-columns-' + previewTabWidth
									: ''
							}`}
							style={{
								width:
									'vtabs' === previewLayout
										? previewVerticalTabWidth + previewVerticalTabWidthUnit
										: undefined,
								marginRight:
									'tabs' === layout && widthType === 'percent' ? -gutter[0] + 'px' : undefined,
							}}
						>
							{renderPreviewArray}
						</ul>
						{googleFont && <WebfontLoader config={config}></WebfontLoader>}
						{enableSubtitle && subtitleFont && subtitleFont[0] && subtitleFont[0].google && (
							<WebfontLoader config={sconfig}></WebfontLoader>
						)}
						<div
							className="fxt-tabs-content-wrap"
							style={{
								paddingTop: getSpacingOptionOutput(previewInnerPaddingTop, innerPaddingType),
								paddingBottom: getSpacingOptionOutput(previewInnerPaddingBottom, innerPaddingType),
								paddingLeft: getSpacingOptionOutput(previewInnerPaddingLeft, innerPaddingType),
								paddingRight: getSpacingOptionOutput(previewInnerPaddingRight, innerPaddingType),
								borderTopLeftRadius: previewContentRadiusTop + contentBorderRadiusUnit,
								borderTopRightRadius: previewContentRadiusRight + contentBorderRadiusUnit,
								borderBottomRightRadius: previewContentRadiusBottom + contentBorderRadiusUnit,
								borderBottomLeftRadius: previewContentRadiusLeft + contentBorderRadiusUnit,
								minHeight: previewMinHeight + 'px',
								backgroundColor: FlxbkColorOutput(contentBgColor),
								borderTop: previewContentBorderTop ? previewContentBorderTop : undefined,
								borderRight: previewContentBorderRight ? previewContentBorderRight : undefined,
								borderBottom: previewContentBorderBottom ? previewContentBorderBottom : undefined,
								borderLeft: previewContentBorderLeft ? previewContentBorderLeft : undefined,
							}}
						>
							<InnerBlocks
								template={getPanesTemplate(tabCount)}
								templateLock="all"
								allowedBlocks={ALLOWED_BLOCKS}
							/>
						</div>
					</div>
				)}
			</div>
		</div>
	);
}
export default compose([
	withSelect((select, ownProps) => {
		const { clientId } = ownProps;
		const { getBlock, getBlockOrder } = select('core/block-editor');
		const block = getBlock(clientId);
		return {
			tabsBlock: block,
			realTabsCount: block.innerBlocks.length,
			tabsInner: getBlockOrder(clientId),
			previewDevice: select('flxblocks/data').getPreviewDeviceType(),
		};
	}),
	withDispatch((dispatch, { clientId }, { select }) => {
		const { getBlock, getBlocks } = select('core/block-editor');
		const { moveBlockToPosition, updateBlockAttributes, insertBlock, replaceInnerBlocks } =
			dispatch('core/block-editor');
		const block = getBlock(clientId);
		const innerBlocks = getBlocks(clientId);
		return {
			resetOrder() {
				times(block.innerBlocks.length, (n) => {
					updateBlockAttributes(block.innerBlocks[n].clientId, {
						id: n + 1,
					});
				});
			},
			moveTab(tabId, newIndex) {
				innerBlocks.splice(newIndex, 0, innerBlocks.splice(tabId, 1)[0]);
				replaceInnerBlocks(clientId, innerBlocks);
			},
			insertTab(newBlock) {
				insertBlock(newBlock, parseInt(block.innerBlocks.length), clientId);
			},
			removeTab(tabId) {
				innerBlocks.splice(tabId, 1);
				replaceInnerBlocks(clientId, innerBlocks);
			},
		};
	}),
])(FlxbkTabs);
