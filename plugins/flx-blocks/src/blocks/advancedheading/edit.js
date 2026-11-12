/**
 * BLOCK: Flux Advanced Heading
 *
 */

/**
 * Internal dependencies
 */
import classnames from 'classnames';
import {
	PopColorControl,
	TextShadowControl,
	ResponsiveShadowControl,
	TypographyControls,
	InlineTypographyControls,
	ResponsiveMeasurementControls,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	URLInputControl,
	FlxbkWebfontLoader,
	HeadingLevelIcon,
	InlinePopColorControl,
	ResponsiveAlignControls,
	InspectorControlTabs,
	FlxbkBlockDefaults,
	ResponsiveMeasureRangeControl,
	SpacingVisualizer,
	ResponsiveUnitControl,
	TwoColumn,
	ColorGroup,
	ResponsiveFontSizeControl,
	FlxbkRadioButtons,
	TagSelect,
	ResponsiveBorderControl,
	CopyPasteAttributes,
	FlxbkIconPicker,
	IconRender,
	DynamicTextControl,
	DynamicInlineReplaceControl,
	GradientControl,
	ResponsiveFlxbkRadioButtons,
} from '@flx-blocks/components';

import {
	dynamicIcon,
	horizontalTextOrientationIcon,
	stackedTextOrientationIcon,
	sidewaysDownTextOrientationIcon,
	sidewaysUpTextOrientationIcon,
} from '@flx-blocks/icons';

import {
	FlxbkColorOutput,
	showSettings,
	getPreviewSize,
	getSpacingOptionOutput,
	mouseOverVisualizer,
	getFontSizeOptionOutput,
	getBorderStyle,
	getBorderColor,
	uniqueIdHelper,
	getInQueryBlock,
} from '@flx-blocks/helpers';

/**
 * Block dependencies
 */
import './formats/markformat';
import './formats/typed-text';
import './formats/tooltips';
import './formats/inline-image';
import './formats/screen-reader-text';

import Typed from 'typed.js';

/**
 * Import Css
 */
import './editor.scss';
import {
	presetPropertyValueForDevice,
	resetAttr,
	useLinkedMeasureState,
	usePresetBinding,
} from '../../extension/token-indicators';
import {
	anyCornerInherited,
	inheritedMeasureSlots,
	measureAttrsForDevice,
	presetValueForDevice,
} from '../../extension/token-indicators/normalize';
import { borderSideDeclarations } from '../../extension/design-tokens/border-sides';
import { EditorBorderControl } from '../../extension/design-tokens/components/EditorBorderControl';
import { EditorBoxControl } from '../../extension/design-tokens/components/EditorBoxControl';
import { EditorFontFamilyControl } from '../../extension/design-tokens/components/EditorFontFamilyControl';
import { EditorScalarControl } from '../../extension/design-tokens/components/EditorScalarControl';
import { useColorGroups } from '../../extension/design-tokens/hooks/use-color-groups';
import { resolveColorLiteral } from '../../extension/design-tokens/color-literal';
import { BorderColorField } from '../../extension/design-tokens/components/border-color';
import { tokenDimension } from '../../extension/design-tokens/token-dimension';
import { pickableTokensForControl, pickableTokensForKey } from '../../extension/token-picker';
import { ColorControl } from '../../token-controls';
import { EditorColorControlGroup } from '../../extension/design-tokens/components/EditorColorControlGroup';
import { presetFontVariant } from './preset-font-variant';
import { isFseMode } from '../../site-styles/supported-blocks';
import { isConvertedHeading } from './converted-headings';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';
import metadata from './block.json';
/**
 * Internal block libraries
 */
import { __, sprintf } from '@wordpress/i18n';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import {
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
	InspectorAdvancedControls,
	RichText,
	getColorClassName,
	useBlockProps,
} from '@wordpress/block-editor';
import { useCallback, useEffect, useState, useRef } from '@wordpress/element';

import {
	ToolbarGroup,
	Spinner,
	SelectControl,
	ToolbarDropdownMenu,
	TextControl,
	ToggleControl,
	TextareaControl,
	Tooltip,
} from '@wordpress/components';

import { applyFilters } from '@wordpress/hooks';
import { get } from 'lodash';

/**
 * Regular expression matching invalid anchor characters for replacement.
 *
 * @type {RegExp}
 */
