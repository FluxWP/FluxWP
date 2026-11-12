/**
 * BLOCK: Flux Spacer
 *
 * Registering a basic block with Gutenberg.
 */

import SvgPattern from './svg-pattern';
import classnames from 'classnames';
import { FlxbkColorOutput, showSettings, getPreviewSize, uniqueIdHelper } from '@flx-blocks/helpers';
import {
	PopColorControl,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	InspectorControlTabs,
	ResponsiveAlignControls,
	FlxbkBlockDefaults,
	CopyPasteAttributes,
} from '@flx-blocks/components';

/**
 * Import Css
 */
import './editor.scss';
import metadata from './block.json';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

import {
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
	BlockAlignmentToolbar,
	useBlockProps,
} from '@wordpress/block-editor';
import { ToggleControl, RangeControl, SelectControl, ResizableBox } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

import { useEffect, useState } from '@wordpress/element';

/**
 * Build the spacer edit
 */
function FlxbkSpacerDivider(props) {
	const { attributes, clientId, setAttributes, toggleSelection } = props;
	const {
		className,
		blockAlignment,
		spacerHeight,
		tabletSpacerHeight,
		mobileSpacerHeight,
		dividerEnable,
		dividerStyle,
		dividerColor,
		dividerOpacity,
		dividerHeight,
		dividerWidth,
		hAlign,
		uniqueID,
		spacerHeightUnits,
		rotate,
		strokeWidth,
		strokeGap,
		mobileHAlign,
		tabletHAlign,
		dividerWidthUnits,
		tabletDividerWidth,
		mobileDividerWidth,
		tabletDividerHeight,
		mobileDividerHeight,
		vsdesk,
		vstablet,
		vsmobile,
	} = attributes;
	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	uniqueIdHelper(props);

	const [activeTab, setActiveTab] = useState('general');
	const isSiteStylesPanel = useIsSiteStylesPanel();

	const blockProps = useBlockProps({
		className: classnames(className, {
			'wp-block': true,
		}),
		draggable: false,
		'data-align':
			'full' === blockAlignment || 'wide' === blockAlignment || 'center' === blockAlignment
				? blockAlignment
				: undefined,
	});

	let alp;
	if (dividerOpacity < 10) {
		alp = '0.0' + dividerOpacity;
	} else if (dividerOpacity >= 100) {
		alp = '1';
	} else {
		alp = '0.' + dividerOpacity;
	}

	const editorDocument = document.querySelector('iframe[name="editor-canvas"]')?.contentWindow.document || document;
	const dividerBorderColor = !dividerColor
		? FlxbkColorOutput('#eeeeee', alp)
		: FlxbkColorOutput(dividerColor, alp);

	const previewHeight = getPreviewSize(
		previewDevice,
		'' !== spacerHeight ? spacerHeight : 60,
		'' !== tabletSpacerHeight ? tabletSpacerHeight : '',
		'' !== mobileSpacerHeight ? mobileSpacerHeight : ''
	);
	const previewHAlign = getPreviewSize(
		previewDevice,
		'' !== hAlign ? hAlign : '',
		'' !== tabletHAlign ? tabletHAlign : '',
		'' !== mobileHAlign ? mobileHAlign : ''
	);
	const minD = dividerStyle !== 'stripe' ? 1 : 10;
	const maxD = dividerStyle !== 'stripe' ? 400 : 60;
	const previewDividerHeight = getPreviewSize(
		previewDevice,
		'' !== dividerHeight ? dividerHeight : 1,
		'' !== tabletDividerHeight ? tabletDividerHeight : '',
		'' !== mobileDividerHeight ? mobileDividerHeight : ''
	);
	const previewDividerWidth = getPreviewSize(
		previewDevice,
		'' !== dividerWidth ? dividerWidth : 1,
		'' !== tabletDividerWidth ? tabletDividerWidth : '',
		'' !== mobileDividerWidth ? mobileDividerWidth : ''
	);
	return (
		<div {...blockProps}>
			{showSettings('spacerDivider', 'flx/spacer') && (
				<>
					<BlockControls key="controls">
						<BlockAlignmentToolbar
							value={blockAlignment}
							controls={['center', 'wide', 'full']}
							onChange={(value) => setAttributes({ blockAlignment: value })}
						/>
						<AlignmentToolbar value={hAlign} onChange={(value) => setAttributes({ hAlign: value })} />
						<CopyPasteAttributes
							attributes={attributes}
							defaultAttributes={metadata.attributes}
							blockSlug={metadata.name}
							onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
						/>
					</BlockControls>
					<InspectorControls>
						<InspectorControlTabs
							panelName={'spacer'}
							allowedTabs={isSiteStylesPanel ? ['general'] : ['general', 'advanced']}
							setActiveTab={(value) => setActiveTab(value)}
							activeTab={activeTab}
						/>

						{activeTab === 'general' && (
							<>
								<FlxbkPanelBody
									title={__('Spacer Settings', 'flx-blocks')}
									initialOpen={true}
									panelName={'fxb-spacer-settings'}
								>
									{showSettings('spacerHeight', 'flx/spacer') && (
										<ResponsiveRangeControls
											label={__('Height', 'flx-blocks')}
											value={spacerHeight}
											onChange={(value) => setAttributes({ spacerHeight: value })}
											tabletValue={tabletSpacerHeight ? tabletSpacerHeight : ''}
											onChangeTablet={(value) => setAttributes({ tabletSpacerHeight: value })}
											mobileValue={mobileSpacerHeight ? mobileSpacerHeight : ''}
											onChangeMobile={(value) => setAttributes({ mobileSpacerHeight: value })}
											min={6}
											max={600}
											step={1}
											unit={spacerHeightUnits}
											onUnit={
												showSettings('spacerHeightUnits', 'flx/spacer')
													? (value) => setAttributes({ spacerHeightUnits: value })
													: false
											}
											units={['px', 'vh']}
										/>
									)}
								</FlxbkPanelBody>
								<FlxbkPanelBody
									title={__('Divider Settings', 'flx-blocks')}
									initialOpen={true}
									panelName={'fxb-divider-settings'}
								>
									{!isSiteStylesPanel && showSettings('dividerToggle', 'flx/spacer') && (
										<ToggleControl
											label={__('Enable Divider', 'flx-blocks')}
											checked={dividerEnable}
											onChange={(value) => setAttributes({ dividerEnable: value })}
										/>
									)}
									{dividerEnable && showSettings('dividerStyles', 'flx/spacer') && (
										<>
											{!isSiteStylesPanel && (
												<ResponsiveAlignControls
													label={__('Alignment', 'flx-blocks')}
													value={hAlign ? hAlign : ''}
													mobileValue={mobileHAlign ? mobileHAlign : ''}
													tabletValue={tabletHAlign ? tabletHAlign : ''}
													onChange={(nextAlign) => setAttributes({ hAlign: nextAlign })}
													onChangeTablet={(nextAlign) =>
														setAttributes({ tabletHAlign: nextAlign })
													}
													onChangeMobile={(nextAlign) =>
														setAttributes({ mobileHAlign: nextAlign })
													}
												/>
											)}
											{!isSiteStylesPanel && (
												<SelectControl
													label={__('Divider Style', 'flx-blocks')}
													value={dividerStyle}
													options={[
														{ value: 'solid', label: __('Solid', 'flx-blocks') },
														{ value: 'dashed', label: __('Dashed', 'flx-blocks') },
														{ value: 'dotted', label: __('Dotted', 'flx-blocks') },
														{ value: 'stripe', label: __('Stripe', 'flx-blocks') },
													]}
													onChange={(value) => setAttributes({ dividerStyle: value })}
												/>
											)}
											<PopColorControl
												label={__('Divider Color', 'flx-blocks')}
												value={dividerColor ? dividerColor : ''}
												default={''}
												opacityValue={dividerOpacity}
												onChange={(value) => setAttributes({ dividerColor: value })}
												onOpacityChange={(value) => setAttributes({ dividerOpacity: value })}
												opacityUnit={100}
											/>
											{!isSiteStylesPanel && 'stripe' === dividerStyle && (
												<>
													<RangeControl
														label={__('Stripe Angle', 'flx-blocks')}
														value={rotate}
														onChange={(value) => setAttributes({ rotate: value })}
														min={0}
														max={135}
													/>
													<RangeControl
														label={__('Stripe Width', 'flx-blocks')}
														value={strokeWidth}
														onChange={(value) => setAttributes({ strokeWidth: value })}
														min={1}
														max={30}
													/>
													<RangeControl
														label={__('Stripe Gap', 'flx-blocks')}
														value={strokeGap}
														onChange={(value) => setAttributes({ strokeGap: value })}
														min={1}
														max={30}
													/>
												</>
											)}
											<ResponsiveRangeControls
												label={__('Divider Height', 'flx-blocks')}
												value={dividerHeight}
												onChange={(value) => setAttributes({ dividerHeight: value })}
												tabletValue={tabletDividerHeight ? tabletDividerHeight : ''}
												onChangeTablet={(value) =>
													setAttributes({ tabletDividerHeight: value })
												}
												mobileValue={mobileDividerHeight ? mobileDividerHeight : ''}
												onChangeMobile={(value) =>
													setAttributes({ mobileDividerHeight: value })
												}
												min={minD}
												max={maxD}
												step={1}
												unit={'px'}
											/>
											<ResponsiveRangeControls
												label={__('Divider Width', 'flx-blocks')}
												value={dividerWidth}
												onChange={(value) => setAttributes({ dividerWidth: value })}
												tabletValue={tabletDividerWidth ? tabletDividerWidth : ''}
												onChangeTablet={(value) => setAttributes({ tabletDividerWidth: value })}
												mobileValue={mobileDividerWidth ? mobileDividerWidth : ''}
												onChangeMobile={(value) => setAttributes({ mobileDividerWidth: value })}
												min={0}
												max={dividerWidthUnits === 'px' ? 3000 : 100}
												step={1}
												unit={dividerWidthUnits}
												onUnit={(value) => setAttributes({ dividerWidthUnits: value })}
												units={['px', '%']}
											/>
										</>
									)}
								</FlxbkPanelBody>
							</>
						)}

						{activeTab === 'advanced' && !isSiteStylesPanel && (
							<>
								<FlxbkPanelBody
									title={__('Visibility Settings', 'flx-blocks')}
									panelName={'fxb-visibility-settings'}
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
								</FlxbkPanelBody>

								<FlxbkBlockDefaults
									attributes={attributes}
									defaultAttributes={metadata.attributes}
									blockSlug={metadata.name}
								/>
							</>
						)}
					</InspectorControls>
				</>
			)}
			<div className={`fxt-block-spacer fxt-block-spacer-halign-${previewHAlign}`}>
				{dividerEnable && (
					<>
						{dividerStyle === 'stripe' && (
							<span
								className="fxt-divider-stripe"
								style={{
									height: (previewDividerHeight < 10 ? 10 : previewDividerHeight) + 'px',
									width: previewDividerWidth + (dividerWidthUnits ? dividerWidthUnits : '%'),
								}}
							>
								<SvgPattern
									uniqueID={uniqueID}
									color={FlxbkColorOutput(dividerColor)}
									opacity={dividerOpacity}
									rotate={rotate}
									strokeWidth={strokeWidth}
									strokeGap={strokeGap}
								/>
							</span>
						)}
						{dividerStyle !== 'stripe' && (
							<hr
								className="fxt-divider"
								style={{
									borderTopColor: dividerBorderColor,
									borderTopWidth: previewDividerHeight + 'px',
									width: previewDividerWidth + (dividerWidthUnits ? dividerWidthUnits : '%'),
									borderTopStyle: dividerStyle,
								}}
							/>
						)}
					</>
				)}
				{spacerHeightUnits && 'vh' === spacerHeightUnits && (
					<div
						className="fxt-spacer-height-preview"
						style={{
							height: spacerHeight + (spacerHeightUnits ? spacerHeightUnits : 'px'),
						}}
					>
						<span id={`spacing-height-${uniqueID}`}>
							{spacerHeight + (spacerHeightUnits ? spacerHeightUnits : 'px')}
						</span>
					</div>
				)}
				{'vh' !== spacerHeightUnits &&
					showSettings('spacerDivider', 'flx/spacer') &&
					showSettings('spacerHeight', 'flx/spacer') && (
						<ResizableBox
							size={{
								height: previewHeight,
							}}
							minHeight="20"
							handleClasses={{
								top: 'flxbk-spacer__resize-handler-top',
								bottom: 'flxbk-spacer__resize-handler-bottom',
							}}
							enable={{
								top: false,
								right: false,
								bottom: true,
								left: false,
								topRight: false,
								bottomRight: false,
								bottomLeft: false,
								topLeft: false,
							}}
							onResize={(event, direction, elt, delta) => {
								editorDocument.getElementById(
									'spacing-height-' + (uniqueID ? uniqueID : 'no-unique')
								).innerHTML =
									parseInt(previewHeight + delta.height, 10) +
									(spacerHeightUnits ? spacerHeightUnits : 'px');
							}}
							onResizeStop={(event, direction, elt, delta) => {
								toggleSelection(true);
								if ('Mobile' === previewDevice) {
									setAttributes({
										mobileSpacerHeight: parseInt(previewHeight + delta.height, 10),
									});
								} else if ('Tablet' === previewDevice) {
									setAttributes({
										tabletSpacerHeight: parseInt(previewHeight + delta.height, 10),
									});
								} else {
									setAttributes({
										spacerHeight: parseInt(previewHeight + delta.height, 10),
									});
								}
							}}
							onResizeStart={() => {
								toggleSelection(false);
							}}
						>
							{uniqueID && (
								<div className="fxt-spacer-height-preview">
									<span id={`spacing-height-${uniqueID}`}>
										{previewHeight + (spacerHeightUnits ? spacerHeightUnits : 'px')}
									</span>
								</div>
							)}
						</ResizableBox>
					)}
				{'vh' !== spacerHeightUnits &&
					(!showSettings('spacerDivider', 'flx/spacer') ||
						!showSettings('spacerHeight', 'flx/spacer')) && (
						<div
							className="fxt-spacer-height-preview"
							style={{
								height: previewHeight + (spacerHeightUnits ? spacerHeightUnits : 'px'),
							}}
						>
							<span id={`spacing-height-${uniqueID}`}>
								{previewHeight + (spacerHeightUnits ? spacerHeightUnits : 'px')}
							</span>
						</div>
					)}
			</div>
		</div>
	);
}

export default FlxbkSpacerDivider;
