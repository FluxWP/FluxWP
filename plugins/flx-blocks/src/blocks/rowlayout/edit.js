/**
 * BLOCK: Flux Row / Layout
 */

/**
 * Import Icons
 */
import {
	rowIcon,
	twoColIcon,
	threeColIcon,
	twoLeftGoldenIcon,
	twoRightGoldenIcon,
	leftHalfIcon,
	rightHalfIcon,
	centerHalfIcon,
	wideCenterIcon,
	exWideCenterIcon,
	fourColIcon,
	lFourFortyIcon,
	rFourFortyIcon,
	fiveColIcon,
	sixColIcon,
} from '@flx-blocks/icons';

/**
 * Import External
 */
import Select from 'react-select';
import { times, dropRight, debounce, map } from 'lodash';
import classnames from 'classnames';
/**
 * Import Flux Components
 */
import {
	RangeControl,
	ResponsiveMeasureRangeControl,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	InspectorControlTabs,
	FlxbkBlockDefaults,
	SpacingVisualizer,
	CopyPasteAttributes,
	ColumnDragResizer,
} from '@flx-blocks/components';

import {
	FlxbkColorOutput,
	getPreviewSize,
	showSettings,
	mouseOverVisualizer,
	uniqueIdHelper,
	getInQueryBlock,
	setDynamicState,
	hasFlxbkCustomCss,
	getBlockDefaults,
} from '@flx-blocks/helpers';

/**
 * Import Block Specific Components
 */
import Overlay from './row-overlay';
import RowBackground from './row-background';
import ContentWidthIcon from './content-width-icons';
import LayoutControls from './layout-controls';
import StyleControls from './style-controls';
import PaddingResizer from './padding-resizer';
import renderSVGDivider from './render-svg-divider';
import GridVisualizer from './gridvisualizer';
import { getGutterTotal, getPreviewGutterSize, getSpacingOptionOutput } from './utils';
import { SPACING_SIZES_MAP, COLUMN_WIDTH_MAP } from './constants';
import { runRowLayoutMigrations } from './migrations';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';
/**
 * Import Css
 */
import './editor.scss';
import metadata from './block.json';
/**
 * Import WordPress Internals
 */
