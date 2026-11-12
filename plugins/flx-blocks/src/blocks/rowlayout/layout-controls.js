/**
 * BLOCK: Flux Row / Layout
 */

/**
 * Import Icons
 */
import {
	rowIcon,
	collapseRowIcon,
	collapseRowThreeIcon,
	collapseRowFourIcon,
	collapseRowFiveIcon,
	collapseRowSixIcon,
	twoColIcon,
	gridIcon,
	threeColIcon,
	threeGridIcon,
	lastRowIcon,
	firstRowIcon,
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
 * Import Flux Components
 */
import {
	PopColorControl,
	SmallResponsiveControl,
	FlxbkIconPicker,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	StepControls,
	FlxbkRadioButtons,
	ResponsiveRadioRangeControls,
} from '@flx-blocks/components';
import { showSettings } from '@flx-blocks/helpers';

/**
 * Import Block Specific Components
 */
import renderSVGDivider from './render-svg-divider';
import metadata from './block.json';
/**
 * Import WordPress Internals
 */
import { Fragment } from '@wordpress/element';

import { ToggleControl, SelectControl } from '@wordpress/components';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

/**
 * Build the row edit
 */
function LayoutControls({
	clientId,
	attributes,
	setAttributes,
	updateColumns,
	innerItemCount,
	widthString,
	previewDevice,
	isSiteStylesPanel,
}) {
	const {
		uniqueID,
		columns,
		mobileLayout,
		currentTab,
		colLayout,
		tabletLayout,
		columnGutter,
		customGutter,
		customRowGutter,
		collapseGutter,
		tabletGutter,
		mobileGutter,
		tabletRowGutter,
		mobileRowGutter,
		gutterType,
		rowGutterType,
		collapseOrder,
		topPadding,
		bottomPadding,
		leftPadding,
		rightPadding,
		topPaddingM,
		bottomPaddingM,
		leftPaddingM,
		rightPaddingM,
		topMargin,
		bottomMargin,
		topMarginM,
		bottomMarginM,
		bgColor,
		bgImg,
		bgImgAttachment,
		bgImgSize,
		bgImgPosition,
		bgImgRepeat,
		bgImgID,
		verticalAlignment,
		overlayOpacity,
		overlayBgImg,
		overlayBgImgAttachment,
		overlayBgImgID,
		overlayBgImgPosition,
		overlayBgImgRepeat,
		overlayBgImgSize,
		currentOverlayTab,
		overlayBlendMode,
		overlayGradAngle,
		overlayGradLoc,
		overlayGradLocSecond,
		overlayGradType,
		overlay,
		overlaySecond,
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
		secondColumnWidth,
		textColor,
		linkColor,
		linkHoverColor,
		tabletPadding,
		topMarginT,
		bottomMarginT,
		minHeightUnit,
		maxWidthUnit,
		marginUnit,
		columnsUnlocked,
		tabletBackground,
		tabletOverlay,
		mobileBackground,
		mobileOverlay,
		columnsInnerHeight,
		zIndex,
		backgroundInline,
		backgroundSettingTab,
		backgroundSliderCount,
		backgroundSlider,
		inheritMaxWidth,
		backgroundSliderSettings,
		backgroundVideo,
		backgroundVideoType,
		overlaySecondOpacity,
		overlayFirstOpacity,
		paddingUnit,
		align,
		minHeightTablet,
		minHeightMobile,
		bgColorClass,
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
		borderWidth,
		tabletBorderWidth,
		mobileBorderWidth,
		borderRadius,
		tabletBorderRadius,
		mobileBorderRadius,
		border,
		tabletBorder,
		mobileBorder,
		isPrebuiltModal,
		responsiveMaxWidth,
		flxbkBlockCSS,
		breakoutLeft,
		breakoutRight,
		topSepHeightUnit,
		bottomSepHeightUnit,
	} = attributes;

	const editorDocument = document.querySelector('iframe[name="editor-canvas"]')?.contentWindow.document || document;
	let layoutOptions;
	let mobileLayoutOptions;
	if (2 === columns) {
		layoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: twoColIcon },
			{ value: 'left-golden', label: __('Left Heavy 66/33', 'flx-blocks'), icon: twoLeftGoldenIcon },
			{ value: 'right-golden', label: __('Right Heavy 33/66', 'flx-blocks'), icon: twoRightGoldenIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowIcon },
		];
	} else if (3 === columns) {
		layoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: threeColIcon },
			{ value: 'left-half', label: __('Left Heavy 50/25/25', 'flx-blocks'), icon: leftHalfIcon },
			{ value: 'right-half', label: __('Right Heavy 25/25/50', 'flx-blocks'), icon: rightHalfIcon },
			{ value: 'center-half', label: __('Center Heavy 25/50/25', 'flx-blocks'), icon: centerHalfIcon },
			{ value: 'center-wide', label: __('Wide Center 20/60/20', 'flx-blocks'), icon: wideCenterIcon },
			{ value: 'center-exwide', label: __('Wider Center 15/70/15', 'flx-blocks'), icon: exWideCenterIcon },
			{
				value: 'first-row',
				label: __('First Row, Next Columns 100 – 50/50', 'flx-blocks'),
				icon: firstRowIcon,
			},
			{
				value: 'last-row',
				label: __('Last Row, Previous Columns 50/50 – 100', 'flx-blocks'),
				icon: lastRowIcon,
			},
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowThreeIcon },
		];
	} else if (4 === columns) {
		layoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: fourColIcon },
			{ value: 'left-forty', label: __('Left Heavy 40/20/20/20', 'flx-blocks'), icon: lFourFortyIcon },
			{ value: 'right-forty', label: __('Right Heavy 20/20/20/40', 'flx-blocks'), icon: rFourFortyIcon },
			{ value: 'two-grid', label: __('Two Column Grid', 'flx-blocks'), icon: gridIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowFourIcon },
		];
	} else if (5 === columns) {
		layoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: fiveColIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowFiveIcon },
		];
	} else if (6 === columns) {
		layoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: sixColIcon },
			{ value: 'two-grid', label: __('Two Column Grid', 'flx-blocks'), icon: gridIcon },
			{ value: 'three-grid', label: __('Three Column Grid', 'flx-blocks'), icon: threeGridIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowSixIcon },
		];
	} else {
		layoutOptions = [{ value: 'equal', label: __('Single Row', 'flx-blocks'), icon: rowIcon }];
	}
	if (2 === columns) {
		mobileLayoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: twoColIcon },
			{ value: 'left-golden', label: __('Left Heavy 66/33', 'flx-blocks'), icon: twoLeftGoldenIcon },
			{ value: 'right-golden', label: __('Right Heavy 33/66', 'flx-blocks'), icon: twoRightGoldenIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowIcon },
		];
	} else if (3 === columns) {
		mobileLayoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: threeColIcon },
			{ value: 'left-half', label: __('Left Heavy 50/25/25', 'flx-blocks'), icon: leftHalfIcon },
			{ value: 'right-half', label: __('Right Heavy 25/25/50', 'flx-blocks'), icon: rightHalfIcon },
			{ value: 'center-half', label: __('Center Heavy 25/50/25', 'flx-blocks'), icon: centerHalfIcon },
			{ value: 'center-wide', label: __('Wide Center 20/60/20', 'flx-blocks'), icon: wideCenterIcon },
			{ value: 'center-exwide', label: __('Wider Center 15/70/15', 'flx-blocks'), icon: exWideCenterIcon },
			{
				value: 'first-row',
				label: __('First Row, Next Columns 100 – 50/50', 'flx-blocks'),
				icon: firstRowIcon,
			},
			{
				value: 'last-row',
				label: __('Last Row, Previous Columns 50/50 – 100', 'flx-blocks'),
				icon: lastRowIcon,
			},
			{ value: 'two-grid', label: __('Two Column Grid', 'flx-blocks'), icon: gridIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowThreeIcon },
		];
	} else if (4 === columns) {
		mobileLayoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: fourColIcon },
			{ value: 'left-forty', label: __('Left Heavy 40/20/20/20', 'flx-blocks'), icon: lFourFortyIcon },
			{ value: 'right-forty', label: __('Right Heavy 20/20/20/40', 'flx-blocks'), icon: rFourFortyIcon },
			{ value: 'two-grid', label: __('Two Column Grid', 'flx-blocks'), icon: gridIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowFourIcon },
		];
	} else if (5 === columns) {
		mobileLayoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: fiveColIcon },
			{ value: 'two-grid', label: __('Two Column Grid', 'flx-blocks'), icon: gridIcon },
			{ value: 'three-grid', label: __('Three Column Grid', 'flx-blocks'), icon: threeGridIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowFiveIcon },
		];
	} else if (6 === columns) {
		mobileLayoutOptions = [
			{ value: 'equal', label: __('Equal', 'flx-blocks'), icon: sixColIcon },
			{ value: 'two-grid', label: __('Two Column Grid', 'flx-blocks'), icon: gridIcon },
			{ value: 'three-grid', label: __('Three Column Grid', 'flx-blocks'), icon: threeGridIcon },
			{ value: 'row', label: __('Collapse to Rows', 'flx-blocks'), icon: collapseRowSixIcon },
		];
	} else {
		mobileLayoutOptions = [{ value: 'row', label: __('Single Row', 'flx-blocks'), icon: rowIcon }];
	}
	const selectColLayout = columns ? widthString : colLayout;
	return (
		<Fragment>
			<>
				{showSettings('basicLayout', 'flx/rowlayout') && (
					<>
						<FlxbkPanelBody panelName={'fxb-row-basic-layout'}>
							{!isSiteStylesPanel && (
								<StepControls
									label={__('Columns', 'flx-blocks')}
									value={columns}
									onChange={(nextColumns) => {
										updateColumns(innerItemCount, nextColumns);
										setAttributes({
											columns: nextColumns,
											colLayout: 'equal',
											firstColumnWidth: undefined,
											secondColumnWidth: undefined,
											thirdColumnWidth: undefined,
											fourthColumnWidth: undefined,
											fifthColumnWidth: undefined,
											sixthColumnWidth: undefined,
											firstColumnWidthTablet: undefined,
											secondColumnWidthTablet: undefined,
											thirdColumnWidthTablet: undefined,
											fourthColumnWidthTablet: undefined,
											fifthColumnWidthTablet: undefined,
											sixthColumnWidthTablet: undefined,
											firstColumnWidthMobile: undefined,
											secondColumnWidthMobile: undefined,
											thirdColumnWidthMobile: undefined,
											fourthColumnWidthMobile: undefined,
											fifthColumnWidthMobile: undefined,
											sixthColumnWidthMobile: undefined,
											tabletLayout: 'inherit',
											mobileLayout: 'row',
										});
									}}
									min={1}
									max={6}
								/>
							)}
							{!isSiteStylesPanel && columns > 1 && (
								<SmallResponsiveControl
									label={__('Layout', 'flx-blocks')}
									desktopChildren={
										<FlxbkRadioButtons
											value={selectColLayout}
											options={layoutOptions}
											wrap={true}
											hideLabel={true}
											className={'flxbk-row-layout-radio-btns'}
											onChange={(value) => {
												setAttributes({
													colLayout: value,
												});
												setAttributes({
													firstColumnWidth: undefined,
													secondColumnWidth: undefined,
													thirdColumnWidth: undefined,
													fourthColumnWidth: undefined,
													fifthColumnWidth: undefined,
													sixthColumnWidth: undefined,
												});
											}}
										/>
									}
									tabletChildren={
										<FlxbkRadioButtons
											value={tabletLayout}
											options={mobileLayoutOptions}
											wrap={true}
											className={'flxbk-row-layout-radio-btns'}
											hideLabel={true}
											onChange={(value) => {
												if (value === tabletLayout) {
													setAttributes({
														tabletLayout: 'inherit',
														firstColumnWidthTablet: undefined,
														secondColumnWidthTablet: undefined,
														thirdColumnWidthTablet: undefined,
														fourthColumnWidthTablet: undefined,
														fifthColumnWidthTablet: undefined,
														sixthColumnWidthTablet: undefined,
													});
												} else {
													setAttributes({
														tabletLayout: value,
														firstColumnWidthTablet: undefined,
														secondColumnWidthTablet: undefined,
														thirdColumnWidthTablet: undefined,
														fourthColumnWidthTablet: undefined,
														fifthColumnWidthTablet: undefined,
														sixthColumnWidthTablet: undefined,
													});
												}
											}}
										/>
									}
									mobileChildren={
										<FlxbkRadioButtons
											value={mobileLayout}
											options={mobileLayoutOptions}
											wrap={true}
											className={'flxbk-row-layout-radio-btns'}
											hideLabel={true}
											onChange={(value) => {
												setAttributes({
													mobileLayout: value,
													firstColumnWidthMobile: undefined,
													secondColumnWidthMobile: undefined,
													thirdColumnWidthMobile: undefined,
													fourthColumnWidthMobile: undefined,
													fifthColumnWidthMobile: undefined,
													sixthColumnWidthMobile: undefined,
												});
											}}
										/>
									}
								/>
							)}
							{(colLayout === 'grid-layout' || columns > 1) && (
								<>
									<ResponsiveRadioRangeControls
										label={__('Column Gutter', 'flx-blocks')}
										options={[
											{ value: 'none', size: 0, label: __('None', 'flx-blocks') },
											{ value: 'skinny', size: 16, label: __('SM', 'flx-blocks') },
											{ value: 'default', size: 32, label: __('MD', 'flx-blocks') },
											{ value: 'wider', size: 64, label: __('LG', 'flx-blocks') },
										]}
										value={{
											value: undefined !== columnGutter ? columnGutter : 'default',
											size:
												undefined !== customGutter && undefined !== customGutter[0]
													? customGutter[0]
													: 30,
										}}
										onChange={(value, size) => {
											setAttributes({
												columnGutter: value,
												customGutter: [size, customGutter[1] ?? '', customGutter[2] ?? ''],
											});
										}}
										tabletValue={{
											value: undefined !== tabletGutter ? tabletGutter : '',
											size:
												undefined !== customGutter && undefined !== customGutter[1]
													? customGutter[1]
													: '',
										}}
										onChangeTablet={(value, size) => {
											setAttributes({
												tabletGutter: value,
												customGutter: [customGutter[0] ?? '', size, customGutter[2] ?? ''],
											});
										}}
										mobileValue={{
											value: undefined !== mobileGutter ? mobileGutter : '',
											size:
												undefined !== customGutter && undefined !== customGutter[2]
													? customGutter[2]
													: '',
										}}
										onChangeMobile={(value, size) => {
											setAttributes({
												mobileGutter: value,
												customGutter: [customGutter[0] ?? '', customGutter[1] ?? '', size],
											});
										}}
										min={0}
										max={gutterType === 'px' ? 200 : 12}
										step={gutterType === 'px' ? 1 : 0.1}
										unit={gutterType ? gutterType : 'px'}
										onUnit={(value) => {
											setAttributes({ gutterType: value });
										}}
										units={['px', 'em', 'rem']}
										reset={true}
									/>
								</>
							)}
							<ResponsiveRadioRangeControls
								label={__('Row Gutter', 'flx-blocks')}
								options={[
									{ value: 'none', size: 0, label: __('None', 'flx-blocks') },
									{ value: 'skinny', size: 10, label: __('Sm', 'flx-blocks') },
									{ value: 'default', size: 30, label: __('Md', 'flx-blocks') },
									{ value: 'wider', size: 60, label: __('Lg', 'flx-blocks') },
								]}
								value={{
									value: undefined !== collapseGutter ? collapseGutter : 'default',
									size:
										undefined !== customRowGutter && undefined !== customRowGutter[0]
											? customRowGutter[0]
											: '',
								}}
								onChange={(value, size) => {
									setAttributes({
										collapseGutter: value,
										customRowGutter: [size, customRowGutter[1] ?? '', customRowGutter[2] ?? ''],
									});
								}}
								tabletValue={{
									value: undefined !== tabletRowGutter ? tabletRowGutter : '',
									size:
										undefined !== customRowGutter && undefined !== customRowGutter[1]
											? customRowGutter[1]
											: '',
								}}
								onChangeTablet={(value, size) => {
									setAttributes({
										tabletRowGutter: value,
										customRowGutter: [customRowGutter[0] ?? '', size, customRowGutter[2] ?? ''],
									});
								}}
								mobileValue={{
									value: undefined !== mobileRowGutter ? mobileRowGutter : '',
									size:
										undefined !== customRowGutter && undefined !== customRowGutter[2]
											? customRowGutter[2]
											: '',
								}}
								onChangeMobile={(value, size) => {
									setAttributes({
										mobileRowGutter: value,
										customRowGutter: [customRowGutter[0] ?? '', customRowGutter[1] ?? '', size],
									});
								}}
								min={0}
								max={rowGutterType === 'px' ? 200 : 12}
								step={rowGutterType === 'px' ? 1 : 0.1}
								unit={rowGutterType ? rowGutterType : 'px'}
								onUnit={(value) => {
									setAttributes({ rowGutterType: value });
								}}
								units={['px', 'em', 'rem']}
								reset={true}
							/>
							{!isSiteStylesPanel &&
								(colLayout === 'grid-layout' ||
									innerItemCount > columns ||
									previewDevice != 'Desktop') && (
									<>
										<SelectControl
											label={__('Collapse Order', 'flx-blocks')}
											value={collapseOrder}
											options={[
												{ value: 'left-to-right', label: __('Normal ->', 'flx-blocks') },
												{ value: 'right-to-left', label: __('Reverse <-', 'flx-blocks') },
											]}
											onChange={(value) => setAttributes({ collapseOrder: value })}
										/>
									</>
								)}
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Content Max Width', 'flx-blocks')}
							initialOpen={true}
							panelName={'fxb-row-max-width'}
						>
							{!isSiteStylesPanel && (
								<ToggleControl
									label={__('Inherit Max Width from Theme?', 'flx-blocks')}
									checked={undefined !== inheritMaxWidth ? inheritMaxWidth : false}
									onChange={(value) => setAttributes({ inheritMaxWidth: value })}
								/>
							)}
							{inheritMaxWidth !== true && (
								<ResponsiveRangeControls
									label={__('Custom Content Max Width', 'flx-blocks')}
									value={maxWidth ? maxWidth : ''}
									onChange={(value) => {
										setAttributes({ maxWidth: value });
									}}
									tabletValue={
										undefined !== responsiveMaxWidth && undefined !== responsiveMaxWidth[0]
											? responsiveMaxWidth[0]
											: ''
									}
									onChangeTablet={(value) => {
										setAttributes({
											responsiveMaxWidth: [
												value,
												undefined !== responsiveMaxWidth && undefined !== responsiveMaxWidth[1]
													? responsiveMaxWidth[1]
													: '',
											],
										});
									}}
									mobileValue={
										undefined !== responsiveMaxWidth && undefined !== responsiveMaxWidth[1]
											? responsiveMaxWidth[1]
											: ''
									}
									onChangeMobile={(value) => {
										setAttributes({
											responsiveMaxWidth: [
												undefined !== responsiveMaxWidth && undefined !== responsiveMaxWidth[0]
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
									units={['px', '%', 'vw']}
									reset={true}
								/>
							)}
							{align === 'full' && 2 === columns && inheritMaxWidth === true && (
								<>
									<ToggleControl
										label={__('Break Left Section Full Width?', 'flx-blocks')}
										checked={undefined !== breakoutLeft ? breakoutLeft : false}
										onChange={(value) => setAttributes({ breakoutLeft: value })}
									/>
									<ToggleControl
										label={__('Break Right Section Full Width?', 'flx-blocks')}
										checked={undefined !== breakoutRight ? breakoutRight : false}
										onChange={(value) => setAttributes({ breakoutRight: value })}
									/>
								</>
							)}
						</FlxbkPanelBody>
					</>
				)}
			</>
			<div className="fxt-sidebar-settings-spacer"></div>
			{!isSiteStylesPanel && showSettings('dividers', 'flx/rowlayout') && (
				<>
					<FlxbkPanelBody
						title={__('Top Divider', 'flx-blocks')}
						initialOpen={false}
						panelName={'fxb-row-dividers-top'}
					>
						<FlxbkIconPicker
							icons={[
								'ct',
								'cti',
								'ctd',
								'ctdi',
								'sltl',
								'sltr',
								'crv',
								'crvi',
								'crvl',
								'crvli',
								'crvr',
								'crvri',
								'wave',
								'wavei',
								'waves',
								'wavesi',
								'mtns',
								'littri',
								'littrii',
								'threelevels',
								'threelevelsi',
							]}
							value={topSep === 'none' ? '' : topSep}
							onChange={(value) => setAttributes({ topSep: value })}
							showSearch={false}
							renderFunc={(svg) => renderSVGDivider(svg, 'top', '100%')}
							theme="dividers"
							allowClear={true}
							placeholder={__('Select Divider', 'flx-blocks')}
						/>
						<PopColorControl
							label={__('Divider Color', 'flx-blocks')}
							value={topSepColor ? topSepColor : ''}
							default={'#ffffff'}
							onChange={(value) => setAttributes({ topSepColor: value })}
						/>
						<ResponsiveRangeControls
							label={__('Divider Height', 'flx-blocks')}
							value={topSepHeight}
							onChange={(value) => {
								setAttributes({ topSepHeight: value });
							}}
							tabletValue={topSepHeightTab}
							onChangeTablet={(value) => {
								setAttributes({ topSepHeightTab: value });
							}}
							mobileValue={topSepHeightMobile}
							onChangeMobile={(value) => {
								setAttributes({ topSepHeightMobile: value });
							}}
							min={0}
							max={'em' === topSepHeightUnit ? 100 : 500}
							step={'em' === topSepHeightUnit ? 0.01 : 1}
							unit={topSepHeightUnit}
							onUnit={(value) => {
								setAttributes({ topSepHeightUnit: value });
							}}
							units={['px', 'em', 'vh']}
							showUnit={true}
							defaultValue={metadata.attributes.topSepHeight.default}
							defaultTablet={metadata.attributes.topSepHeightTab.default}
							defaultMobile={metadata.attributes.topSepHeightMobile.default}
							reset={true}
						/>
						<ResponsiveRangeControls
							label={__('Divider Width', 'flx-blocks')}
							value={topSepWidth}
							onChange={(value) => {
								setAttributes({ topSepWidth: value });
							}}
							tabletValue={topSepWidthTab}
							onChangeTablet={(value) => {
								setAttributes({ topSepWidthTab: value });
							}}
							mobileValue={topSepWidthMobile}
							onChangeMobile={(value) => {
								setAttributes({ topSepWidthMobile: value });
							}}
							min={100}
							max={400}
							step={1}
							showUnit={true}
							unit={'%'}
							units={['%']}
							defaultValue={metadata.attributes.topSepWidth.default}
							defaultTablet={metadata.attributes.topSepWidthTablet.default}
							defaultMobile={metadata.attributes.topSepWidthMobile.default}
							reset={true}
						/>
					</FlxbkPanelBody>
					<FlxbkPanelBody
						title={__('Bottom Divider', 'flx-blocks')}
						initialOpen={false}
						panelName={'fxb-row-dividers'}
					>
						<FlxbkIconPicker
							icons={[
								'ct',
								'cti',
								'ctd',
								'ctdi',
								'sltl',
								'sltr',
								'crv',
								'crvi',
								'crvl',
								'crvli',
								'crvr',
								'crvri',
								'wave',
								'wavei',
								'waves',
								'wavesi',
								'mtns',
								'littri',
								'littrii',
								'threelevels',
								'threelevelsi',
							]}
							value={bottomSep === 'none' ? '' : bottomSep}
							onChange={(value) => setAttributes({ bottomSep: value })}
							showSearch={false}
							renderFunc={(svg) => renderSVGDivider(svg, 'bottom', '100%')}
							theme="dividers"
							allowClear={true}
							placeholder={__('Select Divider', 'flx-blocks')}
						/>
						<PopColorControl
							label={__('Divider Color', 'flx-blocks')}
							value={bottomSepColor ? bottomSepColor : ''}
							default={'#ffffff'}
							onChange={(value) => setAttributes({ bottomSepColor: value })}
						/>
						<ResponsiveRangeControls
							label={__('Divider Height', 'flx-blocks')}
							value={bottomSepHeight}
							onChange={(value) => {
								setAttributes({ bottomSepHeight: value });
							}}
							tabletValue={bottomSepHeightTab}
							onChangeTablet={(value) => {
								setAttributes({ bottomSepHeightTab: value });
							}}
							mobileValue={bottomSepHeightMobile}
							onChangeMobile={(value) => {
								setAttributes({ bottomSepHeightMobile: value });
							}}
							min={0}
							max={'em' === bottomSepHeightUnit ? 100 : 500}
							step={'em' === bottomSepHeightUnit ? 0.01 : 1}
							unit={bottomSepHeightUnit}
							onUnit={(value) => {
								setAttributes({ bottomSepHeightUnit: value });
							}}
							units={['px', 'em', 'vh']}
							showUnit={true}
							defaultValue={metadata.attributes.bottomSepHeight.default}
							defaultTablet={metadata.attributes.bottomSepHeightTab.default}
							defaultMobile={metadata.attributes.bottomSepHeightMobile.default}
							reset={true}
						/>
						<ResponsiveRangeControls
							label={__('Divider Width', 'flx-blocks')}
							value={bottomSepWidth}
							onChange={(value) => {
								setAttributes({ bottomSepWidth: value });
							}}
							tabletValue={bottomSepWidthTab}
							onChangeTablet={(value) => {
								setAttributes({ bottomSepWidthTab: value });
							}}
							mobileValue={bottomSepWidthMobile}
							onChangeMobile={(value) => {
								setAttributes({ bottomSepWidthMobile: value });
							}}
							min={100}
							max={400}
							step={1}
							showUnit={true}
							unit={'%'}
							units={['%']}
							defaultValue={metadata.attributes.bottomSepWidth.default}
							defaultTablet={metadata.attributes.bottomSepWidthTab.default}
							defaultMobile={metadata.attributes.bottomSepWidthMobile.default}
							reset={true}
						/>
					</FlxbkPanelBody>
				</>
			)}
		</Fragment>
	);
}
export default LayoutControls;
