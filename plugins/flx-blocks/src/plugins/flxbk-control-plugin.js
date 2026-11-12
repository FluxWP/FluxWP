import { map } from 'lodash';

import { PluginSidebar, PluginSidebarMoreMenuItem, store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { Fragment, useState, useEffect, useRef } from '@wordpress/element';
import { PanelBody, Button, ToggleControl } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { useSelect, useDispatch } from '@wordpress/data';
import { external } from '@wordpress/icons';
import { store as coreStore } from '@wordpress/core-data';
import { store as preferencesStore } from '@wordpress/preferences';
import { store as noticesStore } from '@wordpress/notices';
import { addQueryArgs } from '@wordpress/url';
/**
 * Import Icons
 */
import * as BlockIcons from '@flx-blocks/icons';

/**
 * Import Settings
 */
import FlxbkEditorWidth from './editor-width';
import FlxbkSetting from './settings';
import FlxbkColors from './block-defaults/color-palette-defaults';

/*
 * Components
 */
import FlxbkFontFamily from './block-defaults/typography-defaults';
import FlxbkVisibilitySettings from './block-visibility-settings';
import ExportDefaults from './block-defaults/export-defaults';
import ImportDefaults from './block-defaults/import-defaults';
import ResetDefaults from './block-defaults/reset-defaults';
import DefaultEditorBlock from './default-editor-block';

// Optimizer constants - matching the PHP version
const META_KEY = '_fxb_optimizer_status';
const STATUS_EXCLUDED = -1;
const STATUS_UNOPTIMIZED = 0;
const STATUS_OPTIMIZED = 1;

/**
 * Custom hook to get the performance optimizer enabled setting
 * @returns {boolean} Whether the optimizer is enabled
 */
function useOptimizerEnabled() {
	const globalSettings = flxbk_params.globalSettings ? JSON.parse(flxbk_params.globalSettings) : {};
	return globalSettings?.performance_optimizer_enabled === true;
}

/**
 * Performance Optimizer toggle component that uses the shared hook
 * This ensures we use the same state source for both the toggle and conditional panel
 */
function PerformanceOptimizerToggle() {
	const isOptimizerEnabled = useOptimizerEnabled();
	const [isSaving, setIsSaving] = useState(false);
	const { createSuccessNotice } = useDispatch(noticesStore);

	const saveConfig = (value) => {
		setIsSaving(true);
		const config = flxbk_params.globalSettings ? JSON.parse(flxbk_params.globalSettings) : {};
		config.performance_optimizer_enabled = value;
		const settingModel = new wp.api.models.Settings({ flxbk_settings: JSON.stringify(config) });

		settingModel.save().then((response) => {
			createSuccessNotice(__('Settings saved', 'flx-blocks'), {
				type: 'snackbar',
			});

			setIsSaving(false);
			flxbk_params.globalSettings = JSON.stringify(config);
			// Reload the page to load/unload the external optimizer JavaScript.
			window.location.reload();
		});
	};

	return (
		<ToggleControl
			label={__('Globally Enable The Performance Optimizer', 'flx-blocks')}
			isBusy={isSaving}
			checked={isOptimizerEnabled}
			onChange={(value) => {
				saveConfig(value);
			}}
		/>
	);
}

/**
 * Component for excluding the current post from optimization
 */
function OptimizerExcludeToggle() {
	const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta'));
	const savedMeta = useSelect((select) => select('core/editor').getCurrentPostAttribute('meta'));
	const { editPost } = useDispatch('core/editor');
	const originalMetaValue = useRef(null);

	// Capture the original meta value when it first becomes available, before any edits.
	useEffect(() => {
		if (savedMeta !== undefined && originalMetaValue.current === null) {
			// Capture the value from the saved post, not the edited version
			if (savedMeta && savedMeta[META_KEY] !== undefined) {
				originalMetaValue.current = savedMeta[META_KEY];
			} else {
				// If no saved value exists, default to unoptimized
				originalMetaValue.current = STATUS_UNOPTIMIZED;
			}
		}
	}, [savedMeta]);

	if (meta === undefined) {
		return null;
	}

	return (
		<ToggleControl
			label={__('Exclude this post from optimization', 'flx-blocks')}
			checked={meta[META_KEY] === STATUS_EXCLUDED}
			onChange={(value) => {
				if (value) {
					// When checking, set to excluded
					editPost({ meta: { [META_KEY]: STATUS_EXCLUDED } });
				} else {
					// When unchecking, restore to original value (or unoptimized if original was excluded)
					const restoreValue =
						originalMetaValue.current !== null && originalMetaValue.current !== STATUS_EXCLUDED
							? originalMetaValue.current
							: STATUS_UNOPTIMIZED;
					editPost({ meta: { [META_KEY]: restoreValue } });
				}
			}}
		/>
	);
}

/**
 * Component for viewing the optimized version of a post
 */
function OptimizedViewLink() {
	const { hasLoaded, permalink, isPublished, label, meta, showIconLabels } = useSelect((select) => {
		const editor = select(editorStore);
		const { get } = select(preferencesStore);

		// Get post type for label.
		const postTypeSlug = editor.getCurrentPostType();
		const postType = select(coreStore).getPostType(postTypeSlug);
		const postTypeLabel = postType?.labels?.singular_name || 'Post';
		const dynamicLabel = __('View Optimized', 'flx-blocks') + ' ' + postTypeLabel;

		return {
			permalink: editor.getPermalink(),
			isPublished: editor.isCurrentPostPublished(),
			label: dynamicLabel,
			hasLoaded: !!postType,
			meta: editor.getEditedPostAttribute('meta'),
			showIconLabels: get('core', 'showIconLabels'),
		};
	}, []);

	if (!isPublished || !permalink || !hasLoaded) {
		return null;
	}

	if (meta !== undefined && meta[META_KEY] !== STATUS_OPTIMIZED) {
		return null;
	}

	const optimizerData = window.fxbOptimizer || {};
	const nonce = optimizerData.token;

	const url = addQueryArgs(permalink, {
		perf_token: nonce,
		fxb_optimizer_preview: 1,
	});

	return (
		<div style={{ marginTop: '12px' }}>
			<Button
				style={{ paddingLeft: 0 }}
				icon={external}
				iconPosition={'right'}
				label={label}
				href={url}
				target="_blank"
				showTooltip={!showIconLabels}
				size="compact"
			>
				{label}
			</Button>
		</div>
	);
}

/**
 * Build the row edit
 */
function FlxbkConfig() {
	const [user, setUser] = useState(flxbk_params.userrole ? flxbk_params.userrole : 'admin');
	const [controls, setControls] = useState(applyFilters('flxbk.block_controls_sidebar', []));
	const [blocks, setBlocks] = useState(applyFilters('flxbk.block_blocks_sidebar', []));
	const [extraPanels, setExtraPanels] = useState(applyFilters('flxbk.block_panels_sidebar', []));
	const [controlName, setControlName] = useState(
		applyFilters('flxbk.block_sidebar_control_name', __('Flux Block Editor Controls', 'flx-blocks'))
	);
	const [controlIcon, setControlIcon] = useState(
		applyFilters('flxbk.block_sidebar_control_icon', BlockIcons.flxbkNewIcon)
	);

	// Get the performance optimizer enabled setting using the shared hook
	const isOptimizerEnabled = useOptimizerEnabled();

	return (
		<Fragment>
			<PluginSidebarMoreMenuItem target="flxbk-controls" icon={controlIcon}>
				{controlName}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar isPinnable={true} name="flxbk-controls" title={controlName}>
				<PanelBody title={__('Color Palette', 'flx-blocks')} initialOpen={true}>
					<FlxbkColors />
				</PanelBody>

				{'admin' === user && (
					<>
						<PanelBody title={__('Block Visibility', 'flx-blocks')} initialOpen={false}>
							<div className="fxt-blocks-control-wrap">
								{/*Accordion*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Accordion', 'flx-blocks')}
										icon={BlockIcons.accordionBlockIcon}
										blockSlug={'accordion'}
										options={[
											{
												key: 'paneControl',
												label: __('Enable Pane Close/Open Settings', 'flx-blocks'),
											},
											{
												key: 'titleColors',
												label: __('Enable Title Color Settings', 'flx-blocks'),
											},
											{
												key: 'titleIcon',
												label: __('Enable Title Trigger Icon Settings', 'flx-blocks'),
											},
											{
												key: 'titleSpacing',
												label: __('Enable Title Spacing Settings', 'flx-blocks'),
											},
											{
												key: 'titleBorder',
												label: __('Enable Title Border Settings', 'flx-blocks'),
											},
											{
												key: 'titleFont',
												label: __('Enable Title Font Settings', 'flx-blocks'),
											},
											{
												key: 'paneContent',
												label: __('Enable Inner Content Settings', 'flx-blocks'),
											},
											{
												key: 'titleTag',
												label: __('Enable Title Tag Settings', 'flx-blocks'),
											},
											{
												key: 'structure',
												label: __('Enable Structure Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Advanced Button*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Advanced Button', 'flx-blocks')}
										icon={BlockIcons.advancedBtnIcon}
										blockSlug={'advancedbtn'}
										options={[
											{
												key: 'countSettings',
												label: __('Enable Count Settings', 'flx-blocks'),
											},
											{
												key: 'sizeSettings',
												label: __('Enable Size Settings', 'flx-blocks'),
											},
											{
												key: 'colorSettings',
												label: __('Enable Color Settings', 'flx-blocks'),
											},
											{
												key: 'iconSettings',
												label: __('Enable Icon Settings', 'flx-blocks'),
											},
											{
												key: 'fontSettings',
												label: __('Enable Font Family Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Advanced Form */}
								{/*<div className="fxt-blocks-control-row">*/}
								{/*		<FlxbkVisibilitySettings blockName={ __('Advanced Form', 'flx-blocks' )}*/}
								{/*								   blockSlug={'advanced-form'}*/}
								{/*								   icon={ BlockIcons.advancedFormIcon }*/}
								{/*								   options={[*/}
								{/*			{ key: 'containerSettings', label: __( 'Enable Container Style Settings', 'flx-blocks' ) },*/}
								{/*			// { key: 'itemStyle', label: __( 'Enable Item Style Settings', 'flx-blocks' ) },*/}
								{/*			// { key: 'numberStyle', label: __( 'Enable Number Style Settings', 'flx-blocks' ) },*/}
								{/*			// { key: 'labelStyle', label: __( 'Enable Label Style Settings', 'flx-blocks' ) },*/}
								{/*			// { key: 'visibilitySettings', label: __( 'Enable Visibility Settings', 'flx-blocks' ) },*/}
								{/*		]}/>*/}
								{/*</div>*/}

								{/*Advanced Gallery*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Advanced Gallery', 'flx-blocks')}
										blockSlug={'advancedgallery'}
										icon={BlockIcons.galleryIcon}
										options={[
											{
												key: 'gutterSettings',
												label: __('Enable Gutter Settings', 'flx-blocks'),
											},
											{
												key: 'lightboxSettings',
												label: __('Enable Lightbox Settings', 'flx-blocks'),
											},
											{
												key: 'styleSettings',
												label: __('Enable Image Style Settings', 'flx-blocks'),
											},
											{
												key: 'captionSettings',
												label: __('Enable Caption Settings', 'flx-blocks'),
											},
											{
												key: 'shadowSettings',
												label: __('Enable Image Shadow Settings', 'flx-blocks'),
											},
											{
												key: 'spacingSettings',
												label: __('Enable Gallery Spacing Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Advanced Heading*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Advanced Text', 'flx-blocks')}
										blockSlug={'advancedheading'}
										icon={BlockIcons.advancedHeadingIcon}
										options={[
											{
												key: 'toolbarTypography',
												label: __('Enable Typography Settings in Toolbar', 'flx-blocks'),
												initial: 'none',
											},
											{
												key: 'toolbarColor',
												label: __('Enable Color Settings in Toolbar', 'flx-blocks'),
												initial: 'none',
											},
											{
												key: 'colorSettings',
												label: __('Enable Color Settings', 'flx-blocks'),
											},
											{
												key: 'sizeSettings',
												label: __('Enable Size Settings', 'flx-blocks'),
											},
											{
												key: 'advancedSettings',
												label: __('Enable Advanced Typography Settings', 'flx-blocks'),
											},
											{
												key: 'iconSettings',
												label: __('Enable Icon Settings', 'flx-blocks'),
											},
											{
												key: 'highlightSettings',
												label: __('Enable Highlight Settings', 'flx-blocks'),
											},
											{
												key: 'marginSettings',
												label: __('Enable Margin Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Countdown*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Countdown', 'flx-blocks')}
										blockSlug={'countdown'}
										icon={BlockIcons.countdownIcon}
										options={[
											{
												key: 'containerSettings',
												label: __('Enable Container Style Settings', 'flx-blocks'),
											},
											{
												key: 'itemStyle',
												label: __('Enable Item Style Settings', 'flx-blocks'),
											},
											{
												key: 'numberStyle',
												label: __('Enable Number Style Settings', 'flx-blocks'),
											},
											{
												key: 'labelStyle',
												label: __('Enable Label Style Settings', 'flx-blocks'),
											},
											{
												key: 'visibilitySettings',
												label: __('Enable Visibility Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Count Up */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Count Up', 'flx-blocks')}
										blockSlug={'countup'}
										icon={BlockIcons.countUpIcon}
										options={[
											{
												key: 'titleStyle',
												label: __('Enable Title Style Settings', 'flx-blocks'),
											},
											{
												key: 'numberStyle',
												label: __('Enable Number Style Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Google Maps */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Google Maps', 'flx-blocks')}
										blockSlug={'googlemaps'}
										icon={BlockIcons.googleMapsIcon}
										options={[
											{ key: 'apiSettings', label: __('Enable API Settings', 'flx-blocks') },
											{
												key: 'containerStyle',
												label: __('Enable Container Style Settings', 'flx-blocks'),
											},
											{
												key: 'mapLocation',
												label: __('Enable Map Location Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Icon */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Icon', 'flx-blocks')}
										blockSlug={'icon'}
										icon={BlockIcons.iconIcon}
										options={[
											{
												key: 'iconSpacing',
												label: __('Enable Icon Spacing Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Icon List*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Icon List', 'flx-blocks')}
										blockSlug={'iconlist'}
										icon={BlockIcons.iconListBlockIcon}
										options={[
											{
												key: 'column',
												label: __('Enable List Column Settings', 'flx-blocks'),
											},
											{
												key: 'spacing',
												label: __('Enable List Spacing Settings', 'flx-blocks'),
											},
											{
												key: 'textStyle',
												label: __('Enable Text Style Settings', 'flx-blocks'),
											},
											{
												key: 'joinedIcons',
												label: __('Enable All List Icon Control', 'flx-blocks'),
											},
											{
												key: 'individualIcons',
												label: __('Enable individual List Item Control', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Info Box*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Info Box', 'flx-blocks')}
										blockSlug={'infobox'}
										icon={BlockIcons.infoboxIcon}
										options={[
											{
												key: 'containerSettings',
												label: __('Enable Container Settings', 'flx-blocks'),
											},
											{
												key: 'mediaSettings',
												label: __('Enable Media Settings', 'flx-blocks'),
											},
											{
												key: 'titleSettings',
												label: __('Enable Title Settings', 'flx-blocks'),
											},
											{
												key: 'textSettings',
												label: __('Enable Text Settings', 'flx-blocks'),
											},
											{
												key: 'learnMoreSettings',
												label: __('Enable Learn More Settings', 'flx-blocks'),
											},
											{
												key: 'shadowSettings',
												label: __('Enable Shadow Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Lottie */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Lottie', 'flx-blocks')}
										blockSlug={'lottie'}
										icon={BlockIcons.lottieIcon}
										options={[
											{
												key: 'sourceFile',
												label: __('Enable Source File Settings', 'flx-blocks'),
											},
											{
												key: 'playbackSettings',
												label: __('Enable Playback Settings', 'flx-blocks'),
											},
											{
												key: 'sizeControl',
												label: __('Enable Size Control Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Posts */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Posts', 'flx-blocks')}
										blockSlug={'posts'}
										icon={BlockIcons.postsIcon}
										options={[
											{
												key: 'layoutSettings',
												label: __('Enable Layout Settings', 'flx-blocks'),
											},
											{
												key: 'imageSettings',
												label: __('Enable Image Settings', 'flx-blocks'),
											},
											{
												key: 'categorySettings',
												label: __('Enable Category Settings', 'flx-blocks'),
											},
											{
												key: 'titleSettings',
												label: __('Enable Title Style Settings', 'flx-blocks'),
											},
											{
												key: 'metaSettings',
												label: __('Enable Meta Settings', 'flx-blocks'),
											},
											{
												key: 'contentSettings',
												label: __('Enable Content Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Row Layout*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Row Layout', 'flx-blocks')}
										blockSlug={'rowlayout'}
										icon={BlockIcons.blockRowIcon}
										options={[
											{
												key: 'columnResize',
												label: __('Control Individual Settings Groups', 'flx-blocks'),
											},
											{
												key: 'basicLayout',
												label: __('Enable Basic Layout Controls', 'flx-blocks'),
											},
											{
												key: 'paddingMargin',
												label: __('Enable Padding/Margin Settings', 'flx-blocks'),
											},
											{
												key: 'background',
												label: __('Enable Background Settings', 'flx-blocks'),
											},
											{
												key: 'backgroundOverlay',
												label: __('Enable Background Overlay Settings', 'flx-blocks'),
											},
											{ key: 'border', label: __('Enable Border Settings', 'flx-blocks') },
											{
												key: 'dividers',
												label: __('Enable Dividers Settings', 'flx-blocks'),
											},
											{
												key: 'textColor',
												label: __('Enable Text Color Settings', 'flx-blocks'),
											},
											{
												key: 'structure',
												label: __('Enable Structure Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Section */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Section', 'flx-blocks')}
										blockSlug={'column'}
										icon={BlockIcons.blockColumnIcon}
										options={[
											{
												key: 'container',
												label: __('Enable Container Settings', 'flx-blocks'),
											},
											{
												key: 'textAlign',
												label: __('Enable Text Align Control', 'flx-blocks'),
											},
											{
												key: 'textColor',
												label: __('Enable Text Color Control', 'flx-blocks'),
											},
											{
												key: 'paddingMargin',
												label: __('Enable Padding/Margin Control', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Show More */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Show More', 'flx-blocks')}
										blockSlug={'show-more'}
										icon={BlockIcons.showMoreIcon}
										options={[
											{
												key: 'showMoreSettings',
												label: __('Enable Show More Settings', 'flx-blocks'),
											},
											{
												key: 'spacingSettings',
												label: __('Enable Spacing Settings', 'flx-blocks'),
											},
											{
												key: 'expandSettings',
												label: __('Enable Expand Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Spacer*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Spacer/Divider', 'flx-blocks')}
										blockSlug={'spacer'}
										icon={BlockIcons.spacerIcon}
										options={[
											{
												key: 'spacerHeightUnits',
												label: __('Enable Height Units', 'flx-blocks'),
											},
											{
												key: 'spacerHeight',
												label: __('Enable Height Control', 'flx-blocks'),
											},
											{
												key: 'dividerToggle',
												label: __('Enable Divider Toggle Control', 'flx-blocks'),
											},
											{
												key: 'dividerStyles',
												label: __('Enable Divider Styles Control', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/* Table of Contents */}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Table of Contents', 'flx-blocks')}
										blockSlug={'table-of-contents'}
										icon={BlockIcons.tableOfContentsIcon}
										options={[
											{
												key: 'allowedHeaders',
												label: __('Enable Allowed Headers Settings', 'flx-blocks'),
											},
											{
												key: 'titleSettings',
												label: __('Enable Title Settings', 'flx-blocks'),
											},
											{
												key: 'collapsibleSettings',
												label: __('Enable Collapsible Settings', 'flx-blocks'),
											},
											{
												key: 'listSettings',
												label: __('Enable List Settings', 'flx-blocks'),
											},
											{
												key: 'scrollSettings',
												label: __('Enable Scroll Settings', 'flx-blocks'),
											},
											{
												key: 'containerSettings',
												label: __('Enable Container Settings', 'flx-blocks'),
											},
											{
												key: 'nonStaticContent',
												label: __('Enable Non static Content', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Tabs*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Tabs', 'flx-blocks')}
										blockSlug={'tabs'}
										icon={BlockIcons.blockTabsIcon}
										options={[
											{ key: 'tabLayout', label: __('Enable Layout Settings', 'flx-blocks') },
											{
												key: 'tabContent',
												label: __('Enable Content Settings', 'flx-blocks'),
											},
											{
												key: 'titleColor',
												label: __('Enable Title Color Settings', 'flx-blocks'),
											},
											{
												key: 'titleSpacing',
												label: __('Enable Title Spacing/Border Settings', 'flx-blocks'),
											},
											{
												key: 'titleFont',
												label: __('Enable Title Font Settings', 'flx-blocks'),
											},
											{
												key: 'titleIcon',
												label: __('Enable Title Icon Settings', 'flx-blocks'),
											},
											{
												key: 'structure',
												label: __('Enable Structure Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>

								{/*Testimonials*/}
								<div className="fxt-blocks-control-row">
									<FlxbkVisibilitySettings
										blockName={__('Testimonials', 'flx-blocks')}
										blockSlug={'testimonials'}
										icon={BlockIcons.testimonialBlockIcon}
										options={[
											{
												key: 'layoutSettings',
												label: __('Enable Layout Settings', 'flx-blocks'),
											},
											{
												key: 'styleSettings',
												label: __('Enable Style Settings', 'flx-blocks'),
											},
											{
												key: 'columnSettings',
												label: __('Enable Column Settings', 'flx-blocks'),
											},
											{
												key: 'containerSettings',
												label: __('Enable Container Settings', 'flx-blocks'),
											},
											{
												key: 'carouselSettings',
												label: __('Enable Carousel Settings', 'flx-blocks'),
											},
											{
												key: 'iconSettings',
												label: __('Enable Top Icon Settings', 'flx-blocks'),
											},
											{
												key: 'titleSettings',
												label: __('Enable Title Settings', 'flx-blocks'),
											},
											{
												key: 'ratingSettings',
												label: __('Enable Rating Settings', 'flx-blocks'),
											},
											{
												key: 'contentSettings',
												label: __('Enable Content Settings', 'flx-blocks'),
											},
											{
												key: 'mediaSettings',
												label: __('Enable Media Settings', 'flx-blocks'),
											},
											{
												key: 'nameSettings',
												label: __('Enable Name Settings', 'flx-blocks'),
											},
											{
												key: 'occupationSettings',
												label: __('Enable Occupation Settings', 'flx-blocks'),
											},
											{
												key: 'shadowSettings',
												label: __('Enable Shadow Settings', 'flx-blocks'),
											},
											{
												key: 'individualSettings',
												label: __('Enable Individual Item Settings', 'flx-blocks'),
											},
										]}
									/>
								</div>
								{map(blocks, ({ Control }) => (
									<Control />
								))}
								<h3>{__('Components', 'flx-blocks')}</h3>
								<FlxbkFontFamily />

								<FlxbkVisibilitySettings
									blockName={__('Design Library', 'flx-blocks')}
									blockSlug={'designlibrary'}
									icon={BlockIcons.flxbkCatNewIcon}
									showBlockWideSettings={false}
									options={[
										{
											key: 'show',
											label: __('Show Design Library Button For', 'flx-blocks'),
										},
										{
											key: 'section',
											label: __('Show Flux Library For', 'flx-blocks'),
											requiresPro: true,
										},
										{
											key: 'templates',
											label: __('Show Starter Packs Library For', 'flx-blocks'),
											initial: 'none',
										},
									]}
								/>

								{map(controls, ({ Control }, index) => (
									<Control key={index} />
								))}
							</div>
						</PanelBody>

						<DefaultEditorBlock />

						<PanelBody title={__('Import/Export Block Settings', 'flx-blocks')} initialOpen={false}>
							<ExportDefaults />

							<hr />

							<ImportDefaults />
						</PanelBody>

						<PanelBody title={__('Reset Block Defaults', 'flx-blocks')} initialOpen={false}>
							<ResetDefaults />
						</PanelBody>
						<PanelBody title={__('Pexels Library Search', 'flx-blocks')} initialOpen={false}>
							<FlxbkSetting
								slug={'enable_image_picker'}
								label={__('Enable Pexels Image Picker', 'flx-blocks')}
								type={'toggle'}
								theDefault={true}
							/>
						</PanelBody>
						<PanelBody title={__('Performance Optimizer', 'flx-blocks')} initialOpen={false}>
							<PerformanceOptimizerToggle />

							{isOptimizerEnabled && (
								<>
									<OptimizerExcludeToggle />
									<OptimizedViewLink />
								</>
							)}
						</PanelBody>
						<PanelBody title={__('Custom CSS Indicator', 'flx-blocks')} initialOpen={false}>
							<FlxbkSetting
								slug={'enable_custom_css_indicator'}
								label={__('Enable Custom CSS Indicator', 'flx-blocks')}
								type={'toggle'}
								theDefault={false}
								help={__(
									'This will add a custom indicator to the block editor to indicate if the block has custom CSS.',
									'flx-blocks'
								)}
							/>
						</PanelBody>
						{'undefined' !== typeof window.flxbkDynamicParams && (
							<PanelBody title={__('Dynamic Content Settings', 'flx-blocks')} initialOpen={false}>
								<FlxbkSetting
									slug={'get_fields_show_all'}
									label={__('Always show all fields', 'flx-blocks')}
									type={'toggle'}
									theDefault={false}
								/>
							</PanelBody>
						)}
					</>
					// End check for user = admin.
				)}

				{(undefined === flxbk_params ||
					(undefined !== flxbk_params && undefined === flxbk_params.editor_width) ||
					(undefined !== flxbk_params &&
						undefined !== flxbk_params.editor_width &&
						flxbk_params.editor_width)) && (
					<PanelBody title={__('Editor Width', 'flx-blocks')} initialOpen={false}>
						<FlxbkEditorWidth />
					</PanelBody>
				)}
				{/* <PanelBody
						title={ __( 'Global Styles' ) }
						initialOpen={ false }
					>
						{ 'admin' === user && (
							<FlxbkGlobalTypography />
						) }
					</PanelBody> */}
				{map(extraPanels, ({ Panel }, index) => (
					<Panel key={index} />
				))}
			</PluginSidebar>
		</Fragment>
	);
}

export default FlxbkConfig;
