/**
 * Radio Buttons control.
 *
 */
/**
 * Import Css
 */

import SmallResponsiveControl from '../small-responsive-control';
import FlxbkRadioButtons from '../common/radio-buttons';

/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function ResponsiveFlxbkRadioButtons({
	label,
	className,
	hideLabel = false,
	wrap = false,
	allowClear = false,
	help = '',
	onChange,
	onChangeTablet,
	onChangeMobile,
	value,
	mobileValue,
	tabletValue,
	options = [],
	tabletOptions = options,
	mobileOptions = tabletOptions,
	...props
}) {
	return (
		<SmallResponsiveControl
			label={label}
			desktopChildren={
				<FlxbkRadioButtons
					// className={'flxbk-row-layout-radio-btns fxb-acccordion-column-layout'}
					className={className}
					value={value}
					options={options}
					onChange={onChange}
					hideLabel={hideLabel}
					help={help}
					wrap={wrap}
					allowClear={allowClear}
				/>
			}
			tabletChildren={
				<FlxbkRadioButtons
					// className={'flxbk-row-layout-radio-btns fxb-acccordion-column-layout'}
					className={className}
					value={tabletValue}
					options={tabletOptions}
					onChange={onChangeTablet}
					hideLabel={hideLabel}
					help={help}
					wrap={wrap}
					allowClear={true}
				/>
			}
			mobileChildren={
				<FlxbkRadioButtons
					// className={'flxbk-row-layout-radio-btns fxb-acccordion-column-layout'}
					className={className}
					value={mobileValue}
					options={mobileOptions}
					onChange={onChangeMobile}
					hideLabel={hideLabel}
					help={help}
					wrap={wrap}
					allowClear={true}
				/>
			}
		/>
	);
}
