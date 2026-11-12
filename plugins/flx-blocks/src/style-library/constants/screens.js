/**
 * The Style Library screen catalog: the fixed BASE STYLES entries, the sidebar section
 * definitions, and the extension-point names. Screen ids are stable — they are used in the URL
 * (`?fxb-screen=<id>`) and by the per-screen modules that claim them.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	advancedImage,
	advancedText,
	borderRadius,
	borderWidth,
	button,
	colorPalette,
	iconSizes,
	rowLayout,
	section,
	shadow,
	singleIcon,
	spacing,
	typography,
} from '../icons';

/**
 * The filter third parties use to register a screen component for their preset-bound block:
 * `addFilter( PRESET_SCREENS_FILTER, 'my-plugin/screens', ( screens ) => ( { ...screens,
 * 'my-vendor/my-block': MyScreen } ) )`. Keyed by block name, valued by component. Resolution runs
 * on every screen render (inside `resolveScreen`), so a listener added after the app has already
 * rendered a screen still takes effect on the next render or navigation.
 *
 */
export const PRESET_SCREENS_FILTER = 'flxbk.style_library.preset_screens';

/**
 * The fixed BASE STYLES nav entries, in design order. An entry with no component registered in
 * `StyleLibraryApp` falls back to `PlaceholderScreen`.
 *
 */
export const BASE_STYLES_SCREENS = [
	{ id: 'color-palette', label: __('Color Palette', 'flx-blocks'), icon: colorPalette },
	{ id: 'typography', label: __('Typography', 'flx-blocks'), icon: typography },
	{ id: 'border-radius', label: __('Border Radius', 'flx-blocks'), icon: borderRadius },
	{ id: 'border-width', label: __('Border Width', 'flx-blocks'), icon: borderWidth },
	{ id: 'spacing', label: __('Spacing', 'flx-blocks'), icon: spacing },
	{ id: 'icon-sizes', label: __('Icon Sizes', 'flx-blocks'), icon: iconSizes },
	{ id: 'shadow', label: __('Shadow', 'flx-blocks'), icon: shadow },
];

/**
 * The sidebar icon of each BLOCK PRESETS entry, keyed by block name. The entries themselves come
 * from the admin feed, which carries no icon; a block with no icon here renders its label alone.
 *
 */
export const PRESET_SCREEN_ICONS = {
	'flx/singlebtn': button,
	'flx/image': advancedImage,
	'flx/rowlayout': rowLayout,
	'flx/column': section,
	'flx/single-icon': singleIcon,
	'flx/advancedheading': advancedText,
};

/**
 * The screen the app falls back to when the route names no screen or an unknown one.
 *
 */
export const DEFAULT_SCREEN_ID = BASE_STYLES_SCREENS[0].id;
