/**
 * BLOCK: Flux Icon
 */
/**
 * Import externals
 */
import {
	PopColorControl,
	FlxbkPanelBody,
	URLInputControl,
	ResponsiveRangeControls,
	InspectorControlTabs,
	RangeControl,
	FlxbkRadioButtons,
	FlxbkInspectorControls,
	FlxbkBlockDefaults,
	FlxbkIconPicker,
	CopyPasteAttributes,
} from '@flx-blocks/components';
import { FlxbkColorOutput, uniqueIdHelper, getInQueryBlock, getPreviewSize } from '@flx-blocks/helpers';
import { useSelect, useDispatch } from '@wordpress/data';
import { EditorScalarControl } from '../../extension/design-tokens/components/EditorScalarControl';
import { tokenLiteral } from '../../extension/design-tokens/token-literals';
import { activeLibrary } from '../../extension/preset-picker';
import { measureAttrsForDevice } from '../../extension/token-indicators/normalize';
import { presetPropertyValueForDevice, resetAttr, usePresetBinding } from '../../extension/token-indicators';
import { useColorGroups } from '../../extension/design-tokens/hooks/use-color-groups';
import { resolveColorLiteral } from '../../extension/design-tokens/color-literal';
import { ColorControl, ColorControlGroup } from '../../token-controls';
import { boundTokenAliasForControl, pickableTokensForControl } from '../../extension/token-picker';
import { PreviewIcon } from './preview-icon';
import { AdvancedSettings } from './advanced-settings';
import { tooltip as tooltipIcon } from '@flx-blocks/icons';
import metadata from './block.json';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';

import { useBlockProps, BlockControls } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';
import {
	TextControl,
	TextareaControl,
	Dropdown,
	Button,
	SelectControl,
	ToggleControl,
	ToolbarGroup,
} from '@wordpress/components';

