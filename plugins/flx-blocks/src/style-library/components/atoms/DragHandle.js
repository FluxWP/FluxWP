/**
 * The drag-to-reorder grip: a 6-dot glyph inside a real `<button>` so keyboard reordering works
 * for free. Dnd-agnostic — the sortable wrapper hands it the dnd-kit listeners/attributes via
 * `handleProps` rather than this atom importing anything from `@dnd-kit` itself.
 */

/**
 * WordPress dependencies
 */
import { Icon, dragHandle } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './DragHandle.scss';

/**
 * Render the drag handle.
 *
 * @param {Object} props               The component props.
 * @param {Object} [props.handleProps] The dnd-kit listeners/attributes to spread onto the button.
 * @param {string} [props.label]       The button's accessible name. Defaults to "Drag to reorder";
 *                                     a list with more than one kind of draggable thing (the swatch
 *                                     grid's groups and swatches) names the thing so the two are
 *                                     told apart by a screen reader.
 *
 *
 * @return {JSX.Element} The drag handle button.
 */
export function DragHandle({ handleProps, label }) {
	return (
		<button
			type="button"
			className="flx-blocks-style-library__drag-handle"
			aria-label={label || __('Drag to reorder', 'flx-blocks')}
			{...handleProps}
		>
			<Icon icon={dragHandle} className="flx-blocks-style-library__drag-handle-icon" />
		</button>
	);
}
