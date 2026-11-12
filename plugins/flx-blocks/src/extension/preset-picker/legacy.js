/**
 * The preset slug each retired "Button Inherit Styles" value maps to. Read when a block carries no
 * fxbPreset yet, so a saved button renders and lists the same preset the server resolves for it. Fill and
 * an empty value map to nothing: the default look.
 *
 */
export const LEGACY_PRESETS = Object.freeze({
	inherit: 'theme-base',
	'inherit-secondary': 'theme-secondary',
	outline: 'outline',
});

/**
 * The blocks whose retired style attribute maps to a preset.
 *
 */
export const LEGACY_PRESET_BLOCKS = Object.freeze(['flx/singlebtn']);