function FlxbkSingleIcon(props) {
	const { attributes, className, setAttributes, clientId, isSelected, name, context } = props;
	const {
		inQueryBlock,
		icon,
		link,
		target,
		size,
		width,
		title,
		hColor,
		hBackground,
		tabletSize,
		hBorder,
		color,
		background,
		border,
		borderRadius,
		borderWidth,
		style,
		linkTitle,
		mobileSize,
		uniqueID,
		tooltip,
		tooltipPlacement,
		tooltipDash,
	} = attributes;

	const nonTransAttrs = ['icon', 'link', 'target'];

	const [activeTab, setActiveTab] = useState('general');
	const isSiteStylesPanel = useIsSiteStylesPanel();

	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);

	const previewSize = getPreviewSize(
		previewDevice,
		undefined !== size ? size : undefined,
		undefined !== tabletSize ? tabletSize : undefined,
		undefined !== mobileSize ? mobileSize : undefined
	);

	const { setPreviewDeviceType } = useDispatch('flxblocks/data');

	// Design-token indicators. This block previously wired its Size control with pools and defaults but
	// no binding state, so nothing on it reported whether a value still matched the selected preset.
	// Adding it here serves the color control below and backfills Size at the same time.
	const tokenBinding = usePresetBinding(name, attributes, undefined, previewDevice);
	// The schema matters most on this block: `size` is a SCALAR (a bare number, with no companion unit
	// attribute), and without it `resetAttrPatch` falls back to the four-slot shape every other measure
	// control uses -- writing `['', '', '', '']` into a scalar, which the field then reads straight back
	// as a custom value, plus a `sizeUnit` the block never declares.
	const resetToken = (attr) => resetAttr(attr, setAttributes, tokenBinding[attr]?.kind, metadata.attributes);
	// One fetch of the block's effective palette groups, shared by both `ColorControl` instances below.
	const colorGroups = useColorGroups(clientId);
	// The Icon Size control writes three sibling attributes but edits one at a time, so it resolves the
	// active device's attribute up front — reading or writing `size` while the editor is on Tablet would
	// hit the wrong breakpoint.
	const sizeForDevice = measureAttrsForDevice(
		attributes,
		'size',
		{ tablet: 'tabletSize', mobile: 'mobileSize' },
		previewDevice
	);
	// Empty when the token registry is inactive, which is what keeps the plain range control below as the
	// fallback rather than leaving the block with no size control at all.
	const iconSizeTokens = pickableTokensForControl(name, 'size') || [];
	// What the active device falls back to when it stores nothing: the breakpoints ABOVE it, in cascade
	// order, then the SELECTED PRESET's size, then the icon-size token the binding names. The device's own
	// value is deliberately left out — this is what the field reports when it is unset, so including it
	// would just echo the value already on screen. Tablet and Mobile inherit; Desktop has nothing above it.
	//
	// The preset sits ahead of the token because that is the order the rendered CSS resolves in: the
	// preset's scoped rule sets `--fxb-icon-size`, which the block-default rule reads before falling through
	// to the token. Reporting the token here would tell a site owner their unset field inherits one size
	// while the block renders another.
	const inheritedSizeChain = {
		Tablet: [size],
		Mobile: [tabletSize, size],
	};
	const inheritedSize = (inheritedSizeChain[previewDevice] || []).find(
		(value) => value !== undefined && value !== null && value !== ''
	);
	const presetSize = presetPropertyValueForDevice(name, 'size', attributes, undefined, previewDevice);
	const iconSizeDefault =
		inheritedSize ??
		(presetSize ? tokenLiteral(presetSize, activeLibrary()) : undefined) ??
		tokenLiteral(boundTokenAliasForControl(name, 'size'), activeLibrary());

	useEffect(() => {
		setAttributes({ inQueryBlock: getInQueryBlock(context, inQueryBlock) });
	}, []);

	uniqueIdHelper(props);

	const blockProps = useBlockProps({
		className,
	});

	const renderCSS = (
		<style>
			{`.wp-block-flx-single-icon .fxt-svg-item-${uniqueID}:hover .fxt-svg-icon {
					${undefined !== hColor && hColor ? 'color:' + FlxbkColorOutput(hColor) + '!important;' : ''}
            }
            .wp-block-flx-single-icon .fxt-svg-style-stacked.fxt-svg-item-${uniqueID}:hover .fxt-svg-icon {
					${undefined !== hBackground && hBackground ? 'background:' + FlxbkColorOutput(hBackground) + '!important;' : ''}
					${undefined !== hBorder && hBorder ? 'border-color:' + FlxbkColorOutput(hBorder) + '!important;' : ''}
            }`}
		</style>
	);

	return (
		<div {...blockProps}>
			{renderCSS}
			<BlockControls>
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
									<ToggleControl
										label={__('Show indicator underline', 'flx-blocks')}
										checked={tooltipDash}
										onChange={(value) => {
											setAttributes({ tooltipDash: value });
										}}
									/>
								</div>
							</>
						)}
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
			<FlxbkInspectorControls blockSlug={'flx/icon'}>
				<InspectorControlTabs
					panelName={'single-icon'}
					allowedTabs={['general', 'advanced']}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody
							title={__('Icon Settings', 'flx-blocks')}
							initialOpen={true}
							panelName={'fxb-icon-settings'}
						>
							{!isSiteStylesPanel && (
								<FlxbkIconPicker
									value={icon}
									onChange={(value) => {
										setAttributes({ icon: value });
									}}
								/>
							)}

							{iconSizeTokens.length ? (
								<EditorScalarControl
									label={__('Icon Size', 'flx-blocks')}
									value={sizeForDevice.value}
									onChange={(value) => {
										setAttributes({ [sizeForDevice.attr]: value });
									}}
									previewDevice={previewDevice}
									onDeviceChange={(device) => setPreviewDeviceType(device)}
									tokens={iconSizeTokens}
									// So an unset field names the size actually in effect — "Inherited (50px)" on a
									// breakpoint taking Desktop's value, "Default (50px)" once the cascade reaches the
									// token — instead of reading blank after a Reset.
									defaultValue={iconSizeDefault}
									inherited={inheritedSize !== undefined}
									state={tokenBinding.size}
									onReset={() => resetToken('size')}
									// The size attributes store a bare number and the block declares no unit
									// attribute of its own, so the front end always renders them as px. Pinning the
									// Custom tab to px keeps a hand-typed value in the one unit the attribute can
									// actually mean; an em/rem-valued TOKEN is unaffected, since its unit travels
									// inside the token and reaches output as the var() reference.
									unit={'px'}
									units={['px']}
									min={0}
									max={300}
									step={1}
								/>
							) : (
								<ResponsiveRangeControls
									label={__('Icon Size', 'flx-blocks')}
									value={previewSize}
									onChange={(value) => {
										setAttributes({ size: value });
									}}
									tabletValue={undefined !== tabletSize ? tabletSize : ''}
									onChangeTablet={(value) => {
										setAttributes({ tabletSize: value });
									}}
									mobileValue={undefined !== mobileSize ? mobileSize : ''}
									onChangeMobile={(value) => {
										setAttributes({ mobileSize: value });
									}}
									min={0}
									max={300}
									step={1}
									unit={'px'}
								/>
							)}
							{!isSiteStylesPanel && icon && 'fe' === icon.substring(0, 2) && (
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

							<FlxbkRadioButtons
								label={__('Icon Style', 'flx-blocks')}
								value={style}
								options={[
									{ value: 'default', label: __('Default', 'flx-blocks') },
									{ value: 'stacked', label: __('Stacked', 'flx-blocks') },
								]}
								onChange={(value) => setAttributes({ style: value })}
							/>
							{isSiteStylesPanel && (
								<p className="components-base-control__help">
									{__(
										'Each icon keeps its own style. Pick Stacked to edit the box that Stacked icons use.',
										'flx-blocks'
									)}
								</p>
							)}
							{/* One two-swatch control becomes two single-value ones, matching the Button's own
							    icon pair. Both are bound now that the icon's hover color is a preset property,
							    so each carries its own binding state and its own reset -- the hover swatch's
							    entry only appears once a preset actually resolves a hover color, which is what
							    keeps its indicator silent until there is something to diverge from. */}
							<ColorControlGroup>
								<ColorControl
									label={__('Icon Color', 'flx-blocks')}
									value={color ? color : ''}
									groups={colorGroups}
									status={{
										bound: !!tokenBinding.color?.bound,
										modified: !!tokenBinding.color?.overridden,
									}}
									onReset={() => resetToken('color')}
									onPick={(alias) => setAttributes({ color: alias })}
									onCustom={(literal) => setAttributes({ color: literal })}
									onClear={() => setAttributes({ color: '' })}
									resolveLiteral={resolveColorLiteral}
								/>
								<ColorControl
									label={__('Hover Color', 'flx-blocks')}
									value={hColor ? hColor : ''}
									groups={colorGroups}
									status={{
										bound: !!tokenBinding.hColor?.bound,
										modified: !!tokenBinding.hColor?.overridden,
									}}
									onReset={() => resetToken('hColor')}
									onPick={(alias) => setAttributes({ hColor: alias })}
									onCustom={(literal) => setAttributes({ hColor: literal })}
									onClear={() => setAttributes({ hColor: '' })}
									resolveLiteral={resolveColorLiteral}
								/>
							</ColorControlGroup>
							{style !== 'default' && (
								<>
									<PopColorControl
										label={__('Background Color', 'flx-blocks')}
										value={background ? background : ''}
										default={''}
										onChange={(value) => {
											setAttributes({ background: value });
										}}
										swatchLabel2={__('Hover Background', 'flx-blocks')}
										value2={hBackground ? hBackground : ''}
										default2={''}
										onChange2={(value) => {
											setAttributes({ hBackground: value });
										}}
									/>
									<PopColorControl
										label={__('Border Color', 'flx-blocks')}
										value={border ? border : ''}
										default={''}
										onChange={(value) => {
											setAttributes({ border: value });
										}}
										swatchLabel2={__('Hover Border', 'flx-blocks')}
										value2={hBorder ? hBorder : ''}
										default2={''}
										onChange2={(value) => {
											setAttributes({ hBorder: value });
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
									/>
									<RangeControl
										label={__('Border Radius (%)', 'flx-blocks')}
										value={borderRadius}
										onChange={(value) => {
											setAttributes({ borderRadius: value });
										}}
										min={0}
										max={50}
									/>
								</>
							)}
							{!isSiteStylesPanel && (
								<>
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
										linkTitle={linkTitle}
										onChangeTitle={(value) => {
											setAttributes({ linkTitle: value });
										}}
										dynamicAttribute={'link'}
										allowClear={true}
										isSelected={isSelected}
										attributes={attributes}
										setAttributes={setAttributes}
										name={name}
										clientId={clientId}
										context={context}
									/>
									<TextControl
										label={__('Title for screen readers', 'flx-blocks')}
										help={__(
											'If no title added screen readers will ignore, good if the icon is purely decorative.',
											'flx-blocks'
										)}
										value={title}
										onChange={(value) => {
											setAttributes({ title: value });
										}}
									/>
								</>
							)}
						</FlxbkPanelBody>
					</>
				)}
				{activeTab === 'advanced' && (
					<>
						<AdvancedSettings attributes={attributes} setAttributes={setAttributes} />

						{!isSiteStylesPanel && (
							<FlxbkBlockDefaults
								attributes={attributes}
								defaultAttributes={metadata.attributes}
								blockSlug={metadata.name}
								excludedAttrs={nonTransAttrs}
							/>
						)}
					</>
				)}
			</FlxbkInspectorControls>
			<PreviewIcon attributes={attributes} previewDevice={previewDevice} />
		</div>
	);
}

export default FlxbkSingleIcon;
