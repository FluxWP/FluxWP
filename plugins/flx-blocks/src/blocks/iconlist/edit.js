/**
 * BLOCK: Flux Icon
 */

/**
 * Import Icons
 */
import { alignTopIcon, alignMiddleIcon, alignBottomIcon } from '@flx-blocks/icons';

/**
 * Import Externals
 */
import { map, get, isEqual } from 'lodash';
/**
 * Import Flux Components
 */
import {
	FlxbkColorOutput,
	showSettings,
	getPreviewSize,
	mouseOverVisualizer,
	getSpacingOptionOutput,
	getFontSizeOptionOutput,
	uniqueIdHelper,
} from '@flx-blocks/helpers';

import {
	PopColorControl,
	TypographyControls,
	FlxbkIconPicker,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	FlxbkWebfontLoader,
	InspectorControlTabs,
	FlxbkBlockDefaults,
	ResponsiveMeasureRangeControl,
	SpacingVisualizer,
	CopyPasteAttributes,
	RangeControl,
} from '@flx-blocks/components';

/**
 * Import Css
 */
import './editor.scss';
import metadata from './block.json';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { migrateToInnerblocks } from './utils';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
	RichText,
	BlockControls,
	BlockAlignmentToolbar,
	store as blockEditorStore,
} from '@wordpress/block-editor';

import { useEffect, useState, Fragment, Platform, forwardRef } from '@wordpress/element';

