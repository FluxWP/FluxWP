import { FlxBlocksCSS } from '@flx-blocks/helpers';
import { memo } from '@wordpress/element';

function BackendStyles({ attributes, previewDevice }) {
	const { uniqueID, padding, tabletPadding, mobilePadding, paddingType } = attributes;
	const css = new FlxBlocksCSS();

	css.set_selector(`tr th.fxb-table-data${uniqueID}, tr td.fxb-table-data${uniqueID}`);
	css.render_measure_output(padding, tabletPadding, mobilePadding, previewDevice, 'padding', paddingType);

	const cssOutput = css.css_output();

	if (cssOutput === '') {
		return null;
	}

	return <style>{`${cssOutput}`}</style>;
}

export default memo(BackendStyles);