import { useEffect, useState, useRef } from '@wordpress/element';
import {
	InspectorControls,
	BlockControls,
	BlockAlignmentToolbar,
	InspectorAdvancedControls,
	useInnerBlocksProps,
	BlockVerticalAlignmentControl,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	ButtonGroup,
	Popover,
	ToolbarGroup,
	ToolbarButton,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { blockDefault, brush, settings, plusCircle } from '@wordpress/icons';
/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

const ALLOWED_BLOCKS = ['flx/column'];

/**
 * Build the row edit
 */
const FlxbkRowLayout = (props) => {
	const { clientId, attributes, setAttributes, context, toggleSelection, isSelected, name } = props;

	const {
		uniqueID,
		columns,
		mobileLayout,
		currentTab,
		colLayout,
		tabletLayout,
		columnGutter,
		collapseGutter,
		collapseOrder,
		bgColor,
		bgImg,
		verticalAlignment,
		overlayBgImg,
		overlay,
		htmlTag,
		minHeight,
		maxWidth,
		bottomSep,
		bottomSepColor,
		bottomSepHeight,
		bottomSepHeightMobile,
		bottomSepHeightTab,
		bottomSepWidth,
		bottomSepWidthMobile,
		bottomSepWidthTab,
		topSep,
		topSepColor,
		topSepHeight,
		topSepHeightMobile,
		topSepHeightTab,
		topSepWidth,
		topSepWidthMobile,
		topSepWidthTab,
		firstColumnWidth,
		firstColumnWidthTablet,
		firstColumnWidthMobile,
		textColor,
		linkColor,
		linkHoverColor,
		tabletPadding,
		minHeightUnit,
		maxWidthUnit,
		marginUnit,
		columnsUnlocked,
		columnsInnerHeight,
		zIndex,
		inheritMaxWidth,
		paddingUnit,
		align,
		minHeightTablet,
		minHeightMobile,
		gradient,
		overlayGradient,
		vsdesk,
		vstablet,
		vsmobile,
		loggedInUser,
		loggedIn,
		loggedOut,
		loggedInShow,
		rcpAccess,
		rcpMembership,
		rcpMembershipLevel,
		borderRadius,
		isPrebuiltModal,
		responsiveMaxWidth,
		flxbkBlockCSS,
		customGutter,
		gutterType,
		padding,
		mobilePadding,
		margin,
		tabletMargin,
		mobileMargin,
		customRowGutter,
		rowType,
		tabletGutter,
		mobileGutter,
		mobileRowGutter,
		tabletRowGutter,
		templateLock,
		inQueryBlock,
		breakoutLeft,
		breakoutRight,
		topSepHeightUnit,
		bottomSepHeightUnit,
		borderRadiusOverflow,
	} = attributes;

	const { isPreviewMode } = useSelect((_select) => {
		const { __unstableIsPreviewMode } = _select(blockEditorStore).getSettings();
		return {
			isPreviewMode: __unstableIsPreviewMode,
		};
	}, []);

	const { previewDevice, innerItemCount } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				innerItemCount: select(blockEditorStore).getBlockCount(clientId),
			};
		},
		[clientId]
	);
	const isSiteStylesPanel = useIsSiteStylesPanel();

	const { updateBlockAttributes, replaceInnerBlocks, insertBlock } = useDispatch(blockEditorStore);
	const {
		getBlockOrder: selectGetBlockOrder,
		getBlocks: selectGetBlocks,
		getBlock: selectGetBlock,
	} = useSelect(
		(select) => ({
			getBlockOrder: select(blockEditorStore).getBlockOrder,
			getBlocks: select(blockEditorStore).getBlocks,
			getBlock: select(blockEditorStore).getBlock,
		}),
		[]
	);

	/**
	 * Update all child Column blocks with a new vertical alignment setting
	 * based on whatever alignment is passed in. This allows change to parent
	 * to override anything set on a individual column basis.
	 *
	 * @param {string} verticalAlignment the vertical alignment setting
	 */
	const updateAlignment = (verticalAlignment) => {
		// Update own alignment.
		setAttributes({ verticalAlignment });

		// Update all child Column Blocks to match.
		const innerBlockClientIds = selectGetBlockOrder(clientId);
		innerBlockClientIds.forEach((innerBlockClientId) => {
			updateBlockAttributes(innerBlockClientId, {
				verticalAlignment,
			});
		});
	};

	/**
	 * Updates the column count, including necessary revisions to child Column
	 * blocks to grant required or redistribute available space.
	 *
	 * @param {number} previousColumns Previous column count.
	 * @param {number} newColumns      New column count.
	 */
	const updateColumns = (previousColumns, newColumns) => {
		let innerBlocks = selectGetBlocks(clientId);
		const isAddingColumn = newColumns > previousColumns;

		if (isAddingColumn) {
			const arrayLength = innerBlocks.length;
			for (let i = 0; i < arrayLength; i++) {
				innerBlocks[i].attributes.id = i + 1;
			}
			innerBlocks = [
				...innerBlocks,
				...times(newColumns - previousColumns, (n) => {
					return createBlock('flx/column', {
						id: previousColumns + n + 1,
					});
				}),
			];
		} else if (1 === previousColumns - newColumns) {
			const lastItem = innerBlocks.length - 1;
			if (!innerBlocks[lastItem].innerBlocks.length) {
				innerBlocks = dropRight(innerBlocks, previousColumns - newColumns);
			}
		}

		replaceInnerBlocks(clientId, innerBlocks);
	};

	const insertSection = (newBlock) => {
		const block = selectGetBlock(clientId);
		insertBlock(newBlock, parseInt(block.innerBlocks.length), clientId);
	};

	useEffect(() => {
		// The Flux panel's block holds site values, not saved content to migrate.
		if (isSiteStylesPanel) {
			return;
		}

		const isInQueryBlock = getInQueryBlock(context, inQueryBlock);
		if (attributes.inQueryBlock !== isInQueryBlock) {
			attributes.inQueryBlock = isInQueryBlock;
			setAttributes({ inQueryBlock: isInQueryBlock });
		}

		// Run all attribute migrations
		runRowLayoutMigrations(attributes, setAttributes);
	}, []);

	uniqueIdHelper(props);
	useEffect(() => {
		if (innerItemCount < columns && uniqueID) {
			updateColumns(innerItemCount, columns);
		} else if (innerItemCount < columns && !uniqueID) {
			const defaults = getBlockDefaults('flx/rowlayout', attributes);
			const newColumns = defaults?.columns ? defaults.columns : columns;

			updateColumns(innerItemCount, newColumns);
		}
	}, [innerItemCount, columns]);

	useEffect(() => {
		debouncedSetDynamicState(
			'flxbk.dynamicBackground',
			'',
			attributes,
			'bgImg',
			setAttributes,
			context,
			setDynamicBackgroundImg,
			bgImg ? false : true
		);
	}, [bgImg, context]);

	const [contentWidthPop, setContentWidthPop] = useState(false);
	const [resizingVisually, setResizingVisually] = useState(false);
	const [resizeColumnWidthNumbers, setResizeColumnWidthNumbers] = useState(null);
	const timeoutRef = useRef();
	const clearTimer = () => {
		if (timeoutRef.current) {
			window.clearTimeout(timeoutRef.current);
		}
	};
	const [activeTab, setActiveTab] = useState('general');
	const [dynamicBackgroundImg, setDynamicBackgroundImg] = useState('');
	const debouncedSetDynamicState = debounce(setDynamicState, 200);
	const previewFirstColumnWidth = getPreviewSize(
		previewDevice,
		firstColumnWidth,
		firstColumnWidthTablet,
		firstColumnWidthMobile
	);

	const editorDocument = document.querySelector('iframe[name="editor-canvas"]')?.contentWindow.document || document;
	const hasBG = bgColor || bgImg || gradient || overlay || overlayGradient || overlayBgImg ? 'fxt-row-has-bg' : '';
	const isFlxbkT = typeof flxbk_params !== 'undefined' && flxbk_params.isFlxbkT ? true : false;
	const paddingSidesTheme = isFlxbkT && true === inheritMaxWidth ? 'var(--global-content-edge-padding)' : '0px';
	const paddingSidesDefault = hasBG && !(isFlxbkT && true === inheritMaxWidth) ? '' : '';
	const previewPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== padding?.[0] ? padding[0] : '',
		undefined !== tabletPadding ? tabletPadding[0] : '',
		undefined !== mobilePadding ? mobilePadding[0] : ''
	);
	const previewPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== padding?.[1] && '' !== padding[1] ? padding[1] : paddingSidesDefault,
		undefined !== tabletPadding ? tabletPadding[1] : '',
		undefined !== mobilePadding ? mobilePadding[1] : ''
	);
	const previewPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== padding?.[2] ? padding[2] : '',
		undefined !== tabletPadding ? tabletPadding[2] : '',
		undefined !== mobilePadding ? mobilePadding[2] : ''
	);
	const previewPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== padding?.[3] && '' !== padding[3] ? padding[3] : paddingSidesDefault,
		undefined !== tabletPadding ? tabletPadding[3] : '',
		undefined !== mobilePadding ? mobilePadding[3] : ''
	);
	const previewMaxWidth = getPreviewSize(
		previewDevice,
		undefined !== maxWidth ? maxWidth : '',
		undefined !== responsiveMaxWidth && responsiveMaxWidth[0] ? responsiveMaxWidth[0] : '',
		undefined !== responsiveMaxWidth && responsiveMaxWidth[1] ? responsiveMaxWidth[1] : ''
	);
	const previewMinHeight = getPreviewSize(
		previewDevice,
		undefined !== minHeight ? minHeight : '',
		undefined !== minHeightTablet ? minHeightTablet : '',
		undefined !== minHeightMobile ? minHeightMobile : ''
	);
	const previewMarginTop = getPreviewSize(
		previewDevice,
		undefined !== margin && undefined !== margin[0] ? margin[0] : '',
		undefined !== tabletMargin && undefined !== tabletMargin[0] ? tabletMargin[0] : '',
		undefined !== mobileMargin && undefined !== mobileMargin[0] ? mobileMargin[0] : ''
	);
	const previewMarginBottom = getPreviewSize(
		previewDevice,
		undefined !== margin && undefined !== margin[2] ? margin[2] : '',
		undefined !== tabletMargin && undefined !== tabletMargin[2] ? tabletMargin[2] : '',
		undefined !== mobileMargin && undefined !== mobileMargin[2] ? mobileMargin[2] : ''
	);
	const previewTopSepHeight = getPreviewSize(
		previewDevice,
		undefined !== topSepHeight ? topSepHeight : '',
		undefined !== topSepHeightTab ? topSepHeightTab : '',
		undefined !== topSepHeightMobile ? topSepHeightMobile : ''
	);
	const previewTopSepWidth = getPreviewSize(
		previewDevice,
		undefined !== topSepWidth ? topSepWidth : '',
		undefined !== topSepWidthTab ? topSepWidthTab : '',
		undefined !== topSepWidthMobile ? topSepWidthMobile : ''
	);
	const previewBottomSepHeight = getPreviewSize(
		previewDevice,
		undefined !== bottomSepHeight ? bottomSepHeight : '',
		undefined !== bottomSepHeightTab ? bottomSepHeightTab : '',
		undefined !== bottomSepHeightMobile ? bottomSepHeightMobile : ''
	);
	const previewBottomSepWidth = getPreviewSize(
		previewDevice,
		undefined !== bottomSepWidth ? bottomSepWidth : '',
		undefined !== bottomSepWidthTab ? bottomSepWidthTab : '',
		undefined !== bottomSepWidthMobile ? bottomSepWidthMobile : ''
	);
	const paddingRightBreakout =
		'' !== previewPaddingRight
			? getSpacingOptionOutput(previewPaddingRight, paddingUnit ? paddingUnit : 'px')
			: paddingSidesTheme;
	const paddingLeftBreakout = previewPaddingLeft
		? getSpacingOptionOutput(previewPaddingLeft, paddingUnit ? paddingUnit : 'px')
		: paddingSidesTheme;
	const previewLayout = getPreviewSize(
		previewDevice,
		!colLayout ? 'equal' : colLayout,
		!tabletLayout ? '' : tabletLayout,
		!mobileLayout ? '' : mobileLayout
	);
	const previewColLayout = getPreviewSize(
		previewDevice,
		!colLayout ? 'equal' : colLayout,
		!tabletLayout || 'inherit' === tabletLayout ? '' : tabletLayout,
		!mobileLayout ? '' : mobileLayout
	);
	const hasBorderRadius =
		(borderRadius?.[0] || borderRadius?.[1] || borderRadius?.[2] || borderRadius?.[3]) &&
		borderRadiusOverflow !== false
			? true
			: false;

	const multiRowColLayouts = ['row', 'last-row', 'first-row', 'grid-layout', 'two-grid', 'three-grid'];
	const columnAttributes = [
		'firstColumnWidth',
		'secondColumnWidth',
		'thirdColumnWidth',
		'fourthColumnWidth',
		'fifthColumnWidth',
		'sixthColumnWidth',
	];

	//An array that has the number (in percent) of each column
	const columnWidthNumbers = [];
	//An array that has a string representing the layout (or width) of each column
	const widthStrings = [];
	for (let column = 0; column < columnAttributes.length; column++) {
		const deviceSuffix = previewDevice == 'Desktop' ? '' : previewDevice;

		//this needs to inherit if the layout does, but should be the sized custom value otherwise
		const attributeColumnWidth = attributes[columnAttributes[column]];
		const attributeColumnWidthTablet = attributes[columnAttributes[column] + 'Tablet'];
		const attributeColumnWidthMobile = attributes[columnAttributes[column] + 'Mobile'];
		const previewAttributeColumnWidth = getPreviewSize(
			previewDevice,
			attributeColumnWidth,
			attributeColumnWidthTablet,
			attributeColumnWidthMobile,
			true
		);
		const attributeColumnWidthToUse =
			previewLayout == 'inherit'
				? previewAttributeColumnWidth
				: attributes[columnAttributes[column] + deviceSuffix];

		const mappedWidth = COLUMN_WIDTH_MAP?.[columns]?.[previewColLayout]?.[column];
		const widthString = attributeColumnWidthToUse || previewColLayout;

		columnWidthNumbers.push(
			Math.abs(widthString) === parseFloat(widthString) ? attributeColumnWidthToUse : (mappedWidth ?? 0)
		);
		widthStrings.push(`${widthString}`);
	}
	const areColumnsCustom =
		(previewFirstColumnWidth && widthStrings[0] != previewLayout) || resizeColumnWidthNumbers ? true : false;

	const previewColumnGutter = getPreviewSize(previewDevice, columnGutter, tabletGutter, mobileGutter);
	const columnGap = getPreviewGutterSize(previewDevice, previewColumnGutter, customGutter, gutterType);
	const previewRowGutter = getPreviewSize(previewDevice, collapseGutter, tabletRowGutter, mobileRowGutter);
	const rowGap = getPreviewGutterSize(previewDevice, previewRowGutter, customRowGutter, rowType);
	const gapTotal = getGutterTotal(columnGap, columns);
	const hasCustomCss = hasFlxbkCustomCss(flxbkBlockCSS);
	const classes = classnames({
		'fxt-row-column-wrap': true,
		[`align${align}`]: align,
		[`fxb-row-id-${uniqueID}`]: uniqueID,
		[`fxt-has-${columns}-columns`]: columns,
		[`fxt-row-layout-${previewColLayout}`]: previewColLayout,
		[`fxt-row-valign-${verticalAlignment}`]: verticalAlignment,
		// [ `fxt-tab-layout-${ tabLayoutClass }` ]: tabLayoutClass,
		// [ `fxt-mobile-layout-${ mobileLayoutClass }` ]: mobileLayoutClass,
		[`current-tab-${currentTab}`]: currentTab,
		[`fxt-v-gutter-${collapseGutter}`]: collapseGutter,
		[`fxt-m-colapse-${collapseOrder}`]: previewDevice !== 'Desktop' ? collapseOrder : false,
		[hasBG]: hasBG,
		'has-border-radius': hasBorderRadius,
		'fxt-inner-column-height-full': columnsInnerHeight,
		'fxvs-false': getPreviewSize(previewDevice, vsdesk, vstablet, vsmobile),
		'flxbk-has-rcp-display': rcpMembership && flxbk_params && flxbk_params.rcp_access,
		'flxbk-has-custom-css': hasCustomCss,
	});
	const startlayoutOptions = [
		{ key: 'equal', col: 1, name: __('Row', 'flx-blocks'), icon: rowIcon },
		{ key: 'equal', col: 2, name: __('Two: Equal', 'flx-blocks'), icon: twoColIcon },
		{ key: 'left-golden', col: 2, name: __('Two: Left Heavy 66/33', 'flx-blocks'), icon: twoLeftGoldenIcon },
		{
			key: 'right-golden',
			col: 2,
			name: __('Two: Right Heavy 33/66', 'flx-blocks'),
			icon: twoRightGoldenIcon,
		},
		{ key: 'equal', col: 3, name: __('Three: Equal', 'flx-blocks'), icon: threeColIcon },
		{ key: 'left-half', col: 3, name: __('Three: Left Heavy 50/25/25', 'flx-blocks'), icon: leftHalfIcon },
		{ key: 'right-half', col: 3, name: __('Three: Right Heavy 25/25/50', 'flx-blocks'), icon: rightHalfIcon },
		{
			key: 'center-half',
			col: 3,
			name: __('Three: Center Heavy 25/50/25', 'flx-blocks'),
			icon: centerHalfIcon,
		},
		{
			key: 'center-wide',
			col: 3,
			name: __('Three: Wide Center 20/60/20', 'flx-blocks'),
			icon: wideCenterIcon,
		},
		{
			key: 'center-exwide',
			col: 3,
			name: __('Three: Wider Center 15/70/15', 'flx-blocks'),
			icon: exWideCenterIcon,
		},
		{ key: 'equal', col: 4, name: __('Four: Equal', 'flx-blocks'), icon: fourColIcon },
		{
			key: 'left-forty',
			col: 4,
			name: __('Four: Left Heavy 40/20/20/20', 'flx-blocks'),
			icon: lFourFortyIcon,
		},
		{
			key: 'right-forty',
			col: 4,
			name: __('Four: Right Heavy 20/20/20/40', 'flx-blocks'),
			icon: rFourFortyIcon,
		},
		{ key: 'equal', col: 5, name: __('Five: Equal', 'flx-blocks'), icon: fiveColIcon },
		{ key: 'equal', col: 6, name: __('Six: Equal', 'flx-blocks'), icon: sixColIcon },
		//{ key: 'grid-layout', col: 1, name: __( 'Grid Layout', 'flx-blocks' ), icon: sixColIcon },
	];

	let columnsString = '';
	for (let column = 0; column < columns; column++) {
		const columnWidthToUse = resizeColumnWidthNumbers
			? resizeColumnWidthNumbers[column]
			: columnWidthNumbers[column];
		const columnString = ` minmax(0, calc( ${parseFloat(columnWidthToUse)}%${
			gapTotal ? ' - (' + gapTotal + ' / ' + columns + ')' : ''
		} ) )`;
		columnsString += columnString;
	}

	const innerClasses = classnames({
		'innerblocks-wrap': true,
		'fxb-theme-content-width': inheritMaxWidth,
		[`fxt-layout-inner-wrap-id${uniqueID}`]: uniqueID,
		[`fxb-grid-columns-${columns}`]: columns,
	});
	const containerRef = useRef(null);

	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{
			ref: containerRef,
			className: innerClasses,
			style: {
				maxWidth: !inheritMaxWidth && previewMaxWidth ? previewMaxWidth + maxWidthUnit : undefined,
				minHeight:
					previewMinHeight && paddingUnit && '%' !== paddingUnit
						? 'calc(' +
							previewMinHeight +
							minHeightUnit +
							' - (' +
							('' !== previewPaddingTop
								? getSpacingOptionOutput(previewPaddingTop, paddingUnit ? paddingUnit : 'px')
								: '0px') +
							' + ' +
							('' !== previewPaddingBottom
								? getSpacingOptionOutput(previewPaddingBottom, paddingUnit ? paddingUnit : 'px')
								: '0px') +
							')'
						: undefined,
				paddingLeft:
					'' !== previewPaddingLeft
						? getSpacingOptionOutput(previewPaddingLeft, paddingUnit ? paddingUnit : 'px')
						: undefined,
				paddingRight:
					'' !== previewPaddingRight
						? getSpacingOptionOutput(previewPaddingRight, paddingUnit ? paddingUnit : 'px')
						: undefined,
			},
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			orientation: 'horizontal',
			renderAppender: false,
			templateLock,
		}
	);
	const paddingMouseOver = mouseOverVisualizer();
	const marginMouseOver = mouseOverVisualizer();
	return (
		<>
			{'contentOnly' !== templateLock && !isPreviewMode && showSettings('allSettings', 'flx/rowlayout') && (
				<BlockControls>
					<BlockAlignmentToolbar
						value={align}
						controls={['center', 'wide', 'full']}
						onChange={(value) => setAttributes({ align: value })}
					/>
					<ToolbarGroup group="content-width">
						<ToolbarButton
							className="fxb-content-width"
							icon={
								inheritMaxWidth ? (
									<ContentWidthIcon value="theme" />
								) : (
									<ContentWidthIcon value="normal" />
								)
							}
							onClick={() => {
								if (!contentWidthPop) {
									setContentWidthPop(true);
								}
							}}
							isPressed={contentWidthPop ? true : false}
							aria-haspopup="true"
							aria-expanded={contentWidthPop}
							label={__('Inner Content Width', 'flx-blocks')}
							showTooltip={true}
						/>
						{contentWidthPop && (
							<Popover
								className="fxb-content-width-popover"
								position="bottom center"
								onClick={() => {}}
								expandOnMobile={true}
								onClose={() => {
									setContentWidthPop(false);
								}}
							>
								<div className="fxb-content-width-popover-inner-wrap">
									<ToggleControl
										label={__('Use Theme Content Inner Width?', 'flx-blocks')}
										checked={undefined !== inheritMaxWidth ? inheritMaxWidth : false}
										onChange={(value) => setAttributes({ inheritMaxWidth: value })}
									/>
									{inheritMaxWidth !== true && (
										<>
											<ResponsiveRangeControls
												label={__('Custom Content Max Width', 'flx-blocks')}
												value={maxWidth ? maxWidth : ''}
												onChange={(value) => {
													setAttributes({ maxWidth: value });
												}}
												tabletValue={
													undefined !== responsiveMaxWidth &&
													undefined !== responsiveMaxWidth[0]
														? responsiveMaxWidth[0]
														: ''
												}
												onChangeTablet={(value) => {
													setAttributes({
														responsiveMaxWidth: [
															value,
															undefined !== responsiveMaxWidth &&
															undefined !== responsiveMaxWidth[1]
																? responsiveMaxWidth[1]
																: '',
														],
													});
												}}
												mobileValue={
													undefined !== responsiveMaxWidth &&
													undefined !== responsiveMaxWidth[1]
														? responsiveMaxWidth[1]
														: ''
												}
												onChangeMobile={(value) => {
													setAttributes({
														responsiveMaxWidth: [
															undefined !== responsiveMaxWidth &&
															undefined !== responsiveMaxWidth[0]
																? responsiveMaxWidth[0]
																: '',
															value,
														],
													});
												}}
												min={0}
												max={maxWidthUnit === 'px' ? 2000 : 100}
												step={1}
												unit={maxWidthUnit ? maxWidthUnit : 'px'}
												onUnit={(value) => {
													setAttributes({ maxWidthUnit: value });
												}}
												units={['px', '%', 'vw', 'rem']}
											/>
										</>
									)}
								</div>
							</Popover>
						)}
					</ToolbarGroup>
					<ToolbarGroup group="align">
						<BlockVerticalAlignmentControl
							value={verticalAlignment === 'middle' ? 'center' : verticalAlignment}
							onChange={(value) => {
								if (value === 'center') {
									setAttributes({ verticalAlignment: 'middle' });
								} else if (value === 'bottom') {
									setAttributes({ verticalAlignment: 'bottom' });
								} else {
									setAttributes({ verticalAlignment: 'top' });
								}
							}}
						/>
					</ToolbarGroup>
					<ToolbarGroup group="add-block">
						<ToolbarButton
							className="fxb-row-add-section"
							icon={plusCircle}
							onClick={() => {
								const newBlock = createBlock('flx/column', {});
								insertSection(newBlock);
							}}
							label={__('Add Another Section', 'flx-blocks')}
							showTooltip={true}
						/>
					</ToolbarGroup>
					<CopyPasteAttributes
						attributes={attributes}
						defaultAttributes={metadata.attributes}
						blockSlug={metadata.name}
						onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
					/>
				</BlockControls>
			)}
			{!isPreviewMode && showSettings('allSettings', 'flx/rowlayout') && (
				<>
					<InspectorControls>
						<InspectorControlTabs
							panelName={'rowlayout'}
							setActiveTab={setActiveTab}
							activeTab={activeTab}
							tabs={[
								{
									key: 'general',
									title: __('Layout', 'flx-blocks'),
									icon: blockDefault,
								},
								{
									key: 'style',
									title: __('Style', 'flx-blocks'),
									icon: brush,
								},
								{
									key: 'advanced',
									title: __('Advanced', 'flx-blocks'),
									icon: settings,
								},
							]}
						/>
						{activeTab === 'general' && (
							<>
								<LayoutControls
									clientId={clientId}
									attributes={attributes}
									setAttributes={setAttributes}
									updateColumns={updateColumns}
									innerItemCount={innerItemCount}
									widthString={widthStrings[0]}
									previewDevice={previewDevice}
									isSiteStylesPanel={isSiteStylesPanel}
								/>
							</>
						)}
						{activeTab === 'style' && (
							<StyleControls
								clientId={clientId}
								attributes={attributes}
								setAttributes={setAttributes}
								isSelected={isSelected}
								context={context}
								name={name}
								isSiteStylesPanel={isSiteStylesPanel}
							/>
						)}
						{activeTab === 'advanced' && (
							<>
								{showSettings('paddingMargin', 'flx/rowlayout') && (
									<FlxbkPanelBody panelName={'fxb-row-padding'}>
										<div
											className={
												hasBG && padding[1] === '' && padding[3] !== ''
													? 'padding-right-measure-range-control'
													: hasBG && padding[1] !== '' && padding[3] === ''
														? 'padding-left-measure-range-control'
														: hasBG && padding[1] === '' && padding[3] === ''
															? 'padding-right-measure-range-control padding-left-measure-range-control'
															: ''
											}
										>
											<ResponsiveMeasureRangeControl
												label={__('Padding', 'flx-blocks')}
												value={
													hasBG &&
													undefined !== padding &&
													undefined !== padding[0] &&
													padding[1] === '' &&
													padding[3] === ''
														? [padding[0], '', padding[2], '']
														: undefined !== padding && undefined !== padding[0]
															? padding
															: ['sm', '', 'sm', '']
												}
												tabletValue={tabletPadding}
												mobileValue={
													undefined !== mobilePadding && undefined !== mobilePadding[0]
														? mobilePadding
														: ['', '', '', '']
												}
												onChange={(value) => setAttributes({ padding: value })}
												onChangeTablet={(value) => setAttributes({ tabletPadding: value })}
												onChangeMobile={(value) => setAttributes({ mobilePadding: value })}
												min={0}
												max={paddingUnit === 'em' || paddingUnit === 'rem' ? 25 : 999}
												step={paddingUnit === 'em' || paddingUnit === 'rem' ? 0.1 : 1}
												deskDefault={['sm', '', 'sm', '']}
												unit={paddingUnit}
												options={SPACING_SIZES_MAP}
												units={['px', 'em', 'rem', '%', 'vh', 'vw']}
												onUnit={(value) => setAttributes({ paddingUnit: value })}
												onMouseOver={paddingMouseOver.onMouseOver}
												onMouseOut={paddingMouseOver.onMouseOut}
											/>
										</div>

										<ResponsiveMeasureRangeControl
											label={__('Margin', 'flx-blocks')}
											value={[
												undefined !== margin && undefined !== margin[0] ? margin[0] : '',
												'auto',
												undefined !== margin && undefined !== margin[2] ? margin[2] : '',
												'auto',
											]}
											tabletValue={[
												undefined !== tabletMargin && undefined !== tabletMargin[0]
													? tabletMargin[0]
													: '',
												'auto',
												undefined !== tabletMargin && undefined !== tabletMargin[2]
													? tabletMargin[2]
													: '',
												'auto',
											]}
											mobileValue={[
												undefined !== mobileMargin && undefined !== mobileMargin[0]
													? mobileMargin[0]
													: '',
												'auto',
												undefined !== mobileMargin && undefined !== mobileMargin[2]
													? mobileMargin[2]
													: '',
												'auto',
											]}
											onChange={(value) => {
												setAttributes({ margin: [value[0], '', value[2], ''] });
											}}
											onChangeTablet={(value) => {
												setAttributes({ tabletMargin: [value[0], '', value[2], ''] });
											}}
											onChangeMobile={(value) => {
												setAttributes({ mobileMargin: [value[0], '', value[2], ''] });
											}}
											min={marginUnit === 'em' || marginUnit === 'rem' ? -25 : -999}
											max={marginUnit === 'em' || marginUnit === 'rem' ? 25 : 999}
											step={marginUnit === 'em' || marginUnit === 'rem' ? 0.1 : 1}
											unit={marginUnit}
											allowEmpty={true}
											options={SPACING_SIZES_MAP}
											units={['px', 'em', 'rem', '%', 'vh']}
											onUnit={(value) => setAttributes({ marginUnit: value })}
											onMouseOver={marginMouseOver.onMouseOver}
											onMouseOut={marginMouseOver.onMouseOut}
											allowAuto={true}
										/>
									</FlxbkPanelBody>
								)}
								<div className="fxt-sidebar-settings-spacer"></div>
								{!isSiteStylesPanel && showSettings('structure', 'flx/rowlayout') && (
									<>
										<FlxbkPanelBody
											title={__('Structure Settings', 'flx-blocks')}
											initialOpen={false}
											panelName={'fxb-row-structure-settings'}
										>
											<SelectControl
												label={__('Container HTML tag', 'flx-blocks')}
												value={htmlTag}
												options={[
													{ value: 'div', label: 'div' },
													{ value: 'header', label: 'header' },
													{ value: 'section', label: 'section' },
													{ value: 'article', label: 'article' },
													{ value: 'main', label: 'main' },
													{ value: 'aside', label: 'aside' },
													{ value: 'footer', label: 'footer' },
												]}
												onChange={(value) => setAttributes({ htmlTag: value })}
											/>
											<ResponsiveRangeControls
												label={__('Minimum Height', 'flx-blocks')}
												value={minHeight ? minHeight : ''}
												onChange={(value) => {
													setAttributes({ minHeight: value });
												}}
												tabletValue={undefined !== minHeightTablet ? minHeightTablet : ''}
												onChangeTablet={(value) => {
													setAttributes({ minHeightTablet: value });
												}}
												mobileValue={undefined !== minHeightMobile ? minHeightMobile : ''}
												onChangeMobile={(value) => {
													setAttributes({ minHeightMobile: value });
												}}
												min={0}
												max={minHeightUnit === 'px' ? 2000 : 200}
												step={1}
												unit={minHeightUnit ? minHeightUnit : 'px'}
												onUnit={(value) => {
													setAttributes({ minHeightUnit: value });
												}}
												units={['px', 'vw', 'vh']}
												reset={true}
											/>
											<ToggleControl
												label={__('Inner Column Height 100%', 'flx-blocks')}
												checked={undefined !== columnsInnerHeight ? columnsInnerHeight : false}
												onChange={(value) => setAttributes({ columnsInnerHeight: value })}
											/>
											<RangeControl
												label={__('Z-Index Control', 'flx-blocks')}
												value={zIndex}
												onChange={(value) => {
													setAttributes({
														zIndex: value,
													});
												}}
												min={-200}
												max={2000}
												reset={true}
											/>
										</FlxbkPanelBody>
										<FlxbkPanelBody
											title={__('Visibility Settings', 'flx-blocks')}
											initialOpen={false}
											panelName={'fxb-row-visibility-settings'}
										>
											<ToggleControl
												label={__('Hide on Desktop', 'flx-blocks')}
												checked={undefined !== vsdesk ? vsdesk : false}
												onChange={(value) => setAttributes({ vsdesk: value })}
											/>
											<ToggleControl
												label={__('Hide on Tablet', 'flx-blocks')}
												checked={undefined !== vstablet ? vstablet : false}
												onChange={(value) => setAttributes({ vstablet: value })}
											/>
											<ToggleControl
												label={__('Hide on Mobile', 'flx-blocks')}
												checked={undefined !== vsmobile ? vsmobile : false}
												onChange={(value) => setAttributes({ vsmobile: value })}
											/>
											<h2>{__('User Visibility Settings', 'flx-blocks')}</h2>
											<ToggleControl
												label={__('Hide from Logged in Users', 'flx-blocks')}
												checked={undefined !== loggedIn ? loggedIn : false}
												onChange={(value) => setAttributes({ loggedIn: value })}
											/>
											{loggedIn && (
												<>
													<div className="components-base-control">
														<span className="flxbk-sidebar-label">
															{__(
																'Optional: Hide from Specific User Roles',
																'flx-blocks'
															)}
														</span>
														<div className="fxt-meta-select-wrap">
															<Select
																options={
																	flxbk_user_params &&
																	flxbk_user_params.userVisibility
																		? flxbk_user_params.userVisibility
																		: []
																}
																value={loggedInUser}
																isMulti={true}
																maxMenuHeight={200}
																isClearable={true}
																placeholder={''}
																onChange={(value) =>
																	setAttributes({ loggedInUser: value })
																}
															/>
														</div>
													</div>
													<div className="components-base-control">
														<span className="flxbk-sidebar-label">
															{__(
																'Optional: Show Only to Specific User Roles',
																'flx-blocks'
															)}
														</span>
														<div className="fxt-meta-select-wrap">
															<Select
																options={
																	flxbk_user_params &&
																	flxbk_user_params.userVisibility
																		? flxbk_user_params.userVisibility
																		: []
																}
																value={loggedInShow}
																isMulti={true}
																maxMenuHeight={200}
																isClearable={true}
																placeholder={''}
																onChange={(value) =>
																	setAttributes({ loggedInShow: value })
																}
															/>
														</div>
													</div>
												</>
											)}
											<ToggleControl
												label={__('Hide from Logged out Users', 'flx-blocks')}
												checked={undefined !== loggedOut ? loggedOut : false}
												onChange={(value) => setAttributes({ loggedOut: value })}
											/>
											{flxbk_params && flxbk_params.rcp_access && (
												<>
													<ToggleControl
														label={__('Restrict based on Membership', 'flx-blocks')}
														checked={undefined !== rcpMembership ? rcpMembership : false}
														onChange={(value) => setAttributes({ rcpMembership: value })}
													/>
													{rcpMembership && (
														<>
															<SelectControl
																label={__('Minimum Access Level', 'flx-blocks')}
																value={rcpAccess}
																options={
																	flxbk_params &&
																	flxbk_params.rcp_access
																		? flxbk_params.rcp_access
																		: []
																}
																onChange={(value) =>
																	setAttributes({ rcpAccess: value })
																}
															/>
															<div className="components-base-control">
																<span className="flxbk-sidebar-label">
																	{__('Specific Memberships', 'flx-blocks')}
																</span>
																<div className="fxt-meta-select-wrap">
																	<Select
																		options={
																			flxbk_params &&
																			flxbk_params.rcp_levels
																				? flxbk_params.rcp_levels
																				: []
																		}
																		value={rcpMembershipLevel}
																		isMulti={true}
																		maxMenuHeight={200}
																		isClearable={true}
																		placeholder={__('Any Membership', 'flx-blocks')}
																		onChange={(value) =>
																			setAttributes({
																				rcpMembershipLevel: value,
																			})
																		}
																	/>
																</div>
															</div>
														</>
													)}
												</>
											)}
										</FlxbkPanelBody>
									</>
								)}

								{!isSiteStylesPanel && (
									<FlxbkBlockDefaults
										attributes={attributes}
										defaultAttributes={metadata.attributes}
										blockSlug={metadata.name}
									/>
								)}
							</>
						)}
					</InspectorControls>
					<InspectorAdvancedControls>
						<ToggleControl
							label={__('Row Content Only Editing', 'flx-blocks')}
							checked={'contentOnly' === templateLock ? true : false}
							onChange={(value) => setAttributes({ templateLock: 'contentOnly' })}
						/>
					</InspectorAdvancedControls>
				</>
			)}
			<RowBackground
				backgroundClasses={classes}
				attributes={attributes}
				previewDevice={previewDevice}
				dynamicBackgroundImg={dynamicBackgroundImg}
			>
				<style>
					{textColor
						? `.fxb-row-id-${uniqueID}, .fxb-row-id-${uniqueID} p:not(.use-for-specificity), .fxb-row-id-${uniqueID} h1:not(.use-for-specificity), .fxb-row-id-${uniqueID} h2:not(.use-for-specificity), .fxb-row-id-${uniqueID} h3:not(.use-for-specificity), .fxb-row-id-${uniqueID} h4:not(.use-for-specificity), .fxb-row-id-${uniqueID} h5:not(.use-for-specificity), .fxb-row-id-${uniqueID} h6:not(.use-for-specificity) { color: ${FlxbkColorOutput(
								textColor
							)}; }`
						: ''}
					{columns &&
						columns === 2 &&
						inheritMaxWidth &&
						(breakoutLeft || breakoutRight) &&
						'row' !== previewLayout &&
						'full' === align &&
						'Desktop' === previewDevice && (
							<>
								{breakoutRight
									? `@media (min-width: 1290px) {
											.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxb-grid-columns-2.fxt-layout-inner-wrap-id${uniqueID} > .wp-block-flx-column:nth-child(${
												isSelected ? 3 : 2
											}) > .flxbk-inner-column-inner {
												margin-inline-end: calc( ( ( ( var( --global-fxb-editor-full-width ) - ( var( --fxb-global-content-width ) - ( ${paddingRightBreakout} *2 ) ) ) /2 ) *-1) ) !important;
										}}
										@media (max-width: 1289px) {
											.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxb-grid-columns-2.fxt-layout-inner-wrap-id${uniqueID} > .wp-block-flx-column:nth-child(${
												isSelected ? 3 : 2
											}) > .flxbk-inner-column-inner {
												margin-inline-end: calc( ${paddingRightBreakout} * -1 ) !important;
										}}`
									: ''}
								{breakoutLeft
									? `@media (min-width: 1290px) {
											.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxb-grid-columns-2.fxt-layout-inner-wrap-id${uniqueID} > .wp-block-flx-column:nth-child(${
												isSelected ? 2 : 1
											}) > .flxbk-inner-column-inner {
												margin-inline-start: calc( ( ( ( var( --global-fxb-editor-full-width ) - ( var( --fxb-global-content-width ) - ( ${paddingLeftBreakout} *2 ) ) ) /2 ) *-1) ) !important;
										}}
										@media (max-width: 1289px) {
											.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxb-grid-columns-2.fxt-layout-inner-wrap-id${uniqueID} > .wp-block-flx-column:nth-child(${
												isSelected ? 2 : 1
											}) > .flxbk-inner-column-inner {
												margin-inline-start: calc( ${paddingLeftBreakout} * -1 ) !important;
										}}`
									: ''}
							</>
						)}
					{columns &&
						columns === 2 &&
						inheritMaxWidth &&
						(breakoutLeft || breakoutRight) &&
						'row' !== previewLayout &&
						'full' === align &&
						'Tablet' === previewDevice && (
							<>
								{/*{breakoutRight*/}
								{/*	? `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxb-grid-columns-2.fxt-layout-inner-wrap-id${uniqueID} > .wp-block-flx-column:nth-child(2) > .flxbk-inner-column-inner { margin-inline-end: calc( ${paddingRightBreakout} *-1 ) !important; }`*/}
								{/*	: ''}*/}
								{/*{breakoutLeft*/}
								{/*	? `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxb-grid-columns-2.fxt-layout-inner-wrap-id${uniqueID} > .wp-block-flx-column:nth-child(1) > .flxbk-inner-column-inner { margin-inline-start: calc( ${paddingLeftBreakout} *-1 ) !important; }`*/}
								{/*	: ''}*/}
							</>
						)}
					{linkColor
						? `.fxb-row-id-${uniqueID} a:not(.use-for-specificity) { color: ${FlxbkColorOutput(
								linkColor
							)}; }`
						: ''}
					{linkHoverColor
						? `.fxb-row-id-${uniqueID} a:not(.use-for-specificity):hover { color: ${FlxbkColorOutput(
								linkHoverColor
							)}; }`
						: ''}
					<>
						{undefined !== columnGap
							? `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxt-layout-inner-wrap-id${uniqueID}, .wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .fxb-grid-align-display-wrap > .fxb-grid-align-display { column-gap:${columnGap} }`
							: ''}
					</>
					{undefined !== rowGap
						? `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxt-layout-inner-wrap-id${uniqueID}, .wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .fxb-grid-align-display-wrap > .fxb-grid-align-display { row-gap:${rowGap} }`
						: ''}

					{columns && !multiRowColLayouts.includes(previewColLayout) && (
						<>
							{`.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap.fxt-layout-inner-wrap-id${uniqueID} { grid-template-columns: ${columnsString} }`}
						</>
					)}
					{'Desktop' !== previewDevice &&
						'right-to-left' === collapseOrder &&
						('grid-layout' === previewLayout ||
							'row' === previewLayout ||
							'last-row' === previewLayout ||
							'first-row' === previewLayout) && (
							<>
								{times(columns, (n) => {
									const item = n + 1;
									return `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap >  .wp-block-flx-column:nth-child(${item}) { order: ${
										columns - item
									} }`;
								})}
								{times(columns, (n) => {
									const item = n + 1 + columns;
									return `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap >  .wp-block-flx-column:nth-child(${item}) { order: ${
										10 + (columns - (n + 1))
									} }`;
								})}
								{times(columns, (n) => {
									const item = n + 1 + columns + columns;
									return `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap >  .wp-block-flx-column:nth-child(${item}) { order: ${
										20 + (columns - (n + 1))
									} }`;
								})}
								{times(columns, (n) => {
									const item = n + 1 + columns + columns + columns;
									return `.wp-block-flx-rowlayout.fxb-row-id-${uniqueID} > .innerblocks-wrap >  .wp-block-flx-column:nth-child(${item}) { order: ${
										30 + (columns - (n + 1))
									} }`;
								})}
							</>
						)}
					{zIndex && zIndex > 30 && (
						<>
							{`.components-popover.block-editor-block-list__block-popover { z-index: ${zIndex + 1000} }`}
							;
						</>
					)}
					{flxbkBlockCSS && <>{flxbkBlockCSS.replace(/selector/g, `.fxb-row-id-${uniqueID}`)}</>}
					{isSelected &&
						colLayout &&
						'contentOnly' !== templateLock &&
						'row' !== colLayout &&
						columns &&
						1 !== columns &&
						'Desktop' === previewDevice &&
						showSettings('columnResize', 'flx/rowlayout') && (
							<>
								{`.block-editor-block-popover__inbetween-container .block-editor-block-list__insertion-point.is-horizontal.is-with-inserter { display: none }`}
								;
							</>
						)}
				</style>
				<Overlay attributes={attributes} previewDevice={previewDevice} />
				{colLayout && 'grid-layout' === colLayout && (
					<GridVisualizer attributes={attributes} setAttributes={setAttributes} />
				)}
				{!colLayout && (
					<div className="fxt-select-layout">
						<div className="fxt-select-layout-title">{__('Select Your Layout', 'flx-blocks')}</div>
						<ButtonGroup aria-label={__('Column Layout', 'flx-blocks')}>
							{map(startlayoutOptions, ({ name, key, icon, col }) => (
								<Button
									key={key}
									className="fxt-layout-btn"
									isSmall
									label={name}
									icon={icon}
									onClick={() => {
										updateColumns(columns, col);
										setAttributes({
											colLayout: key,
											columns: col,
											firstColumnWidth: 0,
											secondColumnWidth: 0,
											thirdColumnWidth: 0,
											fourthColumnWidth: 0,
											fifthColumnWidth: 0,
											sixthColumnWidth: 0,
										});
									}}
								/>
							))}
						</ButtonGroup>
					</div>
				)}
				{colLayout && 'none' !== topSep && '' !== topSep && (
					<div
						className={`fxt-row-layout-top-sep fxt-row-sep-type-${topSep}`}
						style={{
							height: previewTopSepHeight + topSepHeightUnit,
							color: FlxbkColorOutput(topSepColor),
						}}
					>
						{renderSVGDivider(topSep, 'top', previewTopSepWidth + '%')}
					</div>
				)}
				{colLayout && (
					<PaddingResizer
						previewDevice={previewDevice}
						edge={'top'}
						isPreviewMode={isPreviewMode}
						attributes={attributes}
						setAttributes={setAttributes}
						toggleSelection={toggleSelection}
						finishedResizing={() => {
							setResizingVisually(true);
							clearTimer();
							timeoutRef.current = setTimeout(() => {
								setResizingVisually(false);
							}, 100);
						}}
					/>
				)}
				{colLayout && (
					<div {...innerBlocksProps}>
						{isSelected &&
							colLayout &&
							'contentOnly' !== templateLock &&
							!multiRowColLayouts.includes(previewColLayout) &&
							columns &&
							showSettings('allSettings', 'flx/rowlayout') &&
							showSettings('columnResize', 'flx/rowlayout') && (
								<ColumnDragResizer
									attributes={attributes}
									setAttributes={setAttributes}
									previewDevice={previewDevice}
									columns={columns}
									columnWidths={
										resizeColumnWidthNumbers ? resizeColumnWidthNumbers : columnWidthNumbers
									}
									columnGap={columnGap}
									columnsUnlocked={columnsUnlocked}
									onColumnsUnlocked={(value) => setAttributes({ columnsUnlocked: value })}
									onResize={(newColumnWidths) => {
										//for performance reasons, we set a temporary state for the widths during resize
										setResizeColumnWidthNumbers(newColumnWidths);
									}}
									onResizeTablet={(newColumnWidths) => {
										setResizeColumnWidthNumbers(newColumnWidths);
									}}
									onResizeMobile={(newColumnWidths) => {
										setResizeColumnWidthNumbers(newColumnWidths);
									}}
									onResizeStop={(newColumnWidths) => {
										setAttributes({
											firstColumnWidth: newColumnWidths?.[0],
											secondColumnWidth: newColumnWidths?.[1],
											thirdColumnWidth: newColumnWidths?.[2],
											fourthColumnWidth: newColumnWidths?.[3],
											fifthColumnWidth: newColumnWidths?.[4],
											sixthColumnWidth: newColumnWidths?.[5],
										});
										setResizeColumnWidthNumbers(null);
									}}
									onResizeStopTablet={(newColumnWidths) => {
										setAttributes({
											firstColumnWidthTablet: newColumnWidths?.[0],
											secondColumnWidthTablet: newColumnWidths?.[1],
											thirdColumnWidthTablet: newColumnWidths?.[2],
											fourthColumnWidthTablet: newColumnWidths?.[3],
											fifthColumnWidthTablet: newColumnWidths?.[4],
											sixthColumnWidthTablet: newColumnWidths?.[5],
										});
										setResizeColumnWidthNumbers(null);
									}}
									onResizeStopMobile={(newColumnWidths) => {
										setAttributes({
											firstColumnWidthMobile: newColumnWidths?.[0],
											secondColumnWidthMobile: newColumnWidths?.[1],
											thirdColumnWidthMobile: newColumnWidths?.[2],
											fourthColumnWidthMobile: newColumnWidths?.[3],
											fifthColumnWidthMobile: newColumnWidths?.[4],
											sixthColumnWidthMobile: newColumnWidths?.[5],
										});
										setResizeColumnWidthNumbers(null);
									}}
									active={areColumnsCustom}
									tempDuringResize={true}
								/>
							)}
						{children}
					</div>
				)}
				{colLayout && (
					<PaddingResizer
						previewDevice={previewDevice}
						edge={'bottom'}
						isPreviewMode={isPreviewMode}
						attributes={attributes}
						setAttributes={setAttributes}
						toggleSelection={toggleSelection}
						finishedResizing={() => {
							setResizingVisually(true);
							clearTimer();
							timeoutRef.current = setTimeout(() => {
								setResizingVisually(false);
							}, 100);
						}}
					/>
				)}
				{colLayout && 'none' !== bottomSep && '' !== bottomSep && (
					<div
						className={`fxt-row-layout-bottom-sep fxt-row-sep-type-${bottomSep}`}
						style={{
							height: previewBottomSepHeight + bottomSepHeightUnit,
							color: FlxbkColorOutput(bottomSepColor),
						}}
					>
						{renderSVGDivider(bottomSep, 'bottom', previewBottomSepWidth + '%')}
					</div>
				)}
				<SpacingVisualizer
					style={{
						maxWidth: !inheritMaxWidth && previewMaxWidth ? previewMaxWidth + maxWidthUnit : undefined,
					}}
					type="inside"
					forceHide={resizingVisually}
					forceShow={paddingMouseOver.isMouseOver}
					spacing={[
						getSpacingOptionOutput(previewPaddingTop, paddingUnit ? paddingUnit : 'px'),
						getSpacingOptionOutput(previewPaddingRight, paddingUnit ? paddingUnit : 'px'),
						getSpacingOptionOutput(previewPaddingBottom, paddingUnit ? paddingUnit : 'px'),
						getSpacingOptionOutput(previewPaddingLeft, paddingUnit ? paddingUnit : 'px'),
					]}
				/>
				<SpacingVisualizer
					type="outside"
					forceShow={marginMouseOver.isMouseOver}
					spacing={[
						getSpacingOptionOutput(previewMarginTop, marginUnit ? marginUnit : 'px'),
						0,
						getSpacingOptionOutput(previewMarginBottom, marginUnit ? marginUnit : 'px'),
						0,
					]}
				/>
			</RowBackground>
		</>
	);
};

export default FlxbkRowLayout;
