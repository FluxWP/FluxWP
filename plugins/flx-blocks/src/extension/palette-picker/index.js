/**
 * Per-block color-palette selector.
 *
 * Reads the active set's palettes from the server-localized `window.flxbkDesignTokensPalettes`
 * (`{ active, current, palettes: [ { id, label } ] }`) and lets a block override which palette its subtree
 * renders against, independent of the set's `$current`. The selection lives in the block's `fxbPalette`
 * string attribute; the Design Tokens projector emits a `[data-fxb-palette="<id>"]` switch layer the block's
 * `data-fxb-palette` wrapper hooks. An empty selection inherits the set `$current`.
 *
 * Presented as a pop-out that mirrors the design-token preset control (see PresetButton): an uppercase
 * label, a bordered toggle button showing the selected palette with a trailing icon, and a dropdown menu of
 * palettes with a check on the active one.
 */
import { get } from 'lodash';
import { Button, Dropdown, MenuGroup, MenuItem } from '@wordpress/components';
import { Icon, check, brush } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import './palette-picker.scss';

/**
 * The whole palette catalog the editor localizer prints, or an empty shape when the token registry is
 * inactive (no palettes offered).
 *
 *
 * @return {Object} The catalog ({ active, current, palettes }).
 */
function paletteCatalog() {
	return get(window, 'flxbkDesignTokensPalettes', {}) || {};
}

/**
 * The active set's palettes as `[ { id, label } ]`, or an empty array when none are offered.
 *
 *
 * @return {Array} The palettes.
 */
export function selectablePalettes() {
	const palettes = get(paletteCatalog(), 'palettes', []);

	return Array.isArray(palettes) ? palettes : [];
}

/**
 * The set's `$current` palette id, defaulting to "default".
 *
 *
 * @return {string} The current palette id.
 */
export function currentPalette() {
	return get(paletteCatalog(), 'current', 'default') || 'default';
}

/**
 * The per-block palette selector, rendered as a preset-style pop-out (a labeled toggle button that opens a
 * menu of palettes with a check on the selection). Renders nothing when the set offers fewer than two
 * palettes (there is nothing to switch between). Choosing an option calls onChange with the chosen palette
 * id (the caller writes it into the block's fxbPalette attribute); the first option inherits the set
 * `$current`.
 *
 * @param {Object}   props          The component props.
 * @param {string}   props.value    The currently selected palette id ('' inherits the set current).
 * @param {Function} props.onChange Called with the selected palette id.
 * @param {string}   [props.label]  The control label.
 *
 *
 * @return {Object|null} The selector element, or null when there is nothing to switch between.
 */
export function PalettePicker({ value, onChange, label }) {
	const palettes = selectablePalettes();

	if (palettes.length < 2) {
		return null;
	}

	const current = currentPalette();
	const options = [
		{ id: '', label: inheritLabel(current) },
		...palettes.map((palette) => ({ id: palette.id, label: palette.label })),
	];
	const selectedId = value || '';
	const selectedOption = options.find((option) => option.id === selectedId) || options[0];

	return (
		<>
			<span className="fxb-palette-picker__control-label">{label || __('Color Palette', 'flx-blocks')}</span>
			<div className="fxb-palette-picker__row">
				<Dropdown
					className="fxb-palette-picker__dropdown"
					contentClassName="fxb-palette-picker__menu"
					popoverProps={{ placement: 'left-start' }}
					renderToggle={({ isOpen, onToggle }) => (
						<Button className="fxb-palette-picker__button" aria-expanded={isOpen} onClick={onToggle}>
							<span className="fxb-palette-picker__label">{selectedOption.label}</span>
							<span className="fxb-palette-picker__icon">
								<Icon icon={brush} size={16} />
							</span>
						</Button>
					)}
					renderContent={({ onClose }) => (
						<MenuGroup label={__('Color Palettes', 'flx-blocks')}>
							{options.map((option) => {
								const isCurrent = option.id === selectedId;

								return (
									<MenuItem
										key={option.id || 'inherit'}
										role="menuitemradio"
										aria-checked={isCurrent}
										suffix={
											isCurrent ? (
												<Icon className="fxb-palette-picker__check" icon={check} />
											) : null
										}
										onClick={() => {
											onChange(option.id);
											onClose();
										}}
									>
										{option.label}
									</MenuItem>
								);
							})}
						</MenuGroup>
					)}
				/>
			</div>
		</>
	);
}

/**
 * The label for the "inherit the set current" option, naming the current palette so the default is legible.
 *
 * @param {string} current The set's current palette id.
 *
 *
 * @return {string} The option label.
 */
function inheritLabel(current) {
	const match = selectablePalettes().find((palette) => palette.id === current);
	const name = match ? match.label : current;

	return `${__('Inherit', 'flx-blocks')} (${name})`;
}
