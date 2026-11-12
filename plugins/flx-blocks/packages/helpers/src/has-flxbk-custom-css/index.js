// "selector { prop: value }" shape check without a backtracking regex.
function isCSSRule( css ) {
	if ( ! css || 'string' !== typeof css ) {
		return false;
	}
	const rule = css.trim();
	const open = rule.indexOf( '{' );
	if ( open < 1 || '}' !== rule[ rule.length - 1 ] || rule.indexOf( '}' ) !== rule.length - 1 ) {
		return false;
	}
	if ( '' === rule.slice( 0, open ).trim() ) {
		return false;
	}
	const body = rule.slice( open + 1, -1 ).trim();
	const sep = body.search( /[:;]/ );
	return sep > 0 && ! /[\s}]/.test( body.slice( 0, sep ).trim() ) && '' !== body.slice( 0, sep ).trim();
}

export default function hasFlxbkCustomCSS( flxbkBlockCSS ) {
		const isValidCSSRule = isCSSRule( flxbkBlockCSS );
		const globalSettings = flxbk_params.globalSettings
			? JSON.parse(flxbk_params.globalSettings)
			: {};
		const showCSSIndicator =
			globalSettings.enable_custom_css_indicator !== undefined
				? globalSettings.enable_custom_css_indicator
				: false;

		return !!(isValidCSSRule && showCSSIndicator);
}