import {
	ButtonGroup,
	Tooltip,
	Button,
	ToolbarGroup,
	ToolbarButton,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';

import { withDispatch, useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { plusCircle } from '@wordpress/icons';

function FlxbkIconLists(props) {
	const {
		attributes,
		className,
		setAttributes,
		isSelected,
		insertListItem,
		insertListItems,
		listBlock,
		container,
		clientId,
		updateBlockAttributes,
		onDelete,
	} = props;
	const {
		listCount,
		items,
		listStyles,
		columns,
		listLabelGap,
		listGap,
		tabletListGap,
		mobileListGap,
		columnGap,
		tabletColumnGap,
		mobileColumnGap,
		blockAlignment,
		uniqueID,
		listMargin,
		tabletListMargin,
		mobileListMargin,
		listMarginType,
		listPadding,
		tabletListPadding,
		mobileListPadding,
		listPaddingType,
		iconAlign,
		tabletColumns,
		mobileColumns,
		icon,
		iconSize,
		width,
		color,
		background,
		border,
		borderRadius,
		padding,
		borderWidth,
		style,
		linkUnderline,
		linkColor,
		linkHoverColor,
		iconTitle,
	} = attributes;

	const [activeTab, setActiveTab] = useState('general');
	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	useEffect(() => {
		if (!uniqueID) {
			// Check for old block defaults before 3.0
			const blockConfigObject = flxbk_params.configuration
				? JSON.parse(flxbk_params.configuration)
				: [];
			if (undefined === attributes.noCustomDefaults || !attributes.noCustomDefaults) {
				if (
					blockConfigObject['flx/iconlist'] !== undefined &&
					typeof blockConfigObject['flx/iconlist'] === 'object'
				) {
					Object.keys(blockConfigObject['flx/iconlist']).map((attribute) => {
						if (attribute === 'items') {
							attributes[attribute] = attributes[attribute].map((item, index) => {
								item.icon = blockConfigObject['flx/iconlist'][attribute][0].icon;
								item.size = blockConfigObject['flx/iconlist'][attribute][0].size;
								item.color = blockConfigObject['flx/iconlist'][attribute][0].color;
								item.background = blockConfigObject['flx/iconlist'][attribute][0].background;
								item.border = blockConfigObject['flx/iconlist'][attribute][0].border;
								item.borderRadius = blockConfigObject['flx/iconlist'][attribute][0].borderRadius;
								item.padding = blockConfigObject['flx/iconlist'][attribute][0].padding;
								item.borderWidth = blockConfigObject['flx/iconlist'][attribute][0].borderWidth;
								item.style = blockConfigObject['flx/iconlist'][attribute][0].style;
								item.level = get(blockConfigObject['flx/iconlist'][attribute][0], 'level', 0);
								return item;
							});
						}
					});
				}
			}
		}
	}, []);

	uniqueIdHelper(props);

	useEffect(() => {
		if (uniqueID && !listBlock.innerBlocks.length) {
			// Check if we are attempting recovery.
			if (
				items?.length &&
				items.length &&
				undefined !== metadata?.attributes?.items?.default &&
				!isEqual(metadata.attributes.items.default, items)
			) {
				const migrateUpdate = migrateToInnerblocks(attributes);
				setAttributes(migrateUpdate[0]);
				insertListItems(migrateUpdate[1]);
			} else {
				// Delete if no inner blocks.
				onDelete();
			}
		}
	}, [listBlock.innerBlocks.length]);

	function selfOrChildSelected(isSelected, clientId) {
		const childSelected = useSelect((select) => select('core/block-editor').hasSelectedInnerBlock(clientId, true));
		return isSelected || childSelected;
	}

	const previewListMarginTop = getPreviewSize(
		previewDevice,
		undefined !== listMargin ? listMargin[0] : '',
		undefined !== tabletListMargin ? tabletListMargin[0] : '',
		undefined !== mobileListMargin ? mobileListMargin[0] : ''
	);
	const previewListMarginRight = getPreviewSize(
		previewDevice,
		undefined !== listMargin ? listMargin[1] : '',
		undefined !== tabletListMargin ? tabletListMargin[1] : '',
		undefined !== mobileListMargin ? mobileListMargin[1] : ''
	);
	const previewListMarginBottom = getPreviewSize(
		previewDevice,
		undefined !== listMargin ? listMargin[2] : '',
		undefined !== tabletListMargin ? tabletListMargin[2] : '',
		undefined !== mobileListMargin ? mobileListMargin[2] : ''
	);
	const previewListMarginLeft = getPreviewSize(
		previewDevice,
		undefined !== listMargin ? listMargin[3] : '',
		undefined !== tabletListMargin ? tabletListMargin[3] : '',
		undefined !== mobileListMargin ? mobileListMargin[3] : ''
	);

	const previewListPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== listPadding ? listPadding[0] : '',
		undefined !== tabletListPadding ? tabletListPadding[0] : '',
		undefined !== mobileListPadding ? mobileListPadding[0] : ''
	);
	const previewListPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== listPadding ? listPadding[1] : '',
		undefined !== tabletListPadding ? tabletListPadding[1] : '',
		undefined !== mobileListPadding ? mobileListPadding[1] : ''
	);
	const previewListPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== listPadding ? listPadding[2] : '',
		undefined !== tabletListPadding ? tabletListPadding[2] : '',
		undefined !== mobileListPadding ? mobileListPadding[2] : ''
	);
	const previewListPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== listPadding ? listPadding[3] : '',
		undefined !== tabletListPadding ? tabletListPadding[3] : '',
		undefined !== mobileListPadding ? mobileListPadding[3] : ''
	);

	const previewColumnGap = getPreviewSize(
		previewDevice,
		undefined !== columnGap ? columnGap : '',
		undefined !== tabletColumnGap ? tabletColumnGap : '',
		undefined !== mobileColumnGap ? mobileColumnGap : ''
	);
	const previewListGap = getPreviewSize(
		previewDevice,
		undefined !== listGap ? listGap : '',
		undefined !== tabletListGap ? tabletListGap : '',
		undefined !== mobileListGap ? mobileListGap : ''
	);
	const previewColumns = getPreviewSize(
		previewDevice,
		undefined !== columns ? columns : '',
		undefined !== tabletColumns ? tabletColumns : '',
		undefined !== mobileColumns ? mobileColumns : ''
	);
	const listMarginMouseOver = mouseOverVisualizer();
	const listPaddingMouseOver = mouseOverVisualizer();

	const previewFontSize = getPreviewSize(
		previewDevice,
		undefined !== listStyles[0].size && undefined !== listStyles[0].size[0] ? listStyles[0].size[0] : '',
		undefined !== listStyles[0].size && undefined !== listStyles[0].size[1] ? listStyles[0].size[1] : '',
		undefined !== listStyles[0].size && undefined !== listStyles[0].size[2] ? listStyles[0].size[2] : ''
	);
	const previewLineHeight = getPreviewSize(
		previewDevice,
		undefined !== listStyles[0].lineHeight && undefined !== listStyles[0].lineHeight[0]
			? listStyles[0].lineHeight[0]
			: '',
		undefined !== listStyles[0].lineHeight && undefined !== listStyles[0].lineHeight[1]
			? listStyles[0].lineHeight[1]
			: '',
		undefined !== listStyles[0].lineHeight && undefined !== listStyles[0].lineHeight[2]
			? listStyles[0].lineHeight[2]
			: ''
	);

	const previewIconSize = getPreviewSize(
		previewDevice,
		undefined !== iconSize?.[0] ? iconSize[0] : '',
		undefined !== iconSize?.[1] ? iconSize[1] : '',
		undefined !== iconSize?.[2] ? iconSize[2] : ''
	);

	const saveListStyles = (value) => {
		const newUpdate = listStyles.map((item, index) => {
			if (index === 0) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			listStyles: newUpdate,
		});
	};

	const iconAlignOptions = [
		{ key: 'top', name: __('Top', 'flx-blocks'), icon: alignTopIcon },
		{ key: 'middle', name: __('Middle', 'flx-blocks'), icon: alignMiddleIcon },
		{ key: 'bottom', name: __('Bottom', 'flx-blocks'), icon: alignBottomIcon },
	];

	const nonTransAttrs = ['listCount'];

	const blockProps = useBlockProps({
		className,
		// 'data-align': ( 'center' === blockAlignment || 'left' === blockAlignment || 'right' === blockAlignment ? blockAlignment : undefined )
	});
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		allowedBlocks: ['flx/listitem'],
		template: [['flx/listitem']],
		templateLock: false,
		templateInsertUpdatesSelection: true,
	});

	return (
		<div {...blockProps}>
			<BlockControls>
				<BlockAlignmentToolbar
					value={blockAlignment}
					controls={['center', 'left', 'right']}
					onChange={(value) => setAttributes({ blockAlignment: value })}
				/>
				<ToolbarGroup>
					<ToolbarButton
						className="fxb-icons-add-icon"
						icon={plusCircle}
						onClick={() => {
							const prevAttributes = listBlock.innerBlocks[listBlock.innerBlocks.length - 1].attributes;
							const latestAttributes = JSON.parse(JSON.stringify(prevAttributes));
							latestAttributes.uniqueID = '';
							latestAttributes.text = '';
							const newBlock = createBlock('flx/listitem', latestAttributes);
							insertListItem(newBlock);
						}}
						label={__('Add Another List Item', 'flx-blocks')}
						showTooltip={true}
					/>
				</ToolbarGroup>
				<CopyPasteAttributes
					attributes={attributes}
					excludedAttrs={nonTransAttrs}
					defaultAttributes={metadata.attributes}
					blockSlug={metadata.name}
					onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
				/>
			</BlockControls>
			{showSettings('allSettings', 'flx/iconlist') && (
				<InspectorControls>
					<InspectorControlTabs
						panelName={'iconlist'}
						setActiveTab={(value) => setActiveTab(value)}
						activeTab={activeTab}
						initialOpen={'style'}
					/>

					{activeTab === 'general' && (
						<>
							<FlxbkPanelBody initialOpen={true} panelName={'fxb-icon-list-controls'}>
								{showSettings('column', 'flx/iconlist') && (
									<ResponsiveRangeControls
										label={__('List Columns', 'flx-blocks')}
										value={columns}
										onChange={(value) => setAttributes({ columns: value })}
										tabletValue={tabletColumns ? tabletColumns : ''}
										onChangeTablet={(value) => setAttributes({ tabletColumns: value })}
										mobileValue={mobileColumns ? mobileColumns : ''}
										onChangeMobile={(value) => setAttributes({ mobileColumns: value })}
										min={1}
										max={3}
										step={1}
										showUnit={false}
									/>
								)}
								{showSettings('spacing', 'flx/iconlist') && (
									<Fragment>
										<ResponsiveRangeControls
											label={__('List Column Gap', 'flx-blocks')}
											value={columnGap}
											onChange={(value) => setAttributes({ columnGap: value })}
											tabletValue={tabletColumnGap ? tabletColumnGap : ''}
											onChangeTablet={(value) => setAttributes({ tabletColumnGap: value })}
											mobileValue={mobileColumnGap ? mobileColumnGap : ''}
											onChangeMobile={(value) => setAttributes({ mobileColumnGap: value })}
											min={0}
											max={200}
											step={1}
											showUnit={false}
										/>
										<ResponsiveRangeControls
											label={__('List Vertical Spacing', 'flx-blocks')}
											value={listGap}
											onChange={(value) => setAttributes({ listGap: value })}
											tabletValue={undefined !== tabletListGap ? tabletListGap : ''}
											onChangeTablet={(value) => setAttributes({ tabletListGap: value })}
											mobileValue={undefined !== mobileListGap ? mobileListGap : ''}
											onChangeMobile={(value) => setAttributes({ mobileListGap: value })}
											min={0}
											max={60}
											step={1}
											showUnit={false}
										/>
										<RangeControl
											label={__('List Horizontal Icon and Label Spacing', 'flx-blocks')}
											value={listLabelGap}
											onChange={(value) => {
												setAttributes({ listLabelGap: value });
											}}
											min={0}
											max={60}
											reset={true}
										/>
										<div className="fxt-btn-size-settings-container">
											<h2 className="fxt-beside-btn-group">
												{__('Icon Align', 'flx-blocks')}
											</h2>
											<ButtonGroup
												className="fxt-button-size-type-options"
												aria-label={__('Icon Align', 'flx-blocks')}
											>
												{map(iconAlignOptions, ({ name, icon, key }) => (
													<Tooltip text={name}>
														<Button
															key={key}
															className="fxt-btn-size-btn"
															isSmall
															isPrimary={iconAlign === key}
															aria-pressed={iconAlign === key}
															onClick={() => setAttributes({ iconAlign: key })}
														>
															{icon}
														</Button>
													</Tooltip>
												))}
											</ButtonGroup>
										</div>
									</Fragment>
								)}
							</FlxbkPanelBody>
						</>
					)}

					{activeTab === 'style' && (
						<>
							<FlxbkPanelBody
								title={__('Icon Styling', 'flx-blocks')}
								panelName={'fxb-list-icon-styling'}
							>
								<FlxbkIconPicker
									value={icon}
									onChange={(value) => {
										setAttributes({ icon: value });
									}}
								/>
								<ResponsiveRangeControls
									label={__('Icon Size', 'flx-blocks')}
									value={undefined !== iconSize?.[0] ? iconSize[0] : ''}
									onChange={(value) => {
										setAttributes({
											iconSize: [
												value,
												undefined !== iconSize?.[1] ? iconSize[1] : '',
												undefined !== iconSize?.[2] ? iconSize[2] : '',
											],
										});
									}}
									tabletValue={undefined !== iconSize?.[1] ? iconSize[1] : ''}
									onChangeTablet={(value) => {
										setAttributes({
											iconSize: [
												undefined !== iconSize?.[0] ? iconSize[0] : '',
												value,
												undefined !== iconSize?.[2] ? iconSize[2] : '',
											],
										});
									}}
									mobileValue={undefined !== iconSize?.[2] ? iconSize[2] : ''}
									onChangeMobile={(value) => {
										setAttributes({
											iconSize: [
												undefined !== iconSize?.[0] ? iconSize[0] : '',
												undefined !== iconSize?.[1] ? iconSize[1] : '',
												value,
											],
										});
									}}
									min={0}
									max={300}
									step={1}
									unit={'px'}
									showUnit={true}
									units={['px']}
								/>
								{icon && 'fe' === icon.substring(0, 2) && (
									<RangeControl
										label={__('Line Width', 'flx-blocks')}
										value={width}
										onChange={(value) => {
											setAttributes({ width: value });
										}}
										step={0.5}
										min={0.5}
										max={4}
										reset={true}
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
										{ value: 'default', label: __('Default', 'flx-blocks') },
										{ value: 'stacked', label: __('Stacked', 'flx-blocks') },
									]}
									onChange={(value) => {
										setAttributes({ style: value });
									}}
								/>
								{style === 'stacked' && (
									<>
										<PopColorControl
											label={__('Icon Background', 'flx-blocks')}
											value={background ? background : ''}
											default={''}
											onChange={(value) => {
												setAttributes({ background: value });
											}}
										/>
										<PopColorControl
											label={__('Border Color', 'flx-blocks')}
											value={border ? border : ''}
											default={''}
											onChange={(value) => {
												setAttributes({ border: value });
											}}
										/>
										<RangeControl
											label={__('Border Size (px)', 'flx-blocks')}
											value={borderWidth}
											onChange={(value) => {
												setAttributes({ borderWidth: value });
											}}
											min={0}
											max={20}
											reset={true}
										/>
									</>
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
										reset={true}
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
										reset={true}
									/>
								)}
							</FlxbkPanelBody>
							{showSettings('textStyle', 'flx/iconlist') && (
								<FlxbkPanelBody
									title={__('Text Styling', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-list-text-styling'}
								>
									<PopColorControl
										label={__('Color', 'flx-blocks')}
										value={listStyles[0].color ? listStyles[0].color : ''}
										default={''}
										onChange={(value) => {
											saveListStyles({ color: value });
										}}
									/>
									<TypographyControls
										fontGroup={'body'}
										fontSize={listStyles[0].size}
										onFontSize={(value) => saveListStyles({ size: value })}
										fontSizeType={listStyles[0].sizeType}
										onFontSizeType={(value) => saveListStyles({ sizeType: value })}
										lineHeight={listStyles[0].lineHeight}
										onLineHeight={(value) => saveListStyles({ lineHeight: value })}
										lineHeightType={listStyles[0].lineType}
										onLineHeightType={(value) => saveListStyles({ lineType: value })}
										letterSpacing={listStyles[0].letterSpacing}
										onLetterSpacing={(value) => saveListStyles({ letterSpacing: value })}
										fontFamily={listStyles[0].family}
										onFontFamily={(value) => saveListStyles({ family: value })}
										onFontChange={(select) => {
											saveListStyles({
												family: select.value,
												google: select.google,
											});
										}}
										onFontArrayChange={(values) => saveListStyles(values)}
										googleFont={listStyles[0].google}
										onGoogleFont={(value) => saveListStyles({ google: value })}
										loadGoogleFont={listStyles[0].loadGoogle}
										onLoadGoogleFont={(value) => saveListStyles({ loadGoogle: value })}
										fontVariant={listStyles[0].variant}
										onFontVariant={(value) => saveListStyles({ variant: value })}
										fontWeight={listStyles[0].weight}
										onFontWeight={(value) => saveListStyles({ weight: value })}
										fontStyle={listStyles[0].style}
										onFontStyle={(value) => saveListStyles({ style: value })}
										fontSubset={listStyles[0].subset}
										onFontSubset={(value) => saveListStyles({ subset: value })}
										textTransform={listStyles[0].textTransform}
										onTextTransform={(value) => saveListStyles({ textTransform: value })}
									/>
								</FlxbkPanelBody>
							)}

							<FlxbkPanelBody
								title={__('Link Styling', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-list-link-styling'}
							>
								<PopColorControl
									label={__('Link Color', 'flx-blocks')}
									value={linkColor}
									default={''}
									onChange={(value) => {
										setAttributes({ linkColor: value });
									}}
									swatchLabel2={__('Hover Color', 'flx-blocks')}
									value2={linkHoverColor ? linkHoverColor : ''}
									default2={''}
									onChange2={(value) => setAttributes({ linkHoverColor: value })}
								/>
								<SelectControl
									label={__('Underline Links', 'flx-blocks')}
									value={linkUnderline}
									options={[
										{ value: 'inherit', label: __('Inherit', 'flx-blocks') },
										{ value: 'always', label: __('Underline', 'flx-blocks') },
										{ value: 'hover', label: __('Underline on Hover', 'flx-blocks') },
										{ value: 'none', label: __('None', 'flx-blocks') },
									]}
									onChange={(value) => setAttributes({ linkUnderline: value })}
								/>
							</FlxbkPanelBody>
						</>
					)}

					{activeTab === 'advanced' && (
						<>
							<FlxbkPanelBody panelName={'fxb-icon-list-spacing-settings'}>
								<ResponsiveMeasureRangeControl
									label={__('Padding', 'flx-blocks')}
									value={listPadding}
									tabletValue={tabletListPadding}
									mobileValue={mobileListPadding}
									onChange={(value) => setAttributes({ listPadding: value })}
									onChangeTablet={(value) => setAttributes({ tabletListPadding: value })}
									onChangeMobile={(value) => setAttributes({ mobileListPadding: value })}
									min={listPaddingType === 'em' || listPaddingType === 'rem' ? -25 : -999}
									max={listPaddingType === 'em' || listPaddingType === 'rem' ? 25 : 999}
									step={listPaddingType === 'em' || listPaddingType === 'rem' ? 0.1 : 1}
									unit={listPaddingType}
									units={['px', 'em', 'rem', '%']}
									onUnit={(value) => setAttributes({ listPaddingType: value })}
									onMouseOver={listPaddingMouseOver.onMouseOver}
									onMouseOut={listPaddingMouseOver.onMouseOut}
								/>
								<ResponsiveMeasureRangeControl
									label={__('Margin', 'flx-blocks')}
									value={listMargin}
									tabletValue={tabletListMargin}
									mobileValue={mobileListMargin}
									onChange={(value) => setAttributes({ listMargin: value })}
									onChangeTablet={(value) => setAttributes({ tabletListMargin: value })}
									onChangeMobile={(value) => setAttributes({ mobileListMargin: value })}
									min={listMarginType === 'em' || listMarginType === 'rem' ? -25 : -999}
									max={listMarginType === 'em' || listMarginType === 'rem' ? 25 : 999}
									step={listMarginType === 'em' || listMarginType === 'rem' ? 0.1 : 1}
									unit={listMarginType}
									units={['px', 'em', 'rem', '%']}
									onUnit={(value) => setAttributes({ listMarginType: value })}
									onMouseOver={listMarginMouseOver.onMouseOver}
									onMouseOut={listMarginMouseOver.onMouseOut}
									allowAuto={true}
								/>
							</FlxbkPanelBody>

							<FlxbkBlockDefaults
								attributes={attributes}
								defaultAttributes={metadata.attributes}
								blockSlug={metadata.name}
								excludedAttrs={nonTransAttrs}
								preventMultiple={['items']}
							/>
						</>
					)}
				</InspectorControls>
			)}
			<style>
				{listLabelGap
					? `body:not(.rtl) .fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-single { margin-right: ${listLabelGap}px; }`
					: ''}
				{listLabelGap
					? `body.rtl .fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-single { margin-left: ${listLabelGap}px; }`
					: ''}
				{listStyles?.[0]
					? `.fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-item-wrap {
						${listStyles[0].weight ? `font-weight:${listStyles[0].weight};` : ''}
						${listStyles[0].style ? `font-style:${listStyles[0].style};` : ''}
						${listStyles[0].color ? `color:${FlxbkColorOutput(listStyles[0].color)};` : ''}
						${previewFontSize ? `font-size:${getFontSizeOptionOutput(previewFontSize, listStyles[0].sizeType)};` : ''}
						${previewLineHeight ? `line-height:${previewLineHeight + listStyles[0].lineType};` : ''}
						${listStyles[0].letterSpacing ? `letter-spacing:${listStyles[0].letterSpacing}px;` : ''}
						${listStyles[0].family ? `font-family:${listStyles[0].family};` : ''}
						${listStyles[0].textTransform ? `text-transform:${listStyles[0].textTransform};` : ''}
					}`
					: ''}
				{listStyles[0].color
					? `.fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-item-wrap a {
						color:${FlxbkColorOutput(listStyles[0].color)};
					}`
					: ''}
				{`.fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-item-wrap a {
						${linkColor ? `color:${FlxbkColorOutput(linkColor)};` : ''}
						${linkUnderline && linkUnderline === 'always' ? 'text-decoration: underline;' : ''}
						${linkUnderline && (linkUnderline === 'none' || linkUnderline === 'hover') ? 'text-decoration: none;' : ''}
					}`}
				{`.fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-item-wrap a:hover {
						${linkHoverColor ? `color:${FlxbkColorOutput(linkHoverColor)};` : ''}
						${linkUnderline && linkUnderline === 'hover' ? 'text-decoration: underline;' : ''}
					}`}
				{'' !== previewListGap
					? `.fxt-svg-icon-list-items${uniqueID} .wp-block-flx-iconlist { row-gap: ${previewListGap}px; }`
					: ''}
				{'' !== previewColumnGap
					? `.fxt-svg-icon-list-items${uniqueID} .wp-block-flx-iconlist { column-gap: ${previewColumnGap}px; }`
					: ''}
				{previewIconSize
					? `.fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-item-wrap .fxt-svg-icon-list-single { font-size: ${previewIconSize}px; }`
					: ''}
				{color
					? `.fxt-svg-icon-list-items${uniqueID} .fxt-svg-icon-list-item-wrap .fxt-svg-icon-list-single { color: ${FlxbkColorOutput(
							color
						)}; }`
					: ''}
				{background
					? `.fxt-svg-icon-list-items${uniqueID}.fxb-icon-list-style-stacked .fxt-svg-icon-list-item-wrap:not(.fxt-svg-icon-list-style-default) .fxt-svg-icon-list-single { background-color: ${FlxbkColorOutput(
							background
						)}; }`
					: ''}
				{padding
					? `.fxt-svg-icon-list-items${uniqueID}.fxb-icon-list-style-stacked .fxt-svg-icon-list-item-wrap:not(.fxt-svg-icon-list-style-default) .fxt-svg-icon-list-single { padding: ${padding}px; }`
					: ''}
				{border
					? `.fxt-svg-icon-list-items${uniqueID}.fxb-icon-list-style-stacked .fxt-svg-icon-list-item-wrap:not(.fxt-svg-icon-list-style-default) .fxt-svg-icon-list-single { border-color: ${FlxbkColorOutput(
							border
						)}; }`
					: ''}
				{borderWidth
					? `.fxt-svg-icon-list-items${uniqueID}.fxb-icon-list-style-stacked .fxt-svg-icon-list-item-wrap:not(.fxt-svg-icon-list-style-default) .fxt-svg-icon-list-single { border-width: ${borderWidth}px; }`
					: ''}
				{borderRadius
					? `.fxt-svg-icon-list-items${uniqueID}.fxb-icon-list-style-stacked .fxt-svg-icon-list-item-wrap:not(.fxt-svg-icon-list-style-default) .fxt-svg-icon-list-single { border-radius: ${borderRadius}%; }`
					: ''}
			</style>
			{listStyles?.[0]?.google && (
				<FlxbkWebfontLoader typography={listStyles} clientId={clientId} id={'listStyles'} />
			)}
			<div
				ref={container}
				className={`fxt-svg-icon-list-container fxt-svg-icon-list-items${uniqueID} fxb-icon-list-style-${style} fxt-svg-icon-list-columns-${previewColumns}${
					undefined !== iconAlign && 'middle' !== iconAlign ? ' fxt-list-icon-align' + iconAlign : ''
				}${
					undefined !== tabletColumns && '' !== tabletColumns
						? ' fxt-tablet-svg-icon-list-columns-' + tabletColumns
						: ''
				}${
					undefined !== mobileColumns && '' !== mobileColumns
						? ' fxt-mobile-svg-icon-list-columns-' + mobileColumns
						: ''
				}`}
				style={{
					marginTop: getSpacingOptionOutput(previewListMarginTop, listMarginType),
					marginRight: getSpacingOptionOutput(previewListMarginRight, listMarginType),
					marginBottom: getSpacingOptionOutput(previewListMarginBottom, listMarginType),
					marginLeft: getSpacingOptionOutput(previewListMarginLeft, listMarginType),
					paddingTop: getSpacingOptionOutput(previewListPaddingTop, listPaddingType),
					paddingRight: getSpacingOptionOutput(previewListPaddingRight, listPaddingType),
					paddingBottom: getSpacingOptionOutput(previewListPaddingBottom, listPaddingType),
					paddingLeft: getSpacingOptionOutput(previewListPaddingLeft, listPaddingType),
				}}
			>
				<div {...innerBlocksProps} />
				<SpacingVisualizer
					type="inside"
					style={{
						marginLeft:
							undefined !== previewListMarginLeft
								? getSpacingOptionOutput(previewListMarginLeft, listMarginType)
								: undefined,
						marginRight:
							undefined !== previewListMarginRight
								? getSpacingOptionOutput(previewListMarginRight, listMarginType)
								: undefined,
						marginTop:
							undefined !== previewListMarginTop
								? getSpacingOptionOutput(previewListMarginTop, listMarginType)
								: undefined,
						marginBottom:
							undefined !== previewListMarginBottom
								? getSpacingOptionOutput(previewListMarginBottom, listMarginType)
								: undefined,
					}}
					forceShow={listPaddingMouseOver.isMouseOver}
					spacing={[
						getSpacingOptionOutput(previewListPaddingTop, listPaddingType),
						getSpacingOptionOutput(previewListPaddingRight, listPaddingType),
						getSpacingOptionOutput(previewListPaddingBottom, listPaddingType),
						getSpacingOptionOutput(previewListPaddingLeft, listPaddingType),
					]}
				/>
				<SpacingVisualizer
					type="outsideVertical"
					forceShow={listMarginMouseOver.isMouseOver}
					spacing={[
						getSpacingOptionOutput(previewListMarginTop, listMarginType),
						getSpacingOptionOutput(previewListMarginRight, listMarginType),
						getSpacingOptionOutput(previewListMarginBottom, listMarginType),
						getSpacingOptionOutput(previewListMarginLeft, listMarginType),
					]}
				/>
			</div>
		</div>
	);
}

const FlxbkIconsListWrapper = withDispatch((dispatch, ownProps, registry) => ({
	insertListItem(newBlock) {
		const { clientId } = ownProps;
		const { insertBlock } = dispatch(blockEditorStore);
		const { getBlock } = registry.select(blockEditorStore);
		const block = getBlock(clientId);
		insertBlock(newBlock, parseInt(block.innerBlocks.length), clientId);
	},
	insertListItems(newBlocks) {
		const { clientId } = ownProps;
		const { replaceInnerBlocks } = dispatch(blockEditorStore);

		replaceInnerBlocks(clientId, newBlocks);
	},
	onDelete() {
		const { clientId } = ownProps;
		const { removeBlock } = dispatch(blockEditorStore);
		removeBlock(clientId, true);
	},
	updateBlockAttributes(...args) {
		const { updateBlockAttributes } = registry.select(blockEditorStore);
		updateBlockAttributes(...args);
	},
}))(FlxbkIconLists);
const FlxbkListEdit = (props) => {
	const { clientId } = props;
	const { listBlock } = useSelect(
		(select) => {
			const { getBlock } = select('core/block-editor');
			const block = getBlock(clientId);
			return {
				listBlock: block,
			};
		},
		[clientId]
	);
	return <FlxbkIconsListWrapper listBlock={listBlock} {...props} />;
};
export default FlxbkListEdit;
