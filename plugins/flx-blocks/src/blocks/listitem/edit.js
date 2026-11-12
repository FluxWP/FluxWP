/**
 * BLOCK: Flux Icon
 */

/**
 * Import Flux Components
 */
import {
	FlxbkColorOutput,
	uniqueIdHelper,
	showSettings,
	getFontSizeOptionOutput,
	getSpacingOptionOutput,
	getPreviewSize,
	getBorderStyle,
} from '@flx-blocks/helpers';

import {
	PopColorControl,
	FlxbkIconPicker,
	IconRender,
	FlxbkPanelBody,
	URLInputControl,
	InspectorControlTabs,
	SelectParentBlock,
	Tooltip,
	ResponsiveBorderControl,
	ResponsiveMeasurementControls,
	TypographyControls,
	ResponsiveMeasureRangeControl,
	GradientControl,
	FlxbkWebfontLoader,
} from '@flx-blocks/components';

import { applyFilters } from '@wordpress/hooks';

import metadata from './block.json';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';

import { InspectorControls, RichText, BlockControls, useBlockProps } from '@wordpress/block-editor';

import { useEffect, useState, useRef } from '@wordpress/element';

import {
	RangeControl,
	ToggleControl,
	SelectControl,
	ToolbarGroup,
	ToolbarButton,
	TextControl,
	Dropdown,
	Button,
	TextareaControl,
} from '@wordpress/components';

import { useSelect, useDispatch } from '@wordpress/data';
import { formatIndent, formatOutdent } from '@wordpress/icons';
import { tooltip as tooltipIcon } from '@flx-blocks/icons';

