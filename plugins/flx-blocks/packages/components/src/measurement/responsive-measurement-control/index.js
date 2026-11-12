/**
 * Responsive Range Component
 *
 */

/**
 * Internal block libraries
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { map, isEqual } from 'lodash';
import MeasurementControls from '../measurement-control';
import { capitalizeFirstLetter } from '@flx-blocks/helpers';
import { undo, settings, link, linkOff } from '@wordpress/icons';
import { Dashicon, Button, ButtonGroup } from '@wordpress/components';
import {
	outlineTopIcon,
	outlineRightIcon,
	outlineBottomIcon,
	outlineLeftIcon,
	individualIcon,
	linkedIcon,
} from '@flx-blocks/icons';
import { controlActions } from '../../common/control-extensions';
/**
 * Build the Measure controls
 * @returns {object} Measure settings.
 */
export default function ResponsiveMeasurementControls({
	label,
	subLabel,
	onChange,
	onChangeTablet,
	onChangeMobile,
	mobileValue,
	tabletValue,
	value,
	onChangeControl = false,
	control = 'individual',
	step = 1,
	max = 100,
	min = 0,
	unit = '',
	onUnit,
	showUnit = false,
	units = ['px', 'em', 'rem'],
	allowEmpty = true,
	preset = '',
	isBorderRadius = false,
	firstIcon = outlineTopIcon,
	secondIcon = outlineRightIcon,
	thirdIcon = outlineBottomIcon,
	fourthIcon = outlineLeftIcon,
	linkIcon = link,
	unlinkIcon = linkOff,
	reset = true,
	context,
}) {
	const ref = useRef();
	const [localControl, setLocalControl] = useState(control);
	const realControl = onChangeControl ? control : localControl;
	const realSetOnControl = onChangeControl ? onChangeControl : setLocalControl;
	const zero = allowEmpty ? true : false;
	const [deviceType, setDeviceType] = useState('Desktop');
	const theDevice = useSelect((select) => {
		return select('flxblocks/data').getPreviewDeviceType();
	}, []);
	if (theDevice !== deviceType) {
		setDeviceType(theDevice);
	}
	const { setPreviewDeviceType } = useDispatch('flxblocks/data');
	const customSetPreviewDeviceType = (device) => {
		setPreviewDeviceType(capitalizeFirstLetter(device));
		setDeviceType(capitalizeFirstLetter(device));
	};
	const devices = [
		{
			name: 'Desktop',
			title: <Dashicon icon="desktop" />,
			itemClass: 'fxb-desk-tab',
		},
		{
			name: 'Tablet',
			title: <Dashicon icon="tablet" />,
			itemClass: 'fxb-tablet-tab',
		},
		{
			name: 'Mobile',
			key: 'mobile',
			title: <Dashicon icon="smartphone" />,
			itemClass: 'fxb-mobile-tab',
		},
	];
	let liveValue = value ? value : ['', '', '', ''];
	if (deviceType === 'Tablet') {
		liveValue = tabletValue ? tabletValue : ['', '', '', ''];
	} else if (deviceType === 'Mobile') {
		liveValue = mobileValue ? mobileValue : ['', '', '', ''];
	}
	// The write handler for whichever device is live, so a header action injected through the seam
	// targets the value the user is actually looking at.
	const activeOnChange =
		deviceType === 'Tablet' ? onChangeTablet : deviceType === 'Mobile' ? onChangeMobile : onChange;
	const onReset = () => {
		if (deviceType === 'Tablet') {
			onChangeTablet(['', '', '', '']);
		} else if (deviceType === 'Mobile') {
			onChangeMobile(['', '', '', '']);
		} else {
			onChange(['', '', '', '']);
		}
	};
	const output = {};
	output.Mobile = (
		<MeasurementControls
			key={2}
			className="measure-mobile-size"
			label={subLabel ? __('Mobile:', 'flx-blocks') + subLabel : undefined}
			measurement={mobileValue ? mobileValue : ['', '', '', '']}
			control={realControl}
			onChange={(size) => onChangeMobile(size)}
			onControl={(sizeControl) => realSetOnControl(sizeControl)}
			min={min}
			max={max}
			step={step}
			allowEmpty={zero}
			unit={unit}
			showUnit={true}
			units={[unit]}
			preset={preset}
			isBorderRadius={isBorderRadius}
			firstIcon={firstIcon}
			secondIcon={secondIcon}
			thirdIcon={thirdIcon}
			fourthIcon={fourthIcon}
			linkIcon={linkIcon}
			unlinkIcon={unlinkIcon}
			context={context}
		/>
	);
	output.Tablet = (
		<MeasurementControls
			key={1}
			className="measure-tablet-size"
			label={subLabel ? __('Tablet:', 'flx-blocks') + subLabel : undefined}
			measurement={tabletValue ? tabletValue : ['', '', '', '']}
			control={realControl}
			onChange={(size) => onChangeTablet(size)}
			onControl={(sizeControl) => realSetOnControl(sizeControl)}
			min={min}
			max={max}
			step={step}
			allowEmpty={zero}
			unit={unit}
			showUnit={true}
			units={[unit]}
			preset={preset}
			isBorderRadius={isBorderRadius}
			firstIcon={firstIcon}
			secondIcon={secondIcon}
			thirdIcon={thirdIcon}
			fourthIcon={fourthIcon}
			linkIcon={linkIcon}
			unlinkIcon={unlinkIcon}
			context={context}
		/>
	);
	output.Desktop = (
		<MeasurementControls
			key={0}
			className="measure-desktop-size"
			label={subLabel ? subLabel : undefined}
			measurement={value ? value : ['', '', '', '']}
			control={realControl}
			onChange={(size) => onChange(size)}
			onControl={(sizeControl) => realSetOnControl(sizeControl)}
			min={min}
			max={max}
			step={step}
			allowEmpty={zero}
			unit={unit}
			onUnit={onUnit ? onUnit : undefined}
			showUnit={showUnit}
			units={units}
			preset={preset}
			isBorderRadius={isBorderRadius}
			firstIcon={firstIcon}
			secondIcon={secondIcon}
			thirdIcon={thirdIcon}
			fourthIcon={fourthIcon}
			linkIcon={linkIcon}
			unlinkIcon={unlinkIcon}
			context={context}
		/>
	);
	return [
		onChange && onChangeTablet && onChangeMobile && (
			<div ref={ref} className={'components-base-control fxb-responsive-measure-control'}>
				<div className="flxbk-component__header flxbk-title-bar">
					{label && (
						<div className="flxbk-component__header__title flxbk-title-bar flxbk-measure-control__title">
							<label className="components-base-control__label">{label}</label>
							{reset && (
								<div className="title-reset-wrap">
									<Button
										className="is-reset is-single"
										label="reset"
										isSmall
										disabled={
											isEqual(['', '', '', ''], liveValue) ||
											isEqual(['', 'auto', '', 'auto'], liveValue)
												? true
												: false
										}
										icon={undo}
										onClick={() => onReset()}
									/>
								</div>
							)}
						</div>
					)}
					<ButtonGroup className="fxb-measure-responsive-options" aria-label={__('Device', 'flx-blocks')}>
						{map(devices, ({ name, key, title, itemClass }) => (
							<Button
								key={key}
								className={`fxb-responsive-btn ${itemClass}${name === deviceType ? ' is-active' : ''}`}
								isSmall
								aria-pressed={deviceType === name}
								onClick={() => customSetPreviewDeviceType(name)}
							>
								{title}
							</Button>
						))}
					</ButtonGroup>
					{realSetOnControl && (
						<Button
							isSmall={true}
							className={'flxbk-radio-item flxbk-control-toggle radio-custom is-single only-icon'}
							label={
								realControl !== 'individual'
									? __('Individual', 'flx-blocks')
									: __('Linked', 'flx-blocks')
							}
							icon={realControl !== 'individual' ? linkIcon : unlinkIcon}
							onClick={() => realSetOnControl(realControl !== 'individual' ? 'individual' : 'linked')}
							isPressed={realControl !== 'individual' ? true : false}
							isTertiary={realControl !== 'individual' ? false : true}
						/>
					)}
					{controlActions({
						control: 'measure',
						index: null,
						value: liveValue,
						onChange: activeOnChange,
						context,
					})}
				</div>
				<div className="fxb-responsive-measure-control-inner">
					{output[deviceType] ? output[deviceType] : output.Desktop}
				</div>
			</div>
		),
	];
}
