/**
 * Early Gutenberg Blocks Filters
 *
 */
import { addFilter } from '@wordpress/hooks';
import { hasBlockSupport, getBlockSupport, createBlock } from '@wordpress/blocks';
import { assign, get } from 'lodash';
import { Button, Modal } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import { blockExists } from '@flx-blocks/helpers';
import { createHigherOrderComponent } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch, select } from '@wordpress/data';
import { addSiteStylesSupports } from './site-styles/block-supports';
import { hideCoreColorControls } from './site-styles/instance-controls';
import { withSiteCopiesHidden, withSiteStyles } from './site-styles/edit-overlay';
import { gateBlockDefaults } from './site-styles/block-defaults';
import { isFseMode } from './site-styles/supported-blocks';
import { useIsSiteStylesPanel } from './site-styles/use-is-site-styles-panel';
import { blockPresets, activeLibrary, activePresetFor, blockDefaultPreset } from './extension/preset-picker';
import { PresetButton } from './extension/preset-picker/PresetButton';
import { designTokenInspectorControl } from './extension/preset-picker/inspector-control';
import { PalettePicker, selectablePalettes } from './extension/palette-picker';
import { registerTokenAliasFilters } from './extension/design-tokens/register-filters';
import { registerColorControlFilters } from './extension/design-tokens/register-color-control-filters';
import { registerCustomColorFilters } from './extension/custom-colors/register-custom-color-filters';
import { registerComponentTokenFilters } from './extension/design-tokens/register-component-filters';
import { registerTokenSwatchPalettePreview } from './extension/design-tokens/palette-swatch-preview';

// Make the @flx-blocks/helpers output helpers design-token aware by resolving `{dot.alias}` values to
// their `var(--fxb-token--<id>)` reference through the library's filter seam.
registerTokenAliasFilters();

// Keep token-backed global-palette colors visible in the shared Flux color controls when the
// "Use only Custom Colors" override is on, through the color control's swatch filter seam.
registerColorControlFilters();

// Keep the Flux Block Editor custom colors in the shared Flux color controls when the override is on in FSE
// mode, where Global Styles owns the editor palette and leaves them out.
registerCustomColorFilters();

// Inject the design-token picker UI into the token-agnostic @flx-blocks/components control seams, so a
// control that receives a `context` gets the chip/picker without the package knowing about tokens.
registerComponentTokenFilters();

// Mirror the selected block's per-block palette onto the top-document <html>, so the color-control swatches
// preview that palette's colors through the projector's existing [data-fxb-palette] switch layer.
registerTokenSwatchPalettePreview();

/**
 * Add animation attributes
 *
 * @param {Array} settings The block settings.
 * @return {Array} The block settings with animation added.
 */
export function blockMetadataAttribute(settings) {
	if (hasBlockSupport(settings, 'fxbMetadata')) {
		settings.attributes = assign(settings.attributes, {
			metadata: {
				type: 'object',
				default: {
					name: '',
				},
			},
		});

		const contentLabel = getBlockSupport(settings, 'fxbContentLabel');

		settings.__experimentalLabel = (attributes, { context }) => {
			const { metadata } = attributes;

			// In the list view, use the block's content as the label.
			// If the content is empty, fall back to the default label.
			if (context === 'list-view' && get(metadata, 'name', '') !== '') {
				return metadata.name;
			} else if (context === 'list-view' && undefined !== contentLabel && get(attributes, contentLabel) !== '') {
				// Accordion pane block is stored as an array, doing this instead of deprecation on parent accordion.
				if (get(settings, 'name') === 'flx/pane' && get(attributes, contentLabel) instanceof Array) {
					return convertArrayTitleToString(get(attributes, contentLabel));
				}

				return get(attributes, contentLabel);
			}
		};
	}

	return settings;
}

function convertArrayTitleToString(arr) {
	let result = '';

	arr.forEach((item) => {
		if (typeof item === 'string') {
			result += item;
		} else if (item.props && item.props.children) {
			result += convertArrayTitleToString(item.props.children);
		}
	});

	return result;
}

addFilter('blocks.registerBlockType', 'flx/block-label', blockMetadataAttribute);
addFilter('blocks.registerBlockType', 'flx/site-styles-supports', addSiteStylesSupports);
addFilter('blockEditor.useSetting.before', 'flx/site-styles-instance-controls', hideCoreColorControls);
addFilter('editor.BlockEdit', 'flx/site-styles-overlay', withSiteStyles);
addFilter('editor.BlockEdit', 'flx/site-styles-copies', withSiteCopiesHidden);
addFilter('blocks.registerBlockType', 'flx/site-styles-block-defaults', gateBlockDefaults);

