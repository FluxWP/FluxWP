/**
 * WordPress dependencies
 */
import { SVG, Path, G, Defs, LinearGradient, Stop } from '@wordpress/primitives';

/**
 * The Flux Block Editor mark, shaped like a `@wordpress/icons` glyph so it renders through `Icon`.
 * The gradient is fixed: the logo keeps its colour wherever it sits.
 */
export const flxbkLogo = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
		<Defs>
			<LinearGradient id="flxbk-logo-g" x1="0" y1="0" x2="1" y2="1">
				<Stop offset="0" stopColor="#F472B6" />
				<Stop offset=".5" stopColor="#DB2777" />
				<Stop offset="1" stopColor="#831843" />
			</LinearGradient>
		</Defs>
		<Path d="M12 0C3.3 0 0 3.3 0 12s3.3 12 12 12 12-3.3 12-12S20.7 0 12 0Z" fill="url(#flxbk-logo-g)" />
		<G
			transform="translate(6 6) scale(0.5)"
			fill="none"
			stroke="#fff"
			strokeWidth="2.2"
			strokeLinecap="round"
			strokeLinejoin="round"
		>
			<Path d="M13 7 8.7 2.7a2.41 2.41 0 0 0-3.4 0L2.7 5.3a2.41 2.41 0 0 0 0 3.4L7 13" />
			<Path d="m8 6 2-2" />
			<Path d="m18 16 2-2" />
			<Path d="m17 11 4.3 4.3c.94.94.94 2.46 0 3.4l-2.6 2.6c-.94.94-2.46.94-3.4 0L11 17" />
			<Path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />
			<Path d="m15 5 4 4" />
		</G>
	</SVG>
);
