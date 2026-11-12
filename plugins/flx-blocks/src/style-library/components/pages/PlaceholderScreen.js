/**
 * The generic Style Library screen: a centered, muted "coming soon" panel rendered for any route
 * with no registered screen component.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './PlaceholderScreen.scss';

/**
 * Render the placeholder screen.
 *
 * @param {Object} props       The component props.
 * @param {string} props.label The active screen's nav label.
 *
 *
 * @return {JSX.Element} The placeholder screen.
 */
export function PlaceholderScreen({ label }) {
	return (
		<div className="flx-blocks-style-library__placeholder-screen">
			<h2 className="flx-blocks-style-library__placeholder-screen-title">{label}</h2>
			<p className="flx-blocks-style-library__placeholder-screen-copy">
				{__('This screen is coming soon.', 'flx-blocks')}
			</p>
		</div>
	);
}
