import { FlxBlocksCSS, getPreviewSize, FlxbkColorOutput } from '@flx-blocks/helpers';
import { memo } from '@wordpress/element';

function BackendStyles(props) {
	const { attributes, previewDevice } = props;

	const {
		uniqueID,
		backgroundColor,
		backgroundHoverColor,
		minHeight,
		tabletMinHeight,
		mobileMinHeight,
		minHeightType,
	} = attributes;

	const previewMinHeight = getPreviewSize(previewDevice, minHeight, tabletMinHeight, mobileMinHeight);

	const css = new FlxBlocksCSS();

	css.set_selector(`.fxb-table-container .fxb-table tr.fxb-table-row${uniqueID}`);
	if (previewMinHeight) {
		css.add_property('height', previewMinHeight + minHeightType);
	}
	if (backgroundColor !== '') {
		css.add_property('background-color', FlxbkColorOutput(backgroundColor));
	}

	css.set_selector(`.fxb-table-container .fxb-table tr.fxb-table-row${uniqueID}:hover`);
	if (backgroundHoverColor !== '') {
		css.add_property('background-color', FlxbkColorOutput(backgroundHoverColor));
	}

	const cssOutput = css.css_output();

	if (cssOutput === '') {
		return null;
	}

	return <style>{`${cssOutput}`}</style>;
}

export default memo(BackendStyles);
