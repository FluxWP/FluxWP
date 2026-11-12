/**
 * BLOCK: Flux Off Canvas block
 */

/**
 * Import Css
 */
import './editor.scss';

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal block libraries
 */
import { useSelect, useDispatch } from '@wordpress/data';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';

import { uniqueIdHelper, useEditorElement, getPreviewSize } from '@flx-blocks/helpers';
import {
	SelectParentBlock,
	FlxbkRadioButtons,
	ResponsiveFlxbkRadioButtons,
	FlxbkPanelBody,
	PopColorControl,
	InspectorControlTabs,
	ResponsiveMeasureRangeControl,
	ResponsiveRangeControls,
	SmallResponsiveControl,
	ResponsiveAlignControls,
	ResponsiveBorderControl,
	ResponsiveMeasurementControls,
	FlxbkIconPicker,
	HoverToggleControl,
	IconRender,
} from '@flx-blocks/components';
import { TextControl, RangeControl } from '@wordpress/components';
/**
 * Internal dependencies
 */
import { useEffect, useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import BackendStyles from './components/backend-styles';

export function Edit(props) {
	const { attributes, setAttributes, clientId, isSelected } = props;
	const [activeTab, setActiveTab] = useState('general');

	const {
		uniqueID,
		slideFrom,
		slideFromTablet,
		slideFromMobile,
		backgroundColor,
		backgroundColorTablet,
		backgroundColorMobile,
		pageBackgroundColor,
		pageBackgroundColorTablet,
		pageBackgroundColorMobile,
		padding,
		paddingTablet,
		paddingMobile,
		paddingUnit,
		widthType,
		widthTypeTablet,
		widthTypeMobile,
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		maxWidthUnit,
		containerMaxWidth,
		containerMaxWidthTablet,
		containerMaxWidthMobile,
		containerMaxWidthUnit,
		hAlign,
		hAlignTablet,
		hAlignMobile,
		vAlign,
		vAlignTablet,
		vAlignMobile,
		border,
		borderTablet,
		borderMobile,
		borderRadius,
		borderRadiusTablet,
		borderRadiusMobile,
		borderRadiusUnit,
		closeIcon,
		closeLabel,
		closeLineWidth,
		closeIconSize,
		closeIconSizeTablet,
		closeIconSizeMobile,
		closeIconColor,
		closeIconColorTablet,
		closeIconColorMobile,
		closeIconColorHover,
		closeIconColorHoverTablet,
		closeIconColorHoverMobile,
		closeIconBackgroundColor,
		closeIconBackgroundColorTablet,
		closeIconBackgroundColorMobile,
		closeIconBackgroundColorHover,
		closeIconBackgroundColorHoverTablet,
		closeIconBackgroundColorHoverMobile,
		closeIconPadding,
		closeIconPaddingTablet,
		closeIconPaddingMobile,
		closeIconPaddingUnit,
		closeIconMargin,
		closeIconMarginTablet,
		closeIconMarginMobile,
		closeIconMarginUnit,
		closeIconBorder,
		closeIconBorderTablet,
		closeIconBorderMobile,
		closeIconBorderHover,
		closeIconBorderHoverTablet,
		closeIconBorderHoverMobile,
		closeIconBorderRadius,
		closeIconBorderRadiusTablet,
		closeIconBorderRadiusMobile,
		closeIconBorderRadiusUnit,
	} = attributes;

	const { setOffCanvasOpenId } = useDispatch('flxblocks/data');
	const { selectBlock } = useDispatch(blockEditorStore);

	const { previewDevice, parentClientId, showOffCanvas, childSelected } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				showOffCanvas: select('flxblocks/data').getOpenOffCanvasId() === clientId,
				childSelected: select('core/block-editor').hasSelectedInnerBlock(clientId, true),
				parentClientId: select('core/block-editor').getBlockParents(clientId)[0],
			};
		},
		[clientId]
	);

	uniqueIdHelper(props);

	const selfOrChildSelected = () => {
		return isSelected || childSelected;
	};

	const ref = useRef();

	const handleModalOverlayClick = (e) => {
		if (e.target.classList.contains('fxb-off-canvas-overlay')) {
			setOffCanvasOpenId(null);
			selectBlock(parentClientId);
		}
	};

	const editorElement = useEditorElement(ref, [selfOrChildSelected, showOffCanvas]);
	const previewCloseIconSize = getPreviewSize(previewDevice, closeIconSize, closeIconSizeTablet, closeIconSizeMobile);
	const previewSlideFrom = getPreviewSize(previewDevice, slideFrom, slideFromTablet, slideFromMobile);
	const previewWidthType = getPreviewSize(previewDevice, widthType, widthTypeTablet, widthTypeMobile);
	const active = selfOrChildSelected() || showOffCanvas;

	const classes = classnames('wp-block-flx-off-canvas', `off-canvas-side-${previewSlideFrom}`, {
		active,
		[`wp-block-flx-off-canvas${uniqueID}`]: uniqueID,
	});

	const blockProps = useBlockProps({
		className: classes,
	});

	const innerNavClasses = classnames('fxb-off-canvas-inner');

	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{
			className: innerNavClasses,
		},
		{
			templateLock: false,
			template: [['core/paragraph', { placeholder: __('Create Awesome', 'flx-blocks') }]],
		}
	);

	const overlayClasses = classnames('fxb-off-canvas-overlay', {
		[`fxb-off-canvas-overlay${uniqueID}`]: uniqueID,
	});

	const styleColorControls = (size = '', suffix = '') => {
		const backgroundColorValue = attributes['backgroundColor' + suffix + size];
		const pageBackgroundColorValue = attributes['pageBackgroundColor' + suffix + size];
		return (
			<>
				<PopColorControl
					label={__('Background', 'flx-blocks')}
					value={backgroundColorValue}
					default={''}
					onChange={(value) => setAttributes({ ['backgroundColor' + suffix + size]: value })}
					key={'normal'}
				/>
				{previewWidthType === 'partial' && (
					<PopColorControl
						label={__('Page Background', 'flx-blocks')}
						value={pageBackgroundColorValue}
						default={'rgba(0, 0, 0, 0.6)'}
						onChange={(value) => setAttributes({ ['pageBackgroundColor' + suffix + size]: value })}
						key={'normalb'}
					/>
				)}
			</>
		);
	};

	const styleColorControlsCloseIcon = (size = '', suffix = '') => {
		const closeIconColorValue = attributes['closeIconColor' + suffix + size];
		const closeIconBackgroundColorValue = attributes['closeIconBackgroundColor' + suffix + size];
		return (
			<>
				<PopColorControl
					label={__('Color', 'flx-blocks')}
					value={closeIconColorValue}
					default={''}
					onChange={(value) => setAttributes({ ['closeIconColor' + suffix + size]: value })}
					key={'closeIcon' + suffix}
				/>
				<PopColorControl
					label={__('Background', 'flx-blocks')}
					value={closeIconBackgroundColorValue}
					default={''}
					onChange={(value) => setAttributes({ ['closeIconBackgroundColor' + suffix + size]: value })}
					key={'closeIconb' + suffix}
				/>
			</>
		);
	};

	return (
		<>
			<InspectorControls>
				<SelectParentBlock
					label={__('View Header Settings', 'flx-blocks')}
					clientId={clientId}
					parentSlug={'flx/header'}
				/>

				<InspectorControlTabs panelName={'off-canvas'} setActiveTab={setActiveTab} activeTab={activeTab} />
				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody>
							<FlxbkRadioButtons
								label={__('Show off canvas content in editor when not selected', 'flx-blocks')}
								value={showOffCanvas}
								options={[
									{ value: true, label: __('Show', 'flx-blocks') },
									{ value: false, label: __('Hide', 'flx-blocks') },
								]}
								hideLabel={false}
								onChange={(value) => {
									setOffCanvasOpenId(value ? clientId : null);
								}}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Display Settings', 'flx-blocks')}
							panelName={'fxb-off-canvas-display'}
							initialOpen={true}
						>
							<ResponsiveFlxbkRadioButtons
								label={__('Slide in from', 'flx-blocks')}
								value={slideFrom}
								tabletValue={slideFromTablet}
								mobileValue={slideFromMobile}
								options={[
									{ value: 'left', label: __('Left', 'flx-blocks') },
									{ value: 'right', label: __('Right', 'flx-blocks') },
								]}
								onChange={(value) => {
									setAttributes({ slideFrom: value });
								}}
								onChangeTablet={(value) => {
									setAttributes({ slideFromTablet: value });
								}}
								onChangeMobile={(value) => {
									setAttributes({ slideFromMobile: value });
								}}
							/>

							<ResponsiveFlxbkRadioButtons
								label={__('Content Width', 'flx-blocks')}
								value={widthType}
								tabletValue={widthTypeTablet}
								mobileValue={widthTypeMobile}
								options={[
									{ value: 'partial', label: __('Partial', 'flx-blocks') },
									{ value: 'full', label: __('Full', 'flx-blocks') },
								]}
								onChange={(value) => {
									setAttributes({ widthType: value });
								}}
								onChangeTablet={(value) => {
									setAttributes({ widthTypeTablet: value });
								}}
								onChangeMobile={(value) => {
									setAttributes({ widthTypeMobile: value });
								}}
							/>

							{previewWidthType === 'partial' && (
								<>
									<ResponsiveRangeControls
										label={__('Max Width', 'flx-blocks')}
										value={maxWidth !== 0 ? maxWidth : ''}
										onChange={(value) => setAttributes({ maxWidth: value })}
										tabletValue={maxWidthTablet ? maxWidthTablet : ''}
										onChangeTablet={(value) => setAttributes({ maxWidthTablet: value })}
										mobileValue={maxWidthMobile ? maxWidthMobile : ''}
										onChangeMobile={(value) => setAttributes({ maxWidthMobile: value })}
										min={maxWidthUnit === 'px' ? 250 : 0}
										max={maxWidthUnit === 'px' ? 3000 : 100}
										step={1}
										unit={maxWidthUnit}
										onUnit={(value) => setAttributes({ maxWidthUnit: value })}
										units={['px', 'vw', '%']}
										reset={() =>
											setAttributes({
												maxWidth: '',
												maxWidthTablet: '',
												maxWidthMobile: '',
												maxWidthUnit: 'px',
											})
										}
									/>
								</>
							)}

							<ResponsiveRangeControls
								label={__('Content Max Width', 'flx-blocks')}
								value={containerMaxWidth ? containerMaxWidth : ''}
								onChange={(value) => setAttributes({ containerMaxWidth: value })}
								tabletValue={containerMaxWidthTablet ? containerMaxWidthTablet : ''}
								onChangeTablet={(value) => setAttributes({ containerMaxWidthTablet: value })}
								mobileValue={containerMaxWidthMobile ? containerMaxWidthMobile : ''}
								onChangeMobile={(value) => setAttributes({ containerMaxWidthMobile: value })}
								min={0}
								max={containerMaxWidthUnit == 'px' ? 3000 : 100}
								step={1}
								unit={containerMaxWidthUnit}
								onUnit={(value) => setAttributes({ containerMaxWidthUnit: value })}
								showUnit={true}
								units={['px', '%', 'vw']}
								reset={() =>
									setAttributes({
										containerMaxWidth: '',
										containerMaxWidthTablet: '',
										containerMaxWidthMobile: '',
										containerMaxWidthUnit: 'px',
									})
								}
							/>
							<ResponsiveAlignControls
								label={__('Content Alignment', 'flx-blocks')}
								value={hAlign ? hAlign : ''}
								tabletValue={hAlignTablet ? hAlignTablet : ''}
								mobileValue={hAlignMobile ? hAlignMobile : ''}
								onChange={(nextAlign) => setAttributes({ hAlign: nextAlign ? nextAlign : 'center' })}
								onChangeTablet={(nextAlign) =>
									setAttributes({ hAlignTablet: nextAlign ? nextAlign : '' })
								}
								onChangeMobile={(nextAlign) =>
									setAttributes({ hAlignMobile: nextAlign ? nextAlign : '' })
								}
								type={'text'}
							/>
							<ResponsiveAlignControls
								label={__('Content Vertical Alignment', 'flx-blocks')}
								value={vAlign ? vAlign : ''}
								tabletValue={vAlignTablet ? vAlignTablet : ''}
								mobileValue={vAlignMobile ? vAlignMobile : ''}
								onChange={(nextAlign) => setAttributes({ vAlign: nextAlign ? nextAlign : '' })}
								onChangeTablet={(nextAlign) =>
									setAttributes({ vAlignTablet: nextAlign ? nextAlign : '' })
								}
								onChangeMobile={(nextAlign) =>
									setAttributes({ vAlignMobile: nextAlign ? nextAlign : '' })
								}
								type={'vertical'}
							/>
						</FlxbkPanelBody>

						<FlxbkPanelBody
							title={__('Close Trigger Settings', 'flx-blocks')}
							panelName={'fxb-off-canvas-close-trigger'}
							initialOpen={false}
						>
							<FlxbkIconPicker
								value={closeIcon}
								onChange={(value) => setAttributes({ closeIcon: value })}
								allowClear={true}
							/>
							{closeIcon && 'fe' === closeIcon.substring(0, 2) && (
								<RangeControl
									label={__('Icon Line Width', 'flx-blocks')}
									value={closeLineWidth}
									onChange={(value) => setAttributes({ closeLineWidth: value })}
									step={0.5}
									min={0.5}
									max={4}
								/>
							)}
							<ResponsiveRangeControls
								label={__('Icon Size', 'flx-blocks')}
								value={closeIconSize}
								tabletValue={closeIconSizeTablet ? closeIconSizeTablet : ''}
								mobileValue={closeIconSizeMobile ? closeIconSizeMobile : ''}
								onChange={(value) => setAttributes({ closeIconSize: value })}
								onChangeTablet={(value) => setAttributes({ closeIconSizeTablet: value })}
								onChangeMobile={(value) => setAttributes({ closeIconSizeMobile: value })}
								units={['px']}
								unit={'px'}
								min={5}
								max={250}
								step={1}
							/>
							<TextControl
								label={__('Button Label Attribute for Accessibility', 'flx-blocks')}
								value={closeLabel}
								placeholder={__('Close Menu', 'flx-blocks')}
								onChange={(value) => setAttributes({ closeLabel: value })}
							/>
						</FlxbkPanelBody>
					</>
				)}

				{activeTab === 'style' && (
					<>
						<FlxbkPanelBody>
							<SmallResponsiveControl
								label={__('Colors', 'flx-blocks')}
								desktopChildren={styleColorControls()}
								tabletChildren={styleColorControls('Tablet')}
								mobileChildren={styleColorControls('Mobile')}
							></SmallResponsiveControl>
							<ResponsiveBorderControl
								label={__('Border', 'flx-blocks')}
								value={border}
								tabletValue={borderTablet}
								mobileValue={borderMobile}
								onChange={(value) => setAttributes({ border: value })}
								onChangeTablet={(value) => setAttributes({ borderTablet: value })}
								onChangeMobile={(value) => setAttributes({ borderMobile: value })}
							/>
							<ResponsiveMeasurementControls
								label={__('Border Radius', 'flx-blocks')}
								value={borderRadius}
								tabletValue={borderRadiusTablet}
								mobileValue={borderRadiusMobile}
								onChange={(value) => setAttributes({ borderRadius: value })}
								onChangeTablet={(value) => setAttributes({ borderRadiusTablet: value })}
								onChangeMobile={(value) => setAttributes({ borderRadiusMobile: value })}
								min={0}
								max={borderRadiusUnit === 'em' || borderRadiusUnit === 'rem' ? 24 : 100}
								step={borderRadiusUnit === 'em' || borderRadiusUnit === 'rem' ? 0.1 : 1}
								unit={borderRadiusUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ borderRadiusUnit: value })}
								isBorderRadius={true}
								allowEmpty={true}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Close Trigger Styles', 'flx-blocks')}
							panelName={'fxb-off-canvas-close-trigger-style'}
							initialOpen={false}
						>
							<HoverToggleControl
								normal={
									<>
										<SmallResponsiveControl
											label={__('Colors', 'flx-blocks')}
											desktopChildren={styleColorControlsCloseIcon()}
											tabletChildren={styleColorControlsCloseIcon('Tablet')}
											mobileChildren={styleColorControlsCloseIcon('Mobile')}
										></SmallResponsiveControl>
										<ResponsiveBorderControl
											label={__('Border', 'flx-blocks')}
											value={closeIconBorder}
											tabletValue={closeIconBorderTablet}
											mobileValue={closeIconBorderMobile}
											onChange={(value) => setAttributes({ closeIconBorder: value })}
											onChangeTablet={(value) => setAttributes({ closeIconBorderTablet: value })}
											onChangeMobile={(value) => setAttributes({ closeIconBorderMobile: value })}
											key={'normalbr'}
										/>
									</>
								}
								hover={
									<>
										<SmallResponsiveControl
											label={__('Hover Colors', 'flx-blocks')}
											desktopChildren={styleColorControlsCloseIcon('', 'Hover')}
											tabletChildren={styleColorControlsCloseIcon('Tablet', 'Hover')}
											mobileChildren={styleColorControlsCloseIcon('Mobile', 'Hover')}
										></SmallResponsiveControl>
										<ResponsiveBorderControl
											label={__('Hover Border', 'flx-blocks')}
											value={closeIconBorderHover}
											tabletValue={closeIconBorderHoverTablet}
											mobileValue={closeIconBorderHoverMobile}
											onChange={(value) => setAttributes({ closeIconBorderHover: value })}
											onChangeTablet={(value) =>
												setAttributes({ closeIconBorderHoverTablet: value })
											}
											onChangeMobile={(value) =>
												setAttributes({ closeIconBorderHoverMobile: value })
											}
											key={'normalbrhv'}
										/>
									</>
								}
							/>

							<ResponsiveMeasurementControls
								label={__('Border Radius', 'flx-blocks')}
								value={closeIconBorderRadius}
								tabletValue={closeIconBorderRadiusTablet}
								mobileValue={closeIconBorderRadiusMobile}
								onChange={(value) => setAttributes({ closeIconBorderRadius: value })}
								onChangeTablet={(value) => setAttributes({ closeIconBorderRadiusTablet: value })}
								onChangeMobile={(value) => setAttributes({ closeIconBorderRadiusMobile: value })}
								min={0}
								max={
									closeIconBorderRadiusUnit === 'em' || closeIconBorderRadiusUnit === 'rem' ? 24 : 100
								}
								step={
									closeIconBorderRadiusUnit === 'em' || closeIconBorderRadiusUnit === 'rem' ? 0.1 : 1
								}
								unit={closeIconBorderRadiusUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ closeIconBorderRadiusUnit: value })}
								isBorderRadius={true}
								allowEmpty={true}
							/>
						</FlxbkPanelBody>
					</>
				)}

				{activeTab === 'advanced' && (
					<>
						<FlxbkPanelBody>
							<ResponsiveMeasureRangeControl
								label={__('Padding', 'flx-blocks')}
								value={padding}
								tabletValue={paddingTablet}
								mobileValue={paddingMobile}
								onChange={(value) => {
									setAttributes({ padding: value });
								}}
								onChangeTablet={(value) => setAttributes({ paddingTablet: value })}
								onChangeMobile={(value) => setAttributes({ paddingMobile: value })}
								min={
									paddingUnit === 'em' || paddingUnit === 'rem'
										? -12
										: paddingUnit === 'px'
											? -999
											: -100
								}
								max={
									paddingUnit === 'em' || paddingUnit === 'rem'
										? 24
										: paddingUnit === 'px'
											? 999
											: 100
								}
								step={paddingUnit === 'em' || paddingUnit === 'rem' ? 0.1 : 1}
								unit={paddingUnit}
								units={['px', 'em', 'rem', '%', 'vh']}
								onUnit={(value) => setAttributes({ paddingUnit: value })}
								ghostDefault={['md', 'md', 'md', 'md']}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody title={__('Close Trigger Spacing', 'flx-blocks')} initialOpen={false}>
							<ResponsiveMeasureRangeControl
								label={__('Padding', 'flx-blocks')}
								value={closeIconPadding}
								tabletValue={closeIconPaddingTablet}
								mobileValue={closeIconPaddingMobile}
								onChange={(value) => setAttributes({ closeIconPadding: value })}
								onChangeTablet={(value) => setAttributes({ closeIconPaddingTablet: value })}
								onChangeMobile={(value) => setAttributes({ closeIconPaddingMobile: value })}
								min={0}
								max={
									closeIconPaddingUnit === 'em' || closeIconPaddingUnit === 'rem'
										? 25
										: closeIconPaddingUnit === 'px'
											? 400
											: 100
								}
								step={closeIconPaddingUnit === 'em' || closeIconPaddingUnit === 'rem' ? 0.1 : 1}
								unit={closeIconPaddingUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ closeIconPaddingUnit: value })}
							/>

							<ResponsiveMeasureRangeControl
								label={__('Margin', 'flx-blocks')}
								value={closeIconMargin}
								tabletValue={closeIconMarginTablet}
								mobileValue={closeIconMarginMobile}
								onChange={(value) => setAttributes({ closeIconMargin: value })}
								onChangeTablet={(value) => setAttributes({ closeIconMarginTablet: value })}
								onChangeMobile={(value) => setAttributes({ closeIconMarginMobile: value })}
								min={0}
								max={
									closeIconMarginUnit === 'em' || closeIconMarginUnit === 'rem'
										? 25
										: closeIconMarginUnit === 'px'
											? 400
											: 100
								}
								step={closeIconMarginUnit === 'em' || closeIconMarginUnit === 'rem' ? 0.1 : 1}
								unit={closeIconMarginUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setAttributes({ closeIconMarginUnit: value })}
							/>
						</FlxbkPanelBody>
					</>
				)}
			</InspectorControls>
			<BackendStyles {...props} previewDevice={previewDevice} editorElement={editorElement} active={active} />
			<div {...blockProps}>
				{closeIcon && previewCloseIconSize && (
					<button className="fxb-off-canvas-close">
						<IconRender
							className={`fxb-off-canvas-close-icon`}
							name={closeIcon}
							size={previewCloseIconSize}
							strokeWidth={'fe' === closeIcon.substring(0, 2) ? closeLineWidth : undefined}
						/>
					</button>
				)}
				{/* <div className="fxb-off-canvas-label">{__('Off Canvas Content', 'flx-blocks')}</div> */}
				<div {...innerBlocksProps}>{active && children}</div>
			</div>
			<div
				className={overlayClasses}
				ref={ref}
				onClick={(e) => (!showOffCanvas ? handleModalOverlayClick(e) : null)}
			></div>
		</>
	);
}

export default Edit;
