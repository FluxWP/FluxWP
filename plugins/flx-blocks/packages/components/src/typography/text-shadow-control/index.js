/**
 * BoxShadow Component
 *
 */

/**
 * Import Externals
 */
import PopColorControl from '../../pop-color-control';

/**
 * Internal block libraries
 */
import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
/**
 * Build the BoxShadow controls
 * @returns {object} BoxShadow settings.
 */
const TextShadowControl = ({
	label,
	enable = true,
	color,
	colorDefault,
	blur,
	hOffset,
	vOffset,
	onColorChange,
	onBlurChange,
	onHOffsetChange,
	onVOffsetChange,
	onEnableChange,
}) => (
	<div className="fxb-text-shadow-container components-base-control">
		{label && (
			<div className="fxt-box-shadow-label">
				<h2 className="fxt-beside-color-label">{label}</h2>
				{onEnableChange && <ToggleControl checked={enable} onChange={(value) => onEnableChange(value)} />}
			</div>
		)}
		{enable && (
			<div className="fxt-inner-sub-section">
				<div className="fxt-inner-sub-section-row">
					<div className="fxt-box-color-settings fxt-box-shadow-subset">
						<p className="fxt-box-shadow-title">{__('Color', 'flx-blocks')}</p>
						<PopColorControl
							value={color ? color : colorDefault}
							default={colorDefault}
							onChange={(value) => onColorChange(value)}
						/>
					</div>
					<div className="fxt-box-x-settings fxt-box-shadow-subset">
						<p className="fxt-box-shadow-title">{'X'}</p>
						<div className="components-base-control fxt-boxshadow-number-input">
							<div className="components-base-control__field">
								<input
									value={undefined !== hOffset ? hOffset : ''}
									onChange={(event) => onHOffsetChange(Number(event.target.value))}
									min={-200}
									max={200}
									step={1}
									type="number"
									className="components-text-control__input"
								/>
							</div>
						</div>
					</div>
					<div className="fxt-box-y-settings fxt-box-shadow-subset">
						<p className="fxt-box-shadow-title">{'Y'}</p>
						<div className="components-base-control fxt-boxshadow-number-input">
							<div className="components-base-control__field">
								<input
									value={undefined !== vOffset ? vOffset : ''}
									onChange={(event) => onVOffsetChange(Number(event.target.value))}
									min={-200}
									max={200}
									step={1}
									type="number"
									className="components-text-control__input"
								/>
							</div>
						</div>
					</div>
					<div className="fxt-box-blur-settings fxt-box-shadow-subset">
						<p className="fxt-box-shadow-title">{'Blur'}</p>
						<div className="components-base-control fxt-boxshadow-number-input">
							<div className="components-base-control__field">
								<input
									value={undefined !== blur ? blur : ''}
									onChange={(event) => onBlurChange(Number(event.target.value))}
									min={0}
									max={200}
									step={1}
									type="number"
									className="components-text-control__input"
								/>
							</div>
						</div>
					</div>
				</div>
			</div>
		)}
	</div>
);
export default TextShadowControl;
