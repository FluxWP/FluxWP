/**
 * function to return string with var if needed.
 * @param {string} string the word string.
 * @returns {string} with var if needed.
 */
/* global flxbk_params */
import hexToRGBA from '../hex-to-rgba';

// eslint-disable-next-line camelcase
export default function FlxbkColorOutput( string, opacity = null ) {
	if ( string && string.startsWith( 'palette' ) ) {
		string = 'var(--global-' + string + ')';
	} else if ( opacity !== null && ! isNaN( opacity ) && 1 !== Number( opacity ) && undefined !== string && '' !== string ) {
		string = hexToRGBA( string, opacity );
	}
	return string;
}
