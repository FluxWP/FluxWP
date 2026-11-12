/**
 * BLOCK: Flux Column
 *
 * Registering a basic block with Gutenberg.
 */

/**
 * Import Controls
 */
import classnames from 'classnames';
import { debounce } from 'lodash';

/**
 * Flux Components.
 */
import {
	PopColorControl,
	ResponsiveMeasurementControls,
	SmallResponsiveControl,
	RangeControl,
	ResponsiveRangeControls,
	FlxbkPanelBody,
	URLInputControl,
	ResponsiveRadioRangeControls,
	ResponsiveAlignControls,
	BoxShadowControl,
	FlxbkFormConditionals,
	BackgroundControl as FlxbkBackgroundControl,
	ResponsiveBorderControl,
	BackgroundTypeControl,
	GradientControl,
	InspectorControlTabs,
	FlxbkBlockDefaults,
	ResponsiveMeasureRangeControl,
	SpacingVisualizer,
	ColorGroup,
	HoverToggleControl,
	CopyPasteAttributes,
	DynamicTextInputControl,
} from '@flx-blocks/components';

/**
 * Flux Helpers.
 */
import {
	FlxbkColorOutput,
	getPreviewSize,
	showSettings,
	mouseOverVisualizer,
	getSpacingOptionOutput,
	getBorderStyle,
	uniqueIdHelper,
	getInQueryBlock,
	setDynamicState,
	hasFlxbkCustomCss,
} from '@flx-blocks/helpers';

