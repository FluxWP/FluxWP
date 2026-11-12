import { FlxBlocksCSS, getPreviewSize, getSpacingOptionOutput } from '@flx-blocks/helpers';
import { FlxbkWebfontLoader } from '@flx-blocks/components';

export default function BackendStyles(props) {
	const { attributes, clientId, previewDevice } = props;

	const {
		uniqueID,
		showSiteTitle,
		showSiteTagline,
		layout,
		padding,
		tabletPadding,
		mobilePadding,
		paddingType,
		margin,
		tabletMargin,
		mobileMargin,
		marginType,
		textVerticalAlign,
		titleTypography,
		taglineTypography,
	} = attributes;

	const css = new FlxBlocksCSS();

	css.set_selector(`.fxb-identity${uniqueID}`);

	css.render_measure_output(padding, tabletPadding, mobilePadding, previewDevice, 'padding', paddingType);
	css.render_measure_output(margin, tabletMargin, mobileMargin, previewDevice, 'margin', marginType);

	if (
		layout === 'logo-left' ||
		layout === 'logo-right' ||
		layout === 'logo-right-stacked' ||
		layout === 'logo-left-stacked'
	) {
		css.set_selector(`.fxb-identity${uniqueID} .fxb-identity-layout-container`);

		css.add_property('align-items', textVerticalAlign);
	}

	css.set_selector(`.fxb-identity${uniqueID} .wp-block-site-title`);
	css.render_font(titleTypography ? titleTypography : [], previewDevice);

	css.set_selector(`.fxb-identity${uniqueID} .wp-block-site-tagline`);
	css.render_font(taglineTypography ? taglineTypography : [], previewDevice);

	const cssOutput = css.css_output();

	return (
		<>
			<style>{`${cssOutput}`}</style>
			{titleTypography?.[0]?.google && (
				<FlxbkWebfontLoader typography={titleTypography} clientId={clientId} id={'typography'} />
			)}
			{taglineTypography?.[0]?.google && (
				<FlxbkWebfontLoader typography={taglineTypography} clientId={clientId} id={'typography'} />
			)}
		</>
	);
}
