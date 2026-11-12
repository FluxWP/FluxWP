/**
 * BLOCK: Flux Advanced Heading
 */

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { useState, Fragment, useMemo, useRef, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { get } from 'lodash';
import { addQueryArgs } from '@wordpress/url';
import { useEntityBlockEditor, useEntityProp } from '@wordpress/core-data';
import { applyFilters } from '@wordpress/hooks';
import {
	InspectorControls,
	useInnerBlocksProps,
	InspectorAdvancedControls,
	store as editorStore,
	BlockContextProvider,
	BlockControls,
} from '@wordpress/block-editor';
import {
	TextControl,
	ExternalLink,
	Button,
	Placeholder,
	ToggleControl,
	ToolbarGroup,
	ToolbarButton,
	SelectControl,
	RangeControl,
} from '@wordpress/components';
import { headerBlockIcon } from '@flx-blocks/icons';
import {
	FlxbkPanelBody,
	InspectorControlTabs,
	ResponsiveMeasureRangeControl,
	ResponsiveMeasurementControls,
	ResponsiveBorderControl,
	TypographyControls,
	PopColorControl,
	ColorGroup,
	BackgroundControl as FlxbkBackgroundControl,
	HoverToggleControl,
	ResponsiveAlignControls,
	ResponsiveRangeControls,
	BackgroundTypeControl,
	GradientControl,
	ResponsiveSelectControl,
	SmallResponsiveControl,
	BoxShadowControl,
	SelectPostFromPostType,
	CopyPasteAttributes,
} from '@flx-blocks/components';
import { getPreviewSize, mouseOverVisualizer, arrayStringToInt } from '@flx-blocks/helpers';

import { BackendStyles, PopoverTutorial } from './components';
import { HEADER_ALLOWED_BLOCKS } from './constants';
import { findHeaderBlockClientId } from './helpers';

/**
 * Internal dependencies
 */
import metadata from './block.json';
/**
 * Regular expression matching invalid anchor characters for replacement.
 *
 * @type {RegExp}
 */
const ANCHOR_REGEX = /[\s#]/g;

export function EditInner(props) {
	const { attributes, setAttributes, clientId, context, direct, id, isSelected, justCompletedOnboarding } = props;
	const { setHeaderVisualBuilderOpenId, setHeaderVisualBuilderSelectedId } = useDispatch('flxblocks/data');

	const { previewDevice, showVisualBuilder, visualBuilderClientId } = useSelect(
		(select) => {
			return {
				previewDevice: select('flxblocks/data').getPreviewDeviceType(),
				showVisualBuilder: select('flxblocks/data').getOpenHeaderVisualBuilderId() === clientId,
				visualBuilderClientId: select('flxblocks/data').getSelectedHeaderVisualBuilderId(),
			};
		},
		[clientId]
	);

	const { childSelected } = useSelect((select) => {
		return {
			childSelected: select('core/block-editor').hasSelectedInnerBlock(clientId, true),
		};
	}, []);

	const [componentRef, setComponentRef] = useState();

	const [activeTab, setActiveTab] = useState('general');

	const paddingMouseOver = mouseOverVisualizer();
	const marginMouseOver = mouseOverVisualizer();

	const [meta, setMeta] = useHeaderProp('meta');

	useEffect(() => {
		const setValue = isSelected || childSelected || showVisualBuilder ? clientId : null;

		if (visualBuilderClientId !== setValue) {
			setHeaderVisualBuilderSelectedId(setValue);
		}
	}, [isSelected, childSelected]);

	const metaAttributes = {
		padding: meta?._flxbk_header_padding,
		tabletPadding: meta?._flxbk_header_tabletPadding,
		mobilePadding: meta?._flxbk_header_mobilePadding,
		paddingUnit: meta?._flxbk_header_paddingUnit,
		margin: meta?._flxbk_header_margin,
		tabletMargin: meta?._flxbk_header_tabletMargin,
		mobileMargin: meta?._flxbk_header_mobileMargin,
		marginUnit: meta?._flxbk_header_marginUnit,
		border: meta?._flxbk_header_border,
		borderTablet: meta?._flxbk_header_borderTablet,
		borderMobile: meta?._flxbk_header_borderMobile,
		borderUnit: meta?._flxbk_header_borderUnit,
		borderHover: meta?._flxbk_header_borderHover,
		borderHoverTablet: meta?._flxbk_header_borderHoverTablet,
		borderHoverMobile: meta?._flxbk_header_borderHoverMobile,
		borderHoverUnit: meta?._flxbk_header_borderHoverUnit,
		borderRadius: meta?._flxbk_header_borderRadius,
		borderRadiusTablet: meta?._flxbk_header_borderRadiusTablet,
		borderRadiusMobile: meta?._flxbk_header_borderRadiusMobile,
		borderRadiusUnit: meta?._flxbk_header_borderRadiusUnit,
		borderRadiusHover: meta?._flxbk_header_borderRadiusHover,
		borderRadiusHoverTablet: meta?._flxbk_header_borderRadiusHoverTablet,
		borderRadiusHoverMobile: meta?._flxbk_header_borderRadiusHoverMobile,
		borderRadiusHoverUnit: meta?._flxbk_header_borderRadiusHoverUnit,
		borderTransparent: meta?._flxbk_header_borderTransparent,
		borderTransparentTablet: meta?._flxbk_header_borderTransparentTablet,
		borderTransparentMobile: meta?._flxbk_header_borderTransparentMobile,
		borderTransparentUnit: meta?._flxbk_header_borderTransparentUnit,
		borderTransparentHover: meta?._flxbk_header_borderTransparentHover,
		borderTransparentHoverTablet: meta?._flxbk_header_borderTransparentHoverTablet,
		borderTransparentHoverMobile: meta?._flxbk_header_borderTransparentHoverMobile,
		borderTransparentHoverUnit: meta?._flxbk_header_borderTransparentHoverUnit,
		borderRadiusTransparent: meta?._flxbk_header_borderRadiusTransparent,
		borderRadiusTransparentTablet: meta?._flxbk_header_borderRadiusTransparentTablet,
		borderRadiusTransparentMobile: meta?._flxbk_header_borderRadiusTransparentMobile,
		borderRadiusTransparentUnit: meta?._flxbk_header_borderRadiusTransparentUnit,
		borderRadiusTransparentHover: meta?._flxbk_header_borderRadiusTransparentHover,
		borderRadiusTransparentHoverTablet: meta?._flxbk_header_borderRadiusTransparentHoverTablet,
		borderRadiusTransparentHoverMobile: meta?._flxbk_header_borderRadiusTransparentHoverMobile,
		borderRadiusTransparentHoverUnit: meta?._flxbk_header_borderRadiusTransparentHoverUnit,
		borderSticky: meta?._flxbk_header_borderSticky,
		borderStickyTablet: meta?._flxbk_header_borderStickyTablet,
		borderStickyMobile: meta?._flxbk_header_borderStickyMobile,
		borderStickyUnit: meta?._flxbk_header_borderStickyUnit,
		borderStickyHover: meta?._flxbk_header_borderStickyHover,
		borderStickyHoverTablet: meta?._flxbk_header_borderStickyHoverTablet,
		borderStickyHoverMobile: meta?._flxbk_header_borderStickyHoverMobile,
		borderStickyHoverUnit: meta?._flxbk_header_borderStickyHoverUnit,
		borderRadiusSticky: meta?._flxbk_header_borderRadiusSticky,
		borderRadiusStickyTablet: meta?._flxbk_header_borderRadiusStickyTablet,
		borderRadiusStickyMobile: meta?._flxbk_header_borderRadiusStickyMobile,
		borderRadiusStickyUnit: meta?._flxbk_header_borderRadiusStickyUnit,
		borderRadiusStickyHover: meta?._flxbk_header_borderRadiusStickyHover,
		borderRadiusStickyHoverTablet: meta?._flxbk_header_borderRadiusStickyHoverTablet,
		borderRadiusStickyHoverMobile: meta?._flxbk_header_borderRadiusStickyHoverMobile,
		borderRadiusStickyHoverUnit: meta?._flxbk_header_borderRadiusStickyHoverUnit,
		className: meta?._flxbk_header_className,
		anchor: meta?._flxbk_header_anchor,
		background: meta?._flxbk_header_background,
		backgroundHover: meta?._flxbk_header_backgroundHover,
		backgroundTransparent: meta?._flxbk_header_backgroundTransparent,
		backgroundTransparentHover: meta?._flxbk_header_backgroundTransparentHover,
		backgroundSticky: meta?._flxbk_header_backgroundSticky,
		backgroundStickyHover: meta?._flxbk_header_backgroundStickyHover,
		typography: meta?._flxbk_header_typography,
		linkColor: meta?._flxbk_header_linkColor,
		linkHoverColor: meta?._flxbk_header_linkHoverColor,
		height: meta?._flxbk_header_height,
		heightUnit: meta?._flxbk_header_heightUnit,
		width: meta?._flxbk_header_width,
		widthUnit: meta?._flxbk_header_widthUnit,
		isSticky: meta?._flxbk_header_isSticky,
		isStickyTablet: meta?._flxbk_header_isStickyTablet,
		isStickyMobile: meta?._flxbk_header_isStickyMobile,
		isTransparent: meta?._flxbk_header_isTransparent,
		isTransparentTablet: meta?._flxbk_header_isTransparentTablet,
		isTransparentMobile: meta?._flxbk_header_isTransparentMobile,
		autoTransparentSpacing: meta?._flxbk_header_autoTransparentSpacing,
		stickySection: meta?._flxbk_header_stickySection,
		stickySectionTablet: meta?._flxbk_header_stickySectionTablet,
		stickySectionMobile: meta?._flxbk_header_stickySectionMobile,
		shrinkMain: meta?._flxbk_header_shrinkMain,
		shrinkMainHeight: meta?._flxbk_header_shrinkMainHeight,
		shrinkMainHeightTablet: meta?._flxbk_header_shrinkMainHeightTablet,
		shrinkMainHeightMobile: meta?._flxbk_header_shrinkMainHeightMobile,
		revealScrollUp: meta?._flxbk_header_revealScrollUp,
		shadow: meta?._flxbk_header_shadow,
		headerTag: meta?._flxbk_header_headerTag,
		inheritPostTransparent: meta?._flxbk_header_inheritPostTransparent,
		disableTransparentOverrides: meta?._flxbk_header_disableTransparentOverrides,
		backdropFilterType: meta?._flxbk_header_pro_backdropFilterType,
		backdropFilterSize: meta?._flxbk_header_pro_backdropFilterSize,
		backdropFilterString: meta?._flxbk_header_pro_backdropFilterString,
		mobileBreakpoint: meta?._flxbk_header_mobileBreakpoint,
	};

	const {
		padding,
		tabletPadding,
		mobilePadding,
		paddingUnit,
		margin,
		tabletMargin,
		mobileMargin,
		marginUnit,
		border,
		borderTablet,
		borderMobile,
		borderHover,
		borderHoverTablet,
		borderHoverMobile,
		borderRadius,
		borderRadiusTablet,
		borderRadiusMobile,
		borderRadiusUnit,
		borderRadiusHover,
		borderRadiusHoverTablet,
		borderRadiusHoverMobile,
		borderRadiusHoverUnit,
		borderTransparent,
		borderTransparentTablet,
		borderTransparentMobile,
		borderTransparentHover,
		borderTransparentHoverTablet,
		borderTransparentHoverMobile,
		borderRadiusTransparent,
		borderRadiusTransparentTablet,
		borderRadiusTransparentMobile,
		borderRadiusTransparentUnit,
		borderRadiusTransparentHover,
		borderRadiusTransparentHoverTablet,
		borderRadiusTransparentHoverMobile,
		borderRadiusTransparentHoverUnit,
		borderSticky,
		borderStickyTablet,
		borderStickyMobile,
		borderStickyHover,
		borderStickyHoverTablet,
		borderStickyHoverMobile,
		borderRadiusSticky,
		borderRadiusStickyTablet,
		borderRadiusStickyMobile,
		borderRadiusStickyUnit,
		borderRadiusStickyHover,
		borderRadiusStickyHoverTablet,
		borderRadiusStickyHoverMobile,
		borderRadiusStickyHoverUnit,
		className,
		anchor,
		background,
		backgroundHover,
		backgroundTransparent,
		backgroundTransparentHover,
		backgroundSticky,
		backgroundStickyHover,
		typography,
		linkColor,
		linkHoverColor,
		height,
		heightUnit,
		width,
		widthUnit,
		isSticky,
		isStickyTablet,
		isStickyMobile,
		isTransparent,
		isTransparentTablet,
		isTransparentMobile,
		autoTransparentSpacing,
		stickySection,
		stickySectionTablet,
		stickySectionMobile,
		shrinkMain,
		shrinkMainHeight,
		shrinkMainHeightTablet,
		shrinkMainHeightMobile,
		revealScrollUp,
		shadow,
		headerTag,
		inheritPostTransparent,
		disableTransparentOverrides,
		backdropFilterType,
		backdropFilterSize,
		mobileBreakpoint,
	} = metaAttributes;

	const setMetaAttribute = (value, key) => {
		setMeta({ ...meta, ['_flxbk_header_' + key]: value });
	};
	const previewIsSticky = getPreviewSize(previewDevice, isSticky, isStickyTablet, isStickyMobile);
	const previewIsTransparent = getPreviewSize(previewDevice, isTransparent, isTransparentTablet, isTransparentMobile);

	const previewStickySection = getPreviewSize(
		previewDevice,
		stickySection ? stickySection : 'main',
		stickySectionTablet,
		stickySectionMobile
	);

	let [blocks, onInput, onChange] = useEntityBlockEditor('postType', 'flxbk_header', id);

	const emptyHeader = useMemo(() => {
		return [createBlock('flx/header', {})];
	}, [clientId]);

	if (blocks.length === 0) {
		blocks = emptyHeader;
	}

	const headerInnerBlocks = useMemo(() => {
		const innerBlocks = get(blocks, [0, 'innerBlocks'], []);
		const findHeader = findHeaderBlockClientId(innerBlocks);

		if (findHeader !== null) {
			wp.data.dispatch('core/block-editor').removeBlock(findHeader);
		}
		return innerBlocks;
	}, [blocks]);

	const newBlock = useMemo(() => {
		return get(blocks, [0], {});
	}, [blocks]);

	const backgroundStyleControls = (size = '', suffix = '') => {
		//previously had hover settings in here but didn't end up neededing them for the header container.
		const backgroundValue = metaAttributes['background' + suffix + size];
		return (
			<>
				<BackgroundTypeControl
					label={__('Type', 'flx-blocks')}
					type={undefined != backgroundValue?.type ? backgroundValue.type : 'normal'}
					onChange={(value) =>
						setMetaAttribute({ ...backgroundValue, type: value }, 'background' + suffix + size)
					}
					allowedTypes={['normal', 'gradient']}
				/>
				{'normal' === backgroundValue?.type && (
					<>
						<PopColorControl
							label={__('Background Color', 'flx-blocks')}
							value={undefined !== backgroundValue?.color ? backgroundValue.color : ''}
							default={''}
							onChange={(value) => {
								setMetaAttribute({ ...backgroundValue, color: value }, 'background' + suffix + size);
							}}
						/>
						<FlxbkBackgroundControl
							label={__('Background Image', 'flx-blocks')}
							hasImage={
								undefined === backgroundValue.image || '' === backgroundValue.image ? false : true
							}
							imageURL={backgroundValue?.image ? backgroundValue.image : ''}
							imageID={backgroundValue?.imageID}
							imagePosition={backgroundValue?.position ? backgroundValue.position : 'center center'}
							imageSize={backgroundValue?.size ? backgroundValue.size : 'cover'}
							imageRepeat={backgroundValue?.repeat ? backgroundValue.repeat : 'no-repeat'}
							imageAttachment={backgroundValue?.attachment ? backgroundValue.attachment : 'scroll'}
							imageAttachmentParallax={false}
							onRemoveImage={() => {
								setMetaAttribute(
									{ ...backgroundValue, imageID: undefined },
									'background' + suffix + size
								);
								setMetaAttribute(
									{ ...backgroundValue, image: undefined },
									'background' + suffix + size
								);
							}}
							onSaveImage={(value) => {
								setMetaAttribute(
									{ ...backgroundValue, imageID: value.id.toString() },
									'background' + suffix + size
								);
								setMetaAttribute(
									{ ...backgroundValue, image: value.url },
									'background' + suffix + size
								);
							}}
							onSaveURL={(newURL) => {
								if (newURL !== backgroundValue.image) {
									setMetaAttribute(
										{ ...backgroundValue, imageID: undefined },
										'background' + suffix + size
									);
									setMetaAttribute(
										{ ...backgroundValue, image: newURL },
										'background' + suffix + size
									);
								}
							}}
							onSavePosition={(value) => {
								setMetaAttribute({ ...backgroundValue, position: value }, 'background' + suffix + size);
							}}
							onSaveSize={(value) => {
								setMetaAttribute({ ...backgroundValue, size: value }, 'background' + suffix + size);
							}}
							onSaveRepeat={(value) =>
								setMetaAttribute({ ...backgroundValue, repeat: value }, 'background' + suffix + size)
							}
							onSaveAttachment={(value) =>
								setMetaAttribute(
									{ ...backgroundValue, attachment: value },
									'background' + suffix + size
								)
							}
							disableMediaButtons={backgroundValue?.image ? true : false}
							isSelected={isSelected}
							attributes={attributes}
							setAttributes={setAttributes}
							name={'flx/header'}
							clientId={clientId}
							context={context}
						/>
					</>
				)}
				{'gradient' === backgroundValue?.type && (
					<>
						<GradientControl
							value={backgroundValue?.gradient}
							onChange={(value) => {
								setMetaAttribute({ ...backgroundValue, gradient: value }, 'background' + suffix + size);
							}}
							gradients={[]}
						/>
					</>
				)}
			</>
		);
	};

	const headerBackdropFilter = null;

	const saveShadow = (value) => {
		const newUpdate = shadow.map((item, index) => {
			if (0 === index) {
				item = { ...item, ...value };
			}
			return item;
		});
		setMetaAttribute(newUpdate, 'shadow');
	};

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: '',
		},
		{
			allowedBlocks: HEADER_ALLOWED_BLOCKS,
			value: !direct ? headerInnerBlocks : undefined,
			onInput: !direct ? (a, b) => onInput([{ ...newBlock, innerBlocks: a }], b) : undefined,
			onChange: !direct ? (a, b) => onChange([{ ...newBlock, innerBlocks: a }], b) : undefined,
			templateLock: 'all',
		}
	);

	const contextValue = useMemo(
		() => ({
			'flx/headerPostId': id,
			'flx/headerIsSticky': previewIsSticky,
			'flx/headerIsTransparent': previewIsTransparent,
		}),
		[id, previewIsSticky, previewIsTransparent]
	);

	if (typeof pagenow !== 'undefined' && ('widgets' === pagenow || 'customize' === pagenow)) {
		const editPostLink = addQueryArgs('post.php', {
			post: id,
			action: 'edit',
		});
		return (
			<>
				<Placeholder
					className="fxb-select-or-create-placeholder"
					label={__('Advanced Header', 'flx-blocks')}
					icon={headerBlockIcon}
				>
					<p style={{ width: '100%', marginBottom: '10px' }}>
						{__('Advanced Headers can not be edited within the widgets screen.', 'flx-blocks')}
					</p>
					<Button href={editPostLink} variant="primary" className="fxb-form-edit-link">
						{__('Edit Header', 'flx-blocks')}
					</Button>
				</Placeholder>
				<InspectorControls>
					<FlxbkPanelBody panelName={'fxb-advanced-form-selected-switch'}>
						<SelectPostFromPostType
							postType="flxbk_header"
							label={__('Selected Header', 'flx-blocks')}
							onChange={(nextId) => {
								setAttributes({ id: parseInt(nextId) });
							}}
							value={id}
						/>

						<Button
							variant="link"
							onClick={() => {
								setAttributes({ id: 0 });
							}}
							style={{ marginBottom: '10px' }}
						>
							{__('Create a New Header', 'flx-blocks')}
						</Button>
					</FlxbkPanelBody>
				</InspectorControls>
			</>
		);
	}
	return (
		<>
			<BackendStyles
				{...props}
				metaAttributes={metaAttributes}
				previewDevice={previewDevice}
				currentRef={componentRef}
			/>
			<BlockControls>
				<CopyPasteAttributes
					attributes={meta}
					excludedAttributes={['_flxbk_header_description']}
					blockSlug={metadata.name}
					onPaste={(attributesToPaste) => setMeta({ ...meta, ...attributesToPaste })}
				/>
				<ToolbarGroup>
					<ToolbarButton
						className="components-tab-button"
						isPressed={showVisualBuilder}
						onClick={() => setHeaderVisualBuilderOpenId(showVisualBuilder ? null : clientId)}
					>
						<span>{__('Visual Builder', 'flx-blocks')}</span>
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<InspectorControlTabs
					panelName={'advanced-header'}
					setActiveTab={(value) => setActiveTab(value)}
					activeTab={activeTab}
				/>

				{activeTab === 'general' && (
					<>
						<FlxbkPanelBody panelName={'fxb-header-selected-switch'}>
							{!direct && (
								<>
									<SelectPostFromPostType
										postType="flxbk_header"
										label={__('Selected Header', 'flx-blocks')}
										onChange={(nextId) => {
											setAttributes({ id: parseInt(nextId) });
										}}
										value={id}
									/>

									<Button
										variant="link"
										onClick={() => {
											setAttributes({ id: 0 });
										}}
										style={{ marginBottom: '10px' }}
									>
										{__('Create a New Header', 'flx-blocks')}
									</Button>
								</>
							)}
						</FlxbkPanelBody>
						<FlxbkPanelBody panelName={'fxb-header-general-settings'}>
							<ResponsiveSelectControl
								label={__('Sticky Header', 'flx-blocks')}
								value={isSticky}
								tabletValue={isStickyTablet}
								mobileValue={isStickyMobile}
								options={[
									{ value: '', label: __('-', 'flx-blocks') },
									{ value: '1', label: __('Yes', 'flx-blocks') },
									{ value: '0', label: __('No', 'flx-blocks') },
								]}
								onChange={(value) => setMetaAttribute(value, 'isSticky')}
								onChangeTablet={(value) => setMetaAttribute(value, 'isStickyTablet')}
								onChangeMobile={(value) => setMetaAttribute(value, 'isStickyMobile')}
							/>
							<ResponsiveSelectControl
								label={__('Transparent Header', 'flx-blocks')}
								value={isTransparent}
								tabletValue={isTransparentTablet}
								mobileValue={isTransparentMobile}
								options={[
									{ value: '', label: __('-', 'flx-blocks') },
									{ value: '1', label: __('Yes', 'flx-blocks') },
									{ value: '0', label: __('No', 'flx-blocks') },
								]}
								onChange={(value) => setMetaAttribute(value, 'isTransparent')}
								onChangeTablet={(value) => setMetaAttribute(value, 'isTransparentTablet')}
								onChangeMobile={(value) => setMetaAttribute(value, 'isTransparentMobile')}
							/>

							{previewIsTransparent === '1' && (
								<ToggleControl
									label={__('Auto spacing under', 'flx-blocks')}
									checked={autoTransparentSpacing}
									onChange={(value) => setMetaAttribute(value, 'autoTransparentSpacing')}
								/>
							)}
							{previewIsSticky === '1' && (
								<>
									<ResponsiveSelectControl
										label={__('Sticky Section', 'flx-blocks')}
										value={stickySection}
										tabletValue={stickySectionTablet}
										mobileValue={stickySectionMobile}
										options={[
											{ value: '', label: __('Whole Header', 'flx-blocks') },
											{ value: 'main', label: __('Main Row', 'flx-blocks') },
											{ value: 'top', label: __('Top Row', 'flx-blocks') },
											{ value: 'bottom', label: __('Bottom Row', 'flx-blocks') },
											{ value: 'top_main', label: __('Top and Main Row', 'flx-blocks') },
											{
												value: 'bottom_main',
												label: __('Bottom and Main Row', 'flx-blocks'),
											},
										]}
										onChange={(value) => setMetaAttribute(value, 'stickySection')}
										onChangeTablet={(value) => setMetaAttribute(value, 'stickySectionTablet')}
										onChangeMobile={(value) => setMetaAttribute(value, 'stickySectionMobile')}
									/>
									<ToggleControl
										label={__('Reveal on scroll up', 'flx-blocks')}
										checked={revealScrollUp}
										onChange={(value) => setMetaAttribute(value, 'revealScrollUp')}
									/>
									<ToggleControl
										label={__('Shrink Middle Row', 'flx-blocks')}
										checked={shrinkMain}
										onChange={(value) => setMetaAttribute(value, 'shrinkMain')}
									/>
									{shrinkMain &&
										(previewStickySection.includes('main') || previewStickySection === '') && (
											<ResponsiveRangeControls
												label={__('Middle Row Shrink Height', 'flx-blocks')}
												value={parseFloat(shrinkMainHeight)}
												tabletValue={parseFloat(shrinkMainHeightTablet)}
												mobileValue={parseFloat(shrinkMainHeightMobile)}
												onChange={(value) =>
													setMetaAttribute(value.toString(), 'shrinkMainHeight')
												}
												onChangeTablet={(value) =>
													setMetaAttribute(value.toString(), 'shrinkMainHeightTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value.toString(), 'shrinkMainHeightMobile')
												}
												min={0}
												max={500}
												step={1}
												unit={'px'}
												units={['px']}
												showUnit={true}
											/>
										)}
								</>
							)}
						</FlxbkPanelBody>

						{(isTransparent === '1' || isTransparentMobile === '1' || isTransparentTablet === '1') && (
							<FlxbkPanelBody
								title={__('Conditional Transparent Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-header-transparent-settings'}
							>
								{/* Disable transparent on Search & Archive */}
								<ToggleControl
									label={__('Disable Transparent on Search & Archive', 'flx-blocks')}
									checked={disableTransparentOverrides.includes('searchAndArchive')}
									onChange={(value) => {
										const newVal = disableTransparentOverrides.includes('searchAndArchive')
											? disableTransparentOverrides.filter((item) => item !== 'searchAndArchive')
											: [...disableTransparentOverrides, 'searchAndArchive'];
										setMetaAttribute(newVal, 'disableTransparentOverrides');
									}}
								/>

								{/*Loop through each post type and add a toggle control for each one*/}
								{flxbk_params?.postTypes.map((postType) => (
									<ToggleControl
										label={__('Disable Transparent on', 'flx-blocks') + ' ' + postType.label}
										checked={disableTransparentOverrides.includes(postType.value)}
										onChange={(value) => {
											const newVal = disableTransparentOverrides.includes(postType.value)
												? disableTransparentOverrides.filter((item) => item !== postType.value)
												: [...disableTransparentOverrides, postType.value];
											setMetaAttribute(newVal, 'disableTransparentOverrides');
										}}
									/>
								))}

								{flxbk_params?.isFlxbkT && (
									<>
										<div
											className="fxt-sidebar-settings-spacer"
											style={{ marginBottom: '20px' }}
										></div>

										<ToggleControl
											label={__('Inherit post settings', 'flx-blocks')}
											help={__(
												'Inherit the settings from the post or page for transparent header.',
												'flx-blocks'
											)}
											checked={inheritPostTransparent}
											onChange={(value) => setMetaAttribute(value, 'inheritPostTransparent')}
										/>
									</>
								)}
							</FlxbkPanelBody>
						)}
					</>
				)}

				{activeTab === 'style' && (
					<>
						<FlxbkPanelBody
							title={__('Background Settings', 'flx-blocks')}
							initialOpen={true}
							panelName={'fxb-header-bg-settings'}
						>
							{backgroundStyleControls()}
						</FlxbkPanelBody>
						{(previewIsTransparent === '1' ||
							(flxbk_params?.isFlxbkT && inheritPostTransparent)) && (
							<FlxbkPanelBody
								title={__('Transparent Background Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-header-bg-transparent-settings'}
							>
								{backgroundStyleControls('', 'Transparent')}
							</FlxbkPanelBody>
						)}
						{previewIsSticky === '1' && (
							<FlxbkPanelBody
								title={__('Sticky Background Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-header-bg-styicky-settings'}
							>
								{backgroundStyleControls('', 'Sticky')}
							</FlxbkPanelBody>
						)}
						<>
							{applyFilters(
								'flxbk.headerBackdropFilter',
								headerBackdropFilter,
								metaAttributes,
								meta,
								setMeta
							)}
						</>

						<FlxbkPanelBody
							title={__('Border Settings', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-header-border'}
						>
							<ResponsiveBorderControl
								label={__('Border', 'flx-blocks')}
								value={border}
								tabletValue={borderTablet}
								mobileValue={borderMobile}
								onChange={(value) => {
									setMetaAttribute(value, 'border');
								}}
								onChangeTablet={(value) => setMetaAttribute(value, 'borderTablet')}
								onChangeMobile={(value) => setMetaAttribute(value, 'borderMobile')}
							/>
							<ResponsiveMeasurementControls
								label={__('Border Radius', 'flx-blocks')}
								value={borderRadius}
								tabletValue={borderRadiusTablet}
								mobileValue={borderRadiusMobile}
								onChange={(value) => setMetaAttribute(value, 'borderRadius')}
								onChangeTablet={(value) => setMetaAttribute(value, 'borderRadiusTablet')}
								onChangeMobile={(value) => setMetaAttribute(value, 'borderRadiusMobile')}
								unit={borderRadiusUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setMetaAttribute(value, 'borderRadiusUnit')}
								max={
									borderRadiusUnit === 'em' || borderRadiusUnit === 'rem'
										? 24
										: borderRadiusUnit === 'px'
											? 500
											: 100
								}
								step={borderRadiusUnit === 'em' || borderRadiusUnit === 'rem' ? 0.1 : 1}
								min={0}
								isBorderRadius={true}
								allowEmpty={true}
								reset={() => {
									if (previewDevice === 'Tablet') {
										setMetaAttribute([0, 0, 0, 0], 'borderRadiusTablet');
									} else if (previewDevice === 'Mobile') {
										setMetaAttribute([0, 0, 0, 0], 'borderRadiusMobile');
									} else {
										setMetaAttribute([0, 0, 0, 0], 'borderRadius');
									}
								}}
							/>
						</FlxbkPanelBody>

						<FlxbkPanelBody
							title={__('Box Shadow', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-header-shadow'}
						>
							<BoxShadowControl
								label={__('Box Shadow', 'flx-blocks')}
								enable={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].enable
										? shadow[0].enable
										: true
								}
								color={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].color
										? shadow[0].color
										: '#000000'
								}
								colorDefault={'#000000'}
								onArrayChange={(color, opacity) => {
									saveShadow({ color, opacity });
								}}
								opacity={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].opacity
										? shadow[0].opacity
										: 0.2
								}
								hOffset={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].hOffset
										? shadow[0].hOffset
										: 0
								}
								vOffset={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].vOffset
										? shadow[0].vOffset
										: 0
								}
								blur={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].blur
										? shadow[0].blur
										: 14
								}
								spread={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].spread
										? shadow[0].spread
										: 0
								}
								inset={
									undefined !== shadow && undefined !== shadow[0] && undefined !== shadow[0].inset
										? shadow[0].inset
										: false
								}
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
								onSpreadChange={(value) => {
									saveShadow({ spread: value });
								}}
								onInsetChange={(value) => {
									saveShadow({ inset: value });
								}}
							/>
						</FlxbkPanelBody>
						{previewIsTransparent === '1' && (
							<FlxbkPanelBody
								title={__('Transparent Border Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-header-border-transparent'}
							>
								<HoverToggleControl
									normal={
										<>
											<ResponsiveBorderControl
												label={__('Border', 'flx-blocks')}
												value={borderTransparent}
												tabletValue={borderTransparentTablet}
												mobileValue={borderTransparentMobile}
												onChange={(value) => {
													setMetaAttribute(value, 'borderTransparent');
												}}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderTransparentTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderTransparentMobile')
												}
											/>
											<ResponsiveMeasurementControls
												label={__('Border Radius', 'flx-blocks')}
												value={borderRadiusTransparent}
												tabletValue={borderRadiusTransparentTablet}
												mobileValue={borderRadiusTransparentMobile}
												onChange={(value) => setMetaAttribute(value, 'borderRadiusTransparent')}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentMobile')
												}
												unit={borderRadiusTransparentUnit}
												units={['px', 'em', 'rem', '%']}
												onUnit={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentUnit')
												}
												max={
													borderRadiusTransparentUnit === 'em' ||
													borderRadiusTransparentUnit === 'rem'
														? 24
														: borderRadiusTransparentUnit === 'px'
															? 500
															: 100
												}
												step={
													borderRadiusTransparentUnit === 'em' ||
													borderRadiusTransparentUnit === 'rem'
														? 0.1
														: 1
												}
												min={0}
												isBorderRadius={true}
												allowEmpty={true}
												reset={() => {
													if (previewDevice === 'Tablet') {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusTransparentTablet');
													} else if (previewDevice === 'Mobile') {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusTransparentMobile');
													} else {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusTransparent');
													}
												}}
											/>
										</>
									}
									hover={
										<>
											<ResponsiveBorderControl
												label={__('Hover Border', 'flx-blocks')}
												value={borderTransparentHover}
												tabletValue={borderTransparentHoverTablet}
												mobileValue={borderTransparentHoverMobile}
												onChange={(value) => {
													setMetaAttribute(value, 'borderTransparentHover');
												}}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderTransparentHoverTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderTransparentHoverMobile')
												}
											/>
											<ResponsiveMeasurementControls
												label={__('Border Radius', 'flx-blocks')}
												value={borderRadiusTransparentHover}
												tabletValue={borderRadiusTransparentHoverTablet}
												mobileValue={borderRadiusTransparentHoverMobile}
												onChange={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentHover')
												}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentHoverTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentHoverMobile')
												}
												unit={borderRadiusTransparentHoverUnit}
												units={['px', 'em', 'rem', '%']}
												onUnit={(value) =>
													setMetaAttribute(value, 'borderRadiusTransparentHoverUnit')
												}
												max={
													borderRadiusTransparentHoverUnit === 'em' ||
													borderRadiusTransparentHoverUnit === 'rem'
														? 24
														: borderRadiusTransparentHoverUnit === 'px'
															? 500
															: 100
												}
												step={
													borderRadiusTransparentHoverUnit === 'em' ||
													borderRadiusTransparentHoverUnit === 'rem'
														? 0.1
														: 1
												}
												min={0}
												isBorderRadius={true}
												allowEmpty={true}
												reset={() => {
													if (previewDevice === 'Tablet') {
														setMetaAttribute(
															[0, 0, 0, 0],
															'borderRadiusTransparentHoverTablet'
														);
													} else if (previewDevice === 'Mobile') {
														setMetaAttribute(
															[0, 0, 0, 0],
															'borderRadiusTransparentHoverMobile'
														);
													} else {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusTransparentHover');
													}
												}}
											/>
										</>
									}
								/>
							</FlxbkPanelBody>
						)}
						{previewIsSticky === '1' && (
							<FlxbkPanelBody
								title={__('Sticky Border Settings', 'flx-blocks')}
								initialOpen={false}
								panelName={'fxb-header-border-sticky'}
							>
								<HoverToggleControl
									normal={
										<>
											<ResponsiveBorderControl
												label={__('Border', 'flx-blocks')}
												value={borderSticky}
												tabletValue={borderStickyTablet}
												mobileValue={borderStickyMobile}
												onChange={(value) => {
													setMetaAttribute(value, 'borderSticky');
												}}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderStickyTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderStickyMobile')
												}
											/>
											<ResponsiveMeasurementControls
												label={__('Border Radius', 'flx-blocks')}
												value={borderRadiusSticky}
												tabletValue={borderRadiusStickyTablet}
												mobileValue={borderRadiusStickyMobile}
												onChange={(value) => setMetaAttribute(value, 'borderRadiusSticky')}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderRadiusStickyTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderRadiusStickyMobile')
												}
												unit={borderRadiusStickyUnit}
												units={['px', 'em', 'rem', '%']}
												onUnit={(value) => setMetaAttribute(value, 'borderRadiusStickyUnit')}
												max={
													borderRadiusStickyUnit === 'em' || borderRadiusStickyUnit === 'rem'
														? 24
														: borderRadiusStickyUnit === 'px'
															? 500
															: 100
												}
												step={
													borderRadiusStickyUnit === 'em' || borderRadiusStickyUnit === 'rem'
														? 0.1
														: 1
												}
												min={0}
												isBorderRadius={true}
												allowEmpty={true}
												reset={() => {
													if (previewDevice === 'Tablet') {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusStickyTablet');
													} else if (previewDevice === 'Mobile') {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusStickyMobile');
													} else {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusSticky');
													}
												}}
											/>
										</>
									}
									hover={
										<>
											<ResponsiveBorderControl
												label={__('Hover Border', 'flx-blocks')}
												value={borderStickyHover}
												tabletValue={borderStickyHoverTablet}
												mobileValue={borderStickyHoverMobile}
												onChange={(value) => {
													setMetaAttribute(value, 'borderStickyHover');
												}}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderStickyHoverTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderStickyHoverMobile')
												}
											/>
											<ResponsiveMeasurementControls
												label={__('Border Radius', 'flx-blocks')}
												value={borderRadiusStickyHover}
												tabletValue={borderRadiusStickyHoverTablet}
												mobileValue={borderRadiusStickyHoverMobile}
												onChange={(value) => setMetaAttribute(value, 'borderRadiusStickyHover')}
												onChangeTablet={(value) =>
													setMetaAttribute(value, 'borderRadiusStickyHoverTablet')
												}
												onChangeMobile={(value) =>
													setMetaAttribute(value, 'borderRadiusStickyHoverMobile')
												}
												unit={borderRadiusStickyHoverUnit}
												units={['px', 'em', 'rem', '%']}
												onUnit={(value) =>
													setMetaAttribute(value, 'borderRadiusStickyHoverUnit')
												}
												max={
													borderRadiusStickyHoverUnit === 'em' ||
													borderRadiusStickyHoverUnit === 'rem'
														? 24
														: borderRadiusStickyHoverUnit === 'px'
															? 500
															: 100
												}
												step={
													borderRadiusStickyHoverUnit === 'em' ||
													borderRadiusStickyHoverUnit === 'rem'
														? 0.1
														: 1
												}
												min={0}
												isBorderRadius={true}
												allowEmpty={true}
												reset={() => {
													if (previewDevice === 'Tablet') {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusStickyHoverTablet');
													} else if (previewDevice === 'Mobile') {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusStickyHoverMobile');
													} else {
														setMetaAttribute([0, 0, 0, 0], 'borderRadiusStickyHover');
													}
												}}
											/>
										</>
									}
								/>
							</FlxbkPanelBody>
						)}
						<FlxbkPanelBody
							title={__('Typography Settings', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-header-font-family'}
						>
							<TypographyControls
								fontGroup={'header'}
								fontSize={typography.size}
								onFontSize={(value) => setMetaAttribute({ ...typography, size: value }, 'typography')}
								fontSizeType={typography.sizeType}
								onFontSizeType={(value) =>
									setMetaAttribute({ ...typography, sizeType: value }, 'typography')
								}
								lineHeight={typography.lineHeight}
								onLineHeight={(value) =>
									setMetaAttribute({ ...typography, lineHeight: value }, 'typography')
								}
								lineHeightType={typography.lineType}
								onLineHeightType={(value) =>
									setMetaAttribute({ ...typography, lineType: value }, 'typography')
								}
								reLetterSpacing={typography.letterSpacing}
								onLetterSpacing={(value) =>
									setMetaAttribute({ ...typography, letterSpacing: value }, 'typography')
								}
								letterSpacingType={typography.letterType}
								onLetterSpacingType={(value) =>
									setMetaAttribute({ ...typography, letterType: value }, 'typography')
								}
								textTransform={typography.textTransform}
								onTextTransform={(value) =>
									setMetaAttribute({ ...typography, textTransform: value }, 'typography')
								}
								fontFamily={typography.family}
								onFontFamily={(value) =>
									setMetaAttribute({ ...typography, family: value }, 'typography')
								}
								onFontChange={(select) => {
									setMetaAttribute({ ...typography, ...select }, 'typography');
								}}
								onFontArrayChange={(values) =>
									setMetaAttribute({ ...typography, ...values }, 'typography')
								}
								googleFont={typography.google}
								onGoogleFont={(value) =>
									setMetaAttribute({ ...typography, google: value }, 'typography')
								}
								loadGoogleFont={typography.loadGoogle}
								onLoadGoogleFont={(value) =>
									setMetaAttribute({ ...typography, loadGoogle: value }, 'typography')
								}
								fontVariant={typography.variant}
								onFontVariant={(value) =>
									setMetaAttribute({ ...typography, variant: value }, 'typography')
								}
								fontWeight={typography.weight}
								onFontWeight={(value) =>
									setMetaAttribute({ ...typography, weight: value }, 'typography')
								}
								fontStyle={typography.style}
								onFontStyle={(value) => setMetaAttribute({ ...typography, style: value }, 'typography')}
								fontSubset={typography.subset}
								onFontSubset={(value) =>
									setMetaAttribute({ ...typography, subset: value }, 'typography')
								}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Text Color Settings', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-header-text-color'}
						>
							<ColorGroup>
								<PopColorControl
									label={__('Text Color', 'flx-blocks')}
									value={typography.color ? typography.color : ''}
									default={''}
									onChange={(value) =>
										setMetaAttribute({ ...typography, color: value }, 'typography')
									}
								/>
								<PopColorControl
									label={__('Link Color', 'flx-blocks')}
									value={linkColor ? linkColor : ''}
									default={''}
									onChange={(value) => setMetaAttribute(value, 'linkColor')}
									swatchLabel2={__('Hover Color', 'flx-blocks')}
									value2={linkHoverColor ? linkHoverColor : ''}
									default2={''}
									onChange2={(value) => setMetaAttribute(value, 'linkHoverColor')}
								/>
							</ColorGroup>
						</FlxbkPanelBody>
						<div className="fxt-sidebar-settings-spacer"></div>
					</>
				)}

				{activeTab === 'advanced' && (
					<>
						<FlxbkPanelBody panelName={'fxb-header-padding'}>
							<ResponsiveMeasureRangeControl
								label={__('Padding', 'flx-blocks')}
								value={arrayStringToInt(padding)}
								tabletValue={arrayStringToInt(tabletPadding)}
								mobileValue={arrayStringToInt(mobilePadding)}
								onChange={(value) => {
									setMetaAttribute(value.map(String), 'padding');
								}}
								onChangeTablet={(value) => {
									setMetaAttribute(value.map(String), 'tabletPadding');
								}}
								onChangeMobile={(value) => {
									setMetaAttribute(value.map(String), 'mobilePadding');
								}}
								min={0}
								max={
									paddingUnit === 'em' || paddingUnit === 'rem'
										? 24
										: paddingUnit === 'px'
											? 200
											: 100
								}
								step={paddingUnit === 'em' || paddingUnit === 'rem' ? 0.1 : 1}
								unit={paddingUnit}
								units={['px', 'em', 'rem', '%']}
								onUnit={(value) => setMetaAttribute(value, 'paddingUnit')}
								onMouseOver={paddingMouseOver.onMouseOver}
								onMouseOut={paddingMouseOver.onMouseOut}
							/>
							<ResponsiveMeasureRangeControl
								label={__('Margin', 'flx-blocks')}
								value={arrayStringToInt(margin)}
								tabletValue={arrayStringToInt(tabletMargin)}
								mobileValue={arrayStringToInt(mobileMargin)}
								onChange={(value) => {
									setMetaAttribute(value.map(String), 'margin');
								}}
								onChangeTablet={(value) => {
									setMetaAttribute(value.map(String), 'tabletMargin');
								}}
								onChangeMobile={(value) => {
									setMetaAttribute(value.map(String), 'mobileMargin');
								}}
								min={marginUnit === 'em' || marginUnit === 'rem' ? -12 : -200}
								max={marginUnit === 'em' || marginUnit === 'rem' ? 24 : marginUnit === 'px' ? 200 : 100}
								step={marginUnit === 'em' || marginUnit === 'rem' ? 0.1 : 1}
								unit={marginUnit}
								units={['px', 'em', 'rem', '%', 'vh']}
								onUnit={(value) => setMetaAttribute(value, 'marginUnit')}
								onMouseOver={marginMouseOver.onMouseOver}
								onMouseOut={marginMouseOver.onMouseOut}
								allowAuto={true}
							/>
						</FlxbkPanelBody>
						<FlxbkPanelBody
							title={__('Container Size Settings', 'flx-blocks')}
							initialOpen={false}
							panelName={'fxb-header-size-settings'}
						>
							{/* unhooking this control, but will leave the backend styling and everything. done during beta so probably safe to remove the whole chain if we want. */}
							{/* <ResponsiveRangeControls
								label={__('Min Height', 'flx-blocks')}
								value={height?.[0] ? parseFloat(height[0]) : ''}
								onChange={(value) => {
									setMetaAttribute(
										[
											value.toString(),
											undefined !== height?.[1] ? height[1] : '',
											undefined !== height?.[2] ? height[2] : '',
										],
										'height'
									);
								}}
								tabletValue={height?.[1] ? parseFloat(height[1]) : ''}
								onChangeTablet={(value) => {
									setMetaAttribute(
										[
											undefined !== height?.[0] ? height[0] : '',
											value.toString(),
											undefined !== height?.[2] ? height[2] : '',
										],
										'height'
									);
								}}
								mobileValue={height?.[2] ? parseFloat(height[2]) : ''}
								onChangeMobile={(value) => {
									setMetaAttribute(
										[
											undefined !== height?.[0] ? height[0] : '',
											undefined !== height?.[1] ? height[1] : '',
											value.toString(),
										],
										'height'
									);
								}}
								min={0}
								max={heightUnit === 'px' ? 2000 : 200}
								step={1}
								unit={heightUnit ? heightUnit : 'px'}
								onUnit={(value) => {
									setMetaAttribute(value, 'heightUnit');
								}}
								reset={() => {
									setMetaAttribute(['', '', ''], 'height');
								}}
								units={['px', 'vh', 'vw']}
							/> */}
							<ResponsiveRangeControls
								label={__('Max Width', 'flx-blocks')}
								value={width?.[0] ? parseFloat(width[0]) : ''}
								onChange={(value) => {
									setMetaAttribute(
										[
											value.toString(),
											undefined !== width?.[1] ? width[1] : '',
											undefined !== width?.[2] ? width[2] : '',
										],
										'width'
									);
								}}
								tabletValue={width?.[1] ? parseFloat(width[1]) : ''}
								onChangeTablet={(value) => {
									setMetaAttribute(
										[
											undefined !== width?.[0] ? width[0] : '',
											value.toString(),
											undefined !== width?.[2] ? width[2] : '',
										],
										'width'
									);
								}}
								mobileValue={width?.[2] ? parseFloat(width[2]) : ''}
								onChangeMobile={(value) => {
									setMetaAttribute(
										[
											undefined !== width?.[0] ? width[0] : '',
											undefined !== width?.[1] ? width[1] : '',
											value.toString(),
										],
										'width'
									);
								}}
								min={0}
								max={widthUnit === 'px' ? 2000 : 200}
								step={1}
								unit={widthUnit ? widthUnit : 'px'}
								onUnit={(value) => {
									setMetaAttribute(value, 'widthUnit');
								}}
								reset={() => {
									setMetaAttribute(['', '', ''], 'width');
								}}
								units={['px', '%', 'vw']}
							/>
							<RangeControl
								label={__('Screen size to switch to mobile header', 'flx-blocks')}
								value={mobileBreakpoint === 0 ? null : mobileBreakpoint}
								onChange={(value) => {
									if (value === undefined) {
										value = 0;
									}
									setMetaAttribute(value, 'mobileBreakpoint');
								}}
								step={1}
								min={1}
								max={2000}
								allowReset={true}
								help={__(
									"Note: this ONLY affects when the header changes to it's mobile content. It DOES NOT affect responsive breakpoints for any other blocks or settings.",
									'flx-blocks'
								)}
							/>
						</FlxbkPanelBody>
					</>
				)}
			</InspectorControls>
			<InspectorAdvancedControls>
				<SelectControl
					label={__('Header Tag', 'flx-blocks')}
					value={headerTag}
					options={[
						{ value: '', label: __('<header>', 'flx-blocks') },
						{ value: 'div', label: __('<div>', 'flx-blocks') },
					]}
					onChange={(value) => setMetaAttribute(value, 'headerTag')}
				/>
				<TextControl
					__nextHasNoMarginBottom
					className="html-anchor-control"
					label={__('HTML anchor', 'flx-blocks')}
					help={
						<>
							{__(
								'Enter a word or two — without spaces — to make a unique web address just for this block, called an “anchor.” Then, you’ll be able to link directly to this section of your page.',
								'flx-blocks'
							)}

							<ExternalLink href={__('https://wordpress.org/documentation/article/page-jumps/')}>
								{__('Learn more about anchors', 'flx-blocks')}
							</ExternalLink>
						</>
					}
					value={anchor}
					placeholder={__('Add an anchor')}
					onChange={(nextValue) => {
						nextValue = nextValue.replace(ANCHOR_REGEX, '-');
						setMetaAttribute(nextValue, 'anchor');
					}}
					autoCapitalize="none"
					autoComplete="off"
				/>

				<TextControl
					__nextHasNoMarginBottom
					autoComplete="off"
					label={__('Additional CSS class(es)', 'flx-blocks')}
					value={className}
					onChange={(nextValue) => {
						setMetaAttribute(nextValue !== '' ? nextValue : undefined, 'className');
					}}
					help={__('Separate multiple classes with spaces.', 'flx-blocks')}
				/>
			</InspectorAdvancedControls>
			<BlockContextProvider value={contextValue}>
				<Fragment {...innerBlocksProps} />
			</BlockContextProvider>
			<span className="placeholder-ref" ref={setComponentRef} />
			{/*<SpacingVisualizer*/}
			{/*	style={ {*/}
			{/*		marginLeft: ( undefined !== previewMarginLeft ? getSpacingOptionOutput( previewMarginLeft, marginUnit ) : undefined ),*/}
			{/*		marginRight: ( undefined !== previewMarginRight ? getSpacingOptionOutput( previewMarginRight, marginUnit ) : undefined ),*/}
			{/*		marginTop: ( undefined !== previewMarginTop ? getSpacingOptionOutput( previewMarginTop, marginUnit ) : undefined ),*/}
			{/*		marginBottom: ( undefined !== previewMarginBottom ? getSpacingOptionOutput( previewMarginBottom, marginUnit ) : undefined ),*/}
			{/*	} }*/}
			{/*	type="inside"*/}
			{/*	forceShow={ paddingMouseOver.isMouseOver }*/}
			{/*	spacing={ [ getSpacingOptionOutput( previewPaddingTop, paddingUnit ), getSpacingOptionOutput( previewPaddingRight, paddingUnit ), getSpacingOptionOutput( previewPaddingBottom, paddingUnit ), getSpacingOptionOutput( previewPaddingLeft, paddingUnit ) ] }*/}
			{/*/>*/}
			{/*<SpacingVisualizer*/}
			{/*	type="inside"*/}
			{/*	forceShow={ marginMouseOver.isMouseOver }*/}
			{/*	spacing={ [ getSpacingOptionOutput( previewMarginTop, marginUnit ), getSpacingOptionOutput( previewMarginRight, marginUnit ), getSpacingOptionOutput( previewMarginBottom, marginUnit ), getSpacingOptionOutput( previewMarginLeft, marginUnit ) ] }*/}
			{/*/>*/}
			{justCompletedOnboarding && <PopoverTutorial {...props} headerRef={componentRef} />}
		</>
	);
}

export default EditInner;

function useHeaderProp(prop) {
	return useEntityProp('postType', 'flxbk_header', prop);
}
