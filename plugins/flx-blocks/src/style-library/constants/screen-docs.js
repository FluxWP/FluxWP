/**
 * The per-screen helper copy and documentation links, keyed by screen id — the same id space
 * `helpers/screens.js` builds (`'color-palette'`, `'blocks/flx/singlebtn'`). One catalog
 * rather than a field on each screen's own config: the sentences are reviewed together as copy,
 * and the short URLs are swapped together when the documentation moves.
 *
 * A screen with no entry here renders no description — that is the supported state for a
 * third-party preset screen, which can register its own entry through the filter below.
 *
 * The URLs are short URLs on purpose: the documentation can be reorganized, split, or merged
 * into one article with anchors without any of these changing.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * The filter a third party (or a white-label build) uses to add, change, or remove screen helper
 * copy: `addFilter( SCREEN_DOCS_FILTER, 'my-plugin/docs', ( docs ) => ( { ...docs,
 * 'blocks/my-vendor/my-block': { description: '…', docUrl: 'https://…' } } ) )`. Resolution runs
 * on every lookup, so a listener added after first render still takes effect.
 *
 */
export const SCREEN_DOCS_FILTER = 'flxbk.style_library.screen_docs';

/**
 * The shipped catalog. Copy is authored by the documentation owner — do not reword it here.
 *
 */
export const SCREEN_DOCS = {
	'color-palette': {
		description: __(
			"Set the colors your site uses. Change a color here and it updates everywhere it's applied.",
			'flx-blocks'
		),
		docUrl: '',
	},
	typography: {
		description: __(
			'Set the text sizes your site uses, and pick the fonts you want close to hand.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'border-radius': {
		description: __(
			'Set the border radius options your site uses, so buttons, images and cards stay consistent.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'border-width': {
		description: __(
			'Set the border thickness options your site uses, so borders match across your blocks.',
			'flx-blocks'
		),
		docUrl: '',
	},
	spacing: {
		description: __(
			'Set the padding and margin steps your site uses, so gaps stay consistent from block to block.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'icon-sizes': {
		description: __(
			'Set the sizes your icons can use, so icons stay consistent wherever they appear.',
			'flx-blocks'
		),
		docUrl: '',
	},
	shadow: {
		description: __(
			'Set the shadow styles your site uses, so depth looks consistent across your blocks.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'blocks/flx/singlebtn': {
		description: __(
			'Save button styles as presets, so you can apply a whole look in one click instead of setting each option by hand.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'blocks/flx/image': {
		description: __(
			'Save image styles as presets, so rounding, shadows and spacing stay the same across your images.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'blocks/flx/rowlayout': {
		description: __(
			'Save row styles as presets, so the sections of a page share the same background and rounding.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'blocks/flx/column': {
		description: __(
			'Save section styles as presets, so the columns inside your rows share the same background and rounding.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'blocks/flx/single-icon': {
		description: __(
			'Save icon styles as presets, so icons keep the same size and color wherever you use them.',
			'flx-blocks'
		),
		docUrl: '',
	},
	'blocks/flx/advancedheading': {
		description: __(
			'Save text styles as presets, so headings and paragraphs read the same from page to page.',
			'flx-blocks'
		),
		docUrl: '',
	},
};