/**
 * Add the fxbPreset and/or fxbPalette attributes to a block, each gated on its own block support so the two
 * controls stay independent.
 *
 * fxbPreset (added for `fxbPreset`-support blocks) is the selected preset slug (e.g. "ghost"); an empty value
 * means the block keeps its $default look (the block preset). fxbPalette (added for `fxbPalette`-support blocks)
 * holds the id of a per-block color-palette override (e.g. "dark"); empty means the block follows the set's
 * `$current` palette. A palette only changes which colors the tokens resolve to, so it is orthogonal to
 * presets — a block can support either, both, or neither. Both the scoped preset CSS and the palette switch
 * layer are emitted server-side by the Design Tokens projector.
 *
 * @param {Object} settings The block settings.
 *
 *
 * @return {Object} The block settings with the fxbPreset and/or fxbPalette attributes added.
 */
export function blockPresetAttribute(settings) {
	if (hasBlockSupport(settings, 'fxbPreset')) {
		settings.attributes = assign(settings.attributes, {
			fxbPreset: {
				type: 'string',
				default: '',
			},
		});
	}

	if (hasBlockSupport(settings, 'fxbPalette')) {
		settings.attributes = assign(settings.attributes, {
			fxbPalette: {
				type: 'string',
				default: '',
			},
		});
	}

	return settings;
}
addFilter('blocks.registerBlockType', 'flx/fxb-preset-attribute', blockPresetAttribute);

/**
 * Override a block's registered attribute defaults with the value the editor attribute-default catalog
 * names for it, read from window.flxbkDesignTokensAttributeDefaults (Editor\Localizer,
 * Editor\Attribute_Default_Catalog). Today that is an empty `size` on the icon block: a freshly inserted
 * icon then holds no size of its own and previews at its selected preset's size, instead of carrying a
 * pixel number that would outrank every preset. Existing content is untouched — it already has an
 * explicit stored value, or no stored value and the same empty default.
 *
 * @param {Object} settings The block settings.
 * @param {string} name     The block name.
 *
 *
 * @return {Object} The block settings, with any catalog-covered attribute defaults overridden.
 */
export function blockPresetAttributeDefault(settings, name) {
	const catalog = window.flxbkDesignTokensAttributeDefaults;
	const entry = catalog && catalog[name];

	if (!entry) {
		return settings;
	}

	Object.keys(entry).forEach((attribute) => {
		if (settings.attributes && settings.attributes[attribute]) {
			settings.attributes[attribute] = assign({}, settings.attributes[attribute], {
				default: entry[attribute],
			});
		}
	});

	return settings;
}
addFilter('blocks.registerBlockType', 'flx/preset-attribute-default', blockPresetAttributeDefault);

/**
 * Sanitize a design-token slug to the identifier form the projector emits.
 *
 * Mirrors the projector's PHP Css_Builder::sanitize_identifier() sanitizer, so a slug written to a class
 * or a data attribute always matches the scoped selector / switch selector the projector emits even if it
 * carries an unexpected character.
 *
 * @param {string} slug The raw slug.
 *
 *
 * @return {string} The sanitized slug.
 */
function sanitizeTokenIdentifier(slug) {
	return (slug || '').replace(/[^A-Za-z0-9_-]+/g, '-');
}

/**
 * The class a block's selected preset outputs: `fxb-preset--<slug>`. The slug is sanitized, mirroring the
 * projector's PHP Style::preset_class(). Empty when nothing is selected.
 *
 * @param {string} fxbPreset The fxbPreset attribute (the selected preset slug).
 *
 *
 * @return {string} The preset class, or an empty string.
 */
function fxbPresetClassName(fxbPreset) {
	const slug = sanitizeTokenIdentifier(typeof fxbPreset === 'string' ? fxbPreset : '');

	return slug ? `fxb-preset--${slug}` : '';
}

/**
 * Append the fxb-preset--<name> class to a block's saved markup, so the projector's scoped overrides
 * apply on the front end. A no-op for blocks that do not opt in or have no preset selected.
 *
 * @param {Object} props      The save element props.
 * @param {Object} blockType  The block type.
 * @param {Object} attributes The block attributes.
 *
 *
 * @return {Object} The props, with the preset class appended when one is selected.
 */
export function blockPresetSaveClass(props, blockType, attributes) {
	if (!hasBlockSupport(blockType, 'fxbPreset')) {
		return props;
	}

	const presetClass = fxbPresetClassName(get(attributes, 'fxbPreset', ''));

	if (presetClass) {
		props.className = props.className ? `${props.className} ${presetClass}` : presetClass;
	}

	return props;
}
addFilter('blocks.getSaveContent.extraProps', 'flx/fxb-preset-save-class', blockPresetSaveClass);

