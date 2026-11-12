/**
 * Wire design-token alias resolution into the `@flx-blocks/helpers` output-filter seam.
 *
 * The shared helper library is token-agnostic: `FlxbkColorOutput`, the border/dimension helpers and
 * `FlxBlocksCSS` run their value through `flxbk.helpers.colorValue` / `flxbk.helpers.dimensionValue`
 * filters. Here Flux Block Editor registers a listener on both that resolves a `{dot.alias}` to its
 * `var(--fxb-token--<id>)` reference and passes every other value through untouched — so the token
 * knowledge lives only in this plugin, and the change is strictly additive.
 */
import { addFilter, removeFilter } from '@wordpress/hooks';
import { isTokenAlias, pathOfAlias, resolveTokenAlias } from './alias';
import { isBackedToken } from './backed-tokens';

const NAMESPACE = 'flx-blocks/token-alias';
const HOOKS = ['flxbk.helpers.colorValue', 'flxbk.helpers.dimensionValue'];

/**
 * Resolve a helper value: a design-token alias backed by the active library becomes its CSS var, and
 * anything else is unchanged. A syntactically-valid but unbacked alias — a token deleted after it was
 * saved into a post — is left as the raw value, so the editor emits no dead `var(--fxb-token--<id>)` and
 * the property falls back to whatever global CSS exists (matching the front-end renderer).
 *
 * @param {*} value The raw value the helper received.
 *
 *
 * @return {*} The resolved value.
 */
function resolveAlias(value) {
	if (!isTokenAlias(value)) {
		return value;
	}

	if (!isBackedToken(pathOfAlias(value))) {
		return value;
	}

	return resolveTokenAlias(value);
}

/**
 * Register the alias-resolution listeners on the helper output filters.
 *
 * Idempotent: it removes any prior listener under this namespace before adding, so it is safe to call
 * more than once (e.g. editor init plus a test's setup).
 *
 * @return {void}
 */
export function registerTokenAliasFilters() {
	HOOKS.forEach((hook) => {
		removeFilter(hook, NAMESPACE);
		addFilter(hook, NAMESPACE, resolveAlias);
	});
}