import './editor.scss';
import metadata from './block.json';
import { getPreviewGutterSize } from './utils';
import {
	deriveStateBinding,
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
import { EditorBoxControl } from '../../extension/design-tokens/components/EditorBoxControl';
import { useColorGroups } from '../../extension/design-tokens/hooks/use-color-groups';
import { resolveColorLiteral } from '../../extension/design-tokens/color-literal';
import { tokenDimension } from '../../extension/design-tokens/token-dimension';
import { pickableTokensForControl } from '../../extension/token-picker';
import { ColorControl, isTokenAlias } from '../../token-controls';
import { useIsSiteStylesPanel } from '../../site-styles/use-is-site-styles-panel';
//import ResizeGridSection from './resize-grid-section';
/**
 * Import WordPress
 */
import { __ } from '@wordpress/i18n';

import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import {
	BlockAlignmentToolbar,
	BlockVerticalAlignmentToolbar,
	BlockControls,
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { ToggleControl, SelectControl, ToolbarGroup, ExternalLink } from '@wordpress/components';
const FORM_ALLOWED_BLOCKS = [
	'core/paragraph',
	'flx/advancedheading',
	'flx/spacer',
	'flx/rowlayout',
	'flx/column',
	'flx/advanced-form-text',
	'flx/advanced-form-textarea',
	'flx/advanced-form-select',
	'flx/advanced-form-submit',
	'flx/advanced-form-radio',
	'flx/advanced-form-file',
	'flx/advanced-form-time',
	'flx/advanced-form-date',
	'flx/advanced-form-telephone',
	'flx/advanced-form-checkbox',
	'flx/advanced-form-email',
	'flx/advanced-form-accept',
	'flx/advanced-form-number',
	'flx/advanced-form-hidden',
	'flx/advanced-form-captcha',
];
import { BLEND_OPTIONS } from '../rowlayout/constants';
import { applyFilters } from '@wordpress/hooks';
/**
 * Build the section edit.
 */
function SectionEdit(props) {
	const { attributes, setAttributes, isSelected, clientId, context, className } = props;
	const {
		id,
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
		leftMargin,
		rightMargin,
		leftMarginM,
		rightMarginM,
		topMarginT,
		bottomMarginT,
		leftMarginT,
		rightMarginT,
		topPaddingT,
		bottomPaddingT,
		leftPaddingT,
		rightPaddingT,
		backgroundOpacity,
		background,
		zIndex,
		border,
		borderWidth,
		borderOpacity,
		borderRadius,
		uniqueID,
		flxbkAnimation,
		flxbkAOSOptions,
		collapseOrder,
		backgroundImg,
		textAlign,
		textColor,
		linkColor,
		linkHoverColor,
		shadow,
		displayShadow,
		vsdesk,
		vstablet,
		vsmobile,
		paddingType,
		marginType,
		mobileBorderWidth,
		tabletBorderWidth,
		templateLock,
		flxbkBlockCSS,
		flxbkDynamic,
		direction,
		gutter,
		gutterUnit,
		verticalAlignment,
		verticalAlignmentTablet,
		verticalAlignmentMobile,
		justifyContent,
		backgroundImgHover,
		backgroundHover,
		borderHover,
		borderHoverWidth,
		borderHoverRadius,
		shadowHover,
		displayHoverShadow,
		tabletBorderHoverWidth,
		mobileBorderHoverWidth,
		textColorHover,
		linkColorHover,
		linkHoverColorHover,
		linkNoFollow,
		linkSponsored,
		link,
		linkTarget,
		linkTitle,
		wrapContent,
		heightUnit,
		height,
		maxWidth,
		maxWidthUnit,
		maxWidthTabletUnit,
		maxWidthMobileUnit,
		htmlTag,
		sticky,
		stickyOffset,
		stickyOffsetUnit,
		overlay,
		overlayHover,
		overlayImg,
		overlayImgHover,
		overlayOpacity,
		overlayHoverOpacity,
		align,
		padding,
		tabletPadding,
		mobilePadding,
		margin,
		tabletMargin,
		mobileMargin,
		backgroundType,
		backgroundHoverType,
		gradient,
		gradientHover,
		overlayType,
		overlayHoverType,
		overlayGradient,
		overlayGradientHover,
		borderRadiusUnit,
		borderHoverRadiusUnit,
		tabletBorderRadius,
		mobileBorderRadius,
		borderStyle,
		mobileBorderStyle,
		tabletBorderStyle,
		borderHoverStyle,
		tabletBorderHoverStyle,
		mobileBorderHoverStyle,
		tabletBorderHoverRadius,
		mobileBorderHoverRadius,
		inQueryBlock,
		hoverOverlayBlendMode,
		overlayBlendMode,
		rowGapUnit,
		rowGap,
		flexBasis,
		flexBasisUnit,
		rowGapVariable,
		gutterVariable,
		fxbVersion,
		flexGrow,
		backdropFilterType,
		backdropFilterSize,
		backdropFilterString,
	} = attributes;
	const [activeTab, setActiveTab] = useState('general');
	const [dynamicBackgroundImg, setDynamicBackgroundImg] = useState('');
	const isSiteStylesPanel = useIsSiteStylesPanel();

	const debouncedSetDynamicState = debounce(setDynamicState, 200);

	const { previewDevice } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
			};
		},
		[clientId]
	);
	const { setPreviewDeviceType: setPreviewDevice } = useDispatch('flxblocks/data');

	// Design-token indicators: the per-attribute bound/overridden state for the selected preset, plus a
	// reset that clears the mapped attribute back to the preset value (served by the existing scoped CSS).
	const tokenBinding = usePresetBinding('flx/column', attributes, undefined, previewDevice);
	const resetToken = (attr) => resetAttr(attr, setAttributes, tokenBinding[attr]?.kind, metadata.attributes);
	// One fetch of the block's effective palette groups, shared by every `ColorControl` on this block.
	const colorGroups = useColorGroups(clientId);

	// Empty when the token registry is inactive, which is what keeps the plain measurement controls below
	// as the fallback rather than leaving the column with no radius control at all. Both the normal and
	// the hover radius pick from this one pool — the column binds only the normal corner, and the hover
	// corner is the same kind of value.
	const borderRadiusTokens = pickableTokensForControl('flx/column', 'borderRadius') || [];
	// A radius control keeps ONE linked/individual mode but writes a different attribute per breakpoint,
	// so the mode must be read from — and "link" must collapse — whichever device is active.
	const borderRadiusForDevice = measureAttrsForDevice(
		attributes,
		'borderRadius',
		{ tablet: 'tabletBorderRadius', mobile: 'mobileBorderRadius' },
		previewDevice
	);
	const borderHoverRadiusForDevice = measureAttrsForDevice(
		attributes,
		'borderHoverRadius',
		{ tablet: 'tabletBorderHoverRadius', mobile: 'mobileBorderHoverRadius' },
		previewDevice
	);
	const borderRadiusPresetValue = presetValueForDevice(
		tokenBinding.borderRadius?.presetValue,
		tokenBinding.borderRadius?.responsive,
		previewDevice
	);
	// What an unset corner falls back to on the active device: another breakpoint's corner before the
	// preset's, matching the cascade the column actually renders through. The corners stay stored-empty —
	// this only tells the field's popover which size is in effect and where it came from.
	const inheritedBorderRadius = inheritedMeasureSlots(
		previewDevice,
		{ desktop: borderRadius, tablet: tabletBorderRadius },
		borderRadiusPresetValue
	);
	// The hover corners cascade among themselves, then fall to the preset's HOVER radius, and only then
	// to its resting one. A preset that sets no hover radius really does keep its resting corners
	// through `:hover`: the column paints a hover radius only from its own attribute, and the state
	// rule is emitted only for a preset that resolves the property. Without the resting step the field
	// had no literal to show and rendered empty, where the resting control beside it read "Default".
	const borderHoverRadiusPresetValue = presetValueForDevice(
		tokenBinding.borderHoverRadius?.presetValue,
		tokenBinding.borderHoverRadius?.responsive,
		previewDevice
	);
	const inheritedBorderHoverRadius = inheritedMeasureSlots(
		previewDevice,
		{
			desktop: borderHoverRadius,
			tablet: tabletBorderHoverRadius,
		},
		borderHoverRadiusPresetValue ?? borderRadiusPresetValue
	);
	// Falls back to a derived indicator while the active preset carries no hover radius of its own: without
	// a resolved value `usePresetBinding` makes no entry, and the derived one at least reports divergence
	// from what the preset set for NORMAL, which is the only preset-relative statement left to make.
	const borderHoverRadiusBinding = deriveStateBinding({
		shared: tokenBinding.borderRadius,
		kind: tokenBinding.borderRadius?.kind,
		value: borderHoverRadiusForDevice.value,
		unit: borderHoverRadiusUnit,
		devicePresetValue: borderRadiusPresetValue,
		previewDevice,
	});
	const borderRadiusIsRelative = borderRadiusUnit === 'em' || borderRadiusUnit === 'rem';
	const borderHoverRadiusIsRelative = borderHoverRadiusUnit === 'em' || borderHoverRadiusUnit === 'rem';
	// `resetOn` clears the remembered link choice on a preset change, since an override records a choice
	// about the PREVIOUS preset's corners — otherwise an explicit "link" would stick and hide a new
	// preset's per-corner radius.
	const { isLinked: borderRadiusIsLinked, toggleLink: toggleBorderRadiusLink } = useLinkedMeasureState({
		forDevice: borderRadiusForDevice,
		previewDevice,
		setAttributes,
		resetOn: attributes.fxbPreset,
	});
	const { isLinked: borderHoverRadiusIsLinked, toggleLink: toggleBorderHoverRadiusLink } = useLinkedMeasureState({
		forDevice: borderHoverRadiusForDevice,
		previewDevice,
		setAttributes,
		resetOn: attributes.fxbPreset,
	});

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

		// Update Old Styles
		if ('' !== topPadding || '' !== rightPadding || '' !== bottomPadding || '' !== leftPadding) {
			setAttributes({
				padding: [topPadding, rightPadding, bottomPadding, leftPadding],
				topPadding: '',
				rightPadding: '',
				bottomPadding: '',
				leftPadding: '',
			});
		}
		if ('' !== topPaddingT || '' !== rightPaddingT || '' !== bottomPaddingT || '' !== leftPaddingT) {
			setAttributes({
				tabletPadding: [topPaddingT, rightPaddingT, bottomPaddingT, leftPaddingT],
				topPaddingT: '',
				rightPaddingT: '',
				bottomPaddingT: '',
				leftPaddingT: '',
			});
		}
		if ('' !== topPaddingM || '' !== rightPaddingM || '' !== bottomPaddingM || '' !== leftPaddingM) {
			setAttributes({
				mobilePadding: [topPaddingM, rightPaddingM, bottomPaddingM, leftPaddingM],
				topPaddingM: '',
				rightPaddingM: '',
				bottomPaddingM: '',
				leftPaddingM: '',
			});
		}
		if ('' !== topMargin || '' !== rightMargin || '' !== bottomMargin || '' !== leftMargin) {
			setAttributes({
				margin: [topMargin, rightMargin, bottomMargin, leftMargin],
				topMargin: '',
				rightMargin: '',
				bottomMargin: '',
				leftMargin: '',
			});
		}
		if ('' !== topMarginT || '' !== rightMarginT || '' !== bottomMarginT || '' !== leftMarginT) {
			setAttributes({
				tabletMargin: [topMarginT, rightMarginT, bottomMarginT, leftMarginT],
				topMarginT: '',
				rightMarginT: '',
				bottomMarginT: '',
				leftMarginT: '',
			});
		}
		if ('' !== topMarginM || '' !== rightMarginM || '' !== bottomMarginM || '' !== leftMarginM) {
			setAttributes({
				mobileMargin: [topMarginM, rightMarginM, bottomMarginM, leftMarginM],
				topMarginM: '',
				rightMarginM: '',
				bottomMarginM: '',
				leftMarginM: '',
			});
		}
		// Update from old border settings in one write, so the site styles overlay still sees the old attributes.
		const borderPatch = {};
		const tempBorderStyle = JSON.parse(
			JSON.stringify(
				attributes.borderStyle
					? attributes.borderStyle
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
		if ('' !== border) {
			tempBorderStyle[0].top[0] = border;
			tempBorderStyle[0].right[0] = border;
			tempBorderStyle[0].bottom[0] = border;
			tempBorderStyle[0].left[0] = border;
			updateBorderStyle = true;
			borderPatch.border = '';
		}
		if ('' !== borderWidth?.[0] || '' !== borderWidth?.[1] || '' !== borderWidth?.[2] || '' !== borderWidth?.[3]) {
			tempBorderStyle[0].top[2] = borderWidth?.[0] || '';
			tempBorderStyle[0].right[2] = borderWidth?.[1] || '';
			tempBorderStyle[0].bottom[2] = borderWidth?.[2] || '';
			tempBorderStyle[0].left[2] = borderWidth?.[3] || '';
			updateBorderStyle = true;
			borderPatch.borderWidth = ['', '', '', ''];
		}
		if (updateBorderStyle) {
			borderPatch.borderStyle = tempBorderStyle;
		}
		const tempBorderHoverStyle = JSON.parse(
			JSON.stringify(
				attributes.borderHoverStyle
					? attributes.borderHoverStyle
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
		let updateBorderHoverStyle = false;
		if ('' !== borderHover) {
			tempBorderHoverStyle[0].top[0] = borderHover;
			tempBorderHoverStyle[0].right[0] = borderHover;
			tempBorderHoverStyle[0].bottom[0] = borderHover;
			tempBorderHoverStyle[0].left[0] = borderHover;
			updateBorderHoverStyle = true;
			borderPatch.borderHover = '';
		}
		if (
			'' !== borderHoverWidth?.[0] ||
			'' !== borderHoverWidth?.[1] ||
			'' !== borderHoverWidth?.[2] ||
			'' !== borderHoverWidth?.[3]
		) {
			tempBorderHoverStyle[0].top[2] = borderHoverWidth?.[0] || '';
			tempBorderHoverStyle[0].right[2] = borderHoverWidth?.[1] || '';
			tempBorderHoverStyle[0].bottom[2] = borderHoverWidth?.[2] || '';
			tempBorderHoverStyle[0].left[2] = borderHoverWidth?.[3] || '';
			updateBorderHoverStyle = true;
			borderPatch.borderHoverWidth = ['', '', '', ''];
		}
		if (updateBorderHoverStyle) {
			borderPatch.borderHoverStyle = tempBorderHoverStyle;
		}
		const tempTabBorderStyle = JSON.parse(
			JSON.stringify(
				attributes.tabletBorderStyle
					? attributes.tabletBorderStyle
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
		if (
			'' !== tabletBorderWidth?.[0] ||
			'' !== tabletBorderWidth?.[1] ||
			'' !== tabletBorderWidth?.[2] ||
			'' !== tabletBorderWidth?.[3]
		) {
			tempTabBorderStyle[0].top[2] = tabletBorderWidth?.[0] || '';
			tempTabBorderStyle[0].right[2] = tabletBorderWidth?.[1] || '';
			tempTabBorderStyle[0].bottom[2] = tabletBorderWidth?.[2] || '';
			tempTabBorderStyle[0].left[2] = tabletBorderWidth?.[3] || '';
			const tempTabBorderWidth = JSON.parse(JSON.stringify(tempTabBorderStyle));
			Object.assign(borderPatch, { tabletBorderStyle: tempTabBorderWidth, tabletBorderWidth: ['', '', '', ''] });
		}
		const tempTabBorderHoverStyle = JSON.parse(
			JSON.stringify(
				attributes.tabletBorderHoverStyle
					? attributes.tabletBorderHoverStyle
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
		if (
			'' !== tabletBorderHoverWidth?.[0] ||
			'' !== tabletBorderHoverWidth?.[1] ||
			'' !== tabletBorderHoverWidth?.[2] ||
			'' !== tabletBorderHoverWidth?.[3]
		) {
			tempTabBorderHoverStyle[0].top[2] = tabletBorderHoverWidth?.[0] || '';
			tempTabBorderHoverStyle[0].right[2] = tabletBorderHoverWidth?.[1] || '';
			tempTabBorderHoverStyle[0].bottom[2] = tabletBorderHoverWidth?.[2] || '';
			tempTabBorderHoverStyle[0].left[2] = tabletBorderHoverWidth?.[3] || '';
			const tempTabBorderHoverWidth = JSON.parse(JSON.stringify(tempTabBorderHoverStyle));
			Object.assign(borderPatch, {
				tabletBorderHoverStyle: tempTabBorderHoverWidth,
				tabletBorderHoverWidth: ['', '', '', ''],
			});
		}
		const tempMobileBorderStyle = JSON.parse(
			JSON.stringify(
				attributes.mobileBorderStyle
					? attributes.mobileBorderStyle
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
		if (
			'' !== mobileBorderWidth?.[0] ||
			'' !== mobileBorderWidth?.[1] ||
			'' !== mobileBorderWidth?.[2] ||
			'' !== mobileBorderWidth?.[3]
		) {
			tempMobileBorderStyle[0].top[2] = mobileBorderWidth?.[0] || '';
			tempMobileBorderStyle[0].right[2] = mobileBorderWidth?.[1] || '';
			tempMobileBorderStyle[0].bottom[2] = mobileBorderWidth?.[2] || '';
			tempMobileBorderStyle[0].left[2] = mobileBorderWidth?.[3] || '';
			Object.assign(borderPatch, {
				mobileBorderStyle: tempMobileBorderStyle,
				mobileBorderWidth: ['', '', '', ''],
			});
		}
		const tempMobileBorderHoverStyle = JSON.parse(
			JSON.stringify(
				attributes.mobileBorderHoverStyle
					? attributes.mobileBorderHoverStyle
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
		if (
			'' !== mobileBorderHoverWidth?.[0] ||
			'' !== mobileBorderHoverWidth?.[1] ||
			'' !== mobileBorderHoverWidth?.[2] ||
			'' !== mobileBorderHoverWidth?.[3]
		) {
			tempMobileBorderHoverStyle[0].top[2] = mobileBorderHoverWidth?.[0] || '';
			tempMobileBorderHoverStyle[0].right[2] = mobileBorderHoverWidth?.[1] || '';
			tempMobileBorderHoverStyle[0].bottom[2] = mobileBorderHoverWidth?.[2] || '';
			tempMobileBorderHoverStyle[0].left[2] = mobileBorderHoverWidth?.[3] || '';
			const tempMobileBorderHoverWidth = JSON.parse(JSON.stringify(tempMobileBorderHoverStyle));
			Object.assign(borderPatch, {
				mobileBorderHoverStyle: tempMobileBorderHoverWidth,
				mobileBorderHoverWidth: ['', '', '', ''],
			});
		}
		if (Object.keys(borderPatch).length) {
			setAttributes(borderPatch);
		}
		if (!fxbVersion || fxbVersion < 2) {
			// Update Align and Text Align settings for 3.2.0
			let deskJustifyAlign = justifyContent?.[0] ? justifyContent[0] : '';
			let tabletJustifyAlign = justifyContent?.[1] ? justifyContent[1] : '';
			let mobileJustifyAlign = justifyContent?.[2] ? justifyContent[2] : '';
			const deskDirection = direction?.[0] ? direction[0] : '';
			const tabletDirection = direction?.[1] ? direction[1] : '';
			const mobileDirection = direction?.[2] ? direction[2] : '';
			let updateJustify = false;
			if ('' !== textAlign?.[0] && deskDirection === 'horizontal' && deskJustifyAlign === '') {
				switch (textAlign[0]) {
					case 'center':
						deskJustifyAlign = 'center';
						break;
					case 'left':
						deskJustifyAlign = 'flex-start';
						break;
					case 'right':
						deskJustifyAlign = 'flex-end';
						break;
				}
				updateJustify = true;
			}
			if (
				'' !== textAlign?.[1] &&
				(tabletDirection === 'horizontal' || (tabletDirection === '' && deskDirection === 'horizontal')) &&
				deskJustifyAlign === ''
			) {
				switch (textAlign[1]) {
					case 'center':
						tabletJustifyAlign = 'center';
						break;
					case 'left':
						tabletJustifyAlign = 'flex-start';
						break;
					case 'right':
						tabletJustifyAlign = 'flex-end';
						break;
				}
				updateJustify = true;
			}
			if (
				'' !== textAlign?.[2] &&
				(mobileDirection === 'horizontal' ||
					(mobileDirection === '' && tabletDirection === 'horizontal') ||
					(mobileDirection === '' && tabletDirection === '' && deskDirection === 'horizontal')) &&
				deskJustifyAlign === ''
			) {
				switch (textAlign[2]) {
					case 'center':
						mobileJustifyAlign = 'center';
						break;
					case 'left':
						mobileJustifyAlign = 'flex-start';
						break;
					case 'right':
						mobileJustifyAlign = 'flex-end';
						break;
				}
				updateJustify = true;
			}
			if (deskDirection === 'vertical' && deskJustifyAlign !== '') {
				deskJustifyAlign = '';
				updateJustify = true;
			}
			if (
				(tabletDirection === 'vertical' || (tabletDirection === '' && deskDirection === 'vertical')) &&
				tabletJustifyAlign !== ''
			) {
				tabletJustifyAlign = '';
				updateJustify = true;
			}
			if (
				(mobileDirection === 'vertical' ||
					(mobileDirection === '' && tabletDirection === 'vertical') ||
					(mobileDirection === '' && tabletDirection === '' && deskDirection === 'vertical')) &&
				mobileJustifyAlign !== ''
			) {
				mobileJustifyAlign = '';
				updateJustify = true;
			}
			if (updateJustify) {
				setAttributes({ justifyContent: [deskJustifyAlign, tabletJustifyAlign, mobileJustifyAlign] });
			}
			// Update Gutter settings for 3.2.0.
			if ('' !== gutter?.[0] || '' !== gutter?.[1] || '' !== gutter?.[2]) {
				setAttributes({
					gutterVariable: [
						'' !== gutter?.[0] ? 'custom' : '',
						'' !== gutter?.[1] ? 'custom' : '',
						'' !== gutter?.[2] ? 'custom' : '',
					],
				});
			}
			if ('' !== rowGap?.[0] || '' !== rowGap?.[1] || '' !== rowGap?.[2]) {
				setAttributes({
					rowGapVariable: [
						'' !== rowGap?.[0] ? 'custom' : '',
						'' !== rowGap?.[1] ? 'custom' : '',
						'' !== rowGap?.[2] ? 'custom' : '',
					],
				});
			}
			// Update row gap to match gutter if layout is horizontal.
			if (
				undefined !== rowGap &&
				(deskDirection === 'horizontal' || tabletDirection === 'horizontal' || mobileDirection === 'horizontal')
			) {
				const tempRowGap = JSON.parse(JSON.stringify(rowGap));
				const tempRowGapVariable = ['', '', ''];
				if (deskDirection === 'horizontal') {
					tempRowGap[0] = '' !== gutter?.[0] ? gutter[0] : 10;
					tempRowGapVariable[0] = '' !== gutter?.[0] ? 'custom' : '';
				}
				if (
					((tabletDirection === '' && deskDirection === 'horizontal') || tabletDirection === 'horizontal') &&
					'' !== gutter?.[1]
				) {
					tempRowGap[1] = gutter?.[1];
					tempRowGapVariable[1] = '' !== gutter?.[1] ? 'custom' : '';
				}
				if (
					((mobileDirection === '' && tabletDirection === 'horizontal') ||
						(mobileDirection === '' && tabletDirection === '' && deskDirection === 'horizontal') ||
						mobileDirection === 'horizontal') &&
					'' !== gutter?.[2]
				) {
					tempRowGap[2] = gutter?.[2];
					tempRowGapVariable[2] = '' !== gutter?.[2] ? 'custom' : '';
				}

				setAttributes({
					rowGapVariable: tempRowGapVariable,
					rowGap: tempRowGap,
					rowGapUnit: gutterUnit ? gutterUnit : 'px',
				});
			}
			attributes.fxbVersion = 2;
			setAttributes({ fxbVersion: 2 });
		}

		if (inRowBlock && align !== '') {
			setAttributes({ align: '' });
		}
	}, []);

	uniqueIdHelper(props);

	//set the dynamic image state
	useEffect(() => {
		debouncedSetDynamicState(
			'flxbk.dynamicBackground',
			'',
			attributes,
			'backgroundImg:0:bgImg',
			setAttributes,
			context,
			setDynamicBackgroundImg,
			backgroundImg ? false : true
		);
	}, [backgroundImg, context]);

	const { hasInnerBlocks, inRowBlock, inFormBlock } = useSelect(
		(select) => {
			const { getBlock, getBlockRootClientId, getBlockParentsByBlockName, getBlocksByClientId } =
				select(blockEditorStore);
			const block = getBlock(clientId);
			const rootID = getBlockRootClientId(clientId);
			let inRowBlock = false;
			let inFormBlock = false;
			if (rootID) {
				const hasForm = getBlockParentsByBlockName(clientId, 'flx/advanced-form');
				const parentBlock = getBlocksByClientId(rootID);
				inFormBlock = undefined !== hasForm && hasForm.length ? true : false;
				inRowBlock =
					undefined !== parentBlock &&
					undefined !== parentBlock[0] &&
					undefined !== parentBlock[0].name &&
					parentBlock[0].name === 'flx/rowlayout'
						? true
						: false;
			}
			return {
				inRowBlock,
				hasInnerBlocks: !!(block && block.innerBlocks.length),
				inFormBlock,
			};
		},
		[clientId]
	);
	const paddingMouseOver = mouseOverVisualizer();
	const marginMouseOver = mouseOverVisualizer();
	const saveShadow = (value) => {
		const newUpdate = shadow.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			shadow: newUpdate,
		});
	};
	const saveShadowHover = (value) => {
		const newItems = shadowHover.map((item, thisIndex) => {
			if (0 === thisIndex) {
				item = { ...item, ...value };
			}

			return item;
		});
		setAttributes({
			shadowHover: newItems,
		});
	};
	const saveBackgroundImage = (value) => {
		const newUpdate = backgroundImg.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			backgroundImg: newUpdate,
		});
	};
	const saveHoverBackgroundImage = (value) => {
		const newUpdate = backgroundImgHover.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			backgroundImgHover: newUpdate,
		});
	};
	const onRemoveBGImage = () => {
		saveBackgroundImage({
			bgImgID: '',
			bgImg: '',
		});
	};
	const onRemoveHoverBGImage = () => {
		saveHoverBackgroundImage({
			bgImgID: '',
			bgImg: '',
		});
	};
	const saveOverlayImage = (value) => {
		const newUpdate = overlayImg.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			overlayImg: newUpdate,
		});
	};
	const saveHoverOverlayImage = (value) => {
		const newUpdate = overlayImgHover.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setAttributes({
			overlayImgHover: newUpdate,
		});
	};
	const onRemoveOverlayImage = () => {
		saveOverlayImage({
			bgImgID: '',
			bgImg: '',
		});
	};
	const onRemoveHoverOverlayImage = () => {
		saveHoverOverlayImage({
			bgImgID: '',
			bgImg: '',
		});
	};
	const previewPaddingType = undefined !== paddingType ? paddingType : 'px';
	const previewMarginType = undefined !== marginType ? marginType : 'px';
	// Margin
	const previewMarginTop = getPreviewSize(
		previewDevice,
		undefined !== margin ? margin[0] : '',
		undefined !== tabletMargin ? tabletMargin[0] : '',
		undefined !== mobileMargin ? mobileMargin[0] : ''
	);
	const previewMarginRight = getPreviewSize(
		previewDevice,
		undefined !== margin && undefined !== margin[1] && '' !== margin[1] ? margin[1] : '',
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
		undefined !== margin && undefined !== margin[3] && '' !== margin[3] ? margin[3] : '',
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
		undefined !== padding && undefined !== padding[1] && '' !== padding[1] ? padding[1] : '',
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
		undefined !== padding && undefined !== padding[3] && '' !== padding[3] ? padding[3] : '',
		undefined !== tabletPadding ? tabletPadding[3] : '',
		undefined !== mobilePadding ? mobilePadding[3] : ''
	);
	// Border.
	const previewBorderTopStyle = getBorderStyle(
		previewDevice,
		'top',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderRightStyle = getBorderStyle(
		previewDevice,
		'right',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderBottomStyle = getBorderStyle(
		previewDevice,
		'bottom',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewBorderLeftStyle = getBorderStyle(
		previewDevice,
		'left',
		borderStyle,
		tabletBorderStyle,
		mobileBorderStyle
	);
	const previewRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[0] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[0] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[0] : ''
	);
	const previewRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[1] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[1] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[1] : ''
	);
	const previewRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[2] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[2] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[2] : ''
	);
	const previewRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== borderRadius ? borderRadius[3] : '',
		undefined !== tabletBorderRadius ? tabletBorderRadius[3] : '',
		undefined !== mobileBorderRadius ? mobileBorderRadius[3] : ''
	);
	// Hover Border
	const previewBorderHoverTopStyle = getBorderStyle(
		previewDevice,
		'top',
		borderHoverStyle,
		tabletBorderHoverStyle,
		mobileBorderHoverStyle
	);
	const previewBorderHoverRightStyle = getBorderStyle(
		previewDevice,
		'right',
		borderHoverStyle,
		tabletBorderHoverStyle,
		mobileBorderHoverStyle
	);
	const previewBorderHoverBottomStyle = getBorderStyle(
		previewDevice,
		'bottom',
		borderHoverStyle,
		tabletBorderHoverStyle,
		mobileBorderHoverStyle
	);
	const previewBorderHoverLeftStyle = getBorderStyle(
		previewDevice,
		'left',
		borderHoverStyle,
		tabletBorderHoverStyle,
		mobileBorderHoverStyle
	);
	const previewHoverRadiusTop = getPreviewSize(
		previewDevice,
		undefined !== borderHoverRadius ? borderHoverRadius[0] : '',
		undefined !== tabletBorderHoverRadius ? tabletBorderHoverRadius[0] : '',
		undefined !== mobileBorderHoverRadius ? mobileBorderHoverRadius[0] : ''
	);
	const previewHoverRadiusRight = getPreviewSize(
		previewDevice,
		undefined !== borderHoverRadius ? borderHoverRadius[1] : '',
		undefined !== tabletBorderHoverRadius ? tabletBorderHoverRadius[1] : '',
		undefined !== mobileBorderHoverRadius ? mobileBorderHoverRadius[1] : ''
	);
	const previewHoverRadiusBottom = getPreviewSize(
		previewDevice,
		undefined !== borderHoverRadius ? borderHoverRadius[2] : '',
		undefined !== tabletBorderHoverRadius ? tabletBorderHoverRadius[2] : '',
		undefined !== mobileBorderHoverRadius ? mobileBorderHoverRadius[2] : ''
	);
	const previewHoverRadiusLeft = getPreviewSize(
		previewDevice,
		undefined !== borderHoverRadius ? borderHoverRadius[3] : '',
		undefined !== tabletBorderHoverRadius ? tabletBorderHoverRadius[3] : '',
		undefined !== mobileBorderHoverRadius ? mobileBorderHoverRadius[3] : ''
	);

	const previewAlign = getPreviewSize(
		previewDevice,
		textAlign && textAlign[0] ? textAlign[0] : '',
		textAlign && textAlign[1] ? textAlign[1] : '',
		textAlign && textAlign[2] ? textAlign[2] : ''
	);
	const previewVerticalAlign = getPreviewSize(
		previewDevice,
		verticalAlignment ? verticalAlignment : '',
		verticalAlignmentTablet ? verticalAlignmentTablet : '',
		verticalAlignmentMobile ? verticalAlignmentMobile : ''
	);
	let previewVerticalAlignCSS = previewVerticalAlign;
	if (previewVerticalAlign && 'top' === previewVerticalAlign) {
		previewVerticalAlignCSS = 'flex-start';
	} else if (previewVerticalAlign && 'middle' === previewVerticalAlign) {
		previewVerticalAlignCSS = 'center';
	} else if (previewVerticalAlign && 'bottom' === previewVerticalAlign) {
		previewVerticalAlignCSS = 'flex-end';
	}
	const previewGutter = getPreviewSize(
		previewDevice,
		gutter && '' !== gutter[0] ? gutter[0] : '10',
		gutter && '' !== gutter[1] ? gutter[1] : '',
		gutter && '' !== gutter[2] ? gutter[2] : ''
	);
	const previewGutterVariable = getPreviewSize(
		previewDevice,
		gutterVariable && '' !== gutterVariable[0] ? gutterVariable[0] : 'sm',
		gutterVariable && '' !== gutterVariable[1] ? gutterVariable[1] : '',
		gutterVariable && '' !== gutterVariable[2] ? gutterVariable[2] : ''
	);
	const horizontalGap = getPreviewGutterSize(previewDevice, previewGutterVariable, gutter, gutterUnit);

	const previewRowGap = getPreviewSize(
		previewDevice,
		rowGap && '' !== rowGap[0] ? rowGap[0] : '',
		rowGap && '' !== rowGap[1] ? rowGap[1] : '',
		rowGap && '' !== rowGap[2] ? rowGap[2] : ''
	);
	const previewRowGapVariable = getPreviewSize(
		previewDevice,
		rowGapVariable && '' !== rowGapVariable[0] ? rowGapVariable[0] : 'none',
		rowGapVariable && '' !== rowGapVariable[1] ? rowGapVariable[1] : '',
		rowGapVariable && '' !== rowGapVariable[2] ? rowGapVariable[2] : ''
	);
	const flexRowGapVariable = getPreviewSize(
		previewDevice,
		rowGapVariable && '' !== rowGapVariable[0] ? rowGapVariable[0] : '',
		rowGapVariable && '' !== rowGapVariable[1] ? rowGapVariable[1] : '',
		rowGapVariable && '' !== rowGapVariable[2] ? rowGapVariable[2] : ''
	);

	const verticalGap = getPreviewGutterSize(previewDevice, previewRowGapVariable, rowGap, rowGapUnit);
	const previewDirection = getPreviewSize(
		previewDevice,
		direction && '' !== direction[0] ? direction[0] : 'vertical',
		direction && '' !== direction[1] ? direction[1] : '',
		direction && '' !== direction[2] ? direction[2] : ''
	);

	const previewJustify = getPreviewSize(
		previewDevice,
		undefined !== justifyContent?.[0] && '' !== justifyContent?.[0] ? justifyContent[0] : '',
		undefined !== justifyContent?.[1] && '' !== justifyContent?.[1]
			? justifyContent[1]
			: 'horizontal' == direction?.[0] && 'vertical' == direction?.[1]
				? 'stretch'
				: '',
		undefined !== justifyContent?.[2] && '' !== justifyContent?.[2]
			? justifyContent[2]
			: 'horizontal' == direction?.[1] && 'vertical' == direction?.[2]
				? 'stretch'
				: ''
	);

	const previewWrap = getPreviewSize(
		previewDevice,
		wrapContent && '' !== wrapContent[0] ? wrapContent[0] : '',
		wrapContent && '' !== wrapContent[1] ? wrapContent[1] : '',
		wrapContent && '' !== wrapContent[2] ? wrapContent[2] : ''
	);
	const backgroundString = background ? FlxbkColorOutput(background, backgroundOpacity) : undefined;
	const previewFlexBasis = getPreviewSize(
		previewDevice,
		(previewDirection === 'horizontal' || previewDirection === 'horizontal-reverse') &&
			flexBasis &&
			'' !== flexBasis[0]
			? flexBasis[0]
			: '',
		(previewDirection === 'horizontal' || previewDirection === 'horizontal-reverse') &&
			flexBasis &&
			'' !== flexBasis[1]
			? flexBasis[1]
			: '',
		(previewDirection === 'horizontal' || previewDirection === 'horizontal-reverse') &&
			flexBasis &&
			'' !== flexBasis[2]
			? flexBasis[2]
			: ''
	);

	const previewMaxWidth = getPreviewSize(
		previewDevice,
		maxWidth && maxWidth[0] ? maxWidth[0] : '',
		maxWidth && maxWidth[1] ? maxWidth[1] : '',
		maxWidth && maxWidth[2] ? maxWidth[2] : ''
	);

	// Check for falsy to support how units worked before
	const previewMaxWidthUnit = getPreviewSize(
		previewDevice,
		maxWidthUnit ? maxWidthUnit : 'px',
		maxWidthTabletUnit ? maxWidthTabletUnit : '',
		maxWidthMobileUnit ? maxWidthMobileUnit : ''
	);
	const previewMinHeight = getPreviewSize(
		previewDevice,
		height && '' !== height[0] ? height[0] : '',
		height && '' !== height[1] ? height[1] : '',
		height && '' !== height[2] ? height[2] : ''
	);
	const previewStickyOffset = getPreviewSize(
		previewDevice,
		stickyOffset && stickyOffset[0] ? stickyOffset[0] : '',
		stickyOffset && stickyOffset[1] ? stickyOffset[1] : '',
		stickyOffset && stickyOffset[2] ? stickyOffset[2] : ''
	);
	const previewFlexGrow = getPreviewSize(
		previewDevice,
		flexGrow && flexGrow[0] ? flexGrow[0] : '',
		flexGrow && flexGrow[1] ? flexGrow[1] : '',
		flexGrow && flexGrow[2] ? flexGrow[2] : ''
	);
	const previewMinHeightUnit = heightUnit ? heightUnit : 'px';
	const previewStickyOffsetUnit = stickyOffsetUnit ? stickyOffsetUnit : 'px';
	const hasCustomCss = hasFlxbkCustomCss(flxbkBlockCSS);
	const classes = classnames({
		[className]: className,
		'flxbk-column': true,
		[`inner-column-${id}`]: id,
		[`flxbk-column-${uniqueID}`]: uniqueID,
		[`flxbk-section-sticky`]: sticky !== undefined ? sticky : false,
		'fxvs-lg-false': vsdesk !== 'undefined' && vsdesk,
		'fxvs-md-false': vstablet !== 'undefined' && vstablet,
		'fxvs-sm-false': vsmobile !== 'undefined' && vsmobile,
		'flxbk-has-custom-css': hasCustomCss,
	});
	const previewBackgroundImg = dynamicBackgroundImg ? dynamicBackgroundImg : backgroundImg;
	const hasBackgroundImage =
		previewBackgroundImg && previewBackgroundImg[0] && previewBackgroundImg[0].bgImg ? true : false;
	const previewBackgroundImgCSS = hasBackgroundImage ? `url( ${previewBackgroundImg[0].bgImg} )` : '';
	const previewBackground = backgroundType === 'gradient' ? gradient : previewBackgroundImgCSS;
	const hasHoverBackgroundImage =
		backgroundImgHover && backgroundImgHover[0] && backgroundImgHover[0].bgImg ? true : false;
	const previewHoverBackgroundImg = hasHoverBackgroundImage ? `url( ${backgroundImgHover[0].bgImg} )` : '';
	const previewHoverBackground = backgroundHoverType === 'gradient' ? gradientHover : previewHoverBackgroundImg;
	const hasOverlayImage = overlayImg && overlayImg[0] && overlayImg[0].bgImg ? true : false;
	const previewOverlayImg = hasOverlayImage ? `url( ${overlayImg[0].bgImg} )` : '';
	const previewOverlay = overlayType === 'gradient' ? overlayGradient : previewOverlayImg;
	const hasHoverOverlayImage = overlayImgHover && overlayImgHover[0] && overlayImgHover[0].bgImg ? true : false;
	const previewHoverOverlayImg = hasHoverOverlayImage ? `url( ${overlayImgHover[0].bgImg} )` : '';
	const previewHoverOverlay = overlayHoverType === 'gradient' ? overlayGradientHover : previewHoverOverlayImg;
	const nonTransAttrs = ['images', 'imagesDynamic'];
	const innerClasses = classnames({
		'flxbk-inner-column-inner': true,
		'aos-animate': true,
		'fxt-animation-wrap': true,
		'fxb-section-only-appender': !hasInnerBlocks,
		[`flxbk-inner-column-direction-${previewDirection ? previewDirection : 'vertical'}`]: true,
		[`flxbk-inner-column-text-align-${previewAlign ? previewAlign : 'normal'}`]: true,
		[`flxbk-inner-column-vertical-align-${previewVerticalAlign ? previewVerticalAlign : 'inherit'}`]: true,
		'section-is-flex':
			previewDirection === 'horizontal' ||
			previewDirection === 'horizontal-reverse' ||
			previewDirection === 'vertical-reverse' ||
			previewJustify ||
			previewFlexBasis ||
			flexRowGapVariable ||
			previewVerticalAlign,
	});
	const blockProps = useBlockProps({
		className: classes,
		style: {
			top:
				sticky && undefined !== previewStickyOffset ? previewStickyOffset + previewStickyOffsetUnit : undefined,
		},
		'data-align': !inRowBlock && ('full' === align || 'wide' === align) ? align : undefined,
		'data-vertical-align':
			'top' === previewVerticalAlign || 'middle' === previewVerticalAlign || 'bottom' === previewVerticalAlign
				? previewVerticalAlign
				: undefined,
	});
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: innerClasses,
		},
		{
			orientation:
				previewDirection === 'horizontal' || previewDirection === 'horizontal-reverse'
					? 'horizontal'
					: 'vertical',
			templateLock,
			renderAppender: hasInnerBlocks ? undefined : InnerBlocks.ButtonBlockAppender,
			allowedBlocks: inFormBlock ? FORM_ALLOWED_BLOCKS : undefined,
		}
	);
	const actualVerticalAlign = previewVerticalAlign
		? previewVerticalAlign
		: previewDirection === 'horizontal' || previewDirection === 'horizontal-reverse'
			? 'middle'
			: 'top';

	const styleControlsBackdropFilter = null;
	return (
		<div {...blockProps}>
			<style>
				{'' !== previewFlexGrow
					? `.flxbk-column-${uniqueID}, .wp-block-flx-column > .flxbk-inner-column-direction-horizontal > .flxbk-column-${uniqueID} { flex-grow: ${previewFlexGrow} }.flxbk-column-${uniqueID} > .flxbk-inner-column-inner{height:100%;}`
					: ''}
				{overlayOpacity !== undefined && overlayOpacity !== ''
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { opacity: ${overlayOpacity} }`
					: ''}
				{overlay
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { background-color: ${FlxbkColorOutput(
							overlay
						)}; }`
					: ''}
				{overlayBlendMode
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { mix-blend-mode: ${overlayBlendMode}; }`
					: ''}
				{previewOverlay
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { background-image:${previewOverlay}; }`
					: ''}
				{hasOverlayImage && overlayImg[0].bgImgPosition
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { background-position:${overlayImg[0].bgImgPosition}; }`
					: ''}
				{hasOverlayImage && overlayImg[0].bgImgSize
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { background-size:${overlayImg[0].bgImgSize}; }`
					: ''}
				{hasOverlayImage && overlayImg[0].bgImgRepeat
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { background-repeat:${overlayImg[0].bgImgRepeat}; }`
					: ''}
				{hasOverlayImage && overlayImg[0].bgImgAttachment
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { background-attachment:${overlayImg[0].bgImgAttachment}; }`
					: ''}

				{/* Tested against '' for the same reason the inner element's inline radius is: a corner
				    set to 0 is a real value that truthiness throws away. */}
				{'' !== previewRadiusTop
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { border-top-left-radius:${tokenDimension(
							previewRadiusTop,
							borderRadiusUnit ? borderRadiusUnit : 'px'
						)}; }`
					: ''}
				{'' !== previewRadiusRight
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { border-top-right-radius:${tokenDimension(
							previewRadiusRight,
							borderRadiusUnit ? borderRadiusUnit : 'px'
						)}; }`
					: ''}
				{'' !== previewRadiusBottom
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { border-bottom-right-radius:${tokenDimension(
							previewRadiusBottom,
							borderRadiusUnit ? borderRadiusUnit : 'px'
						)}; }`
					: ''}
				{'' !== previewRadiusLeft
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:before { border-bottom-left-radius:${tokenDimension(
							previewRadiusLeft,
							borderRadiusUnit ? borderRadiusUnit : 'px'
						)}; }`
					: ''}

				{overlayHoverOpacity !== undefined && overlayHoverOpacity !== ''
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { opacity: ${overlayHoverOpacity} }`
					: ''}
				{overlayHover
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { background-color: ${FlxbkColorOutput(
							overlayHover
						)}; ; }`
					: ''}
				{hoverOverlayBlendMode
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { mix-blend-mode: ${hoverOverlayBlendMode} !important; }`
					: ''}
				{previewHoverOverlay
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { background-image:${previewHoverOverlay}; }`
					: ''}
				{!previewHoverOverlay && overlayHover
					? `.flxbk-column-${uniqueID}:hover .flxbk-inner-column-inner:before { background-image:none; }`
					: ''}
				{hasHoverOverlayImage && overlayImgHover[0].bgImgPosition
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { background-position:${overlayImgHover[0].bgImgPosition}; }`
					: ''}
				{hasHoverOverlayImage && overlayImgHover[0].bgImgSize
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { background-size:${overlayImgHover[0].bgImgSize}; }`
					: ''}
				{hasHoverOverlayImage && overlayImgHover[0].bgImgRepeat
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { background-repeat:${overlayImgHover[0].bgImgRepeat}; }`
					: ''}
				{hasHoverOverlayImage && overlayImgHover[0].bgImgAttachment
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { background-attachment:${overlayImgHover[0].bgImgAttachment}; }`
					: ''}

				{previewHoverRadiusTop
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { border-top-left-radius:${tokenDimension(
							previewHoverRadiusTop,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{previewHoverRadiusRight
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { border-top-right-radius:${tokenDimension(
							previewHoverRadiusRight,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{previewHoverRadiusBottom
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { border-bottom-right-radius:${tokenDimension(
							previewHoverRadiusBottom,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{previewHoverRadiusLeft
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner:before { border-bottom-left-radius:${tokenDimension(
							previewHoverRadiusLeft,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}

				{previewMaxWidth
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner { max-width:${
							previewMaxWidth + previewMaxWidthUnit
						}; margin-left: auto; margin-right:auto; }`
					: ''}
				{previewMaxWidth
					? `.wp-block-flx-column > .flxbk-inner-column-direction-horizontal > .wp-block-flx-column.flxbk-column-${uniqueID} > .flxbk-inner-column-inner, .wp-block-flx-column > .flxbk-inner-column-direction-horizontal-reverse > .wp-block-flx-column.flxbk-column-${uniqueID} > .flxbk-inner-column-inner { max-width:100%; margin-left: unset; margin-right:unset; }`
					: ''}
				{previewMaxWidth
					? `.wp-block-flx-column > .flxbk-inner-column-direction-horizontal > .wp-block-flx-column.flxbk-column-${uniqueID}, .wp-block-flx-column > .flxbk-inner-column-direction-horizontal-reverse > .wp-block-flx-column.flxbk-column-${uniqueID} { flex: 0 1 ${
							previewMaxWidth + previewMaxWidthUnit
						}; }`
					: ''}
				{undefined !== zIndex && '' !== zIndex ? `.flxbk-column-${uniqueID} { z-index: ${zIndex}; }` : ''}
				{textColor
					? `.flxbk-column-${uniqueID}, .flxbk-column-${uniqueID} .fxt-svg-icon-list-item-wrap, .flxbk-column-${uniqueID} p, .flxbk-column-${uniqueID} h1, .flxbk-column-${uniqueID} h1.flxbk-advancedheading-text, .flxbk-column-${uniqueID} h2, .flxbk-column-${uniqueID} h2.flxbk-advancedheading-text, .flxbk-column-${uniqueID} h3, .flxbk-column-${uniqueID} h3.flxbk-advancedheading-text, .flxbk-column-${uniqueID} h4, .flxbk-column-${uniqueID} h4.flxbk-advancedheading-text, .flxbk-column-${uniqueID} h5, .flxbk-column-${uniqueID} h5.flxbk-advancedheading-text, .flxbk-column-${uniqueID} h6, .flxbk-column-${uniqueID} h6.flxbk-advancedheading-text { color: ${FlxbkColorOutput(
							textColor
						)}; }`
					: ''}
				{linkColor ? `.flxbk-column-${uniqueID} a { color: ${FlxbkColorOutput(linkColor)}; }` : ''}
				{linkHoverColor
					? `.flxbk-column-${uniqueID} a:hover { color: ${FlxbkColorOutput(linkHoverColor)}; }`
					: ''}
				{'' !== horizontalGap
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner { column-gap: ${horizontalGap}; }`
					: ''}
				{'' !== previewFlexBasis && null !== previewFlexBasis
					? `.wp-block-flx-column.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal > *, .wp-block-flx-column.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal-reverse > * { flex-basis: ${
							previewFlexBasis + (flexBasisUnit ? flexBasisUnit : 'px')
						}; }.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal > .wp-block-flx-infobox, .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal-reverse > .wp-block-flx-infobox { width:0px; }`
					: ''}
				{'' !== previewFlexBasis
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal > .wp-block-flx-image:not(:last-child), .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal-reverse > .wp-block-flx-image:not(:last-child) { margin-bottom: unset; }`
					: ''}
				{'' == previewFlexBasis
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal > .fxb-image-is-ratio-size:not(.fxb-image-max-width-set), .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal-reverse > .fxb-image-is-ratio-size:not(.fxb-image-max-width-set) { flex-grow: 1; }`
					: ''}
				{'' == previewFlexBasis
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical > .fxb-image-is-ratio-size:not(.fxb-image-max-width-set), .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical-reverse > .fxb-image-is-ratio-size:not(.fxb-image-max-width-set) { align-self: stretch; }`
					: ''}
				{'' == previewFlexBasis
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical > .fxb-image-is-ratio-size:not(.fxb-image-max-width-set), .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical-reverse > .fxb-image-is-ratio-size:not(.fxb-image-max-width-set) { align-self: stretch; }`
					: ''}

				{'' !== verticalGap
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner { row-gap: ${verticalGap}; }`
					: ''}

				{previewJustify
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal, .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal-reverse { justify-content: ${previewJustify}; }`
					: ''}
				{previewJustify
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical, .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical-reverse { align-items: ${previewJustify}; }`
					: ''}

				{previewJustify
					? `.flxbk-column-${uniqueID}.wp-block-flx-column > .flxbk-inner-column-direction-vertical > *, .flxbk-column-${uniqueID}.wp-block-flx-column > .flxbk-inner-column-direction-vertical-reverse > * { max-width: 100%; }`
					: ''}

				{previewJustify
					? `.flxbk-column-${uniqueID}.wp-block-flx-column > .flxbk-inner-column-direction-vertical > .block-editor-block-list__block.wp-block.fxb-gallery-container.wp-block-flx-advancedgallery, .flxbk-column-${uniqueID}.wp-block-flx-column > .flxbk-inner-column-direction-vertical-reverse > .block-editor-block-list__block.wp-block.fxb-gallery-container.wp-block-flx-advancedgallery { align-self:stretch; }`
					: ''}

				{previewVerticalAlignCSS
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical, .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-vertical-reverse { justify-content: ${previewVerticalAlignCSS}; }`
					: ''}
				{previewVerticalAlignCSS
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal, .flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal-reverse { align-items: ${previewVerticalAlignCSS}; }`
					: ''}

				{previewWrap
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-direction-horizontal { flex-wrap: ${previewWrap}; }`
					: ''}
				{textColorHover
					? `.flxbk-column-${uniqueID}:hover, .flxbk-column-${uniqueID}:hover .fxt-svg-icon-list-item-wrap, .flxbk-column-${uniqueID}:hover p, .flxbk-column-${uniqueID}:hover h1, .flxbk-column-${uniqueID}:hover h2, .flxbk-column-${uniqueID}:hover h3, .flxbk-column-${uniqueID}:hover h4, .flxbk-column-${uniqueID}:hover h5, .flxbk-column-${uniqueID}:hover h6 { color: ${FlxbkColorOutput(
							textColorHover
						)}; }`
					: ''}
				{linkColorHover
					? `.flxbk-column-${uniqueID}:hover a { color: ${FlxbkColorOutput(linkColorHover)}; }`
					: ''}
				{linkHoverColorHover
					? `.flxbk-column-${uniqueID}:hover a:hover { color: ${FlxbkColorOutput(linkHoverColorHover)}; }`
					: ''}
				{backgroundHover
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner { background-color: ${FlxbkColorOutput(
							backgroundHover
						)}!important; }`
					: ''}
				{previewHoverBackground
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner { background-image:${previewHoverBackground} !important; }`
					: ''}
				{!previewHoverBackground && backgroundHover
					? `.flxbk-column-${uniqueID}:hover > .flxbk-inner-column-inner { background-image:none!important; }`
					: ''}
				{hasHoverBackgroundImage && backgroundImgHover[0].bgImgPosition
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { background-position:${backgroundImgHover[0].bgImgPosition} !important; }`
					: ''}
				{hasHoverBackgroundImage && backgroundImgHover[0].bgImgSize
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { background-size:${backgroundImgHover[0].bgImgSize} !important; }`
					: ''}
				{hasHoverBackgroundImage && backgroundImgHover[0].bgImgRepeat
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { background-repeat:${backgroundImgHover[0].bgImgRepeat} !important; }`
					: ''}
				{hasHoverBackgroundImage && backgroundImgHover[0].bgImgAttachment
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { background-attachment:${backgroundImgHover[0].bgImgAttachment} !important; }`
					: ''}
				{previewBorderHoverTopStyle
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-top:${previewBorderHoverTopStyle} !important; }`
					: ''}
				{previewBorderHoverRightStyle
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-right:${previewBorderHoverRightStyle} !important; }`
					: ''}
				{previewBorderHoverBottomStyle
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-bottom:${previewBorderHoverBottomStyle} !important; }`
					: ''}
				{previewBorderHoverLeftStyle
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-left:${previewBorderHoverLeftStyle} !important; }`
					: ''}

				{previewHoverRadiusTop
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-top-left-radius:${tokenDimension(
							previewHoverRadiusTop,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{previewHoverRadiusRight
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-top-right-radius:${tokenDimension(
							previewHoverRadiusRight,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{previewHoverRadiusBottom
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-bottom-right-radius:${tokenDimension(
							previewHoverRadiusBottom,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{previewHoverRadiusLeft
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { border-bottom-left-radius:${tokenDimension(
							previewHoverRadiusLeft,
							borderHoverRadiusUnit ? borderHoverRadiusUnit : 'px'
						)} !important; }`
					: ''}
				{collapseOrder && previewDevice === 'Tablet'
					? `.fxt-row-layout-row > .innerblocks-wrap > .flxbk-column-${uniqueID} { order:${collapseOrder}; }`
					: ''}
				{collapseOrder && previewDevice === 'Mobile'
					? `.flxbk-column-${uniqueID} { order:${collapseOrder} !important; }`
					: ''}

				{displayHoverShadow &&
				undefined !== shadowHover &&
				undefined !== shadowHover[0] &&
				undefined !== shadowHover[0].color
					? `.flxbk-column-${uniqueID} > .flxbk-inner-column-inner:hover { box-shadow:${
							(undefined !== shadowHover[0].inset && shadowHover[0].inset ? 'inset ' : '') +
							(undefined !== shadowHover[0].hOffset ? shadowHover[0].hOffset : 0) +
							'px ' +
							(undefined !== shadowHover[0].vOffset ? shadowHover[0].vOffset : 0) +
							'px ' +
							(undefined !== shadowHover[0].blur ? shadowHover[0].blur : 14) +
							'px ' +
							(undefined !== shadowHover[0].spread ? shadowHover[0].spread : 0) +
							'px ' +
							FlxbkColorOutput(
								undefined !== shadowHover[0].color ? shadowHover[0].color : '#000000',
								undefined !== shadowHover[0].opacity ? shadowHover[0].opacity : 1
							)
						} !important; }`
					: ''}
				{flxbkBlockCSS && <>{flxbkBlockCSS.replace(/selector/g, `.flxbk-column-${uniqueID}`)}</>}
			</style>
			{showSettings('allSettings', 'flx/column') && (
				<>
					<BlockControls>
						{!inRowBlock && (
							<BlockAlignmentToolbar
								value={align}
								controls={['wide', 'full']}
								onChange={(value) => setAttributes({ align: value })}
							/>
						)}
						<BlockVerticalAlignmentToolbar
							value={actualVerticalAlign === 'middle' ? 'center' : actualVerticalAlign}
							controls={['top', 'center', 'bottom', 'stretch']}
							onChange={(value) => {
								if (value === 'center') {
									setAttributes({ verticalAlignment: 'middle' });
								} else if (value === 'bottom') {
									setAttributes({ verticalAlignment: 'bottom' });
								} else if (value === 'top') {
									setAttributes({ verticalAlignment: 'top' });
								} else if (value === 'stretch') {
									setAttributes({ verticalAlignment: 'stretch' });
								} else {
									setAttributes({ verticalAlignment: '' });
								}
							}}
						/>
						<CopyPasteAttributes
							attributes={attributes}
							excludedAttrs={nonTransAttrs}
							defaultAttributes={metadata.attributes}
							blockSlug={metadata.name}
							onPaste={(attributesToPaste) => setAttributes(attributesToPaste)}
						/>
					</BlockControls>
					<InspectorControls>
						<InspectorControlTabs
							panelName={'column'}
							setActiveTab={setActiveTab}
							activeTab={activeTab}
							allowedTabs={isSiteStylesPanel ? ['style', 'advanced'] : null}
							initialOpen={isSiteStylesPanel ? 'style' : undefined}
						/>
						{activeTab === 'general' && !isSiteStylesPanel && (
							<>
								{showSettings('textAlign', 'flx/column') && (
									<FlxbkPanelBody
										title={__('Flex Settings', 'flx-blocks')}
										panelName={'fxb-col-flex-settings'}
									>
										<ResponsiveAlignControls
											label={__('Direction', 'flx-blocks')}
											value={direction && direction[0] ? direction[0] : 'vertical'}
											tabletValue={direction && direction[1] ? direction[1] : ''}
											mobileValue={direction && direction[2] ? direction[2] : ''}
											onChange={(value) => {
												if (value) {
													setAttributes({
														direction: [
															value,
															undefined !== direction?.[1] ? direction[1] : '',
															undefined !== direction?.[2] ? direction[2] : '',
														],
													});
												}
											}}
											onChangeTablet={(value) => {
												let tempValue = value;
												if (direction && direction[1] && tempValue === direction[1]) {
													tempValue = '';
												}
												setAttributes({
													direction: [
														undefined !== direction?.[0] ? direction[0] : '',
														tempValue,
														undefined !== direction?.[2] ? direction[2] : '',
													],
												});
											}}
											onChangeMobile={(value) => {
												let tempValue = value;
												if (direction && direction[2] && tempValue === direction[2]) {
													tempValue = '';
												}
												setAttributes({
													direction: [
														undefined !== direction?.[0] ? direction[0] : '',
														undefined !== direction?.[1] ? direction[1] : '',
														tempValue,
													],
												});
											}}
											type={'orientation-column'}
										/>
										{(previewDirection === 'horizontal-reverse' ||
											previewDirection === 'horizontal') && (
											<ResponsiveAlignControls
												label={__('Alignment', 'flx-blocks')}
												value={justifyContent && justifyContent?.[0] ? justifyContent[0] : ''}
												tabletValue={
													justifyContent && justifyContent?.[1] ? justifyContent[1] : ''
												}
												mobileValue={
													justifyContent && justifyContent?.[2] ? justifyContent[2] : ''
												}
												onChange={(value) => {
													let tempValue = value;
													if (
														(justifyContent && justifyContent?.[0]
															? justifyContent[0]
															: '') === value
													) {
														tempValue = '';
													}
													setAttributes({
														justifyContent: [
															tempValue,
															justifyContent && justifyContent[1]
																? justifyContent[1]
																: '',
															justifyContent && justifyContent[2]
																? justifyContent[2]
																: '',
														],
													});
												}}
												onChangeTablet={(value) => {
													let tempValue = value;
													if (
														(justifyContent && justifyContent?.[1]
															? justifyContent[1]
															: '') === value
													) {
														tempValue = '';
													}
													setAttributes({
														justifyContent: [
															justifyContent && justifyContent?.[0]
																? justifyContent[0]
																: '',
															tempValue,
															justifyContent && justifyContent[2]
																? justifyContent[2]
																: '',
														],
													});
												}}
												onChangeMobile={(value) => {
													let tempValue = value;
													if (
														(justifyContent && justifyContent?.[2]
															? justifyContent[2]
															: '') === value
													) {
														tempValue = '';
													}
													setAttributes({
														justifyContent: [
															justifyContent && justifyContent?.[0]
																? justifyContent[0]
																: '',
															justifyContent && justifyContent[1]
																? justifyContent[1]
																: '',
															tempValue,
														],
													});
												}}
												type={'justify-column'}
												reverse={previewDirection === 'horizontal-reverse' ? true : false}
											/>
										)}
										{(previewDirection === 'vertical-reverse' ||
											previewDirection === 'vertical') && (
											<ResponsiveAlignControls
												label={__('Alignment', 'flx-blocks')}
												value={justifyContent && justifyContent?.[0] ? justifyContent[0] : ''}
												tabletValue={
													justifyContent && justifyContent?.[1] ? justifyContent[1] : ''
												}
												mobileValue={
													justifyContent && justifyContent?.[2] ? justifyContent[2] : ''
												}
												onChange={(value) => {
													let tempValue = value;
													if (
														(justifyContent && justifyContent?.[0]
															? justifyContent[0]
															: '') === value
													) {
														tempValue = '';
													}
													setAttributes({
														justifyContent: [
															tempValue,
															justifyContent && justifyContent[1]
																? justifyContent[1]
																: '',
															justifyContent && justifyContent[2]
																? justifyContent[2]
																: '',
														],
													});
												}}
												onChangeTablet={(value) => {
													let tempValue = value;
													if (
														(justifyContent && justifyContent?.[1]
															? justifyContent[1]
															: '') === value
													) {
														tempValue = '';
													}
													setAttributes({
														justifyContent: [
															justifyContent && justifyContent?.[0]
																? justifyContent[0]
																: '',
															tempValue,
															justifyContent && justifyContent[2]
																? justifyContent[2]
																: '',
														],
													});
												}}
												onChangeMobile={(value) => {
													let tempValue = value;
													if (
														(justifyContent && justifyContent?.[2]
															? justifyContent[2]
															: '') === value
													) {
														tempValue = '';
													}
													setAttributes({
														justifyContent: [
															justifyContent && justifyContent?.[0]
																? justifyContent[0]
																: '',
															justifyContent && justifyContent[1]
																? justifyContent[1]
																: '',
															tempValue,
														],
													});
												}}
												type={'justify-align'}
											/>
										)}
										{(previewDirection === 'horizontal-reverse' ||
											previewDirection === 'horizontal') && (
											<ResponsiveAlignControls
												label={__('Vertical Alignment', 'flx-blocks')}
												value={verticalAlignment ? verticalAlignment : ''}
												tabletValue={verticalAlignmentTablet ? verticalAlignmentTablet : ''}
												mobileValue={verticalAlignmentMobile ? verticalAlignmentMobile : ''}
												onChange={(value) => {
													let tempValue = value;
													if ((verticalAlignment ? verticalAlignment : '') === value) {
														tempValue = '';
													}
													setAttributes({ verticalAlignment: tempValue });
												}}
												onChangeTablet={(value) => {
													let tempValue = value;
													if (
														(verticalAlignmentTablet ? verticalAlignmentTablet : '') ===
														value
													) {
														tempValue = '';
													}
													setAttributes({ verticalAlignmentTablet: tempValue });
												}}
												onChangeMobile={(value) => {
													let tempValue = value;
													if (
														(verticalAlignmentMobile ? verticalAlignmentMobile : '') ===
														value
													) {
														tempValue = '';
													}
													setAttributes({ verticalAlignmentMobile: tempValue });
												}}
												type={'vertical-column'}
											/>
										)}
										{(previewDirection === 'vertical-reverse' ||
											previewDirection === 'vertical') && (
											<ResponsiveAlignControls
												label={__('Vertical Alignment', 'flx-blocks')}
												value={verticalAlignment ? verticalAlignment : ''}
												tabletValue={verticalAlignmentTablet ? verticalAlignmentTablet : ''}
												mobileValue={verticalAlignmentMobile ? verticalAlignmentMobile : ''}
												onChange={(value) => {
													let tempValue = value;
													if ((verticalAlignment ? verticalAlignment : '') === value) {
														tempValue = '';
													}
													setAttributes({ verticalAlignment: tempValue });
												}}
												onChangeTablet={(value) => {
													let tempValue = value;
													if (
														(verticalAlignmentTablet ? verticalAlignmentTablet : '') ===
														value
													) {
														tempValue = '';
													}
													setAttributes({ verticalAlignmentTablet: tempValue });
												}}
												onChangeMobile={(value) => {
													let tempValue = value;
													if (
														(verticalAlignmentMobile ? verticalAlignmentMobile : '') ===
														value
													) {
														tempValue = '';
													}
													setAttributes({ verticalAlignmentMobile: tempValue });
												}}
												type={'justify-vertical'}
												reverse={previewDirection === 'vertical-reverse' ? true : false}
											/>
										)}
										{previewDirection === 'horizontal-reverse' ||
										previewDirection === 'horizontal' ? (
											<>
												<ResponsiveRadioRangeControls
													label={__('Horizontal Gap', 'flx-blocks')}
													options={[
														{ value: 'none', size: 0, label: __('None', 'flx-blocks') },
														{ value: 'sm', size: 16, label: __('Sm', 'flx-blocks') },
														{ value: 'md', size: 32, label: __('Md', 'flx-blocks') },
														{ value: 'lg', size: 64, label: __('Lg', 'flx-blocks') },
													]}
													value={{
														value:
															undefined !== gutterVariable?.[0] &&
															'' !== gutterVariable?.[0]
																? gutterVariable[0]
																: 'sm',
														size:
															gutter &&
															undefined !== gutter?.[0] &&
															null !== gutter?.[0] &&
															'' !== gutter?.[0]
																? gutter[0]
																: '',
													}}
													onChange={(value, size) => {
														setAttributes({
															gutterVariable: [
																value,
																gutterVariable?.[1] ? gutterVariable[1] : '',
																gutterVariable?.[2] ? gutterVariable[2] : '',
															],
															gutter: [
																size,
																gutter?.[1] ? gutter[1] : '',
																gutter?.[2] ? gutter[2] : '',
															],
														});
													}}
													tabletValue={{
														value:
															undefined !== gutterVariable?.[1] ? gutterVariable[1] : '',
														size:
															gutter &&
															undefined !== gutter?.[1] &&
															null !== gutter?.[1] &&
															'' !== gutter?.[1]
																? gutter[1]
																: '',
													}}
													onChangeTablet={(value, size) => {
														setAttributes({
															gutterVariable: [
																gutterVariable?.[0] ? gutterVariable[0] : '',
																value,
																gutterVariable?.[2] ? gutterVariable[2] : '',
															],
															gutter: [
																gutter?.[0] ? gutter[0] : '',
																size,
																gutter?.[2] ? gutter[2] : '',
															],
														});
													}}
													mobileValue={{
														value:
															undefined !== gutterVariable?.[2] ? gutterVariable[2] : '',
														size:
															gutter &&
															undefined !== gutter?.[2] &&
															null !== gutter?.[2] &&
															'' !== gutter?.[2]
																? gutter[2]
																: '',
													}}
													onChangeMobile={(value, size) => {
														setAttributes({
															gutterVariable: [
																gutterVariable?.[0] ? gutterVariable[0] : '',
																gutterVariable?.[1] ? gutterVariable[1] : '',
																value,
															],
															gutter: [
																gutter?.[0] ? gutter[0] : '',
																gutter?.[1] ? gutter[1] : '',
																size,
															],
														});
													}}
													min={0}
													max={gutterUnit !== 'px' ? 12 : 200}
													step={gutterUnit !== 'px' ? 0.1 : 1}
													unit={gutterUnit ? gutterUnit : 'px'}
													onUnit={(value) => {
														setAttributes({ gutterUnit: value });
													}}
													units={['px', 'em', 'rem', '%', 'vh']}
													defaultValue={'sm'}
													reset={true}
												/>
												<ResponsiveRadioRangeControls
													label={__('Vertical Gap', 'flx-blocks')}
													options={[
														{ value: 'none', size: 0, label: __('None', 'flx-blocks') },
														{ value: 'sm', size: 16, label: __('Sm', 'flx-blocks') },
														{ value: 'md', size: 32, label: __('Md', 'flx-blocks') },
														{ value: 'lg', size: 64, label: __('Lg', 'flx-blocks') },
													]}
													value={{
														value:
															undefined !== rowGapVariable?.[0]
																? rowGapVariable[0]
																: 'none',
														size: undefined !== rowGap?.[0] ? rowGap[0] : 0,
													}}
													onChange={(value, size) => {
														setAttributes({
															rowGapVariable: [
																value,
																undefined !== rowGapVariable?.[1]
																	? rowGapVariable[1]
																	: '',
																undefined !== rowGapVariable?.[2]
																	? rowGapVariable[2]
																	: '',
															],
															rowGap: [
																size,
																rowGap?.[1] ? rowGap[1] : '',
																rowGap?.[2] ? rowGap[2] : '',
															],
														});
													}}
													tabletValue={{
														value:
															undefined !== rowGapVariable?.[1] ? rowGapVariable[1] : '',
														size: undefined !== rowGap?.[1] ? rowGap[1] : '',
													}}
													onChangeTablet={(value, size) => {
														setAttributes({
															rowGapVariable: [
																undefined !== rowGapVariable?.[0]
																	? rowGapVariable[0]
																	: '',
																value,
																undefined !== rowGapVariable?.[2]
																	? rowGapVariable[2]
																	: '',
															],
															rowGap: [
																rowGap?.[0] ? rowGap[0] : 10,
																size,
																rowGap?.[2] ? rowGap[2] : '',
															],
														});
													}}
													mobileValue={{
														value:
															undefined !== rowGapVariable?.[2] ? rowGapVariable[2] : '',
														size: undefined !== rowGap?.[2] ? rowGap[2] : '',
													}}
													onChangeMobile={(value, size) => {
														setAttributes({
															rowGapVariable: [
																undefined !== rowGapVariable?.[0]
																	? rowGapVariable[0]
																	: '',
																undefined !== rowGapVariable?.[1]
																	? rowGapVariable[1]
																	: '',
																value,
															],
															rowGap: [
																rowGap?.[0] ? rowGap[0] : 10,
																rowGap?.[2] ? rowGap[2] : '',
																size,
															],
														});
													}}
													min={0}
													max={rowGapUnit !== 'px' ? 12 : 200}
													step={rowGapUnit !== 'px' ? 0.1 : 1}
													unit={rowGapUnit ? rowGapUnit : 'px'}
													onUnit={(value) => {
														setAttributes({ rowGapUnit: value });
													}}
													units={['px', 'em', 'rem', '%', 'vh']}
													defaultValue={metadata.attributes.rowGapVariable.default[0]}
													reset={true}
												/>
												<SmallResponsiveControl
													label={__('Wrap Content', 'flx-blocks')}
													desktopChildren={
														<SelectControl
															value={wrapContent && wrapContent[0] ? wrapContent[0] : ''}
															options={[
																{ value: '', label: __('Inherit', 'flx-blocks') },
																{
																	value: 'nowrap',
																	label: __('No Wrap', 'flx-blocks'),
																},
																{ value: 'wrap', label: __('Wrap', 'flx-blocks') },
																{
																	value: 'wrap-reverse',
																	label: __('Wrap Reverse', 'flx-blocks'),
																},
															]}
															onChange={(value) =>
																setAttributes({
																	wrapContent: [
																		value,
																		wrapContent && wrapContent[1]
																			? wrapContent[1]
																			: '',
																		wrapContent && wrapContent[2]
																			? wrapContent[2]
																			: '',
																	],
																})
															}
														/>
													}
													tabletChildren={
														<SelectControl
															value={wrapContent && wrapContent[1] ? wrapContent[1] : ''}
															options={[
																{ value: '', label: __('Inherit', 'flx-blocks') },
																{
																	value: 'nowrap',
																	label: __('No Wrap', 'flx-blocks'),
																},
																{ value: 'wrap', label: __('Wrap', 'flx-blocks') },
																{
																	value: 'wrap-reverse',
																	label: __('Wrap Reverse', 'flx-blocks'),
																},
															]}
															onChange={(value) =>
																setAttributes({
																	wrapContent: [
																		wrapContent && wrapContent[0]
																			? wrapContent[0]
																			: '',
																		value,
																		wrapContent && wrapContent[2]
																			? wrapContent[2]
																			: '',
																	],
																})
															}
														/>
													}
													mobileChildren={
														<SelectControl
															value={wrapContent && wrapContent[2] ? wrapContent[2] : ''}
															options={[
																{ value: '', label: __('Inherit', 'flx-blocks') },
																{
																	value: 'nowrap',
																	label: __('No Wrap', 'flx-blocks'),
																},
																{ value: 'wrap', label: __('Wrap', 'flx-blocks') },
																{
																	value: 'wrap-reverse',
																	label: __('Wrap Reverse', 'flx-blocks'),
																},
															]}
															onChange={(value) =>
																setAttributes({
																	wrapContent: [
																		wrapContent && wrapContent[0]
																			? wrapContent[0]
																			: '',
																		wrapContent && wrapContent[1]
																			? wrapContent[1]
																			: '',
																		value,
																	],
																})
															}
														/>
													}
												/>

												{wrapContent !== 'nowrap' && wrapContent !== '' && (
													<ResponsiveRangeControls
														label={__('Flex Basis', 'flx-blocks')}
														value={flexBasis && '' !== flexBasis[0] ? flexBasis[0] : ''}
														onChange={(value) =>
															setAttributes({
																flexBasis: [
																	value,
																	flexBasis && flexBasis[1] ? flexBasis[1] : '',
																	flexBasis && flexBasis[2] ? flexBasis[2] : '',
																],
															})
														}
														tabletValue={
															flexBasis && '' !== flexBasis[1] ? flexBasis[1] : ''
														}
														onChangeTablet={(value) =>
															setAttributes({
																flexBasis: [
																	flexBasis && flexBasis[0] ? flexBasis[0] : 10,
																	value,
																	flexBasis && flexBasis[2] ? flexBasis[2] : '',
																],
															})
														}
														mobileValue={
															flexBasis && '' !== flexBasis[2] ? flexBasis[2] : ''
														}
														onChangeMobile={(value) =>
															setAttributes({
																flexBasis: [
																	flexBasis && flexBasis[0] ? flexBasis[0] : 10,
																	flexBasis && flexBasis[2] ? flexBasis[2] : '',
																	value,
																],
															})
														}
														min={0}
														max={flexBasisUnit === '%' ? 100 : 1000}
														step={flexBasisUnit === '%' ? 0.1 : 1}
														unit={flexBasisUnit}
														onUnit={(value) => setAttributes({ flexBasisUnit: value })}
														units={['%', 'px']}
													/>
												)}
											</>
										) : (
											<>
												<ResponsiveRadioRangeControls
													label={__('Vertical Gap', 'flx-blocks')}
													options={[
														{ value: 'none', size: 0, label: __('None', 'flx-blocks') },
														{ value: 'sm', size: 16, label: __('Sm', 'flx-blocks') },
														{ value: 'md', size: 32, label: __('Md', 'flx-blocks') },
														{ value: 'lg', size: 64, label: __('Lg', 'flx-blocks') },
													]}
													value={{
														value:
															undefined !== rowGapVariable?.[0]
																? rowGapVariable[0]
																: 'none',
														size: undefined !== rowGap?.[0] ? rowGap[0] : 0,
													}}
													onChange={(value, size) => {
														setAttributes({
															rowGapVariable: [
																value,
																undefined !== rowGapVariable?.[1]
																	? rowGapVariable[1]
																	: '',
																undefined !== rowGapVariable?.[2]
																	? rowGapVariable[2]
																	: '',
															],
															rowGap: [
																size,
																rowGap?.[1] ? rowGap[1] : '',
																rowGap?.[2] ? rowGap[2] : '',
															],
														});
													}}
													tabletValue={{
														value:
															undefined !== rowGapVariable?.[1] ? rowGapVariable[1] : '',
														size: undefined !== rowGap?.[1] ? rowGap[1] : '',
													}}
													onChangeTablet={(value, size) => {
														setAttributes({
															rowGapVariable: [
																undefined !== rowGapVariable?.[0]
																	? rowGapVariable[0]
																	: '',
																value,
																undefined !== rowGapVariable?.[2]
																	? rowGapVariable[2]
																	: '',
															],
															rowGap: [
																rowGap?.[0] ? rowGap[0] : 10,
																size,
																rowGap?.[2] ? rowGap[2] : '',
															],
														});
													}}
													mobileValue={{
														value:
															undefined !== rowGapVariable?.[2] ? rowGapVariable[2] : '',
														size: undefined !== rowGap?.[2] ? rowGap[2] : '',
													}}
													onChangeMobile={(value, size) => {
														setAttributes({
															rowGapVariable: [
																undefined !== rowGapVariable?.[0]
																	? rowGapVariable[0]
																	: '',
																undefined !== rowGapVariable?.[1]
																	? rowGapVariable[1]
																	: '',
																value,
															],
															rowGap: [
																rowGap?.[0] ? rowGap[0] : 10,
																rowGap?.[2] ? rowGap[2] : '',
																size,
															],
														});
													}}
													min={0}
													max={rowGapUnit !== 'px' ? 12 : 200}
													step={rowGapUnit !== 'px' ? 0.1 : 1}
													unit={rowGapUnit ? rowGapUnit : 'px'}
													onUnit={(value) => {
														setAttributes({ rowGapUnit: value });
													}}
													units={['px', 'em', 'rem', '%', 'vh']}
													defaultValue={metadata.attributes.rowGapVariable.default[0]}
													reset={true}
												/>
											</>
										)}
										<ResponsiveAlignControls
											label={__('Text Alignment', 'flx-blocks')}
											value={textAlign && textAlign[0] ? textAlign[0] : ''}
											mobileValue={textAlign && textAlign[2] ? textAlign[2] : ''}
											tabletValue={textAlign && textAlign[1] ? textAlign[1] : ''}
											onChange={(nextAlign) =>
												setAttributes({
													textAlign: [
														nextAlign,
														textAlign && textAlign[1] ? textAlign[1] : '',
														textAlign && textAlign[2] ? textAlign[2] : '',
													],
												})
											}
											onChangeTablet={(nextAlign) =>
												setAttributes({
													textAlign: [
														textAlign && textAlign[0] ? textAlign[0] : '',
														nextAlign,
														textAlign && textAlign[2] ? textAlign[2] : '',
													],
												})
											}
											onChangeMobile={(nextAlign) =>
												setAttributes({
													textAlign: [
														textAlign && textAlign[0] ? textAlign[0] : '',
														textAlign && textAlign[1] ? textAlign[1] : '',
														nextAlign,
													],
												})
											}
										/>
									</FlxbkPanelBody>
								)}
								{!inRowBlock && showSettings('container', 'flx/column') && (
									<FlxbkPanelBody
										title={__('Content Max Width', 'flx-blocks')}
										initialOpen={false}
										panelName={'fxb-col-width-style-settings'}
									>
										<ResponsiveRangeControls
											label={__('Max Width', 'flx-blocks')}
											value={
												undefined !== maxWidth && undefined !== maxWidth[0] ? maxWidth[0] : ''
											}
											onChange={(value) => {
												setAttributes({
													maxWidth: [
														value,
														undefined !== maxWidth && undefined !== maxWidth[1]
															? maxWidth[1]
															: '',
														undefined !== maxWidth && undefined !== maxWidth[2]
															? maxWidth[2]
															: '',
													],
												});
											}}
											tabletValue={
												undefined !== maxWidth && undefined !== maxWidth[1] ? maxWidth[1] : ''
											}
											onChangeTablet={(value) => {
												setAttributes({
													maxWidth: [
														undefined !== maxWidth && undefined !== maxWidth[0]
															? maxWidth[0]
															: '',
														value,
														undefined !== maxWidth && undefined !== maxWidth[2]
															? maxWidth[2]
															: '',
													],
												});
											}}
											mobileValue={
												undefined !== maxWidth && undefined !== maxWidth[2] ? maxWidth[2] : ''
											}
											onChangeMobile={(value) => {
												setAttributes({
													maxWidth: [
														undefined !== maxWidth && undefined !== maxWidth[0]
															? maxWidth[0]
															: '',
														undefined !== maxWidth && undefined !== maxWidth[1]
															? maxWidth[1]
															: '',
														value,
													],
												});
											}}
											min={0}
											max={previewMaxWidthUnit === 'px' ? 2000 : 100}
											step={1}
											unit={previewMaxWidthUnit}
											allowResponsiveUnitChange={true}
											onUnit={(value) => {
												const device = 'Desktop' === previewDevice ? '' : previewDevice;
												setAttributes({ ['maxWidth' + device + 'Unit']: value });
											}}
											units={['px', '%', 'vw']}
											reset={() => {
												setAttributes({
													maxWidth: ['', '', ''],
												});
											}}
										/>
									</FlxbkPanelBody>
								)}

								{showSettings('overlayLink', 'flx/column') && (
									<FlxbkPanelBody
										title={__('Overlay Link', 'flx-blocks')}
										initialOpen={false}
										panelName={'fxb-col-overlay-link'}
									>
										<p className="flxbk-sidebar-notice">
											{__(
												'Please note, If a link is added nothing else inside of the section will be selectable.',
												'flx-blocks'
											)}
										</p>
										<URLInputControl
											label={__('Link entire section', 'flx-blocks')}
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
											isSelected={isSelected}
											attributes={attributes}
											setAttributes={setAttributes}
											name={'flx/column'}
											clientId={clientId}
											context={context}
										/>
										<DynamicTextInputControl
											label={__('Link Title', 'flx-blocks')}
											value={linkTitle}
											onChange={(value) => setAttributes({ linkTitle: value })}
											dynamicAttribute={'linkTitle'}
											allowClear={true}
											isSelected={isSelected}
											attributes={attributes}
											setAttributes={setAttributes}
											name={'flx/column'}
											clientId={clientId}
											context={context}
										/>
									</FlxbkPanelBody>
								)}
							</>
						)}

						{activeTab === 'advanced' && (
							<>
								{showSettings('paddingMargin', 'flx/column') && (
									<FlxbkPanelBody panelName={'fxb-column-padding'}>
										<ResponsiveMeasureRangeControl
											label={__('Padding', 'flx-blocks')}
											value={padding}
											tabletValue={tabletPadding}
											mobileValue={mobilePadding}
											onChange={(value) => {
												setAttributes({ padding: value });
											}}
											onChangeTablet={(value) => {
												setAttributes({ tabletPadding: value });
											}}
											onChangeMobile={(value) => {
												setAttributes({ mobilePadding: value });
											}}
											min={0}
											max={paddingType === 'em' || paddingType === 'rem' ? 24 : 999}
											step={paddingType === 'em' || paddingType === 'rem' ? 0.1 : 1}
											unit={paddingType}
											units={['px', 'em', 'rem', '%', 'vh', 'vw']}
											onUnit={(value) => setAttributes({ paddingType: value })}
											onMouseOver={paddingMouseOver.onMouseOver}
											onMouseOut={paddingMouseOver.onMouseOut}
										/>
										{!isSiteStylesPanel && (
											<ResponsiveMeasureRangeControl
												label={__('Margin', 'flx-blocks')}
												value={margin}
												tabletValue={tabletMargin}
												mobileValue={mobileMargin}
												onChange={(value) => {
													setAttributes({ margin: value });
												}}
												onChangeTablet={(value) => {
													setAttributes({ tabletMargin: value });
												}}
												onChangeMobile={(value) => {
													setAttributes({ mobileMargin: value });
												}}
												min={marginType === 'em' || marginType === 'rem' ? -24 : -999}
												max={marginType === 'em' || marginType === 'rem' ? 24 : 999}
												step={marginType === 'em' || marginType === 'rem' ? 0.1 : 1}
												unit={marginType}
												units={['px', 'em', 'rem', '%', 'vh', 'vw']}
												onUnit={(value) => setAttributes({ marginType: value })}
												onMouseOver={marginMouseOver.onMouseOver}
												onMouseOut={marginMouseOver.onMouseOut}
												allowAuto={true}
											/>
										)}
									</FlxbkPanelBody>
								)}
								<div className="fxt-sidebar-settings-spacer"></div>
								{showSettings('container', 'flx/column') && (
									<FlxbkPanelBody
										title={__('Structure Settings', 'flx-blocks')}
										initialOpen={false}
										panelName={'fxb-col-container-style-settings'}
									>
										{!isSiteStylesPanel && (
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
										)}
										<ResponsiveRangeControls
											label={__('Min Height', 'flx-blocks')}
											value={undefined !== height && undefined !== height[0] ? height[0] : ''}
											onChange={(value) => {
												setAttributes({
													height: [
														value,
														undefined !== height && undefined !== height[1]
															? height[1]
															: '',
														undefined !== height && undefined !== height[2]
															? height[2]
															: '',
													],
												});
											}}
											tabletValue={
												undefined !== height && undefined !== height[1] ? height[1] : ''
											}
											onChangeTablet={(value) => {
												setAttributes({
													height: [
														undefined !== height && undefined !== height[0]
															? height[0]
															: '',
														value,
														undefined !== height && undefined !== height[2]
															? height[2]
															: '',
													],
												});
											}}
											mobileValue={
												undefined !== height && undefined !== height[2] ? height[2] : ''
											}
											onChangeMobile={(value) => {
												setAttributes({
													height: [
														undefined !== height && undefined !== height[0]
															? height[0]
															: '',
														undefined !== height && undefined !== height[1]
															? height[1]
															: '',
														value,
													],
												});
											}}
											min={0}
											max={heightUnit === 'px' ? 2000 : 200}
											step={1}
											unit={heightUnit ? heightUnit : 'px'}
											onUnit={(value) => {
												setAttributes({ heightUnit: value });
											}}
											units={['px', 'vw', 'vh']}
											reset={true}
										/>
										{!isSiteStylesPanel && (
											<>
												<ResponsiveRangeControls
													label={__('Flex Grow', 'flx-blocks')}
													value={
														undefined !== flexGrow && undefined !== flexGrow[0]
															? flexGrow[0]
															: ''
													}
													onChange={(value) => {
														setAttributes({
															flexGrow: [
																value,
																undefined !== flexGrow && undefined !== flexGrow[1]
																	? flexGrow[1]
																	: '',
																undefined !== flexGrow && undefined !== flexGrow[2]
																	? flexGrow[2]
																	: '',
															],
														});
													}}
													tabletValue={
														undefined !== flexGrow && undefined !== flexGrow[1]
															? flexGrow[1]
															: ''
													}
													onChangeTablet={(value) => {
														setAttributes({
															flexGrow: [
																undefined !== flexGrow && undefined !== flexGrow[0]
																	? flexGrow[0]
																	: '',
																value,
																undefined !== flexGrow && undefined !== flexGrow[2]
																	? flexGrow[2]
																	: '',
															],
														});
													}}
													mobileValue={
														undefined !== flexGrow && undefined !== flexGrow[2]
															? flexGrow[2]
															: ''
													}
													onChangeMobile={(value) => {
														setAttributes({
															flexGrow: [
																undefined !== flexGrow && undefined !== flexGrow[0]
																	? flexGrow[0]
																	: '',
																undefined !== flexGrow && undefined !== flexGrow[1]
																	? flexGrow[1]
																	: '',
																value,
															],
														});
													}}
													min={0}
													max={200}
													step={1}
													showUnits={false}
													reset={true}
												/>
												<RangeControl
													label={__('Z Index Control', 'flx-blocks')}
													value={zIndex}
													onChange={(value) => {
														setAttributes({
															zIndex: value,
														});
													}}
													min={-200}
													max={200}
													reset={true}
												/>
												{inRowBlock && (
													<RangeControl
														label={__('Mobile Collapse Order', 'flx-blocks')}
														value={collapseOrder}
														onChange={(value) => {
															setAttributes({
																collapseOrder: value,
															});
														}}
														min={-10}
														max={10}
														reset={true}
													/>
												)}
											</>
										)}
									</FlxbkPanelBody>
								)}
								{!isSiteStylesPanel && (
									<>
										<FlxbkPanelBody
											title={__('Sticky Settings', 'flx-blocks')}
											initialOpen={false}
											panelName={'fxb-col-sticky-settings'}
										>
											<ToggleControl
												label={__('Make sticky', 'flx-blocks')}
												help={__(
													'This will stick the section to viewport for the height of outer container.',
													'flx-blocks'
												)}
												checked={undefined !== sticky ? sticky : false}
												onChange={(value) => setAttributes({ sticky: value })}
											/>
											{sticky && (
												<ResponsiveRangeControls
													label={__('Sticky Header Offset', 'flx-blocks')}
													value={
														undefined !== stickyOffset && undefined !== stickyOffset[0]
															? stickyOffset[0]
															: ''
													}
													onChange={(value) => {
														setAttributes({
															stickyOffset: [
																value,
																undefined !== stickyOffset &&
																undefined !== stickyOffset[1]
																	? stickyOffset[1]
																	: '',
																undefined !== stickyOffset &&
																undefined !== stickyOffset[2]
																	? stickyOffset[2]
																	: '',
															],
														});
													}}
													tabletValue={
														undefined !== stickyOffset && undefined !== stickyOffset[1]
															? stickyOffset[1]
															: ''
													}
													onChangeTablet={(value) => {
														setAttributes({
															stickyOffset: [
																undefined !== stickyOffset &&
																undefined !== stickyOffset[0]
																	? stickyOffset[0]
																	: '',
																value,
																undefined !== stickyOffset &&
																undefined !== stickyOffset[2]
																	? stickyOffset[2]
																	: '',
															],
														});
													}}
													mobileValue={
														undefined !== stickyOffset && undefined !== stickyOffset[2]
															? stickyOffset[2]
															: ''
													}
													onChangeMobile={(value) => {
														setAttributes({
															stickyOffset: [
																undefined !== stickyOffset &&
																undefined !== stickyOffset[0]
																	? stickyOffset[0]
																	: '',
																undefined !== stickyOffset &&
																undefined !== stickyOffset[1]
																	? stickyOffset[1]
																	: '',
																value,
															],
														});
													}}
													min={0}
													max={stickyOffsetUnit === 'px' ? 2000 : 100}
													step={1}
													unit={stickyOffsetUnit ? stickyOffsetUnit : 'px'}
													onUnit={(value) => {
														setAttributes({ stickyOffsetUnit: value });
													}}
													units={['px', 'rem', 'vh']}
													defaultValue={1}
													reset={true}
												/>
											)}
										</FlxbkPanelBody>
										<FlxbkPanelBody
											title={__('Visibility Settings', 'flx-blocks')}
											panelName={'fxb-col-visibility-settings'}
											initialOpen={false}
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
										{inFormBlock && <FlxbkFormConditionals {...props} />}
										<FlxbkBlockDefaults
											attributes={attributes}
											defaultAttributes={metadata.attributes}
											blockSlug={metadata.name}
											excludedAttrs={nonTransAttrs}
										/>
									</>
								)}
							</>
						)}

						{activeTab === 'style' && (
							<>
								<FlxbkPanelBody
									title={__('Background', 'flx-blocks')}
									initialOpen={true}
									panelName={'fxb-col-bg-settings'}
								>
									<HoverToggleControl
										hover={
											<>
												{!isSiteStylesPanel && (
													<BackgroundTypeControl
														label={__('Hover Type', 'flx-blocks')}
														type={backgroundHoverType ? backgroundHoverType : 'normal'}
														onChange={(value) =>
															setAttributes({ backgroundHoverType: value })
														}
														allowedTypes={['normal', 'gradient']}
													/>
												)}
												{!isSiteStylesPanel && 'gradient' === backgroundHoverType && (
													<GradientControl
														value={gradientHover}
														onChange={(value) => setAttributes({ gradientHover: value })}
														gradients={[]}
													/>
												)}
												{'gradient' !== backgroundHoverType && (
													<>
														<ColorControl
															label={__('Background Color', 'flx-blocks')}
															value={backgroundHover ? backgroundHover : ''}
															groups={colorGroups}
															status={{
																bound: !!tokenBinding.backgroundHover?.bound,
																modified: !!tokenBinding.backgroundHover?.overridden,
															}}
															onReset={() => resetToken('backgroundHover')}
															onPick={(alias) =>
																setAttributes({ backgroundHover: alias })
															}
															onCustom={(literal) =>
																setAttributes({ backgroundHover: literal })
															}
															onClear={() => setAttributes({ backgroundHover: '' })}
															resolveLiteral={resolveColorLiteral}
														/>
														{!isSiteStylesPanel && (
															<FlxbkBackgroundControl
																label={__('Background Image', 'flx-blocks')}
																hasImage={hasHoverBackgroundImage}
																imageURL={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImg
																		? backgroundImgHover[0].bgImg
																		: ''
																}
																imageID={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImgID
																		? backgroundImgHover[0].bgImgID
																		: ''
																}
																imagePosition={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImgPosition
																		? backgroundImgHover[0].bgImgPosition
																		: 'center center'
																}
																imageSize={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImgSize
																		? backgroundImgHover[0].bgImgSize
																		: 'cover'
																}
																imageRepeat={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImgRepeat
																		? backgroundImgHover[0].bgImgRepeat
																		: 'no-repeat'
																}
																imageAttachment={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImgAttachment
																		? backgroundImgHover[0].bgImgAttachment
																		: 'scroll'
																}
																onRemoveImage={onRemoveHoverBGImage}
																onSaveImage={(img) => {
																	saveHoverBackgroundImage({
																		bgImgID: img.id,
																		bgImg: img.url,
																	});
																}}
																onSaveURL={(newURL) => {
																	if (
																		newURL !==
																		(backgroundImgHover &&
																		backgroundImgHover[0] &&
																		backgroundImgHover[0].bgImg
																			? backgroundImgHover[0].bgImg
																			: '')
																	) {
																		saveHoverBackgroundImage({
																			bgImgID: undefined,
																			bgImg: newURL,
																		});
																	}
																}}
																onSavePosition={(value) =>
																	saveHoverBackgroundImage({ bgImgPosition: value })
																}
																onSaveSize={(value) =>
																	saveHoverBackgroundImage({ bgImgSize: value })
																}
																onSaveRepeat={(value) =>
																	saveHoverBackgroundImage({ bgImgRepeat: value })
																}
																onSaveAttachment={(value) =>
																	saveHoverBackgroundImage({ bgImgAttachment: value })
																}
																disableMediaButtons={
																	backgroundImgHover &&
																	backgroundImgHover[0] &&
																	backgroundImgHover[0].bgImg
																		? backgroundImgHover[0].bgImg
																		: ''
																}
																dynamicAttribute="backgroundImgHover:0:bgImg"
																isSelected={isSelected}
																attributes={attributes}
																setAttributes={setAttributes}
																name={'flx/column'}
																clientId={clientId}
																context={context}
															/>
														)}
													</>
												)}
											</>
										}
										normal={
											<>
												{!isSiteStylesPanel && (
													<BackgroundTypeControl
														label={__('Type', 'flx-blocks')}
														type={backgroundType ? backgroundType : 'normal'}
														onChange={(value) => setAttributes({ backgroundType: value })}
														allowedTypes={['normal', 'gradient']}
													/>
												)}
												{!isSiteStylesPanel && 'gradient' === backgroundType && (
													<GradientControl
														value={gradient}
														onChange={(value) => setAttributes({ gradient: value })}
														gradients={[]}
													/>
												)}
												{'gradient' !== backgroundType && (
													<>
														<ColorControl
															label={__('Background Color', 'flx-blocks')}
															value={background ? background : ''}
															groups={colorGroups}
															status={{
																bound: !!tokenBinding.background?.bound,
																modified: !!tokenBinding.background?.overridden,
															}}
															onReset={() => resetToken('background')}
															onPick={(alias) => setAttributes({ background: alias })}
															onCustom={(literal) =>
																setAttributes({ background: literal })
															}
															onClear={() => setAttributes({ background: '' })}
															resolveLiteral={resolveColorLiteral}
														/>
														{/* Opacity applies to a literal color only, and that is
														    the render path's own rule rather than this control's:
														    `FlxbkColorOutput` runs the token filter FIRST and
														    returns the resolved `var()` without ever reaching the
														    `hexToRGBA` branch, so a token's own alpha is the only
														    one that can apply to it. Showing the slider for a
														    token pick would offer a setting that silently does
														    nothing. `PopColorControl` carried this as a second
														    channel inside itself; `ColorControl` is single-value,
														    so it stands beside it. */}
														{!isTokenAlias(background) && (
															<RangeControl
																label={__('Background Opacity', 'flx-blocks')}
																value={backgroundOpacity}
																onChange={(value) =>
																	setAttributes({ backgroundOpacity: value })
																}
																min={0}
																max={1}
																step={0.01}
															/>
														)}
														{!isSiteStylesPanel && (
															<FlxbkBackgroundControl
																label={__('Background Image', 'flx-blocks')}
																hasImage={hasBackgroundImage}
																imageURL={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImg
																		? backgroundImg[0].bgImg
																		: ''
																}
																imageID={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImgID
																		? backgroundImg[0].bgImgID
																		: ''
																}
																imagePosition={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImgPosition
																		? backgroundImg[0].bgImgPosition
																		: 'center center'
																}
																imageSize={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImgSize
																		? backgroundImg[0].bgImgSize
																		: 'cover'
																}
																imageRepeat={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImgRepeat
																		? backgroundImg[0].bgImgRepeat
																		: 'no-repeat'
																}
																imageAttachment={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImgAttachment
																		? backgroundImg[0].bgImgAttachment
																		: 'scroll'
																}
																onRemoveImage={onRemoveBGImage}
																onSaveImage={(img) => {
																	saveBackgroundImage({
																		bgImgID: img.id,
																		bgImg: img.url,
																	});
																}}
																onSaveURL={(newURL) => {
																	if (
																		newURL !==
																		(backgroundImg &&
																		backgroundImg[0] &&
																		backgroundImg[0].bgImg
																			? backgroundImg[0].bgImg
																			: '')
																	) {
																		saveBackgroundImage({
																			bgImgID: undefined,
																			bgImg: newURL,
																		});
																	}
																}}
																onSavePosition={(value) =>
																	saveBackgroundImage({ bgImgPosition: value })
																}
																onSaveSize={(value) =>
																	saveBackgroundImage({ bgImgSize: value })
																}
																onSaveRepeat={(value) =>
																	saveBackgroundImage({ bgImgRepeat: value })
																}
																onSaveAttachment={(value) =>
																	saveBackgroundImage({ bgImgAttachment: value })
																}
																disableMediaButtons={
																	backgroundImg &&
																	backgroundImg[0] &&
																	backgroundImg[0].bgImg
																		? backgroundImg[0].bgImg
																		: ''
																}
																dynamicAttribute="backgroundImg:0:bgImg"
																isSelected={isSelected}
																attributes={attributes}
																setAttributes={setAttributes}
																name={'flx/column'}
																clientId={clientId}
																context={context}
															/>
														)}
													</>
												)}
											</>
										}
									/>
								</FlxbkPanelBody>

								{!isSiteStylesPanel && (
									<>
										{applyFilters(
											'flxbk.styleControlsBackdropFilter',
											styleControlsBackdropFilter,
											props
										)}
									</>
								)}

								{!isSiteStylesPanel && (
									<FlxbkPanelBody
										title={__('Background Overlay', 'flx-blocks')}
										initialOpen={false}
										panelName={'fxb-col-bg-overlay-settings'}
									>
										<HoverToggleControl
											hover={
												<>
													<BackgroundTypeControl
														label={__('Hover Type', 'flx-blocks')}
														type={overlayHoverType ? overlayHoverType : 'normal'}
														onChange={(value) => setAttributes({ overlayHoverType: value })}
														allowedTypes={['normal', 'gradient']}
													/>
													<RangeControl
														label={__('Overlay Opacity', 'flx-blocks')}
														value={overlayHoverOpacity}
														onChange={(value) => {
															setAttributes({
																overlayHoverOpacity: value,
															});
														}}
														step={0.01}
														min={0}
														max={1}
														reset={true}
													/>
													{'gradient' === overlayHoverType && (
														<GradientControl
															value={overlayGradientHover}
															onChange={(value) =>
																setAttributes({ overlayGradientHover: value })
															}
															gradients={[]}
														/>
													)}
													{'gradient' !== overlayHoverType && (
														<>
															<PopColorControl
																label={__('Background Color', 'flx-blocks')}
																value={overlayHover ? overlayHover : ''}
																default={''}
																onChange={(value) =>
																	setAttributes({ overlayHover: value })
																}
															/>
															<FlxbkBackgroundControl
																label={__('Background Image', 'flx-blocks')}
																hasImage={hasHoverOverlayImage}
																imageURL={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImg
																		? overlayImgHover[0].bgImg
																		: ''
																}
																imageID={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImgID
																		? overlayImgHover[0].bgImgID
																		: ''
																}
																imagePosition={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImgPosition
																		? overlayImgHover[0].bgImgPosition
																		: 'center center'
																}
																imageSize={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImgSize
																		? overlayImgHover[0].bgImgSize
																		: 'cover'
																}
																imageRepeat={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImgRepeat
																		? overlayImgHover[0].bgImgRepeat
																		: 'no-repeat'
																}
																imageAttachment={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImgAttachment
																		? overlayImgHover[0].bgImgAttachment
																		: 'scroll'
																}
																onRemoveImage={onRemoveHoverOverlayImage}
																onSaveImage={(img) => {
																	saveHoverOverlayImage({
																		bgImgID: img.id,
																		bgImg: img.url,
																	});
																}}
																onSaveURL={(newURL) => {
																	if (
																		newURL !==
																		(overlayImgHover &&
																		overlayImgHover[0] &&
																		overlayImgHover[0].bgImg
																			? overlayImgHover[0].bgImg
																			: '')
																	) {
																		saveHoverOverlayImage({
																			bgImgID: undefined,
																			bgImg: newURL,
																		});
																	}
																}}
																onSavePosition={(value) =>
																	saveHoverOverlayImage({ bgImgPosition: value })
																}
																onSaveSize={(value) =>
																	saveHoverOverlayImage({ bgImgSize: value })
																}
																onSaveRepeat={(value) =>
																	saveHoverOverlayImage({ bgImgRepeat: value })
																}
																onSaveAttachment={(value) =>
																	saveHoverOverlayImage({ bgImgAttachment: value })
																}
																disableMediaButtons={
																	overlayImgHover &&
																	overlayImgHover[0] &&
																	overlayImgHover[0].bgImg
																		? overlayImgHover[0].bgImg
																		: ''
																}
																dynamicAttribute="overlayImgHover:0:bgImg"
																isSelected={isSelected}
																attributes={attributes}
																setAttributes={setAttributes}
																name={'flx/column'}
																clientId={clientId}
																context={context}
															/>
															<SelectControl
																label={__('Hover Blend Mode', 'flx-blocks')}
																value={
																	hoverOverlayBlendMode
																		? hoverOverlayBlendMode
																		: 'none'
																}
																options={BLEND_OPTIONS}
																onChange={(value) =>
																	setAttributes({ hoverOverlayBlendMode: value })
																}
															/>
															<p>
																{__(
																	'Notice: Blend Mode not supported in all browsers',
																	'flx-blocks'
																)}
															</p>
														</>
													)}
												</>
											}
											normal={
												<>
													<BackgroundTypeControl
														label={__('Type', 'flx-blocks')}
														type={overlayType ? overlayType : 'normal'}
														onChange={(value) => setAttributes({ overlayType: value })}
														allowedTypes={['normal', 'gradient']}
													/>
													<RangeControl
														label={__('Overlay Opacity', 'flx-blocks')}
														value={overlayOpacity}
														defaultValue={metadata.attributes.overlayOpacity.default}
														onChange={(value) => {
															setAttributes({
																overlayOpacity: value,
															});
														}}
														step={0.01}
														min={0}
														max={1}
														reset={true}
													/>
													{'gradient' === overlayType && (
														<GradientControl
															value={overlayGradient}
															onChange={(value) =>
																setAttributes({ overlayGradient: value })
															}
															gradients={[]}
														/>
													)}
													{'gradient' !== overlayType && (
														<>
															<PopColorControl
																label={__('Background Color', 'flx-blocks')}
																value={overlay ? overlay : ''}
																default={''}
																onChange={(value) => setAttributes({ overlay: value })}
															/>
															<FlxbkBackgroundControl
																label={__('Background Image', 'flx-blocks')}
																hasImage={hasOverlayImage}
																imageURL={
																	overlayImg && overlayImg[0] && overlayImg[0].bgImg
																		? overlayImg[0].bgImg
																		: ''
																}
																imageID={
																	overlayImg && overlayImg[0] && overlayImg[0].bgImgID
																		? overlayImg[0].bgImgID
																		: ''
																}
																imagePosition={
																	overlayImg &&
																	overlayImg[0] &&
																	overlayImg[0].bgImgPosition
																		? overlayImg[0].bgImgPosition
																		: 'center center'
																}
																imageSize={
																	overlayImg &&
																	overlayImg[0] &&
																	overlayImg[0].bgImgSize
																		? overlayImg[0].bgImgSize
																		: 'cover'
																}
																imageRepeat={
																	overlayImg &&
																	overlayImg[0] &&
																	overlayImg[0].bgImgRepeat
																		? overlayImg[0].bgImgRepeat
																		: 'no-repeat'
																}
																imageAttachment={
																	overlayImg &&
																	overlayImg[0] &&
																	overlayImg[0].bgImgAttachment
																		? overlayImg[0].bgImgAttachment
																		: 'scroll'
																}
																onRemoveImage={onRemoveOverlayImage}
																onSaveImage={(img) => {
																	saveOverlayImage({
																		bgImgID: img.id,
																		bgImg: img.url,
																	});
																}}
																onSaveURL={(newURL) => {
																	if (
																		newURL !==
																		(overlayImg &&
																		overlayImg[0] &&
																		overlayImg[0].bgImg
																			? overlayImg[0].bgImg
																			: '')
																	) {
																		saveOverlayImage({
																			bgImgID: undefined,
																			bgImg: newURL,
																		});
																	}
																}}
																onSavePosition={(value) =>
																	saveOverlayImage({ bgImgPosition: value })
																}
																onSaveSize={(value) =>
																	saveOverlayImage({ bgImgSize: value })
																}
																onSaveRepeat={(value) =>
																	saveOverlayImage({ bgImgRepeat: value })
																}
																onSaveAttachment={(value) =>
																	saveOverlayImage({ bgImgAttachment: value })
																}
																disableMediaButtons={
																	overlayImg && overlayImg[0] && overlayImg[0].bgImg
																		? overlayImg[0].bgImg
																		: ''
																}
																dynamicAttribute="overlayImg:0:bgImg"
																isSelected={isSelected}
																attributes={attributes}
																setAttributes={setAttributes}
																name={'flx/column'}
																clientId={clientId}
																context={context}
															/>
															<SelectControl
																label={__('Blend Mode', 'flx-blocks')}
																value={overlayBlendMode ? overlayBlendMode : 'none'}
																options={BLEND_OPTIONS}
																onChange={(value) =>
																	setAttributes({ overlayBlendMode: value })
																}
															/>
															<p>
																{__(
																	'Notice: Blend Mode not supported in all browsers',
																	'flx-blocks'
																)}
															</p>
														</>
													)}
												</>
											}
										/>
									</FlxbkPanelBody>
								)}
								<FlxbkPanelBody
									title={__('Border Styles', 'flx-blocks')}
									initialOpen={false}
									panelName={'fxb-col-border-settings'}
								>
									<HoverToggleControl
										hover={
											<>
												<ResponsiveBorderControl
													label={__('Border', 'flx-blocks')}
													value={borderHoverStyle}
													tabletValue={tabletBorderHoverStyle}
													mobileValue={mobileBorderHoverStyle}
													onChange={(value) => setAttributes({ borderHoverStyle: value })}
													onChangeTablet={(value) =>
														setAttributes({ tabletBorderHoverStyle: value })
													}
													onChangeMobile={(value) =>
														setAttributes({ mobileBorderHoverStyle: value })
													}
												/>
												{borderRadiusTokens.length ? (
													<EditorBoxControl
														label={__('Border Radius', 'flx-blocks')}
														value={borderHoverRadiusForDevice.value}
														onChange={(next) =>
															setAttributes({
																[borderHoverRadiusForDevice.attr]: next,
															})
														}
														previewDevice={previewDevice}
														onDeviceChange={setPreviewDevice}
														tokens={borderRadiusTokens}
														defaultValue={inheritedBorderHoverRadius.values}
														inherited={anyCornerInherited(
															inheritedBorderHoverRadius.inherited
														)}
														state={
															tokenBinding.borderHoverRadius ?? borderHoverRadiusBinding
														}
														// The kind is stated rather than looked up.
														// `usePresetBinding` only makes an entry for a property
														// the ACTIVE preset resolves, so `resetToken` reads
														// `undefined` on every preset that carries no hover
														// radius -- and on any site with the registry inactive.
														// That clears the desktop attribute to a bare `''`,
														// leaving the tablet/mobile companions and the unit
														// untouched and storing a string where the block declares
														// a four-side array.
														onReset={() =>
															resetAttr(
																'borderHoverRadius',
																setAttributes,
																'dimension',
																metadata.attributes
															)
														}
														isLinked={borderHoverRadiusIsLinked}
														onToggleLink={toggleBorderHoverRadiusLink}
														unit={borderHoverRadiusUnit}
														units={['px', 'em', 'rem', '%']}
														onUnit={(value) =>
															setAttributes({ borderHoverRadiusUnit: value })
														}
														min={0}
														max={borderHoverRadiusIsRelative ? 24 : 500}
														step={borderHoverRadiusIsRelative ? 0.1 : 1}
													/>
												) : (
													<ResponsiveMeasurementControls
														label={__('Border Radius', 'flx-blocks')}
														value={borderHoverRadius}
														tabletValue={tabletBorderHoverRadius}
														mobileValue={mobileBorderHoverRadius}
														onChange={(value) =>
															setAttributes({ borderHoverRadius: value })
														}
														onChangeTablet={(value) =>
															setAttributes({ tabletBorderHoverRadius: value })
														}
														onChangeMobile={(value) =>
															setAttributes({ mobileBorderHoverRadius: value })
														}
														unit={borderHoverRadiusUnit}
														units={['px', 'em', 'rem', '%']}
														onUnit={(value) =>
															setAttributes({ borderHoverRadiusUnit: value })
														}
														max={borderHoverRadiusIsRelative ? 24 : 500}
														step={borderHoverRadiusIsRelative ? 0.1 : 1}
														min={0}
														isBorderRadius={true}
														allowEmpty={true}
													/>
												)}
												<BoxShadowControl
													label={__('Box Shadow', 'flx-blocks')}
													enable={
														undefined !== displayHoverShadow ? displayHoverShadow : false
													}
													color={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].color
															? shadowHover[0].color
															: '#000000'
													}
													colorDefault={'#000000'}
													onArrayChange={(color, opacity) => {
														saveShadowHover({ color, opacity });
													}}
													opacity={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].opacity
															? shadowHover[0].opacity
															: 0.2
													}
													hOffset={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].hOffset
															? shadowHover[0].hOffset
															: 0
													}
													vOffset={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].vOffset
															? shadowHover[0].vOffset
															: 0
													}
													blur={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].blur
															? shadowHover[0].blur
															: 14
													}
													spread={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].spread
															? shadowHover[0].spread
															: 0
													}
													inset={
														undefined !== shadowHover &&
														undefined !== shadowHover[0] &&
														undefined !== shadowHover[0].inset
															? shadowHover[0].inset
															: false
													}
													onEnableChange={(value) => {
														setAttributes({
															displayHoverShadow: value,
														});
													}}
													onColorChange={(value) => {
														saveShadowHover({ color: value });
													}}
													onOpacityChange={(value) => {
														saveShadowHover({ opacity: value });
													}}
													onHOffsetChange={(value) => {
														saveShadowHover({ hOffset: value });
													}}
													onVOffsetChange={(value) => {
														saveShadowHover({ vOffset: value });
													}}
													onBlurChange={(value) => {
														saveShadowHover({ blur: value });
													}}
													onSpreadChange={(value) => {
														saveShadowHover({ spread: value });
													}}
													onInsetChange={(value) => {
														saveShadowHover({ inset: value });
													}}
												/>
											</>
										}
										normal={
											<>
												<ResponsiveBorderControl
													label={__('Border', 'flx-blocks')}
													value={borderStyle ? JSON.parse(JSON.stringify(borderStyle)) : ''}
													tabletValue={tabletBorderStyle}
													mobileValue={mobileBorderStyle}
													onChange={(value) => setAttributes({ borderStyle: value })}
													onChangeTablet={(value) =>
														setAttributes({ tabletBorderStyle: value })
													}
													onChangeMobile={(value) =>
														setAttributes({ mobileBorderStyle: value })
													}
												/>
												{borderRadiusTokens.length ? (
													<EditorBoxControl
														label={__('Border Radius', 'flx-blocks')}
														value={borderRadiusForDevice.value}
														onChange={(next) =>
															setAttributes({ [borderRadiusForDevice.attr]: next })
														}
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
														onChangeTablet={(value) =>
															setAttributes({ tabletBorderRadius: value })
														}
														onChangeMobile={(value) =>
															setAttributes({ mobileBorderRadius: value })
														}
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
												<BoxShadowControl
													label={__('Box Shadow', 'flx-blocks')}
													enable={undefined !== displayShadow ? displayShadow : false}
													color={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].color
															? shadow[0].color
															: '#000000'
													}
													colorDefault={'#000000'}
													onArrayChange={(color, opacity) => {
														saveShadow({ color, opacity });
													}}
													opacity={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].opacity
															? shadow[0].opacity
															: 0.2
													}
													hOffset={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].hOffset
															? shadow[0].hOffset
															: 0
													}
													vOffset={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].vOffset
															? shadow[0].vOffset
															: 0
													}
													blur={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].blur
															? shadow[0].blur
															: 14
													}
													spread={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].spread
															? shadow[0].spread
															: 0
													}
													inset={
														undefined !== shadow &&
														undefined !== shadow[0] &&
														undefined !== shadow[0].inset
															? shadow[0].inset
															: false
													}
													onEnableChange={(value) => {
														setAttributes({
															displayShadow: value,
														});
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
													onSpreadChange={(value) => {
														saveShadow({ spread: value });
													}}
													onInsetChange={(value) => {
														saveShadow({ inset: value });
													}}
												/>
											</>
										}
									/>
								</FlxbkPanelBody>
								<div className="fxt-sidebar-settings-spacer"></div>
								{showSettings('textColor', 'flx/column') && (
									<FlxbkPanelBody
										title={__('Text Color Settings', 'flx-blocks')}
										initialOpen={false}
										panelName={'fxb-col-text-color-settings'}
									>
										<HoverToggleControl
											hover={
												<ColorGroup>
													<PopColorControl
														label={__('Text Color', 'flx-blocks')}
														value={textColorHover ? textColorHover : ''}
														default={''}
														onChange={(value) => setAttributes({ textColorHover: value })}
													/>
													<PopColorControl
														label={__('Text Link Color', 'flx-blocks')}
														value={linkColorHover ? linkColorHover : ''}
														default={''}
														onChange={(value) => setAttributes({ linkColorHover: value })}
													/>
													<PopColorControl
														label={__('Text Link Hover Color', 'flx-blocks')}
														value={linkHoverColorHover ? linkHoverColorHover : ''}
														default={''}
														onChange={(value) =>
															setAttributes({ linkHoverColorHover: value })
														}
													/>
												</ColorGroup>
											}
											normal={
												<ColorGroup>
													<PopColorControl
														label={__('Text Color', 'flx-blocks')}
														value={textColor ? textColor : ''}
														default={''}
														onChange={(value) => setAttributes({ textColor: value })}
													/>
													<PopColorControl
														label={__('Text Link Color', 'flx-blocks')}
														value={linkColor ? linkColor : ''}
														default={''}
														onChange={(value) => setAttributes({ linkColor: value })}
													/>
													<PopColorControl
														label={__('Text Link Hover Color', 'flx-blocks')}
														value={linkHoverColor ? linkHoverColor : ''}
														default={''}
														onChange={(value) => setAttributes({ linkHoverColor: value })}
													/>
												</ColorGroup>
											}
										/>
									</FlxbkPanelBody>
								)}
							</>
						)}
					</InspectorControls>
				</>
			)}
			<div
				id={`animate-id${uniqueID}`}
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
				style={{
					minHeight: undefined !== previewMinHeight ? previewMinHeight + previewMinHeightUnit : undefined,
					paddingLeft:
						undefined !== previewPaddingLeft
							? getSpacingOptionOutput(previewPaddingLeft, previewPaddingType)
							: undefined,
					paddingRight:
						undefined !== previewPaddingRight
							? getSpacingOptionOutput(previewPaddingRight, previewPaddingType)
							: undefined,
					paddingTop:
						undefined !== previewPaddingTop
							? getSpacingOptionOutput(previewPaddingTop, previewPaddingType)
							: undefined,
					paddingBottom:
						undefined !== previewPaddingBottom
							? getSpacingOptionOutput(previewPaddingBottom, previewPaddingType)
							: undefined,
					marginLeft:
						undefined !== previewMarginLeft
							? getSpacingOptionOutput(previewMarginLeft, previewMarginType)
							: undefined,
					marginRight:
						undefined !== previewMarginRight
							? getSpacingOptionOutput(previewMarginRight, previewMarginType)
							: undefined,
					marginTop:
						undefined !== previewMarginTop
							? getSpacingOptionOutput(previewMarginTop, previewMarginType)
							: undefined,
					marginBottom:
						undefined !== previewMarginBottom
							? getSpacingOptionOutput(previewMarginBottom, previewMarginType)
							: undefined,
					textAlign: previewAlign ? previewAlign : undefined,
					backgroundColor: backgroundString,
					backgroundImage: previewBackground ? previewBackground : undefined,
					backgroundSize:
						previewBackgroundImg && previewBackgroundImg[0] && previewBackgroundImg[0].bgImgSize
							? previewBackgroundImg[0].bgImgSize
							: undefined,
					backgroundPosition:
						previewBackgroundImg && previewBackgroundImg[0] && previewBackgroundImg[0].bgImgPosition
							? previewBackgroundImg[0].bgImgPosition
							: undefined,
					backgroundRepeat:
						previewBackgroundImg && previewBackgroundImg[0] && previewBackgroundImg[0].bgImgRepeat
							? previewBackgroundImg[0].bgImgRepeat
							: undefined,
					backgroundAttachment:
						previewBackgroundImg && previewBackgroundImg[0] && previewBackgroundImg[0].bgImgAttachment
							? previewBackgroundImg[0].bgImgAttachment
							: undefined,
					borderTop: previewBorderTopStyle ? previewBorderTopStyle : undefined,
					borderRight: previewBorderRightStyle ? previewBorderRightStyle : undefined,
					borderBottom: previewBorderBottomStyle ? previewBorderBottomStyle : undefined,
					borderLeft: previewBorderLeftStyle ? previewBorderLeftStyle : undefined,
					// Tested against '' rather than truthiness, the way the Advanced Image does it: a corner
					// set to 0 is a real value, and both `0` and `'0'` are falsy. Skipping the declaration
					// for them left the block-default rule -- which carries the selected preset's radius --
					// as the only `border-radius` on the element, so a block squared off at 0 still drew
					// the preset's corners.
					borderTopLeftRadius:
						'' !== previewRadiusTop
							? tokenDimension(previewRadiusTop, borderRadiusUnit ? borderRadiusUnit : 'px')
							: undefined,
					borderTopRightRadius:
						'' !== previewRadiusRight
							? tokenDimension(previewRadiusRight, borderRadiusUnit ? borderRadiusUnit : 'px')
							: undefined,
					borderBottomRightRadius:
						'' !== previewRadiusBottom
							? tokenDimension(previewRadiusBottom, borderRadiusUnit ? borderRadiusUnit : 'px')
							: undefined,
					borderBottomLeftRadius:
						'' !== previewRadiusLeft
							? tokenDimension(previewRadiusLeft, borderRadiusUnit ? borderRadiusUnit : 'px')
							: undefined,
					boxShadow:
						undefined !== displayShadow &&
						displayShadow &&
						undefined !== shadow &&
						undefined !== shadow[0] &&
						undefined !== shadow[0].color
							? (undefined !== shadow[0].inset && shadow[0].inset ? 'inset ' : '') +
								(undefined !== shadow[0].hOffset ? shadow[0].hOffset : 0) +
								'px ' +
								(undefined !== shadow[0].vOffset ? shadow[0].vOffset : 0) +
								'px ' +
								(undefined !== shadow[0].blur ? shadow[0].blur : 14) +
								'px ' +
								(undefined !== shadow[0].spread ? shadow[0].spread : 0) +
								'px ' +
								FlxbkColorOutput(
									undefined !== shadow[0].color ? shadow[0].color : '#000000',
									undefined !== shadow[0].opacity ? shadow[0].opacity : 1
								)
							: undefined,
					'-webkit-backdrop-filter': backdropFilterString !== undefined ? backdropFilterString : undefined,
					'backdrop-filter': backdropFilterString !== undefined ? backdropFilterString : undefined,
				}}
				{...innerBlocksProps}
			></div>
			<SpacingVisualizer
				style={{
					marginLeft:
						undefined !== previewMarginLeft
							? getSpacingOptionOutput(previewMarginLeft, previewMarginType)
							: undefined,
					marginRight:
						undefined !== previewMarginRight
							? getSpacingOptionOutput(previewMarginRight, previewMarginType)
							: undefined,
					marginTop:
						undefined !== previewMarginTop
							? getSpacingOptionOutput(previewMarginTop, previewMarginType)
							: undefined,
					marginBottom:
						undefined !== previewMarginBottom
							? getSpacingOptionOutput(previewMarginBottom, previewMarginType)
							: undefined,
				}}
				type="inside"
				forceShow={paddingMouseOver.isMouseOver}
				spacing={[
					getSpacingOptionOutput(previewPaddingTop, previewPaddingType),
					getSpacingOptionOutput(previewPaddingRight, previewPaddingType),
					getSpacingOptionOutput(previewPaddingBottom, previewPaddingType),
					getSpacingOptionOutput(previewPaddingLeft, previewPaddingType),
				]}
			/>
			<SpacingVisualizer
				type="outsideVertical"
				forceShow={marginMouseOver.isMouseOver}
				spacing={[
					getSpacingOptionOutput(previewMarginTop, previewMarginType),
					getSpacingOptionOutput(previewMarginRight, previewMarginType),
					getSpacingOptionOutput(previewMarginBottom, previewMarginType),
					getSpacingOptionOutput(previewMarginLeft, previewMarginType),
				]}
			/>
		</div>
	);
}
export default SectionEdit;
