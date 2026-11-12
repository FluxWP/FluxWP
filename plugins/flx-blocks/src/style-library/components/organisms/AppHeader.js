/**
 * The Style Library header bar: logo and title on the left, a slash separator, then a
 * library-selector slot and an actions slot. Presentational only: the slots are filled by the
 * library selector and the header actions (e.g. delete) elsewhere in the app.
 */

/**
 * WordPress dependencies
 */
import { Icon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SlashSeparator } from '../atoms/SlashSeparator';
import { flxbkLogo } from '../../icons';
import './AppHeader.scss';

/**
 * Render the app header bar.
 *
 * @param {Object}       props              The component props.
 * @param {?JSX.Element} props.librarySlot  The library selector, or null when empty.
 * @param {?JSX.Element} props.actionsSlot  The header actions (e.g. delete), or null when empty.
 *
 *
 * @return {JSX.Element} The header bar.
 */
export function AppHeader({ librarySlot, actionsSlot }) {
	return (
		<div className="flx-blocks-style-library__header-bar">
			<Icon className="flx-blocks-style-library__logo" icon={flxbkLogo} size={48} />
			<h1 className="flx-blocks-style-library__title">{__('Style Library', 'flx-blocks')}</h1>
			<SlashSeparator className="flx-blocks-style-library__header-separator" />
			<div className="flx-blocks-style-library__header-library">{librarySlot}</div>
			<div className="flx-blocks-style-library__header-actions">{actionsSlot}</div>
		</div>
	);
}
