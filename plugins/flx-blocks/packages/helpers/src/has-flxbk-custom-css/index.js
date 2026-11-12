export default function hasFlxbkCustomCSS( flxbkBlockCSS ) {
		const isValidCSSRule = flxbkBlockCSS && /^\s*[^{]+\s*\{\s*[^\s}]+\s*[:;][^}]*\}$/.test(flxbkBlockCSS);
		const globalSettings = flxbk_params.globalSettings
			? JSON.parse(flxbk_params.globalSettings)
			: {};
		const showCSSIndicator =
			globalSettings.enable_custom_css_indicator !== undefined
				? globalSettings.enable_custom_css_indicator
				: false;

		return !!(isValidCSSRule && showCSSIndicator);
}
