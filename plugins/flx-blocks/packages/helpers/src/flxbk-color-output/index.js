/**
 * function to return string with var if needed.
 * @param {string} string the word string.
 * @returns {string} with var if needed.
 */
/* global flxbk_params */
import hexToRGBA from '../hex-to-rgba';
import { filterColorValue } from '../apply-output-filters';

// eslint-disable-next-line camelcase
export default function FlxbkColorOutput( string, opacity = null ) {
	return filterColorValue( string, opacity, () => {
		let output = string;
		if ( output && output.startsWith( 'palette' ) ) {
			output = 'var(--global-' + output + ')';
		} else if ( opacity !== null && ! isNaN( opacity ) && 1 !== Number( opacity ) && undefined !== output && '' !== output ) {
			output = hexToRGBA( output, opacity );
		}
		return output;
	} );
}