/**
 * Append the data-fxb-palette="<id>" attribute to a block's saved markup when it carries a per-block palette
 * override, so the projector's `[data-fxb-palette]` switch layer re-points the block's canonical color vars
 * to that palette on the front end. A no-op for blocks that do not opt in or follow the set `$current`.
 *
 * @param {Object} props      The save element props.
 * @param {Object} blockType  The block type.
 * @param {Object} attributes The block attributes.
 *
 *
 * @return {Object} The props, with the data attribute appended when a palette is pinned.
 */
export function blockPaletteSaveAttr(props, blockType, attributes) {
	if (!hasBlockSupport(blockType, 'fxbPalette')) {
		return props;
	}

	const id = sanitizeTokenIdentifier(get(attributes, 'fxbPalette', ''));

	if (id) {
		props['data-fxb-palette'] = id;
	}

	return props;
}
addFilter('blocks.getSaveContent.extraProps', 'flx/fxb-palette-save-attr', blockPaletteSaveAttr);

/**
 * Mirror the fxb-preset--<name> class onto the block in the editor canvas, so a selected preset previews
 * live with the same scoped overrides the front end uses. The class names the preset the block renders
 * with after the fallback chain (`activePresetFor()`), exactly as the server does for a dynamic block, and
 * is left out for the default preset, whose rules are scoped by the absence of a preset class.
 *
 */
const withBlockPresetClass = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { name, attributes } = props;

		if (!hasBlockSupport(name, 'fxbPreset')) {
			return <BlockListBlock {...props} />;
		}

		const activePreset = activePresetFor(name, attributes);
		const presetClass =
			activePreset && activePreset !== blockDefaultPreset(name) ? fxbPresetClassName(activePreset) : '';

		if (!presetClass) {
			return <BlockListBlock {...props} />;
		}

		const className = props.className ? `${props.className} ${presetClass}` : presetClass;

		return <BlockListBlock {...props} className={className} />;
	};
}, 'withBlockPresetClass');
addFilter('editor.BlockListBlock', 'flx/fxb-preset-class', withBlockPresetClass);

/**
 * Mirror the data-fxb-palette="<id>" attribute onto the block in the editor canvas, so a per-block palette
 * override previews live with the same switch-layer re-pointing the front end uses. Added via wrapperProps
 * so it lands on the same block wrapper the projector's `[data-fxb-palette]` selector targets.
 *
 */
const withBlockPaletteAttr = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { name, attributes } = props;

		if (!hasBlockSupport(name, 'fxbPalette')) {
			return <BlockListBlock {...props} />;
		}

		const id = sanitizeTokenIdentifier(get(attributes, 'fxbPalette', ''));

		if (!id) {
			return <BlockListBlock {...props} />;
		}

		const wrapperProps = { ...(props.wrapperProps || {}), 'data-fxb-palette': id };

		return <BlockListBlock {...props} wrapperProps={wrapperProps} />;
	};
}, 'withBlockPaletteAttr');
addFilter('editor.BlockListBlock', 'flx/fxb-palette-attr', withBlockPaletteAttr);

/**
 * Add the design-token preset picker and the per-block Color Palette override to the inspector of any block
 * that opts into `fxbPreset` or `fxbPalette` support. Selecting a preset writes the fxbPreset attribute, which
 * the save/preview filters turn into the fxb-preset--<slug> class the projector's scoped CSS hooks; an empty
 * preset means the block follows its `$default` preset. Selecting a palette writes the fxbPalette attribute,
 * which the save/preview filters turn into the data-fxb-palette switch the projector's `[data-fxb-palette]`
 * layer re-skins.
 *
 * EVERY preset-capable block gets the same control — `PresetButton`, the dropdown the Button has always
 * used, rendered at the top of the inspector. One preset UI across all blocks was the point: a block's
 * presets are the same concept whichever block it is, so a second presentation would be a second thing for a
 * site owner to learn. `PresetButton` also carries the affordances the picker it replaced did not — the
 * current preset's name with its edited state, per-control edit highlighting, reset-to-preset, and "Save As a
 * New Preset" — and it renders the palette dropdown itself, directly below the preset row, which is why the
 * palette is surfaced here only for a block that gets no preset control.
 *
 * The controls still gate independently on their own block support: the preset row needs `fxbPreset` support
 * and at least one preset in the active library, the palette dropdown needs `fxbPalette` support and at least
 * two palettes.
 *
 * A block whose `fxbPreset` support requests `inlinePicker` renders `PresetButton` itself, from inside its own
 * inspector layout, so this filter renders neither control for it.
 *
 * Neither control renders in the Flux panel, which stores no preset or palette pick.
 *
 */