const ANCHOR_REGEX = /[\s#]/g;

function FlxbkAdvancedHeading(props) {
	const { attributes, className, isSelected, setAttributes, mergeBlocks, onReplace, clientId, context } = props;
	const {
		inQueryBlock,
		uniqueID,
		align,
		level,
		content,
		color,
		colorClass,
		enableTextShadow,
		textShadow,
		textShadowTablet,
		textShadowMobile,
		textOrientation,
		tabletTextOrientation,
		mobileTextOrientation,
		mobileAlign,
		tabletAlign,
		size,
		sizeType,
		lineType,
		lineHeight,
		tabLineHeight,
		tabSize,
		mobileSize,
		mobileLineHeight,
		letterSpacing,
		typography,
		fontVariant,
		fontWeight,
		fontStyle,
		fontSubset,
		googleFont,
		loadGoogleFont,
		marginType,
		topMargin,
		bottomMargin,
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
		anchor,
		textTransform,
		markTextTransform,
		flxbkAnimation,
		flxbkAOSOptions,
		htmlTag,
		leftMargin,
		rightMargin,
		tabletMargin,
		mobileMargin,
		margin,
		padding,
		tabletPadding,
		mobilePadding,
		paddingType,
		markMobilePadding,
		markTabPadding,
		loadItalic,
		flxbkDynamic,
		link,
		linkTarget,
		linkNoFollow,
		linkSponsored,
		background,
		backgroundColorClass,
		linkStyle,
		linkColor,
		linkHoverColor,
		fontSize,
		fontHeight,
		fontHeightType,
		letterSpacingType,
		tabletLetterSpacing,
		mobileLetterSpacing,
		markLetterSpacingType,
		markPaddingType,
		tabletMarkLetterSpacing,
		mobileMarkLetterSpacing,
		markBorderStyles,
		tabletMarkBorderStyles,
		mobileMarkBorderStyles,
		maxWidthType,
		maxWidth,
		maxHeightType,
		maxHeight,
		beforeIcon,
		afterIcon,
		icon,
		iconTitle,
		iconColor,
		iconColorHover,
		iconSide,
		iconVerticalAlign,
		iconPadding,
		tabletIconPadding,
		mobileIconPadding,
		iconSize,
		iconSizeUnit,
		iconPaddingUnit,
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle,
		borderRadius,
		tabletBorderRadius,
		mobileBorderRadius,
		borderRadiusUnit,
		markBorderRadius,
		tabletMarkBorderRadius,
		mobileMarkBorderRadius,
		markBorderRadiusUnit,
		altTitle,
		iconTooltipPlacement,
		iconTooltipDash,
		iconTooltip,
		textGradient,
		enableTextGradient,
		enableMarkGradient,
		enableMarkBackgroundGradient,
		markGradient,
		markBackgroundGradient,
		inlineImageWidth,
		inlineImageBorderStyles,
		tabletInlineImageBorderStyles,
		mobileInlineImageBorderStyles,
		inlineImageBorderRadius,
		tabletInlineImageBorderRadius,
		mobileInlineImageBorderRadius,
		inlineImageBorderRadiusUnit,
		inlineImageVerticalAlign,
		useRatio,
		ratio,
	} = attributes;

	const isSiteStylesPanel = useIsSiteStylesPanel();
	const [activeTab, setActiveTab] = useState('style');
	const [contentRef, setContentRef] = useState();

	const { previewDevice, allowedFormats } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				allowedFormats: select('core/rich-text').getFormatTypes(),
			};
		},
		[clientId]
	);

	const { replaceBlocks, insertBlocks } = useDispatch('core/block-editor');
	const { setPreviewDeviceType: setPreviewDevice } = useDispatch('flxblocks/data');

	// Design-token indicators: the per-attribute bound/overridden state for the selected preset, plus a
	// reset that clears the mapped attribute back to the preset value (served by the existing scoped CSS).
	const tokenBinding = usePresetBinding('flx/advancedheading', attributes, undefined, previewDevice);
	// The block's attribute schema goes through so a reset clears a color's legacy palette-CLASS
	// companion (`color` -> `colorClass`, `background` -> `backgroundColorClass`) alongside the color.
	// WordPress emits those utility classes with `!important`, so a stale one outranks whatever the
	// reset restores and the heading would keep the old color while the field claimed otherwise.
	const resetToken = (attr) => resetAttr(attr, setAttributes, tokenBinding[attr]?.kind, metadata.attributes);
	// One fetch of the block's effective palette groups, shared by every `ColorControl` on this block.
	const colorGroups = useColorGroups(clientId);
	// `BorderControl` forwards `renderColor` down through `SlotGrid`'s `renderSlot`, so this is
	// defined once per render rather than inline at each of the border panels below.
	const renderBorderColor = useCallback((row) => <BorderColorField {...row} groups={colorGroups} />, [colorGroups]);

	// Empty when the token registry is inactive, which is what keeps each plain control below as the
	// fallback rather than leaving the heading without it.
	const fontSizeTokens = pickableTokensForControl('flx/advancedheading', 'fontSize') || [];
	const borderRadiusTokens = pickableTokensForControl('flx/advancedheading', 'borderRadius') || [];
	const paddingTokens = pickableTokensForControl('flx/advancedheading', 'padding') || [];
	// Width, style and color all declare `borderStyle` as their control attribute, so a lookup by
	// attribute cannot tell them apart — the width pool is fetched by the binding's own key instead.
	const borderWidthTokens = pickableTokensForKey('flx/advancedheading', 'borderWidth');

	// A measure control keeps ONE linked/individual mode but writes a different attribute per
	// breakpoint, so the mode must be read from — and "link" must collapse — whichever device is active.
	const borderRadiusForDevice = measureAttrsForDevice(
		attributes,
		'borderRadius',
		{ tablet: 'tabletBorderRadius', mobile: 'mobileBorderRadius' },
		previewDevice
	);
	const paddingForDevice = measureAttrsForDevice(
		attributes,
		'padding',
		{ tablet: 'tabletPadding', mobile: 'mobilePadding' },
		previewDevice
	);
	const borderRadiusPresetValue = presetValueForDevice(
		tokenBinding.borderRadius?.presetValue,
		tokenBinding.borderRadius?.responsive,
		previewDevice
	);
	const paddingPresetValue = presetValueForDevice(
		tokenBinding.padding?.presetValue,
		tokenBinding.padding?.responsive,
		previewDevice
	);
	// What an unset slot falls back to on the active device: another breakpoint's slot before the
	// preset's, matching the cascade the heading actually renders through. The slots stay stored-empty —
	// this only tells the field's popover which size is in effect and where it came from.
	const inheritedBorderRadius = inheritedMeasureSlots(
		previewDevice,
		{ desktop: borderRadius, tablet: tabletBorderRadius },
		borderRadiusPresetValue
	);
	const inheritedPadding = inheritedMeasureSlots(
		previewDevice,
		{ desktop: padding, tablet: tabletPadding },
		paddingPresetValue
	);

	// Font size packs all three devices into ONE array attribute (`fontSize[0..2]` is desktop/tablet/
	// mobile) rather than naming a separate attribute per device, so `measureAttrsForDevice` — which maps
	// a breakpoint to an attribute NAME — cannot address it. The index is resolved here instead, and the
	// control reads and writes through it; the same reason the binding declares no `responsive_attrs`.
	const fontSizeIndex = { Tablet: 1, Mobile: 2 }[previewDevice] ?? 0;
	const fontSizeValue = fontSize?.[fontSizeIndex] ?? '';
	const writeFontSize = (next) => {
		const slots = [fontSize?.[0] ?? '', fontSize?.[1] ?? '', fontSize?.[2] ?? ''];

		slots[fontSizeIndex] = next;

		setAttributes({ fontSize: slots });
	};
	// The heading's own cascade for an unset size: the breakpoints above this one, then the preset's own
	// value. A preset can only set the base size (see the binding's docblock), so there is no per-device
	// preset value to prefer over it.
	const inheritedFontSize = ({ Tablet: [fontSize?.[0]], Mobile: [fontSize?.[1], fontSize?.[0]] }[previewDevice] || [])
		.filter((value) => value !== undefined && value !== null && value !== '')
		.shift();
	const fontSizePresetValue = presetPropertyValueForDevice(
		'flx/advancedheading',
		'fontSize',
		attributes,
		undefined,
		previewDevice
	);
	// The border width shares one control with style and color, so its default is read by the binding's
	// key rather than off a `borderWidth` attribute the block does not have.
	const borderWidthPresetValue = presetPropertyValueForDevice(
		'flx/advancedheading',
		'borderWidth',
		attributes,
		undefined,
		previewDevice
	);

	const borderRadiusIsRelative = borderRadiusUnit === 'em' || borderRadiusUnit === 'rem';
	const paddingIsRelative = paddingType === 'em' || paddingType === 'rem';
	// `resetOn` clears the remembered link choice on a preset change, since an override records a choice
	// about the PREVIOUS preset's slots — otherwise an explicit "link" would stick and hide a new preset's
	// per-slot value.
	const { isLinked: borderRadiusIsLinked, toggleLink: toggleBorderRadiusLink } = useLinkedMeasureState({
		forDevice: borderRadiusForDevice,
		previewDevice,
		setAttributes,
		resetOn: attributes.fxbPreset,
	});
	const { isLinked: paddingIsLinked, toggleLink: togglePaddingLink } = useLinkedMeasureState({
		forDevice: paddingForDevice,
		previewDevice,
		setAttributes,
		resetOn: attributes.fxbPreset,
	});

	const handlePaste = (event) => {
		const pastedText = event.clipboardData.getData('text/plain');

		const containsBlocks = pastedText && (pastedText.includes('<!-- wp:') || pastedText.includes('wp-block-'));

		if (containsBlocks) {
			const rawBlocks = wp.blocks.rawHandler({ HTML: pastedText });

			if (!content || content === '') {
				replaceBlocks(clientId, rawBlocks);
			} else {
				const { getBlockIndex, getBlockRootClientId } = wp.data.select('core/block-editor');
				const currentBlockIndex = getBlockIndex(clientId);
				const parentBlockClientId = getBlockRootClientId(clientId);

				insertBlocks(rawBlocks, currentBlockIndex + 1, parentBlockClientId);
			}
			event.preventDefault();
		} else if (pastedText && isDefaultEditorBlock && (!content || content === '')) {
			const paragraphs = pastedText.split(/\n\s*\n/).flatMap((paragraph) => paragraph.split(/\r\s*/));

			const newBlocks = paragraphs
				.map((paragraph, thisIndex) => {
					const trimmedParagraph = paragraph.trim();
					if (!trimmedParagraph) {
						return null;
					}

					// The stored attributes, so the site values the heading shows do not become its own.
					const newAttributes = { ...select('core/block-editor').getBlockAttributes(clientId) };
					newAttributes.uniqueID = attributes.uniqueID + '_' + thisIndex;

					return wp.blocks.createBlock('flx/advancedheading', {
						...newAttributes,
						content: trimmedParagraph,
					});
				})
				.filter(Boolean);

			if (newBlocks.length > 0 && isDefaultEditorBlock) {
				replaceBlocks(clientId, newBlocks);
				event.preventDefault();
			}
		}
	};

	const paddingMouseOver = mouseOverVisualizer();
	const marginMouseOver = mouseOverVisualizer();
	const config = get(flxbk_params, 'globalSettings') ? JSON.parse(flxbk_params.globalSettings) : {};
	const isDefaultEditorBlock =
		undefined !== config.adv_text_is_default_editor_block && config.adv_text_is_default_editor_block;

	useEffect(() => {
		setAttributes({ inQueryBlock: getInQueryBlock(context, inQueryBlock) });

		// Update Old Styles
		if ('' !== topMargin || '' !== rightMargin || '' !== bottomMargin || '' !== leftMargin) {
			setAttributes({
				margin: [topMargin, rightMargin, bottomMargin, leftMargin],
				topMargin: '',
				rightMargin: '',
				bottomMargin: '',
				leftMargin: '',
			});
		}
		// Update Old font Styles
		if (size || tabSize || mobileSize) {
			setAttributes({ fontSize: [size, tabSize, mobileSize], size: '', tabSize: '', mobileSize: '' });
		}
		// Update Old Line height Styles
		if (lineHeight || tabLineHeight || mobileLineHeight) {
			setAttributes({
				fontHeight: [lineHeight, tabLineHeight, mobileLineHeight],
				fontHeightType: lineType,
				lineHeight: '',
				tabLineHeight: '',
				mobileLineHeight: '',
			});
		}

		// Update "regular" to Normal font weight
		if ('regular' === fontWeight) {
			setAttributes({ fontWeight: 'normal' });
		}

		// Update from old border settings.
		const tempBorderStyle = JSON.parse(
			JSON.stringify(
				attributes.markBorderStyles
					? attributes.markBorderStyles
					: [
							{
								top: ['', '', ''],
								right: ['', '', ''],
								bottom: ['', '', ''],
								left: ['', '', ''],
								unit: 'px',
							},
						]
			)
		);
		let updateBorderStyle = false;
		if (markBorder) {
			tempBorderStyle[0].top[0] = FlxbkColorOutput(markBorder, markBorderOpacity);
			tempBorderStyle[0].right[0] = FlxbkColorOutput(markBorder, markBorderOpacity);
			tempBorderStyle[0].bottom[0] = FlxbkColorOutput(markBorder, markBorderOpacity);
			tempBorderStyle[0].left[0] = FlxbkColorOutput(markBorder, markBorderOpacity);
			updateBorderStyle = true;
			setAttributes({ markBorder: '' });
		}
		if ('' !== markBorderWidth && 0 !== markBorderWidth) {
			tempBorderStyle[0].top[2] = markBorderWidth;
			tempBorderStyle[0].right[2] = markBorderWidth;
			tempBorderStyle[0].bottom[2] = markBorderWidth;
			tempBorderStyle[0].left[2] = markBorderWidth;
			updateBorderStyle = true;
			setAttributes({ markBorderWidth: 0 });
		}
		if ('' !== markBorderStyle && 'solid' !== markBorderStyle) {
			tempBorderStyle[0].top[1] = markBorderStyle;
			tempBorderStyle[0].right[1] = markBorderStyle;
			tempBorderStyle[0].bottom[1] = markBorderStyle;
			tempBorderStyle[0].left[1] = markBorderStyle;
			updateBorderStyle = true;
			setAttributes({ markBorderStyle: 'solid' });
		}
		if (updateBorderStyle) {
			setAttributes({ markBorderStyles: tempBorderStyle });
			setAttributes({ tabletMarkBorderStyles: tempBorderStyle });
			setAttributes({ mobileMarkBorderStyles: tempBorderStyle });
		}

		// update enableTextShadow if textShadow[0].enable is true for previous unresponsive setting
		if (!enableTextShadow && textShadow[0].enable) {
			setAttributes({ enableTextShadow: true });
		}
	}, []);

	useEffect(() => {
		/*
		 * FSE mode ignores Block Defaults, which is where "Advanced Text as default block" keeps the paragraph tag.
		 * A block that arrives with a tag, a level or text, or from converting a core Heading, is not a fresh default block.
		 */
		if (
			isFseMode() &&
			!isSiteStylesPanel &&
			!uniqueID &&
			isDefaultEditorBlock &&
			!isConvertedHeading(clientId) &&
			!content &&
			'heading' === htmlTag &&
			2 === level
		) {
			setAttributes({ htmlTag: 'p' });
		}
	}, []);

	uniqueIdHelper(props);

	let newItems;
	const saveShadow = (value) => {
		if (value.enable === 'reset') {
			const resetItems = [
				{
					type: 'array',
					default: [
						{
							enable: false,
							color: '#000000',
							opacity: 0.2,
							blur: 1,
							hOffset: 1,
							vOffset: 1,
						},
					],
					enable: false,
				},
			];
			switch (previewDevice) {
				case 'Desktop':
					setAttributes({ textShadow: resetItems });
					break;
				case 'Tablet':
					setAttributes({ textShadowTablet: resetItems });
					break;
				case 'Mobile':
					setAttributes({ textShadowMobile: resetItems });
					break;
			}
		} else if (previewDevice === 'Desktop') {
			newItems = textShadow.map((item, thisIndex) => {
				if (0 === thisIndex) {
					item = { ...item, ...value };
				}
				return item;
			});
			setAttributes({
				textShadow: newItems,
			});
		} else if (previewDevice === 'Tablet') {
			newItems = textShadowTablet.map((item, thisIndex) => {
				if (0 === thisIndex) {
					item = { ...item, ...value };
				}
				return item;
			});
			setAttributes({
				textShadowTablet: newItems,
			});
		} else if (previewDevice === 'Mobile') {
			newItems = textShadowMobile.map((item, thisIndex) => {
				if (0 === thisIndex) {
					item = { ...item, ...value };
				}
				return item;
			});
			setAttributes({
				textShadowMobile: newItems,
			});
		}
	};

	const isDynamicReplaced =
		undefined !== flxbkDynamic &&
		undefined !== flxbkDynamic.content &&
		undefined !== flxbkDynamic.content.enable &&
		flxbkDynamic.content.enable;
	let richTextFormatsBase = applyFilters(
		'flxbk.whitelist_richtext_formats',
		[
			'core/bold',
			'core/italic',
			'core/link',
			'flx/mark',
			'flx/typed',
			'flx/tooltips',
			'flx/inline-image',
			'core/strikethrough',
			'core/superscript',
			'core/superscript',
			'toolset/inline-field',
			'flx/screen-reader-text',
		],
		'flx/advancedheading'
	);

	let richTextFormats = allowedFormats.map((format) => format.name);
	if (link || flxbkDynamic?.content?.shouldReplace) {
		richTextFormatsBase = !flxbkDynamic?.content?.shouldReplace
			? [...['flx/insert-dynamic'], ...richTextFormatsBase]
			: richTextFormatsBase;
		richTextFormats = richTextFormatsBase;
	}

	// The family a heading with none of its own falls back to: whatever the SELECTED preset carries.
	// A preset stores the family as a literal (the font catalog is not a token scale), so this is the
	// name itself rather than a token reference, and it is quoted for CSS exactly like the block's own.
	// The front end does the same through `render_preset_typography()`, which points font-family at the
	// preset variable only when the block's own `typography` is empty — the two agree by construction.
	const presetTypography = presetPropertyValueForDevice(metadata.name, 'typography', attributes);
	// The weight to ASK GOOGLE FOR alongside that family. The preset's weight already reaches the page
	// as a `font-weight` declaration through its own binding, but the face it names has to be requested
	// too — a family loaded at 400 with `font-weight: 700` over it renders as a synthesized bold. An
	// explicit variant on the block still wins, then a weight the block sets itself, then the preset's.
	const presetFontWeight = presetPropertyValueForDevice(metadata.name, 'fontWeight', attributes);
	const presetVariant = fontVariant || presetFontVariant(fontWeight || presetFontWeight);
	const effectiveTypography = typography || presetTypography || '';
	const renderTypography =
		effectiveTypography && !effectiveTypography.includes(',')
			? "'" + effectiveTypography + "'"
			: effectiveTypography;
	const markBGString = markBG ? FlxbkColorOutput(markBG, markBGOpacity) : '';
	const markBorderString = markBorder ? FlxbkColorOutput(markBorder, markBorderOpacity) : '';
	const textColorClass = getColorClassName('color', colorClass);
	const textBackgroundColorClass = getColorClassName('background-color', backgroundColorClass);
	const tagName = htmlTag && htmlTag !== 'heading' ? htmlTag : 'h' + level;
	const TagHTML = tagName;

	const previewMarginTop = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[0] : '',
		undefined !== tabletMargin ? tabletMargin[0] : '',
		undefined !== mobileMargin ? mobileMargin[0] : ''
	);
	const previewMarginRight = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[1] : '',
		undefined !== tabletMargin ? tabletMargin[1] : '',
		undefined !== mobileMargin ? mobileMargin[1] : ''
	);
	const previewMarginBottom = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[2] : '',
		undefined !== tabletMargin ? tabletMargin[2] : '',
		undefined !== mobileMargin ? mobileMargin[2] : ''
	);
	const previewMarginLeft = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[3] : '',
		undefined !== tabletMargin ? tabletMargin[3] : '',
		undefined !== mobileMargin ? mobileMargin[3] : ''
	);
	const previewPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[0] : '',
		undefined !== tabletPadding ? tabletPadding[0] : '',
		undefined !== mobilePadding ? mobilePadding[0] : ''
	);
	const previewPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[1] : '',
		undefined !== tabletPadding ? tabletPadding[1] : '',
		undefined !== mobilePadding ? mobilePadding[1] : ''
	);
	const previewPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[2] : '',
		undefined !== tabletPadding ? tabletPadding[2] : '',
		undefined !== mobilePadding ? mobilePadding[2] : ''
	);
	const previewPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== padding ? padding[3] : '',
		undefined !== tabletPadding ? tabletPadding[3] : '',
		undefined !== mobilePadding ? mobilePadding[3] : ''
	);
	const previewFontSize = getPreviewSize(
		previewDevice,
		undefined !== fontSize?.[0] ? fontSize[0] : '',
		undefined !== fontSize?.[1] ? fontSize[1] : '',
		undefined !== fontSize?.[2] ? fontSize[2] : ''
	);
	const previewLineHeight = getPreviewSize(
		previewDevice,
		undefined !== fontHeight?.[0] ? fontHeight[0] : '',
		undefined !== fontHeight?.[1] ? fontHeight[1] : '',
		undefined !== fontHeight?.[2] ? fontHeight[2] : ''
	);

	const previewLetterSpacing = getPreviewSize(
		previewDevice,
		undefined !== letterSpacing ? letterSpacing : '',
		undefined !== tabletLetterSpacing ? tabletLetterSpacing : '',
		undefined !== mobileLetterSpacing ? mobileLetterSpacing : ''
	);

	const previewAlign = getPreviewSize(
		previewDevice,
		undefined !== align ? align : '',
		undefined !== tabletAlign ? tabletAlign : '',
		undefined !== mobileAlign ? mobileAlign : ''
	);
	const previewColorTextShadow = getPreviewSize(
		previewDevice,
		undefined !== textShadow?.[0]?.color ? textShadow[0].color : '#000000',
		undefined !== textShadowTablet?.[0]?.color ? textShadowTablet[0].color : '',
		undefined !== textShadowMobile?.[0]?.color ? textShadowMobile[0].color : ''
	);
	function isRGBA(color) {
		const rgbaRegex = /rgba\(\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d*\.?\d+)\s*\)/;
		return rgbaRegex.test(color);
	}
	const previewTextShadowOpacity = getPreviewSize(
		previewDevice,
		undefined !== textShadow?.[0]?.opacity ? textShadow[0].opacity : 1,
		undefined !== textShadowTablet?.[0]?.opacity ? textShadowTablet[0].opacity : '',
		undefined !== textShadowMobile?.[0]?.opacity ? textShadowMobile[0].opacity : ''
	);
	const previewHOffset = getPreviewSize(
		previewDevice,
		undefined !== textShadow?.[0]?.hOffset ? textShadow[0].hOffset : 1,
		undefined !== textShadowTablet?.[0]?.hOffset ? textShadowTablet[0].hOffset : '',
		undefined !== textShadowMobile?.[0]?.hOffset ? textShadowMobile[0].hOffset : ''
	);
	const previewVOffset = getPreviewSize(
		previewDevice,
		undefined !== textShadow?.[0]?.vOffset ? textShadow[0].vOffset : 1,
		undefined !== textShadowTablet?.[0]?.vOffset ? textShadowTablet[0].vOffset : '',
		undefined !== textShadowMobile?.[0]?.vOffset ? textShadowMobile[0].vOffset : ''
	);
	const previewBlur = getPreviewSize(
		previewDevice,
		undefined !== textShadow?.[0]?.blur ? textShadow[0].blur : 1,
		undefined !== textShadowTablet?.[0]?.blur ? textShadowTablet[0].blur : '',
		undefined !== textShadowMobile?.[0]?.blur ? textShadowMobile[0].blur : ''
	);
	let previewJustifyAlign = previewAlign;
	switch (previewAlign) {
		case 'left':
			previewJustifyAlign = 'flex-start';
			break;
		case 'right':
			previewJustifyAlign = 'flex-end';
			break;
	}
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

	const previewIconSize = getPreviewSize(
		previewDevice,
		undefined !== iconSize?.[0] ? iconSize[0] : '',
		undefined !== iconSize?.[1] ? iconSize[1] : '',
		undefined !== iconSize?.[2] ? iconSize[2] : ''
	);
	const previewIconPaddingTop = getPreviewSize(
		previewDevice,
		undefined !== iconPadding?.[0] ? iconPadding[0] : '',
		undefined !== tabletIconPadding?.[0] ? tabletIconPadding[0] : '',
		undefined !== mobileIconPadding?.[0] ? mobileIconPadding[0] : ''
	);
	const previewIconPaddingRight = getPreviewSize(
		previewDevice,
		undefined !== iconPadding?.[1] ? iconPadding[1] : '',
		undefined !== tabletIconPadding?.[1] ? tabletIconPadding[1] : '',
		undefined !== mobileIconPadding?.[1] ? mobileIconPadding[1] : ''
	);
	const previewIconPaddingBottom = getPreviewSize(
		previewDevice,
		undefined !== iconPadding?.[2] ? iconPadding[2] : '',
		undefined !== tabletIconPadding?.[2] ? tabletIconPadding[2] : '',
		undefined !== mobileIconPadding?.[2] ? mobileIconPadding[2] : ''
	);
	const previewIconPaddingLeft = getPreviewSize(
		previewDevice,
		undefined !== iconPadding?.[3] ? iconPadding[3] : '',
		undefined !== tabletIconPadding?.[3] ? tabletIconPadding[3] : '',
		undefined !== mobileIconPadding?.[3] ? mobileIconPadding[3] : ''
	);

	const previewMarkLetterSpacing = getPreviewSize(
		previewDevice,
		undefined !== markLetterSpacing ? markLetterSpacing : '',
		undefined !== tabletMarkLetterSpacing ? tabletMarkLetterSpacing : '',
		undefined !== mobileMarkLetterSpacing ? mobileMarkLetterSpacing : ''
	);

	const previewMaxWidth = getPreviewSize(
		previewDevice,
		maxWidth && maxWidth[0] ? maxWidth[0] : '',
		maxWidth && maxWidth[1] ? maxWidth[1] : '',
		maxWidth && maxWidth[2] ? maxWidth[2] : ''
	);

	const previewMaxHeight = getPreviewSize(
		previewDevice,
		maxHeight && maxHeight[0] ? maxHeight[0] : '',
		maxHeight && maxHeight[1] ? maxHeight[1] : '',
		maxHeight && maxHeight[2] ? maxHeight[2] : ''
	);

	const previewInlineImageWidth = getPreviewSize(
		previewDevice,
		undefined !== inlineImageWidth && undefined !== inlineImageWidth[0] ? inlineImageWidth[0] : 150,
		undefined !== inlineImageWidth && undefined !== inlineImageWidth[1] ? inlineImageWidth[1] : '',
		undefined !== inlineImageWidth && undefined !== inlineImageWidth[2] ? inlineImageWidth[2] : ''
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

	const previewInlineImageBorderTopStyle = getBorderStyle(
		previewDevice,
		'top',
		inlineImageBorderStyles,
		tabletInlineImageBorderStyles,
		mobileInlineImageBorderStyles
	);
	const previewInlineImageBorderRightStyle = getBorderStyle(
		previewDevice,
		'right',
		inlineImageBorderStyles,
		tabletInlineImageBorderStyles,
		mobileInlineImageBorderStyles
	);
	const previewInlineImageBorderBottomStyle = getBorderStyle(
		previewDevice,
		'bottom',
		inlineImageBorderStyles,
		tabletInlineImageBorderStyles,
		mobileInlineImageBorderStyles
	);
	const previewInlineImageBorderLeftStyle = getBorderStyle(
		previewDevice,
		'left',
		inlineImageBorderStyles,
		tabletInlineImageBorderStyles,
		mobileInlineImageBorderStyles
	);

	const previewBorderTop = getBorderStyle(previewDevice, 'top', borderStyle, tabletBorderStyle, mobileBorderStyle);
	const previewBorderRight = getBorderStyle(
		previewDevice,
		'right',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderBottom = getBorderStyle(
		previewDevice,
		'bottom',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderLeft = getBorderStyle(previewDevice, 'left', borderStyle, tabletBorderStyle, mobileBorderStyle);
	const previewBorderTopColor = getBorderColor(
		previewDevice,
		'top',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderRightColor = getBorderColor(
		previewDevice,
		'right',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderBottomColor = getBorderColor(
		previewDevice,
		'bottom',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderLeftColor = getBorderColor(
		previewDevice,
		'left',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderDeclarations = Object.fromEntries(
		borderSideDeclarations(previewDevice, [borderStyle, tabletBorderStyle, mobileBorderStyle], {
			top: [previewBorderTop, previewBorderTopColor],
			right: [previewBorderRight, previewBorderRightColor],
			bottom: [previewBorderBottom, previewBorderBottomColor],
			left: [previewBorderLeft, previewBorderLeftColor],
		}).map(([property, value]) => [property.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase()), value])
	);

	const previewBorderRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[0] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[0] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[0] : ''
	);
	const previewBorderRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[1] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[1] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[1] : ''
	);
	const previewBorderRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[2] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[2] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[2] : ''
	);
	const previewBorderRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[3] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[3] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[3] : ''
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

	const previewInlineImageBorderRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== inlineImageBorderRadius ? inlineImageBorderRadius[0] : '',
		undefined !== tabletInlineImageBorderRadius ? tabletInlineImageBorderRadius[0] : '',
		undefined !== mobileInlineImageBorderRadius ? mobileInlineImageBorderRadius[0] : ''
	);
	const previewInlineImageBorderRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== inlineImageBorderRadius ? inlineImageBorderRadius[1] : '',
		undefined !== tabletInlineImageBorderRadius ? tabletInlineImageBorderRadius[1] : '',
		undefined !== mobileInlineImageBorderRadius ? mobileInlineImageBorderRadius[1] : ''
	);
	const previewInlineImageBorderRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== inlineImageBorderRadius ? inlineImageBorderRadius[2] : '',
		undefined !== tabletInlineImageBorderRadius ? tabletInlineImageBorderRadius[2] : '',
		undefined !== mobileInlineImageBorderRadius ? mobileInlineImageBorderRadius[2] : ''
	);
	const previewInlineImageBorderRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== inlineImageBorderRadius ? inlineImageBorderRadius[3] : '',
		undefined !== tabletInlineImageBorderRadius ? tabletInlineImageBorderRadius[3] : '',
		undefined !== mobileInlineImageBorderRadius ? mobileInlineImageBorderRadius[3] : ''
	);
	let previewInlineImageAspectRatio = '';
	if (useRatio && ratio) {
		switch (ratio) {
			case 'land43':
				previewInlineImageAspectRatio = '4 / 3';
				break;
			case 'land32':
				previewInlineImageAspectRatio = '3 / 2';
				break;
			case 'land169':
				previewInlineImageAspectRatio = '16 / 9';
				break;
			case 'land21':
				previewInlineImageAspectRatio = '2 / 1';
				break;
			case 'land31':
				previewInlineImageAspectRatio = '3 / 1';
				break;
			case 'land41':
				previewInlineImageAspectRatio = '4 / 1';
				break;
			case 'port34':
				previewInlineImageAspectRatio = '3 / 4';
				break;
			case 'port23':
				previewInlineImageAspectRatio = '2 / 3';
				break;
			case 'port916':
				previewInlineImageAspectRatio = '9 / 16';
				break;
			case 'square':
				previewInlineImageAspectRatio = '1 / 1';
				break;
		}
	}
	const previewTextOrientation = getPreviewSize(
		previewDevice,
		undefined !== textOrientation ? textOrientation : '',
		undefined !== tabletTextOrientation ? tabletTextOrientation : '',
		undefined !== mobileTextOrientation ? mobileTextOrientation : ''
	);
	const markBorderRadiusUnitPreview = undefined !== markBorderRadiusUnit ? markBorderRadiusUnit : 'px';
	let backgroundIgnoreClass = backgroundColorClass ? false : true;
	if (!backgroundIgnoreClass && !flxbk_params.isFlxbkT && background && background.startsWith('palette')) {
		backgroundIgnoreClass = true;
	}
	const headingOptions = [
		[
			{
				icon: (
					<HeadingLevelIcon
						level={1}
						isPressed={1 === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: __('Heading 1', 'flx-blocks'),
				isActive: 1 === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => setAttributes({ level: 1, htmlTag: 'heading' }),
			},
		],
		[
			{
				icon: (
					<HeadingLevelIcon
						level={2}
						isPressed={2 === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: __('Heading 2', 'flx-blocks'),
				isActive: 2 === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => setAttributes({ level: 2, htmlTag: 'heading' }),
			},
		],
		[
			{
				icon: (
					<HeadingLevelIcon
						level={3}
						isPressed={3 === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: __('Heading 3', 'flx-blocks'),
				isActive: 3 === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => setAttributes({ level: 3, htmlTag: 'heading' }),
			},
		],
		[
			{
				icon: (
					<HeadingLevelIcon
						level={4}
						isPressed={4 === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: __('Heading 4', 'flx-blocks'),
				isActive: 4 === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => setAttributes({ level: 4, htmlTag: 'heading' }),
			},
		],
		[
			{
				icon: (
					<HeadingLevelIcon
						level={5}
						isPressed={5 === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: __('Heading 5', 'flx-blocks'),
				isActive: 5 === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => setAttributes({ level: 5, htmlTag: 'heading' }),
			},
		],
		[
			{
				icon: (
					<HeadingLevelIcon
						level={6}
						isPressed={6 === level && htmlTag && htmlTag === 'heading' ? true : false}
					/>
				),
				title: __('Heading 6', 'flx-blocks'),
				isActive: 6 === level && htmlTag && htmlTag === 'heading' ? true : false,
				onClick: () => setAttributes({ level: 6, htmlTag: 'heading' }),
			},
		],
		[
			{
				icon: <HeadingLevelIcon level={'p'} isPressed={htmlTag && htmlTag === 'p' ? true : false} />,
				title: __('Paragraph', 'flx-blocks'),
				isActive: htmlTag && htmlTag === 'p' ? true : false,
				onClick: () => setAttributes({ htmlTag: 'p' }),
			},
		],
		[
			{
				icon: <HeadingLevelIcon level={'span'} isPressed={htmlTag && htmlTag === 'span' ? true : false} />,
				title: __('Span', 'flx-blocks'),
				isActive: htmlTag && htmlTag === 'span' ? true : false,
				onClick: () => setAttributes({ htmlTag: 'span' }),
			},
		],
		[
			{
				icon: <HeadingLevelIcon level={'div'} isPressed={htmlTag && htmlTag === 'div' ? true : false} />,
				title: __('div', 'flx-blocks'),
				isActive: htmlTag && htmlTag === 'div' ? true : false,
				onClick: () => setAttributes({ htmlTag: 'div' }),
			},
		],
	];

	const classes = classnames({
		[`fxt-adv-heading${uniqueID}`]: uniqueID,
		'flxbk-advancedheading-text': true,
		'fxb-content-is-dynamic': isDynamicReplaced,
		[textColorClass]: textColorClass,
		'has-text-color': textColorClass,
		[textBackgroundColorClass]: textBackgroundColorClass,
		'has-background': textBackgroundColorClass,
		[`hls-${linkStyle}`]: !link && linkStyle,
		[`fxt-adv-heading-has-icon`]: icon,
	});
	const renderIcon = () => {
		if (!icon) {
			return null;
		}

		return (
			<Tooltip
				text={iconTooltip ? iconTooltip : undefined}
				className="fxb-custom-tooltip"
				placement={iconTooltipPlacement || 'top'}
			>
				<span
					className={`fxb-advanced-heading-icon-wrap${iconTooltipDash ? ' fxb-adv-text-icon-dash' : ''}`}
					style={{
						display: 'inline-flex',
						justifyContent: 'center',
						alignItems: 'center',
					}}
				>
					<IconRender
						className={`fxb-advanced-heading-svg-icon fxb-advanced-heading-svg-icon-${icon} fxb-advanced-heading-icon-side-${iconSide}`}
						name={icon}
						size={'1em'}
						style={{
							fontSize: previewIconSize
								? getFontSizeOptionOutput(
										previewIconSize,
										undefined !== iconSizeUnit ? iconSizeUnit : 'px'
									)
								: undefined,
							color: '' !== iconColor ? FlxbkColorOutput(iconColor) : undefined,
							paddingTop: previewIconPaddingTop
								? getSpacingOptionOutput(previewIconPaddingTop, iconPaddingUnit)
								: undefined,
							paddingRight: previewIconPaddingRight
								? getSpacingOptionOutput(previewIconPaddingRight, iconPaddingUnit)
								: undefined,
							paddingBottom: previewIconPaddingBottom
								? getSpacingOptionOutput(previewIconPaddingBottom, iconPaddingUnit)
								: undefined,
							paddingLeft: previewIconPaddingLeft
								? getSpacingOptionOutput(previewIconPaddingLeft, iconPaddingUnit)
								: undefined,
						}}
					/>
				</span>
			</Tooltip>
		);
	};

	const headingContent = (
		<TagHTML
			className={classes}
			data-alt-title={altTitle ? altTitle : undefined}
			style={{
				display: icon ? 'flex' : undefined,
				alignItems: icon ? iconVerticalAlign : undefined,
				gap: icon ? '0.25em' : undefined,
				justifyContent: icon && previewJustifyAlign ? previewJustifyAlign : undefined,
				textAlign: previewAlign ? previewAlign : undefined,
				backgroundColor:
					!enableTextGradient && background && backgroundIgnoreClass
						? FlxbkColorOutput(background)
						: undefined,
				backgroundImage: enableTextGradient && textGradient !== '' ? textGradient : undefined,
				paddingTop:
					'' !== previewPaddingTop ? getSpacingOptionOutput(previewPaddingTop, paddingType) : undefined,
				paddingRight:
					'' !== previewPaddingRight ? getSpacingOptionOutput(previewPaddingRight, paddingType) : undefined,
				paddingBottom:
					'' !== previewPaddingBottom ? getSpacingOptionOutput(previewPaddingBottom, paddingType) : undefined,
				paddingLeft:
					'' !== previewPaddingLeft ? getSpacingOptionOutput(previewPaddingLeft, paddingType) : undefined,
				marginTop: '' !== previewMarginTop ? getSpacingOptionOutput(previewMarginTop, marginType) : undefined,
				marginRight:
					'' !== previewMarginRight ? getSpacingOptionOutput(previewMarginRight, marginType) : undefined,
				marginBottom:
					'' !== previewMarginBottom ? getSpacingOptionOutput(previewMarginBottom, marginType) : undefined,
				marginLeft:
					'' !== previewMarginLeft ? getSpacingOptionOutput(previewMarginLeft, marginType) : undefined,
				lineHeight: previewLineHeight ? previewLineHeight + (fontHeightType ? fontHeightType : '') : undefined,
				color: color && (!enableTextGradient || textGradient !== '') ? FlxbkColorOutput(color) : undefined,
				fontSize: previewFontSize
					? getFontSizeOptionOutput(previewFontSize, sizeType ? sizeType : 'px')
					: undefined,
				borderTopLeftRadius: tokenDimension(previewBorderRadiusTop, borderRadiusUnit),
				borderTopRightRadius: tokenDimension(previewBorderRadiusRight, borderRadiusUnit),
				borderBottomRightRadius: tokenDimension(previewBorderRadiusBottom, borderRadiusUnit),
				borderBottomLeftRadius: tokenDimension(previewBorderRadiusLeft, borderRadiusUnit),
				...previewBorderDeclarations,
			}}
		>
			{iconSide === 'left' && renderIcon()}

			{!isDynamicReplaced && (
				<RichText
					id={'adv-heading' + uniqueID}
					tagName="span"
					className={'fxb-adv-heading-inner'}
					allowedFormats={richTextFormats}
					value={content}
					onChange={(value) => setAttributes({ content: value })}
					onMerge={mergeBlocks}
					onSplit={(value) => {
						if (!value && !isDefaultEditorBlock) {
							return createBlock('core/paragraph');
						}
						return createBlock('flx/advancedheading', {
							...select('core/block-editor').getBlockAttributes(clientId),
							content: value ?? '',
						});
					}}
					onReplace={onReplace}
					onRemove={() => onReplace([])}
					style={{
						fontWeight,
						fontStyle,
						letterSpacing: previewLetterSpacing
							? previewLetterSpacing + (letterSpacingType ? letterSpacingType : 'px')
							: undefined,
						textTransform: textTransform ? textTransform : undefined,
						fontFamily: effectiveTypography ? renderTypography : '',
						textShadow: enableTextShadow
							? `${previewHOffset}px ${previewVOffset}px ${previewBlur}px ${
									isRGBA(previewColorTextShadow)
										? previewColorTextShadow // If rgba, use the color as is
										: FlxbkColorOutput(previewColorTextShadow, previewTextShadowOpacity) // Otherwise, apply opacity
								}`
							: undefined,

						writingMode:
							previewTextOrientation === 'stacked' || previewTextOrientation === 'sideways-down'
								? 'vertical-lr'
								: previewTextOrientation === 'sideways-up'
									? 'sideways-lr'
									: '',
						textOrientation: previewTextOrientation === 'stacked' ? 'upright' : '',
						maxHeight: textOrientation !== 'horizontal' && textOrientation !== '' ? previewMaxHeight : '',
					}}
					placeholder={__('Write something…', 'flx-blocks')}
					isSelected={isSelected}
					onPaste={handlePaste}
				/>
			)}
			{isDynamicReplaced && (
				<>
					{applyFilters('flxbk.dynamicContent', <Spinner />, attributes, 'content', setAttributes, context)}
				</>
			)}

			{iconSide === 'right' && renderIcon()}
		</TagHTML>
	);

	const headingLinkContent = (
		<a
			href={link}
			className={`fxb-advanced-heading-link${linkStyle ? ' hls-' + linkStyle : ''}`}
			onClick={(event) => {
				event.preventDefault();
			}}
		>
			{headingContent}
		</a>
	);
	const wrapperClasses = classnames({
		'fxb-is-heading': htmlTag && htmlTag === 'heading',
		'fxb-adv-text': true,
	});
	const nonTransAttrs = ['content', 'altTitle'];
	const blockProps = useBlockProps({
		className: wrapperClasses,
	});

	function sanitizeString(input) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#x27;',
			'/': '&#x2F;',
		};
		const reg = /[&<>"'/]/gi;
		return input.replace(reg, (match) => map[match]);
	}

	const typed = useRef(null);
	useEffect(() => {
		if (!isSelected && undefined !== attributes.content && attributes.content.includes('fxt-typed-text')) {
			const parser = new DOMParser();
			const contentHtml = parser.parseFromString(attributes.content, 'text/html');

			if (contentHtml.querySelectorAll('.fxt-typed-text')[0]) {
				const typedElement = contentHtml.querySelectorAll('.fxt-typed-text')[0];
				const dataStrings = typedElement.getAttribute('data-strings');
				const strings = dataStrings ? JSON.parse(dataStrings.replaceAll('&', '&amp;')) : [];

				// Adding the default/existing string twice is required for displaying properly
				strings.unshift(typedElement.textContent.replaceAll('&', '&amp;'));
				strings.unshift(typedElement.textContent.replaceAll('&', '&amp;'));

				const options = {
					strings,
					cursorChar: typedElement.getAttribute('data-cursor-char')
						? sanitizeString(typedElement.getAttribute('data-cursor-char'))
						: '_',
					startDelay: typedElement.getAttribute('data-start-delay')
						? parseInt(typedElement.getAttribute('data-start-delay'))
						: 0,
					backDelay: typedElement.getAttribute('data-back-delay')
						? parseInt(typedElement.getAttribute('data-back-delay'))
						: 700,
					typeSpeed: typedElement.getAttribute('data-type-speed')
						? parseInt(typedElement.getAttribute('data-type-speed'))
						: 40,
					backSpeed: typedElement.getAttribute('data-back-speed')
						? parseInt(typedElement.getAttribute('data-back-speed'))
						: 0,
					smartBackspace: typedElement.getAttribute('data-smart-backspace') === 'true',
					loop: typedElement.getAttribute('data-loop') !== 'false',
					loopCount: false,
					showCursor: typedElement.getAttribute('data-cursor-char') !== '',
					shuffle: typedElement.getAttribute('data-shuffle') === 'true',
				};

				const iFrameSelector = document.getElementsByName('editor-canvas');
				const selector =
					iFrameSelector.length > 0
						? document.getElementsByName('editor-canvas')[0].contentWindow.document
						: document;
				const typedElementHtml = selector
					.getElementById('adv-heading' + uniqueID)
					.querySelector('.fxt-typed-text');

				typed.current = new Typed(typedElementHtml, options);
			}

			return function cleanup() {
				// Destroy the typed instance and reset richtext content
				typed.current.destroy();

				const iFrameSelector = document.getElementsByName('editor-canvas');
				const selector =
					iFrameSelector.length > 0
						? document.getElementsByName('editor-canvas')[0].contentWindow.document
						: document;
				if (selector.getElementById('adv-heading' + uniqueID)) {
					selector.getElementById('adv-heading' + uniqueID).innerHTML = attributes.content;
				}
			};
		}
	}, [isSelected]);
	return (
		<div {...blockProps}>
			<style>
				{`.fxt-adv-heading${uniqueID} mark.fxt-highlight, .fxt-adv-heading${uniqueID} .rich-text:focus mark.fxt-highlight[data-rich-text-format-boundary] {
						color: ${!enableMarkGradient ? FlxbkColorOutput(markColor) : 'transparent'};
						background: ${markBG && !enableMarkGradient ? markBGString : 'transparent'};
						background-image: ${enableMarkGradient ? markGradient : enableMarkBackgroundGradient ? markBackgroundGradient : 'none'};
						-webkit-background-clip: ${enableMarkGradient ? 'text' : enableTextGradient ? 'initial !important' : undefined};
						background-clip: ${enableMarkGradient ? 'text' : enableTextGradient ? 'initial !important' : undefined};
						-webkit-text-fill-color: ${enableMarkGradient ? 'transparent' : enableTextGradient ? 'initial !important' : undefined};
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
				{`.fxt-adv-heading${uniqueID} img.fxb-inline-image {
						width: ${previewInlineImageWidth}px;
						vertical-align: ${inlineImageVerticalAlign || 'baseline'};
						border-top: ${previewInlineImageBorderTopStyle ? previewInlineImageBorderTopStyle : 'inherit'};
						border-right: ${previewInlineImageBorderRightStyle ? previewInlineImageBorderRightStyle : 'inherit'};
						border-bottom: ${previewInlineImageBorderBottomStyle ? previewInlineImageBorderBottomStyle : 'inherit'};
						border-left: ${previewInlineImageBorderLeftStyle ? previewInlineImageBorderLeftStyle : 'inherit'};
						${'' !== previewInlineImageBorderRadiusTop ? 'border-top-left-radius:' + previewInlineImageBorderRadiusTop + (inlineImageBorderRadiusUnit || 'px') + ';' : ''}
						${'' !== previewInlineImageBorderRadiusRight ? 'border-top-right-radius:' + previewInlineImageBorderRadiusRight + (inlineImageBorderRadiusUnit || 'px') + ';' : ''}
						${'' !== previewInlineImageBorderRadiusBottom ? 'border-bottom-right-radius:' + previewInlineImageBorderRadiusBottom + (inlineImageBorderRadiusUnit || 'px') + ';' : ''}
						${'' !== previewInlineImageBorderRadiusLeft ? 'border-bottom-left-radius:' + previewInlineImageBorderRadiusLeft + (inlineImageBorderRadiusUnit || 'px') + ';' : ''}
						${previewInlineImageAspectRatio ? 'aspect-ratio: ' + previewInlineImageAspectRatio + ';' : ''}
						${previewInlineImageAspectRatio ? 'object-fit: cover;' : ''}
					}`}
				{previewMaxWidth
					? `.editor-styles-wrapper *:not(.flxbk-inner-column-direction-horizontal) > .wp-block-flx-advancedheading .fxt-adv-heading${uniqueID}, .editor-styles-wrapper .flxbk-inner-column-direction-horizontal > .wp-block-flx-advancedheading[data-block="${clientId}"] { max-width:${
							previewMaxWidth + (maxWidthType ? maxWidthType : 'px')
						} !important; }`
					: ''}
				{previewMaxWidth && previewAlign === 'center'
					? `.editor-styles-wrapper *:not(.flxbk-inner-column-direction-horizontal) > .wp-block-flx-advancedheading .fxt-adv-heading${uniqueID}, .editor-styles-wrapper .flxbk-inner-column-direction-horizontal > .wp-block-flx-advancedheading[data-block="${clientId}"] { margin-left: auto; margin-right:auto; }`
					: ''}
				{previewMaxWidth && previewAlign === 'right'
					? `.editor-styles-wrapper *:not(.flxbk-inner-column-direction-horizontal) > .wp-block-flx-advancedheading .fxt-adv-heading${uniqueID}, .editor-styles-wrapper .flxbk-inner-column-direction-horizontal > .wp-block-flx-advancedheading[data-block="${clientId}"] { margin-left: auto; margin-right:0; }`
					: ''}
				{linkStyle &&
					`.fxt-adv-heading${uniqueID} a, #block-${clientId} a {
							${linkStyle === 'underline' ? 'text-decoration: underline;' : ''}
							${linkStyle === 'none' ? 'text-decoration: none;' : ''}
					}
					.fxt-adv-heading${uniqueID} a, #block-${clientId} a:hover {
							${linkStyle === 'hover_underline' ? 'text-decoration: underline;' : ''}
					}
					`}
				{linkColor &&
					`.fxt-adv-heading${uniqueID} a, #block-${clientId} a.fxb-advanced-heading-link, #block-${clientId} a.fxb-advanced-heading-link > .flxbk-advancedheading-text {
							color: ${FlxbkColorOutput(linkColor)} !important;
						}`}
				{linkHoverColor &&
					`.fxt-adv-heading${uniqueID} a:hover, #block-${clientId} a.fxb-advanced-heading-link:hover, #block-${clientId} a.fxb-advanced-heading-link:hover > .flxbk-advancedheading-text {
							color: ${FlxbkColorOutput(linkHoverColor)}!important;
						}`}
				{enableTextGradient &&
					textGradient !== '' &&
					`.fxt-adv-heading${uniqueID}.flxbk-advancedheading-text, .fxt-adv-heading${uniqueID} .flxbk-advancedheading-text {
						-webkit-background-clip: text;
						background-clip: text;
						-webkit-text-fill-color: transparent;
						-webkit-box-decoration-break: clone;
				}`}
				{iconColorHover &&
					`#block-${clientId} .flxbk-advancedheading-text:hover > .fxb-advanced-heading-svg-icon {
							color: ${FlxbkColorOutput(iconColorHover)}!important;
						}`}
				{enableTextGradient &&
					`.fxt-adv-heading${uniqueID} > span#adv-heading${uniqueID} {
						background-image: ${textGradient};
						-webkit-background-clip: text;
						background-clip: text;
						-webkit-text-fill-color: transparent;
						-webkit-box-decoration-break: clone;
						box-decoration-break: clone;
						display: inline;
					}`}
				{/* This style is used to override the default color which applied to already saved color palette with root css variable */}
				{color &&
					colorClass?.includes('fxb-palette') &&
					(!textGradient || textGradient === '') &&
					`.has-${colorClass}-color{
						color: ${color} !important;
					}`}
			</style>
			<BlockControls>
				<ToolbarGroup group="tag">
					<ToolbarDropdownMenu
						icon={<HeadingLevelIcon level={htmlTag !== 'heading' ? htmlTag : level} />}
						label={__('Change heading tag', 'flx-blocks')}
						controls={headingOptions}
					/>
				</ToolbarGroup>
				{showSettings('allSettings', 'flx/advancedheading') &&
					showSettings('toolbarTypography', 'flx/advancedheading', false) && (
						<InlineTypographyControls
							uniqueID={uniqueID}
							fontGroup={'heading'}
							letterSpacing={letterSpacing}
							onLetterSpacing={(value) => setAttributes({ letterSpacing: value })}
							fontFamily={typography}
							onFontFamily={(value) => setAttributes({ typography: value })}
							context={{ blockName: 'flx/advancedheading' }}
							onFontChange={(select) => {
								setAttributes({
									typography: select.value,
									googleFont: select.google,
								});
							}}
							googleFont={googleFont}
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
							fontSizeArray={false}
							fontSizeType={sizeType}
							onFontSizeType={(value) => setAttributes({ sizeType: value })}
							lineHeightType={fontHeightType}
							onLineHeightType={(value) => setAttributes({ fontHeightType: value })}
							fontSize={undefined !== fontSize?.[0] ? fontSize[0] : ''}
							onFontSize={(value) =>
								setAttributes({
									fontSize: [
										value,
										undefined !== fontSize[1] ? fontSize[1] : '',
										undefined !== fontSize[2] ? fontSize[2] : '',
									],
								})
							}
							tabSize={undefined !== fontSize?.[1] ? fontSize[1] : ''}
							onTabSize={(value) =>
								setAttributes({
									fontSize: [
										undefined !== fontSize[0] ? fontSize[0] : '',
										value,
										undefined !== fontSize[2] ? fontSize[2] : '',
									],
								})
							}
							mobileSize={undefined !== fontSize?.[2] ? fontSize[2] : ''}
							onMobileSize={(value) =>
								setAttributes({
									fontSize: [
										undefined !== fontSize[0] ? fontSize[0] : '',
										undefined !== fontSize[1] ? fontSize[1] : '',
										value,
									],
								})
							}
							lineHeight={undefined !== fontHeight?.[0] ? fontHeight[0] : ''}
							onLineHeight={(value) =>
								setAttributes({
									fontHeight: [
										value,
										undefined !== fontHeight[1] ? fontHeight[1] : '',
										undefined !== fontHeight[2] ? fontHeight[2] : '',
									],
								})
							}
							tabLineHeight={undefined !== fontHeight?.[1] ? fontHeight[1] : ''}
							onTabLineHeight={(value) =>
								setAttributes({
									fontHeight: [
										undefined !== fontHeight[0] ? fontHeight[0] : '',
										value,
										undefined !== fontHeight[2] ? fontHeight[2] : '',
									],
								})
							}
							mobileLineHeight={undefined !== fontHeight?.[2] ? fontHeight[2] : ''}
							onMobileLineHeight={(value) =>
								setAttributes({
									fontHeight: [
										undefined !== fontHeight[0] ? fontHeight[0] : '',
										undefined !== fontHeight[1] ? fontHeight[1] : '',
										value,
									],
								})
							}
						/>
					)}
				{showSettings('allSettings', 'flx/advancedheading') &&
					showSettings('toolbarColor', 'flx/advancedheading', false) && (
						/* Left as-is while the inspector's Color moved to `ColorControl`. The toolbar is not
						   token-blind: it routes through the same `flxbk.components.popColorControl.colors`
						   filter, so a palette pick here writes the same `{alias}` the inspector would. What it
						   lacks is the Style Library picker and the binding indicator, which do not fit a
						   toolbar popover. Only an explicit Custom pick here replaces a token, which is the
						   same thing that control has always done. */
						<InlinePopColorControl
							label={__('Color', 'flx-blocks')}
							value={color ? color : ''}
							default={''}
							onChange={(value) => setAttributes({ color: value, colorClass: '' })}
							onClassChange={(value) => setAttributes({ colorClass: value })}
						/>
					)}
				<AlignmentToolbar
					value={align}
					onChange={(nextAlign) => {
						setAttributes({ align: nextAlign });
					}}
				/>
				<CopyPasteAttributes
					attributes={attributes}
					excludedAttrs={nonTransAttrs}
					defaultAttributes={metadata.attributes}
					blockSlug={metadata.name}
					onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
				/>
				{Boolean(flxbkDynamic?.content?.shouldReplace) && (
					<DynamicTextControl dynamicAttribute={'content'} {...props} />
				)}
			</BlockControls>
			{showSettings('allSettings', 'flx/advancedheading') && (
				<InspectorControls>
					<InspectorControlTabs
						panelName={'advancedheading'}
						initialOpen={'style'}
						setActiveTab={(value) => setActiveTab(value)}
						activeTab={activeTab}
					/>

					{activeTab === 'general' && (
						<>
							<FlxbkPanelBody panelName={'fxb-adv-heading-general-settings'}>
								{!isSiteStylesPanel && (
									<TagSelect
										label={__('HTML Tag', 'flx-blocks')}
										value={'heading' === htmlTag ? level : htmlTag}
										onChange={(value) => {
											if ('div' === value || 'p' === value || 'span' === value) {
												setAttributes({ level: 2, htmlTag: value });
											} else {
												setAttributes({ level: value, htmlTag: 'heading' });
											}
										}}
									/>
								)}
								<ResponsiveAlignControls
									label={__('Text Alignment', 'flx-blocks')}
									value={align ? align : ''}
									mobileValue={mobileAlign ? mobileAlign : ''}
									tabletValue={tabletAlign ? tabletAlign : ''}
									onChange={(nextAlign) => setAttributes({ align: nextAlign })}
									onChangeTablet={(nextAlign) => setAttributes({ tabletAlign: nextAlign })}
									onChangeMobile={(nextAlign) => setAttributes({ mobileAlign: nextAlign })}
								/>
								<ResponsiveRangeControls
									reset={() => {
										setAttributes({
											maxWidth: ['', '', ''],
											maxWidthType: 'px',
										});
									}}
									label={__('Max Width', 'flx-blocks')}
									value={undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : ''}
									onChange={(value) => {
										setAttributes({
											maxWidth: [
												value,
												undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : '',
												undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : '',
											],
										});
									}}
									tabletValue={undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : ''}
									onChangeTablet={(value) => {
										setAttributes({
											maxWidth: [
												undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : '',
												value,
												undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : '',
											],
										});
									}}
									mobileValue={undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : ''}
									onChangeMobile={(value) => {
										setAttributes({
											maxWidth: [
												undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : '',
												undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : '',
												value,
											],
										});
									}}
									min={0}
									max={maxWidthType === 'px' ? 2000 : 100}
									step={1}
									unit={maxWidthType ? maxWidthType : 'px'}
									onUnit={(value) => {
										setAttributes({ maxWidthType: value });
									}}
									units={['px', '%', 'vw']}
								/>
							</FlxbkPanelBody>
							{showSettings('linkSettings', 'flx/advancedheading') && (
								<FlxbkPanelBody
									title={__('Link Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-adv-heading-link-settings'}
								>
									<PopColorControl
										label={__('Link Color', 'flx-blocks')}
										swatchLabel={__('Link Color', 'flx-blocks')}
										value={linkColor ? linkColor : ''}
										default={''}
										onChange={(value) => setAttributes({ linkColor: value })}
										swatchLabel2={__('Hover Color', 'flx-blocks')}
										value2={linkHoverColor ? linkHoverColor : ''}
										default2={''}
										onChange2={(value) => setAttributes({ linkHoverColor: value })}
									/>
									<SelectControl
										label={__('Link Style', 'flx-blocks')}
										value={linkStyle}
										options={[
											{ value: '', label: __('Unset', 'flx-blocks') },
											{ value: 'none', label: __('None', 'flx-blocks') },
											{ value: 'underline', label: __('Underline', 'flx-blocks') },
											{
												value: 'hover_underline',
												label: __('Underline on Hover', 'flx-blocks'),
											},
										]}
										onChange={(value) => setAttributes({ linkStyle: value })}
									/>
									{!isSiteStylesPanel && (
										<URLInputControl
											label={__('Text Wrap Link', 'flx-blocks')}
											url={link}
											onChangeUrl={(value) => setAttributes({ link: value })}
											additionalControls={true}
											opensInNewTab={undefined !== linkTarget ? linkTarget : false}
											onChangeTarget={(value) => setAttributes({ linkTarget: value })}
											linkNoFollow={undefined !== linkNoFollow ? linkNoFollow : false}
											onChangeFollow={(value) => setAttributes({ linkNoFollow: value })}
											linkSponsored={undefined !== linkSponsored ? linkSponsored : false}
											onChangeSponsored={(value) => setAttributes({ linkSponsored: value })}
											dynamicAttribute={'link'}
											allowClear={true}
											{...props}
										/>
									)}
								</FlxbkPanelBody>
							)}
						</>
					)}

					{activeTab === 'style' && (
						<>
							<FlxbkPanelBody panelName={'fxb-adv-heading-style'}>
								{showSettings('colorSettings', 'flx/advancedheading') && (
									<>
										{!enableTextGradient && (
											<EditorColorControlGroup>
												{/* Every write clears the matching `*Class` attribute. Those carry
												    legacy global-palette CLASSES the heading emits on its own
												    element, so they beat the color these controls write and a stale
												    one would silently discard the pick. `PopColorControl` set them
												    itself through `onClassChange`; a palette color is a token alias
												    now, so nothing sets them again. */}
												<ColorControl
													label={__('Color', 'flx-blocks')}
													value={color ? color : ''}
													groups={colorGroups}
													status={{
														bound: !!tokenBinding.color?.bound,
														modified: !!tokenBinding.color?.overridden,
													}}
													onReset={() => resetToken('color')}
													onPick={(alias) => setAttributes({ color: alias, colorClass: '' })}
													onCustom={(literal) =>
														setAttributes({ color: literal, colorClass: '' })
													}
													onClear={() => setAttributes({ color: '', colorClass: '' })}
													resolveLiteral={resolveColorLiteral}
												/>
												<ColorControl
													label={__('Background Color', 'flx-blocks')}
													value={background ? background : ''}
													groups={colorGroups}
													status={{
														bound: !!tokenBinding.background?.bound,
														modified: !!tokenBinding.background?.overridden,
													}}
													onReset={() => resetToken('background')}
													onPick={(alias) =>
														setAttributes({
															background: alias,
															backgroundColorClass: '',
														})
													}
													onCustom={(literal) =>
														setAttributes({
															background: literal,
															backgroundColorClass: '',
														})
													}
													onClear={() =>
														setAttributes({ background: '', backgroundColorClass: '' })
													}
													resolveLiteral={resolveColorLiteral}
												/>
											</EditorColorControlGroup>
										)}
										<ToggleControl
											style={{ marginTop: '10px' }}
											label={__('Enable Text Gradient', 'flx-blocks')}
											checked={enableTextGradient}
											onChange={(value) => setAttributes({ enableTextGradient: value })}
										/>

										{enableTextGradient && (
											<GradientControl
												value={textGradient}
												onChange={(value) => setAttributes({ textGradient: value })}
												gradients={[]}
											/>
										)}
									</>
								)}
								{showSettings('sizeSettings', 'flx/advancedheading') && (
									<>
										{fontSizeTokens.length ? (
											<EditorScalarControl
												label={__('Font Size', 'flx-blocks')}
												value={fontSizeValue}
												onChange={writeFontSize}
												previewDevice={previewDevice}
												onDeviceChange={setPreviewDevice}
												tokens={fontSizeTokens}
												defaultValue={inheritedFontSize ?? fontSizePresetValue}
												inherited={inheritedFontSize !== undefined}
												state={tokenBinding.fontSize}
												onReset={() => resetToken('fontSize')}
												unit={sizeType ? sizeType : 'px'}
												units={['px', 'em', 'rem', 'vw']}
												onUnit={(value) => setAttributes({ sizeType: value })}
												min={0}
												max={sizeType === 'px' ? 200 : 12}
												step={sizeType === 'px' ? 1 : 0.001}
											/>
										) : (
											<ResponsiveFontSizeControl
												label={__('Font Size', 'flx-blocks')}
												value={undefined !== fontSize?.[0] ? fontSize[0] : ''}
												onChange={(value) =>
													setAttributes({
														fontSize: [
															value,
															undefined !== fontSize[1] ? fontSize[1] : '',
															undefined !== fontSize[2] ? fontSize[2] : '',
														],
													})
												}
												tabletValue={undefined !== fontSize?.[1] ? fontSize[1] : ''}
												onChangeTablet={(value) =>
													setAttributes({
														fontSize: [
															undefined !== fontSize[0] ? fontSize[0] : '',
															value,
															undefined !== fontSize[2] ? fontSize[2] : '',
														],
													})
												}
												mobileValue={undefined !== fontSize?.[2] ? fontSize[2] : ''}
												onChangeMobile={(value) =>
													setAttributes({
														fontSize: [
															undefined !== fontSize[0] ? fontSize[0] : '',
															undefined !== fontSize[1] ? fontSize[1] : '',
															value,
														],
													})
												}
												min={0}
												max={sizeType === 'px' ? 200 : 12}
												step={sizeType === 'px' ? 1 : 0.001}
												unit={sizeType ? sizeType : 'px'}
												onUnit={(value) => {
													setAttributes({ sizeType: value });
												}}
												units={['px', 'em', 'rem', 'vw']}
											/>
										)}
										<div className="fxb-advanced-heading-font-settings">
											<TwoColumn className="fxb-font-settings">
												<ResponsiveUnitControl
													label={__('Line Height', 'flx-blocks')}
													value={undefined !== fontHeight?.[0] ? fontHeight[0] : ''}
													onChange={(value) =>
														setAttributes({
															fontHeight: [
																value,
																undefined !== fontHeight[1] ? fontHeight[1] : '',
																undefined !== fontHeight[2] ? fontHeight[2] : '',
															],
														})
													}
													tabletValue={undefined !== fontHeight?.[1] ? fontHeight[1] : ''}
													onChangeTablet={(value) =>
														setAttributes({
															fontHeight: [
																undefined !== fontHeight[0] ? fontHeight[0] : '',
																value,
																undefined !== fontHeight[2] ? fontHeight[2] : '',
															],
														})
													}
													mobileValue={undefined !== fontHeight?.[2] ? fontHeight[2] : ''}
													onChangeMobile={(value) =>
														setAttributes({
															fontHeight: [
																undefined !== fontHeight[0] ? fontHeight[0] : '',
																undefined !== fontHeight[1] ? fontHeight[1] : '',
																value,
															],
														})
													}
													min={0}
													max={fontHeightType === 'px' ? 200 : 12}
													step={fontHeightType === 'px' ? 1 : 0.1}
													unit={fontHeightType ? fontHeightType : ''}
													onUnit={(value) => setAttributes({ fontHeightType: value })}
													units={['-', 'px', 'em', 'rem']}
													compressedDevice={true}
												/>
												<FlxbkRadioButtons
													label={__('Letter Case', 'flx-blocks')}
													value={textTransform}
													className={'fxb-letter-case'}
													options={[
														{
															value: 'none',
															label: __('-', 'flx-blocks'),
															tooltip: __('None', 'flx-blocks'),
														},
														{
															value: 'uppercase',
															label: __('AB', 'flx-blocks'),
															tooltip: __('Uppercase', 'flx-blocks'),
														},
														{
															value: 'lowercase',
															label: __('ab', 'flx-blocks'),
															tooltip: __('Lowercase', 'flx-blocks'),
														},
														{
															value: 'capitalize',
															label: __('Ab', 'flx-blocks'),
															tooltip: __('Capitalize', 'flx-blocks'),
														},
													]}
													allowClear={true}
													onChange={(value) => setAttributes({ textTransform: value })}
												/>
											</TwoColumn>
										</div>
									</>
								)}
							</FlxbkPanelBody>
							{showSettings('advancedSettings', 'flx/advancedheading') && (
								<FlxbkPanelBody
									title={__('Advanced Typography Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-adv-heading-typography-settings'}
								>
									<TypographyControls
										fontGroup={htmlTag !== 'heading' ? 'body' : 'heading'}
										reLetterSpacing={[letterSpacing, tabletLetterSpacing, mobileLetterSpacing]}
										onLetterSpacing={(value) =>
											setAttributes({
												letterSpacing: value[0],
												tabletLetterSpacing: value[1],
												mobileLetterSpacing: value[2],
											})
										}
										letterSpacingType={letterSpacingType}
										onLetterSpacingType={(value) => setAttributes({ letterSpacingType: value })}
										fontFamily={typography}
										onFontFamily={(value) => setAttributes({ typography: value })}
										context={{ blockName: 'flx/advancedheading' }}
										// The family is not a token, but the heading's preset surface can carry one, so the row
										// wears the same chrome and bound/overridden mark as Font Size above it.
										renderFontFamily={({ label, value, onChange, onClear }) => (
											<EditorFontFamilyControl
												label={label}
												value={value}
												onChange={onChange}
												onClear={onClear}
												inheritedLabel={presetTypography}
												state={tokenBinding.typography}
												onReset={() => resetToken('typography')}
											/>
										)}
										onFontChange={(select) => {
											setAttributes({
												typography: select.value,
												googleFont: select.google,
											});
										}}
										googleFont={googleFont}
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
										loadItalic={loadItalic}
										onLoadItalic={(value) => setAttributes({ loadItalic: value })}
									/>
								</FlxbkPanelBody>
							)}
							<FlxbkPanelBody
								title={__('Border Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-heading-border'}
							>
								{borderWidthTokens.length ? (
									<EditorBorderControl
										label={__('Border', 'flx-blocks')}
										value={borderStyle}
										tabletValue={tabletBorderStyle}
										mobileValue={mobileBorderStyle}
										onChange={(value) => setAttributes({ borderStyle: value })}
										onChangeTablet={(value) => setAttributes({ tabletBorderStyle: value })}
										onChangeMobile={(value) => setAttributes({ mobileBorderStyle: value })}
										previewDevice={previewDevice}
										onDeviceChange={setPreviewDevice}
										widthTokens={borderWidthTokens}
										defaultValue={borderWidthPresetValue}
										renderColor={renderBorderColor}
										state={tokenBinding.borderStyle}
										onReset={() => resetToken('borderStyle')}
									/>
								) : (
									<ResponsiveBorderControl
										label={__('Border', 'flx-blocks')}
										value={borderStyle}
										tabletValue={tabletBorderStyle}
										mobileValue={mobileBorderStyle}
										onChange={(value) => setAttributes({ borderStyle: value })}
										onChangeTablet={(value) => setAttributes({ tabletBorderStyle: value })}
										onChangeMobile={(value) => setAttributes({ mobileBorderStyle: value })}
									/>
								)}
								{borderRadiusTokens.length ? (
									<EditorBoxControl
										label={__('Border Radius', 'flx-blocks')}
										value={borderRadiusForDevice.value}
										onChange={(next) => setAttributes({ [borderRadiusForDevice.attr]: next })}
										previewDevice={previewDevice}
										onDeviceChange={setPreviewDevice}
										tokens={borderRadiusTokens}
										defaultValue={inheritedBorderRadius.values}
										inherited={anyCornerInherited(inheritedBorderRadius.inherited)}
										state={tokenBinding.borderRadius}
										onReset={() => resetToken('borderRadius')}
										isLinked={borderRadiusIsLinked}
										onToggleLink={toggleBorderRadiusLink}
										unit={borderRadiusUnit}
										units={['px', 'em', 'rem', '%']}
										onUnit={(value) => setAttributes({ borderRadiusUnit: value })}
										min={0}
										max={borderRadiusIsRelative ? 24 : 500}
										step={borderRadiusIsRelative ? 0.1 : 1}
									/>
								) : (
									<ResponsiveMeasurementControls
										label={__('Border Radius', 'flx-blocks')}
										value={borderRadius}
										tabletValue={tabletBorderRadius}
										mobileValue={mobileBorderRadius}
										onChange={(value) => setAttributes({ borderRadius: value })}
										onChangeTablet={(value) => setAttributes({ tabletBorderRadius: value })}
										onChangeMobile={(value) => setAttributes({ mobileBorderRadius: value })}
										unit={borderRadiusUnit}
										units={['px', 'em', 'rem', '%']}
										onUnit={(value) => setAttributes({ borderRadiusUnit: value })}
										max={borderRadiusIsRelative ? 24 : 500}
										step={borderRadiusIsRelative ? 0.1 : 1}
										min={0}
										isBorderRadius={true}
										allowEmpty={true}
									/>
								)}
							</FlxbkPanelBody>
							<FlxbkPanelBody
								title={__('Text Shadow Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-heading-text-shadow'}
							>
								<ToggleControl
									label={'Enable Text Shadow'}
									checked={enableTextShadow}
									onChange={(value) => {
										setAttributes({ enableTextShadow: value });
									}}
								/>
								{enableTextShadow === true && (
									<ResponsiveShadowControl
										label={__('Text Shadow', 'flx-blocks')}
										enable={enableTextShadow}
										color={previewColorTextShadow}
										colorDefault={'#000000'}
										onArrayChange={(color, opacity) => {
											saveShadow({ color, opacity });
										}}
										opacity={previewTextShadowOpacity}
										hOffset={previewHOffset}
										vOffset={previewVOffset}
										blur={previewBlur}
										onEnableChange={(value) => {
											saveShadow({ enable: value });
										}}
										onColorChange={(value) => {
											saveShadow({ color: value });
										}}
										onOpacityChange={(value) => {
											saveShadow({ opacity: value });
										}}
										onHOffsetChange={(value) => {
											saveShadow({ hOffset: value });
										}}
										onVOffsetChange={(value) => {
											saveShadow({ vOffset: value });
										}}
										onBlurChange={(value) => {
											saveShadow({ blur: value });
										}}
									/>
								)}
							</FlxbkPanelBody>
							<FlxbkPanelBody
								title={__('Text Orientation', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-adv-heading-text-orientation'}
							>
								<ResponsiveFlxbkRadioButtons
									label={__('Orientation', 'flx-blocks')}
									help={
										'horizontal' !== previewTextOrientation ? (
											<p
												dangerouslySetInnerHTML={{
													__html: __(
														'Vertical text can be harder to read at certain screen sizes or zoom levels. Responsive orientation can help, but browser support may vary.',
														'flx-blocks'
													),
												}}
											/>
										) : (
											''
										)
									}
									value={previewTextOrientation}
									tabletValue={previewTextOrientation}
									mobileValue={previewTextOrientation}
									className={'fxb-text-orientation'}
									options={[
										{
											value: 'horizontal',
											tooltip: __('Horizontal', 'flx-blocks'),
											icon: horizontalTextOrientationIcon,
										},
										{
											value: 'stacked',
											tooltip: __('Stacked Vertically', 'flx-blocks'),
											icon: stackedTextOrientationIcon,
										},
										{
											value: 'sideways-down',
											tooltip: __('Sideways Down', 'flx-blocks'),
											icon: sidewaysDownTextOrientationIcon,
										},
										{
											value: 'sideways-up',
											tooltip: __('Sideways Up', 'flx-blocks'),
											icon: sidewaysUpTextOrientationIcon,
										},
									]}
									onChange={(value) => setAttributes({ textOrientation: value })}
									onChangeTablet={(value) => setAttributes({ tabletTextOrientation: value })}
									onChangeMobile={(value) => setAttributes({ mobileTextOrientation: value })}
								/>
								{!isSiteStylesPanel && textOrientation !== 'horizontal' && textOrientation !== '' && (
									<ResponsiveRangeControls
										reset={() => {
											setAttributes({
												maxHeight: ['', '', ''],
												maxHeightType: 'px',
											});
										}}
										label={__('Max Height', 'flx-blocks')}
										value={previewMaxHeight}
										onChange={(value) => {
											setAttributes({
												maxHeight: [
													value,
													undefined !== maxHeight && undefined !== maxHeight[1]
														? maxHeight[1]
														: '',
													undefined !== maxHeight && undefined !== maxHeight[2]
														? maxHeight[2]
														: '',
												],
											});
										}}
										tabletValue={
											undefined !== maxHeight && undefined !== maxHeight[1] ? maxHeight[1] : ''
										}
										onChangeTablet={(value) => {
											setAttributes({
												maxHeight: [
													undefined !== maxHeight && undefined !== maxHeight[0]
														? maxHeight[0]
														: '',
													value,
													undefined !== maxHeight && undefined !== maxHeight[2]
														? maxHeight[2]
														: '',
												],
											});
										}}
										mobileValue={
											undefined !== maxHeight && undefined !== maxHeight[2] ? maxHeight[2] : ''
										}
										onChangeMobile={(value) => {
											setAttributes({
												maxHeight: [
													undefined !== maxHeight && undefined !== maxHeight[0]
														? maxHeight[0]
														: '',
													undefined !== maxHeight && undefined !== maxHeight[1]
														? maxHeight[1]
														: '',
													value,
												],
											});
										}}
										min={0}
										max={maxHeightType === 'px' ? 2000 : 100}
										step={1}
										unit={maxHeightType ? maxHeightType : 'px'}
										onUnit={(value) => {
											setAttributes({ maxHeightType: value });
										}}
										units={['px', '%', 'vw']}
									/>
								)}
							</FlxbkPanelBody>
							{showSettings('iconSettings', 'flx/advancedheading') && (
								<FlxbkPanelBody
									title={__('Icon Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-adv-heading-icon'}
								>
									{!isSiteStylesPanel && (
										<div className="fxt-select-icon-container">
											<FlxbkIconPicker
												value={icon}
												onChange={(value) => {
													setAttributes({ icon: value });
												}}
												allowClear={true}
											/>
										</div>
									)}
									<SelectControl
										label={__('Icon Location', 'flx-blocks')}
										value={iconSide}
										options={[
											{ value: 'left', label: __('Left', 'flx-blocks') },
											{ value: 'right', label: __('Right', 'flx-blocks') },
										]}
										onChange={(value) => {
											setAttributes({ iconSide: value });
										}}
									/>
									<SelectControl
										label={__('Vertical Alignment', 'flx-blocks')}
										value={iconVerticalAlign}
										options={[
											{ value: 'unset', label: __('Unset', 'flx-blocks') },
											{ value: 'baseline', label: __('Baseline', 'flx-blocks') },
											{ value: 'center', label: __('Center', 'flx-blocks') },
											{ value: 'end', label: __('End', 'flx-blocks') },
											{ value: 'start', label: __('Start', 'flx-blocks') },
										]}
										onChange={(value) => {
											setAttributes({ iconVerticalAlign: value });
										}}
									/>
									<ResponsiveRangeControls
										label={__('Icon Size', 'flx-blocks')}
										value={undefined !== iconSize?.[0] ? iconSize[0] : ''}
										onChange={(value) => {
											setAttributes({
												iconSize: [
													value,
													undefined !== iconSize[1] ? iconSize[1] : '',
													undefined !== iconSize?.[2] && iconSize[2] ? iconSize[2] : '',
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
										max={(iconSizeUnit ? iconSizeUnit : 'px') !== 'px' ? 12 : 200}
										step={(iconSizeUnit ? iconSizeUnit : 'px') !== 'px' ? 0.1 : 1}
										unit={iconSizeUnit ? iconSizeUnit : 'px'}
										onUnit={(value) => {
											setAttributes({ iconSizeUnit: value });
										}}
										units={['px', 'em', 'rem']}
										reset={true}
									/>
									<PopColorControl
										label={__('Icon Color', 'flx-blocks')}
										value={iconColor ? iconColor : ''}
										default={''}
										onChange={(value) => {
											setAttributes({ iconColor: value });
										}}
										swatchLabel2={__('Hover Color', 'flx-blocks')}
										value2={iconColorHover ? iconColorHover : ''}
										default2={''}
										onChange2={(value) => {
											setAttributes({ iconColorHover: value });
										}}
									/>
									<ResponsiveMeasureRangeControl
										label={__('Icon Padding', 'flx-blocks')}
										value={undefined !== iconPadding ? iconPadding : ['', '', '', '']}
										tabletValue={
											undefined !== tabletIconPadding ? tabletIconPadding : ['', '', '', '']
										}
										mobileValue={
											undefined !== mobileIconPadding ? mobileIconPadding : ['', '', '', '']
										}
										onChange={(value) => setAttributes({ iconPadding: value })}
										onChangeTablet={(value) => setAttributes({ tabletIconPadding: value })}
										onChangeMobile={(value) => setAttributes({ mobileIconPadding: value })}
										min={iconPaddingUnit === 'em' || iconPaddingUnit === 'rem' ? -2 : -999}
										max={iconPaddingUnit === 'em' || iconPaddingUnit === 'rem' ? 12 : 999}
										step={iconPaddingUnit === 'em' || iconPaddingUnit === 'rem' ? 0.1 : 1}
										unit={iconPaddingUnit}
										units={['px', 'em', 'rem']}
										onUnit={(value) => setAttributes({ iconPaddingUnit: value })}
									/>
									{!isSiteStylesPanel && (
										<>
											<TextControl
												label={__('Title for screen readers', 'flx-blocks')}
												help={__(
													'If no title added screen readers will ignore, good if the icon is purely decorative.',
													'flx-blocks'
												)}
												value={iconTitle}
												onChange={(value) => {
													setAttributes({ iconTitle: value });
												}}
											/>
											<TextareaControl
												label={__('Icon Tooltip Content', 'flx-blocks')}
												value={iconTooltip}
												onChange={(newValue) => setAttributes({ iconTooltip: newValue })}
											/>
											<SelectControl
												label={__('Icon Tooltip Placement', 'flx-blocks')}
												value={iconTooltipPlacement || 'top'}
												options={[
													{ label: __('Top', 'flx-blocks'), value: 'top' },
													{ label: __('Top Start', 'flx-blocks'), value: 'top-start' },
													{ label: __('Top End', 'flx-blocks'), value: 'top-end' },
													{ label: __('Right', 'flx-blocks'), value: 'right' },
													{
														label: __('Right Start', 'flx-blocks'),
														value: 'right-start',
													},
													{ label: __('Right End', 'flx-blocks'), value: 'right-end' },
													{ label: __('Bottom', 'flx-blocks'), value: 'bottom' },
													{
														label: __('Bottom Start', 'flx-blocks'),
														value: 'bottom-start',
													},
													{ label: __('Bottom End', 'flx-blocks'), value: 'bottom-end' },
													{ label: __('Left', 'flx-blocks'), value: 'left' },
													{ label: __('Left Start', 'flx-blocks'), value: 'left-start' },
													{ label: __('Left End', 'flx-blocks'), value: 'left-end' },
													{ label: __('Auto', 'flx-blocks'), value: 'auto' },
													{ label: __('Auto Start', 'flx-blocks'), value: 'auto-start' },
													{ label: __('Auto End', 'flx-blocks'), value: 'auto-end' },
												]}
												onChange={(val) => {
													setAttributes({ iconTooltipPlacement: val });
												}}
											/>
											<ToggleControl
												label={__('Show indicator underline', 'flx-blocks')}
												checked={iconTooltipDash}
												onChange={(value) => {
													setAttributes({ iconTooltipDash: value });
												}}
											/>
										</>
									)}
								</FlxbkPanelBody>
							)}
							{showSettings('highlightSettings', 'flx/advancedheading') && (
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
										context={{ blockName: 'flx/advancedheading' }}
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
							{showSettings('inlineImageSettings', 'flx/advancedheading') && (
								<FlxbkPanelBody
									title={__('Inline Image Settings', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-adv-heading-inline-image-settings'}
								>
									<ResponsiveRangeControls
										label={__('Image Width', 'flx-blocks')}
										value={
											undefined !== inlineImageWidth && undefined !== inlineImageWidth[0]
												? inlineImageWidth[0]
												: 150
										}
										onChange={(value) => {
											setAttributes({
												inlineImageWidth: [
													value,
													undefined !== inlineImageWidth && undefined !== inlineImageWidth[1]
														? inlineImageWidth[1]
														: '',
													undefined !== inlineImageWidth && undefined !== inlineImageWidth[2]
														? inlineImageWidth[2]
														: '',
												],
											});
										}}
										tabletValue={
											undefined !== inlineImageWidth && undefined !== inlineImageWidth[1]
												? inlineImageWidth[1]
												: ''
										}
										onChangeTablet={(value) => {
											setAttributes({
												inlineImageWidth: [
													undefined !== inlineImageWidth && undefined !== inlineImageWidth[0]
														? inlineImageWidth[0]
														: 150,
													value,
													undefined !== inlineImageWidth && undefined !== inlineImageWidth[2]
														? inlineImageWidth[2]
														: '',
												],
											});
										}}
										mobileValue={
											undefined !== inlineImageWidth && undefined !== inlineImageWidth[2]
												? inlineImageWidth[2]
												: ''
										}
										onChangeMobile={(value) => {
											setAttributes({
												inlineImageWidth: [
													undefined !== inlineImageWidth && undefined !== inlineImageWidth[0]
														? inlineImageWidth[0]
														: 150,
													undefined !== inlineImageWidth && undefined !== inlineImageWidth[1]
														? inlineImageWidth[1]
														: '',
													value,
												],
											});
										}}
										min={10}
										max={500}
										step={1}
										unit="px"
									/>
									<ToggleControl
										label={__('Use fixed ratio instead of image ratio', 'flx-blocks')}
										checked={useRatio}
										onChange={(value) => {
											setAttributes({
												useRatio: value,
												ratio: value && !ratio ? 'land43' : ratio,
											});
										}}
									/>
									{useRatio && (
										<>
											<SelectControl
												label={__('Size Ratio', 'flx-blocks')}
												value={ratio}
												options={[
													{
														label: __('Landscape 4:3', 'flx-blocks'),
														value: 'land43',
													},
													{
														label: __('Landscape 3:2', 'flx-blocks'),
														value: 'land32',
													},
													{
														label: __('Landscape 16:9', 'flx-blocks'),
														value: 'land169',
													},
													{
														label: __('Landscape 2:1', 'flx-blocks'),
														value: 'land21',
													},
													{
														label: __('Landscape 3:1', 'flx-blocks'),
														value: 'land31',
													},
													{
														label: __('Landscape 4:1', 'flx-blocks'),
														value: 'land41',
													},
													{
														label: __('Portrait 3:4', 'flx-blocks'),
														value: 'port34',
													},
													{
														label: __('Portrait 2:3', 'flx-blocks'),
														value: 'port23',
													},
													{
														label: __('Square 1:1', 'flx-blocks'),
														value: 'square',
													},
												]}
												onChange={(value) => setAttributes({ ratio: value })}
											/>
										</>
									)}
									<ResponsiveBorderControl
										label={__('Border', 'flx-blocks')}
										value={inlineImageBorderStyles}
										tabletValue={tabletInlineImageBorderStyles}
										mobileValue={mobileInlineImageBorderStyles}
										onChange={(value) => setAttributes({ inlineImageBorderStyles: value })}
										onChangeTablet={(value) =>
											setAttributes({ tabletInlineImageBorderStyles: value })
										}
										onChangeMobile={(value) =>
											setAttributes({ mobileInlineImageBorderStyles: value })
										}
									/>
									<ResponsiveMeasurementControls
										label={__('Border Radius', 'flx-blocks')}
										value={inlineImageBorderRadius}
										tabletValue={tabletInlineImageBorderRadius}
										mobileValue={mobileInlineImageBorderRadius}
										onChange={(value) => setAttributes({ inlineImageBorderRadius: value })}
										onChangeTablet={(value) =>
											setAttributes({ tabletInlineImageBorderRadius: value })
										}
										onChangeMobile={(value) =>
											setAttributes({ mobileInlineImageBorderRadius: value })
										}
										unit={inlineImageBorderRadiusUnit}
										units={['px', 'em', 'rem', '%']}
										onUnit={(value) => setAttributes({ inlineImageBorderRadiusUnit: value })}
										max={
											inlineImageBorderRadiusUnit === 'em' ||
											inlineImageBorderRadiusUnit === 'rem'
												? 24
												: 500
										}
										step={
											inlineImageBorderRadiusUnit === 'em' ||
											inlineImageBorderRadiusUnit === 'rem'
												? 0.1
												: 1
										}
										min={0}
										isBorderRadius={true}
										allowEmpty={true}
									/>
									<SelectControl
										label={__('Vertical Alignment', 'flx-blocks')}
										value={inlineImageVerticalAlign || 'baseline'}
										options={[
											{ value: 'baseline', label: __('Baseline', 'flx-blocks') },
											{ value: 'top', label: __('Top', 'flx-blocks') },
											{ value: 'middle', label: __('Middle', 'flx-blocks') },
											{ value: 'bottom', label: __('Bottom', 'flx-blocks') },
											{ value: 'text-top', label: __('Text Top', 'flx-blocks') },
											{ value: 'text-bottom', label: __('Text Bottom', 'flx-blocks') },
											{ value: 'sub', label: __('Sub', 'flx-blocks') },
											{ value: 'super', label: __('Super', 'flx-blocks') },
										]}
										onChange={(value) => setAttributes({ inlineImageVerticalAlign: value })}
									/>
								</FlxbkPanelBody>
							)}
						</>
					)}

					{activeTab === 'advanced' && (
						<>
							{showSettings('marginSettings', 'flx/advancedheading') && (
								<>
									<FlxbkPanelBody panelName={'fxb-row-padding'}>
										{paddingTokens.length ? (
											<EditorBoxControl
												label={__('Padding', 'flx-blocks')}
												value={paddingForDevice.value}
												onChange={(next) => setAttributes({ [paddingForDevice.attr]: next })}
												previewDevice={previewDevice}
												onDeviceChange={setPreviewDevice}
												tokens={paddingTokens}
												defaultValue={inheritedPadding.values}
												inherited={anyCornerInherited(inheritedPadding.inherited)}
												state={tokenBinding.padding}
												onReset={() => resetToken('padding')}
												isLinked={paddingIsLinked}
												onToggleLink={togglePaddingLink}
												role="sides"
												unit={paddingType}
												units={['px', 'em', 'rem', '%']}
												onUnit={(value) => setAttributes({ paddingType: value })}
												min={0}
												max={paddingIsRelative ? 12 : 999}
												step={paddingIsRelative ? 0.1 : 1}
											/>
										) : (
											<ResponsiveMeasureRangeControl
												label={__('Padding', 'flx-blocks')}
												value={padding}
												tabletValue={tabletPadding}
												mobileValue={mobilePadding}
												onChange={(value) => setAttributes({ padding: value })}
												onChangeTablet={(value) => setAttributes({ tabletPadding: value })}
												onChangeMobile={(value) => setAttributes({ mobilePadding: value })}
												min={0}
												max={paddingIsRelative ? 12 : 999}
												step={paddingIsRelative ? 0.1 : 1}
												unit={paddingType}
												units={['px', 'em', 'rem', '%']}
												onUnit={(value) => setAttributes({ paddingType: value })}
												onMouseOver={paddingMouseOver.onMouseOver}
												onMouseOut={paddingMouseOver.onMouseOut}
											/>
										)}
										<ResponsiveMeasureRangeControl
											label={__('Margin', 'flx-blocks')}
											value={margin}
											tabletValue={tabletMargin}
											mobileValue={mobileMargin}
											onChange={(value) => {
												setAttributes({ margin: value });
											}}
											onChangeTablet={(value) => setAttributes({ tabletMargin: value })}
											onChangeMobile={(value) => setAttributes({ mobileMargin: value })}
											min={marginType === 'em' || marginType === 'rem' ? -25 : -999}
											max={marginType === 'em' || marginType === 'rem' ? 25 : 999}
											step={marginType === 'em' || marginType === 'rem' ? 0.1 : 1}
											unit={marginType}
											units={['px', 'em', 'rem', '%', 'vh']}
											onUnit={(value) => setAttributes({ marginType: value })}
											onMouseOver={marginMouseOver.onMouseOver}
											onMouseOut={marginMouseOver.onMouseOut}
											allowAuto={true}
										/>
									</FlxbkPanelBody>

									<div className="fxt-sidebar-settings-spacer"></div>
								</>
							)}

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
				</InspectorControls>
			)}

			<DynamicInlineReplaceControl dynamicAttribute={'content'} {...props} />

			{!isSiteStylesPanel && (
				<InspectorAdvancedControls>
					{htmlTag === 'heading' && (
						<TextControl
							label={__('Alternative title for table of contents', 'flx-blocks')}
							help={__(
								'If using table of contents block you can define a custom title here.',
								'flx-blocks'
							)}
							value={altTitle}
							onChange={(value) => {
								setAttributes({ altTitle: value });
							}}
						/>
					)}
					<TextControl
						label={__('HTML Anchor', 'flx-blocks')}
						help={__('Anchors lets you link directly to a section on a page.', 'flx-blocks')}
						value={anchor || ''}
						onChange={(nextValue) => {
							nextValue = nextValue.replace(ANCHOR_REGEX, '-');
							setAttributes({
								anchor: nextValue,
							});
						}}
					/>
				</InspectorAdvancedControls>
			)}

			{flxbkAnimation && (
				<div className={`fxt-animation-wrap-${flxbkAnimation}`}>
					<div
						id={`animate-id${uniqueID}`}
						className={'aos-animate fxt-animation-wrap'}
						data-aos={flxbkAnimation ? flxbkAnimation : undefined}
						data-aos-duration={
							flxbkAOSOptions && flxbkAOSOptions[0] && flxbkAOSOptions[0].duration
								? flxbkAOSOptions[0].duration
								: undefined
						}
						data-aos-easing={
							flxbkAOSOptions && flxbkAOSOptions[0] && flxbkAOSOptions[0].easing
								? flxbkAOSOptions[0].easing
								: undefined
						}
					>
						{link ? headingLinkContent : headingContent}
					</div>
				</div>
			)}
			{!flxbkAnimation && (link ? headingLinkContent : headingContent)}
			{/*
			 * The preset's family is loaded on the same terms as the block's own. Without this a heading
			 * that takes its family from a preset renders in a fallback face: the loader is driven by the
			 * `typography` attribute, which is empty in exactly the case the preset supplies one.
			 */}
			{!typography && presetTypography && (
				<FlxbkWebfontLoader
					typography={[{ family: presetTypography, variant: presetVariant }]}
					clientId={clientId}
					id={'adv-heading-preset'}
				/>
			)}
			{googleFont && typography && (
				<FlxbkWebfontLoader
					typography={[{ family: typography, variant: fontVariant ? fontVariant : '' }]}
					clientId={clientId}
					id={'advancedHeading'}
				/>
			)}
			{markGoogleFont && markTypography && (
				<FlxbkWebfontLoader
					typography={[{ family: markTypography, variant: markFontVariant ? markFontVariant : '' }]}
					clientId={clientId}
					id={'advancedHeadingMark'}
				/>
			)}

			<SpacingVisualizer
				type="outsideVertical"
				forceShow={marginMouseOver.isMouseOver}
				spacing={[
					getSpacingOptionOutput(previewMarginTop, marginType),
					getSpacingOptionOutput(previewMarginRight, marginType),
					getSpacingOptionOutput(previewMarginBottom, marginType),
					getSpacingOptionOutput(previewMarginLeft, marginType),
				]}
			/>
			<SpacingVisualizer
				style={{
					marginLeft:
						undefined !== previewMarginLeft
							? getSpacingOptionOutput(previewMarginLeft, marginType)
							: undefined,
					marginRight:
						undefined !== previewMarginRight
							? getSpacingOptionOutput(previewMarginRight, marginType)
							: undefined,
					marginTop:
						undefined !== previewMarginTop
							? getSpacingOptionOutput(previewMarginTop, marginType)
							: undefined,
					marginBottom:
						undefined !== previewMarginBottom
							? getSpacingOptionOutput(previewMarginBottom, marginType)
							: undefined,
				}}
				type="inside"
				forceShow={paddingMouseOver.isMouseOver}
				spacing={[
					getSpacingOptionOutput(previewPaddingTop, paddingType),
					getSpacingOptionOutput(previewPaddingRight, paddingType),
					getSpacingOptionOutput(previewPaddingBottom, paddingType),
					getSpacingOptionOutput(previewPaddingLeft, paddingType),
				]}
			/>
		</div>
	);
}

export default FlxbkAdvancedHeading;
