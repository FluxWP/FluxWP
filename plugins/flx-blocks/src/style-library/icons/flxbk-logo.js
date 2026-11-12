/**
 * WordPress dependencies
 */
import { SVG, Rect, G, Defs, LinearGradient, Stop } from '@wordpress/primitives';

/**
 * The FluxWP mark (same as the FluxWP app icon), shaped like a `@wordpress/icons` glyph so it
 * renders through `Icon`. Colours are fixed: the logo keeps them wherever it sits.
 */
export const flxbkLogo = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
		<Defs>
			<LinearGradient id="flxbk-logo-g" x1="0" y1="0" x2="1" y2="1">
				<Stop offset="0" stopColor="#6366F1" />
				<Stop offset=".55" stopColor="#3730A3" />
				<Stop offset="1" stopColor="#1E1B4B" />
			</LinearGradient>
		</Defs>
		<Rect width="256" height="256" rx="60" fill="url(#flxbk-logo-g)" />
		<Rect x="1.5" y="1.5" width="253" height="253" rx="58.5" fill="none" stroke="#fff" strokeOpacity=".22" strokeWidth="3" />
		<G transform="translate(32 32) scale(3)">
			<Rect x="9" y="9" width="13" height="13" rx="3.5" fill="#FFFFFF" /><Rect x="25.5" y="9" width="13" height="13" rx="3.5" fill="#FFFFFF" /><Rect x="9" y="25.5" width="13" height="13" rx="3.5" fill="#FFFFFF" /><Rect x="25.5" y="25.5" width="13" height="13" rx="3.5" fill="#FFFFFF" /><Rect x="9" y="42" width="13" height="13" rx="3.5" fill="#FFFFFF" /><Rect x="44.2" y="6.8" width="13" height="13" rx="3.5" fill="#F59E0B" />
		</G>
	</SVG>
);