const withPresetPicker = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, attributes, setAttributes, isSelected } = props;
		const isSiteStylesPanel = useIsSiteStylesPanel();

		const hasPreset = hasBlockSupport(name, 'fxbPreset');
		const hasPalette = hasBlockSupport(name, 'fxbPalette');

		if (!hasPreset && !hasPalette) {
			return <BlockEdit {...props} />;
		}

		const library = activeLibrary();
		const control = designTokenInspectorControl({
			hasPreset,
			hasPalette,
			inlinePicker: hasPreset && Boolean(getBlockSupport(name, 'fxbPreset')?.inlinePicker),
			presetCount: blockPresets(name, library).length,
			paletteCount: selectablePalettes().length,
			isSiteStylesPanel,
		});

		if (control === null) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				{/*
				 * Both controls surface at the top level of the inspector — a bare InspectorControls fill, so they
				 * sit above the block's own panels rather than buried in a lower one, matching where the Button
				 * has always shown its preset row. Rendered before BlockEdit so the fill registers ahead of the
				 * block's own inspector fills.
				 */}
				{isSelected && (
					<InspectorControls>
						{control === 'preset' ? (
							<PresetButton
								blockName={name}
								library={library}
								attributes={attributes}
								setAttributes={setAttributes}
							/>
						) : (
							<PalettePicker
								value={get(attributes, 'fxbPalette', '')}
								onChange={(value) => setAttributes({ fxbPalette: value })}
							/>
						)}
					</InspectorControls>
				)}
				<BlockEdit {...props} />
			</>
		);
	};
}, 'withPresetPicker');
addFilter('editor.BlockEdit', 'flx/fxb-preset-picker', withPresetPicker);

const flxbkHeaderTemplatePartNotice = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const isHeaderTemplate = select('core/editor').getEditedPostAttribute('area') === 'header';
		const blocks = select('core/block-editor').getBlocks();
		const firstBlock = get(blocks, ['0', 'clientId']);
		const [tmpHideNotice, setTmpHideNotice] = useState(false);

		const [isOpen, setOpen] = useState(false);
		const closeModal = () => setOpen(false);

		const showNotice = isHeaderTemplate && props.clientId === firstBlock && !blockExists(blocks, 'flx/header');
		const { getEntityRecord } = select('core');
		const { saveEntityRecord } = useDispatch('core');
		const { removeBlocks, insertBlock } = useDispatch('core/block-editor');

		const siteSettings = getEntityRecord('root', 'site');
		const isNoticeDismissed = get(siteSettings, 'flxbk_header_notice_dismissed', false);

		const deleteBlocksAndInsert = () => {
			removeBlocks(blocks.map((block) => block.clientId));
			insertBlock(createBlock('flx/header', {}));
			saveEntityRecord('root', 'site', {
				flxbk_header_notice_dismissed: true,
			});
			setTmpHideNotice(true);
			closeModal();
		};
		return (
			<>
				{isOpen && (
					<Modal title={__('Replace header', 'flx-blocks')} onRequestClose={closeModal}>
						<p>{__('This will replace your header with a Flux Header block.', 'flx-blocks')}</p>
						<div style={{ marginBottom: '15px', display: 'flex', justifyContent: 'space-between' }}>
							<Button isPrimary={true} onClick={deleteBlocksAndInsert}>
								{__('Replace', 'flx-blocks')}
							</Button>
							<Button isSecondary={true} onClick={closeModal}>
								{__('Cancel', 'flx-blocks')}
							</Button>
						</div>
					</Modal>
				)}
				{showNotice && !tmpHideNotice && !isNoticeDismissed && (
					<div
						style={{
							height: '30px',
							padding: '15px',
							backgroundColor: '#fef8ef',
							borderBottom: '1px solid rgb(155, 155, 155, 0.6)',
							borderLeft: '4px solid #f1b849',
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							marginBottom: '5px',
						}}
					>
						{__('Try the Flux Header block!', 'flx-blocks')}
						<div>
							<Button
								isPrimary={true}
								style={{ marginRight: '15px' }}
								onClick={() => {
									setOpen(true);
								}}
							>
								{__('Replace with Flux Header', 'flx-blocks')}
							</Button>
							<Button
								isSecondary={true}
								onClick={() => {
									setTmpHideNotice(true);
									saveEntityRecord('root', 'site', {
										flxbk_header_notice_dismissed: true,
									});
								}}
							>
								{__('Dismiss', 'flx-blocks')}
							</Button>
						</div>
					</div>
				)}
				<BlockEdit {...props} />
			</>
		);
	};
}, 'withTemplatePartNotice');

// In the Flux theme's FSE mode the header part is the theme's own header, which Replace would empty.
if (!window.wpWidgets && !isFseMode()) {
	addFilter('editor.BlockEdit', 'flx-blocks/with-template-part-notice', flxbkHeaderTemplatePartNotice);
}