function FlxbkListItem(props) {
	const {
		attributes,
		className,
		setAttributes,
		clientId,
		isSelected,
		name,
		onReplace,
		onRemove,
		mergeBlocks,
		context,
	} = props;
	const {
		uniqueID,
		icon,
		link,
		linkNoFollow,
		linkSponsored,
		target,
		size,
		width,
		text,
		color,
		iconTitle,
		background,
		border,
		borderRadius,
		padding,
		borderWidth,
		style,
		level,
		showIcon,
		tooltipSelection,
		tooltip,
		tooltipPlacement,
		tooltipDash,
		markSize,
		markSizeType,
		markLineHeight,
		markLineType,
		markLetterSpacing,
		markTypography,
		markGoogleFont,
		markLoadGoogleFont,
		markFontSubset,
		markFontVariant,
		markFontWeight,
		markFontStyle,
		markPadding,
		markColor,
		markBG,
		markBGOpacity,
		markBorder,
		markBorderWidth,
		markBorderOpacity,
		markBorderStyle,
		markTextTransform,
		markMobilePadding,
		markTabPadding,
		markLetterSpacingType,
		markPaddingType,
		tabletMarkLetterSpacing,
		mobileMarkLetterSpacing,
		markBorderStyles,
		tabletMarkBorderStyles,
		mobileMarkBorderStyles,
		markBorderRadius,
		tabletMarkBorderRadius,
		mobileMarkBorderRadius,
		markBorderRadiusUnit,
		enableMarkGradient,
		enableMarkBackgroundGradient,
		markGradient,
		markBackgroundGradient,
	} = attributes;
	const displayIcon = icon ? icon : context['flx/listIcon'];
	const displayWidth = width ? width : context['flx/listIconWidth'];
	const [activeTab, setActiveTab] = useState('general');
	let richTextFormats = applyFilters(
		'flxbk.whitelist_richtext_formats',
		[
			'core/bold',
			'core/italic',
			'flx/mark',
			'flx/typed',
			'core/strikethrough',
			'core/superscript',
			'core/superscript',
			'toolset/inline-field',
		],
		'flx/listitem'
	);

	richTextFormats = link ? richTextFormats : undefined;

	const textRef = useRef(clientId);
	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	uniqueIdHelper(props);

	const blockProps = useBlockProps({
		className,
	});

	const onMoveLeft = () => {
		const newLevel = level - 1;

		setAttributes({ level: Math.max(newLevel, 0) });
	};
	const onMoveRight = () => {
		setAttributes({ level: level + 1 });
	};
	const iconOnlyTooltip = 'icon' === tooltipSelection ? true : false;
	const textOnlyTooltip = 'text' === tooltipSelection ? true : false;
	const WrapTag = link ? 'a' : 'span';
	const markBGString = markBG ? FlxbkColorOutput(markBG, markBGOpacity) : '';
	const previewMarkSize = getPreviewSize(
		previewDevice,
		undefined !== markSize ? markSize[0] : '',
		undefined !== markSize ? markSize[1] : '',
		undefined !== markSize ? markSize[2] : ''
	);
	const previewMarkLineHeight = getPreviewSize(
		previewDevice,
		undefined !== markLineHeight ? markLineHeight[0] : '',
		undefined !== markLineHeight ? markLineHeight[1] : '',
		undefined !== markLineHeight ? markLineHeight[2] : ''
	);
	const previewMarkLetterSpacing = getPreviewSize(
		previewDevice,
		undefined !== markLetterSpacing ? markLetterSpacing : '',
		undefined !== tabletMarkLetterSpacing ? tabletMarkLetterSpacing : '',
		undefined !== mobileMarkLetterSpacing ? mobileMarkLetterSpacing : ''
	);
	const previewMarkBorderTopStyle = getBorderStyle(
		previewDevice,
		'top',
		markBorderStyles,
		tabletMarkBorderStyles,
		mobileMarkBorderStyles
	);
	const previewMarkBorderRightStyle = getBorderStyle(
		previewDevice,
		'right',
		markBorderStyles,
		tabletMarkBorderStyles,
		mobileMarkBorderStyles
	);
	const previewMarkBorderBottomStyle = getBorderStyle(
		previewDevice,
		'bottom',
		markBorderStyles,
		tabletMarkBorderStyles,
		mobileMarkBorderStyles
	);
	const previewMarkBorderLeftStyle = getBorderStyle(
		previewDevice,
		'left',
		markBorderStyles,
		tabletMarkBorderStyles,
		mobileMarkBorderStyles
	);
	const previewMarkPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== markPadding ? markPadding[0] : 0,
		undefined !== markTabPadding ? markTabPadding[0] : '',
		undefined !== markMobilePadding ? markMobilePadding[0] : ''
	);
	const previewMarkPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== markPadding ? markPadding[1] : 0,
		undefined !== markTabPadding ? markTabPadding[1] : '',
		undefined !== markMobilePadding ? markMobilePadding[1] : ''
	);
	const previewMarkPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== markPadding ? markPadding[2] : 0,
		undefined !== markTabPadding ? markTabPadding[2] : '',
		undefined !== markMobilePadding ? markMobilePadding[2] : ''
	);
	const previewMarkPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== markPadding ? markPadding[3] : 0,
		undefined !== markTabPadding ? markTabPadding[3] : '',
		undefined !== markMobilePadding ? markMobilePadding[3] : ''
	);
	const previewMarkBorderRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== markBorderRadius ? markBorderRadius[0] : '',
		undefined !== tabletMarkBorderRadius ? tabletMarkBorderRadius[0] : '',
		undefined !== mobileMarkBorderRadius ? mobileMarkBorderRadius[0] : ''
	);
	const previewMarkBorderRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== markBorderRadius ? markBorderRadius[1] : '',
		undefined !== tabletMarkBorderRadius ? tabletMarkBorderRadius[1] : '',
		undefined !== mobileMarkBorderRadius ? mobileMarkBorderRadius[1] : ''
	);
	const previewMarkBorderRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== markBorderRadius ? markBorderRadius[2] : '',
		undefined !== tabletMarkBorderRadius ? tabletMarkBorderRadius[2] : '',
		undefined !== mobileMarkBorderRadius ? mobileMarkBorderRadius[2] : ''
	);
	const previewMarkBorderRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== markBorderRadius ? markBorderRadius[3] : '',
		undefined !== tabletMarkBorderRadius ? tabletMarkBorderRadius[3] : '',
		undefined !== mobileMarkBorderRadius ? mobileMarkBorderRadius[3] : ''
	);
	const markBorderRadiusUnitPreview = undefined !== markBorderRadiusUnit ? markBorderRadiusUnit : 'px';
	const listItemOutput = (
		<>
			{displayIcon && showIcon && (
				<Tooltip
					className="fxb-icon-list-single-wrap"
					text={tooltip && iconOnlyTooltip ? tooltip : undefined}
					placement={tooltipPlacement || 'top'}
				>
					<IconRender
						className={`fxt-svg-icon-list-single fxt-svg-icon-list-single-${displayIcon}${
							iconOnlyTooltip && tooltip ? ' fxb-icon-list-tooltip' : ''
						}${!tooltipDash && iconOnlyTooltip && tooltip ? ' fxb-list-tooltip-no-border' : ''}`}
						name={displayIcon}
						size={size ? size : '1em'}
						strokeWidth={'fe' === displayIcon.substring(0, 2) ? displayWidth : undefined}
						style={{
							color: color ? FlxbkColorOutput(color) : undefined,
							backgroundColor:
								background && style === 'stacked' ? FlxbkColorOutput(background) : undefined,
							padding: padding && style === 'stacked' ? padding + 'px' : undefined,
							borderColor: border && style === 'stacked' ? FlxbkColorOutput(border) : undefined,
							borderWidth: borderWidth && style === 'stacked' ? borderWidth + 'px' : undefined,
							borderRadius: borderRadius && style === 'stacked' ? borderRadius + '%' : undefined,
						}}
					/>
				</Tooltip>
			)}

			{!showIcon && size !== 0 && (
				<div
					style={{ display: 'inline-flex', justifyContent: 'center', alignItems: 'center' }}
					className={'fxt-svg-icon-list-single'}
				>
					<svg
						style={{ display: 'inline-block', verticalAlign: 'middle' }}
						viewBox={'0 0 24 24'}
						height={size ? size : '1em'}
						width={size ? size : '1em'}
						fill={'none'}
						stroke={displayWidth}
						preserveAspectRatio={true ? 'xMinYMin meet' : undefined}
						stroke-width={displayWidth}
					></svg>
				</div>
			)}
			<Tooltip
				className="fxb-icon-list-text-wrap"
				text={tooltip && textOnlyTooltip ? tooltip : undefined}
				placement={tooltipPlacement || 'top'}
			>
				<RichText
					tagName="div"
					ref={textRef}
					allowedFormats={richTextFormats ? richTextFormats : undefined}
					identifier="text"
					value={text}
					onChange={(value) => {
						setAttributes({ text: value });
					}}
					onSplit={(value, isOriginal) => {
						const newAttributes = { ...attributes };
						newAttributes.text = value;
						if (!isOriginal) {
							newAttributes.uniqueID = '';
							newAttributes.link = '';
						}

						const block = createBlock('flx/listitem', newAttributes);

						if (isOriginal) {
							block.clientId = clientId;
						}

						return block;
					}}
					onMerge={mergeBlocks}
					onRemove={onRemove}
					onReplace={onReplace}
					className={`fxt-svg-icon-list-text${textOnlyTooltip && tooltip ? ' fxb-icon-list-tooltip' : ''}${
						!tooltipDash && textOnlyTooltip && tooltip ? ' fxb-list-tooltip-no-border' : ''
					}`}
					data-empty={!text}
				/>
			</Tooltip>
		</>
	);
	return (
		<div {...blockProps}>
			<BlockControls>
				<ToolbarGroup group="add-indent">
					<ToolbarButton
						icon={formatOutdent}
						title={__('Outdent', 'flx-blocks')}
						describedBy={__('Outdent list item', 'flx-blocks')}
						disabled={level === 0}
						onClick={() => onMoveLeft()}
					/>
					<ToolbarButton
						icon={formatIndent}
						title={__('Indent', 'flx-blocks')}
						describedBy={__('Indent list item', 'flx-blocks')}
						isDisabled={level === 5}
						onClick={() => onMoveRight()}
					/>
				</ToolbarGroup>
				<ToolbarGroup group="tooltip">
					<Dropdown
						className="fxb-popover-inline-tooltip-container components-dropdown-menu components-toolbar"
						contentClassName="fxb-popover-inline-tooltip"
						placement="bottom"
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								className="components-dropdown-menu__toggle fxb-inline-tooltip-toolbar-icon"
								label={__('Tooltip Settings', 'flx-blocks')}
								icon={tooltipIcon}
								onClick={onToggle}
								aria-expanded={isOpen}
							/>
						)}
						renderContent={() => (
							<>
								<div className="fxb-inline-tooltip-control">
									<TextareaControl
										label={__('Tooltip Content', 'flx-blocks')}
										value={tooltip}
										onChange={(newValue) => setAttributes({ tooltip: newValue })}
									/>
									<SelectControl
										label={__('Placement', 'flx-blocks')}
										value={tooltipPlacement || 'top'}
										options={[
											{ label: __('Top', 'flx-blocks'), value: 'top' },
											{ label: __('Top Start', 'flx-blocks'), value: 'top-start' },
											{ label: __('Top End', 'flx-blocks'), value: 'top-end' },
											{ label: __('Right', 'flx-blocks'), value: 'right' },
											{ label: __('Right Start', 'flx-blocks'), value: 'right-start' },
											{ label: __('Right End', 'flx-blocks'), value: 'right-end' },
											{ label: __('Bottom', 'flx-blocks'), value: 'bottom' },
											{ label: __('Bottom Start', 'flx-blocks'), value: 'bottom-start' },
											{ label: __('Bottom End', 'flx-blocks'), value: 'bottom-end' },
											{ label: __('Left', 'flx-blocks'), value: 'left' },
											{ label: __('Left Start', 'flx-blocks'), value: 'left-start' },
											{ label: __('Left End', 'flx-blocks'), value: 'left-end' },
											{ label: __('Auto', 'flx-blocks'), value: 'auto' },
											{ label: __('Auto Start', 'flx-blocks'), value: 'auto-start' },
											{ label: __('Auto End', 'flx-blocks'), value: 'auto-end' },
										]}
										onChange={(val) => {
											setAttributes({ tooltipPlacement: val });
										}}
									/>
									<SelectControl
										label={__('Only apply tooltip to:', 'flx-blocks')}
										value={tooltipSelection || 'both'}
										options={[
											{ label: __('Text and Icon', 'flx-blocks'), value: 'both' },
											{ label: __('Text Only', 'flx-blocks'), value: 'text' },
											{ label: __('Icon Only', 'flx-blocks'), value: 'icon' },
										]}
										onChange={(val) => {
											setAttributes({ tooltipSelection: val });
										}}
									/>
									{!link && (
										<ToggleControl
											label={__('Show indicator underline', 'flx-blocks')}
											checked={tooltipDash}
											onChange={(value) => {
												setAttributes({ tooltipDash: value });
											}}
										/>
									)}
								</div>
							</>
						)}
					/>
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<SelectParentBlock clientId={clientId} />
				<InspectorControlTabs
					panelName={'listitem'}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
				/>
				{activeTab === 'general' && (
					<FlxbkPanelBody initialOpen={true} panelName={'fxb-icon-item-settings'}>
						<URLInputControl
							label={__('Link', 'flx-blocks')}
							url={link}
							onChangeUrl={(value) => {
								setAttributes({ link: value });
							}}
							additionalControls={true}
							opensInNewTab={target && '_blank' == target ? true : false}
							onChangeTarget={(value) => {
								if (value) {
									setAttributes({ target: '_blank' });
								} else {
									setAttributes({ target: '_self' });
								}
							}}
							linkNoFollow={undefined !== linkNoFollow ? linkNoFollow : false}
							onChangeFollow={(value) => setAttributes({ linkNoFollow: value })}
							linkSponsored={undefined !== linkSponsored ? linkSponsored : false}
							onChangeSponsored={(value) => setAttributes({ linkSponsored: value })}
							dynamicAttribute={'link'}
							allowClear={true}
							isSelected={isSelected}
							attributes={attributes}
							setAttributes={setAttributes}
							name={name}
							clientId={clientId}
							context={context}
						/>

						<ToggleControl
							label={__('Hide icon', 'flx-blocks')}
							checked={!showIcon}
							onChange={(value) => {
								setAttributes({ showIcon: !value });
							}}
						/>

						{showIcon && (
							<>
								<FlxbkIconPicker
									value={icon}
									onChange={(value) => {
										setAttributes({ icon: value });
									}}
									allowClear={true}
									placeholder={__('Select Icon', 'flx-blocks')}
								/>
								<TextControl
									label={__('Icon title for screen readers', 'flx-blocks')}
									help={__(
										'If no title added screen readers will ignore, good if the icon is purely decorative.',
										'flx-blocks'
									)}
									value={iconTitle}
									onChange={(value) => {
										setAttributes({ iconTitle: value });
									}}
								/>
							</>
						)}
					</FlxbkPanelBody>
				)}
				{activeTab === 'style' && (
					<>
						<FlxbkPanelBody initialOpen={true} panelName={'fxb-icon-item'}>
							<RangeControl
								label={__('Icon Size', 'flx-blocks')}
								value={size}
								onChange={(value) => {
									setAttributes({ size: value });
								}}
								min={0}
								max={250}
							/>
							{displayIcon && 'fe' === displayIcon.substring(0, 2) && (
								<RangeControl
									label={__('Line Width', 'flx-blocks')}
									value={width}
									onChange={(value) => {
										setAttributes({ width: value });
									}}
									step={0.5}
									min={0.5}
									max={4}
								/>
							)}
							<PopColorControl
								label={__('Icon Color', 'flx-blocks')}
								value={color ? color : ''}
								default={''}
								onChange={(value) => {
									setAttributes({ color: value });
								}}
							/>
							<SelectControl
								label={__('Icon Style', 'flx-blocks')}
								value={style}
								options={[
									{ value: '', label: __('Inherit', 'flx-blocks') },
									{ value: 'default', label: __('Default', 'flx-blocks') },
									{ value: 'stacked', label: __('Stacked', 'flx-blocks') },
								]}
								onChange={(value) => {
									setAttributes({ style: value });
								}}
							/>
							{style === 'stacked' && (
								<PopColorControl
									label={__('Icon Background', 'flx-blocks')}
									value={background ? background : ''}
									default={''}
									onChange={(value) => {
										setAttributes({ background: value });
									}}
								/>
							)}
							{style === 'stacked' && (
								<PopColorControl
									label={__('Border Color', 'flx-blocks')}
									value={border ? border : ''}
									default={''}
									onChange={(value) => {
										setAttributes({ border: value });
									}}
								/>
							)}
							{style === 'stacked' && (
								<RangeControl
									label={__('Border Size (px)', 'flx-blocks')}
									value={borderWidth}
									onChange={(value) => {
										setAttributes({ borderWidth: value });
									}}
									min={0}
									max={20}
								/>
							)}
							{style === 'stacked' && (
								<RangeControl
									label={__('Border Radius (%)', 'flx-blocks')}
									value={borderRadius}
									onChange={(value) => {
										setAttributes({ borderRadius: value });
									}}
									min={0}
									max={50}
								/>
							)}
							{style === 'stacked' && (
								<RangeControl
									label={__('Padding (px)', 'flx-blocks')}
									value={padding}
									onChange={(value) => {
										setAttributes({ padding: value });
									}}
									min={0}
									max={180}
								/>
							)}
						</FlxbkPanelBody>

						{showSettings('highlightSettings', 'flx/listitem') && (
							<FlxbkPanelBody
								title={__('Advanced Highlight Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-heading-highlight-settings'}
							>
								<>
									{!enableMarkGradient && (
										<PopColorControl
											label={__('Color', 'flx-blocks')}
											value={markColor ? markColor : ''}
											default={''}
											onChange={(value) => setAttributes({ markColor: value })}
										/>
									)}
									{!enableMarkBackgroundGradient && (
										<ToggleControl
											style={{ marginTop: '10px' }}
											label={__('Enable Text Gradient', 'flx-blocks')}
											help={__(
												'Enabling Text Gradient disables Background Color and Gradient.',
												'flx-blocks'
											)}
											checked={enableMarkGradient}
											onChange={(value) => setAttributes({ enableMarkGradient: value })}
										/>
									)}

									{enableMarkGradient && (
										<GradientControl
											value={markGradient}
											onChange={(value) => setAttributes({ markGradient: value })}
											gradients={[]}
										/>
									)}

									{!enableMarkGradient && !enableMarkBackgroundGradient && (
										<PopColorControl
											label={__('Background', 'flx-blocks')}
											value={markBG ? markBG : ''}
											default={''}
											onChange={(value) => setAttributes({ markBG: value })}
											opacityValue={markBGOpacity}
											onOpacityChange={(value) => setAttributes({ markBGOpacity: value })}
											onArrayChange={(color, opacity) =>
												setAttributes({ markBG: color, markBGOpacity: opacity })
											}
										/>
									)}
								</>
								{!enableMarkGradient && (
									<ToggleControl
										style={{ marginTop: '10px' }}
										label={__('Enable Background Gradient', 'flx-blocks')}
										help={__(
											'Enabling Background Gradient disables Text Gradient.',
											'flx-blocks'
										)}
										checked={enableMarkBackgroundGradient}
										onChange={(value) => setAttributes({ enableMarkBackgroundGradient: value })}
									/>
								)}
								{enableMarkBackgroundGradient && (
									<GradientControl
										value={markBackgroundGradient}
										onChange={(value) => setAttributes({ markBackgroundGradient: value })}
										gradients={[]}
									/>
								)}
								<ResponsiveBorderControl
									label={__('Border', 'flx-blocks')}
									value={markBorderStyles}
									tabletValue={tabletMarkBorderStyles}
									mobileValue={mobileMarkBorderStyles}
									onChange={(value) => setAttributes({ markBorderStyles: value })}
									onChangeTablet={(value) => setAttributes({ tabletMarkBorderStyles: value })}
									onChangeMobile={(value) => setAttributes({ mobileMarkBorderStyles: value })}
								/>
								<ResponsiveMeasurementControls
									label={__('Border Radius', 'flx-blocks')}
									value={markBorderRadius}
									tabletValue={tabletMarkBorderRadius}
									mobileValue={mobileMarkBorderRadius}
									onChange={(value) => setAttributes({ markBorderRadius: value })}
									onChangeTablet={(value) => setAttributes({ tabletMarkBorderRadius: value })}
									onChangeMobile={(value) => setAttributes({ mobileMarkBorderRadius: value })}
									unit={markBorderRadiusUnit}
									units={['px', 'em', 'rem', '%']}
									onUnit={(value) => setAttributes({ markBorderRadiusUnit: value })}
									max={markBorderRadiusUnit === 'em' || markBorderRadiusUnit === 'rem' ? 24 : 500}
									step={markBorderRadiusUnit === 'em' || markBorderRadiusUnit === 'rem' ? 0.1 : 1}
									min={0}
									isBorderRadius={true}
									allowEmpty={true}
								/>
								<TypographyControls
									fontGroup={'heading'}
									fontSize={markSize}
									onFontSize={(value) => setAttributes({ markSize: value })}
									fontSizeType={markSizeType}
									onFontSizeType={(value) => setAttributes({ markSizeType: value })}
									lineHeight={markLineHeight}
									onLineHeight={(value) => setAttributes({ markLineHeight: value })}
									lineHeightType={markLineType}
									onLineHeightType={(value) => setAttributes({ markLineType: value })}
									reLetterSpacing={[
										markLetterSpacing,
										tabletMarkLetterSpacing,
										mobileMarkLetterSpacing,
									]}
									onLetterSpacing={(value) =>
										setAttributes({
											markLetterSpacing: value[0],
											tabletMarkLetterSpacing: value[1],
											mobileMarkLetterSpacing: value[2],
										})
									}
									letterSpacingType={markLetterSpacingType}
									onLetterSpacingType={(value) => setAttributes({ markLetterSpacingType: value })}
									fontFamily={markTypography}
									onFontFamily={(value) => setAttributes({ markTypography: value })}
									onFontChange={(select) => {
										setAttributes({
											markTypography: select.value,
											markGoogleFont: select.google,
										});
									}}
									googleFont={markGoogleFont}
									onGoogleFont={(value) => setAttributes({ markGoogleFont: value })}
									loadGoogleFont={markLoadGoogleFont}
									onLoadGoogleFont={(value) => setAttributes({ markLoadGoogleFont: value })}
									fontVariant={markFontVariant}
									onFontVariant={(value) => setAttributes({ markFontVariant: value })}
									fontWeight={markFontWeight}
									onFontWeight={(value) => setAttributes({ markFontWeight: value })}
									fontStyle={markFontStyle}
									onFontStyle={(value) => setAttributes({ markFontStyle: value })}
									fontSubset={markFontSubset}
									onFontSubset={(value) => setAttributes({ markFontSubset: value })}
									textTransform={markTextTransform}
									onTextTransform={(value) => setAttributes({ markTextTransform: value })}
								/>
								<ResponsiveMeasureRangeControl
									label={__('Padding', 'flx-blocks')}
									value={markPadding}
									tabletValue={markTabPadding}
									mobileValue={markMobilePadding}
									onChange={(value) => setAttributes({ markPadding: value })}
									onChangeTablet={(value) => setAttributes({ markTabPadding: value })}
									onChangeMobile={(value) => setAttributes({ markMobilePadding: value })}
									min={0}
									max={markPaddingType === 'em' || markPaddingType === 'rem' ? 12 : 999}
									step={markPaddingType === 'em' || markPaddingType === 'rem' ? 0.1 : 1}
									unit={markPaddingType}
									units={['px', 'em', 'rem', '%']}
									onUnit={(value) => setAttributes({ markPaddingType: value })}
								/>
							</FlxbkPanelBody>
						)}
					</>
				)}
			</InspectorControls>

			{markGoogleFont && markTypography && (
				<FlxbkWebfontLoader
					typography={[{ family: markTypography, variant: markFontVariant ? markFontVariant : '' }]}
					clientId={clientId}
					id={'advancedHeadingMark'}
				/>
			)}

			<div
				className={`fxt-svg-icon-list-item-wrap fxt-svg-icon-list-item-wrap-${uniqueID} fxt-svg-icon-list-item-0 fxt-svg-icon-list-level-${level}${
					style ? ' fxt-svg-icon-list-style-' + style : ''
				}`}
			>
				<style>
					{`.fxt-svg-icon-list-item-wrap-${uniqueID}.fxt-svg-icon-list-item-0.fxt-svg-icon-list-level-${level}${
						style ? '.fxt-svg-icon-list-style-' + style : ''
					} mark.fxt-highlight {
						color: ${!enableMarkGradient ? FlxbkColorOutput(markColor) : 'transparent'};
						background: ${markBG && !enableMarkGradient ? markBGString : 'transparent'};
						background-image: ${enableMarkGradient ? markGradient : enableMarkBackgroundGradient ? markBackgroundGradient : 'none'};
						-webkit-background-clip: ${enableMarkGradient ? 'text' : undefined};
						background-clip: ${enableMarkGradient ? 'text' : undefined};
						-webkit-text-fill-color: ${enableMarkGradient ? 'transparent' : undefined};
						-webkit-box-decoration-break: clone;
						box-decoration-break: clone;
						font-weight: ${markFontWeight ? markFontWeight : 'inherit'};
						font-style: ${markFontStyle ? markFontStyle : 'inherit'};
						font-size: ${previewMarkSize ? getFontSizeOptionOutput(previewMarkSize, markSizeType) : 'inherit'};
						line-height: ${previewMarkLineHeight ? previewMarkLineHeight + markLineType : 'inherit'};
						letter-spacing: ${
							previewMarkLetterSpacing
								? previewMarkLetterSpacing + (markLetterSpacingType ? markLetterSpacingType : 'px')
								: 'inherit'
						};
						text-transform: ${markTextTransform ? markTextTransform : 'inherit'};
						font-family: ${markTypography ? markTypography : 'inherit'};
						border-top: ${previewMarkBorderTopStyle ? previewMarkBorderTopStyle : 'inherit'};
						border-right: ${previewMarkBorderRightStyle ? previewMarkBorderRightStyle : 'inherit'};
						border-bottom: ${previewMarkBorderBottomStyle ? previewMarkBorderBottomStyle : 'inherit'};
						border-left: ${previewMarkBorderLeftStyle ? previewMarkBorderLeftStyle : 'inherit'};
						padding-top: ${previewMarkPaddingTop ? getSpacingOptionOutput(previewMarkPaddingTop, markPaddingType) : '0'};
						padding-right: ${previewMarkPaddingRight ? getSpacingOptionOutput(previewMarkPaddingRight, markPaddingType) : '0'};
						padding-bottom: ${previewMarkPaddingBottom ? getSpacingOptionOutput(previewMarkPaddingBottom, markPaddingType) : '0'};
						padding-left: ${previewMarkPaddingLeft ? getSpacingOptionOutput(previewMarkPaddingLeft, markPaddingType) : '0'};
						${
							'' !== previewMarkBorderRadiusTop
								? 'border-top-left-radius:' +
									previewMarkBorderRadiusTop +
									markBorderRadiusUnitPreview +
									';'
								: ''
						}
						${
							'' !== previewMarkBorderRadiusRight
								? 'border-top-right-radius:' +
									previewMarkBorderRadiusRight +
									markBorderRadiusUnitPreview +
									';'
								: ''
						}
						${
							'' !== previewMarkBorderRadiusBottom
								? 'border-bottom-right-radius:' +
									previewMarkBorderRadiusBottom +
									markBorderRadiusUnitPreview +
									';'
								: ''
						}
						${
							'' !== previewMarkBorderRadiusLeft
								? 'border-bottom-left-radius:' +
									previewMarkBorderRadiusLeft +
									markBorderRadiusUnitPreview +
									';'
								: ''
						}
					}`}
				</style>
				<Tooltip
					TagName={WrapTag}
					className={`fxb-icon-list-tooltip-wrap${
						!link && tooltip && !iconOnlyTooltip && !textOnlyTooltip ? ' fxb-icon-list-tooltip' : ''
					}${
						!link && tooltip && !iconOnlyTooltip && !textOnlyTooltip && !tooltipDash
							? ' fxb-list-tooltip-no-border'
							: ''
					}${link ? ' fxt-svg-icon-link' : ''}`}
					text={tooltip && !iconOnlyTooltip && !textOnlyTooltip ? tooltip : undefined}
					placement={tooltipPlacement || 'top'}
				>
					{listItemOutput}
				</Tooltip>
			</div>
		</div>
	);
}

export default FlxbkListItem;
